<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Actions\GrantConsoleAccessAction;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraConsole\Support\ConsoleAddress;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraSupport\Tenancy\TenantSchema;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Models\User;

#[Description('Create a console user, issue a new password to an existing one, or revoke console access')]
#[Signature('vendra-console:user
        {--username= : Username for a new console user; prompts when omitted}
        {--email= : Email address for the console user; defaults to console@<app host>}
        {--password= : Password to set; a strong one is generated when omitted}
        {--revoke : Revoke console access from the user given by --email}')]
final class ConsoleUserCommand extends Command
{
    public function __construct(
        private readonly CreateConsoleUserAction $createConsoleUserAction,
        private readonly GrantConsoleAccessAction $grantConsoleAccessAction,
        private readonly RevokeConsoleUserAction $revokeConsoleUserAction,
        private readonly UpdateUserPasswordAction $updateUserPasswordAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $emailOption = $this->option('email');
        $emailOption = is_string($emailOption) && mb_trim($emailOption) !== '' ? Str::lower(mb_trim($emailOption)) : null;

        if ($this->option('revoke') === true) {
            return $this->revoke($emailOption);
        }

        $email = $emailOption ?? ConsoleAddress::defaultEmail();

        $passwordOption = $this->option('password');
        $passwordOption = is_string($passwordOption) && $passwordOption !== '' ? $passwordOption : null;

        $validator = Validator::make(
            ['email' => $email, 'password' => $passwordOption],
            ['email' => ['required', 'email'], 'password' => ['nullable', 'string', Password::default()]],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = $passwordOption ?? Str::password(32, symbols: false);

        $user = $this->findPlatformUser($email);

        if ($user === null) {
            $username = $this->option('username');

            if ($username === null && $this->input->isInteractive()) {
                $username = $this->ask('Username');
            }

            $username = is_string($username) ? mb_trim($username) : null;
            $validator = Validator::make(
                ['username' => $username],
                ['username' => ['required', 'string', 'max:255']],
                ['username.required' => 'A username is required to create a console user. Use --username.'],
            );

            if ($validator->fails()) {
                $this->components->error($validator->errors()->first());

                return self::FAILURE;
            }

            try {
                $user = $this->createConsoleUserAction->execute((string) $username, $email, $password);
            } catch (UniqueConstraintViolationException) {
                $this->components->error('The username or email is already taken. Choose another.');

                return self::FAILURE;
            }

            ConsoleCredentials::report($this, 'Console user created.', $user->email, $password);

            return self::SUCCESS;
        }

        $hasConsoleAccess = Console::query()->active()->forUser($user)->exists();

        if (! $this->confirmChangesToExistingUser($email, $hasConsoleAccess, $passwordOption !== null)) {
            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($user, $password): User {
            $this->grantConsoleAccessAction->execute($user);

            return $this->updateUserPasswordAction->execute($user, $password);
        });

        ConsoleCredentials::report(
            $this,
            $hasConsoleAccess ? 'Console user password updated.' : 'Console access granted and password updated.',
            $user->email,
            $password,
        );

        return self::SUCCESS;
    }

    /**
     * Confirm before granting access to, or generating a password for, an existing user.
     *
     * An explicit `--password` counts as confirmation, so scripts are not prompted.
     */
    private function confirmChangesToExistingUser(string $email, bool $hasConsoleAccess, bool $passwordGiven): bool
    {
        if (! $hasConsoleAccess) {
            if ($this->confirm("[{$email}] is an existing user without console access. Grant console access and issue a new password?")) {
                return true;
            }

            $this->components->error('No console access was granted.');

            return false;
        }

        if ($passwordGiven || $this->confirm("[{$email}] is already a console user. Issue a new password?")) {
            return true;
        }

        $this->components->error('The password was not changed.');

        return false;
    }

    private function revoke(?string $email): int
    {
        if ($email === null) {
            $this->components->error('The --revoke option requires --email.');

            return self::FAILURE;
        }

        $user = $this->findPlatformUser($email);

        if ($user === null) {
            $this->components->error("No platform user has the email [{$email}].");

            return self::FAILURE;
        }

        try {
            $revoked = $this->revokeConsoleUserAction->execute($user);
        } catch (LastConsoleUserException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($revoked
            ? "Console access revoked from [{$user->email}]."
            : "[{$user->email}] has no console access.");

        return self::SUCCESS;
    }

    private function findPlatformUser(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->when(TenantSchema::enabled(), fn (Builder $query): Builder => $query->whereNull(TenantSchema::column()))
            ->first();
    }
}

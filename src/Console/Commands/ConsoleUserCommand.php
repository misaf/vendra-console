<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Actions\GrantConsoleAccessAction;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraConsole\Support\ConsoleAddress;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Models\User;
use Misaf\VendraUser\Support\UserRules;

#[Description('Create a console user, issue a new password to an existing one, or revoke console access')]
#[Signature('vendra-console:user
        {--username= : Username for a new console user; prompts when omitted. Identifies the user with --revoke}
        {--email= : Email address for the console user; defaults to console@<app host>}
        {--password= : Password to set; a strong one is generated when omitted}
        {--revoke : Revoke console access from the user given by --email or --username}
        {--force : Run without confirmation}')]
final class ConsoleUserCommand extends Command
{
    public function handle(): int
    {
        if ($this->option('revoke') === true) {
            return $this->revoke();
        }

        $email = $this->resolveEmail();

        if ($email === null) {
            return self::FAILURE;
        }

        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $user = User::query()->tenantless()->where('email', $email)->first();

        return $user === null
            ? $this->createUser($email, $password)
            : $this->updateExistingUser($user, $password);
    }

    private function createUser(string $email, string $password): int
    {
        $username = $this->resolveUsername();

        if ($username === null) {
            return self::FAILURE;
        }

        try {
            $user = resolve(CreateConsoleUserAction::class)->execute($username, $email, $password);
        } catch (UniqueConstraintViolationException) {
            $this->components->error('The username or email is already taken. Choose another.');

            return self::FAILURE;
        }

        ConsoleCredentials::report($this, 'Console user created.', $user->email, $password);

        return self::SUCCESS;
    }

    private function updateExistingUser(User $user, string $password): int
    {
        $hasConsoleAccess = Console::query()->active()->forUser($user)->exists();

        if (! $this->confirmChangesToExistingUser($user, $hasConsoleAccess)) {
            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($user, $password): User {
            resolve(GrantConsoleAccessAction::class)->execute($user);

            return resolve(UpdateUserPasswordAction::class)->execute($user, $password);
        });

        ConsoleCredentials::report(
            $this,
            $hasConsoleAccess ? 'Console user password updated.' : 'Console access granted and password updated.',
            $user->email,
            $password,
        );

        return self::SUCCESS;
    }

    private function revoke(): int
    {
        $email = $this->revokeEmail();
        $username = $this->revokeUsername();

        if ($email !== null && $username !== null) {
            $this->components->error('Pass either --email or --username to --revoke, not both.');

            return self::FAILURE;
        }

        if ($email === null && $username === null) {
            $this->components->error('The --revoke option requires --email or --username.');

            return self::FAILURE;
        }

        [$column, $identifier] = $email !== null ? ['email', $email] : ['username', $username];

        $user = User::query()->tenantless()->where($column, $identifier)->first();

        if ($user === null) {
            $this->components->error("No tenantless user has the {$column} [{$identifier}].");

            return self::FAILURE;
        }

        try {
            $revoked = resolve(RevokeConsoleUserAction::class)->execute($user);
        } catch (LastConsoleUserException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($revoked
            ? "Console access revoked from [{$user->email}]."
            : "[{$user->email}] has no console access.");

        return self::SUCCESS;
    }

    private function confirmChangesToExistingUser(User $user, bool $hasConsoleAccess): bool
    {
        if ($this->option('force') === true) {
            return true;
        }

        if (! $hasConsoleAccess) {
            if ($this->confirm("[{$user->email}] is an existing user without console access. Grant console access and issue a new password?")) {
                return true;
            }

            $this->components->error('No console access was granted.');

            return false;
        }

        if (is_string($this->option('password')) || $this->confirm("[{$user->email}] is already a console user. Issue a new password?")) {
            return true;
        }

        $this->components->error('The password was not changed.');

        return false;
    }

    private function resolveUsername(): ?string
    {
        $username = $this->option('username');

        if ($username === null && $this->input->isInteractive()) {
            $username = $this->ask('Username');
        }

        $username = is_string($username) ? mb_trim($username) : null;
        $validator = Validator::make(
            ['username' => $username],
            ['username' => ['bail', 'required', ...UserRules::username(), UserRules::unique('username')]],
            ['username.required' => 'A username is required to create a console user. Use --username.'],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return null;
        }

        return $username;
    }

    private function revokeUsername(): ?string
    {
        $username = $this->option('username');
        $username = is_string($username) ? mb_trim($username) : null;

        return $username === '' ? null : $username;
    }

    /**
     * Validate the format only, without the shared UserRules::email() checks.
     * The default address falls back to console@localhost when app.url has no
     * host, and the stricter rules reject that.
     */
    private function resolveEmail(): ?string
    {
        $email = $this->normalizedEmail() ?? ConsoleAddress::defaultEmail();
        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['bail', 'required', 'email']],
            ['email.required' => 'The --email option cannot be blank.'],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return null;
        }

        return $email;
    }

    private function revokeEmail(): ?string
    {
        $email = $this->normalizedEmail();

        return $email === '' ? null : $email;
    }

    private function normalizedEmail(): ?string
    {
        $email = $this->option('email');

        return is_string($email) ? Str::lower(mb_trim($email)) : null;
    }

    private function resolvePassword(): ?string
    {
        $password = $this->option('password');
        $password = is_string($password) ? $password : UserRules::generatePassword();
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', ...UserRules::password()]],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return null;
        }

        return $password;
    }
}

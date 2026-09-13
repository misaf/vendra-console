<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Actions\GrantConsoleAccessAction;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Models\User;
use Symfony\Component\Console\Formatter\OutputFormatter;

#[Description('Create a console user, issue a new password to an existing one, or revoke console access')]
#[Signature('console:user
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

        $email = $emailOption ?? $this->defaultEmail();

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $passwordOption = $this->option('password');
        $passwordOption = is_string($passwordOption) && $passwordOption !== '' ? $passwordOption : null;
        $password = $passwordOption ?? Str::password(32, symbols: false);

        $user = $this->findPlatformUser($email);

        if ($user === null) {
            $user = $this->createConsoleUserAction->execute($email, $password);

            return $this->reportPassword('Console user created.', $user, $password);
        }

        $hasConsoleAccess = ConsoleUser::query()->forUser($user)->exists();

        if (! $this->confirmChangesToExistingUser($email, $hasConsoleAccess, $passwordOption !== null)) {
            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($user, $password): User {
            $this->grantConsoleAccessAction->execute($user);

            return $this->updateUserPasswordAction->execute($user, $password);
        });

        return $this->reportPassword(
            $hasConsoleAccess ? 'Console user password updated.' : 'Console access granted and password updated.',
            $user,
            $password,
        );
    }

    /**
     * An existing user is never changed silently: granting console access to
     * a user who lacks it, or replacing a console user's password with a
     * generated one, both ask first. An explicit --password is taken as the
     * intent to reset, so scripts are not prompted.
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

    private function reportPassword(string $message, User $user, string $password): int
    {
        $this->components->info($message);
        $this->components->twoColumnDetail('URL', $this->consoleUrl());
        $this->components->twoColumnDetail('Email', $user->email);
        $this->components->twoColumnDetail('Password', OutputFormatter::escape($password));
        $this->newLine();
        $this->components->warn('This password is shown once. Change it after signing in, or run `php artisan console:user` to issue a new one.');

        return self::SUCCESS;
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
            ->whereNull('tenant_id')
            ->first();
    }

    /**
     * The console user's address follows the deployment's own host, the same
     * host the console panel is served under.
     */
    private function defaultEmail(): string
    {
        $host = (string) Uri::of(Config::string('app.url'))->host();

        return 'console@'.($host === '' ? 'localhost' : $host);
    }

    private function consoleUrl(): string
    {
        $appUrl = Uri::of(Config::string('app.url'));

        return sprintf('%s://console.%s', $appUrl->scheme() ?? 'https', $appUrl->host());
    }
}

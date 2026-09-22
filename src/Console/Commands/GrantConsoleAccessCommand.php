<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Misaf\VendraConsole\Actions\GrantConsoleAccessAction;
use Misaf\VendraConsole\Console\Commands\Concerns\IdentifiesConsoleUser;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Models\User;
use Misaf\VendraUser\Support\UserRules;

#[Description('Grant console access to an existing tenantless user')]
#[Signature('vendra-console:user-grant
        {--username= : Username of the user to grant console access to}
        {--email= : Email address of the user to grant console access to}
        {--password= : Password to set alongside the grant; the current one is kept when omitted}')]
final class GrantConsoleAccessCommand extends Command
{
    use IdentifiesConsoleUser;

    public function handle(): int
    {
        $email = $this->givenEmail();
        $username = $this->givenUsername();
        $password = $this->option('password');
        $password = is_string($password) ? $password : null;

        $validator = Validator::make(
            [
                // A supplied but blank option names nobody, so it cannot stand in for the other.
                'identifier' => $email ?? $username,
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ],
            [
                'identifier' => ['required'],
                'email' => [$email === null ? 'nullable' : 'required', ...UserRules::email()],
                'username' => ['bail', $username === null ? 'nullable' : 'required', ...UserRules::username()],
                'password' => [$password === null ? 'nullable' : 'required', ...UserRules::password()],
            ],
            [
                'identifier.required' => __('vendra-console::commands.grant_requires_identifier'),
                'email.required' => __('vendra-console::commands.grant_requires_identifier'),
                'username.required' => __('vendra-console::commands.grant_requires_identifier'),
            ],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        ['user' => $user, 'mismatched' => $mismatched] = $this->findIdentifiedUser($email, $username);

        if ($mismatched) {
            $this->components->error('The --email and --username options identify different users.');

            return self::FAILURE;
        }

        if ($user === null) {
            $this->components->error($email !== null
                ? "No tenantless user has the email [{$email}]."
                : "No tenantless user has the username [{$username}].");
            $this->line('  Use vendra-console:user-create to create one.');

            return self::FAILURE;
        }

        ['granted' => $granted, 'user' => $user] = DB::transaction(function () use ($user, $password): array {
            $granted = resolve(GrantConsoleAccessAction::class)->execute($user);

            if ($password !== null) {
                $user = resolve(UpdateUserPasswordAction::class)->execute($user, $password);
            }

            return ['granted' => $granted, 'user' => $user];
        });

        return $this->report($user, $granted, $password);
    }

    private function report(User $user, bool $granted, ?string $password): int
    {
        if ($password === null) {
            $this->components->info($granted
                ? "Console access granted to [{$user->email}]."
                : "[{$user->email}] already has console access.");

            return self::SUCCESS;
        }

        ConsoleCredentials::report(
            $this,
            $granted ? 'Console access granted and password updated.' : 'Console user password updated.',
            $user->email,
            $password,
        );

        return self::SUCCESS;
    }
}

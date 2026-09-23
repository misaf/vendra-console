<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Misaf\VendraConsole\Actions\RevokeConsoleUserAction;
use Misaf\VendraConsole\Console\Commands\Concerns\IdentifiesConsoleUser;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraUser\Support\UserRules;

#[Description('Revoke console access from a console user')]
#[Signature('vendra-console:user-revoke
        {--username= : Username of the user to revoke console access from}
        {--email= : Email address of the user to revoke console access from}')]
final class RevokeConsoleUserCommand extends Command
{
    use IdentifiesConsoleUser;

    public function handle(): int
    {
        $email = $this->givenEmail();
        $username = $this->givenUsername();

        $validator = Validator::make(
            [
                // A supplied but blank option names nobody, so it cannot stand in for the other.
                'identifier' => $email ?? $username,
                'email' => $email,
                'username' => $username,
            ],
            [
                'identifier' => ['required'],
                'email' => [$email === null ? 'nullable' : 'required', ...UserRules::email()],
                'username' => ['bail', $username === null ? 'nullable' : 'required', ...UserRules::username()],
            ],
            [
                'identifier.required' => __('vendra-console::commands.revoke_requires_identifier'),
                'email.required' => __('vendra-console::commands.revoke_requires_identifier'),
                'username.required' => __('vendra-console::commands.revoke_requires_identifier'),
            ],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        ['user' => $user, 'mismatched' => $mismatched, 'unmatched' => $unmatched] = $this->findIdentifiedUser($email, $username);

        if ($mismatched) {
            $this->components->error('The --email and --username options identify different users.');

            return self::FAILURE;
        }

        if ($user === null) {
            $this->components->error($unmatched === 'email'
                ? "No tenantless user has the email [{$email}]."
                : "No tenantless user has the username [{$username}].");

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
}

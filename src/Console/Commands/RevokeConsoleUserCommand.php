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
use Misaf\VendraUser\Models\User;
use Misaf\VendraUser\Support\UserRules;

#[Description('Revoke console access from a console user')]
#[Signature('vendra-console:user-revoke
        {--username= : Username of the user to revoke console access from}
        {--email= : Email address of the user to revoke console access from; searched for when neither identifier is given}')]
final class RevokeConsoleUserCommand extends Command
{
    use IdentifiesConsoleUser;

    public function handle(): int
    {
        if (! $this->searchForMissingUser('Which console user should lose access?', withConsoleAccess: true, noUserMessage: 'No tenantless user has console access.')) {
            return self::FAILURE;
        }

        $email = $this->givenEmail();
        $username = $this->givenUsername();

        $validator = Validator::make(
            ['email' => $email, 'username' => $username],
            [
                // Keep required_without ahead of exclude_if, which stops the rest of a null field's rules.
                'email' => ['bail', 'required_without:username', 'exclude_if:email,null', 'filled', ...UserRules::email(), UserRules::exists('email')],
                'username' => ['bail', 'required_without:email', 'exclude_if:username,null', 'filled', ...UserRules::username(), UserRules::exists('username')],
            ],
            [
                'email.exists' => 'No tenantless user has the email [:input].',
                'username.exists' => 'No tenantless user has the username [:input].',
                'email.filled' => 'Revoking console access requires --email or --username.',
                'email.required_without' => 'Revoking console access requires --email or --username.',
                'username.filled' => 'Revoking console access requires --email or --username.',
                'username.required_without' => 'Revoking console access requires --email or --username.',
            ],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        // Each identifier exists on its own, so no user matching both means they name different users.
        $user = User::query()->tenantless()->identifiedBy($email, $username)->first();

        if ($user === null) {
            $this->components->error('The --email and --username options identify different users.');

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

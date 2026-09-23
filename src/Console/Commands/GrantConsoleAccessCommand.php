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
use Misaf\VendraConsole\Console\Commands\Concerns\ReadsGivenPassword;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Models\User;
use Misaf\VendraUser\Support\UserRules;

#[Description('Grant console access to an existing tenantless user')]
#[Signature('vendra-console:user-grant
        {--username= : Username of the user to grant console access to}
        {--email= : Email address of the user to grant console access to; searched for when neither identifier is given}
        {--password= : Password to set when access is granted, asked for without echo when given no value; the current one is kept when omitted}')]
final class GrantConsoleAccessCommand extends Command
{
    use IdentifiesConsoleUser;
    use ReadsGivenPassword;

    public function handle(): int
    {
        if (! $this->searchForMissingUser('Which user should get console access?', withConsoleAccess: false, noUserMessage: 'Every tenantless user already has console access. Use vendra-console:user-create to create one.')) {
            return self::FAILURE;
        }

        $email = $this->givenEmail();
        $username = $this->givenUsername();
        $password = $this->givenPassword();

        $validator = Validator::make(
            ['email' => $email, 'username' => $username, 'password' => $password],
            [
                // Keep required_without ahead of exclude_if, which stops the rest of a null field's rules.
                'email' => ['bail', 'required_without:username', 'exclude_if:email,null', 'filled', ...UserRules::email(), UserRules::exists('email')],
                'username' => ['bail', 'required_without:email', 'exclude_if:username,null', 'filled', ...UserRules::username(), UserRules::exists('username')],
                'password' => ['bail', 'exclude_if:password,null', 'filled', ...UserRules::password()],
            ],
            [
                'email.exists' => 'No tenantless user has the email [:input]. Use vendra-console:user-create to create one.',
                'username.exists' => 'No tenantless user has the username [:input]. Use vendra-console:user-create to create one.',
                'email.filled' => 'Granting console access requires --email or --username.',
                'email.required_without' => 'Granting console access requires --email or --username.',
                'username.filled' => 'Granting console access requires --email or --username.',
                'username.required_without' => 'Granting console access requires --email or --username.',
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

        ['granted' => $granted, 'user' => $user] = DB::transaction(function () use ($user, $password): array {
            $granted = resolve(GrantConsoleAccessAction::class)->execute($user);

            if ($granted && $password !== null) {
                $user = resolve(UpdateUserPasswordAction::class)->execute($user, $password);
            }

            return ['granted' => $granted, 'user' => $user];
        });

        if ($password === null) {
            $this->components->info($granted
                ? "Console access granted to [{$user->email}]."
                : "[{$user->email}] already has console access.");

            return self::SUCCESS;
        }

        if (! $granted) {
            $this->components->error("[{$user->email}] already has console access. The password was not changed. Use vendra-console:user-password to issue a new password.");

            return self::FAILURE;
        }

        ConsoleCredentials::report($this, 'Console access granted and password updated.', $user->email, $password);

        return self::SUCCESS;
    }
}

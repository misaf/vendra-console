<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Misaf\VendraConsole\Console\Commands\Concerns\IdentifiesConsoleUser;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Actions\ResetUserAppAuthenticationAction;
use Misaf\VendraUser\Models\User;
use Misaf\VendraUser\Support\UserRules;

use function Laravel\Prompts\confirm;

#[Description("Remove a console user's authenticator app so they set up two-factor again")]
#[Signature('vendra-console:user-two-factor-reset
        {--username= : Username of the console user}
        {--email= : Email address of the console user; searched for when neither identifier is given}
        {--force : Run without confirmation}')]
final class ResetConsoleTwoFactorCommand extends Command
{
    use IdentifiesConsoleUser;

    public function handle(): int
    {
        if (! $this->searchForMissingUser('Whose two-factor authentication should be reset?', withConsoleAccess: true, noUserMessage: 'No tenantless user has console access.')) {
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
                'email.filled' => 'Resetting two-factor authentication requires --email or --username.',
                'email.required_without' => 'Resetting two-factor authentication requires --email or --username.',
                'username.filled' => 'Resetting two-factor authentication requires --email or --username.',
                'username.required_without' => 'Resetting two-factor authentication requires --email or --username.',
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

        if (! Console::query()->active()->forUser($user)->exists()) {
            $this->components->error("[{$user->email}] has no console access. Use vendra-console:user-grant to grant it.");

            return self::FAILURE;
        }

        if (! $user->hasAppAuthentication()) {
            $this->components->info("[{$user->email}] has no two-factor authentication set up.");

            return self::SUCCESS;
        }

        $skipConfirmation = $this->option('force') === true;

        if (! $skipConfirmation && ! $this->input->isInteractive()) {
            $this->components->error('Two-factor authentication was not reset. Pass --force to reset it without a prompt.');

            return self::FAILURE;
        }

        if (! $skipConfirmation && ! confirm("Remove the authenticator app and recovery codes of [{$user->email}]?", default: false)) {
            $this->components->error('Two-factor authentication was not reset.');

            return self::FAILURE;
        }

        resolve(ResetUserAppAuthenticationAction::class)->execute($user);

        $this->components->info("Two-factor authentication reset for [{$user->email}]. They set it up again on their next sign-in.");

        return self::SUCCESS;
    }
}

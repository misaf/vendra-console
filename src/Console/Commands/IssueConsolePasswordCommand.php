<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Misaf\VendraConsole\Console\Commands\Concerns\IdentifiesConsoleUser;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraUser\Actions\UpdateUserPasswordAction;
use Misaf\VendraUser\Support\UserRules;

#[Description('Issue a new password to a console user')]
#[Signature('vendra-console:user-password
        {--username= : Username of the console user}
        {--email= : Email address of the console user; defaults to the vendra-console.default_email config value}
        {--password= : Password to set; a strong one is generated when omitted}
        {--force : Run without confirmation}')]
final class IssueConsolePasswordCommand extends Command
{
    use IdentifiesConsoleUser;

    public function handle(): int
    {
        $email = $this->givenEmail();
        $username = $this->givenUsername();
        $givenPassword = $this->option('password');
        $password = is_string($givenPassword) ? $givenPassword : UserRules::generatePassword();

        if ($email === null && $username === null) {
            $email = Config::string('vendra-console.default_email');
        }

        $validator = Validator::make(
            ['email' => $email, 'username' => $username, 'password' => $password],
            [
                'email' => [$email === null ? 'nullable' : 'required', ...UserRules::email()],
                'username' => ['bail', $username === null ? 'nullable' : 'required', ...UserRules::username()],
                'password' => ['required', ...UserRules::password()],
            ],
            ['email.required' => 'The --email option cannot be blank.'],
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
            $this->line('  Use vendra-console:user-create to create one.');

            return self::FAILURE;
        }

        if (! Console::query()->active()->forUser($user)->exists()) {
            $this->components->error("[{$user->email}] has no console access.");
            $this->line('  Use vendra-console:user-grant to grant it.');

            return self::FAILURE;
        }

        $skipConfirmation = $this->option('force') === true || is_string($givenPassword);

        if (! $skipConfirmation && ! $this->confirm("[{$user->email}] is already a console user. Issue a new password?")) {
            $this->components->error('The password was not changed.');

            return self::FAILURE;
        }

        $user = resolve(UpdateUserPasswordAction::class)->execute($user, $password);

        ConsoleCredentials::report($this, 'Console user password updated.', $user->email, $password);

        return self::SUCCESS;
    }
}

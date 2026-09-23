<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Console\Commands\Concerns\IdentifiesConsoleUser;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraUser\Support\UserRules;

#[Description('Create a console user')]
#[Signature('vendra-console:user-create
        {--username= : Username for the new console user}
        {--email= : Email address for the new console user}
        {--password= : Password to set; a strong one is generated when omitted}')]
final class CreateConsoleUserCommand extends Command
{
    use IdentifiesConsoleUser;

    public function handle(): int
    {
        $email = $this->givenEmail();
        $username = $this->givenUsername();
        $password = $this->option('password');
        $password = is_string($password) ? $password : UserRules::generatePassword();

        $validator = Validator::make(
            ['email' => $email, 'username' => $username, 'password' => $password],
            [
                'email' => ['required', ...UserRules::email()],
                'username' => ['bail', 'required', ...UserRules::username(), UserRules::unique('username')],
                'password' => ['required', ...UserRules::password()],
            ],
            [
                'email.required' => 'An email is required to create a console user. Use --email.',
                'username.required' => 'A username is required to create a console user. Use --username.',
            ],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        ['user' => $existingUser] = $this->findIdentifiedUser($email, null);

        if ($existingUser !== null) {
            $this->components->error("[{$existingUser->email}] already exists.");
            $this->line('  Use vendra-console:user-password to issue a new password.');
            $this->line('  Use vendra-console:user-grant to grant console access.');

            return self::FAILURE;
        }

        try {
            $user = resolve(CreateConsoleUserAction::class)->execute($username, $email, $password);
        } catch (UniqueConstraintViolationException) {
            $this->components->error("The username [{$username}] or the email [{$email}] is already taken. Choose another.");

            return self::FAILURE;
        }

        ConsoleCredentials::report($this, 'Console user created.', $user->email, $password);

        return self::SUCCESS;
    }
}

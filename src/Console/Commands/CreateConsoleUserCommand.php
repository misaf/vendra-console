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
use Misaf\VendraConsole\Console\Commands\Concerns\ReadsGivenPassword;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraUser\Support\PasswordGenerator;
use Misaf\VendraUser\Support\UserRules;

use function Laravel\Prompts\text;

#[Description('Create a console user')]
#[Signature('vendra-console:user-create
        {--username= : Username for the new console user, asked for when omitted}
        {--email= : Email address for the new console user, asked for when omitted}
        {--password= : Password to set, asked for without echo when given no value; a strong one is generated when omitted}')]
final class CreateConsoleUserCommand extends Command
{
    use IdentifiesConsoleUser;
    use ReadsGivenPassword;

    public function handle(): int
    {
        $this->askForMissingIdentifiers();

        $email = $this->givenEmail() ?? '';
        $username = $this->givenUsername() ?? '';
        $password = $this->givenPassword() ?? PasswordGenerator::generate();

        $validator = Validator::make(
            ['email' => $email, 'username' => $username, 'password' => $password],
            $this->rules(),
            $this->messages(),
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        try {
            $user = resolve(CreateConsoleUserAction::class)->execute($username, $email, $password);
        } catch (UniqueConstraintViolationException) {
            $this->components->error("The email [{$email}] or the username [{$username}] already belongs to a tenantless user. Use vendra-console:user-grant or vendra-console:user-password.");

            return self::FAILURE;
        }

        ConsoleCredentials::report($this, 'Console user created.', $user->email, $password);

        return self::SUCCESS;
    }

    private function askForMissingIdentifiers(): void
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        // Validate the normalized answer, since Laravel checks rule arrays against the raw one.
        if ($this->option('email') === null) {
            $this->input->setOption('email', text(
                label: 'Email',
                required: 'An email is required.',
                validate: fn (string $email): ?string => $this->validateAnswer('email', $this->normalizeEmail($email)),
            ));
        }

        if ($this->option('username') === null) {
            $this->input->setOption('username', text(
                label: 'Username',
                required: 'A username is required.',
                validate: fn (string $username): ?string => $this->validateAnswer('username', $this->normalizeUsername($username)),
            ));
        }
    }

    private function validateAnswer(string $attribute, string $answer): ?string
    {
        return $this->validatePrompt($answer, (object) ['rules' => [$attribute => $this->rules()[$attribute]], 'messages' => $this->messages()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'email' => ['required', ...UserRules::email(), UserRules::unique('email')],
            'username' => ['bail', 'required', ...UserRules::username(), UserRules::unique('username')],
            'password' => ['required', ...UserRules::password()],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'email.required' => 'An email is required to create a console user. Use --email.',
            'username.required' => 'A username is required to create a console user. Use --username.',
            'unique' => 'The :attribute [:input] already belongs to a tenantless user. Use vendra-console:user-grant or vendra-console:user-password.',
        ];
    }
}

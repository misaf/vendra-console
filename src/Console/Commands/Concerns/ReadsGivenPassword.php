<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Console\Commands\Concerns;

use Misaf\VendraUser\Support\UserRules;

use function Laravel\Prompts\password;

trait ReadsGivenPassword
{
    /**
     * Ask for the password without echoing it when --password is given without a value.
     *
     * This keeps it out of shell history and the process list. A rejected answer is asked
     * for again, and a run without interaction yields an empty string for the rules to reject.
     */
    private function givenPassword(): ?string
    {
        $password = $this->option('password');

        if (is_string($password)) {
            return $password;
        }

        if (! $this->input->hasParameterOption('--password', true)) {
            return null;
        }

        if (! $this->input->isInteractive()) {
            return '';
        }

        return password(label: 'Password', required: 'A password is required.', validate: ['password' => UserRules::password()]);
    }
}

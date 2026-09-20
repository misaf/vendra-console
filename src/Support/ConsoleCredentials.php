<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Support;

use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class ConsoleCredentials
{
    /**
     * Both the command and the seeder issue a generated password, so they
     * share one presentation instead of formatting it apart.
     */
    public static function report(Command $command, string $message, string $email, string $password): void
    {
        $command->info($message);
        $command->table(['Console URL', 'Email', 'Password'], [
            [ConsoleAddress::url(), $email, OutputFormatter::escape($password)],
        ]);
    }
}

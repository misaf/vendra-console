<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Support;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class ConsoleCredentials
{
    public static function report(Command $command, string $message, string $email, string $password): void
    {
        $command->info($message);
        $command->table(['Console URL', 'Email', 'Password'], [
            [Filament::getPanel('console')->getLoginUrl(), $email, OutputFormatter::escape($password)],
        ]);
    }
}

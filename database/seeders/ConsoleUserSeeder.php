<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Misaf\VendraConsole\Models\Console;
use RuntimeException;

final class ConsoleUserSeeder extends Seeder
{
    /**
     * @throws RuntimeException
     */
    public function run(): void
    {
        if (Console::query()->active()->exists()) {
            return;
        }

        throw_if($this->command->call('console:user') !== Command::SUCCESS, RuntimeException::class, 'No console user was seeded. Run `php artisan console:user --email=<address>` to create one.');
    }
}

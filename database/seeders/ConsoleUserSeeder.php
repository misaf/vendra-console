<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Misaf\VendraConsole\Models\ConsoleUser;
use RuntimeException;

final class ConsoleUserSeeder extends Seeder
{
    /**
     * @throws RuntimeException When `console:user` fails, e.g. the default email belongs to an existing user.
     */
    public function run(): void
    {
        if (ConsoleUser::query()->exists()) {
            return;
        }

        if ($this->command->call('console:user') !== Command::SUCCESS) {
            throw new RuntimeException('No console user was seeded. Run `php artisan console:user --email=<address>` to create one.');
        }
    }
}

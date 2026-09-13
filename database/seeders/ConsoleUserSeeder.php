<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Database\Seeder;
use Misaf\VendraConsole\Models\ConsoleUser;

final class ConsoleUserSeeder extends Seeder
{
    public function run(): void
    {
        if (ConsoleUser::query()->exists()) {
            return;
        }

        $this->command->call('console:user');
    }
}

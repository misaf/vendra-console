<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Models\ConsoleUser;

final class ConsoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = Config::string('console.operator.email');
        $password = Config::string('console.operator.password');

        if ($email === '' || $password === '') {
            return;
        }

        ConsoleUser::query()->firstOrCreate(
            ['email' => Str::lower(mb_trim($email))],
            [
                'email_verified_at' => Date::now(),
                'password' => $password,
            ],
        );
    }
}

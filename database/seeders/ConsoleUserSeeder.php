<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Models\ConsoleUser;

final class ConsoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = Config::string('console.operator.username');
        $email = Config::string('console.operator.email');
        $password = Config::string('console.operator.password');

        if ('' === $username || '' === $email || '' === $password) {
            return;
        }

        ConsoleUser::query()->firstOrCreate(
            ['email' => Str::lower(mb_trim($email))],
            [
                'username'          => $username,
                'email_verified_at' => Carbon::now(),
                'password'          => $password,
            ],
        );
    }
}

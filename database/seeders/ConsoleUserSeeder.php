<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Misaf\VendraUser\Models\User;

/**
 * Seeds the initial console user as a canonical user.
 *
 * A console user holds no tenant or reseller relationship: console access is
 * granted purely by the `console_users` row, which the console package's
 * panel-access resolver checks for the console panel.
 */
final class ConsoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = Config::string('console.user.email');
        $password = Config::string('console.user.password');

        if ($email === '' || $password === '') {
            return;
        }

        $email = Str::lower(mb_trim($email));

        $user = User::query()->firstOrCreate(
            ['email' => $email, 'tenant_id' => null],
            [
                'username' => self::usernameFor($email),
                'email_verified_at' => Date::now(),
                'password' => Hash::make($password),
            ],
        );

        DB::table('console_users')->updateOrInsert(
            ['user_id' => $user->getKey()],
            [
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ],
        );
    }

    private static function usernameFor(string $email): string
    {
        $candidate = Str::of(Str::before($email, '@'))
            ->slug('_')
            ->substr(0, 12)
            ->toString();

        if ($candidate === '') {
            $candidate = 'user';
        }

        return $candidate;
    }
}

<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraConsole\Support\ConsoleAddress;
use RuntimeException;

final class ConsoleSeeder extends Seeder
{
    public function __construct(private readonly CreateConsoleUserAction $createConsoleUserAction) {}

    /**
     * @throws RuntimeException
     */
    public function run(): void
    {
        if (Console::query()->active()->exists()) {
            return;
        }

        $email = ConsoleAddress::defaultEmail();
        $password = Str::password(32, symbols: false);

        try {
            $user = $this->createConsoleUserAction->execute($email, $password);
        } catch (UniqueConstraintViolationException $exception) {
            throw new RuntimeException("No console user was seeded. [{$email}] is already taken; run `php artisan vendra-console:user --email=<address>` to create one.", previous: $exception);
        }

        if (! isset($this->command)) {
            return;
        }

        $this->command->info('Console user created.');
        $this->command->line('URL: '.ConsoleAddress::url());
        $this->command->line("Email: {$user->email}");
        $this->command->line("Password: {$password}");
        $this->command->warn('This password is shown once. Change it after signing in, or run `php artisan vendra-console:user` to issue a new one.');
    }
}

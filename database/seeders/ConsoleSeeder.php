<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraUser\Support\UserRules;
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

        $email = Config::string('vendra-console.default_email');
        $password = UserRules::generatePassword();

        try {
            $user = $this->createConsoleUserAction->execute('console', $email, $password);
        } catch (UniqueConstraintViolationException $exception) {
            throw new RuntimeException("Console username or email [{$email}] is taken. Run `php artisan vendra-console:user-create`.", previous: $exception);
        }

        ConsoleCredentials::report($this->command, 'Console access details', $user->email, $password);
    }
}

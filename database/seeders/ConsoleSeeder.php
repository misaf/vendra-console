<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Misaf\VendraConsole\Actions\CreateConsoleUserAction;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraConsole\Support\ConsoleCredentials;
use Misaf\VendraUser\Support\PasswordGenerator;

final class ConsoleSeeder extends Seeder
{
    public function __construct(private readonly CreateConsoleUserAction $createConsoleUserAction) {}

    public function run(): void
    {
        if (Console::query()->active()->exists()) {
            return;
        }

        $email = Config::string('vendra-console.default_email');
        $password = PasswordGenerator::generate();

        try {
            $user = $this->createConsoleUserAction->execute('console', $email, $password);
        } catch (UniqueConstraintViolationException) {
            $this->command->error("The email [{$email}] or the username [console] already belongs to a tenantless user. Use vendra-console:user-grant to give that user console access, or vendra-console:user-create with a different email and username.");

            return;
        }

        ConsoleCredentials::report($this->command, 'Console access details', $user->email, $password);
    }
}

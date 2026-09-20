<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Actions;

use Illuminate\Support\Facades\DB;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Actions\CreateUserAction;
use Misaf\VendraUser\Models\User;

final readonly class CreateConsoleUserAction
{
    public function __construct(private CreateUserAction $createUserAction) {}

    public function execute(string $username, string $email, string $password): User
    {
        return DB::transaction(function () use ($username, $email, $password): User {
            $user = $this->createUserAction->execute(
                tenant: null,
                username: $username,
                email: $email,
                password: $password,
            );

            Console::query()->create(['user_id' => $user->getKey(), 'active' => true]);

            return $user;
        });
    }
}

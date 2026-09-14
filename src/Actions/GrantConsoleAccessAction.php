<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Actions;

use InvalidArgumentException;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Models\User;

final readonly class GrantConsoleAccessAction
{
    /**
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function execute(User $user): bool
    {
        if ($user->tenant_id !== null) {
            throw new InvalidArgumentException("User [{$user->id}] belongs to a tenant and cannot be a console user.");
        }

        return ConsoleUser::query()->createOrFirst(['user_id' => $user->getKey()])->wasRecentlyCreated;
    }
}

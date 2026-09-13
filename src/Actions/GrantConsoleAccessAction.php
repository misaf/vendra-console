<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Actions;

use InvalidArgumentException;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Models\User;

final readonly class GrantConsoleAccessAction
{
    /**
     * Give an existing platform-level user console access.
     *
     * @return bool Whether a grant was added; false when the user already held one.
     *
     * @throws InvalidArgumentException When the user belongs to a tenant.
     */
    public function execute(User $user): bool
    {
        if ($user->tenant_id !== null) {
            throw new InvalidArgumentException("User [{$user->id}] belongs to a tenant and cannot be a console user.");
        }

        return ConsoleUser::query()->firstOrCreate(['user_id' => $user->getKey()])->wasRecentlyCreated;
    }
}

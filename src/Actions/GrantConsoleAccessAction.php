<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

final readonly class GrantConsoleAccessAction
{
    /**
     * Returns false when the user already had active access.
     *
     * @throws InvalidArgumentException
     */
    public function execute(User $user): bool
    {
        if ($user->tenant_id !== null) {
            throw new InvalidArgumentException("User [{$user->id}] belongs to a tenant and cannot be a console user.");
        }

        return DB::transaction(function () use ($user): bool {
            $console = Console::query()->createOrFirst(['user_id' => $user->getKey()], ['active' => true]);

            if ($console->wasRecentlyCreated) {
                return true;
            }

            $lockedConsole = $console->refreshForUpdate();

            if ($lockedConsole->active) {
                return false;
            }

            $lockedConsole->forceFill(['active' => true])->save();

            return true;
        });
    }
}

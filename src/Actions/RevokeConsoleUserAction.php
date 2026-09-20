<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Actions;

use Illuminate\Support\Facades\DB;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Models\User;

final readonly class RevokeConsoleUserAction
{
    /**
     * Deactivate the user's console grant.
     *
     * The last-user guard counts only grants whose user still exists, so a
     * soft-deleted user can be revoked even as the final grant holder.
     *
     * @throws LastConsoleUserException
     */
    public function execute(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $activeConsoles = Console::query()->active()->lockForUpdate()->get();

            $console = $activeConsoles->firstWhere('user_id', $user->getKey());

            if ($console === null) {
                return false;
            }

            $liveConsoleUserIds = $activeConsoles->load('user')
                ->filter(fn (Console $activeConsole): bool => $activeConsole->user !== null)
                ->pluck('user_id');

            if ($liveConsoleUserIds->all() === [$user->getKey()]) {
                throw LastConsoleUserException::forUser($user->email);
            }

            $console->update(['active' => false]);

            return true;
        });
    }
}

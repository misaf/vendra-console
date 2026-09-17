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
     * Deactivates the user's console; the row stays so access can be granted again.
     *
     * @throws LastConsoleUserException
     */
    public function execute(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $activeConsoles = Console::query()->active()->whereHas('user')->lockForUpdate()->get();

            $console = $activeConsoles->firstWhere('user_id', $user->getKey());

            if ($console === null) {
                return false;
            }

            if ($activeConsoles->count() === 1) {
                throw LastConsoleUserException::forUser($user->email);
            }

            $console->forceFill(['active' => false])->save();

            return true;
        });
    }
}

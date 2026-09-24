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

            $liveConsoleUserIds = Console::query()->active()->whereHas('user')->pluck('user_id');

            if ($liveConsoleUserIds->containsOneItem() && $liveConsoleUserIds->contains($user->getKey())) {
                throw LastConsoleUserException::forUser($user->email);
            }

            $console->update(['active' => false]);

            return true;
        });
    }
}

<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Actions;

use Illuminate\Support\Facades\DB;
use Misaf\VendraConsole\Exceptions\LastConsoleUserException;
use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Models\User;

final readonly class RevokeConsoleUserAction
{
    /**
     * Remove the user's console grant while the canonical identity survives.
     * Every grant is locked so two concurrent revocations cannot leave the
     * console without a user.
     *
     * @return bool Whether a grant was removed; false when the user held none.
     *
     * @throws LastConsoleUserException
     */
    public function execute(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $grants = ConsoleUser::query()->lockForUpdate()->get();

            $grant = $grants->firstWhere('user_id', $user->getKey());

            if ($grant === null) {
                return false;
            }

            if ($grants->count() === 1) {
                throw LastConsoleUserException::forUser($user->email);
            }

            $grant->delete();

            return true;
        });
    }
}

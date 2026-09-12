<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Auth;

use Illuminate\Support\Facades\DB;
use Misaf\VendraUser\Contracts\PanelAccessResolver;
use Misaf\VendraUser\Models\User;

/**
 * Console panel authorization, owned by the console domain.
 *
 * Console user grants live in `console_users` and are written only from
 * here; presence of a row grants panel access, its absence revokes it
 * while leaving the canonical identity intact. A console user holds no
 * tenant or reseller relationship.
 */
final class ConsolePanelAccessResolver implements PanelAccessResolver
{
    public function panelId(): string
    {
        return 'console';
    }

    public function canAccess(User $user): bool
    {
        return DB::table('console_users')
            ->where('user_id', $user->getKey())
            ->exists();
    }
}

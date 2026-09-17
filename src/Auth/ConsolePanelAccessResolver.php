<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Auth;

use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Contracts\PanelAccessResolver;
use Misaf\VendraUser\Models\User;

/**
 * Console panel authorization, owned by the console domain.
 *
 * Each console user has one `Models\Console` row, written through
 * `Actions\CreateConsoleUserAction` and `Actions\GrantConsoleAccessAction`.
 * An active row grants panel access; `Actions\RevokeConsoleUserAction`
 * deactivates it while leaving the canonical identity intact. A console user
 * holds no tenant or reseller relationship.
 */
final class ConsolePanelAccessResolver implements PanelAccessResolver
{
    public function panelId(): string
    {
        return 'console';
    }

    public function canAccess(User $user): bool
    {
        return Console::query()->active()->forUser($user)->exists();
    }
}

<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Auth;

use Misaf\VendraConsole\Models\ConsoleUser;
use Misaf\VendraUser\Contracts\PanelAccessResolver;
use Misaf\VendraUser\Models\User;

/**
 * Console panel authorization, owned by the console domain.
 *
 * Console user grants are `Models\ConsoleUser` rows, written through
 * `Actions\CreateConsoleUserAction` and `Actions\GrantConsoleAccessAction`;
 * presence of a row grants panel access,
 * its absence revokes it while leaving the canonical identity intact. A
 * console user holds no tenant or reseller relationship.
 */
final class ConsolePanelAccessResolver implements PanelAccessResolver
{
    public function panelId(): string
    {
        return 'console';
    }

    public function canAccess(User $user): bool
    {
        return ConsoleUser::query()->forUser($user)->exists();
    }
}

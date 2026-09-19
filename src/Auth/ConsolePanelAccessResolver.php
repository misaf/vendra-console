<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Auth;

use Misaf\VendraConsole\Models\Console;
use Misaf\VendraUser\Contracts\PanelAccessResolver;
use Misaf\VendraUser\Models\User;

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

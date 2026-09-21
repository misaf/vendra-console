<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Filament\Resources\Resellers\Actions\Concerns;

use Misaf\VendraUser\Support\UserRules;

trait ValidatesResellerUser
{
    /**
     * @return list<mixed>
     */
    protected static function resellerUsernameRules(): array
    {
        return [...UserRules::username(), UserRules::unique('username')];
    }

    /**
     * @return list<mixed>
     */
    protected static function resellerEmailRules(?int $ignoreUserId = null): array
    {
        return [...UserRules::email(), UserRules::unique('email', ignoreUserId: $ignoreUserId)];
    }
}

<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Exceptions;

use LogicException;

final class LastConsoleUserException extends LogicException
{
    public static function forUser(string $email): self
    {
        return new self("[{$email}] is the last console user. Grant console access to another user before revoking it.");
    }
}

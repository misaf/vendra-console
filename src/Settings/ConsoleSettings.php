<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Settings;

use Spatie\LaravelSettings\Settings;

final class ConsoleSettings extends Settings
{
    public string $platform_name;

    public static function group(): string
    {
        return 'console';
    }

    /**
     * Use the platform repository, since these settings have no tenant.
     */
    public static function repository(): string
    {
        return 'global';
    }
}

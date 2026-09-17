<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * How the console presents the platform to its users.
 *
 * Platform-wide and read only by the console panel, so it is stored without a
 * tenant and lives in this package rather than in a layer below it.
 */
final class ConsoleSettings extends Settings
{
    /**
     * The brand name shown across the console panel.
     */
    public string $platform_name;

    public static function group(): string
    {
        return 'console';
    }

    /**
     * Platform settings carry no tenant, so they never use the default
     * store-scoped repository.
     */
    public static function repository(): string
    {
        return 'global';
    }
}

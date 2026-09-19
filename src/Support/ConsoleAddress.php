<?php

declare(strict_types=1);

namespace Misaf\VendraConsole\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Uri;

final class ConsoleAddress
{
    public static function domain(): string
    {
        return 'console.'.self::appHost();
    }

    public static function url(): string
    {
        return sprintf('%s://%s', Uri::of(Config::string('app.url'))->scheme() ?? 'https', self::domain());
    }

    public static function defaultEmail(): string
    {
        return 'console@'.self::appHost();
    }

    private static function appHost(): string
    {
        $host = (string) Uri::of(Config::string('app.url'))->host();

        return $host === '' ? 'localhost' : $host;
    }
}

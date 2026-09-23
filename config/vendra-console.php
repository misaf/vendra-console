<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Console Email
    |--------------------------------------------------------------------------
    |
    | The address given to the first console user on a fresh install, and the
    | one "vendra-console:user-password" falls back to when it is run without
    | "--email" or "--username". It is validated by the same shared user rules
    | every other address in the application passes, so it must be a valid
    | address: a dotless domain such as "console@localhost" is rejected.
    |
    */

    'default_email' => env('VENDRA_CONSOLE_DEFAULT_EMAIL', 'console@vendra.test'),

    /*
    |--------------------------------------------------------------------------
    | Console Domain
    |--------------------------------------------------------------------------
    |
    | The host the console panel is served on. It defaults to the "console."
    | subdomain of the host in "APP_URL", so a deployment that sets APP_URL
    | needs nothing further. Set this to serve the panel somewhere else.
    |
    */

    'domain' => env('VENDRA_CONSOLE_DOMAIN', 'console.'.(string) parse_url((string) env('APP_URL'), PHP_URL_HOST)),

];

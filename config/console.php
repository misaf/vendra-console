<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Console User Credentials
    |--------------------------------------------------------------------------
    |
    | The default credentials used to seed the initial console user. These
    | values are only used when the application is freshly installed or
    | when the console user does not yet exist in the database.
    |
    */

    'user' => [
        'email' => env('CONSOLE_USER_EMAIL', ''),
        'password' => env('CONSOLE_USER_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Platform Settings
    |--------------------------------------------------------------------------
    |
    | Deployment-level console settings. Everything here is scoped to the
    | console panel and fixed for the deployment; a rule the reseller or store
    | layer would have to honour cannot live here, because those packages sit
    | below the console and cannot read it. Platform rules a console user can
    | edit — store creation, for one — are settings rows instead, owned by the
    | layer that enforces them.
    |
    */

    'platform' => [
        'name' => env('CONSOLE_PLATFORM_NAME', 'Vendra Console'),
    ],

];

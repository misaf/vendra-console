<?php

declare(strict_types=1);

return [

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

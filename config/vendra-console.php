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

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Screenshot API key
    |--------------------------------------------------------------------------
    |
    | A shared secret that callers must send as a Bearer token to use the
    | screenshot endpoints:  Authorization: Bearer <SCREENSHOT_API_KEY>
    |
    | Generate a strong one with:  php artisan screenshot:key
    |
    | If this is left empty the screenshot endpoints are OPEN (no auth) so the
    | service works out of the box — set a key to lock it down.
    |
    */

    'api_key' => env('SCREENSHOT_API_KEY'),

];

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

    /*
    |--------------------------------------------------------------------------
    | Screenshot client (devdojo/screenshot-client)
    |--------------------------------------------------------------------------
    |
    | Settings for the bundled client that calls the screenshot service from
    | PHP (the screenshot() helper / Screenshot facade). `url` is the service
    | base URL, `disk` is where save() writes (null = filesystems.default), and
    | `timeout` is the HTTP timeout in seconds. The client reuses `api_key`
    | above as the Bearer token it sends.
    |
    */

    'url' => env('SCREENSHOT_URL', 'https://screenshot.laravel.cloud'),

    // Defaults to the "public" disk so saved screenshots are web-accessible
    // (run `php artisan storage:link` once). Override with SCREENSHOT_DISK.
    'disk' => env('SCREENSHOT_DISK', 'public'),

    'timeout' => env('SCREENSHOT_TIMEOUT', 120),

];

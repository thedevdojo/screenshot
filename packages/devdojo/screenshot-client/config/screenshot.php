<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Screenshot service URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the screenshot service the client posts to.
    |
    */

    'url' => env('SCREENSHOT_URL', 'https://screenshot.laravel.cloud'),

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    |
    | Sent to the service as `Authorization: Bearer <key>`. Leave empty if the
    | target service has no key configured. (In this repo the same value also
    | guards the service's own endpoints — see config/screenshot.php's api_key.)
    |
    */

    'api_key' => env('SCREENSHOT_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default storage disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk save() writes to. Null falls back to your app's
    | default disk (config('filesystems.default')).
    |
    */

    'disk' => env('SCREENSHOT_DISK'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Screenshot rendering is not instant, so this is generous by default.
    |
    */

    'timeout' => env('SCREENSHOT_TIMEOUT', 120),

];

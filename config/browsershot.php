<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Node module path
    |--------------------------------------------------------------------------
    |
    | The directory Browsershot points NODE_PATH at so node can resolve
    | puppeteer. Defaults to the project's local node_modules, which is where
    | `npm ci` installs it during a Laravel Cloud deploy.
    |
    */

    'node_module_path' => env('BROWSERSHOT_NODE_MODULE_PATH') ?: base_path('node_modules'),

    /*
    |--------------------------------------------------------------------------
    | Chrome path
    |--------------------------------------------------------------------------
    |
    | Path to the Chrome/Chromium binary. Leave unset/empty to let puppeteer
    | resolve its own downloaded Chromium (e.g. ~/.cache/puppeteer). Only set
    | this if you have a specific system Chrome you want to force.
    |
    */

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Node & npm binaries
    |--------------------------------------------------------------------------
    |
    | Optional absolute paths to the node and npm binaries. Leave null to rely
    | on whatever is found on PATH.
    |
    */

    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),

    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),

];

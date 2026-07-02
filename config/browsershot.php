<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Node module path
    |--------------------------------------------------------------------------
    |
    | The directory Browsershot points NODE_PATH at so node can resolve
    | puppeteer. On Laravel Cloud the deploy stages a persisted copy at
    | /var/www/browsershot/node_modules, so we default to that when it exists;
    | otherwise we fall back to the project's local node_modules. Override with
    | BROWSERSHOT_NODE_MODULE_PATH only if you have a non-standard setup.
    |
    */

    'node_module_path' => env('BROWSERSHOT_NODE_MODULE_PATH')
        ?: (is_dir('/var/www/browsershot/node_modules')
            ? '/var/www/browsershot/node_modules'
            : base_path('node_modules')),

    /*
    |--------------------------------------------------------------------------
    | Chrome path
    |--------------------------------------------------------------------------
    |
    | Path to the Chrome/Chromium binary. On Laravel Cloud (ARM64) the deploy
    | installs a native arm64 Chromium launcher at /var/www/bin/chromium (see
    | deploy/chromium.sh), so we default to that when it exists. Otherwise we
    | leave it null and let puppeteer resolve its own downloaded Chromium (the
    | normal case for local macOS / x86 development). Override with
    | BROWSERSHOT_CHROME_PATH to force a specific binary.
    |
    */

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH')
        ?: (is_file('/var/www/bin/chromium') ? '/var/www/bin/chromium' : null),

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

<?php

use DevDojo\ScreenshotClient\PendingScreenshot;
use DevDojo\ScreenshotClient\ScreenshotManager;

if (! function_exists('screenshot')) {
    /**
     * Start a fluent screenshot request.
     *
     * Pass a URL string for a shortcut (screenshot('https://…')->save()), or
     * call with no arguments and chain ->url()/->html().
     */
    function screenshot(?string $url = null): PendingScreenshot
    {
        return app(ScreenshotManager::class)->make($url);
    }
}

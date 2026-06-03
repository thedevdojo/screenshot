<?php

namespace DevDojo\ScreenshotClient\Facades;

use DevDojo\ScreenshotClient\ScreenshotManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \DevDojo\ScreenshotClient\PendingScreenshot url(string $url)
 * @method static \DevDojo\ScreenshotClient\PendingScreenshot html(string $html)
 * @method static \DevDojo\ScreenshotClient\PendingScreenshot make()
 *
 * @see ScreenshotManager
 */
class Screenshot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ScreenshotManager::class;
    }
}

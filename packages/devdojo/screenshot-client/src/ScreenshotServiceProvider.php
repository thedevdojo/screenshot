<?php

namespace DevDojo\ScreenshotClient;

use Illuminate\Support\ServiceProvider;

class ScreenshotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/screenshot.php', 'screenshot');

        $this->app->singleton(ScreenshotManager::class, fn () => new ScreenshotManager);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/screenshot.php' => config_path('screenshot.php'),
        ], 'screenshot-config');
    }
}

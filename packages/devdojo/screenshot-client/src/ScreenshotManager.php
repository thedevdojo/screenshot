<?php

namespace DevDojo\ScreenshotClient;

/**
 * Entry point / factory. Returns a fresh PendingScreenshot builder for each
 * request. Resolved from the container by the Screenshot facade and the
 * screenshot() helper.
 */
class ScreenshotManager
{
    public function url(string $url): PendingScreenshot
    {
        return $this->make()->url($url);
    }

    public function html(string $html): PendingScreenshot
    {
        return $this->make()->html($html);
    }

    public function make(): PendingScreenshot
    {
        return new PendingScreenshot;
    }
}

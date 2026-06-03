<?php

namespace DevDojo\ScreenshotClient;

/**
 * Entry point / factory. Returns a fresh PendingScreenshot builder for each
 * request. Resolved from the container by the Screenshot facade and the
 * screenshot() helper.
 */
class ScreenshotManager
{
    /**
     * Start a screenshot. Pass a URL to screenshot that page, or omit it and
     * chain ->html(...) for HTML content.
     */
    public function make(?string $url = null): PendingScreenshot
    {
        return new PendingScreenshot($url);
    }

    public function html(string $html): PendingScreenshot
    {
        return $this->make()->html($html);
    }
}

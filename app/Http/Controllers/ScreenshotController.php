<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;

class ScreenshotController extends Controller
{
    /**
     * Default window dimensions for screenshots
     */
    protected const DEFAULT_WIDTH = 1536;
    protected const DEFAULT_HEIGHT = 864;

    /**
     * Take a screenshot from a URL
     *  
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function snapFromUrl(Request $request)
    {
        $request->validate(['url' => 'required|url']);
        
        list($width, $height) = $this->getDimensions($request);

        $screenshot = Browsershot::url($request->url)
            ->setChromePath('/usr/bin/google-chrome')
            ->windowSize($width, $height)
            ->deviceScaleFactor(2)
            ->waitUntilNetworkIdle()
            ->newHeadless()
            ->noSandbox()
            ->timeout(120)
            ->screenshot();

        return $this->createImageResponse($screenshot);
    }

    /**
     * Take a screenshot from HTML content
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function snapFromHtml(Request $request)
    {
        $request->validate(['html' => 'required|string']);
        
        $tailwindCdn = $this->getTailwindCdn($request);
        list($width, $height) = $this->getDimensions($request);
        $html = $this->prepareHtml($request->html, $tailwindCdn);

        $screenshot = Browsershot::html($html)
            ->setChromePath('/usr/bin/google-chrome')
            ->windowSize($width, $height)
            ->deviceScaleFactor(2)
            ->newHeadless()
            ->noSandbox()
            ->timeout(120)
            ->setContentUrl('https://www.example.com')
            ->screenshot();

        return $this->createImageResponse($screenshot);
    }

    /**
     * Get the appropriate Tailwind CDN based on version
     *
     * @param Request $request
     * @return string
     */
    protected function getTailwindCdn(Request $request): string
    {
        if (isset($request->tailwind_version) && $request->tailwind_version == 4) {
            return '<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>';
        }
        
        return '<script src="https://cdn.tailwindcss.com"></script>';
    }

    /**
     * Get the dimensions for the screenshot
     *
     * @param Request $request
     * @return array
     */
    protected function getDimensions(Request $request): array
    {
        if (isset($request->width) && isset($request->height)) {
            return [$request->width, $request->height];
        }
        
        return [self::DEFAULT_WIDTH, self::DEFAULT_HEIGHT];
    }

    /**
     * Prepare the HTML with necessary styles and scripts
     *
     * @param string $content
     * @param string $tailwindCdn
     * @return string
     */
    protected function prepareHtml(string $content, string $tailwindCdn): string
    {
        $fontStack = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">';
        $fontStack .= '<style>html, body{ font-family: "Inter", sans-serif; font-optical-sizing: auto; }</style>';
        
        return '<html><head>' . $fontStack . $tailwindCdn . '</head><body class="antialiased">' . $content . '</body></html>';
    }

    /**
     * Create an image response from screenshot data
     *
     * @param string $screenshot
     * @return \Illuminate\Http\Response
     */
    protected function createImageResponse(string $screenshot)
    {
        return response($screenshot, 200, [
            'Content-Type' => 'image/png',
        ]);
    }
}

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

        $browsershot = Browsershot::url($request->url)
            ->setNodeModulePath(config('browsershot.node_module_path'))
            ->windowSize($width, $height)
            ->deviceScaleFactor(2)
            ->waitUntilNetworkIdle()
            ->newHeadless()
            ->noSandbox()
            ->timeout(120);

        if ($chromePath = config('browsershot.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        if ($nodeBinary = config('browsershot.node_binary')) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        $screenshot = $browsershot->screenshot();

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

        $browsershot = Browsershot::html($html)
            ->setNodeModulePath(config('browsershot.node_module_path'))
            ->windowSize($width, $height)
            ->deviceScaleFactor(2)
            ->newHeadless()
            ->noSandbox()
            ->timeout(120)
            ->setContentUrl('https://www.example.com');

        // A complete document may pull remote fonts and images the fragment
        // wrapper never does; give the network a beat to settle so captures
        // don't race them. Non-strict: up to two in-flight requests still
        // count as idle, so a long-polling page cannot hang the shot.
        if ($this->isCompleteDocument($request->html)) {
            $browsershot->waitUntilNetworkIdle(false);
        }

        if ($chromePath = config('browsershot.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        if ($nodeBinary = config('browsershot.node_binary')) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        $screenshot = $browsershot->screenshot();

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
     * The wrapper exists to make bare fragments presentable. A complete
     * document must render exactly as posted: injecting the wrapper into one
     * corrupts it — the Tailwind v3 CDN's runtime adds an unlayered
     * universal --tw-* variable reset that overrides the document's own
     * layered Tailwind v4 rules (gradients lose their color stops), and the
     * Inter font stack overrides the document's fonts.
     *
     * @param string $content
     * @param string $tailwindCdn
     * @return string
     */
    protected function prepareHtml(string $content, string $tailwindCdn): string
    {
        if ($this->isCompleteDocument($content)) {
            return $content;
        }

        $fontStack = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">';
        $fontStack .= '<style>html, body{ font-family: "Inter", sans-serif; font-optical-sizing: auto; }</style>';

        return '<html><head>' . $fontStack . $tailwindCdn . '</head><body class="antialiased">' . $content . '</body></html>';
    }

    /**
     * Whether the payload is a full HTML document rather than a fragment.
     *
     * @param string $content
     * @return bool
     */
    protected function isCompleteDocument(string $content): bool
    {
        return (bool) preg_match('/^\s*(?:<!doctype\b|<html\b)/i', $content);
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

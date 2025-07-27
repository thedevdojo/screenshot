<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;
use Spatie\Image\Manipulations;

class ScreenshotController extends Controller
{
    public function snapFromUrl(Request $request)
    {
        $request->validate(['url' => 'required|url']);

        $screenshot = Browsershot::url($request->url)
	    ->setChromePath('/usr/bin/google-chrome')
	    ->windowSize(1536, 864)
            ->newHeadless()
	    ->noSandbox()
            ->timeout(120)
            ->screenshot();

        return response($screenshot, 200, [
            'Content-Type' => 'image/png',
        ]);
    }

    public function snapFromHtml(Request $request)
    {
        $request->validate(['html' => 'required|string']);
        
        // If the request contains tailwind_version == 4, use the CDN for Tailwind CSS v4
        if (isset($request->tailwind_version) && $request->tailwind_version == 4) {
             $tailwind_cdn = '<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>';
        } else {
             $tailwind_cdn = '<script src="https://cdn.tailwindcss.com"></script>';
        }

        $default_font_stack = '<style>body{ font-family: system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; }</style>';

        $html = '<html><head>'. $default_font_stack . $tailwind_cdn . '</head><body class="antialiased>' . $request->html . '</body></html>';


        $screenshot = Browsershot::html($html)
            ->setChromePath('/usr/bin/google-chrome')
            ->windowSize(1536, 864)
            ->newHeadless()
            ->noSandbox()
            ->timeout(120)
            ->setContentUrl('https://www.example.com')
            ->screenshot();

        

        return response($screenshot, 200, [
            'Content-Type' => 'image/png',
        ]);
    }
}

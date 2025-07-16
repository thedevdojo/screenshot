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
        
        $html = '<html><head><script src="https://cdn.tailwindcss.com"></script></head><body>' . $request->html . '</body></html>';

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

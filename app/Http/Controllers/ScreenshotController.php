<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;

class ScreenshotController extends Controller
{
    public function snapFromUrl(Request $request)
    {
        $request->validate(['url' => 'required|url']);

        $screenshot = Browsershot::url($request->url)
            ->setChromePath('/snap/bin/chromium')
            ->windowSize(1920, 1080)
            ->newHeadless()
            ->noSandbox()
            ->waitUntilNetworkIdle()
            ->setDelay(500)
            ->timeout(120)
            ->screenshot();

        return response($screenshot, 200, [
            'Content-Type' => 'image/png',
        ]);
    }

    public function snapFromHtml(Request $request)
    {
        dd('hit');
        $request->validate(['html' => 'required|string']);
        
        $html = '<html><head><script src="https://cdn.tailwindcss.com"></script></head><body>' . $request->html . '</body></html>';

        $screenshot = Browsershot::html($html)
            ->setChromePath('/snap/bin/chromium')
            ->windowSize(1920, 1080)
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

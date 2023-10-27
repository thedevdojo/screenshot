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
            //npx @puppeteer/browsers install chromedriver@canary
            ->setChromePath('/var/www/laravel/chromedriver/linux-120.0.6091.0/chromedriver-linux64/chromedriver')
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

        $screenshot = Browsershot::html($request->html)
            //npx @puppeteer/browsers install chromedriver@canary
            ->setChromePath('/var/www/laravel/chromedriver/linux-120.0.6091.0/chromedriver-linux64/chromedriver')
            ->newHeadless()
            ->noSandbox()
            ->timeout(120)
            ->screenshot();

        return response($screenshot, 200, [
            'Content-Type' => 'image/png',
        ]);
    }
}

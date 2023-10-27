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
            ->screenshot();

        return response($screenshot, 200, [
            'Content-Type' => 'image/png',
        ]);
    }

    public function snapFromHtml(Request $request)
    {
        $request->validate(['html' => 'required|string']);

        $screenshot = Browsershot::html($request->html)
            ->applyStylesheet(public_path('css/app.css'))  // Assuming you have tailwind setup in your Laravel project
            ->screenshot();

        return response($screenshot, 200, [
            'Content-Type' => 'image/png',
        ]);
    }
}

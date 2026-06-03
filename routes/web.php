<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Demo: capture a screenshot of some Tailwind HTML, save it to storage, and
// display the saved image. Uses the bundled devdojo/screenshot-client.
Route::get('/example', function () {
    $path = screenshot()
        ->html('<p class="bg-green-500 p-10">Example Here</p>')
        ->save('screenshots/example.png');

    $disk = config('screenshot.disk') ?: config('filesystems.default');

    return response()->file(Storage::disk($disk)->path($path));
});

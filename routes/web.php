<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Demo: capture a screenshot of some Tailwind HTML, save it to the (public)
// disk, and redirect to its public URL. Uses the bundled devdojo/screenshot-client.
Route::get('/example', function () {
    $shot = screenshot()
        ->html('<p class="bg-green-500 p-10">Example Here</p>')
        ->save('screenshots/example.png');

    return redirect($shot->url());
});

// Demo: screenshot a live URL and display it from its public URL.
Route::get('/example-url', function () {
    $shot = screenshot('https://google.com')
        ->save('screenshots/example-url.png');

    return redirect($shot->url());
});

Route::view('example-new', 'example-new');

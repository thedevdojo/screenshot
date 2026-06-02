<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScreenshotController;
use App\Http\Controllers\Api\AuthController;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(function () {
    Route::get('/hello', function(){
        return 'Hey There';
    });
    Route::post('/snap-from-url', [ScreenshotController::class, 'snapFromUrl']);
    Route::post('/snap-from-html', [ScreenshotController::class, 'snapFromHtml']);
});

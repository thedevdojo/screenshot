<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScreenshotController;
use App\Http\Controllers\Api\AuthController;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/hello', function(){
    return 'Hey There';
});

// Screenshot endpoints. Protected by the SCREENSHOT_API_KEY shared secret when
// one is set (sent as `Authorization: Bearer <key>`); open otherwise.
// Generate a key with: php artisan screenshot:key
Route::middleware('screenshot.key')->group(function () {
    Route::post('/snap-from-url', [ScreenshotController::class, 'snapFromUrl']);
    Route::post('/snap-from-html', [ScreenshotController::class, 'snapFromHtml']);
});

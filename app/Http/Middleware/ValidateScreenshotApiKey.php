<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateScreenshotApiKey
{
    /**
     * Require a valid Bearer token matching config('screenshot.api_key').
     *
     * If no key is configured the request is allowed through, so the service
     * works out of the box; set SCREENSHOT_API_KEY (e.g. via `php artisan
     * screenshot:key`) to require authentication.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('screenshot.api_key');

        // No key configured -> endpoints are open.
        if (empty($key)) {
            return $next($request);
        }

        $provided = $request->bearerToken();

        if (! $provided || ! hash_equals($key, $provided)) {
            return response()->json([
                'message' => 'Invalid or missing API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

# Screenshot Client — Design Spec

**Date:** 2026-06-02
**Status:** Approved design, pending spec review

## Purpose

Provide a fluent, Laravel-idiomatic client for calling this screenshot service from PHP/Laravel code, so consumers can write:

```php
screenshot()->url('https://google.com')->save();
screenshot()->html('<div class="...">Hi</div>')->save();
```

instead of hand-rolling `Http::post(...)` + storage every time.

## Location & packaging

A **self-contained Composer package living inside this repo** at `packages/devdojo/screenshot-client/`, namespace `DevDojo\ScreenshotClient`. It is autoloaded by this app's `composer.json` (PSR-4 + `files` for the helper) and its service provider registered in `bootstrap/providers.php`. This lets the screenshot service **dogfood its own client**, while keeping the package self-contained and extractable to a standalone repo (`devdojo/screenshot-client`) later with no code changes.

## API surface

### Invocation (both helper and facade)

```php
// URL: pass it to the helper / make(); HTML: chain ->html()
screenshot('https://google.com')->save()->url();    // global helper
Screenshot::make('https://google.com')->save()->url();  // facade
screenshot()->html('<div>...</div>')->save()->url();
```

The URL source is supplied as the helper / `make()` argument (a bare string is
treated as a URL); HTML content is supplied via `->html()`. This keeps `url()` as
a single concept — the **output** URL on `StoredScreenshot` — avoiding a same-named
input/output method. `screenshot()` / `Screenshot::make()` with no argument returns
an empty builder for use with `->html()`.

### Source methods (on PendingScreenshot, return $this)

- `__construct(?string $url = null)` — a URL passed to the helper/`make()` sets the URL source.
- `html(string $html): static`

### Option methods (chainable, return $this)

- `width(int $width): static`
- `height(int $height): static`
- `dimensions(int $width, int $height): static`
- `tailwind(int $version): static` — maps to the API's `tailwind_version` field
- `disk(string $disk): static` — override the default storage disk for `save()`

### Terminal methods (perform the HTTP request)

- `save(?string $path = null): StoredScreenshot` — capture, store to the configured/overridden disk; auto-generates `screenshots/{uuid}.png` when `$path` is null. Returns a `StoredScreenshot` value object exposing `->path()`, `->disk()`, and `->url()` (full public URL), and stringifying to the path. The default disk is `public`, so saved screenshots are web-accessible after `php artisan storage:link`.
- `bytes(): string` — return raw PNG bytes, no storage.
- `base64(bool $dataUri = false): string` — base64 string, or a `data:image/png;base64,…` URI when `$dataUri` is true.
- `response(): \Illuminate\Http\Response` — an inline `image/png` HTTP response, returnable directly from a controller.

## Components

```
packages/devdojo/screenshot-client/
  src/
    ScreenshotServiceProvider.php   // merge/publish config, bind manager singleton
    ScreenshotManager.php           // entry point: url()/html() -> new PendingScreenshot; holds config
    PendingScreenshot.php           // fluent builder; the only class that performs HTTP + storage
    Facades/Screenshot.php          // facade -> ScreenshotManager
    Exceptions/ScreenshotException.php
    helpers.php                     // screenshot() function
  config/screenshot.php
  composer.json                     // package manifest (for later extraction)
```

Each unit has one purpose: the **manager** is a thin factory + config holder; the **builder** holds request state and executes; the **facade/helper** are entry points; the **exception** carries failures.

### Config (`config/screenshot.php`)

```php
return [
    'url'     => env('SCREENSHOT_URL', 'https://screenshot.laravel.cloud'),
    'key'     => env('SCREENSHOT_API_KEY'),
    'disk'    => env('SCREENSHOT_DISK'),       // null -> filesystems.default
    'timeout' => env('SCREENSHOT_TIMEOUT', 120),
];
```

## Data flow

1. `screenshot()` / `Screenshot::` → `ScreenshotManager` → new `PendingScreenshot` (carrying config).
2. Chained `url()`/`html()` + options accumulate request state.
3. A terminal method builds the JSON payload (`url` or `html`, plus optional `width`/`height`/`tailwind_version`), calls `Http::withToken(config key)->timeout(...)->post(config url + '/api/snap-from-' . source, payload)`.
4. On success (PNG bytes): `save()` writes via `Storage::disk(...)->put()` and returns the path; `bytes()`/`base64()`/`response()` transform the body.

## Error handling

- A terminal with neither `url()` nor `html()` set → `ScreenshotException` ("no source specified").
- HTTP non-2xx → `ScreenshotException` carrying the status code and the server's JSON `message` (e.g. 401 invalid key, 422 validation error). Implemented by inspecting `$response->failed()` rather than letting Guzzle's generic exception bubble, so the message is useful.

## Testing

- The builder uses Laravel's `Http` facade internally, so package tests (and consumer tests) use `Http::fake()` to stub PNG bytes / error responses. No custom fake layer (YAGNI).
- Test matrix: helper + facade return a builder; URL shortcut; payload shape for url vs html and with options; `save()` writes to disk (via `Storage::fake()`) and returns path; auto-name vs explicit path; `bytes()`/`base64()`/`response()` outputs; error mapping (401/422/no-source).

## Out of scope (v1, YAGNI)

- `Screenshot::fake()` convenience (Http::fake suffices).
- Retries / backoff.
- Async / queued capture.
- Publishing to Packagist / extracting to a standalone repo (the package is structured to allow it later).

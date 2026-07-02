# 📸 Screenshot Client

**Turn any URL or HTML into an image with a single line of code.**

```php
screenshot('https://laravel.com')->save()->url();
// → https://your-app.com/storage/screenshots/9b1c…f3.png
```

That's it. No headless Chrome to install, no Puppeteer to babysit, no binaries to compile. This is a tiny, fluent client for the [Screenshot service](https://github.com/thedevdojo/screenshot) — it does the talking, the service does the rendering, and you get a ready-to-use image back.

---

## Why you'll like it

- **One line.** Capture, store, and get a public URL in a single fluent chain.
- **URL or HTML.** Screenshot a live page, or render your own Tailwind-powered HTML.
- **Stores anywhere.** Writes to any Laravel filesystem disk — `public`, `s3`, you name it.
- **Gives you what you need.** A public URL, a stored path, raw bytes, a base64 data URI, or a ready HTTP response.
- **Zero rendering infrastructure.** All the headless-browser complexity lives in the service.

---

## Installation

```bash
composer require devdojo/screenshot-client
```

The service provider and `Screenshot` facade auto-register. Publish the config if you'd like to tweak defaults:

```bash
php artisan vendor:publish --tag=screenshot-config
```

To serve saved images on the default `public` disk, link your storage once:

```bash
php artisan storage:link
```

### Configuration

Add these to your `.env`:

```dotenv
SCREENSHOT_URL=https://screenshot.laravel.cloud
SCREENSHOT_API_KEY=your-api-key
```

> [!IMPORTANT]
> **`SCREENSHOT_API_KEY` must be the *exact* same value as the `SCREENSHOT_API_KEY` set on the Screenshot service you're calling.** The client sends it as a `Bearer` token and the service checks it byte-for-byte — if they don't match you'll get a `401`. (If the service has no key set, it's open and this can be left empty.)

| Variable             | Default                          | Description                                              |
| -------------------- | -------------------------------- | -------------------------------------------------------- |
| `SCREENSHOT_URL`     | `https://screenshot.laravel.cloud` | Base URL of the screenshot service.                      |
| `SCREENSHOT_API_KEY` | `null`                           | Shared secret — **must match the service's key.**        |
| `SCREENSHOT_DISK`    | `public`                         | Filesystem disk that `save()` writes to.                 |
| `SCREENSHOT_TIMEOUT` | `120`                            | Request timeout in seconds (rendering isn't instant).    |

---

## Quick start

### Screenshot a URL

```php
$shot = screenshot('https://laravel.com')->save();

$shot->url();   // public URL  → https://your-app.com/storage/screenshots/<uuid>.png
$shot->path();  // disk path   → screenshots/<uuid>.png
```

### Screenshot your own HTML

```php
$shot = screenshot()
    ->html('<h1 class="text-6xl font-black text-indigo-600 p-16">Hello! 👋</h1>')
    ->save();

return redirect($shot->url());
```

Your HTML is rendered with Tailwind CSS already available — just write classes and they work.

> **The rule of thumb:** pass a **URL** straight to `screenshot('…')`. For **HTML**, call `screenshot()->html('…')`.

---

## Going further

Everything chains. Start with a source, add options, end with what you want back.

### Choose where it's saved

```php
// Auto-generated filename (screenshots/<uuid>.png)
screenshot('https://laravel.com')->save();

// Your own path
screenshot('https://laravel.com')->save('og/homepage.png');

// A different disk (e.g. S3)
screenshot('https://laravel.com')->disk('s3')->save('og/homepage.png')->url();
```

### Control the size & Tailwind version

```php
screenshot()
    ->html('<div class="bg-slate-900 text-white p-20 text-5xl">Open Graph image</div>')
    ->dimensions(1200, 630)   // perfect OG image size  (or ->width(1200)->height(630))
    ->tailwind(4)             // render with Tailwind v4
    ->save('og/post-42.png')
    ->url();
```

### Get it in whatever form you need

```php
// 1. Save to disk, get a StoredScreenshot back (path + url + disk)
$shot = screenshot('https://laravel.com')->save();

// 2. Raw PNG bytes (store/process them yourself)
$bytes = screenshot('https://laravel.com')->bytes();

// 3. A base64 data URI — drop straight into an <img src="...">
$dataUri = screenshot('https://laravel.com')->base64(dataUri: true);

// 4. A ready-to-return HTTP response (great in controllers)
return screenshot('https://laravel.com')->response();
```

### Prefer a facade? Use it instead of the helper

```php
use DevDojo\ScreenshotClient\Facades\Screenshot;

Screenshot::make('https://laravel.com')->save()->url();
Screenshot::html('<h1 class="p-10">Hi</h1>')->save()->url();
```

The `screenshot()` helper and the `Screenshot` facade are equivalent — use whichever you prefer.

---

## Real-world examples

### Generate an Open Graph image for a blog post

```php
Route::get('/posts/{post}/og.png', function (Post $post) {
    return screenshot()
        ->html(view('og.post', ['post' => $post])->render())
        ->dimensions(1200, 630)
        ->save("og/posts/{$post->id}.png")
        ->url();
});
```

### Store a thumbnail on S3 and keep the URL on a model

```php
$post->update([
    'thumbnail_url' => screenshot($post->url)
        ->disk('s3')
        ->dimensions(1280, 720)
        ->save("thumbnails/{$post->id}.png")
        ->url(),
]);
```

### Return a screenshot directly from a controller

```php
public function preview(Request $request)
{
    return screenshot($request->input('url'))->response();
}
```

---

## The `StoredScreenshot` object

`save()` returns a small object so you can grab exactly what you need:

```php
$shot = screenshot('https://laravel.com')->save();

$shot->url();    // full public URL
$shot->path();   // disk-relative path, e.g. "screenshots/<uuid>.png"
$shot->disk();   // the disk it was written to
(string) $shot;  // the path (it's Stringable, so it drops in anywhere a path is expected)
```

> `url()` requires a publicly-served disk (like the default `public` disk). On a private disk the file is still saved — just not web-accessible.

---

## API reference

**Start a screenshot**

| Call                                | Source                       |
| ----------------------------------- | ---------------------------- |
| `screenshot('https://…')`           | a URL                        |
| `screenshot()->html('<…>')`         | raw HTML (Tailwind included) |
| `Screenshot::make('https://…')`     | a URL (facade)               |
| `Screenshot::html('<…>')`           | raw HTML (facade)            |

**Options** (chainable)

| Method                      | Description                                   |
| --------------------------- | --------------------------------------------- |
| `->width(int)`              | Viewport width.                               |
| `->height(int)`             | Viewport height.                              |
| `->dimensions(int, int)`    | Width and height in one call.                 |
| `->tailwind(int)`           | Tailwind major version to render with.        |
| `->disk(string)`            | Storage disk for `save()` (overrides config). |

**Finish** (performs the request)

| Method                         | Returns                                                            |
| ------------------------------ | ------------------------------------------------------------------ |
| `->save(?string $path = null)` | `StoredScreenshot` — stores the image; auto-names when no path.     |
| `->bytes()`                    | `string` — raw PNG bytes.                                          |
| `->base64(bool $dataUri = false)` | `string` — base64, or a `data:image/png;base64,…` URI.          |
| `->response()`                 | `Illuminate\Http\Response` — an inline `image/png` response.       |

---

## Error handling

Any failure (bad API key, validation error, the service being unreachable) throws a `DevDojo\ScreenshotClient\Exceptions\ScreenshotException` carrying the status code and the service's message:

```php
use DevDojo\ScreenshotClient\Exceptions\ScreenshotException;

try {
    $shot = screenshot('https://laravel.com')->save();
} catch (ScreenshotException $e) {
    report($e); // e.g. "Screenshot request failed [401]: Invalid or missing API key."
}
```

---

## Testing

The client uses Laravel's `Http` client under the hood, so you can fake it in your own tests with no extra tooling:

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

Http::fake(['*' => Http::response('fake-png-bytes', 200)]);
Storage::fake('public');

$shot = screenshot('https://laravel.com')->save();

Storage::disk('public')->assertExists($shot->path());
```

---

## License

MIT.

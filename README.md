# 📸 Screenshot Service

Deploy your own screenshot service to [Laravel Cloud](https://laravel.com/cloud). Deploy it and run `php artisan screenshot:key`, this will generate your new API Key. Then, start sending POST requests to generate screenshots from a **URL** or **HTML**:

```
curl -X POST \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer SCREENSHOT_API_KEY" \
    -d '{"url":"https://google.com"}' \
    https://CLOUD_URL/api/snap-from-url \
    --output ./screenshot.png && open ./screenshot.png
```

> Be sure to swap out `SCREENSHOT_API_KEY` with your API Key and swap out CLOUD_URL with your `laravel.cloud` domain.

To make things even easier, you can utilize the [Laravel client package](https://github.com/thedevdojo/screenshot-client), and use a handful of helper methods like this:

```
<img src="{{ screenshot('https://google.com')->save('screenshot.png')->url() }}" />
```

## Installation

### Laravel Cloud

To install via Laravel Cloud, fork this repo, connect the repo when creating a new app in Laravel Cloud and you're good to go. Be sure to add a Redis or Valkey cache to your environment and you're ready to start snapping screenshots 📸

### Local Install

1.  **Clone the Repository:**

    ```sh
    git clone https://github.com/thedevdojo/screenshot.git
    cd screenshot
    ```

2.  **Install Dependencies:**

    ```sh
    composer install
    npm install
    ```

3.  **Set up Environment Variables:**

    Copy the `.env.example` file to a new file named `.env` and update the necessary configuration settings, including database and API configuration.


## Usage

### Example

```bash
curl -X POST \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer password" \
    -d '{"html":"<p class=\"bg-green-400 p-10\">Tailwind support out of the box</p>"}' \
    https://screenshot.laravel.cloud/api/snap-from-html \
    --output ./screenshot.png && open ./screenshot.png
```


### Endpoints:

1.  **Capture Screenshot from URL:**

    **Endpoint:** `/api/snap-from-url`

    **Method:** `POST`

    **Headers:**

    -   `Content-Type: application/json`
    -   `Authorization: Bearer SCREENSHOT_API_KEY`

    **Payload:**

    ```json
    {   "url": "https://www.example.com" }
    ```

2.  **Render HTML with Tailwind and Capture Screenshot:**

    **Endpoint:** `/api/snap-from-html`

    **Method:** `POST`

    **Headers:**

    -   `Content-Type: application/json`
    -   `Authorization: Bearer SCREENSHOT_API_KEY`

    **Payload:**

    ```json
    {   "html": "<div class='bg-blue-500 text-white p-4'>Hello, Tailwind!</div>" }
    ```


### Authentication:

The screenshot endpoints are protected by a single shared **API key**. Send it as a Bearer token:

```
Authorization: Bearer <your-key>
```

Generate a strong key and write it to your `.env` automatically:

```bash
php artisan screenshot:key
```

This sets `SCREENSHOT_API_KEY` in `.env`. You can also set it by hand to any value (e.g. `SCREENSHOT_API_KEY=password`) — any request sending that exact value as the Bearer token is allowed; everything else gets a `401`. If `SCREENSHOT_API_KEY` is left empty, the endpoints are **open** (no auth), so the service works out of the box — set a key to lock it down.

## Getting spatie/browsershot working with Laravel Cloud

`spatie/browsershot` drives a headless Chrome through Puppeteer, and that's where Laravel Cloud gets tricky: Laravel Cloud runs on **ARM64 (aarch64)**, but Google does not publish a Chrome / Chrome-for-Testing build for `linux-arm64`, so the Chromium that Puppeteer auto-downloads is an **x86-64 binary that can't execute on the runtime** (`Exec format error`). On top of that, Laravel Cloud's build runs as an **unprivileged user with no root, no `sudo`, and no apt package indices**, and **only `$HOME` (`/var/www`) persists** from build into runtime — so you can't just `apt install chromium` or its libraries.

The fix is a self-contained build step that (1) uses **Playwright** purely as a *downloader* to fetch a **native arm64 Chromium** (Playwright compiles and hosts Chromium for arm64, unlike Google), (2) downloads Chromium's required **shared libraries** (`libglib`, `libgbm`, `libasound`, etc.) directly from the Debian package mirror over HTTPS — since the base image doesn't ship them and apt isn't available — and (3) wraps the browser in a small launcher that exposes those libraries via `LD_LIBRARY_PATH`, then points Browsershot at it with `BROWSERSHOT_CHROME_PATH`. Everything lands under `$HOME`, so it survives into the running app.

### Setup

The two scripts that do this live in [`deploy/chromium.sh`](deploy/chromium.sh) (orchestration + the `LD_LIBRARY_PATH` launcher) and [`deploy/install-chromium-deps.cjs`](deploy/install-chromium-deps.cjs) (a Node resolver that walks the Debian dependency graph and downloads the arm64 `.deb`s without apt).

**This is wired up to be zero-config** — deploy the repo to Laravel Cloud and it just works:

- The paths are auto-detected in `config/browsershot.php` (Chrome at `/var/www/bin/chromium`, node modules at `/var/www/browsershot/node_modules`) when they exist, so **you don't need to set any environment variables**.
- Puppeteer's pointless x86-64 Chrome download is skipped automatically on `linux-arm64` via [`.puppeteerrc.cjs`](.puppeteerrc.cjs).

The only manual step is the build command:

1.  **Add the build command** (Settings → Deployments → Build Commands), after your existing `composer`/`npm` steps:

    ```sh
    bash deploy/chromium.sh
    ```

2.  **Deploy, then verify** on the server with the bundled diagnostic command, which finds the Chrome binary, checks its architecture and shared libraries, and actually tries to launch it:

    ```sh
    php artisan browsershot:diagnose
    ```

    A `VERDICT: WORKS ✓` for `/var/www/bin/chromium` means Browsershot is ready.

> **Overrides:** Everything above is auto-detected, but you can force any value with the `BROWSERSHOT_CHROME_PATH`, `BROWSERSHOT_NODE_MODULE_PATH`, `BROWSERSHOT_NODE_BINARY`, or `BROWSERSHOT_NPM_BINARY` env vars. The controller applies the Chrome path via `config('browsershot.chrome_path')` (see `app/Http/Controllers/ScreenshotController.php`).
>
> **Note:** This is specific to ARM64 hosts that lack Chrome's system libraries (like Laravel Cloud). On a normal x86-64 server you can usually just let Puppeteer download its own Chrome, or `apt install` the libraries directly.

## Contributing

Contributions are welcome! Please feel free to submit a pull request.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

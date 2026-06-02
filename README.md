# Laravel Screenshot Service

This is a Laravel-based microservice for capturing website screenshots using the `spatie/browsershot` package. It provides API endpoints to take snapshots of websites by URL or by rendering provided HTML with Tailwind CSS.

## Features

-   Capture screenshots from a URL.
-   Render HTML with Tailwind CSS and capture screenshots.
-   Token-based authentication using Laravel Sanctum.

## Requirements

-   PHP >= 7.3
-   Composer
-   Node & npm
-   Puppeteer (for `spatie/browsershot`)

## Installation

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

4.  **Run Database Migrations:**

    ```sh
    php artisan migrate
    ```

5.  **Start the Server:**

    ```sh
    php artisan serve
    ```


## Usage

### Endpoints:

1.  **Capture Screenshot from URL:**

    **Endpoint:** `/api/snap-from-url`

    **Method:** `POST`

    **Headers:**

    -   `Content-Type: application/json`
    -   `Authorization: Bearer YOUR_TOKEN`

    **Payload:**

    ```json
    {   "url": "https://www.example.com" }
    ```

2.  **Render HTML with Tailwind and Capture Screenshot:**

    **Endpoint:** `/api/snap-from-html`

    **Method:** `POST`

    **Headers:**

    -   `Content-Type: application/json`
    -   `Authorization: Bearer YOUR_TOKEN`

    **Payload:**

    ```json
    {   "html": "<div class='bg-blue-500 text-white p-4'>Hello, Tailwind!</div>" }
    ```


### Authentication:

You need to authenticate your requests using Laravel Sanctum. Please refer to the Laravel Sanctum documentation for generating and managing tokens.

## Examples

1.  **Endpoint for Taking a Snapshot of a URL**:

    Before you can make a request to this endpoint, ensure you have an authentication token. Assuming you've implemented Laravel Sanctum's token authentication, you would first get a token and then include it in the headers for authentication.

    First, get a token:

    ```bash
    curl -X POST -H "Content-Type: application/json" -d '{"email":"test@example.com", "password":"testpassword"}' https://screenshot.devdojo.com/api/login
    ```

    Export the token as an env var:

    ```bash
    export API_TOKEN="YOUR_API_TOKEN_HERE"
    ```

    Then make a request to the endpoint:

    ```bash
    curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $API_TOKEN" -d '{"url":"https://www.example.com"}' https://screenshot.devdojo.com/api/snap-from-url --output screenshot.png
    ```

    This will save the screenshot as `screenshot.png` in your current directory.

2.  **Endpoint for Rendering HTML with Tailwind and Taking a Screenshot**:

    Here's how you would send an HTML snippet to be rendered and then captured:

    ```bash
    curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $API_TOKEN" -d '{"html":"<div class=\"bg-blue-500 text-white p-4\">Hello, Tailwind!</div>"}' https://screenshot.devdojo.com/api/snap-from-html --output rendered_screenshot.png
    ```

    This will save the rendered screenshot as `rendered_screenshot.png` in your current directory.

## Getting spatie/browsershot working with Laravel Cloud

`spatie/browsershot` drives a headless Chrome through Puppeteer, and that's where Laravel Cloud gets tricky: Laravel Cloud runs on **ARM64 (aarch64)**, but Google does not publish a Chrome / Chrome-for-Testing build for `linux-arm64`, so the Chromium that Puppeteer auto-downloads is an **x86-64 binary that can't execute on the runtime** (`Exec format error`). On top of that, Laravel Cloud's build runs as an **unprivileged user with no root, no `sudo`, and no apt package indices**, and **only `$HOME` (`/var/www`) persists** from build into runtime — so you can't just `apt install chromium` or its libraries.

The fix is a self-contained build step that (1) uses **Playwright** purely as a *downloader* to fetch a **native arm64 Chromium** (Playwright compiles and hosts Chromium for arm64, unlike Google), (2) downloads Chromium's required **shared libraries** (`libglib`, `libgbm`, `libasound`, etc.) directly from the Debian package mirror over HTTPS — since the base image doesn't ship them and apt isn't available — and (3) wraps the browser in a small launcher that exposes those libraries via `LD_LIBRARY_PATH`, then points Browsershot at it with `BROWSERSHOT_CHROME_PATH`. Everything lands under `$HOME`, so it survives into the running app.

### Setup

The two scripts that do this live in [`deploy/chromium.sh`](deploy/chromium.sh) (orchestration + the `LD_LIBRARY_PATH` launcher) and [`deploy/install-chromium-deps.cjs`](deploy/install-chromium-deps.cjs) (a Node resolver that walks the Debian dependency graph and downloads the arm64 `.deb`s without apt). To enable it on Laravel Cloud:

1.  **Add the build command** (Settings → Deployments → Build Commands), after your existing `composer`/`npm` steps:

    ```sh
    bash deploy/chromium.sh
    ```

2.  **Set the environment variable** (Settings → Environment) so Browsershot uses the installed browser instead of Puppeteer's broken download:

    ```sh
    BROWSERSHOT_CHROME_PATH=/var/www/bin/chromium
    # Optional: skip Puppeteer's pointless x86-64 Chrome download during npm install
    PUPPETEER_SKIP_DOWNLOAD=true
    ```

3.  **Read `BROWSERSHOT_CHROME_PATH` in your Browsershot call** (see `config/browsershot.php` and `app/Http/Controllers/ScreenshotController.php`):

    ```php
    if ($chromePath = config('browsershot.chrome_path')) {
        $browsershot->setChromePath($chromePath);
    }
    ```

4.  **Deploy, then verify** on the server with the bundled diagnostic command, which finds the Chrome binary, checks its architecture and shared libraries, and actually tries to launch it:

    ```sh
    php artisan browsershot:diagnose
    ```

    A `VERDICT: WORKS ✓` for `/var/www/bin/chromium` means Browsershot is ready.

> **Note:** This is specific to ARM64 hosts that lack Chrome's system libraries (like Laravel Cloud). On a normal x86-64 server you can usually just let Puppeteer download its own Chrome, or `apt install` the libraries directly.

## Contributing

Contributions are welcome! Please feel free to submit a pull request.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# CLAUDE.md

Guidance for working in this repository.

## What this project is

A small **Laravel screenshot microservice**. It exposes an API that renders a web page — either a live URL or a snippet of HTML (auto-wrapped with Tailwind CSS + the Inter font) — and returns a **PNG**. Screenshots are produced by headless Chromium, driven through [`spatie/browsershot`](https://github.com/spatie/browsershot) (which uses Puppeteer under the hood).

Production runs on **Laravel Cloud** at `https://screenshot-service-main-*.laravel.cloud`. The public site is `screenshot.devdojo.com`.

## Stack

- **PHP 8.3**, **Laravel 13**
- **`spatie/browsershot` ^5.0** → **Puppeteer ^25** (Node) → headless Chromium
- **Laravel Sanctum** for token auth
- **Vite + Tailwind** for the (minimal) frontend
- Deploy target: **Laravel Cloud**, which runs on **ARM64 (aarch64)**

## How the API works

Routes are in `routes/api.php`; logic in `app/Http/Controllers/ScreenshotController.php`.

- `POST /api/snap-from-url` — body `{ "url": "https://…" }` → PNG of that page.
- `POST /api/snap-from-html` — body `{ "html": "<div>…</div>", "tailwind_version": 4?, "width": int?, "height": int? }` → PNG of the rendered HTML. The controller wraps the snippet in a full HTML doc with the Inter font and a Tailwind CDN (`getTailwindCdn` / `prepareHtml`).
- `POST /api/login` — Sanctum token (`Api/AuthController`).

Both snap endpoints return raw `image/png` bytes (see `createImageResponse`). Note: the auth middleware group around the snap routes is currently **commented out**, so they're effectively public right now.

JSON gotcha: the HTML payload contains quotes, so inner double-quotes must be escaped (`\"`) or the request fails validation with a `422`. When testing with `curl`, use `--fail` and `--output file.png` so a failed request doesn't silently save an error body as a `.png`.

## The Chromium setup (the important part)

`spatie/browsershot` needs a Chromium binary it can launch. How that binary is provided differs by environment, and getting it working on Laravel Cloud's ARM64 runtime was non-trivial.

`config/browsershot.php` exposes the knobs the controller honors:
- `BROWSERSHOT_CHROME_PATH` → `->setChromePath()` (only applied if set)
- `BROWSERSHOT_NODE_MODULE_PATH` → `->setNodeModulePath()` (defaults to project `node_modules`; on Cloud it points at a persisted copy)
- `BROWSERSHOT_NODE_BINARY` / `BROWSERSHOT_NPM_BINARY` (optional)

### Local development
`npm install` pulls Puppeteer, which downloads a Chromium that matches your machine. On macOS/Linux x86-64 that "just works" and you can leave `BROWSERSHOT_CHROME_PATH` empty.

### Laravel Cloud (ARM64) — why it needed special handling
Google publishes **no `linux-arm64` Chrome build**, so Puppeteer's auto-downloaded Chromium is an **x86-64 binary that can't execute** on Cloud's ARM64 runtime (`Exec format error`). Additionally, the Cloud **build runs unprivileged** (user `www-data`, no root/sudo, no apt indices) and **only `$HOME` (`/var/www`) persists** into the running app — so you cannot `apt install` Chromium or its libraries.

The working solution (see `deploy/`):
1. **`deploy/chromium.sh`** (wired up as a Laravel Cloud **build command**) uses **Playwright purely as a downloader** to fetch a native **arm64 Chromium** (Playwright compiles/hosts arm64 builds; Google doesn't), into `$HOME/.cache/ms-playwright`.
2. **`deploy/install-chromium-deps.cjs`** is a Node resolver that downloads Chromium's required **shared libraries** (`libglib`, `libgbm`, `libasound`, … + transitive deps) as Debian bookworm **arm64 `.deb`s, directly over HTTPS** (no apt). `chromium.sh` extracts them with `dpkg-deb -x` into `$HOME/chrome-deps`.
3. `chromium.sh` writes a launcher at **`/var/www/bin/chromium`** that exports `LD_LIBRARY_PATH` (pointing at the bundled libs) and execs the real Chromium. Browsershot is pointed at this launcher via `BROWSERSHOT_CHROME_PATH=/var/www/bin/chromium`.

Everything lands under `$HOME`, so it survives into runtime. The README's "Getting spatie/browsershot working with Laravel Cloud" section is the user-facing writeup of this.

Required Laravel Cloud config:
- **Build command:** `bash deploy/chromium.sh` (after the `composer`/`npm` steps)
- **Env vars:** `BROWSERSHOT_CHROME_PATH=/var/www/bin/chromium`, and optionally `PUPPETEER_SKIP_DOWNLOAD=true` to skip Puppeteer's pointless x86-64 download.

Key insight: the missing-libraries problem is intrinsic to Laravel Cloud's locked-down ARM64 image, **not** to Puppeteer — Playwright-as-the-engine would hit the same wall. So the fix is supplying a native arm64 Chromium + its libs, not switching browser libraries.

## Diagnostics

Custom artisan commands (`app/Console/Commands/`) for debugging the Browsershot/Chromium setup — useful both locally and via Laravel Cloud's command runner (which only allows `php artisan …`):

- `php artisan browsershot:diagnose` — the main one. Finds every candidate Chrome binary, checks its architecture vs the runtime, checks shared libraries, and actually tries to launch it. Prints a `WORKS ✓` / `arch mismatch` / `missing libraries` verdict per binary plus an overall conclusion.
- `php artisan browsershot:env` — dumps node/npm/chrome paths and versions Browsershot will use.
- `php artisan browsershot:check` — verifies Puppeteer is installed and loadable where Browsershot looks.
- `php artisan browsershot:locate` — hunts for node_modules / puppeteer / any Chrome binary on the box.
- `php artisan browsershot:test` — takes a real end-to-end test screenshot.

When debugging "screenshot fails on Cloud," start with `browsershot:diagnose`.

## Deployment

- **The `dev` branch auto-deploys to Laravel Cloud.** Push to `dev` to ship.
- Laravel Cloud builds a Docker image (PHP/Node fine-tuned for Laravel), runs the configured **build commands**, then **deploy commands**. It does **not** honor a repo `nixpacks.toml` for system packages — system deps must be handled via build commands writing into `$HOME` (see above).
- `.github/workflows/deploy.yml` is an **unrelated** legacy DigitalOcean deploy (different server, `main` branch); it is not the Laravel Cloud path.

## Conventions

- Match existing code style; the controllers are plain Laravel with small private helpers.
- Keep the Browsershot config env-driven (via `config/browsershot.php`) rather than hardcoding paths in the controller — the controller already guards `setChromePath` behind a config check so the same code works locally and on Cloud.

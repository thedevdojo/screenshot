#!/usr/bin/env bash
#
# Install a NATIVE arm64 Chromium for Browsershot/Puppeteer on Laravel Cloud.
#
# Why this exists:
#   Google publishes NO linux-arm64 Chrome build, so Puppeteer's own download is an
#   unusable x86-64 binary on Laravel Cloud's ARM64 runtime ("Exec format error").
#   Laravel Cloud does NOT honor nixpacks.toml / apt for persistent system packages,
#   and only persists $HOME (/var/www) from build into the running app.
#
#   Playwright DOES ship arm64 Chromium builds, so we use it purely as a downloader.
#   PLAYWRIGHT_BROWSERS_PATH puts the browser under $HOME/.cache, which persists, and
#   we expose a stable symlink at $HOME/bin/chromium that never changes across versions.
#
# Wire it up in the Laravel Cloud dashboard (Settings -> Deployments):
#   Build command:  bash deploy/chromium.sh
#   Env var:        BROWSERSHOT_CHROME_PATH=/var/www/bin/chromium
#
# Verify after deploy:  php artisan browsershot:diagnose
#
set -euo pipefail

export PLAYWRIGHT_BROWSERS_PATH="${HOME}/.cache/ms-playwright"

echo "==> Installing arm64 Chromium via Playwright into ${PLAYWRIGHT_BROWSERS_PATH}"
npx --yes playwright@latest install chromium

# The binary lives under a version-specific folder, so resolve it dynamically.
BIN="$(find "${PLAYWRIGHT_BROWSERS_PATH}" -type f -name chrome -path '*chrome-linux*' | sort -V | tail -n1)"
if [ -z "${BIN}" ]; then
  echo "!! Could not locate the installed Chromium binary under ${PLAYWRIGHT_BROWSERS_PATH}" >&2
  find "${PLAYWRIGHT_BROWSERS_PATH}" -maxdepth 3 -type d >&2 || true
  exit 1
fi

# Stable path so BROWSERSHOT_CHROME_PATH never needs to change between releases.
mkdir -p "${HOME}/bin"
ln -sf "${BIN}" "${HOME}/bin/chromium"
echo "==> Linked ${HOME}/bin/chromium -> ${BIN}"

echo "==> Architecture:"
file "${BIN}" || true

echo "==> Shared-library check (any 'not found' lines are libs the runtime is missing):"
ldd "${BIN}" | grep -i 'not found' && echo "    ^^ MISSING LIBS — Chromium will not launch until these are provided" || echo "    all libraries resolved ✓"

echo "==> Version probe:"
"${HOME}/bin/chromium" --no-sandbox --headless=new --version || echo "    (probe failed — see lib check above)"

echo "==> Done."

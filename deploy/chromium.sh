#!/usr/bin/env bash
#
# Install a NATIVE arm64 Chromium *with its shared libraries* for Browsershot/
# Puppeteer on Laravel Cloud.
#
# Why this exists:
#   Google publishes NO linux-arm64 Chrome, so Puppeteer's own download is an
#   unusable x86-64 binary on Laravel Cloud's ARM64 runtime ("Exec format error").
#   Playwright DOES ship arm64 Chromium builds, so we use it as a downloader.
#
#   BUT Laravel Cloud's base image is missing the shared libraries Chromium needs
#   (libglib, libgbm, libasound, ...), the build runs as a NON-root user, and only
#   $HOME (/var/www) persists into runtime — so we cannot `apt install` them into
#   /usr. Instead we `apt-get download` the arm64 .debs (no root required), extract
#   their libs into $HOME, and ship a wrapper that puts them on LD_LIBRARY_PATH.
#
# Wire it up in the Laravel Cloud dashboard (Settings -> Deployments):
#   Build command:  bash deploy/chromium.sh
#   Env var:        BROWSERSHOT_CHROME_PATH=/var/www/bin/chromium
#
# Verify after deploy:  php artisan browsershot:diagnose
#
set -euo pipefail

export PLAYWRIGHT_BROWSERS_PATH="${HOME}/.cache/ms-playwright"
DEPS_DIR="${HOME}/chrome-deps"
LIBPATH="${DEPS_DIR}/usr/lib/aarch64-linux-gnu:${DEPS_DIR}/lib/aarch64-linux-gnu:${DEPS_DIR}/usr/lib"

# ---------------------------------------------------------------------------
# 1. Download a native arm64 Chromium via Playwright.
# ---------------------------------------------------------------------------
echo "==> Installing arm64 Chromium via Playwright into ${PLAYWRIGHT_BROWSERS_PATH}"
npx --yes playwright@latest install chromium

BIN="$(find "${PLAYWRIGHT_BROWSERS_PATH}" -type f -name chrome -path '*chrome-linux*' | sort -V | tail -n1)"
if [ -z "${BIN}" ]; then
  echo "!! Could not locate the installed Chromium binary under ${PLAYWRIGHT_BROWSERS_PATH}" >&2
  exit 1
fi
echo "==> Chromium binary: ${BIN}"

# ---------------------------------------------------------------------------
# 2. Bundle Chromium's shared libraries into $HOME (no root needed).
# ---------------------------------------------------------------------------
echo "==> Downloading Chromium's runtime libraries (arm64 .debs) into ${DEPS_DIR}"
rm -rf "${DEPS_DIR}" /tmp/chrome-debs
mkdir -p "${DEPS_DIR}" /tmp/chrome-debs

# Packages that provide the libs Chromium was missing; --recurse pulls their deps.
PKGS="libglib2.0-0 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdbus-1-3 \
libxkbcommon0 libasound2 libgbm1 libpango-1.0-0 libxcomposite1 libxdamage1 \
libxfixes3 libxrandr2 libatspi2.0-0"

(
  cd /tmp/chrome-debs
  DEB_LIST="$(apt-cache depends --recurse --no-recommends --no-suggests \
    --no-conflicts --no-breaks --no-replaces --no-enhances ${PKGS} 2>/dev/null \
    | grep '^\w' | sort -u || true)"
  if [ -z "${DEB_LIST}" ]; then
    echo "!! apt package indices unavailable — could not resolve dependencies." >&2
    echo "   (If this happens, the build user may need 'apt-get update' first.)" >&2
  fi
  # shellcheck disable=SC2086
  apt-get download ${DEB_LIST} 2>/dev/null || echo "    (some packages were skipped)"
  for deb in *.deb; do [ -e "${deb}" ] && dpkg -x "${deb}" "${DEPS_DIR}"; done
)

# Use the system glibc (matching the ELF interpreter), never a bundled copy.
rm -f "${DEPS_DIR}"/lib/aarch64-linux-gnu/libc.so.* \
      "${DEPS_DIR}"/lib/aarch64-linux-gnu/ld-linux-* \
      "${DEPS_DIR}"/usr/lib/aarch64-linux-gnu/libc.so.* 2>/dev/null || true

# ---------------------------------------------------------------------------
# 3. Wrapper at a stable path that puts the bundled libs on LD_LIBRARY_PATH.
#    BROWSERSHOT_CHROME_PATH points here, so nothing else needs LD_LIBRARY_PATH.
# ---------------------------------------------------------------------------
mkdir -p "${HOME}/bin"
cat > "${HOME}/bin/chromium" <<EOF
#!/usr/bin/env bash
export LD_LIBRARY_PATH="${LIBPATH}\${LD_LIBRARY_PATH:+:\$LD_LIBRARY_PATH}"
exec "${BIN}" "\$@"
EOF
chmod +x "${HOME}/bin/chromium"
echo "==> Wrote launcher ${HOME}/bin/chromium -> ${BIN} (with bundled libs)"

# ---------------------------------------------------------------------------
# 4. Self-test so the build log proves whether it works.
# ---------------------------------------------------------------------------
echo "==> Shared-library check with bundled libs (any 'not found' below is still missing):"
LD_LIBRARY_PATH="${LIBPATH}" ldd "${BIN}" | grep -i 'not found' \
  && echo "    ^^ STILL MISSING — see above" \
  || echo "    all libraries resolved ✓"

echo "==> Version probe (through the launcher):"
"${HOME}/bin/chromium" --no-sandbox --headless=new --version \
  || echo "    (probe failed — see lib check above)"

echo "==> Done."

#!/usr/bin/env bash
#
# Install a NATIVE arm64 Chromium (binary + shared libraries) for Browsershot/
# Puppeteer on Laravel Cloud's ARM64 runtime.
#
# Google ships no linux-arm64 Chrome, so Puppeteer's own download is an unusable
# x86-64 binary. Playwright DOES ship arm64 Chromium, so we use it as a downloader.
# Laravel Cloud's base image then lacks Chrome's runtime libs, AND the build runs
# unprivileged (www-data, no root/sudo, no apt indices) with only $HOME persisted.
# So we fetch the libs' Debian .debs directly over HTTPS (deploy/install-chromium-deps.cjs),
# extract them into $HOME, and ship a launcher that exposes them via LD_LIBRARY_PATH.
#
# Dashboard (Settings -> Deployments):
#   Build command:  bash deploy/chromium.sh
#   Env var:        BROWSERSHOT_CHROME_PATH=/var/www/bin/chromium
#
# This script NEVER fails the deploy (always exits 0). Inspect its "==>" log lines
# and `php artisan browsershot:diagnose`.
#
set -uo pipefail   # deliberately NOT -e

export PLAYWRIGHT_BROWSERS_PATH="${HOME}/.cache/ms-playwright"
DEPS_DIR="${HOME}/chrome-deps"
LIBPATH="${DEPS_DIR}/usr/lib/aarch64-linux-gnu:${DEPS_DIR}/lib/aarch64-linux-gnu:${DEPS_DIR}/usr/lib"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Environment: user $(id -un 2>/dev/null) (uid $(id -u 2>/dev/null)), HOME=${HOME}"

# Fast path: if a working launcher + libs are already in place (e.g. a cached
# build layer), don't re-download ~300MB of browser + libs.
if [ -x "${HOME}/bin/chromium" ] && [ -d "${DEPS_DIR}" ] \
   && "${HOME}/bin/chromium" --no-sandbox --headless=new --version >/dev/null 2>&1; then
  echo "==> Chromium already installed and working — skipping download. ($(${HOME}/bin/chromium --no-sandbox --headless=new --version 2>/dev/null))"
  exit 0
fi

echo "==> Installing arm64 Chromium via Playwright"
npx --yes playwright@latest install chromium || echo "    (playwright reported an error)"
BIN="$(find "${PLAYWRIGHT_BROWSERS_PATH}" -type f -name chrome -path '*chrome-linux*' 2>/dev/null | sort -V | tail -n1)"
echo "    binary: ${BIN:-NONE FOUND}"

# Browsershot uses the full headed Chromium, not the headless-shell. Drop the
# headless-shell + ffmpeg Playwright also pulls, to keep the deploy image small.
rm -rf "${PLAYWRIGHT_BROWSERS_PATH}"/chromium_headless_shell-* \
       "${PLAYWRIGHT_BROWSERS_PATH}"/ffmpeg-* 2>/dev/null

echo "==> Fetching Chromium's shared libraries from Debian (direct HTTPS, no apt)"
rm -rf "${DEPS_DIR}" /tmp/chrome-debs 2>/dev/null
node "${SCRIPT_DIR}/install-chromium-deps.cjs" /tmp/chrome-debs || echo "    (resolver reported an error)"

echo "==> Extracting .deb packages into ${DEPS_DIR}"
mkdir -p "${DEPS_DIR}"
extracted=0
for deb in /tmp/chrome-debs/*.deb; do
  [ -e "${deb}" ] || continue
  if dpkg-deb -x "${deb}" "${DEPS_DIR}" 2>/dev/null; then
    extracted=$((extracted + 1))
  elif ( cd "${DEPS_DIR}" && ar p "${deb}" data.tar.xz 2>/dev/null | tar -xJ 2>/dev/null ); then
    extracted=$((extracted + 1))
  fi
done
# Never ship glibc/loader — the runtime's own must be used.
rm -f "${DEPS_DIR}"/lib/aarch64-linux-gnu/libc.so.* \
      "${DEPS_DIR}"/lib/aarch64-linux-gnu/ld-linux-* \
      "${DEPS_DIR}"/usr/lib/aarch64-linux-gnu/libc.so.* 2>/dev/null
echo "    extracted ${extracted} packages, $(find "${DEPS_DIR}" -name '*.so*' 2>/dev/null | wc -l | tr -d ' ') library files"

echo "==> Writing launcher /var/www/bin/chromium"
mkdir -p "${HOME}/bin"
if [ -n "${BIN}" ]; then
  cat > "${HOME}/bin/chromium" <<EOF
#!/usr/bin/env bash
export LD_LIBRARY_PATH="${LIBPATH}\${LD_LIBRARY_PATH:+:\$LD_LIBRARY_PATH}"
exec "${BIN}" "\$@"
EOF
  chmod +x "${HOME}/bin/chromium"
  echo "    launcher -> ${BIN}"
else
  echo "    skipped (no chromium binary)"
fi

echo "==> Self-test: shared libraries"
if [ -n "${BIN}" ]; then
  LD_LIBRARY_PATH="${LIBPATH}" ldd "${BIN}" 2>/dev/null | grep -i 'not found' \
    && echo "    ^^ STILL MISSING" \
    || echo "    all libraries resolved ✓"
  echo "==> Self-test: version probe"
  "${HOME}/bin/chromium" --no-sandbox --headless=new --version 2>&1 \
    || echo "    probe failed (see lib check above)"
fi

echo "==> Done (build continues regardless)."
exit 0

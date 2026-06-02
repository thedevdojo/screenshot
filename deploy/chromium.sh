#!/usr/bin/env bash
#
# Install a NATIVE arm64 Chromium (binary + shared libraries) for Browsershot/
# Puppeteer on Laravel Cloud's ARM64 runtime.
#
# Google ships no linux-arm64 Chrome, so Puppeteer's own download is an unusable
# x86-64 binary. Playwright DOES ship arm64 Chromium, so we use it as a downloader.
# Laravel Cloud's base image then lacks Chrome's runtime libs, the build is NON-root,
# and only $HOME persists — so we also try to obtain the libs and stage them so a
# launcher can expose them via LD_LIBRARY_PATH.
#
# Dashboard (Settings -> Deployments):
#   Build command:  bash deploy/chromium.sh
#   Env var:        BROWSERSHOT_CHROME_PATH=/var/www/bin/chromium
#
# This script intentionally NEVER fails the deploy (always exits 0); inspect its
# "==>" log lines and `php artisan browsershot:diagnose` to see what worked.
#
set -uo pipefail   # deliberately NOT -e

LIBS_PKGS="libglib2.0-0 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdbus-1-3 \
libxkbcommon0 libasound2 libgbm1 libpango-1.0-0 libxcomposite1 libxdamage1 \
libxfixes3 libxrandr2 libatspi2.0-0"

export PLAYWRIGHT_BROWSERS_PATH="${HOME}/.cache/ms-playwright"
DEPS_DIR="${HOME}/chrome-deps"
LIBPATH="${DEPS_DIR}/usr/lib/aarch64-linux-gnu:${DEPS_DIR}/lib/aarch64-linux-gnu:${DEPS_DIR}/usr/lib"

echo "==> Environment probe"
echo "    user:      $(id -un 2>/dev/null) (uid $(id -u 2>/dev/null))"
if [ "$(id -u 2>/dev/null)" = "0" ]; then
  SUDO=""; echo "    privilege: root"
elif sudo -n true 2>/dev/null; then
  SUDO="sudo"; echo "    privilege: passwordless sudo"
else
  SUDO=""; echo "    privilege: unprivileged (no root, no sudo)"
fi
echo "    apt lists: $(ls /var/lib/apt/lists/*Packages* 2>/dev/null | wc -l | tr -d ' ') index files present"

echo "==> Installing arm64 Chromium via Playwright"
npx --yes playwright@latest install chromium || echo "    (playwright install reported an error)"
BIN="$(find "${PLAYWRIGHT_BROWSERS_PATH}" -type f -name chrome -path '*chrome-linux*' 2>/dev/null | sort -V | tail -n1)"
echo "    binary: ${BIN:-NONE FOUND}"

echo "==> Populating apt indices (apt-get update)"
if ${SUDO} apt-get update -y >/dev/null 2>&1; then
  echo "    ok"
else
  echo "    FAILED — no way to refresh apt indices in this build (need root/sudo)"
fi

echo "==> Trying system-wide install of Chromium libs (works only if /usr persists)"
# shellcheck disable=SC2086
if ${SUDO} apt-get install -y --no-install-recommends ${LIBS_PKGS} >/dev/null 2>&1; then
  echo "    installed system-wide"
else
  echo "    unavailable"
fi

echo "==> Bundling libs into ${DEPS_DIR} (survives even if /usr does not persist)"
rm -rf "${DEPS_DIR}" /tmp/chrome-debs 2>/dev/null
mkdir -p "${DEPS_DIR}" /tmp/chrome-debs
# shellcheck disable=SC2086
DEB_LIST="$(apt-cache depends --recurse --no-recommends --no-suggests \
  --no-conflicts --no-breaks --no-replaces --no-enhances ${LIBS_PKGS} 2>/dev/null \
  | grep '^\w' | sort -u)"
if [ -n "${DEB_LIST}" ]; then
  (
    cd /tmp/chrome-debs || exit 0
    # shellcheck disable=SC2086
    apt-get download ${DEB_LIST} 2>/dev/null || true
    for d in *.deb; do [ -e "${d}" ] && dpkg -x "${d}" "${DEPS_DIR}"; done
  )
  rm -f "${DEPS_DIR}"/lib/aarch64-linux-gnu/libc.so.* \
        "${DEPS_DIR}"/lib/aarch64-linux-gnu/ld-linux-* \
        "${DEPS_DIR}"/usr/lib/aarch64-linux-gnu/libc.so.* 2>/dev/null
  echo "    bundled $(find "${DEPS_DIR}" -name '*.so*' 2>/dev/null | wc -l | tr -d ' ') library files"
else
  echo "    could not resolve deps — apt indices unavailable (see probe above)"
fi

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

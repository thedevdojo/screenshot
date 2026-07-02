const os = require('os');

// Google publishes no linux-arm64 Chrome build, so Puppeteer's own download is an
// unusable x86-64 binary there (e.g. Laravel Cloud's ARM64 runtime) — we supply a
// native arm64 Chromium ourselves via deploy/chromium.sh. So skip the pointless
// download on linux-arm64, but let Puppeteer download Chrome normally everywhere
// else (local macOS / x86 dev). An explicit PUPPETEER_SKIP_DOWNLOAD env var wins.
const uselessOnThisPlatform = os.platform() === 'linux' && os.arch() === 'arm64';

module.exports = {
  skipDownload:
    process.env.PUPPETEER_SKIP_DOWNLOAD === 'true' ||
    process.env.PUPPETEER_SKIP_DOWNLOAD === '1' ||
    uselessOnThisPlatform,
};

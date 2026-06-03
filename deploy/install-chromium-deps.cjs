// Resolve + download the Debian (bookworm) arm64 .deb closure for Chromium's
// shared libraries — WITHOUT apt.
//
// Laravel Cloud's build runs as an unprivileged user (www-data) with no sudo and
// no apt package indices, so `apt-get`/`apt-cache` cannot be used. But outbound
// HTTPS and Node both work, so we fetch the official Packages index directly,
// walk the dependency graph from a set of seed packages, and download every .deb.
// A separate step extracts them with `dpkg-deb -x` into $HOME (the only path that
// persists into the runtime), and a launcher exposes them via LD_LIBRARY_PATH.
//
// Usage:  node deploy/install-chromium-deps.cjs <output-dir>
// Never exits non-zero (must not fail the deploy); prints a RESOLVED summary line.

const https = require('https');
const zlib = require('zlib');
const fs = require('fs');
const path = require('path');

const OUT = process.argv[2] || '/tmp/chrome-debs';

// Packages that provide the libs Chromium reported missing on Laravel Cloud.
// Their transitive dependencies are resolved automatically below.
const SEEDS = [
  'libglib2.0-0', 'libatk1.0-0', 'libatk-bridge2.0-0', 'libcups2', 'libdbus-1-3',
  'libxkbcommon0', 'libasound2', 'libgbm1', 'libpango-1.0-0', 'libxcomposite1',
  'libxdamage1', 'libxfixes3', 'libxrandr2', 'libatspi2.0-0',
];

// Essential libs that are always present in the runtime and must NOT be shipped
// from here (especially glibc — overriding it would break the dynamic loader).
const SKIP = new Set([
  'libc6', 'libgcc-s1', 'libstdc++6', 'libc-bin', 'zlib1g', 'dpkg', 'debconf',
]);

// (Packages.gz URL, pool base URL). bookworm main alone covers Chromium's full
// library closure; the -security/-updates suites are intentionally omitted (their
// arm64 indices 404 and aren't needed).
const SOURCES = [
  ['https://deb.debian.org/debian/dists/bookworm/main/binary-arm64/Packages.gz', 'https://deb.debian.org/debian/'],
];

function get(url) {
  return new Promise((resolve, reject) => {
    https.get(url, (res) => {
      if ([301, 302, 307, 308].includes(res.statusCode) && res.headers.location) {
        return get(res.headers.location).then(resolve, reject);
      }
      if (res.statusCode !== 200) return reject(new Error('HTTP ' + res.statusCode));
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => resolve(Buffer.concat(chunks)));
    }).on('error', reject);
  });
}

// Strip version constraints / arch qualifiers: "libfoo (>= 1.2)" / "libfoo:any" -> "libfoo".
const cleanDep = (s) => s.trim().replace(/[:(].*/s, '').trim();

(async () => {
  const pkgs = new Map();      // name -> { file, base, deps:[] }
  const provides = new Map();  // virtual name -> real package name

  for (const [indexUrl, base] of SOURCES) {
    let raw;
    try {
      raw = await get(indexUrl);
    } catch (e) {
      console.error('  index unavailable (' + indexUrl + '): ' + e.message);
      continue;
    }
    const text = zlib.gunzipSync(raw).toString('utf8');
    for (const stanza of text.split('\n\n')) {
      const name = (stanza.match(/^Package: (.+)$/m) || [])[1];
      const file = (stanza.match(/^Filename: (.+)$/m) || [])[1];
      if (!name || !file || pkgs.has(name)) continue;
      const deps = ((stanza.match(/^Depends: (.+)$/m) || [])[1] || '')
        .split(',')
        .map((alt) => cleanDep(alt.split('|')[0]))
        .filter(Boolean);
      pkgs.set(name, { file, base, deps });
      for (const pv of ((stanza.match(/^Provides: (.+)$/m) || [])[1] || '').split(',')) {
        const v = cleanDep(pv);
        if (v && !provides.has(v)) provides.set(v, name);
      }
    }
  }

  if (pkgs.size === 0) {
    console.log('RESOLVED 0 pkgs (could not fetch any Debian index)');
    return;
  }

  const resolveName = (n) => (pkgs.has(n) ? n : provides.get(n) || null);

  const want = new Set();
  const queue = [...SEEDS];
  while (queue.length) {
    const name = resolveName(queue.shift());
    if (!name || want.has(name) || SKIP.has(name)) continue;
    want.add(name);
    for (const dep of pkgs.get(name).deps) {
      const r = resolveName(dep);
      if (r && !want.has(r) && !SKIP.has(r)) queue.push(r);
    }
  }

  fs.mkdirSync(OUT, { recursive: true });
  let downloaded = 0;
  for (const name of want) {
    const entry = pkgs.get(name);
    try {
      const deb = await get(entry.base + entry.file);
      fs.writeFileSync(path.join(OUT, path.basename(entry.file)), deb);
      downloaded++;
    } catch (e) {
      console.error('  download failed (' + name + '): ' + e.message);
    }
  }
  console.log('RESOLVED ' + want.size + ' pkgs, downloaded ' + downloaded + ' .deb files into ' + OUT);
})().catch((e) => {
  console.error('resolver fatal: ' + e.message);
  process.exit(0);
});

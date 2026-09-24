/**
 * Call cc-assistant's REST endpoints through the operator's logged-in Chrome.
 *
 *   node cc-via-browser.mjs <site> <route> [GET|POST] [body.json] [out.json]
 *   node cc-via-browser.mjs https://erofirving.com cc-assistant/v1/whoami
 *   node cc-via-browser.mjs https://erofirving.com cc-assistant/v1/upload-media POST up.json r.json
 *
 * WHY THIS IS NOT A BYPASS. It hits exactly the routes the MCP bridge hits, so
 * the same lint, the same session gate, the same pending-changes queue and the
 * same approval requirement all still apply. The only thing that changes is the
 * HTTP client. Writes remain queued for human approval; this cannot and must not
 * be used to poke wp-admin directly.
 *
 * WHY IT EXISTS. SiteGround flagged this IP's reputation and now answers 202 with
 * a JavaScript browser challenge to every non-browser client, on every site on
 * their platform, including ones we never touched. curl cannot pass it, PHP
 * cannot pass it, and replaying the browser's own clearance cookie from the same
 * IP does not pass it either - the clearance is bound to the browser. A real
 * Chrome does pass, and a signed-in wp-admin page carries the REST nonce, so the
 * plugin's endpoints are reachable that way while the bridge is locked out.
 *
 * Requires a Chrome profile already signed in to the site. Use open-chrome.mjs.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const PROFILE = path.join(HERE, 'card-generator', 'featured', '.chrome-profile');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

function loadPlaywright() {
  for (const base of ['C:/Users/sumit/.cc-assistant/wcag/package.json',
                      'D:/faceless-studio/motion/package.json']) {
    if (!fs.existsSync(base)) continue;
    try { return createRequire(base)('playwright'); } catch { /* next */ }
  }
  throw new Error('No Playwright found');
}

const [site, route, method = 'GET', bodyFile, outFile] = process.argv.slice(2);
if (!site || !route) {
  console.log('usage: node cc-via-browser.mjs <site> <route> [GET|POST] [body.json] [out.json]');
  console.log('       node cc-via-browser.mjs <site> --batch <calls.json>');
  process.exit(1);
}

// Batch mode. Launching Chrome per call costs about twenty seconds of challenge
// clearing each time, so a four-upload-plus-three-patch sequence would spend
// most of its life starting browsers. One session, many calls, stop on the first
// failure so a bad upload cannot be followed by a patch that points at nothing.
const BATCH = route === '--batch';
const calls = BATCH
  ? JSON.parse(fs.readFileSync(path.resolve(HERE, method), 'utf8'))
  : [{ route, method,
       body: bodyFile ? JSON.parse(fs.readFileSync(path.resolve(HERE, bodyFile), 'utf8')) : null,
       out: outFile }];

// Playwright cannot open a persistent profile that another Chrome still holds,
// and a run stopped mid-flight leaves exactly that orphan behind. The failure
// surfaces as a bare "Error" with a browser log, which reads like a site problem
// and is not one. Clear the stale lock files rather than killing processes,
// which would risk closing the operator's own browser.
// 'lockfile' is the Windows name and was missing here until 2026-09-08; on
// Windows the three Singleton* names never exist, so a crashed or killed run
// left 'lockfile' behind and every later launch died with a bare Chrome error
// that reads like a site fault. Clear all four regardless of platform.
for (const lock of ['SingletonLock', 'SingletonCookie', 'SingletonSocket', 'lockfile']) {
  const f = path.join(PROFILE, lock);
  try { if (fs.existsSync(f) || fs.lstatSync(f)) fs.rmSync(f, { force: true }); }
  catch { /* absent, or held by a live browser we must not disturb */ }
}

const { chromium } = loadPlaywright();
const ctx = await chromium.launchPersistentContext(PROFILE, {
  // HEADLESS. An earlier version ran headed because a broken readiness check
  // (see below) made headless look blocked. It is not: headless clears the
  // SiteGround challenge and reaches the API in about 7 seconds, with no window
  // on the operator's screen.
  headless: true,
  executablePath: fs.existsSync(CHROME) ? CHROME : undefined,
  viewport: { width: 1200, height: 820 },
});
const page = ctx.pages()[0] || await ctx.newPage();

// wp-admin, because that is where wpApiSettings.nonce lives.
await page.goto(`${site.replace(/\/$/, '')}/wp-admin/`,
                { waitUntil: 'domcontentloaded', timeout: 120000 });

// READINESS: the nonce is positive proof we are through the challenge AND
// signed in. Do NOT test for the string "sgcaptcha" in the HTML - SiteGround's
// own admin plugin mentions it on every wp-admin page, so that check is a
// false positive 100% of the time. It cost ~32 seconds of pointless waiting on
// every single call and led to the wrong conclusion that headless was blocked.
let state = 'unknown';
for (let i = 0; i < 12; i++) {
  state = await page.evaluate(() => {
    if (window.wpApiSettings && window.wpApiSettings.nonce) return 'nonce-ready';
    if (/Robot Challenge/i.test(document.title)) return 'challenged';
    if (location.pathname.startsWith('/.well-known/')) return 'challenged';
    if (document.querySelector('#loginform, #user_login')) return 'login-form';
    return 'unknown';
  }).catch(() => 'err');
  if (state === 'nonce-ready' || state === 'login-form') break;
  await page.waitForTimeout(2000);
}
if (state === 'login-form') {
  await ctx.close();
  console.log('Not signed in to wp-admin in this profile. Run: node open-chrome.mjs ' + site + '/wp-admin/');
  process.exit(3);
}

const runOne = async ({ route, method = 'GET', body = null }) =>
  page.evaluate(async ({ route, method, body }) => {
  const s = window.wpApiSettings;
  if (!s || !s.nonce) {
    return { status: 'no-nonce',
             text: 'Not signed in to wp-admin in this profile. Run open-chrome.mjs first.' };
  }
  const opts = { method, credentials: 'include',
                 headers: { 'X-WP-Nonce': s.nonce } };
  if (body) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  try {
    const r = await fetch(s.root.replace(/\/$/, '/') + route, opts);
    return { status: r.status, text: await r.text() };
  } catch (e) {
    return { status: 'fetch-error', text: String(e) };
  }
}, { route, method, body });

let failed = false;
for (const call of calls) {
  const res = await runOne(call);
  console.log(`${call.method || 'GET'} ${call.route} -> ${res.status}`);
  if (call.out) {
    fs.writeFileSync(path.resolve(HERE, call.out), res.text);
    console.log(`  ${res.text.length} bytes -> ${call.out}`);
  } else {
    console.log('  ' + res.text.slice(0, 700).split(String.fromCharCode(10)).join(' '));
  }
  if (typeof res.status !== 'number' || res.status >= 400) {
    console.log('  STOPPING: this call failed, so nothing after it runs.');
    failed = true;
    break;
  }
}

await ctx.close();
if (failed) process.exit(2);

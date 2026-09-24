/**
 * Open a real headed Chrome on a site and hold it open while a human signs in.
 *
 *   node open-chrome.mjs [url] [minutes]
 *
 * Uses the persistent profile at card-generator/featured/.chrome-profile so the
 * login survives into later runs. Prints the login state as it changes and
 * reports what the session can actually reach, rather than assuming that a
 * signed-in browser implies a working bridge - it does not. The cc-assistant
 * bridge is php.exe making a REST call with an application password; it has no
 * profile, no cookie jar and no JavaScript engine, so nothing a browser learns
 * can be lent to it. Verified: replaying this browser's own SiteGround clearance
 * cookie on a plain request from the same IP still returns 202.
 *
 * What a signed-in browser DOES buy is read access while the IP is challenged,
 * which is how the post inventory was fetched. Writes still go through the
 * plugin's pending-changes queue, and nothing here should ever be used to poke
 * wp-admin directly and bypass that.
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

const url = process.argv[2] || 'https://erofirving.com/wp-admin/';
const minutes = Number(process.argv[3] || 12);

const { chromium } = loadPlaywright();
const ctx = await chromium.launchPersistentContext(PROFILE, {
  headless: false,
  executablePath: fs.existsSync(CHROME) ? CHROME : undefined,
  viewport: null,                       // real window, so the login form is comfortable
  args: ['--start-maximized', '--disable-blink-features=AutomationControlled'],
});
const page = ctx.pages()[0] || await ctx.newPage();

console.log(`opening ${url}`);
await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 }).catch(() => {});

// SiteGround's challenge needs roughly twenty seconds of JavaScript. A shorter
// wait returns its "Robot Challenge Screen", which once got scraped as a logo.
for (let i = 0; i < 8; i++) {
  const challenged = await page.evaluate(() =>
    document.documentElement.innerHTML.includes('sgcaptcha') ||
    /Robot Challenge/i.test(document.title)).catch(() => false);
  if (!challenged) break;
  process.stdout.write('.');
  await page.waitForTimeout(4000);
}
console.log(`\nready: ${await page.title().catch(() => '?')}`);
console.log(`\nSign in in the window. Watching for up to ${minutes} minutes.\n`);

let wasIn = null;
const deadline = Date.now() + minutes * 60 * 1000;
while (Date.now() < deadline) {
  const state = await page.evaluate(() => ({
    url: location.href,
    loggedIn: !!document.querySelector('#wpadminbar, body.wp-admin #wpbody'),
    onLoginForm: !!document.querySelector('#loginform, #user_login'),
  })).catch(() => null);

  if (state && state.loggedIn !== wasIn) {
    wasIn = state.loggedIn;
    console.log(`[${new Date().toLocaleTimeString()}] ` +
      (state.loggedIn ? `SIGNED IN  (${state.url})`
        : state.onLoginForm ? 'at the login form' : `not signed in  (${state.url})`));

    if (state.loggedIn) {
      // What this session can actually reach, measured rather than assumed.
      const probe = await page.evaluate(async () => {
        const out = {};
        for (const [k, u] of [['public', '/wp-json/'],
                              ['plugin', '/wp-json/cc-assistant/v1/whoami'],
                              ['me', '/wp-json/wp/v2/users/me']]) {
          try { out[k] = (await fetch(u, { credentials: 'include' })).status; }
          catch (e) { out[k] = 'err'; }
        }
        return out;
      }).catch(() => ({}));
      console.log('    in-browser reach:', JSON.stringify(probe));
      console.log('    (the bridge is a separate PHP process and is unaffected)');
    }
  }
  await page.waitForTimeout(3000);
}

console.log('\nWatch window over. Leaving the browser open; close it yourself when done.');

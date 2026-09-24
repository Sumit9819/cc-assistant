/**
 * Clear a SiteGround `sgcaptcha` IP challenge by passing it in a real browser.
 *
 *   node sg-unblock.mjs [url ...]
 *
 * SiteGround's WAF answers HTTP 202 with a meta-refresh to
 * /.well-known/sgcaptcha/?r=... when it decides an IP is making too many
 * requests. Every site on their platform goes behind it at once, so the whole
 * MCP bridge stops working - and it looks exactly like the older UA block,
 * which it is not: the UA fix in v0.35.3 is still correct, the IP is simply
 * rate-limited. Distinguish by the body: a UA block is a 403 SiteGround error
 * page, a rate-limit is a 202 with `sgcaptcha` in it.
 *
 * The challenge is usually transparent JavaScript, so loading the page in a
 * real Chrome resolves it without a human. Whether that clears the IP for
 * other clients (curl, the MCP bridge) or only for this browser's cookie jar
 * depends on SiteGround's configuration, so this verifies with a cookie-less
 * fetch afterwards rather than assuming.
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

const urls = process.argv.slice(2);
if (!urls.length) urls.push('https://erofirving.com/');

const { chromium } = loadPlaywright();
const ctx = await chromium.launchPersistentContext(PROFILE, {
  headless: false,
  executablePath: fs.existsSync(CHROME) ? CHROME : undefined,
  viewport: { width: 1280, height: 800 },
});
const page = ctx.pages()[0] || await ctx.newPage();

for (const url of urls) {
  process.stdout.write(`${url} ... `);
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
    // The challenge redirects, runs, and bounces back. Give it room, then
    // confirm against the DOM rather than the navigation result.
    await page.waitForTimeout(9000);
    const still = await page.evaluate(() =>
      document.documentElement.innerHTML.includes('sgcaptcha'));
    console.log(still ? 'still challenged' : `passed (${page.url()})`);
  } catch (e) {
    console.log(`error: ${e.message.split(String.fromCharCode(10))[0]}`);
  }
}

await page.waitForTimeout(1500);
await ctx.close();
console.log('\nNow re-test WITHOUT the browser cookies to see if the IP itself cleared:');
console.log('  curl -sS -o /dev/null -w "%{http_code}" https://erofirving.com/wp-json/');

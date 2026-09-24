/**
 * Ground truth for a list of "Crawled - currently not indexed" URLs.
 * For each: final URL after redirects, HTTP status, canonical, robots meta,
 * title, H1, and main-content word count.
 *
 * A real browser UA because SiteGround's WAF 403s bot agents.
 */
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json');
const { chromium } = require('playwright');

const urls = fs.readFileSync(process.argv[2], 'utf8').split(/\r?\n/).filter(Boolean);
const browser = await chromium.launch();
const ctx = await browser.newContext({
  userAgent:
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
  viewport: { width: 1280, height: 900 },
});
const out = [];
for (const url of urls) {
  const page = await ctx.newPage();
  let rec = { url };
  try {
    const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    rec.status = resp.status();
    rec.final = page.url();
    rec.redirected = page.url().replace(/\/$/, '') !== url.replace(/\/$/, '');
    Object.assign(rec, await page.evaluate(() => {
      const can = document.querySelector('link[rel=canonical]');
      const rob = document.querySelector('meta[name=robots]');
      const h1 = [...document.querySelectorAll('h1')].map((h) => h.textContent.trim());
      // main content only: the Elementor post-content widget when present
      const main = document.querySelector('.elementor-widget-theme-post-content')
        || document.querySelector('main') || document.body;
      const words = (main.innerText || '').trim().split(/\s+/).length;
      return {
        canonical: can ? can.href : null,
        robots: rob ? rob.content : null,
        title: (document.title || '').slice(0, 70),
        h1count: h1.length,
        h1: h1[0] ? h1[0].slice(0, 60) : null,
        words,
      };
    }));
  } catch (e) {
    rec.error = String(e).slice(0, 90);
  }
  out.push(rec);
  await page.close();
  const canSelf = rec.canonical && rec.final &&
    rec.canonical.replace(/\/$/, '') === rec.final.replace(/\/$/, '');
  console.log(
    `${String(rec.status ?? 'ERR').padEnd(4)} ${rec.redirected ? 'REDIR' : '     '} ` +
    `${String(rec.words ?? '').padStart(5)}w  can:${canSelf ? 'self ' : (rec.canonical ? 'OTHER' : 'none ')} ` +
    `robots:${(rec.robots || '-').slice(0, 22).padEnd(22)} ${rec.url.replace('https://irvingwellnessclinic.com', '')}`
  );
  if (rec.canonical && !canSelf) console.log(`        canonical -> ${rec.canonical}`);
  if (rec.redirected) console.log(`        final     -> ${rec.final}`);
}
fs.writeFileSync('index_audit.json', JSON.stringify(out, null, 1));
await browser.close();

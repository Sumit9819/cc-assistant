import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const URL_ = 'https://eroflufkin.com/';
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const live = await (await (await b.newPage()).goto(URL_, { waitUntil: 'domcontentloaded', timeout: 120000 })).text();
// the fix, as SiteGround would render an excluded image: real src/srcset, no lazyload class
const fixed = live.replace(/<img([^>]*?)data-src="([^"]*ER-near-lufkin-tx\.jpeg)"([^>]*?)data-srcset="([^"]*)"([^>]*?)src="data:image\/gif[^"]*"([^>]*?)class="([^"]*?)\s*lazyload"/,
  (m, a, src, b1, srcset, c, d, cls) => `<img${a}src="${src}"${b1}srcset="${srcset}"${c}${d}class="${cls} skip-lazy" fetchpriority="high"`);
console.log('image un-lazied in simulation:', fixed !== live);
async function run(label, html) {
  const ctx = await b.newContext(devices['Pixel 7']);
  await ctx.addInitScript(() => { window.__cls = 0; window.__max = 0; new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) { window.__cls += e.value; window.__max = Math.max(window.__max, e.value); } }).observe({ type: 'layout-shift', buffered: true }); });
  const p = await ctx.newPage();
  await p.route(URL_, r => r.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: html }));
  await p.goto(URL_, { waitUntil: 'load', timeout: 120000 });
  const h1a = await p.evaluate(() => Math.round(document.querySelector('h1').getBoundingClientRect().top + scrollY));
  await p.waitForTimeout(9000);
  const r = await p.evaluate(() => ({ cls: +window.__cls.toFixed(4), max: +window.__max.toFixed(4), h1: Math.round(document.querySelector('h1').getBoundingClientRect().top + scrollY), imgH: (() => { const i = [...document.images].find(x => /ER-near-lufkin-tx/.test(x.currentSrc || x.src || x.getAttribute('data-src') || '')); return i ? Math.round(i.getBoundingClientRect().height) : null; })() }));
  console.log(`${label.padEnd(26)} CLS ${r.cls} (largest single shift ${r.max}) | H1 at load ${h1a}px -> after 9s ${r.h1}px | image height ${r.imgH}px`);
  await ctx.close();
}
await run('LIVE (lazy image)', live);
await run('FIX (image not lazy)', fixed);
await run('LIVE again', live);
await run('FIX again', fixed);
await b.close();

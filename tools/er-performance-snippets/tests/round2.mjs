import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });

// 1. Irving re-paste landed?  Lufkin image fix visible + CLS?
for (const [site, url] of [['Irving', 'https://erofirving.com/'], ['Lufkin', 'https://eroflufkin.com/']]) {
  for (let run = 1; run <= 2; run++) {
    const ctx = await b.newContext(devices['Pixel 7']);
    await ctx.addInitScript(() => { window.__cls = 0; new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); });
    const p = await ctx.newPage();
    const errors = []; p.on('pageerror', e => errors.push(e.message.slice(0, 60)));
    const r = await p.goto(url, { waitUntil: 'load', timeout: 180000 });
    const html = await r.text();
    await p.waitForTimeout(8000);
    const cls = await p.evaluate(() => +window.__cls.toFixed(3));
    const img = (html.match(/<img[^>]*ER-near-lufkin-tx\.jpeg[^>]*>/) || [''])[0];
    console.log(`${site} run${run}: jQuery Migrate ${/jquery-migrate/.test(html) ? 'STILL THERE' : 'gone'} | global styles ${/global-styles-inline/.test(html) ? 'STILL THERE' : 'gone'} | CLS ${cls} | JS errors ${errors.length}` +
      (site === 'Lufkin' ? ` | image 6458: ${/skip-lazy/.test(img) ? 'skip-lazy' : 'no skip-lazy'}, ${/data-src=/.test(img) ? 'still lazy' : 'loads normally'}` : ''));
    await ctx.close();
  }
}

// 2. Irving combined CSS: what is in it?
const ctx = await b.newContext(devices['Pixel 7']); const p = await ctx.newPage();
let cssUrl = null;
p.on('response', r => { if (/siteground-optimizer-combined-css/.test(r.url())) cssUrl = r.url(); });
await p.goto('https://erofirving.com/', { waitUntil: 'load', timeout: 180000 });
const css = await (await ctx.request.get(cssUrl)).text();
fs.writeFileSync('irving-combined-now.css', css);
// coverage: which rules does the homepage actually use (phone)?
await p.coverage?.startCSSCoverage?.();
await ctx.close();
console.log('\nIrving combined CSS:', cssUrl.split('/').pop(), css.length, 'bytes (uncompressed)');
await b.close();

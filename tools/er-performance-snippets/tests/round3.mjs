import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
fs.mkdirSync('r3shots', { recursive: true });
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });

const SITES = {
  'erofwhiterock.com': ['https://erofwhiterock.com/', 'https://erofwhiterock.com/contact-us/', 'https://erofwhiterock.com/es/'],
  'erofirving.com': ['https://erofirving.com/', 'https://erofirving.com/contact-us/'],
  'eroflufkin.com': ['https://eroflufkin.com/', 'https://eroflufkin.com/contact-us/'],
};

for (const [site, urls] of Object.entries(SITES)) {
  console.log(`\n################ ${site} ################`);
  for (const [i, url] of urls.entries()) {
    for (const [dev, opts] of [['phone', devices['Pixel 7']], ['desktop', { viewport: { width: 1440, height: 900 } }]]) {
      const ctx = await b.newContext(opts);
      await ctx.addInitScript(() => { try { localStorage.setItem('bcp_shown_autumn26', String(Date.now() + 9e9)); } catch (e) {} window.__cls = 0; new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); });
      const p = await ctx.newPage();
      const errors = []; p.on('pageerror', e => errors.push(e.message.slice(0, 70)));
      let bigFont = false; p.on('request', r => { if (/elementskit\.woff/.test(r.url())) bigFont = true; });
      let cssText = '';
      p.on('response', async r => { if (/siteground-optimizer-combined-css/.test(r.url())) { try { cssText = await r.text(); } catch {} } });
      const resp = await p.goto(url, { waitUntil: 'load', timeout: 180000 });
      const html = await resp.text();
      await p.waitForTimeout(3500);
      const f = await p.evaluate(() => ({
        h1: [...document.querySelectorAll('h1')].map(h => h.textContent.trim().slice(0, 50)),
        visibleH1: [...document.querySelectorAll('h1')].filter(h => h.getBoundingClientRect().height > 0).length,
        pageTitleEl: !!document.querySelector('.page-header .entry-title, header.page-header'),
        height: document.documentElement.scrollHeight, dom: document.getElementsByTagName('*').length, cls: +window.__cls.toFixed(3),
      }));
      const count = re => (html.match(re) || []).length;
      const row = {
        status: resp.status(), titles: count(/<title>/g), metaDesc: count(/<meta name="description"/g),
        h1: f.h1.length + (f.h1.length ? ` "${f.h1[0]}"` : ''), helloPageTitle: f.pageTitleEl,
        helloCss: ['assets/css/reset', 'assets/css/theme', 'header-footer'].filter(k => html.includes(k)).join('+') || 'none (combined)',
        feedLinks: count(/application\/rss\+xml/g), jsonld: count(/application\/ld\+json/g), webPageSchema: /"@type":\s*"WebPage",\s*"name"/.test(html),
        errors: errors.length ? errors.join(' ; ') : 'none', cls: f.cls, height: f.height, dom: f.dom,
      };
      if (site === 'erofirving.com') { row.bigFont = bigFont; row.iconSheetInCombined = /\.icon-yelp-1::?before|icon-back_up/.test(cssText) ? 'still there' : 'removed'; row.inlineIcons = html.includes('er-ekit-icons'); }
      if (site === 'eroflufkin.com' && i === 0) { const img = (html.match(/<img[^>]*ER-near-lufkin-tx\.jpeg[^>]*>/) || [''])[0]; row.image6458 = /data-src=/.test(img) ? 'still lazy' : 'loads normally'; }
      console.log(`${url.replace('https://', '').padEnd(30)} ${dev.padEnd(7)} ${JSON.stringify(row)}`);
      if (site === 'erofwhiterock.com' && i === 0) await p.screenshot({ path: `r3shots/wr-home-${dev}.png`, clip: { x: 0, y: 0, width: opts.viewport ? opts.viewport.width : 412, height: dev === 'phone' ? 915 : 900 } });
      await ctx.close();
    }
  }
}

// Lufkin feed redirect (custom theme code, now served by the safety-net snippet)
const ctx = await b.newContext(); const p = await ctx.newPage();
for (const u of ['https://eroflufkin.com/feed/', 'https://eroflufkin.com/contact-us/feed/']) {
  const r = await ctx.request.get(u, { maxRedirects: 0, timeout: 60000 }).catch(e => null);
  console.log(`\nLufkin ${u}: ${r ? r.status() + ' -> ' + (r.headers()['location'] || '(no redirect)') : 'request failed'}`);
}
await b.close();

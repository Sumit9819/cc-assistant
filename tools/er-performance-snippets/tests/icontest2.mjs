import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');

const replacement = fs.readFileSync('irving-icons-replacement.css', 'utf8');
const stripped = fs.readFileSync('irving-combined-stripped.css', 'utf8');
const PAGES = ['https://erofirving.com/', 'https://erofirving.com/contact-us/', 'https://erofirving.com/24-hour-er-in-irving-8-emergencies-that-cant-wait/'];
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });

async function measure(url, opts, patched) {
  const ctx = await b.newContext(opts);
  const p = await ctx.newPage();
  const errors = []; p.on('pageerror', e => errors.push(e.message.slice(0, 60)));
  let bigFont = false;
  p.on('request', r => { if (/elementskit\.woff/.test(r.url())) bigFont = true; });
  if (patched) {
    await p.route(/siteground-optimizer-combined-css-.*\.css/, r => r.fulfill({ status: 200, contentType: 'text/css', body: stripped }));
    await p.route(url, async r => {
      const resp = await r.fetch();
      let html = await resp.text();
      html = html.replace(/<style id="er-ekit-icon-subset">[\s\S]*?<\/style>/, '');
      html = html.replace('</head>', `<style id="er-ekit-icons">${replacement}</style></head>`);
      await r.fulfill({ response: resp, body: html, headers: { ...resp.headers(), 'content-type': 'text/html; charset=UTF-8' } });
    });
  }
  await p.goto(url, { waitUntil: 'load', timeout: 180000 });
  await p.evaluate(() => document.fonts.ready);
  await p.waitForTimeout(2500);
  const icons = await p.evaluate(() => [...document.querySelectorAll('.icon[class*="icon-"]')].map(el => {
    const r = el.getBoundingClientRect(); const s = getComputedStyle(el, '::before');
    return { cls: [...el.classList].find(c => c.startsWith('icon-')), visible: r.width > 0 && r.height > 0,
      w: Math.round(r.width * 10) / 10, h: Math.round(r.height * 10) / 10,
      content: s.content, font: s.fontFamily, size: s.fontSize, lh: s.lineHeight, weight: s.fontWeight, color: s.color };
  }));
  const fontOk = await p.evaluate(() => [...document.fonts].filter(f => /elementskit/i.test(f.family)).map(f => f.status));
  await ctx.close();
  return { icons, errors, bigFont, fontOk };
}

for (const url of PAGES) {
  for (const [dev, opts] of [['phone', devices['Pixel 7']], ['desktop', { viewport: { width: 1440, height: 900 } }]]) {
    const live = await measure(url, opts, false);
    const test = await measure(url, opts, true);
    let diffs = 0; const examples = [];
    const n = Math.max(live.icons.length, test.icons.length);
    for (let i = 0; i < n; i++) {
      const a = JSON.stringify(live.icons[i]), c = JSON.stringify(test.icons[i]);
      if (a !== c) { diffs++; if (examples.length < 2) examples.push({ live: live.icons[i], test: test.icons[i] }); }
    }
    const vis = test.icons.filter(x => x.visible).length;
    console.log(`${url.replace('https://erofirving.com', '').slice(0, 40).padEnd(40)} ${dev.padEnd(7)} icons ${live.icons.length}/${test.icons.length} (visible ${vis}) | differences: ${diffs} | big font requested live=${live.bigFont} test=${test.bigFont} | icon font status test: ${test.fontOk} | JS errors ${live.errors.length}/${test.errors.length}`);
    for (const e of examples) console.log('     DIFF', JSON.stringify(e));
  }
}
await b.close();

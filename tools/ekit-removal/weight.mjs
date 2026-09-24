import { createRequire } from 'node:module';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
for (const site of ['erofirving.com', 'eroflufkin.com', 'erofwhiterock.com']) {
  const ctx = await b.newContext(devices['Pixel 7']);
  const p = await ctx.newPage();
  const reqs = [];
  p.on('response', async r => { try { const buf = await r.body(); reqs.push({ url: r.url(), bytes: buf.length }); } catch {} });
  let combined = '';
  p.on('response', async r => { if (/siteground-optimizer-combined-css/.test(r.url())) { try { combined = await r.text(); } catch {} } });
  await p.goto(`https://${site}/`, { waitUntil: 'load', timeout: 180000 });
  await p.waitForTimeout(3000);
  const ekitReq = reqs.filter(r => /elementskit/i.test(r.url));
  // ekit share of the combined CSS: blocks whose selectors mention ekit/elementskit
  const rules = combined.match(/[^{}]+\{[^{}]*\}/g) || [];
  let ekitBytes = 0, total = 0;
  for (const r of rules) { total += r.length; if (/ekit|elementskit/i.test(r.split('{')[0])) ekitBytes += r.length; }
  const media = [...combined.matchAll(/@media[^{]+\{((?:[^{}]|\{[^{}]*\})*)\}/g)];
  let ekitMedia = 0;
  for (const m of media) for (const r of m[1].match(/[^{}]+\{[^{}]*\}/g) || []) if (/ekit|elementskit/i.test(r.split('{')[0])) ekitMedia += r.length;
  console.log(`${site}: combined CSS ${Math.round(combined.length / 1024)}KB | ElementsKit rules ~${Math.round((ekitBytes + ekitMedia) / 1024)}KB | separate ekit requests: ${ekitReq.length ? ekitReq.map(r => Math.round(r.bytes / 1024) + 'KB ' + r.url.split('/').pop()).join(', ') : 'none'}`);
  await ctx.close();
}
await b.close();

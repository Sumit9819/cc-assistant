import { createRequire } from 'node:module';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch();
for (const site of ['erofirving.com', 'eroflufkin.com']) {
  const ctx = await b.newContext(devices['Pixel 7']); const p = await ctx.newPage();
  const fonts = [];
  p.on('response', async r => { if (r.request().resourceType() === 'font') { let len = 0; try { len = (await r.body()).length; } catch {} fonts.push(`${Math.round(len / 1024)}KB ${r.headers()['content-type'] || ''} ${r.url().split('/').pop()}`); } });
  let css = '';
  p.on('response', async r => { if (/siteground-optimizer-combined-css/.test(r.url())) { try { css = await r.text(); } catch {} } });
  await p.goto(`https://${site}/`, { waitUntil: 'load', timeout: 120000 }); await p.waitForTimeout(3000);
  const ff = [...css.matchAll(/@font-face\s*\{[^}]*elementskit[^}]*\}/gi)].map(m => m[0].replace(/\s+/g, ' ').slice(0, 400));
  const uses = await p.evaluate(() => [...document.querySelectorAll('i[class*="icon-"], .icon')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0; }).length);
  console.log(`\n== ${site}: fonts downloaded on phone:`, fonts);
  console.log('   ekit @font-face in combined CSS:', ff.length ? ff : 'none');
  console.log('   visible ekit icon elements on the phone homepage:', uses);
  await ctx.close();
}
await b.close();

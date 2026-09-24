import { createRequire } from 'node:module';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
for (const site of ['eroflufkin.com', 'erofirving.com']) {
  const ctx = await b.newContext(devices['Pixel 7']); const p = await ctx.newPage();
  const fonts = [];
  p.on('response', async r => { if (r.request().resourceType() === 'font') { let n = 0; try { n = (await r.body()).length; } catch {} fonts.push(`${Math.round(n/1024)}KB ${r.url().split('/').pop().slice(0,40)}`); } });
  await p.goto(`https://${site}/`, { waitUntil: 'load', timeout: 180000 });
  await p.waitForTimeout(3500);
  const glyphs = await p.evaluate(() => [...document.querySelectorAll('i[class*="icon-"]')].filter(e => e.getBoundingClientRect().width > 0).map(e => e.className).slice(0, 12));
  console.log(`${site}: fonts=${JSON.stringify(fonts)}`);
  console.log(`   visible font-glyph icons: ${JSON.stringify(glyphs)}`);
  await ctx.close();
}
await b.close();

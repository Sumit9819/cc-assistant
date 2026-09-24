import { createRequire } from 'node:module';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch(); const p = await b.newPage();
for (const u of ['https://eroflufkin.com/', 'https://eroflufkin.com/?fresh=' + Date.now()]) {
  const r = await p.goto(u, { waitUntil: 'domcontentloaded', timeout: 120000 });
  const h = await r.text(); const img = (h.match(/<img[^>]*ER-near-lufkin-tx\.jpeg[^>]*>/) || [''])[0];
  const hd = r.headers();
  console.log(`${u.replace('https://eroflufkin.com', '') || '/'}: skip-lazy=${/skip-lazy/.test(img)} lazy=${/data-src=/.test(img)} loading=${(img.match(/loading="(\w+)"/) || [])[1]} | sg-f-cache=${hd['sg-f-cache'] || '-'} proxy=${hd['x-proxy-cache'] || '-'}`);
}
await b.close();

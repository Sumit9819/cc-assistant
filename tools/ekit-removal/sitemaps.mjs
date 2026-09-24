import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const ctx = await b.newContext();
const out = {};
for (const site of ['erofirving.com', 'erofwhiterock.com', 'eroflufkin.com', 'irvingwellnessclinic.com']) {
  const urls = new Set();
  try {
    const idx = await (await ctx.request.get(`https://${site}/sitemap_index.xml`, { timeout: 60000 })).text();
    const maps = [...idx.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]).filter(u => !/category|tag|author/.test(u));
    for (const m of maps) {
      const x = await (await ctx.request.get(m, { timeout: 60000 })).text();
      for (const u of [...x.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m2 => m2[1])) urls.add(u);
    }
  } catch (e) { console.log(site, 'sitemap error', e.message.slice(0, 60)); }
  out[site] = [...urls];
  console.log(site, out[site].length, 'urls');
}
fs.writeFileSync('urls.json', JSON.stringify(out, null, 1));
await b.close();

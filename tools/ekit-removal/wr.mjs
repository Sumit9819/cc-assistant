import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const ctx = await b.newContext();
const all = JSON.parse(fs.readFileSync('urls.json', 'utf8'));
const urls = new Set();
for (let attempt = 0; attempt < 3 && urls.size === 0; attempt++) {
  try {
    const idx = await (await ctx.request.get('https://erofwhiterock.com/sitemap_index.xml', { timeout: 90000 })).text();
    const maps = [...idx.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]).filter(u => !/category|tag|author/.test(u));
    for (const m of maps) {
      const x = await (await ctx.request.get(m, { timeout: 90000 })).text();
      for (const u of [...x.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m2 => m2[1])) urls.add(u);
    }
  } catch (e) { console.log('attempt', attempt, e.message.slice(0, 60)); await new Promise(r => setTimeout(r, 3000)); }
}
all['erofwhiterock.com'] = [...urls];
fs.writeFileSync('urls.json', JSON.stringify(all, null, 1));
console.log('erofwhiterock.com', urls.size, 'urls');
await b.close();

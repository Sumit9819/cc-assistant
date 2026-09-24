import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const ctx = await b.newContext();
const h = await (await ctx.request.get('https://erofirving.com/', { timeout: 90000 })).text();
fs.writeFileSync('irving-home.html', h);
// every elementor document wrapper on the page
for (const m of h.matchAll(/data-elementor-type="([a-z-]+)" data-elementor-id="(\d+)"[^>]*/g)) console.log('document:', m[1], 'id', m[2], '|', m[0].slice(0, 140));
for (const m of h.matchAll(/<div[^>]*data-elementor-(type|id)="[^"]*"[^>]*class="[^"]*elementor[^"]*"[^>]*>/g)) console.log('wrapper:', m[0].replace(/\s+/g, ' ').slice(0, 200));
const iNav = h.indexOf('ekit-nav-menu');
console.log('\n--- 1200 chars BEFORE the nav widget ---\n', h.slice(Math.max(0, iNav - 1200), iNav).replace(/\s+/g, ' ').slice(-1200));
const iSoc = h.indexOf('elementskit-social-media');
console.log('\n--- social widget context ---\n', h.slice(Math.max(0, iSoc - 500), iSoc + 900).replace(/\s+/g, ' '));
console.log('\nheader/footer tags present:', /<header/.test(h), /<footer/.test(h));
console.log('ekit template markers:', [...new Set((h.match(/ekit[a-z-]*template[a-z-]*/g) || []))]);
await b.close();

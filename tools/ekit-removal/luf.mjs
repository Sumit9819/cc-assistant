import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const ctx = await b.newContext();
for (const site of ['eroflufkin.com', 'erofwhiterock.com']) {
  const h = await (await ctx.request.get(`https://${site}/`, { timeout: 90000 })).text();
  fs.writeFileSync(`${site}-home.html`, h);
  console.log(`\n============== ${site}`);
  for (const m of h.matchAll(/data-elementor-type="([a-z-]+)" data-elementor-id="(\d+)"[^>]*data-elementor-post-type="([a-z_]+)"/g)) console.log('  document:', m[1], 'id', m[2], 'post type', m[3]);
  // widgets inside header and footer
  for (const part of ['header', 'footer']) {
    const re = new RegExp(`<(header|footer|div)[^>]*data-elementor-type="${part}"[\s\S]*?</(header|footer|div)>\s*(?=<)`);
    const start = h.indexOf(`data-elementor-type="${part}"`);
    if (start < 0) { console.log(`  no ${part} location`); continue; }
    const chunk = h.slice(start, start + 20000);
    const widgets = [...new Set([...chunk.matchAll(/data-widget_type="([^"]+)"/g)].map(m => m[1]))];
    console.log(`  ${part} widgets:`, widgets.join(', '));
  }
  console.log('  ekit template markers:', [...new Set(h.match(/ekit-template-content-[a-z-]+/g) || [])]);
  console.log('  megamenu panels in markup:', (h.match(/elementskit-megamenu-panel/g) || []).length, '| ekit_menu:', (h.match(/ekit_menu_responsive/g) || []).length);
  console.log('  ekit js/css requested:', [...new Set([...h.matchAll(/(?:src|href)=['"][^'"]*(elementskit[^'"\/]*\/[^'"]*)['"]/g)].map(m => m[1]))].slice(0, 8));
}
await b.close();

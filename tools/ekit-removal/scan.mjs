import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const ctx = await b.newContext();
const ALL = JSON.parse(fs.readFileSync('urls.json', 'utf8'));
const report = {};
for (const [site, urls] of Object.entries(ALL)) {
  const widgets = new Map();       // widgetType -> {count, pages:Set}
  const ekitPages = new Set();
  const markers = {};
  const iconClasses = new Map();
  let fetched = 0; const failed = [];
  const bump = (m, k, u) => { if (!m.has(k)) m.set(k, { count: 0, pages: new Set() }); const e = m.get(k); e.count++; if (e.pages.size < 6) e.pages.add(u.replace(`https://${site}`, '') || '/'); };
  async function grab(u) {
    try {
      const h = await (await ctx.request.get(u, { timeout: 90000 })).text();
      for (const m of h.matchAll(/data-widget_type="([^"]+)"/g)) {
        const t = m[1].replace(/\.default$/, '');
        if (/^(elementskit|ekit)/.test(t)) { bump(widgets, t, u); ekitPages.add(u); }
      }
      for (const m of h.matchAll(/\bicon icon-([a-z0-9-]+)/g)) bump(iconClasses, m[1], u);
      for (const [k, re] of Object.entries({
        ekitHeaderFooterTemplate: /ekit-template-content-header-footer|elementskit-header|elementskit-footer/,
        ekitNavbar: /elementskit-navbar-nav/, ekitHamburger: /elementskit-menu-hamburger/, ekitMegaMenu: /ekit-megamenu|elementskit-megamenu/,
        ekitOffcanvas: /elementskit-offcanvas|elementskit-menu-offcanvas/, ekitSticky: /elementskit-sticky/,
        proHeaderLoc: /elementor-location-header/, proFooterLoc: /elementor-location-footer/,
        ekitWidgetCss: /elementskit-lite\/widgets/, ekitIconPack: /elementskit-icon-pack/, ekitCommonCss: /ekit-widget-styles|elementskit-lite[^"']*common/,
      })) if (re.test(h)) markers[k] = (markers[k] || 0) + 1;
      fetched++;
    } catch (e) { failed.push(u); }
  }
  const q = [...urls];
  await Promise.all(Array.from({ length: 6 }, async () => { while (q.length) await grab(q.shift()); }));
  report[site] = {
    urls: urls.length, fetched, failed: failed.length,
    pagesWithEkitWidgets: ekitPages.size,
    widgets: Object.fromEntries([...widgets.entries()].sort((a, c) => c[1].count - a[1].count).map(([k, v]) => [k, { count: v.count, pages: [...v.pages] }])),
    icons: Object.fromEntries([...iconClasses.entries()].sort((a, c) => c[1].count - a[1].count).map(([k, v]) => [k, v.count])),
    markers,
  };
  console.log(`\n===== ${site}: ${fetched}/${urls.length} fetched, ${ekitPages.size} pages use ElementsKit widgets`);
  console.log('   widgets:', JSON.stringify(report[site].widgets, null, 1).slice(0, 1800));
  console.log('   markers (pages where seen):', JSON.stringify(markers));
  console.log('   icon classes:', JSON.stringify(report[site].icons));
}
fs.writeFileSync('scan.json', JSON.stringify(report, null, 1));
await b.close();

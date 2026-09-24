import { createRequire } from 'node:module';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const ctx = await b.newContext();
for (const site of ['erofirving.com', 'erofwhiterock.com', 'eroflufkin.com']) {
  const h = await (await ctx.request.get(`https://${site}/`, { timeout: 90000 })).text();
  console.log(`\n================ ${site}`);
  console.log('elementor-pro assets:', /elementor-pro/.test(h), '| pro version tag:', (h.match(/Elementor Pro[^"]{0,30}/) || [])[0] || '-');
  console.log('generator:', (h.match(/<meta name="generator" content="Elementor[^"]*"/) || ['-'])[0].slice(0, 200));
  for (const re of [/elementor-location-header/, /elementskit-navbar-nav/, /ekit-template-content-header-footer/, /data-elementor-type="[a-z-]+"/g]) {
    const m = h.match(re);
    if (m) console.log(' ', re.source.slice(0, 40), '->', typeof m[0] === 'string' ? [...new Set(h.match(re))].slice(0, 5).join(' ') : '');
  }
  const i = h.indexOf('elementskit-navbar-nav');
  if (i > -1) console.log('  navbar context:', h.slice(Math.max(0, i - 700), i + 200).replace(/\s+/g, ' ').slice(-800));
  const ekitCss = [...h.matchAll(/href=['"]([^'"]*elementskit[^'"]*)['"]/g)].map(m => m[1].split('/').slice(-3).join('/'));
  const ekitJs = [...h.matchAll(/src=['"]([^'"]*elementskit[^'"]*)['"]/g)].map(m => m[1].split('/').slice(-3).join('/'));
  console.log('  ekit css files in HTML:', [...new Set(ekitCss)]);
  console.log('  ekit js files in HTML:', [...new Set(ekitJs)]);
  console.log('  header markup start:', (h.match(/<header[^>]*>[\s\S]{0,400}/) || ['-'])[0].replace(/\s+/g, ' '));
}
await b.close();

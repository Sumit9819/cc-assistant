import { createRequire } from 'node:module';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch(); const p = await b.newPage();
for (const u of ['https://erofwhiterock.com/', 'https://erofwhiterock.com/contact-us/', 'https://erofwhiterock.com/es/']) {
  const r = await p.goto(u, { waitUntil: 'domcontentloaded', timeout: 120000 }).catch(() => null);
  if (!r) { console.log(u, 'failed'); continue; }
  const h = await r.text();
  const titles = h.match(/<title>[^<]*<\/title>/g) || [];
  const hello = ['hello-elementor/assets/css/reset', 'hello-elementor/assets/css/theme', 'header-footer', 'hello-elementor/style.css'].filter(k => h.includes(k));
  console.log(`${u.padEnd(40)} status ${r.status()} | <title> tags: ${titles.length} ${titles[0] ? titles[0].slice(0, 70) : ''} | h1 count: ${(h.match(/<h1\b/g) || []).length} | Hello theme CSS in page: ${hello.join(', ') || 'none'}`);
}
await b.close();

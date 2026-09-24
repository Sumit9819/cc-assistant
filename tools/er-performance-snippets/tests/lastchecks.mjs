import { createRequire } from 'node:module';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });

// 1. Irving hero: which background at which width
const html = await (await (await b.newPage()).goto('https://erofirving.com/', { waitUntil: 'domcontentloaded', timeout: 120000 })).text();
for (const w of [766, 767, 768, 769, 1024, 1025]) {
  const p = await b.newPage({ viewport: { width: w, height: 800 } });
  await p.route('https://erofirving.com/', r => r.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: html }));
  await p.goto('https://erofirving.com/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await p.waitForFunction(() => { const e = document.querySelector('.elementor-element-62713a4'); return e && getComputedStyle(e).backgroundImage !== 'none'; }, null, { timeout: 60000 }).catch(() => {});
  const bg = await p.evaluate(() => getComputedStyle(document.querySelector('.elementor-element-62713a4')).backgroundImage.split('/').pop().replace(/["')]/g, ''));
  console.log(`Irving hero at ${w}px wide: ${bg}`);
  await p.close();
}

// 2. jQuery Migrate removal on White Rock and Lufkin
const pages = {
  'erofwhiterock.com': ['https://erofwhiterock.com/', 'https://erofwhiterock.com/contact-us/'],
  'eroflufkin.com': ['https://eroflufkin.com/', 'https://eroflufkin.com/contact-us/'],
};
for (const [site, urls] of Object.entries(pages)) {
  for (const url of urls) {
    const live = await (await (await b.newPage()).goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 }).catch(() => null))?.text();
    if (!live) { console.log(url, 'could not load'); continue; }
    const noMigrate = live.replace(/<script[^>]*jquery-migrate(\.min)?\.js[^>]*><\/script>/g, '');
    for (const [variant, h] of [['with migrate', live], ['WITHOUT migrate', noMigrate]]) {
      const ctx = await b.newContext(devices['Pixel 7']);
      const p = await ctx.newPage();
      const errors = []; const migrateWarnings = [];
      p.on('pageerror', e => errors.push(e.message.slice(0, 90)));
      p.on('console', m => { if (/JQMIGRATE|deprecated/i.test(m.text())) migrateWarnings.push(m.text().slice(0, 90)); });
      await p.route(url, r => r.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: h }));
      await p.goto(url, { waitUntil: 'load', timeout: 120000 });
      await p.mouse.move(100, 100); await p.waitForTimeout(6000);
      const toggle = p.locator('.elementor-menu-toggle:visible, .elementskit-menu-hamburger:visible, .ekit-menu-nav-link.elementskit-menu-hamburger:visible').first();
      let menu = 'no toggle found';
      if (await toggle.count()) {
        const before = await p.evaluate(() => document.body.innerHTML.length);
        await toggle.click().catch(() => {}); await p.waitForTimeout(900);
        menu = await p.evaluate(() => {
          const t = document.querySelector('.elementor-menu-toggle'); if (t && t.getAttribute('aria-expanded') === 'true') return 'opened (elementor)';
          const oc = document.querySelector('.elementskit-menu-offcanvas-elements, .elementskit-menu-container'); if (oc && /active/.test(oc.className)) return 'opened (elementskit)';
          const vis = [...document.querySelectorAll('.elementor-nav-menu--dropdown, .elementskit-menu-container')].some(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 50 && getComputedStyle(e).visibility !== 'hidden'; });
          return vis ? 'menu panel visible' : 'did NOT open';
        });
      }
      const hasMigrate = await p.evaluate(() => !!(window.jQuery && jQuery.migrateVersion));
      console.log(`${url.padEnd(38)} ${variant.padEnd(16)} jQuery.migrate loaded=${hasMigrate} | JS errors: ${errors.length ? errors.join(' ; ') : 'none'} | migrate warnings: ${migrateWarnings.length} | mobile menu: ${menu}`);
      await ctx.close();
    }
  }
}
await b.close();

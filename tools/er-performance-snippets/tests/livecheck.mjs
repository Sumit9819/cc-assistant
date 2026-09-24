import { createRequire } from 'node:module';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');

const SITES = {
  'erofirving.com': ['https://erofirving.com/', 'https://erofirving.com/contact-us/'],
  'erofwhiterock.com': ['https://erofwhiterock.com/', 'https://erofwhiterock.com/contact-us/'],
  'eroflufkin.com': ['https://eroflufkin.com/', 'https://eroflufkin.com/contact-us/'],
};
const TAGS = /googletagmanager|connect\.facebook|clarity\.ms|doubleclick|google-analytics/;
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });

for (const [site, urls] of Object.entries(SITES)) {
  console.log(`\n################ ${site} ################`);
  for (const url of urls) {
    const isHome = url.endsWith('.com/');
    for (const [dev, opts] of [['phone', devices['Pixel 7']], ['desktop', { viewport: { width: 1440, height: 900 } }]]) {
      if (!isHome && dev === 'desktop') continue;
      const ctx = await b.newContext(opts);
      await ctx.addInitScript(() => { window.__cls = 0; new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); });
      const p = await ctx.newPage();
      const cdp = await ctx.newCDPSession(p);
      await cdp.send('Preload.enable');
      let ruleSets = 0; const ruleErrors = [];
      cdp.on('Preload.ruleSetUpdated', e => { ruleSets++; if (e.ruleSet.errorType) ruleErrors.push(e.ruleSet.errorMessage); });
      const errors = []; p.on('pageerror', e => errors.push(e.message.slice(0, 70)));
      const t0 = Date.now(); let tagAt = null, bigFont = false; const heroes = new Set();
      p.on('request', r => {
        const u = r.url();
        if (tagAt === null && TAGS.test(u)) tagAt = Date.now() - t0;
        if (/elementskit\.woff/.test(u)) bigFont = true;
        if (/Fast-Expert-Care-scaled|ER-of-Irving-Mobile-Background|Lufkin-Facality|Lufkin1\.webp|Lufkin-ER-Ambluance/.test(u)) heroes.add(u.split('/').pop().slice(0, 45));
      });
      let headers = {};
      p.on('response', async r => { if (r.url() === url) headers = await r.allHeaders(); });
      const resp = await p.goto(url, { waitUntil: 'load', timeout: 180000 });
      const html = await resp.text();
      await p.waitForTimeout(1200);
      const moveAt = Date.now() - t0;
      for (let i = 0; i < 6; i++) { await p.mouse.move(150 + i * 30, 300); await p.waitForTimeout(25); }
      await p.waitForTimeout(6500);
      const cls = await p.evaluate(() => +window.__cls.toFixed(3));

      const has = re => re.test(html);
      const leftovers = Object.entries({
        'WP version tag': /<meta name="generator" content="WordPress/, RSD: /EditURI/, 'shortlink tag': /rel=['"]shortlink/,
        'REST link': /api\.w\.org/, oEmbed: /\+oembed/, 'jQuery Migrate': /jquery-migrate/, 'global styles': /global-styles-inline/,
        'Rank Math credit': /<!--[^>]*Rank Math[^>]*-->/, 'plugin hero preload': /cc-hero-preload/,
      }).filter(([, re]) => has(re)).map(([k]) => k);
      const present = {
        'prerender rules': (html.match(/<script type="speculationrules">/g) || []).length === 1 && /"prerender"/.test(html),
        'tag scheduler': html.includes('er-tag-scheduler'),
        'view transitions': html.includes('er-view-transitions'),
      };
      if (site === 'erofirving.com') present['icon subset'] = html.includes('er-ekit-icon-subset');
      if (site === 'eroflufkin.com' && isHome) {
        const tag = (html.match(/<img[^>]*ER-near-lufkin-tx\.jpeg[^>]*>/) || [''])[0];
        present['image 6458 not lazy'] = /skip-lazy/.test(tag) && !/data-src=/.test(tag);
        present['slider preload'] = /rel="preload"[^>]*Lufkin-Facality/.test(html);
      }
      if (site === 'erofirving.com' && isHome) present['hero preloads (2)'] = (html.match(/rel="preload"[^>]*(Fast-Expert-Care-scaled|ER-of-Irving-Mobile-Background)[^>]*media=/g) || []).length === 2;

      let menu = '-';
      if (dev === 'phone') {
        const t = p.locator('.elementor-menu-toggle:visible, .elementskit-menu-hamburger:visible, [aria-label*="enu"]:visible, .e-n-menu-toggle:visible').first();
        if (await t.count()) {
          await t.click().catch(() => {}); await p.waitForTimeout(1000);
          menu = await p.evaluate(() => {
            const a = document.querySelector('.elementor-menu-toggle, .e-n-menu-toggle'); if (a && a.getAttribute('aria-expanded') === 'true') return 'opens';
            const o = document.querySelector('.elementskit-menu-offcanvas-elements, .elementskit-menu-container'); if (o && /active/.test(o.className)) return 'opens';
            const any = [...document.querySelectorAll('nav, .elementor-nav-menu--dropdown')].some(e => { const r = e.getBoundingClientRect(); return r.height > 120 && getComputedStyle(e).visibility !== 'hidden' && getComputedStyle(e).display !== 'none'; });
            return any ? 'menu visible' : 'did not open';
          });
        } else menu = 'no toggle on page';
      }
      const tagText = tagAt === null ? 'not loaded in 8s' : (tagAt < moveAt ? `loaded before interaction (${tagAt}ms)` : `+${tagAt - moveAt}ms after mouse move`);
      console.log(`${url.replace(/^https:\/\//, '').padEnd(32)} ${dev.padEnd(7)} status ${resp.status()} | JS errors: ${errors.length ? errors.join(' ; ') : 'none'} | rules accepted: ${ruleSets > 0 && ruleErrors.length === 0} | tags: ${tagText} | CLS ${cls} | shortlink header: ${/shortlink/.test(headers.link || '')}`);
      console.log(`   present: ${JSON.stringify(present)} | leftovers: ${leftovers.length ? leftovers.join(', ') : 'none'}${site === 'erofirving.com' ? ' | 454KB icon font requested: ' + bigFont : ''}${heroes.size ? ' | hero images: ' + [...heroes].join(', ') : ''}${dev === 'phone' ? ' | mobile menu: ' + menu : ''}`);
      await ctx.close();
    }
  }
}
await b.close();

import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');

const DIR = 'D:/cc-assistant/tools/er-performance-snippets/';
const rules = fs.readFileSync('../nav/rules.json', 'utf8').trim();
const pick = (file, re) => fs.readFileSync(DIR + file, 'utf8').match(re)[0];
const TAGS = /googletagmanager|connect\.facebook|clarity\.ms|doubleclick|google-analytics/;

function commonTransforms(html, file) {
  const sched = pick(file, /<script id="er-tag-scheduler">[\s\S]*?<\/script>/);
  const vt = fs.readFileSync(DIR + file, 'utf8').match(/<style id="er-view-transitions">.*?<\/style>/)[0];
  html = html.replace(/<script type="speculationrules">[\s\S]*?<\/script>/g, '');
  html = html.replace('</body>', `<script type="speculationrules">${rules}</script>${sched}</body>`);
  html = html.replace('</head>', vt + '</head>');
  return html;
}

const SITES = {
  'erofirving.com': {
    file: 'erofirving.php',
    transform(html) {
      const font = fs.readFileSync(DIR + 'erofirving.php', 'utf8').match(/<style id="er-ekit-icon-subset">.*?<\/style>/)[0];
      html = html.replace(/<link[^>]*id="cc-hero-preload"[^>]*>/, '');
      const pre = [...fs.readFileSync(DIR + 'erofirving.php', 'utf8').matchAll(/<link rel="preload"[^']*?>/g)].map(m => m[0]).join('');
      html = html.replace('<head>', '<head>' + pre).replace(/<head([^>]*)>/, (m) => m);
      if (!html.includes('Fast-Expert-Care-scaled.webp" media')) html = html.replace('</title>', '</title>' + pre);
      html = html.replace('</head>', font + '</head>');
      return commonTransforms(html, 'erofirving.php');
    },
  },
  'erofwhiterock.com': {
    file: 'erofwhiterock.php',
    transform(html) {
      html = html.replace(/<script[^>]*jquery-migrate(\.min)?\.js[^>]*><\/script>/g, '');
      html = html.replace(/<style id="global-styles-inline-css">[\s\S]*?<\/style>/, '');
      return commonTransforms(html, 'erofwhiterock.php');
    },
  },
  'eroflufkin.com': {
    file: 'eroflufkin.php',
    transform(html) {
      html = html.replace(/<script[^>]*jquery-migrate(\.min)?\.js[^>]*><\/script>/g, '');
      html = html.replace(/<style id="global-styles-inline-css">[\s\S]*?<\/style>/, '');
      html = html.replace(/<img([^>]*?)data-src="([^"]*ER-near-lufkin-tx\.jpeg)"([^>]*?)data-srcset="([^"]*)"([^>]*?)src="data:image\/gif[^"]*"([^>]*?)class="([^"]*?)\s*lazyload"/,
        (m, a, src, b1, srcset, c, d, cls) => `<img${a}src="${src}"${b1}srcset="${srcset}"${c}${d}class="${cls} skip-lazy" loading="eager"`);
      const pre = fs.readFileSync(DIR + 'eroflufkin.php', 'utf8').match(/<link rel="preload"[^']*?>/)[0];
      html = html.replace('</title>', '</title>' + pre);
      return commonTransforms(html, 'eroflufkin.php');
    },
  },
};

const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
for (const [site, cfg] of Object.entries(SITES)) {
  const URL_ = `https://${site}/`;
  const live = await (await (await b.newPage()).goto(URL_, { waitUntil: 'domcontentloaded', timeout: 120000 })).text();
  const html = cfg.transform(live);
  for (const [dev, opts] of [['phone', devices['Pixel 7']], ['desktop', { viewport: { width: 1440, height: 900 } }]]) {
    const ctx = await b.newContext(opts);
    await ctx.addInitScript(() => { window.__cls = 0; new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); });
    const p = await ctx.newPage();
    const cdp = await ctx.newCDPSession(p);
    await cdp.send('Preload.enable');
    const ruleErrors = []; let ruleSets = 0;
    cdp.on('Preload.ruleSetUpdated', e => { ruleSets++; if (e.ruleSet.errorType) ruleErrors.push(e.ruleSet.errorMessage); });
    const errors = []; p.on('pageerror', e => errors.push(e.message.slice(0, 70)));
    const heroes = []; let bigFont = false; let tagAt = null; const t0 = Date.now(); let moveAt = null;
    p.on('request', r => {
      const u = r.url();
      if (/Fast-Expert-Care-scaled|ER-of-Irving-Mobile-Background|Lufkin-Facality/.test(u)) heroes.push(u.split('/').pop() + (r.frame() ? '' : ''));
      if (/elementskit\.woff/.test(u)) bigFont = true;
      if (tagAt === null && TAGS.test(u)) tagAt = Date.now() - t0;
    });
    await p.route(URL_, r => r.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: html }));
    await p.goto(URL_, { waitUntil: 'load', timeout: 180000 });
    await p.waitForTimeout(1000);
    moveAt = Date.now() - t0;
    for (let i = 0; i < 6; i++) { await p.mouse.move(200 + i * 30, 300); await p.waitForTimeout(25); }
    await p.waitForTimeout(7000);
    const cls = await p.evaluate(() => +window.__cls.toFixed(3));
    const menu = await (async () => {
      if (dev !== 'phone') return '-';
      const t = p.locator('.elementor-menu-toggle:visible, .elementskit-menu-hamburger:visible').first();
      if (!(await t.count())) return 'no toggle';
      await t.click().catch(() => {}); await p.waitForTimeout(900);
      return await p.evaluate(() => {
        const a = document.querySelector('.elementor-menu-toggle'); if (a && a.getAttribute('aria-expanded') === 'true') return 'opens';
        const o = document.querySelector('.elementskit-menu-offcanvas-elements, .elementskit-menu-container'); if (o && /active/.test(o.className)) return 'opens';
        return 'did not open';
      });
    })();
    console.log(`${site.padEnd(18)} ${dev.padEnd(7)} JS errors: ${errors.length ? errors.join(' ; ') : 'none'} | rules accepted: ${ruleSets > 0 && ruleErrors.length === 0} | tags: +${tagAt === null ? 'never' : (tagAt - moveAt) + 'ms after mouse move'} | CLS ${cls} | hero files: ${[...new Set(heroes)].join(', ') || '-'}${site === 'erofirving.com' ? ' | 454KB icon font requested: ' + bigFont : ''} | mobile menu: ${menu}`);
    await ctx.close();
  }
}
await b.close();

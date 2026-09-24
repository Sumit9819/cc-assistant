import { createRequire } from 'node:module';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const ctx = await b.newContext(devices['Pixel 7']);
await ctx.addInitScript(() => {
  window.__shifts = []; window.__heights = [];
  new PerformanceObserver(l => { for (const e of l.getEntries()) { if (e.hadRecentInput) continue;
    window.__shifts.push({ t: Math.round(e.startTime), v: +e.value.toFixed(4), moved: (e.sources || []).map(s => ({ node: s.node ? (s.node.tagName + '.' + String(s.node.className).slice(0, 60)) : '?', from: [Math.round(s.previousRect.y), Math.round(s.previousRect.height)], to: [Math.round(s.currentRect.y), Math.round(s.currentRect.height)] })) }); } })
    .observe({ type: 'layout-shift', buffered: true });
  const t0 = performance.now();
  const iv = setInterval(() => {
    const q = s => { const e = document.querySelector(s); return e ? Math.round(e.getBoundingClientRect().height) : null; };
    const row = { t: Math.round(performance.now() - t0), slidesWidget: q('.elementor-widget-slides'), swiper: q('.elementor-widget-slides .swiper, .elementor-widget-slides .elementor-slides-wrapper'), swiperInit: !!document.querySelector('.swiper-initialized'), h1Top: (() => { const h = document.querySelector('h1'); return h ? Math.round(h.getBoundingClientRect().top + scrollY) : null; })() };
    const last = window.__heights[window.__heights.length - 1];
    if (!last || JSON.stringify({ ...last, t: 0 }) !== JSON.stringify({ ...row, t: 0 })) window.__heights.push(row);
    if (performance.now() - t0 > 9000) clearInterval(iv);
  }, 50);
});
const p = await ctx.newPage();
await p.goto('https://eroflufkin.com/', { waitUntil: 'load', timeout: 120000 });
await p.waitForTimeout(8000);
const r = await p.evaluate(() => ({ shifts: window.__shifts, heights: window.__heights }));
console.log('LAYOUT SHIFTS (mobile):'); for (const s of r.shifts) console.log(' ', JSON.stringify(s));
console.log('\nSLIDER HEIGHT / H1 POSITION OVER TIME (only changes):'); for (const h of r.heights) console.log(' ', JSON.stringify(h));
const order = await p.evaluate(() => [...document.querySelectorAll('[data-elementor-type="wp-page"] > .e-con, [data-elementor-type="wp-page"] > div > .e-con')].slice(0, 4).map(e => ({ id: e.getAttribute('data-id'), top: Math.round(e.getBoundingClientRect().top + scrollY), h: Math.round(e.getBoundingClientRect().height), widgets: [...e.querySelectorAll('[data-widget_type]')].slice(0, 4).map(w => w.getAttribute('data-widget_type')) })));
console.log('\nTOP SECTIONS:', JSON.stringify(order, null, 1));
await b.close();

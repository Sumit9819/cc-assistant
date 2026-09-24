const { chromium } = require('playwright');

const THIRD = /googletagmanager\.com|connect\.facebook\.net|facebook\.com|affirm\.com|intercomcdn\.com|intercom\.io|doubleclick\.net|google-analytics/i;
const SWIPER = /divi-plus.*swiper|swiper.*divi-plus/i;

const CASES = [
  { name: 'baseline',                     third: false, swiper: false },
  { name: 'defer 3rd-party marketing',    third: true,  swiper: false },
  { name: 'drop Divi Plus swiper',        third: false, swiper: true  },
  { name: 'both',                         third: true,  swiper: true  },
];

const run = async (b, c) => {
  const ctx = await b.newContext({
    viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150,
    downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  await p.route('**/*', (route) => {
    const u = route.request().url();
    if (c.third && THIRD.test(u)) return route.abort();
    if (c.swiper && SWIPER.test(u)) return route.abort();
    route.continue();
  });

  await p.addInitScript(() => {
    window.__lcp = 0; window.__cls = 0; window.__long = [];
    new PerformanceObserver(l => { window.__lcp = l.getEntries().at(-1).startTime; })
      .observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; })
      .observe({ type: 'layout-shift', buffered: true });
    try { new PerformanceObserver(l => { for (const e of l.getEntries()) window.__long.push(e.duration); })
      .observe({ type: 'longtask', buffered: true }); } catch (e) {}
  });

  await p.goto('https://sids-ponds.com/', { waitUntil: 'load', timeout: 300000 });
  await p.waitForTimeout(9000);
  const m = await p.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    return {
      fcp: Math.round((performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime || 0),
      lcp: Math.round(window.__lcp), cls: +window.__cls.toFixed(3),
      dcl: Math.round(nav.domContentLoadedEventEnd),
      blockMs: Math.round(window.__long.reduce((a, x) => a + x, 0)),
      tasks: window.__long.length,
    };
  });
  await ctx.close();
  return m;
};

(async () => {
  const b = await chromium.launch();
  const rows = [];
  for (const c of CASES) {
    const m = await run(b, c);
    rows.push({ condition: c.name, FCP: m.fcp, LCP: m.lcp, CLS: m.cls, DCL: m.dcl, blockingMs: m.blockMs, longTasks: m.tasks });
    console.log(`  ${c.name.padEnd(28)} FCP ${String(m.fcp).padStart(5)}  LCP ${String(m.lcp).padStart(5)}  CLS ${m.cls}  blocking ${m.blockMs}ms`);
  }
  console.log('');
  console.table(rows);
  const base = rows[0];
  rows.slice(1).forEach(r => console.log(
    `${r.condition}: FCP ${r.FCP - base.FCP >= 0 ? '+' : ''}${r.FCP - base.FCP}ms | LCP ${r.LCP - base.LCP >= 0 ? '+' : ''}${r.LCP - base.LCP}ms | main-thread blocking ${r.blockingMs - base.blockingMs >= 0 ? '+' : ''}${r.blockingMs - base.blockingMs}ms`));
  await b.close();
})();

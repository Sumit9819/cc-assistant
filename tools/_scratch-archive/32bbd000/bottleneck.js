// Discriminating experiment: is first paint gated by CPU, by network, or by
// neither? Vary one axis at a time against the same page.
const { chromium } = require('playwright');

const CASES = [
  { name: '4x CPU + slow 4G  (baseline)', cpu: 4, net: true },
  { name: '1x CPU + slow 4G  (CPU freed)', cpu: 1, net: true },
  { name: '4x CPU + fast net (net freed)', cpu: 4, net: false },
  { name: '1x CPU + fast net (both freed)', cpu: 1, net: false },
];

const run = async (b, c) => {
  const ctx = await b.newContext({
    viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Network.enable');
  if (c.net) {
    await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150,
      downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
  }
  if (c.cpu > 1) await cdp.send('Emulation.setCPUThrottlingRate', { rate: c.cpu });

  await p.addInitScript(() => {
    window.__lcp = 0; window.__long = [];
    new PerformanceObserver(l => { window.__lcp = l.getEntries().at(-1).startTime; })
      .observe({ type: 'largest-contentful-paint', buffered: true });
    try {
      new PerformanceObserver(l => { for (const e of l.getEntries()) window.__long.push(Math.round(e.duration)); })
        .observe({ type: 'longtask', buffered: true });
    } catch (e) {}
  });

  await p.goto('https://sids-ponds.com/', { waitUntil: 'load', timeout: 300000 });
  await p.waitForTimeout(6000);

  const m = await p.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    const rb = performance.getEntriesByType('resource')
      .filter(r => r.renderBlockingStatus === 'blocking')
      .map(r => ({ f: r.name.split('/').pop().split('?')[0].slice(0, 40), end: Math.round(r.responseEnd) }))
      .sort((a, b) => b.end - a.end);
    const long = window.__long || [];
    return {
      ttfb: Math.round(nav.responseStart), domInteractive: Math.round(nav.domInteractive),
      dcl: Math.round(nav.domContentLoadedEventEnd), load: Math.round(nav.loadEventEnd),
      fcp: Math.round((performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime || 0),
      lcp: Math.round(window.__lcp),
      blockingCount: rb.length, lastBlockingEnd: rb.length ? rb[0].end : 0, topBlocking: rb.slice(0, 3),
      longTasks: long.length, longTaskMs: long.reduce((a, x) => a + x, 0), longestTask: long.length ? Math.max(...long) : 0,
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
    rows.push({ case: c.name, TTFB: m.ttfb, FCP: m.fcp, LCP: m.lcp, domInt: m.domInteractive,
                lastBlockCSS: m.lastBlockingEnd, longTasks: m.longTasks, longMs: m.longTaskMs, longest: m.longestTask });
    console.log(`  ${c.name}  TTFB ${m.ttfb} FCP ${m.fcp} LCP ${m.lcp} | blocking resources ${m.blockingCount}, last ends ${m.lastBlockingEnd}ms | long tasks ${m.longTasks} totalling ${m.longTaskMs}ms (worst ${m.longestTask}ms)`);
    if (m.topBlocking.length) console.log('      slowest render-blocking:', m.topBlocking.map(t => `${t.f} @${t.end}ms`).join(', '));
  }
  console.log('');
  console.table(rows);
  await b.close();
})();

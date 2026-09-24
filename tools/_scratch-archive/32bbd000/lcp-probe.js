const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({
    viewport: { width: 412, height: 823 },
    deviceScaleFactor: 2.625,
    isMobile: true,
    hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1638400, uploadThroughput: 675840 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  await page.addInitScript(() => {
    window.__lcp = [];
    new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        window.__lcp.push({ t: Math.round(e.startTime), size: e.size, url: (e.url || '').slice(-60), tag: e.element ? e.element.tagName + '.' + (e.element.className || '').split(' ').slice(0, 2).join('.') : '?' });
      }
    }).observe({ type: 'largest-contentful-paint', buffered: true });
    window.__shifts = 0;
    new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__shifts += e.value; }).observe({ type: 'layout-shift', buffered: true });
  });

  const heroReq = { start: null, end: null };
  const t0 = Date.now();
  page.on('request', (r) => { if (r.url().includes('Storage-Banner')) heroReq.start = Date.now() - t0; });
  page.on('requestfinished', (r) => { if (r.url().includes('Storage-Banner')) heroReq.end = Date.now() - t0; });

  await page.goto('https://sids-ponds.com/?lcp=' + Date.now(), { waitUntil: 'load', timeout: 90000 }).catch((e) => console.log('goto:', e.message));
  await page.waitForTimeout(9000);

  const data = await page.evaluate(() => ({ lcp: window.__lcp, cls: window.__shifts }));
  console.log('hero image request: start=' + heroReq.start + 'ms end=' + heroReq.end + 'ms');
  console.log('LCP candidates:');
  for (const e of data.lcp) console.log(`  ${e.t}ms size=${e.size} ${e.tag} ${e.url}`);
  console.log('final LCP:', data.lcp.length ? data.lcp[data.lcp.length - 1].t + 'ms' : 'none');
  console.log('CLS:', data.cls.toFixed(4));
  await browser.close();
})();

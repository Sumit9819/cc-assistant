const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const headerCss = fs.readFileSync('header-critical.css', 'utf-8');
  const browser = await chromium.launch();
  const ctx = await browser.newContext({
    viewport: { width: 412, height: 823 },
    deviceScaleFactor: 2.625,
    isMobile: true,
    hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const page = await ctx.newPage();

  // Rewrite the document: inject extracted header CSS right after <head>
  await page.route('**://sids-ponds.com/**', async (route) => {
    if (route.request().resourceType() !== 'document') return route.continue();
    const resp = await route.fetch();
    let body = await resp.text();
    body = body.replace('</head>', `<style id="sp-header-critical">${headerCss}</style></head>`);
    await route.fulfill({ response: resp, body });
  });

  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1638400, uploadThroughput: 675840 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  await page.addInitScript(() => {
    window.__shifts = [];
    new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        if (!e.hadRecentInput) window.__shifts.push({ t: Math.round(e.startTime), v: e.value });
      }
    }).observe({ type: 'layout-shift', buffered: true });
  });

  await page.goto('https://sids-ponds.com/?clsfix=' + Date.now(), { waitUntil: 'load', timeout: 90000 }).catch((e) => console.log('goto:', e.message));
  await page.waitForTimeout(12000);

  const shifts = await page.evaluate(() => window.__shifts);
  const total = shifts.reduce((a, s) => a + s.v, 0);
  console.log('TOTAL CLS with head-injected header CSS:', total.toFixed(4));
  for (const s of shifts.filter((x) => x.v > 0.01)) console.log(`  [t=${s.t}ms] ${s.v.toFixed(4)}`);
  await page.screenshot({ path: 'fix-final.png' });
  await browser.close();
})();

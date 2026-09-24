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
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false, latency: 150, downloadThroughput: 1638400, uploadThroughput: 675840,
  });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  await page.addInitScript(() => {
    window.__shiftTimes = [];
    new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        if (!e.hadRecentInput && e.value > 0.1) window.__shiftTimes.push({ t: Math.round(e.startTime), v: e.value });
      }
    }).observe({ type: 'layout-shift', buffered: true });
  });

  const nav = page.goto('https://sids-ponds.com/?clsprobe2=' + Date.now(), { waitUntil: 'load', timeout: 90000 }).catch((e) => console.log('goto:', e.message));

  // screenshots along the way
  const shots = [4000, 8000, 12000, 15000, 17000, 19000, 21000, 24000];
  const t0 = Date.now();
  for (const ms of shots) {
    const wait = ms - (Date.now() - t0);
    if (wait > 0) await page.waitForTimeout(wait);
    await page.screenshot({ path: `shot-${ms}.png` }).catch(() => {});
  }
  await nav;
  await page.waitForTimeout(3000);

  const data = await page.evaluate(() => {
    const bigShifts = window.__shiftTimes;
    const res = performance.getEntriesByType('resource')
      .filter((r) => /\.css|\.js|et-cache|et-core|dynamic/.test(r.name))
      .map((r) => ({ end: Math.round(r.responseEnd), start: Math.round(r.startTime), name: r.name.replace(/^https:\/\/sids-ponds\.com/, '').slice(0, 110) }))
      .sort((a, b) => a.end - b.end);
    return { bigShifts, res };
  });

  console.log('big shifts:', JSON.stringify(data.bigShifts));
  const shiftT = data.bigShifts.length ? data.bigShifts[0].t : 19000;
  console.log('\nresources finishing within [shift-6000, shift+500]ms of shift at', shiftT + 'ms:');
  for (const r of data.res) {
    if (r.end >= shiftT - 6000 && r.end <= shiftT + 500) console.log(`  end=${r.end}ms start=${r.start}ms  ${r.name}`);
  }
  console.log('\nlast 10 CSS files by finish time:');
  for (const r of data.res.filter((x) => x.name.includes('.css')).slice(-10)) console.log(`  end=${r.end}ms  ${r.name}`);

  await browser.close();
})();

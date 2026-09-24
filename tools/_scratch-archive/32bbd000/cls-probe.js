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

  // Slow the CPU/network like Lighthouse mobile so late-arriving CSS/JS shifts reproduce
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false, latency: 150, downloadThroughput: 1638400, uploadThroughput: 675840,
  });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  // Install the observer before any page script runs
  await page.addInitScript(() => {
    window.__shifts = [];
    const describe = (n) => {
      if (!n || !n.tagName) return String(n);
      const cls = (typeof n.className === 'string' ? n.className : '').split(/\s+/).slice(0, 4).join('.');
      return n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + (cls ? '.' + cls : '');
    };
    new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        if (e.hadRecentInput) continue;
        window.__shifts.push({
          t: Math.round(e.startTime),
          value: e.value,
          sources: (e.sources || []).map((s) => ({
            node: describe(s.node),
            prev: s.previousRect ? [s.previousRect.top, s.previousRect.left, s.previousRect.width, s.previousRect.height] : null,
            curr: s.currentRect ? [s.currentRect.top, s.currentRect.left, s.currentRect.width, s.currentRect.height] : null,
          })),
        });
      }
    }).observe({ type: 'layout-shift', buffered: true });
  });

  await page.goto('https://sids-ponds.com/?clsprobe=' + Date.now(), { waitUntil: 'load', timeout: 90000 }).catch((e) => console.log('goto:', e.message));
  await page.waitForTimeout(12000);

  const shifts = await page.evaluate(() => window.__shifts);
  const total = shifts.reduce((a, s) => a + s.value, 0);
  console.log('TOTAL CLS (unwindowed):', total.toFixed(4), '| events:', shifts.length);
  for (const s of shifts.filter((x) => x.value > 0.005)) {
    console.log(`\n[t=${s.t}ms] value=${s.value.toFixed(4)}`);
    for (const src of s.sources) {
      console.log('   ', src.node);
      console.log('      prev t/l/w/h:', src.prev, ' -> curr:', src.curr);
    }
  }

  // Carousel geometry after full init, for the fix's target height
  const geo = await page.evaluate(() => {
    const el = document.querySelector('.dipl_woo_products_carousel');
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const sw = el.querySelector('.swiper, .swiper-container');
    return { carouselHeight: Math.round(r.height), swiperClasses: sw ? sw.className : 'none', initialized: !!el.querySelector('.swiper-initialized, .swiper-container-initialized') };
  });
  console.log('\ncarousel post-init:', JSON.stringify(geo));

  await browser.close();
})();

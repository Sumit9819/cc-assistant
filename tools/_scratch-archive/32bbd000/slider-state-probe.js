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
    window.__samples = [];
    const t = () => Math.round(performance.now());
    const sample = () => {
      const wrap = document.querySelector('.et_pb_slider');
      const slide = document.querySelector('.et_pb_slide_0');
      const rec = { t: t() };
      for (const [name, el] of [['slider', wrap], ['slide0', slide]]) {
        if (!el) { rec[name] = 'absent'; continue; }
        const cs = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        rec[name] = `d=${cs.display} o=${cs.opacity} v=${cs.visibility} h=${Math.round(r.height)} cls=${el.className.split(' ').filter(c => /active|animat|init|load/.test(c)).join(',') || '-'}`;
      }
      window.__samples.push(rec);
    };
    setInterval(sample, 700);
  });

  await page.goto('https://sids-ponds.com/?sst=' + Date.now(), { waitUntil: 'load', timeout: 90000 }).catch((e) => console.log('goto:', e.message));
  await page.waitForTimeout(10000);

  const samples = await page.evaluate(() => window.__samples);
  let prev = '';
  for (const s of samples) {
    const cur = s.slider + ' || ' + s.slide0;
    if (cur !== prev) { console.log(`${s.t}ms  slider[${s.slider}]  slide0[${s.slide0}]`); prev = cur; }
  }
  await browser.close();
})();

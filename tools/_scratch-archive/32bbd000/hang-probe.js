const { chromium } = require('playwright');

(async () => {
  const mobile = process.argv[2] === 'mobile';
  const browser = await chromium.launch();
  const ctx = await browser.newContext(mobile ? {
    viewport: { width: 412, height: 823 }, deviceScaleFactor: 2.625, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  } : {
    viewport: { width: 1350, height: 940 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
  });
  const page = await ctx.newPage();
  if (mobile) {
    const cdp = await ctx.newCDPSession(page);
    await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1638400, uploadThroughput: 675840 });
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
  }

  const pending = new Map();
  const t0 = Date.now();
  page.on('request', (r) => pending.set(r, Date.now() - t0));
  page.on('requestfinished', (r) => pending.delete(r));
  page.on('requestfailed', (r) => pending.delete(r));

  let loadFired = null;
  page.on('load', () => { loadFired = Date.now() - t0; });

  await page.goto('https://sids-ponds.com/', { waitUntil: 'commit', timeout: 60000 }).catch((e) => console.log('goto:', e.message));

  const label = mobile ? 'mob' : 'desk';
  for (const s of [5, 12, 20, 30]) {
    const wait = s * 1000 - (Date.now() - t0);
    if (wait > 0) await page.waitForTimeout(wait);
    await page.screenshot({ path: `hang-${label}-${s}s.png` }).catch(() => {});
    console.log(`--- t=${s}s | load fired: ${loadFired ? loadFired + 'ms' : 'NO'} | pending requests: ${pending.size}`);
    for (const [r, started] of pending) {
      console.log(`    [started ${started}ms] ${r.method()} ${r.url().slice(0, 120)}`);
    }
  }
  // hero state check
  const hero = await page.evaluate(() => {
    const s = document.querySelector('.et_pb_slider .et_pb_slide');
    if (!s) return 'no slide el';
    const cs = getComputedStyle(s);
    const r = s.getBoundingClientRect();
    return { rect: [Math.round(r.top), Math.round(r.height)], opacity: cs.opacity, bg: (cs.backgroundImage || '').slice(0, 120), visible: cs.visibility, display: cs.display };
  });
  console.log('hero slide state:', JSON.stringify(hero));
  await browser.close();
})();

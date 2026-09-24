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
    window.__log = [];
    const t = () => Math.round(performance.now());

    // layout shifts
    new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        if (!e.hadRecentInput && e.value > 0.05) window.__log.push([t(), 'SHIFT', e.value.toFixed(4)]);
      }
    }).observe({ type: 'layout-shift', buffered: true });

    // watch class/style mutations on the interesting nodes once DOM exists
    const watch = () => {
      const targets = [
        ['header', document.querySelector('.et_pb_section_1_tb_header')],
        ['inner', document.querySelector('.et_builder_inner_content')],
        ['body', document.body],
        ['html', document.documentElement],
      ].filter(([, n]) => n);
      for (const [name, node] of targets) {
        new MutationObserver((muts) => {
          for (const m of muts) {
            const attr = m.attributeName;
            const val = (node.getAttribute(attr) || '').slice(0, 200);
            window.__log.push([t(), 'MUT', `${name}[${attr}]=${val}`]);
          }
        }).observe(node, { attributes: true, attributeFilter: ['class', 'style'] });
      }
      // geometry sampler
      const hdr = document.querySelector('.et_pb_section_1_tb_header');
      const inner = document.querySelector('.et_builder_inner_content');
      setInterval(() => {
        const h = hdr ? hdr.getBoundingClientRect() : null;
        const c = inner ? inner.getBoundingClientRect() : null;
        window.__log.push([t(), 'GEO', `hdr=${h ? Math.round(h.top) + '/' + Math.round(h.height) : '-'} inner=${c ? Math.round(c.top) + '/' + Math.round(c.height) : '-'} sheets=${document.styleSheets.length}`]);
      }, 500);
    };
    if (document.readyState !== 'loading') watch();
    else document.addEventListener('DOMContentLoaded', watch);
  });

  await page.goto('https://sids-ponds.com/?clsprobe3=' + Date.now(), { waitUntil: 'load', timeout: 90000 }).catch((e) => console.log('goto:', e.message));
  await page.waitForTimeout(14000);

  const log = await page.evaluate(() => window.__log);
  // print the window around the shift
  const shiftIdx = log.findIndex((e) => e[1] === 'SHIFT' && parseFloat(e[2]) > 0.5);
  const shiftT = shiftIdx >= 0 ? log[shiftIdx][0] : null;
  console.log('shift at:', shiftT, 'ms; total log entries:', log.length);
  for (const e of log) {
    if (shiftT === null || Math.abs(e[0] - shiftT) < 2500 || e[1] === 'SHIFT') console.log(e[0] + 'ms', e[1], e[2]);
  }
  await browser.close();
})();

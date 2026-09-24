// Current mobile performance baseline. Throttled to match a mid-range phone on
// 4G, which is what the field data reflects.
const { chromium } = require('playwright');

const URL = process.argv[2] || 'https://sids-ponds.com/';

(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 3,
    isMobile: true,
    hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false, latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8,
  });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  const res = [];
  p.on('response', async (r) => {
    try {
      const h = r.headers();
      const ct = (h['content-type'] || '').split(';')[0];
      const len = Number(h['content-length'] || 0);
      let size = len;
      if (!size) { const buf = await r.body().catch(() => null); size = buf ? buf.length : 0; }
      res.push({ url: r.url(), host: new URL(r.url()).host, ct, kb: size / 1024 });
    } catch (e) {}
  });

  await p.addInitScript(() => {
    window.__lcp = 0; window.__cls = 0; window.__lcpEl = '';
    new PerformanceObserver((l) => {
      const e = l.getEntries().at(-1);
      window.__lcp = e.startTime;
      window.__lcpEl = e.element ? (e.element.tagName + '.' + (e.element.className || '').toString().split(' ')[0]) : (e.url || '');
    }).observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver((l) => {
      for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
    }).observe({ type: 'layout-shift', buffered: true });
  });

  const t0 = Date.now();
  await p.goto(URL, { waitUntil: 'load', timeout: 180000 });
  await p.waitForTimeout(6000);
  const loadMs = Date.now() - t0;

  const m = await p.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    return {
      lcp: Math.round(window.__lcp), cls: +window.__cls.toFixed(4), lcpEl: window.__lcpEl,
      ttfb: Math.round(nav.responseStart || 0),
      domContentLoaded: Math.round(nav.domContentLoadedEventEnd || 0),
      fcp: Math.round((performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime || 0),
      // has the category spacing CSS been applied?
      pillarRule: (() => {
        for (const s of document.styleSheets) {
          let r; try { r = s.cssRules; } catch (e) { continue; }
          const walk = (rs) => { for (const x of rs) { if (x.selectorText) { if (/pillar-text/.test(x.selectorText)) return true; continue; } if (x.cssRules && walk(x.cssRules)) return true; } return false; };
          if (walk(r)) return true;
        }
        return false;
      })(),
    };
  });

  const byType = {};
  let total = 0;
  for (const r of res) {
    const k = /javascript/.test(r.ct) ? 'JS' : /css/.test(r.ct) ? 'CSS' : /image|svg/.test(r.ct) ? 'Images'
            : /font/.test(r.ct) ? 'Fonts' : /html/.test(r.ct) ? 'HTML' : 'Other';
    byType[k] = (byType[k] || 0) + r.kb; total += r.kb;
  }

  const thirdParty = res.filter(r => !r.host.includes('sids-ponds.com'));
  const tpTotal = thirdParty.reduce((a, r) => a + r.kb, 0);

  console.log(`\n===== ${URL} — mobile, 4x CPU, ~1.6Mbps =====`);
  console.log(`TTFB ${m.ttfb}ms | FCP ${m.fcp}ms | LCP ${m.lcp}ms | CLS ${m.cls} | DOMContentLoaded ${m.domContentLoaded}ms | wall ${loadMs}ms`);
  console.log(`LCP element: ${m.lcpEl}`);
  console.log(`category spacing CSS applied: ${m.pillarRule ? 'YES' : 'NO'}`);
  console.log(`DiviFlash CSS still loading   : ${res.some(r => r.url.includes('/diviflash/')) ? 'YES' : 'NO'}`);

  console.log(`\ntotal transferred: ${total.toFixed(0)} KB   (third party ${tpTotal.toFixed(0)} KB, ${Math.round(100*tpTotal/total)}%)`);
  console.table(Object.entries(byType).sort((a,b)=>b[1]-a[1]).map(([k,v]) => ({ type: k, KB: +v.toFixed(0) })));

  console.log('\nheaviest 16 resources:');
  console.table(res.sort((a,b)=>b.kb-a.kb).slice(0,16).map(r => ({
    KB: +r.kb.toFixed(0),
    host: r.host.replace('www.','').slice(0,26),
    file: r.url.split('/').pop().split('?')[0].slice(0,44),
  })));

  console.log('\nthird-party totals by host:');
  const tpByHost = {};
  thirdParty.forEach(r => tpByHost[r.host] = (tpByHost[r.host] || 0) + r.kb);
  console.table(Object.entries(tpByHost).sort((a,b)=>b[1]-a[1]).slice(0,12).map(([h,v]) => ({ host: h, KB: +v.toFixed(0) })));

  await b.close();
})();

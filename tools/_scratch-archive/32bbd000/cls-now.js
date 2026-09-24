// CLS attribution + a correct first/third-party split. The previous run
// reported "99% third party" because a top-level `const URL` shadowed the
// global URL constructor, so every hostname lookup threw. Renamed here.
const { chromium } = require('playwright');
const TARGET = process.argv[2] || 'https://sids-ponds.com/';

(async () => {
  const b = await chromium.launch();
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

  await p.addInitScript(() => {
    window.__shifts = []; window.__cls = 0;
    new PerformanceObserver((l) => {
      for (const e of l.getEntries()) {
        if (e.hadRecentInput) continue;
        window.__cls += e.value;
        (e.sources || []).forEach(s => {
          const n = s.node;
          window.__shifts.push({
            v: +e.value.toFixed(4), t: Math.round(e.startTime),
            el: n ? (n.tagName + (n.id ? '#' + n.id : '') + '.' + (n.className || '').toString().trim().split(/\s+/).slice(0,2).join('.')) : '(none)',
            from: s.previousRect ? `${Math.round(s.previousRect.y)}` : '?',
            to: s.currentRect ? `${Math.round(s.currentRect.y)}` : '?',
          });
        });
      }
    }).observe({ type: 'layout-shift', buffered: true });
  });

  await p.goto(TARGET, { waitUntil: 'load', timeout: 180000 });
  await p.waitForTimeout(8000);

  const out = await p.evaluate(() => {
    const rs = performance.getEntriesByType('resource').map(r => ({
      name: r.name, kb: (r.transferSize || r.encodedBodySize || 0) / 1024,
    }));
    return { cls: +window.__cls.toFixed(4), shifts: window.__shifts, rs };
  });

  const hostOf = (u) => { try { return new URL(u).host.replace(/^www\./, ''); } catch { return 'unparsed'; } };
  let first = 0, third = 0; const byHost = {};
  out.rs.forEach(r => {
    const h = hostOf(r.name);
    if (h.includes('sids-ponds.com')) first += r.kb; else { third += r.kb; byHost[h] = (byHost[h] || 0) + r.kb; }
  });

  console.log(`\nCLS: ${out.cls}`);
  console.log('\nlargest individual shifts:');
  console.table(out.shifts.sort((a, b) => b.v - a.v).slice(0, 10));

  console.log(`\nfirst party : ${first.toFixed(0)} KB`);
  console.log(`third party : ${third.toFixed(0)} KB  (${Math.round(100 * third / (first + third))}%)`);
  console.table(Object.entries(byHost).sort((a, b) => b[1] - a[1]).slice(0, 10).map(([h, v]) => ({ host: h, KB: +v.toFixed(0) })));

  await b.close();
})();

// Serve a filtered version of the SG bundle and measure. Only rules belonging to
// components whose MARKUP is absent from the homepage are stripped.
const { chromium } = require('playwright');

const DEAD = [
  /video-js|vjs-|kgvid/,          // Videopack        ~39 KB
  /\blg-(outer|css3|container|backdrop|toolbar|sub-html|thumb|actions|icon|item|object|progress)/, // lightGallery ~15 KB
  /dataTable|dataTables_/,        // DataTables       ~14 KB
  /select2/,                      // Select2          ~4  KB
  /cwginstock|\bcwg-/,            // Back In Stock    ~37 KB (product-only)
  /\bcr-[a-z]|#review_form|ivole/,// Customer Reviews ~108 KB (product-only)
  /ball-|line-spin|line-scale|\bloader-/, // loaders.css spinners
];

function stripDead(css) {
  let out = '', i = 0, selStart = 0, kept = 0, dropped = 0;
  const emit = (from, to) => { out += css.slice(from, to); };
  const walk = (from, to) => {
    let i = from, selStart = from;
    while (i < to) {
      if (css[i] === '{') {
        const sel = css.slice(selStart, i).trim();
        let d = 1, j = i + 1;
        while (j < to && d > 0) { if (css[j] === '{') d++; else if (css[j] === '}') d--; j++; }
        if (/^@(media|supports|container|layer|scope)/i.test(sel)) {
          out += css.slice(selStart, i + 1);
          walk(i + 1, j - 1);
          out += '}';
        } else if (DEAD.some(re => re.test(sel))) {
          dropped += j - selStart;
        } else {
          out += css.slice(selStart, j);
          kept += j - selStart;
        }
        i = j; selStart = j; continue;
      }
      if (css[i] === '}') { i++; selStart = i; continue; }
      i++;
    }
  };
  walk(0, css.length);
  return { css: out, kept, dropped };
}

const run = async (b, strip) => {
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

  let note = '';
  if (strip) {
    await p.route(/siteground-optimizer-combined-css/, async (route) => {
      const resp = await route.fetch();
      const body = await resp.text();
      const r = stripDead(body);
      note = `bundle ${(body.length/1024).toFixed(0)} KB -> ${(r.css.length/1024).toFixed(0)} KB (dropped ${(r.dropped/1024).toFixed(0)} KB)`;
      await route.fulfill({ response: resp, body: r.css, headers: { ...resp.headers(), 'content-length': String(Buffer.byteLength(r.css)) } });
    });
  }

  await p.addInitScript(() => {
    window.__lcp = 0; window.__cls = 0; window.__long = [];
    new PerformanceObserver(l => { window.__lcp = l.getEntries().at(-1).startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
    try { new PerformanceObserver(l => { for (const e of l.getEntries()) window.__long.push(e.duration); }).observe({ type: 'longtask', buffered: true }); } catch (e) {}
  });

  await p.goto('https://sids-ponds.com/', { waitUntil: 'load', timeout: 300000 });
  await p.waitForTimeout(8000);
  const m = await p.evaluate(() => ({
    fcp: Math.round((performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime || 0),
    lcp: Math.round(window.__lcp), cls: +window.__cls.toFixed(3),
    blockMs: Math.round(window.__long.reduce((a, x) => a + x, 0)),
    rules: [...document.styleSheets].reduce((a, s) => { try { return a + s.cssRules.length; } catch { return a; } }, 0),
  }));
  await ctx.close();
  return { ...m, note };
};

(async () => {
  const b = await chromium.launch();
  const base = await run(b, false);
  console.log(`baseline      FCP ${base.fcp}  LCP ${base.lcp}  CLS ${base.cls}  blocking ${base.blockMs}ms  cssRules ${base.rules}`);
  const cut = await run(b, true);
  console.log(`stripped      FCP ${cut.fcp}  LCP ${cut.lcp}  CLS ${cut.cls}  blocking ${cut.blockMs}ms  cssRules ${cut.rules}`);
  console.log(`              ${cut.note}`);
  console.log('');
  console.log(`delta: FCP ${cut.fcp - base.fcp >= 0 ? '+' : ''}${cut.fcp - base.fcp}ms | LCP ${cut.lcp - base.lcp >= 0 ? '+' : ''}${cut.lcp - base.lcp}ms | blocking ${cut.blockMs - base.blockMs >= 0 ? '+' : ''}${cut.blockMs - base.blockMs}ms | rules ${cut.rules - base.rules}`);
  await b.close();
})();

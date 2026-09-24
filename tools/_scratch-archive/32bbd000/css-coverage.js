const { chromium } = require('playwright');

const PAGES = [
  { name: 'homepage', url: 'https://sids-ponds.com/' },
  { name: 'category', url: 'https://sids-ponds.com/product-category/all-products/aggregates-soil-mulch/' },
  { name: 'product',  url: 'https://sids-ponds.com/product/all-products/artificial-turf-and-sod/sod/sod/' },
];

const short = (u) => { try { return u.split('/').pop().split('?')[0].slice(0, 46); } catch { return u; } };

// Merge overlapping ranges, then sum.
const usedBytes = (ranges) => {
  if (!ranges.length) return 0;
  const s = [...ranges].sort((a, b) => a.start - b.start);
  let total = 0, curS = s[0].start, curE = s[0].end;
  for (const r of s.slice(1)) {
    if (r.start <= curE) { curE = Math.max(curE, r.end); }
    else { total += curE - curS; curS = r.start; curE = r.end; }
  }
  return total + (curE - curS);
};

const selectorsOf = (css) => {
  const out = new Set();
  // strip comments + at-rule preludes, grab selector text before each {
  const cleaned = css.replace(/\/\*[\s\S]*?\*\//g, '');
  const re = /([^{}@;]+)\{/g;
  let m;
  while ((m = re.exec(cleaned))) {
    const sel = m[1].trim().replace(/\s+/g, ' ');
    if (sel && sel.length < 300 && !/^\d/.test(sel)) sel.split(',').forEach(x => { const t = x.trim(); if (t) out.add(t); });
  }
  return out;
};

(async () => {
  const b = await chromium.launch();
  const bodies = new Map();   // url -> raw css text
  const perPage = [];

  for (const pg of PAGES) {
    const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
    p.on('response', async (r) => {
      if (!/css/.test(r.headers()['content-type'] || '')) return;
      if (bodies.has(r.url())) return;
      const buf = await r.body().catch(() => null);
      if (buf) bodies.set(r.url(), buf.toString('utf8'));
    });

    await p.coverage.startCSSCoverage();
    await p.goto(pg.url, { waitUntil: 'networkidle', timeout: 120000 });
    await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));
    await p.waitForTimeout(1500);
    const cov = await p.coverage.stopCSSCoverage();

    const rows = cov.map(e => ({
      file: short(e.url) || '(inline)',
      totalKb: +(e.text.length / 1024).toFixed(1),
      usedKb: +(usedBytes(e.ranges) / 1024).toFixed(1),
    })).filter(r => r.totalKb > 2)
      .map(r => ({ ...r, pctUsed: r.totalKb ? Math.round(100 * r.usedKb / r.totalKb) : 0 }))
      .sort((a, b) => (b.totalKb - b.usedKb) - (a.totalKb - a.usedKb));

    const tot = rows.reduce((a, r) => a + r.totalKb, 0);
    const use = rows.reduce((a, r) => a + r.usedKb, 0);
    perPage.push({ page: pg.name, rows, tot: +tot.toFixed(1), use: +use.toFixed(1) });
    await p.close();
  }

  for (const pp of perPage) {
    console.log(`\n===== ${pp.page.toUpperCase()} — ${pp.tot} KB CSS shipped, ${pp.use} KB used (${Math.round(100*pp.use/pp.tot)}%) =====`);
    console.table(pp.rows.slice(0, 10));
  }

  // ---- duplication test -------------------------------------------------
  console.log('\n\n===== DUPLICATION TEST =====');
  const find = (frag) => [...bodies.entries()].find(([u]) => u.includes(frag));
  const combined = find('siteground-optimizer-combined-css');
  if (!combined) { console.log('SG combined file not captured.'); await b.close(); return; }

  const combSel = selectorsOf(combined[1]);
  console.log(`SG combined file: ${(combined[1].length/1024).toFixed(1)} KB, ${combSel.size} unique selectors`);

  for (const frag of ['divi-dynamic.min.css', 'style.min.css', 'all.min.css']) {
    for (const [u, txt] of bodies) {
      if (!u.includes(frag) || u.includes('combined')) continue;
      const s = selectorsOf(txt);
      if (!s.size) continue;
      let inBoth = 0;
      for (const sel of s) if (combSel.has(sel)) inBoth++;
      console.log(`\n  ${short(u)}  (${(txt.length/1024).toFixed(1)} KB, ${s.size} selectors)`);
      console.log(`    selectors also present in the SG combined file: ${inBoth} / ${s.size} (${Math.round(100*inBoth/s.size)}%)`);
    }
  }

  await b.close();
})();

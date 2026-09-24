// Front-end cost per plugin: every CSS/JS/font/image served from
// /wp-content/plugins/<slug>/ across the main page types.
const { chromium } = require('playwright');

const PAGES = [
  ['home',     'https://sids-ponds.com/'],
  ['category', 'https://sids-ponds.com/product-category/all-products/aggregates-soil-mulch/'],
  ['product',  'https://sids-ponds.com/product/all-products/artificial-turf-and-sod/sod/sod/'],
  ['blog',     'https://sids-ponds.com/landscaping-with-river-rocks/'],
  ['cart',     'https://sids-ponds.com/cart/'],
];

(async () => {
  const b = await chromium.launch();
  const cost = {};      // slug -> { page -> kb }
  const seenPage = {};

  for (const [label, url] of PAGES) {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
    const p = await ctx.newPage();
    const seen = new Set();
    p.on('response', async (r) => {
      const u = r.url();
      const m = u.match(/\/wp-content\/plugins\/([^/]+)\//);
      if (!m) return;
      if (seen.has(u)) return; seen.add(u);
      const h = r.headers();
      let kb = Number(h['content-length'] || 0) / 1024;
      if (!kb) { const buf = await r.body().catch(() => null); kb = buf ? buf.length / 1024 : 0; }
      const slug = m[1];
      cost[slug] = cost[slug] || {};
      cost[slug][label] = (cost[slug][label] || 0) + kb;
    });
    await p.goto(url, { waitUntil: 'networkidle', timeout: 180000 });
    await p.waitForTimeout(2500);
    seenPage[label] = true;
    await ctx.close();
  }

  const rows = Object.entries(cost).map(([slug, byPage]) => {
    const r = { plugin: slug.slice(0, 42) };
    let max = 0, total = 0;
    for (const [label] of PAGES) { const v = +(byPage[label] || 0).toFixed(0); r[label] = v; max = Math.max(max, v); total += v; }
    r.everyPage = PAGES.every(([l]) => (byPage[l] || 0) > 0) ? 'yes' : 'no';
    r._max = max;
    return r;
  }).sort((a, b) => b._max - a._max);

  rows.forEach(r => delete r._max);
  console.log('=== FRONT-END ASSET WEIGHT PER PLUGIN (KB, uncompressed where header absent) ===\n');
  console.table(rows);

  const loadsEverywhere = rows.filter(r => r.everyPage === 'yes').length;
  console.log(`\n${rows.length} plugins serve front-end assets; ${loadsEverywhere} load on every page type.`);
  await b.close();
})();

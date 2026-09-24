const { chromium } = require('playwright');

// The four Divi add-ons shipping CSS to every page. Matched by the path
// fragment of their stylesheet, and by the class prefixes their modules emit.
const ADDONS = [
  { key: 'DiviFlash',    file: '/plugins/diviflash/',        prefixes: ['difl_', 'difl-'] },
  { key: 'BodyCommerce', file: '/plugins/divi-bodycommerce/', prefixes: ['dbc_', 'bodycommerce', 'bodyshop'] },
  { key: 'DiviMachine',  file: '/plugins/divi-machine/',      prefixes: ['dmach', 'machine_'] },
  { key: 'DiviPlus',     file: '/plugins/divi-plus/',         prefixes: ['dipl_', 'dipl-'] },
];

const PAGES = [
  ['home',            'https://sids-ponds.com/'],
  ['shop',            'https://sids-ponds.com/shop/'],
  ['product-cat',     'https://sids-ponds.com/product-category/all-products/aggregates-soil-mulch/'],
  ['product-cat-2',   'https://sids-ponds.com/product-category/lighting/'],
  ['single-product',  'https://sids-ponds.com/product/all-products/artificial-turf-and-sod/sod/sod/'],
  ['cart',            'https://sids-ponds.com/cart/'],
  ['checkout',        'https://sids-ponds.com/checkout/'],
  ['my-account',      'https://sids-ponds.com/my-account/'],
  ['about',           'https://sids-ponds.com/about-us/'],
  ['delivery',        'https://sids-ponds.com/delivery/'],
  ['blog-post',       'https://sids-ponds.com/landscaping-with-river-rocks/'],
];

const usedBytes = (ranges) => {
  if (!ranges.length) return 0;
  const s = [...ranges].sort((a, b) => a.start - b.start);
  let total = 0, cs = s[0].start, ce = s[0].end;
  for (const r of s.slice(1)) {
    if (r.start <= ce) ce = Math.max(ce, r.end);
    else { total += ce - cs; cs = r.start; ce = r.end; }
  }
  return total + (ce - cs);
};

(async () => {
  const b = await chromium.launch();
  const results = [];
  const modulesSeen = {};
  ADDONS.forEach(a => modulesSeen[a.key] = new Set());

  for (const [name, url] of PAGES) {
    const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
    let status = 0;
    try {
      await p.coverage.startCSSCoverage();
      const resp = await p.goto(url, { waitUntil: 'networkidle', timeout: 120000 });
      status = resp ? resp.status() : 0;
      await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await p.waitForTimeout(1200);
      const cov = await p.coverage.stopCSSCoverage();

      // per-addon: was the file loaded, and how many bytes of it were used?
      const row = { page: name, status };
      for (const a of ADDONS) {
        const e = cov.find(c => c.url.includes(a.file));
        row[a.key] = e ? `${(usedBytes(e.ranges) / 1024).toFixed(1)} / ${(e.text.length / 1024).toFixed(0)} KB` : 'not loaded';
      }

      // which add-on modules are actually present in the DOM?
      const present = await p.evaluate((addons) => {
        const found = {};
        const all = new Set();
        document.querySelectorAll('[class]').forEach(el => {
          (el.className.baseVal !== undefined ? el.className.baseVal : el.className)
            .toString().split(/\s+/).forEach(c => { if (c) all.add(c); });
        });
        for (const a of addons) {
          found[a.key] = [...all].filter(c => a.prefixes.some(pre => c.startsWith(pre))).slice(0, 40);
        }
        return found;
      }, ADDONS.map(a => ({ key: a.key, prefixes: a.prefixes })));

      for (const a of ADDONS) {
        present[a.key].forEach(c => modulesSeen[a.key].add(c));
        row[a.key + '_dom'] = present[a.key].length;
      }
      results.push(row);
    } catch (e) {
      results.push({ page: name, status: 'ERR ' + e.message.slice(0, 40) });
    }
    await p.close();
  }

  console.log('\n===== USED / SHIPPED per page (KB), and DOM element count using that add-on =====\n');
  console.table(results.map(r => ({
    page: r.page, status: r.status,
    DiviFlash: r.DiviFlash, dom_DF: r.DiviFlash_dom,
    BodyCommerce: r.BodyCommerce, dom_BC: r.BodyCommerce_dom,
    DiviMachine: r.DiviMachine, dom_DM: r.DiviMachine_dom,
    DiviPlus: r.DiviPlus, dom_DP: r.DiviPlus_dom,
  })));

  console.log('\n===== Distinct add-on classes seen anywhere in the crawl =====');
  for (const a of ADDONS) {
    const s = [...modulesSeen[a.key]].sort();
    console.log(`\n${a.key} (${s.length}):`);
    console.log('  ' + (s.length ? s.join(', ') : 'NONE ANYWHERE'));
  }

  await b.close();
})();

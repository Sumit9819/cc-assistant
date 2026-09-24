// Confirm the two label plugins were removed cleanly: pages still 200, sale
// badges (WooCommerce's own) intact, no PHP notices, no orphaned asset requests.
const { chromium } = require('playwright');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

const PAGES = [
  ['home',     'https://sids-ponds.com/'],
  ['shop',     'https://sids-ponds.com/shop/'],
  ['category', 'https://sids-ponds.com/product-category/all-products/aggregates-soil-mulch/'],
  ['product',  'https://sids-ponds.com/product/all-products/power-tools-and-construction/blades-saws-filters-maintenance/iq-power-tools-iqms362i-masonry-saw/'],
  ['cart',     'https://sids-ponds.com/cart/'],
];

(async () => {
  const b = await chromium.launch();
  const rows = [];
  for (const [label, url] of PAGES) {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 }, userAgent: UA });
    const p = await ctx.newPage();
    const failed = [];
    const jsErrors = [];
    p.on('requestfailed', r => { if (/awl|acowebs|aco-product-labels|advanced-woo-labels/i.test(r.url())) failed.push(r.url()); });
    p.on('response', r => { if (r.status() >= 400 && /wp-content\/plugins/.test(r.url())) failed.push(`${r.status()} ${r.url().split('/').pop()}`); });
    p.on('pageerror', e => jsErrors.push(String(e).slice(0, 90)));

    const resp = await p.goto(url, { waitUntil: 'networkidle', timeout: 180000 });
    const d = await p.evaluate(() => {
      const t = document.body.innerText;
      return {
        saleBadges: document.querySelectorAll('.onsale').length,
        awlLeft: document.querySelectorAll('[class*="awl-"]').length,
        acoLeft: document.querySelectorAll('[class*="awcpl"],[class*="aco-pl"]').length,
        phpNotice: /(Fatal error|Warning:|Notice:|Deprecated:)/.test(t),
        products: document.querySelectorAll('li.product, .product').length,
      };
    });
    rows.push({
      page: label, status: resp.status(), products: d.products, saleBadges: d.saleBadges,
      awlLeft: d.awlLeft, acoLeft: d.acoLeft,
      phpNotice: d.phpNotice ? 'YES' : 'no', jsErrors: jsErrors.length, brokenAssets: failed.length,
    });
    await ctx.close();
  }
  console.log('=== AFTER DELETING THE TWO LABEL PLUGINS ===');
  console.table(rows);
  const bad = rows.filter(r => r.status !== 200 || r.phpNotice === 'YES' || r.brokenAssets > 0);
  console.log(bad.length ? `\nPROBLEMS on: ${bad.map(r => r.page).join(', ')}` : '\nAll clean: 200s, no PHP notices, no orphaned plugin assets.');
  await b.close();
})();

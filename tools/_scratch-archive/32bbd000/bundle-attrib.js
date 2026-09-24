// Attribute the SiteGround combined CSS bundle to owning plugins, and mark how
// much of each plugin's share is actually used on a given page type.
const { chromium } = require('playwright');

const PAGES = [
  ['homepage', 'https://sids-ponds.com/'],
  ['category', 'https://sids-ponds.com/product-category/all-products/aggregates-soil-mulch/'],
  ['product',  'https://sids-ponds.com/product/all-products/artificial-turf-and-sod/sod/sod/'],
];

// Selector prefix -> owning plugin. Ordered: first match wins, so put the
// specific ones before the generic .woocommerce catch-all.
const OWNERS = [
  [/dgwt-wcas|wcas-/, 'FiboSearch (ajax search)'],
  [/\bfc-|fluid-checkout|has-checkout/, 'Fluid Checkout'],
  [/wmc-|woo-multi-currency/, 'CURCY multi-currency'],
  [/woosw-|wpc-smart-wishlist/, 'WPC Smart Wishlist'],
  [/wpcbn-/, 'WPC Buy Now'],
  [/cwginstock|cwg-/, 'Back In Stock Notifier'],
  [/awl-|advanced-woo-label/, 'Advanced Woo Labels'],
  [/\bacowebs|aco-pl|awcpl/, 'Acowebs Product Labels'],
  [/cmplz/, 'Complianz (cookie banner)'],
  [/bravepop|brave-/, 'Brave Popup'],
  [/et_bloom/, 'Bloom (opt-ins)'],
  [/rank-?math/, 'Rank Math'],
  [/pdfemb/, 'PDF Embedder'],
  [/kgvid|videopack/, 'Videopack'],
  [/ivole|\bcr-review|customer-review/, 'Customer Reviews for Woo'],
  [/wcpdf/, 'PDF Invoices'],
  [/wcpay|woopay|wc-payment/, 'WooPayments'],
  [/\bgla-|google-listings/, 'Google for WooCommerce'],
  [/wdr-|awdr-|discount-rule/, 'Discount Rules'],
  [/fgf-|free-gift/, 'Free Gifts'],
  [/wpf-|themify/, 'Themify Product Filter'],
  [/clickship/, 'ClickShip'],
  [/klaviyo|\bkl-/, 'Klaviyo'],
  [/wp-statistics|wpstatistics/, 'WP Statistics'],
  [/clearsale/, 'ClearSale'],
  [/swiper/, 'Swiper (carousel lib)'],
  [/select2/, 'Select2'],
  [/dashicons/, 'Dashicons'],
  [/\bpys|pixelyoursite/, 'PixelYourSite'],
  [/dipl[_-]/, 'Divi Plus'],
  [/difl[_-]|\bdf_/, 'DiviFlash'],
  [/dmach|de_mach/, 'Divi Machine'],
  [/bodycommerce|bodyshop|\bdbc_/, 'Divi BodyCommerce'],
  [/et_pb|et-db|et_boc|\bet-/, 'Divi theme'],
  [/woocommerce|\bwc-|\bwoo-/, 'WooCommerce core'],
];
const ownerOf = (sel) => { for (const [re, name] of OWNERS) if (re.test(sel)) return name; return '(unattributed)'; };

// Scan minified CSS into blocks with byte offsets, recursing into at-rule groups.
function parseBlocks(css, from, to, out) {
  let i = from, selStart = from, depth = 0;
  while (i < to) {
    const ch = css[i];
    if (ch === '{') {
      const sel = css.slice(selStart, i).trim();
      // find matching close brace
      let d = 1, j = i + 1;
      while (j < to && d > 0) { if (css[j] === '{') d++; else if (css[j] === '}') d--; j++; }
      if (/^@(media|supports|container|layer|scope)/i.test(sel)) {
        parseBlocks(css, i + 1, j - 1, out);
      } else {
        out.push({ sel, s: selStart, e: j });
      }
      i = j; selStart = j;
      continue;
    }
    if (ch === '}') { i++; selStart = i; continue; }
    i++;
  }
  return out;
}

(async () => {
  const b = await chromium.launch();
  for (const [label, url] of PAGES) {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
    const p = await ctx.newPage();
    await p.coverage.startCSSCoverage();
    await p.goto(url, { waitUntil: 'networkidle', timeout: 180000 });
    await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await p.waitForTimeout(1500);
    const cov = await p.coverage.stopCSSCoverage();
    await ctx.close();

    const entry = cov.find(e => e.url.includes('siteground-optimizer-combined-css'));
    if (!entry) { console.log(`${label}: SG bundle not found`); continue; }

    const css = entry.text;
    const blocks = parseBlocks(css, 0, css.length, []);
    // used ranges -> quick lookup
    const ranges = [...entry.ranges].sort((a, c) => a.start - c.start);
    const isUsed = (s, e) => {
      let lo = 0, hi = ranges.length - 1;
      while (lo <= hi) {
        const mid = (lo + hi) >> 1, r = ranges[mid];
        if (r.end <= s) lo = mid + 1;
        else if (r.start >= e) hi = mid - 1;
        else return true;
      }
      return false;
    };

    const agg = new Map();
    let usedTotal = 0;
    for (const bl of blocks) {
      const bytes = bl.e - bl.s;
      const owner = ownerOf(bl.sel);
      const u = isUsed(bl.s, bl.e);
      if (u) usedTotal += bytes;
      const cur = agg.get(owner) || { used: 0, unused: 0 };
      if (u) cur.used += bytes; else cur.unused += bytes;
      agg.set(owner, cur);
    }

    const rows = [...agg.entries()]
      .map(([owner, v]) => ({ owner, KB: +((v.used + v.unused) / 1024).toFixed(1),
                              usedKB: +(v.used / 1024).toFixed(1), deadKB: +(v.unused / 1024).toFixed(1) }))
      .sort((a, c) => c.deadKB - a.deadKB);

    console.log(`\n================ ${label.toUpperCase()} ================`);
    console.log(`bundle ${(css.length / 1024).toFixed(0)} KB, ${blocks.length} rules, ${(usedTotal / 1024).toFixed(1)} KB used (${Math.round(100 * usedTotal / css.length)}%)`);
    console.table(rows.slice(0, 18));
    const dead = rows.reduce((a, r) => a + r.deadKB, 0);
    console.log(`dead weight in bundle: ${dead.toFixed(0)} KB`);
  }
  await b.close();
})();

// Functional tests, because settings alone can't answer this.
//  1. Free Gifts: rules fire on cart contents, so put something IN the cart.
//  2. Advanced Woo Labels: "show default sale" is on, so find a product ON SALE
//     and see whether AWL renders a label next to WooCommerce's own badge.
// Nothing is written to the site; this is a normal shopping session.
const { chromium } = require('playwright');

(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
  const p = await ctx.newPage();

  // ---------- 1. find an on-sale product ----------
  await p.goto('https://sids-ponds.com/shop/', { waitUntil: 'networkidle', timeout: 180000 });
  const sale = await p.evaluate(() => {
    const badges = [...document.querySelectorAll('.onsale, span.onsale')];
    const awl = document.querySelectorAll('[class*="awl-"]').length;
    const aco = document.querySelectorAll('[class*="awcpl"],[class*="aco-pl"]').length;
    const first = badges[0] ? badges[0].closest('li.product, .product')?.querySelector('a[href*="/product/"]')?.href : null;
    return { saleBadges: badges.length, awlElements: awl, acoElements: aco, firstSaleProduct: first };
  });
  console.log('=== SHOP PAGE ===');
  console.log(`  WooCommerce sale badges : ${sale.saleBadges}`);
  console.log(`  Advanced Woo Labels els : ${sale.awlElements}`);
  console.log(`  Acowebs label elements  : ${sale.acoElements}`);
  console.log(`  first on-sale product   : ${sale.firstSaleProduct || '(none found)'}`);

  if (sale.firstSaleProduct) {
    await p.goto(sale.firstSaleProduct, { waitUntil: 'networkidle', timeout: 180000 });
    const onProd = await p.evaluate(() => ({
      saleBadge: document.querySelectorAll('.onsale').length,
      awl: document.querySelectorAll('[class*="awl-"]').length,
      aco: document.querySelectorAll('[class*="awcpl"],[class*="aco-pl"]').length,
    }));
    console.log(`\n  on that product page -> sale badge ${onProd.saleBadge}, AWL ${onProd.awl}, Acowebs ${onProd.aco}`);
  }

  // ---------- 2. add a product to the cart, then inspect the cart ----------
  console.log('\n=== FREE GIFTS: adding a product to the cart ===');
  await p.goto('https://sids-ponds.com/shop/', { waitUntil: 'networkidle', timeout: 180000 });
  const added = await p.evaluate(async () => {
    const btn = document.querySelector('a.add_to_cart_button[data-product_id], a.ajax_add_to_cart[data-product_id]');
    if (!btn) return { ok: false, why: 'no ajax add-to-cart button on shop page' };
    const id = btn.getAttribute('data-product_id');
    const r = await fetch(`/?add-to-cart=${id}&quantity=1`, { credentials: 'include' });
    return { ok: r.ok, id, status: r.status };
  });
  console.log('  add to cart:', JSON.stringify(added));

  await p.goto('https://sids-ponds.com/cart/', { waitUntil: 'networkidle', timeout: 180000 });
  const cart = await p.evaluate(() => {
    const txt = document.body.innerText;
    return {
      cartHasItems: /Subtotal|subtotal/.test(txt) && !/cart is currently empty/i.test(txt),
      fgfElements: document.querySelectorAll('[class*="fgf"],[class*="free-gift"]').length,
      giftHeading: /Choose Your Gift/i.test(txt),
      eligibilityNotice: /eligible for Free Gift/i.test(txt),
      fgfAssets: performance.getEntriesByType('resource').filter(r => /free-gifts-for-woocommerce/.test(r.name)).length,
    };
  });
  console.log('  cart state:'); console.table([cart]);

  await b.close();
})();

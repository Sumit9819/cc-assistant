/**
 * Preview the proposed product-card design on the LIVE homepage carousel.
 *
 * The card markup comes from DiviFlash, whose own CSS I cannot fully read from
 * outside, so writing selectors blind risks fighting rules that already exist
 * (or silently doing nothing). This injects exactly the CSS the pending change
 * would add, then screenshots the carousel before and after so the design is
 * judged on the real DOM rather than on a mock-up.
 */
import { chromium } from 'playwright';

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const OUT = process.argv[2] || '.';

const CSS = `
.dipl_single_woo_product{background:#fff;border:1px solid #dce0cd;border-radius:14px;overflow:hidden;height:100%;display:flex;flex-direction:column;box-shadow:0 3px 14px rgba(30,40,20,.10);transition:transform .2s ease,box-shadow .2s ease;}
.dipl_single_woo_product:hover{transform:translateY(-6px);box-shadow:0 16px 34px rgba(30,40,20,.18);}
.dipl_single_woo_product_thumbnail_wrapper{background:#fff;padding:16px 16px 4px;}
.dipl_single_woo_product_thumbnail img{border-radius:10px;border-bottom:3px solid #9cc92a;}
.dipl_single_woo_product_sale_badge{border-radius:20px;padding:5px 13px;font-size:11px;font-weight:700;letter-spacing:.08em;line-height:1.5;box-shadow:0 1px 4px rgba(0,0,0,.14);}
.dipl_single_woo_product_content{padding:14px 16px 18px;flex:1;}
.dipl_single_woo_product_title{margin:0 0 10px;font-size:15px;line-height:1.35;height:41px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.dipl_single_woo_product_price{line-height:1.2;}
.dipl_single_woo_product_price del{display:block;font-size:13px;font-weight:400;margin:0 0 1px;}
.dipl_single_woo_product_price del ins{text-decoration:line-through;background:none;font-size:inherit;font-weight:inherit;}
.dipl_single_woo_product .dipl_single_woo_product_price del ins span{color:#767676 !important;}
.dipl_single_woo_product_price ins{display:block;text-decoration:none;background:none;font-size:20px;font-weight:800;}
/* mirrors what the NATIVE attrs emit, at the same specificity */
.dipl_woo_products_carousel_0 .dipl_single_woo_product_price ins span{color:#1F4D12 !important;}
.dipl_woo_products_carousel_0 .dipl_single_woo_product_sale_badge{color:#173000 !important;}
`;

const b = await chromium.launch();
const ctx = await b.newContext({ userAgent: UA, viewport: { width: 1400, height: 1000 }, deviceScaleFactor: 2 });
const p = await ctx.newPage();
await p.goto('https://sids-ponds.com/?cc=' + Date.now(), { waitUntil: 'networkidle', timeout: 120000 });

// clear the cookie banner so it never sits over the shot
const accept = await p.$('.cmplz-btn.cmplz-accept, #cmplz-accept, .cmplz-accept');
if (accept) { await accept.click().catch(() => {}); await p.waitForTimeout(800); }
await p.evaluate(() => {
  document.querySelectorAll('.cmplz-cookiebanner,#cmplz-cookiebanner-container,.intercom-lightweight-app').forEach(n => n.remove());
});

const carousel = await p.$('.dipl_woo_products_carousel');
if (!carousel) { console.log('CAROUSEL NOT FOUND'); await b.close(); process.exit(1); }
await carousel.scrollIntoViewIfNeeded();
await p.waitForTimeout(1200);
await carousel.screenshot({ path: OUT + '/cards-before.png' });

// what does DiviFlash already give the badge and the price?
const probe = await p.evaluate(() => {
  const badge = document.querySelector('.dipl_single_woo_product_sale_badge');
  const price = document.querySelector('.dipl_single_woo_product_price');
  const card  = document.querySelector('.dipl_single_woo_product');
  const cs = (el) => el ? getComputedStyle(el) : null;
  const b = cs(badge), pr = cs(price), c = cs(card);
  return {
    badge: b && { position: b.position, top: b.top, left: b.left, color: b.color, background: b.backgroundColor, radius: b.borderRadius },
    price: pr && { display: pr.display, textAlign: pr.textAlign },
    card:  c && { display: c.display, background: c.backgroundColor, border: c.border, radius: c.borderRadius },
  };
});
console.log('BEFORE computed:', JSON.stringify(probe, null, 2));

await p.addStyleTag({ content: CSS });
await p.waitForTimeout(900);
await carousel.screenshot({ path: OUT + '/cards-after.png' });

const after = await p.evaluate(() => {
  const badge = document.querySelector('.dipl_single_woo_product_sale_badge');
  const del   = document.querySelector('.dipl_single_woo_product_price del');
  const ins   = document.querySelector('.dipl_single_woo_product_price > span > ins, .dipl_single_woo_product_price ins:not(del ins)');
  const cs = (el) => el ? getComputedStyle(el) : null;
  const b = cs(badge), d = cs(del), i = cs(ins);
  return {
    badgeColor: b && b.color,
    delSize: d && d.fontSize, delOpacity: d && d.opacity,
    insSize: i && i.fontSize, insWeight: i && i.fontWeight, insColor: i && i.color,
  };
});
console.log('AFTER computed:', JSON.stringify(after, null, 2));
console.log('screenshots written to', OUT);
await b.close();

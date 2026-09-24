/**
 * Screenshot the live sids-ponds footer payment row + social icons, so the
 * inline-SVG swap can be compared before/after on the real page rather than
 * only in a synthetic preview.
 */
import { chromium } from 'playwright';

const out = process.argv[2];
const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const b = await chromium.launch();
const ctx = await b.newContext({ userAgent: UA, viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
const p = await ctx.newPage();
await p.goto('https://sids-ponds.com/?cc=' + Date.now(), { waitUntil: 'networkidle', timeout: 90000 });
// The Complianz banner overlays the footer, so dismiss it before capturing.
const accept = await p.$('.cmplz-btn.cmplz-accept, #cmplz-accept, .cmplz-accept');
if (accept) { await accept.click().catch(() => {}); await p.waitForTimeout(1200); }
await p.evaluate(() => {
  document.querySelectorAll('.cmplz-cookiebanner, #cmplz-cookiebanner-container').forEach((n) => n.remove());
});
await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await p.waitForTimeout(3500);

const pay = await p.$('.payment-icons');
if (pay) {
  await pay.screenshot({ path: out.replace('.png', '-payment.png') });
  console.log('payment row captured');
} else {
  console.log('!! .payment-icons not found');
}

const social = await p.$('.et_pb_social_media_follow');
if (social) {
  await social.screenshot({ path: out.replace('.png', '-social.png') });
  console.log('social row captured');
} else {
  console.log('!! social row not found');
}

await b.close();

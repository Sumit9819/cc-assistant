/**
 * End-to-end pre-flight: load the LIVE sids-ponds footer, inject exactly the CSS
 * and markup the pending change would introduce, and screenshot the result.
 *
 * This exists because the earlier synthetic preview styled the TikTok ::before
 * with position:absolute/inset:0 — my own CSS, not Divi's. If Divi's ::before
 * has no box of its own, content:"" collapses it to zero size and the
 * background never paints. Only the real page can settle that.
 */
import { chromium } from 'playwright';

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const SP = process.argv[2];

const b = await chromium.launch();
const ctx = await b.newContext({ userAgent: UA, viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
const p = await ctx.newPage();
await p.goto('https://sids-ponds.com/?cc=' + Date.now(), { waitUntil: 'networkidle', timeout: 90000 });

const accept = await p.$('.cmplz-btn.cmplz-accept, #cmplz-accept, .cmplz-accept');
if (accept) { await accept.click().catch(() => {}); await p.waitForTimeout(1000); }
await p.evaluate(() => {
  document.querySelectorAll('.cmplz-cookiebanner, #cmplz-cookiebanner-container').forEach((n) => n.remove());
});

// What box does Divi actually give the TikTok icon and its ::before?
const before = await p.evaluate(() => {
  const a = document.querySelector('.et-social-tiktok a.icon');
  if (!a) return { found: false };
  const cs = getComputedStyle(a);
  const pb = getComputedStyle(a, '::before');
  return {
    found: true,
    aBox: { w: cs.width, h: cs.height, display: cs.display, lineHeight: cs.lineHeight },
    beforeBox: { w: pb.width, h: pb.height, display: pb.display, content: pb.content, pos: pb.position },
  };
});
console.log('BEFORE inject:', JSON.stringify(before, null, 2));

// Inject the exact rule from the pending change.
const TIKTOK = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20448%20512%22%20fill%3D%22%23FFFBEA%22%3E%3Cpath%20d%3D%22M448%2C209.91a210.06%2C210.06%2C0%2C0%2C1-122.77-39.25V349.38A162.55%2C162.55%2C0%2C1%2C1%2C185%2C188.31V278.2a74.62%2C74.62%2C0%2C1%2C0%2C52.23%2C71.18V0l88%2C0a121.18%2C121.18%2C0%2C0%2C0%2C1.86%2C22.17h0A122.18%2C122.18%2C0%2C0%2C0%2C381%2C102.39a121.43%2C121.43%2C0%2C0%2C0%2C67%2C20.14Z%22%2F%3E%3C%2Fsvg%3E';

await p.addStyleTag({
  content:
    '.et-social-tiktok a.icon:before { content: "" !important; background: url("' +
    TIKTOK + '") no-repeat center / 22px 22px; }',
});
await p.waitForTimeout(1200);

const after = await p.evaluate(() => {
  const a = document.querySelector('.et-social-tiktok a.icon');
  const pb = getComputedStyle(a, '::before');
  return { w: pb.width, h: pb.height, display: pb.display, content: pb.content, bg: pb.backgroundImage.slice(0, 40) };
});
console.log('AFTER inject :', JSON.stringify(after, null, 2));

await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await p.waitForTimeout(2000);
const social = await p.$('.et_pb_social_media_follow');
if (social) await social.screenshot({ path: SP + '/inject-social.png' });
console.log('screenshot captured');

await b.close();

/**
 * Prove the actual benefit: with all five brand glyphs replaced, does
 * fa-brands-400.woff2 (109,808 bytes) stop downloading?
 *
 * Browsers fetch a webfont only when a rendered glyph needs it, so replacing
 * every brands consumer SHOULD prevent the request. That is the entire
 * justification for the change, so it gets measured rather than argued.
 *
 * Run 1: the live page untouched            -> expect fa-brands to load
 * Run 2: the same page with the change applied before first paint
 *        (payment glyphs swapped for SVG + the TikTok rule injected)
 *                                            -> expect fa-brands NOT to load
 */
import { chromium } from 'playwright';

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const TIKTOK =
  'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20448%20512%22%20fill%3D%22%23FFFBEA%22%3E%3Cpath%20d%3D%22M448%2C209.91a210.06%2C210.06%2C0%2C0%2C1-122.77-39.25V349.38A162.55%2C162.55%2C0%2C1%2C1%2C185%2C188.31V278.2a74.62%2C74.62%2C0%2C1%2C0%2C52.23%2C71.18V0l88%2C0a121.18%2C121.18%2C0%2C0%2C0%2C1.86%2C22.17h0A122.18%2C122.18%2C0%2C0%2C0%2C381%2C102.39a121.43%2C121.43%2C0%2C0%2C0%2C67%2C20.14Z%22%2F%3E%3C%2Fsvg%3E';

async function run(applyChange) {
  const b = await chromium.launch();
  const ctx = await b.newContext({ userAgent: UA, viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();

  const fonts = [];
  p.on('response', async (r) => {
    const u = r.url();
    if (/\.woff2?(\?|$)/i.test(u) || /fontawesome|font-awesome/i.test(u)) {
      let len = r.headers()['content-length'];
      if (!len) { try { len = (await r.body()).length; } catch { len = '?'; } }
      fonts.push({ file: u.split('/').pop().split('?')[0], bytes: len });
    }
  });

  if (applyChange) {
    // Apply the change BEFORE the document paints, exactly as the real edit would:
    // strip the literal FA glyphs out of .payment-icons and drop the FontAwesome
    // family off that container, plus the TikTok rule.
    await p.addInitScript(() => {
      document.addEventListener('DOMContentLoaded', () => {
        const wrap = document.querySelector('.payment-icons');
        if (wrap) {
          wrap.style.fontFamily = 'inherit';
          wrap.querySelectorAll('div').forEach((d) => {
            d.textContent = '';
            const s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            s.setAttribute('viewBox', '0 0 576 512');
            s.style.height = '1em';
            d.appendChild(s);
          });
        }
        const st = document.createElement('style');
        st.textContent =
          '.et-social-tiktok a.icon:before{content:"" !important;background:url("' +
          TIKTOK + '") no-repeat center / 22px 22px;}';
        document.head.appendChild(st);
      });
    });
  }

  await p.goto('https://sids-ponds.com/?cc=' + Date.now(), { waitUntil: 'load', timeout: 180000 });
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await p.waitForTimeout(9000);
  await b.close();
  return fonts;
}

for (const [label, apply] of [['UNTOUCHED', false], ['WITH CHANGE', true]]) {
  const f = await run(apply);
  const brands = f.filter((x) => /fa-brands/i.test(x.file));
  const solid = f.filter((x) => /fa-solid/i.test(x.file));
  console.log('\n=== ' + label + ' ===');
  console.log('  fa-brands-400.woff2 :', brands.length ? brands.map((x) => x.bytes + 'B').join(', ') : 'NOT LOADED');
  console.log('  fa-solid-900.woff2  :', solid.length ? solid.map((x) => x.bytes + 'B').join(', ') : 'NOT LOADED');
  console.log('  all font requests   :', f.map((x) => x.file).join(', ') || 'none');
}

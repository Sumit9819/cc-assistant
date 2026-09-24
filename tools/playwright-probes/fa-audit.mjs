/**
 * Is Font Awesome actually USED on sids-ponds, or is it 102KB of dead weight?
 *
 * The raw HTML shows no fa-* icon classes, but a parser can only prove
 * presence, never absence — JS could inject icons after load. This checks the
 * RENDERED DOM after network idle, and also watches what the browser really
 * downloads (the CSS itself, plus any webfont files it pulls in).
 */
import { chromium } from 'playwright';

const URLS = [
  'https://sids-ponds.com/',
  'https://sids-ponds.com/about-us/',
  'https://sids-ponds.com/contact/',
  'https://sids-ponds.com/product-category/all-products/green-living-wall-kits/',
];

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const browser = await chromium.launch();
for (const url of URLS) {
  const ctx = await browser.newContext({ userAgent: UA });
  const page = await ctx.newPage();

  const net = [];
  page.on('response', async (r) => {
    const u = r.url();
    if (/fontawesome|font-awesome|cdnjs/i.test(u)) {
      let len = r.headers()['content-length'];
      if (!len) { try { len = (await r.body()).length; } catch { len = '?'; } }
      net.push({ url: u.split('/').slice(-1)[0], status: r.status(), bytes: len });
    }
  });

  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(1500);

  const used = await page.evaluate(() => {
    const RX = /(^|\s)(fa|fas|far|fab|fal|fad)(\s|$)|fa-[a-z0-9]/i;
    const hits = [];
    for (const el of document.querySelectorAll('*')) {
      const cls = typeof el.className === 'string' ? el.className : (el.getAttribute?.('class') || '');
      if (cls && RX.test(cls)) hits.push({ tag: el.tagName.toLowerCase(), cls: cls.slice(0, 70) });
    }
    // Any element whose ::before actually resolves to a Font Awesome family?
    let pseudo = 0;
    for (const el of document.querySelectorAll('*')) {
      const cs = getComputedStyle(el, '::before');
      if (cs && /awesome/i.test(cs.fontFamily || '')) pseudo++;
    }
    const loaded = [];
    document.fonts.forEach((f) => { if (f.status === 'loaded') loaded.push(f.family); });
    return { hits: hits.slice(0, 12), count: hits.length, pseudo, fonts: [...new Set(loaded)] };
  });

  console.log('\n=== ' + url.replace('https://sids-ponds.com', '') || '/');
  console.log('  elements with an fa class : ' + used.count);
  if (used.hits.length) console.log('  examples                  : ' + JSON.stringify(used.hits));
  console.log('  ::before using FA family  : ' + used.pseudo);
  console.log('  FA/cdnjs network requests : ' + (net.length ? JSON.stringify(net) : 'none'));
  console.log('  loaded font families      : ' + used.fonts.join(', '));

  await ctx.close();
}
await browser.close();

/**
 * Measure how wide an in-body card ACTUALLY renders, and walk its ancestor
 * chain to find which element is costing the width.
 *
 * Two traps this handles, both of which produced a wrong answer first:
 *  - SiteGround's edge cache served a pre-swap copy of the page, so the
 *    measurement described markup that is no longer live. Hence the cache bust.
 *  - The in-body cards are lazy-loaded, so above-the-fold-only measuring finds
 *    nothing but placeholder GIFs. Hence the scroll pass before measuring.
 *
 * usage: node measure_mobile.mjs <url> [viewportWidth] [injectCssFile]
 */
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json');
const { chromium } = require('playwright');

const url = process.argv[2];
const width = Number(process.argv[3] || 390);
const cssFile = process.argv[4];

const browser = await chromium.launch();
const ctx = await browser.newContext({
  viewport: { width, height: 844 },
  deviceScaleFactor: 2,
  isMobile: width < 800,
  hasTouch: width < 800,
  // SiteGround's WAF 403s bot user agents; a real browser UA passes.
  userAgent: width < 800
    ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
    : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
});
const page = await ctx.newPage();
const bust = url + (url.includes('?') ? '&' : '?') + 'ccbust=' + Date.now();
const resp = await page.goto(bust, { waitUntil: 'networkidle', timeout: 90000 });

// trigger every lazy-loader before measuring anything
await page.evaluate(async () => {
  for (let y = 0; y < document.body.scrollHeight; y += 400) {
    scrollTo(0, y);
    await new Promise((r) => setTimeout(r, 60));
  }
  scrollTo(0, 0);
});
await page.waitForTimeout(1500);

if (cssFile) {
  await page.addStyleTag({ content: fs.readFileSync(cssFile, 'utf8') });
  await page.waitForTimeout(300);
}

const data = await page.evaluate(() => {
  const out = { viewport: innerWidth, css: null, images: [] };
  const imgs = [...document.querySelectorAll('img')].filter((i) =>
    /\/uploads\/2026\/09\/.*\.webp/.test(i.currentSrc || i.src || '')
  );
  for (const img of imgs.slice(0, 2)) {
    const r = img.getBoundingClientRect();
    const chain = [];
    let el = img;
    for (let i = 0; i < 7 && el; i++) {
      const cs = getComputedStyle(el);
      const er = el.getBoundingClientRect();
      chain.push({
        tag: el.tagName.toLowerCase(),
        cls: (el.className || '').toString().slice(0, 55),
        w: Math.round(er.width),
        pad: `${cs.paddingLeft}/${cs.paddingRight}`,
        mar: `${cs.marginLeft}/${cs.marginRight}`,
        maxW: cs.maxWidth,
      });
      el = el.parentElement;
    }
    out.images.push({
      file: (img.currentSrc || img.src).split('/').pop().split('?')[0],
      renderedW: Math.round(r.width),
      renderedH: Math.round(r.height),
      gapLeft: Math.round(r.left),
      gapRight: Math.round(innerWidth - r.right),
      chain,
    });
  }
  return out;
});

console.log('HTTP', resp.status(), '| viewport', width);
console.log(JSON.stringify(data, null, 1));
await browser.close();

/**
 * Before/after screenshots of a card in the page, plus the check that matters
 * for a 100vw rule: does the page now scroll sideways?
 *
 * usage: node shoot_bleed.mjs <url> <width> <outPrefix>
 */
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json');
const { chromium } = require('playwright');

const [url, wRaw, prefix] = process.argv.slice(2);
const width = Number(wRaw);
const css = fs.readFileSync('bleed.css', 'utf8');

const browser = await chromium.launch();

async function shot(applyCss, tag) {
  const ctx = await browser.newContext({
    viewport: { width, height: 844 },
    deviceScaleFactor: 2,
    isMobile: width < 800,
    hasTouch: width < 800,
    userAgent: width < 800
      ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
      : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
  });
  const page = await ctx.newPage();
  await page.goto(url + (url.includes('?') ? '&' : '?') + 'ccbust=' + Date.now(),
    { waitUntil: 'networkidle', timeout: 90000 });
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 400) {
      scrollTo(0, y); await new Promise((r) => setTimeout(r, 60));
    }
    scrollTo(0, 0);
  });
  await page.waitForTimeout(1200);
  // The site re-opens its Elementor popup after any JS removal, so hide the
  // overlays with CSS - which the page cannot undo - on BOTH shots equally.
  await page.addStyleTag({ content: `
    /* #bcp-overlay is the coupon popup (a separate plugin, not Elementor -
       which is why the Elementor selectors missed it). */
    #bcp-overlay, .dialog-widget, .dialog-lightbox-widget,
    .elementor-popup-modal, .elementor-location-popup,
    div[class*="tawk"], iframe[title*="chat" i], iframe[id*="chat"],
    #tawkchat-container, .tawk-min-container, [class*="chat-widget"] {
      display: none !important;
    }
    html, body { overflow: auto !important; }
  ` });
  await page.waitForTimeout(400);

  if (applyCss) { await page.addStyleTag({ content: css }); await page.waitForTimeout(400); }

  const info = await page.evaluate(() => {
    const img = [...document.querySelectorAll('img')]
      .find((i) => /\/uploads\/2026\/09\/.*\.webp/.test(i.currentSrc || i.src || ''));
    if (img) img.scrollIntoView({ block: 'center' });
    return {
      scrollW: document.documentElement.scrollWidth,
      inner: innerWidth,
      horizontalScroll: document.documentElement.scrollWidth > innerWidth + 1,
      imgW: img ? Math.round(img.getBoundingClientRect().width) : null,
    };
  });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${prefix}-${tag}.png` });
  console.log(tag, JSON.stringify(info));
  await ctx.close();
}

await shot(false, 'before');
await shot(true, 'after');
await browser.close();

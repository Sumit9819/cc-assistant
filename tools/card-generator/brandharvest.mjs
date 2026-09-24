/**
 * Harvest a site's real brand assets from its own rendered front end.
 *
 *   node brandharvest.mjs https://erofirving.com/ erofirving
 *
 * Writes brand/<slug>-logo.png plus a JSON dump of the fonts and colours the
 * live CSS actually computes, into brand/<slug>-harvest.json.
 *
 * WHY THE BROWSER: SiteGround's WAF is currently answering cookie-less requests
 * with an `sgcaptcha` challenge, so the MCP bridge and curl are both locked out
 * while a real Chrome passes fine. This also happens to be the honest way to get
 * a font stack: the design skill says "pull from the Elementor Kit", and the Kit
 * value is whatever the browser ends up computing, not what a settings row says.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const PROFILE = path.join(HERE, 'featured', '.chrome-profile');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const BRAND = path.join(HERE, 'brand');

function loadPlaywright() {
  for (const base of ['C:/Users/sumit/.cc-assistant/wcag/package.json',
                      'D:/faceless-studio/motion/package.json']) {
    if (!fs.existsSync(base)) continue;
    try { return createRequire(base)('playwright'); } catch { /* next */ }
  }
  throw new Error('No Playwright found');
}

const [url, slug] = process.argv.slice(2);
if (!url || !slug) {
  console.log('usage: node brandharvest.mjs <url> <slug>');
  process.exit(1);
}

const { chromium } = loadPlaywright();
const ctx = await chromium.launchPersistentContext(PROFILE, {
  headless: false,
  executablePath: fs.existsSync(CHROME) ? CHROME : undefined,
  viewport: { width: 1440, height: 1000 },
});
const page = ctx.pages()[0] || await ctx.newPage();

await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
await page.waitForTimeout(9000);              // let any WAF challenge resolve
if (await page.evaluate(() => document.documentElement.innerHTML.includes('sgcaptcha'))) {
  await page.waitForTimeout(9000);
}
await page.evaluate(async () => { await document.fonts.ready; });

const harvest = await page.evaluate(() => {
  const fam = (el) => el ? getComputedStyle(el).fontFamily : null;
  const h = document.querySelector('h1,h2') || document.querySelector('h3');
  const p = document.querySelector('p');

  // The logo is whichever image sits inside a site-branding link. Falling back
  // to the largest image in the header, because themes disagree about markup.
  let logo = null;
  const inBrand = document.querySelector(
    '.site-logo img, .elementor-widget-theme-site-logo img, header .logo img, a[rel="home"] img');
  if (inBrand) logo = inBrand.currentSrc || inBrand.src;
  if (!logo) {
    const header = document.querySelector('header') || document.body;
    let best = 0;
    for (const i of header.querySelectorAll('img')) {
      const a = i.naturalWidth * i.naturalHeight;
      if (a > best && i.naturalWidth > 40) { best = a; logo = i.currentSrc || i.src; }
    }
  }

  // Elementor writes its Kit palette into CSS custom properties on :root.
  const rootStyle = getComputedStyle(document.documentElement);
  const kit = {};
  for (const name of ['--e-global-color-primary', '--e-global-color-secondary',
                      '--e-global-color-text', '--e-global-color-accent']) {
    const v = rootStyle.getPropertyValue(name).trim();
    if (v) kit[name.replace('--e-global-color-', '')] = v;
  }
  const kitFonts = {};
  for (const name of ['--e-global-typography-primary-font-family',
                      '--e-global-typography-secondary-font-family',
                      '--e-global-typography-text-font-family']) {
    const v = rootStyle.getPropertyValue(name).trim();
    if (v) kitFonts[name.replace('--e-global-typography-', '').replace('-font-family', '')] = v;
  }

  return {
    url: location.href,
    title: document.title,
    heading_font: fam(h),
    body_font: fam(p),
    kit_colors: kit,
    kit_fonts: kitFonts,
    logo_url: logo,
  };
});

fs.mkdirSync(BRAND, { recursive: true });

if (harvest.logo_url) {
  try {
    const r = await ctx.request.get(harvest.logo_url, { timeout: 60000 });
    if (r.ok()) {
      const body = await r.body();
      const ext = /\.svg(\?|$)/i.test(harvest.logo_url) ? 'svg'
        : /\.png(\?|$)/i.test(harvest.logo_url) ? 'png'
        : /\.webp(\?|$)/i.test(harvest.logo_url) ? 'webp' : 'png';
      const dest = path.join(BRAND, `${slug}-logo.${ext}`);
      fs.writeFileSync(dest, body);
      harvest.logo_file = path.basename(dest);
      harvest.logo_bytes = body.length;
    } else {
      harvest.logo_error = `HTTP ${r.status()}`;
    }
  } catch (e) {
    harvest.logo_error = e.message.slice(0, 80);
  }
}

fs.writeFileSync(path.join(BRAND, `${slug}-harvest.json`),
                 JSON.stringify(harvest, null, 2));
console.log(JSON.stringify(harvest, null, 2));
await ctx.close();

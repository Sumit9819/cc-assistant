// Prove the two candidate fixes by blocking what they would defer, and
// measuring LCP/CLS against a same-session baseline.
const { chromium } = require('playwright');

// The 9 slide backgrounds that are display:none at load (slide 0 is the one shown).
const HIDDEN_SLIDES = [
  'Kichler-website-banner-final-', 'Aquascape-Home-Page-Banner', 'Laguna-Home-Page-Banner',
  'GREENHORIZONS-BANNER-LOGO-BOTTON', 'Sids-Ponds-Green-Living-Wall-Slider-min',
  'Grabo-Suction-Vacuum', 'SidsPondsShoot-June26th2024170of283-scaled',
  'lillies-slider-bgJPG-min', 'turf-1920x393JPEG-min2',
];
// Below-the-fold product-carousel images that currently load eagerly.
const PRODUCT_IMG = /(-600x600|-600x553|1320x1300|1320x1320)\.(png|jpe?g|webp)|Max-Flo|Power-Jet|Submersible-Fountain|Ultra-Klean|Pressure-Flo|Aeration-Kit/i;

const CONDITIONS = [
  { name: 'baseline',                    slides: false, products: false },
  { name: 'lazy-load product images',    slides: false, products: true  },
  { name: 'defer 9 unseen slides',       slides: true,  products: false },
  { name: 'both',                        slides: true,  products: true  },
];

const run = async (b, cond) => {
  const ctx = await b.newContext({
    viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150,
    downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  let blockedKb = 0;
  await p.route('**/*', (route) => {
    const u = route.request().url();
    const isImg = route.request().resourceType() === 'image';
    if (isImg) {
      if (cond.slides && HIDDEN_SLIDES.some(s => u.includes(s))) return route.abort();
      if (cond.products && PRODUCT_IMG.test(u)) return route.abort();
    }
    route.continue();
  });

  await p.addInitScript(() => {
    window.__lcp = 0; window.__cls = 0;
    new PerformanceObserver(l => { window.__lcp = l.getEntries().at(-1).startTime; })
      .observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; })
      .observe({ type: 'layout-shift', buffered: true });
  });

  await p.goto('https://sids-ponds.com/', { waitUntil: 'load', timeout: 240000 });
  await p.waitForTimeout(9000);

  const m = await p.evaluate(() => {
    const imgs = performance.getEntriesByType('resource').filter(r => /\.(png|jpe?g|webp|avif)/i.test(r.name));
    return {
      lcp: Math.round(window.__lcp), cls: +window.__cls.toFixed(4),
      fcp: Math.round((performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime || 0),
      imgKb: Math.round(imgs.reduce((a, r) => a + (r.transferSize || r.encodedBodySize || 0), 0) / 1024),
      imgCount: imgs.length,
    };
  });
  await ctx.close();
  return m;
};

(async () => {
  const b = await chromium.launch();
  const rows = [];
  for (const c of CONDITIONS) {
    const m = await run(b, c);
    rows.push({ condition: c.name, FCP: m.fcp, LCP: m.lcp, CLS: m.cls, imageKB: m.imgKb, images: m.imgCount });
    console.log(`  ${c.name.padEnd(26)} FCP ${m.fcp}  LCP ${m.lcp}  CLS ${m.cls}  ${m.imgKb} KB / ${m.imgCount} imgs`);
  }
  console.log('');
  console.table(rows);
  const base = rows[0];
  rows.slice(1).forEach(r => {
    console.log(`${r.condition}: LCP ${base.LCP - r.LCP >= 0 ? '-' : '+'}${Math.abs(base.LCP - r.LCP)}ms, images -${base.imageKB - r.imageKB} KB`);
  });
  await b.close();
})();

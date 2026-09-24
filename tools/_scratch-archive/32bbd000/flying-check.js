// Is Flying Scripts actually catching the heavy third-party scripts, and when
// do they end up executing?
const { chromium } = require('playwright');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

const PAGES = [
  ['homepage', 'https://sids-ponds.com/'],
  ['product',  'https://sids-ponds.com/product/all-products/artificial-turf-and-sod/sod/sod/'],
  ['cart',     'https://sids-ponds.com/cart/'],
  ['checkout', 'https://sids-ponds.com/checkout/'],
];

(async () => {
  console.log('=== Which scripts are wrapped as type="text/flyingscripts"? ===');
  for (const [label, url] of PAGES) {
    const html = await (await fetch(url, { headers: { 'User-Agent': UA } })).text();
    const wrapped = [...html.matchAll(/<script[^>]*type=["']text\/flyingscripts["'][^>]*>/gi)]
      .map(m => { const s = m[0].match(/src=["']([^"']+)["']/); return s ? s[1].split('/').pop().split('?')[0].slice(0, 40) : '(inline)'; });
    // and which heavy 3rd parties are still normal <script src>
    const normal = [...html.matchAll(/<script[^>]+src=["']([^"']+)["'][^>]*>/gi)]
      .map(m => m[1])
      .filter(u => /googletagmanager|facebook|intercom|affirm|pixelyoursite|clarity/i.test(u))
      .filter(u => { const idx = html.indexOf(u); const tag = html.slice(Math.max(0, idx - 200), idx); return !/flyingscripts/i.test(tag); })
      .map(u => u.split('/').pop().split('?')[0].slice(0, 40));
    console.log(`\n  ${label}: ${wrapped.length} delayed, ${normal.length} NOT delayed`);
    if (wrapped.length) console.log(`     delayed : ${[...new Set(wrapped)].join(', ')}`);
    if (normal.length)  console.log(`     NOT     : ${[...new Set(normal)].join(', ')}`);
  }

  console.log('\n\n=== WHEN do the third parties actually execute? (throttled mobile, no interaction) ===');
  const b = await chromium.launch();
  const ctx = await b.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 3,
    userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150,
    downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  const t0 = Date.now();
  const fired = [];
  p.on('request', (r) => {
    if (/googletagmanager|connect\.facebook|intercom|affirm\.com|clarity\.ms/i.test(r.url()))
      fired.push({ at_ms: Date.now() - t0, file: r.url().split('/').pop().split('?')[0].slice(0, 34) });
  });

  await p.goto('https://sids-ponds.com/', { waitUntil: 'load', timeout: 240000 });
  const loadAt = Date.now() - t0;
  await p.waitForTimeout(14000);   // sit still, never interact

  console.log(`  page load event at ${loadAt}ms; no interaction performed`);
  const seen = new Set();
  console.table(fired.filter(f => { if (seen.has(f.file)) return false; seen.add(f.file); return true; }).slice(0, 14));

  await b.close();
})();

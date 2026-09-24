// Two questions: is PixelYourSite still throwing and tracking nothing, and is
// the same conversion/pixel ID being fired by more than one plugin?
const { chromium } = require('playwright');

(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 3,
    userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const p = await ctx.newPage();

  const errors = [];
  p.on('pageerror', e => errors.push(String(e).slice(0, 130)));
  p.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 120)); });

  const tags = [];
  p.on('request', r => {
    const u = r.url();
    let m;
    if ((m = u.match(/googletagmanager\.com\/(gtag\/js|gtm\.js)\?[^&]*id=([\w-]+)/))) tags.push({ kind: 'Google', id: m[2], url: u.slice(0, 60) });
    if ((m = u.match(/facebook\.com\/tr\/?\?[^]*?id=(\d+)/)))                          tags.push({ kind: 'FB pixel', id: m[1], url: u.slice(0, 60) });
    if ((m = u.match(/connect\.facebook\.net\/[^/]+\/fbevents\.js/)))                   tags.push({ kind: 'FB loader', id: '-', url: u.slice(0, 60) });
    if (/google-analytics\.com\/(g\/)?collect/.test(u))                                 tags.push({ kind: 'GA hit', id: (u.match(/tid=([\w-]+)/) || [])[1] || '?', url: u.slice(0, 60) });
  });

  await p.goto('https://sids-ponds.com/', { waitUntil: 'load', timeout: 240000 });
  await p.waitForTimeout(12000);

  const state = await p.evaluate(() => ({
    pysOptions: typeof window.pysOptions,
    pys: typeof window.pys,
    dataLayerLen: Array.isArray(window.dataLayer) ? window.dataLayer.length : 'absent',
    fbq: typeof window.fbq,
    gtag: typeof window.gtag,
    wgact: typeof window.wgact_settings !== 'undefined' ? 'present (Pixel Manager)' : 'absent',
  }));

  console.log('=== JS errors on the homepage ===');
  [...new Set(errors)].slice(0, 8).forEach(e => console.log('  ' + e));
  if (!errors.length) console.log('  none');

  console.log('\n=== global state ===');
  console.table([state]);

  console.log('\n=== tracking requests fired (dedup by kind+id) ===');
  const seen = new Set();
  const uniq = tags.filter(t => { const k = t.kind + t.id; if (seen.has(k)) return false; seen.add(k); return true; });
  console.table(uniq);

  const counts = {};
  tags.forEach(t => { const k = `${t.kind} ${t.id}`; counts[k] = (counts[k] || 0) + 1; });
  console.log('\n=== fire counts (a duplicate implementation shows the same ID twice) ===');
  console.table(Object.entries(counts).map(([k, v]) => ({ tag: k, times: v })));

  await b.close();
})();

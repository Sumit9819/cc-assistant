// Definitive usage map: scan EVERY public URL for each costly plugin's markup.
// class= only, so a plugin's own JS/CSS payload cannot create false hits.
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
const get = async (u) => { try { const r = await fetch(u, { headers: { 'User-Agent': UA } }); return r.ok ? r.text() : null; } catch { return null; } };
const locs = (x) => [...x.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1].trim());

const MARKERS = {
  'DiviFlash (318 KB)':        /class="[^"]*\b(difl[_-]|df_)/,
  'Back In Stock (386 KB)':    /class="[^"]*\bcwginstock/,
  'Divi Plus (176 KB)':        /class="[^"]*\bdipl[_-]/,
  'Divi Machine (123 KB)':     /class="[^"]*\b(dmach|et_pb_de_mach)/,
  'BodyCommerce (116 KB)':     /class="[^"]*\b(bodycommerce|bodyshop|bc_menu|bc-)/,
  'CURCY currency (63 KB)':    /class="[^"]*\bwmc-/,
  'FiboSearch (67 KB)':        /class="[^"]*\bdgwt-wcas/,
  'Wishlist (25 KB)':          /class="[^"]*\bwoosw/,
  'Customer Reviews':          /class="[^"]*\b(cr-[a-z]|ivole)/,
  'Free Gifts (69 KB)':        /class="[^"]*\b(fgf-|free-gift)/,
  'Product labels: Acowebs':   /class="[^"]*\b(awcpl|aco-pl|acowebs)/,
  'Product labels: AWL':       /class="[^"]*\bawl-/,
};

const kind = (u) => u.includes('/product/') ? 'product'
  : u.includes('/product-category/') ? 'prod-cat'
  : /\/(cart|checkout|my-account)\//.test(u) ? 'shop-fn'
  : u.replace('https://sids-ponds.com/', '').split('/').length <= 2 ? 'page/post' : 'other';

(async () => {
  const idx = await get('https://sids-ponds.com/sitemap_index.xml');
  let urls = [];
  for (const m of locs(idx)) {
    if (/sitemap_index/.test(m)) continue;
    const xml = await get(m); if (xml) urls.push(...locs(xml));
  }
  urls = [...new Set(urls)];
  console.log(`scanning ${urls.length} URLs\n`);

  const hits = {}; Object.keys(MARKERS).forEach(k => hits[k] = []);
  let done = 0, failed = 0;
  const q = [...urls];
  await Promise.all(Array.from({ length: 10 }, async () => {
    while (q.length) {
      const u = q.shift();
      const html = await get(u);
      done++;
      if (done % 150 === 0) process.stdout.write(`  ...${done}/${urls.length}\n`);
      if (!html) { failed++; continue; }
      for (const [k, re] of Object.entries(MARKERS)) if (re.test(html)) hits[k].push(u);
    }
  }));

  console.log(`\nfetched ${done}, failed ${failed}\n`);
  const rows = Object.entries(MARKERS).map(([k]) => {
    const list = hits[k];
    const byKind = {};
    list.forEach(u => { const t = kind(u); byKind[t] = (byKind[t] || 0) + 1; });
    return {
      plugin: k,
      pages: list.length,
      pct: Math.round(100 * list.length / done) + '%',
      where: Object.entries(byKind).sort((a, b) => b[1] - a[1]).map(([t, n]) => `${t}:${n}`).join(' ') || '(nowhere)',
    };
  }).sort((a, b) => a.pages - b.pages);
  console.table(rows);

  console.log('\nnon-product pages using DiviFlash:');
  hits['DiviFlash (318 KB)'].filter(u => !u.includes('/product/')).forEach(u => console.log('   ' + u.replace('https://sids-ponds.com', '')));
  console.log('\nproduct pages using DiviFlash: ' + hits['DiviFlash (318 KB)'].filter(u => u.includes('/product/')).length);
})();

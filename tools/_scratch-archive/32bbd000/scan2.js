// Re-scan with the FULL DiviFlash prefix set, matching only inside class="..."
// attributes so a plugin's own JS/CSS payload cannot create false positives
// (that is what produced the bogus "DiviMachine on 37/37 pages" result).
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
const get = async (u) => { try { const r = await fetch(u, { headers: { 'User-Agent': UA } }); return r.ok ? r.text() : null; } catch { return null; } };
const locs = (xml) => [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1].trim());

const MARKERS = {
  // every class prefix DiviFlash emits, per its own stylesheet
  DiviFlash:   /class="[^"]*\b(difl[_-]|df_)/,
  DiviPlus:    /class="[^"]*\bdipl[_-]/,
  DiviMachine: /class="[^"]*\b(dmach|et_pb_de_mach|divi-filter|loop-grid)/,
  WPForms:     /class="[^"]*\bwpforms/,
};

(async () => {
  const idx = await get('https://sids-ponds.com/sitemap_index.xml');
  const maps = locs(idx);
  const pick = async (frag, limit) => {
    const m = maps.find(x => x.includes(frag));
    if (!m) return [];
    const xml = await get(m);
    const u = xml ? locs(xml) : [];
    if (!limit || u.length <= limit) return u;
    const step = Math.floor(u.length / limit);
    return u.filter((_, i) => i % step === 0).slice(0, limit);
  };

  const sets = [
    ['pages',      await pick('page-sitemap')],
    ['posts',      await pick('post-sitemap')],
    ['prod-cats',  await pick('product_cat-sitemap', 12)],
    ['products',   await pick('product-sitemap1', 15)],
  ];

  const hits = {}; Object.keys(MARKERS).forEach(k => hits[k] = []);
  let total = 0, failed = 0;

  for (const [label, urls] of sets) {
    const q = [...urls];
    total += q.length;
    const worker = async () => {
      while (q.length) {
        const u = q.shift();
        const html = await get(u);
        if (!html) { failed++; continue; }
        for (const [k, re] of Object.entries(MARKERS)) if (re.test(html)) hits[k].push([label, u]);
      }
    };
    await Promise.all(Array.from({ length: 8 }, worker));
    console.log(`scanned ${label}: ${urls.length}`);
  }

  console.log(`\ntotal fetched ${total}, failed ${failed}\n`);
  for (const [k, list] of Object.entries(hits)) {
    console.log(`\n===== ${k}: ${list.length} / ${total} =====`);
    const byType = {};
    list.forEach(([t]) => byType[t] = (byType[t] || 0) + 1);
    console.log('  by type:', JSON.stringify(byType));
    list.slice(0, 20).forEach(([t, u]) => console.log(`   [${t}] ${u.replace('https://sids-ponds.com', '')}`));
    if (list.length > 20) console.log(`   ... and ${list.length - 20} more`);
    if (!list.length) console.log('   (none)');
  }
})();

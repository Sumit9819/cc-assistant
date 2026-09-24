// Fetch every page/post URL from the sitemaps and check the server HTML for
// add-on module markers. Plain HTTP, no browser: the classes are server-rendered.
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

const get = async (url) => {
  const r = await fetch(url, { headers: { 'User-Agent': UA } });
  if (!r.ok) return null;
  return r.text();
};

const urlsFrom = (xml) => [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1].trim());

(async () => {
  const index = await get('https://sids-ponds.com/sitemap_index.xml');
  if (!index) { console.log('no sitemap index'); return; }
  const maps = urlsFrom(index);
  console.log('sitemaps:', maps.map(m => m.split('/').pop()).join(', '));

  // pages + posts only: products are the bulk and share one template
  const wanted = maps.filter(m => /(page|post)-sitemap/.test(m));
  let urls = [];
  for (const m of wanted) {
    const xml = await get(m);
    if (xml) urls.push(...urlsFrom(xml));
  }
  urls = [...new Set(urls)];
  console.log(`scanning ${urls.length} pages/posts\n`);

  const MARKERS = {
    DiviFlash: /difl[_-]/,
    DiviPlus: /dipl[_-]/,
    DiviMachine: /dmach|machine_/,
  };
  const hits = { DiviFlash: [], DiviPlus: [], DiviMachine: [] };
  let done = 0, failed = 0;

  const CONC = 6;
  const queue = [...urls];
  const worker = async () => {
    while (queue.length) {
      const u = queue.shift();
      const html = await get(u).catch(() => null);
      done++;
      if (!html) { failed++; continue; }
      for (const [k, re] of Object.entries(MARKERS)) if (re.test(html)) hits[k].push(u);
    }
  };
  await Promise.all(Array.from({ length: CONC }, worker));

  console.log(`fetched ${done}, failed ${failed}\n`);
  for (const [k, list] of Object.entries(hits)) {
    console.log(`\n===== ${k}: ${list.length} of ${urls.length} pages =====`);
    list.slice(0, 30).forEach(u => console.log('   ' + u.replace('https://sids-ponds.com', '')));
    if (list.length > 30) console.log(`   ... and ${list.length - 30} more`);
    if (!list.length) console.log('   (none)');
  }
})();

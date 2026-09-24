// Exhaustive check before disabling Videopack. It provides its own shortcodes
// AND takes over WordPress's native video player, so look for both.
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
const get = async (u) => { try { const r = await fetch(u, { headers: { 'User-Agent': UA } }); return r.ok ? r.text() : null; } catch { return null; } };
const locs = (x) => [...x.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1].trim());

const MARKERS = {
  'Videopack player (video-js)': /class="[^"]*\bvideo-js/i,
  'Videopack markup (kgvid)':    /\bkgvid[_-]/i,
  'HTML5 <video> element':       /<video[\s>]/i,
  'WP video block':              /wp-block-video/i,
  'self-hosted video file':      /\.(mp4|webm|m4v|mov|ogv)(["'?)]|$)/i,
  'YouTube / Vimeo embed':       /(youtube\.com\/embed|youtu\.be\/|player\.vimeo\.com)/i,
};

(async () => {
  const idx = await get('https://sids-ponds.com/sitemap_index.xml');
  const maps = locs(idx);
  let urls = [];
  for (const m of maps) {
    if (/sitemap_index/.test(m)) continue;
    const xml = await get(m);
    if (xml) urls.push(...locs(xml));
  }
  urls = [...new Set(urls)];
  console.log(`sitemaps: ${maps.length}`);
  console.log(`scanning EVERY url: ${urls.length}\n`);

  const hits = {}; Object.keys(MARKERS).forEach(k => hits[k] = []);
  let done = 0, failed = 0;
  const q = [...urls];
  const worker = async () => {
    while (q.length) {
      const u = q.shift();
      const html = await get(u);
      done++;
      if (done % 100 === 0) process.stdout.write(`  ...${done}/${urls.length}\n`);
      if (!html) { failed++; continue; }
      for (const [k, re] of Object.entries(MARKERS)) if (re.test(html)) hits[k].push(u);
    }
  };
  await Promise.all(Array.from({ length: 10 }, worker));

  console.log(`\nfetched ${done}, failed ${failed}\n`);
  for (const [k, list] of Object.entries(hits)) {
    const flag = list.length ? '  <-- FOUND' : '';
    console.log(`${k.padEnd(30)} ${String(list.length).padStart(4)} pages${flag}`);
    list.slice(0, 12).forEach(u => console.log(`      ${u.replace('https://sids-ponds.com', '')}`));
    if (list.length > 12) console.log(`      ... and ${list.length - 12} more`);
  }

  const anyVideo = hits['Videopack player (video-js)'].length + hits['Videopack markup (kgvid)'].length
                 + hits['HTML5 <video> element'].length + hits['WP video block'].length;
  console.log(`\nVERDICT: ${anyVideo === 0 ? 'no video player markup on ANY public URL' : anyVideo + ' pages carry video markup — DO NOT disable blindly'}`);
})();

// Coverage says "unused"; that is not proof. A component whose MARKUP is absent
// cannot need its CSS. Check presence of each candidate's markup per page type.
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

const PAGES = [
  ['homepage', 'https://sids-ponds.com/'],
  ['category', 'https://sids-ponds.com/product-category/all-products/aggregates-soil-mulch/'],
  ['product',  'https://sids-ponds.com/product/all-products/artificial-turf-and-sod/sod/sod/'],
  ['blogpost', 'https://sids-ponds.com/landscaping-with-river-rocks/'],
  ['cart',     'https://sids-ponds.com/cart/'],
];

// class= matches only, so a plugin's own JS/CSS payload can't create a false hit
const MARKERS = {
  'Customer Reviews (~108 KB)': /class="[^"]*\b(cr-[a-z]|ivole)/,
  'Videopack / video.js (~39 KB)': /class="[^"]*\b(video-js|kgvid)/,
  'Brave Popup (~54 KB)': /class="[^"]*\bbrave_popup/,
  'Back In Stock (~37 KB)': /class="[^"]*\bcwginstock/,
  'FiboSearch (~36 KB)': /class="[^"]*\bdgwt-wcas/,
  'WPC Wishlist (~19 KB)': /class="[^"]*\bwoosw/,
  'CURCY + flags (~28 KB)': /class="[^"]*\b(wmc-|vi-flag)/,
  'lightGallery (~15 KB)': /class="[^"]*\blg-(outer|container|css3)/,
  'DataTables (~14 KB)': /class="[^"]*\bdataTable/,
  'Select2 (~4 KB)': /class="[^"]*\bselect2/,
  'WPC Buy Now (~4 KB)': /class="[^"]*\bwpcbn/,
};

(async () => {
  const results = {};
  for (const [label, url] of PAGES) {
    const html = await (await fetch(url, { headers: { 'User-Agent': UA } })).text();
    results[label] = {};
    for (const [name, re] of Object.entries(MARKERS)) {
      const hits = (html.match(new RegExp(re.source, 'g')) || []).length;
      results[label][name] = hits;
    }
  }

  const names = Object.keys(MARKERS);
  const rows = names.map(n => {
    const row = { component: n };
    let total = 0;
    for (const [label] of PAGES) { row[label] = results[label][n]; total += results[label][n]; }
    row.verdict = total === 0 ? 'ABSENT EVERYWHERE' : 'present somewhere';
    return row;
  });
  console.table(rows);

  console.log('\nSafe to scope (markup absent on that page type):');
  for (const [label] of PAGES) {
    const safe = names.filter(n => results[label][n] === 0);
    console.log(`  ${label.padEnd(9)}: ${safe.length}/${names.length} components absent`);
  }
})();

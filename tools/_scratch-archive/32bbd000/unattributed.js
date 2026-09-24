// What is actually inside the ~330 KB "(unattributed)" share of the bundle?
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

const KNOWN = /dgwt-wcas|wcas-|\bfc-|fluid-checkout|has-checkout|wmc-|woosw-|wpcbn-|cwginstock|awl-|acowebs|cmplz|bravepop|et_bloom|rank-?math|pdfemb|kgvid|videopack|ivole|cr-review|wcpdf|wcpay|woopay|wc-payment|gla-|wdr-|awdr-|fgf-|wpf-|themify|clickship|klaviyo|wp-statistics|clearsale|swiper|select2|dashicons|\bpys|dipl[_-]|difl[_-]|\bdf_|dmach|de_mach|bodycommerce|bodyshop|et_pb|et-db|et_boc|\bet-|woocommerce|\bwc-|\bwoo-/;

function parseBlocks(css, from, to, out) {
  let i = from, selStart = from;
  while (i < to) {
    if (css[i] === '{') {
      const sel = css.slice(selStart, i).trim();
      let d = 1, j = i + 1;
      while (j < to && d > 0) { if (css[j] === '{') d++; else if (css[j] === '}') d--; j++; }
      if (/^@(media|supports|container|layer|scope)/i.test(sel)) parseBlocks(css, i + 1, j - 1, out);
      else out.push({ sel, bytes: j - selStart });
      i = j; selStart = j; continue;
    }
    if (css[i] === '}') { i++; selStart = i; continue; }
    i++;
  }
  return out;
}

(async () => {
  const html = await (await fetch('https://sids-ponds.com/', { headers: { 'User-Agent': UA } })).text();
  const m = html.match(/https:\/\/sids-ponds\.com\/wp-content\/uploads\/siteground-optimizer-assets\/siteground-optimizer-combined-css-[a-f0-9]+\.css/);
  if (!m) { console.log('bundle URL not found'); return; }
  const css = await (await fetch(m[0], { headers: { 'User-Agent': UA } })).text();
  console.log(`bundle: ${(css.length / 1024).toFixed(0)} KB\n`);

  const blocks = parseBlocks(css, 0, css.length, []);
  const un = blocks.filter(b => !KNOWN.test(b.sel));

  // group by the first meaningful token of the selector
  const groups = new Map();
  for (const b of un) {
    const first = (b.sel.split(',')[0] || '').trim();
    let key = first.match(/^[.#]?[A-Za-z][\w-]*/);
    key = key ? key[0] : first.slice(0, 24);
    const g = groups.get(key) || { bytes: 0, n: 0, sample: first };
    g.bytes += b.bytes; g.n++;
    groups.set(key, g);
  }

  const rows = [...groups.entries()].sort((a, b) => b[1].bytes - a[1].bytes).slice(0, 30)
    .map(([k, v]) => ({ token: k.slice(0, 28), KB: +(v.bytes / 1024).toFixed(1), rules: v.n, example: v.sample.slice(0, 46) }));

  console.log(`unattributed: ${(un.reduce((a, b) => a + b.bytes, 0) / 1024).toFixed(0)} KB across ${un.length} rules`);
  console.table(rows);
})();

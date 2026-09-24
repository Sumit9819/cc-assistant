/**
 * Full text-contrast sweep. EVERY visible text node on a page, not one colour pair.
 *
 * Written after a targeted green-link check passed a page that had black text on a
 * black background sitting right above the H1. A check scoped to one colour is not
 * a contrast pass.
 *
 * usage: node contrast-sweep.mjs <url> [url...]
 *        node contrast-sweep.mjs --file urls.txt
 */
import fs from 'node:fs';
import { createRequire } from 'node:module';
const req = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json');
const { chromium } = req('playwright');

let args = process.argv.slice(2);
let urls = args[0] === '--file'
  ? fs.readFileSync(args[1], 'utf8').split(/\r?\n/).map(s => s.trim()).filter(Boolean)
  : args;
if (!urls.length) { console.log('no urls'); process.exit(1); }

const AUDIT = () => {
  const f = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
  const lum = ([r, g, b]) => 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  const parse = (s) => (s.match(/[\d.]+/g) || []).map(Number);
  const over = (fg, bg) => {           // composite a translucent colour over its backdrop
    const a = fg.length > 3 ? fg[3] : 1;
    return [0, 1, 2].map(i => Math.round(fg[i] * a + bg[i] * (1 - a)));
  };
  const res = [];
  for (const el of document.querySelectorAll('body *')) {
    const own = [...el.childNodes].filter(n => n.nodeType === 3)
      .map(n => n.nodeValue.replace(/\s+/g, ' ').trim()).join(' ').trim();
    if (!own) continue;
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden' || +cs.opacity === 0) continue;
    const rect = el.getBoundingClientRect();
    if (rect.width < 2 || rect.height < 2) continue;
    // effective backdrop: first opaque ancestor background, compositing translucent ones
    let n = el, stack = [], bg = null, img = null;
    while (n && n !== document.documentElement) {
      const s = getComputedStyle(n);
      if (s.backgroundImage && s.backgroundImage !== 'none' && !img) img = s.backgroundImage.slice(0, 60);
      const p = parse(s.backgroundColor);
      if (p.length >= 3) {
        const a = p.length > 3 ? p[3] : 1;
        if (a >= 0.999) { bg = [p[0], p[1], p[2]]; break; }
        if (a > 0) stack.push([p[0], p[1], p[2], a]);
      }
      n = n.parentElement;
    }
    if (!bg) bg = [255, 255, 255];
    for (let i = stack.length - 1; i >= 0; i--) bg = over(stack[i], bg);
    const fgRaw = parse(cs.color);
    const fg = over(fgRaw, bg);
    const l1 = lum(fg), l2 = lum(bg);
    const cr = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    const px = parseFloat(cs.fontSize);
    const bold = parseInt(cs.fontWeight, 10) >= 700;
    const large = px >= 24 || (bold && px >= 18.66);
    const need = large ? 3.0 : 4.5;
    if (cr >= need) continue;
    res.push({
      cr: +cr.toFixed(2), need, text: own.slice(0, 58),
      tag: el.tagName.toLowerCase(),
      wid: (el.closest('[data-id]') && el.closest('[data-id]').getAttribute('data-id')) || '?',
      color: `rgb(${fg.join(',')})`, bg: `rgb(${bg.join(',')})`,
      fs: cs.fontSize, fw: cs.fontWeight, bgImage: img,
    });
  }
  return res;
};

const b = await chromium.launch({ headless: true });
const ctx = await b.newContext({ viewport: { width: 1400, height: 950 } });
const page = await ctx.newPage();
let grand = 0;
const summary = [];
for (const url of urls) {
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
    for (let i = 0; i < 10; i++) {
      if (!/well-known|sgcaptcha/.test(page.url())) break;
      await page.waitForTimeout(1500);
    }
    await page.evaluate(() => document.querySelectorAll(
      '.elementskit-card .elementskit-btn, .elementskit-card-header a, .ekit-accordion--toggler')
      .forEach(el => { try { el.click(); } catch {} }));
    await page.waitForTimeout(900);
    const rows = await page.evaluate(AUDIT);
    grand += rows.length;
    summary.push([url, rows.length]);
    console.log(`\n=== ${url.replace('https://mammothmachinery.ca', '') || '/'}   ${rows.length} failing`);
    for (const r of rows.sort((a, z) => a.cr - z.cr)) {
      const flag = r.cr <= 1.3 ? '  <<< INVISIBLE' : '';
      console.log(`  ${String(r.cr).padStart(5)}:1 (needs ${r.need})  <${r.tag}> wid=${r.wid}  ${r.fs}/${r.fw}${flag}`);
      console.log(`        ${r.color} on ${r.bg}${r.bgImage ? '  [bg-image present: ' + r.bgImage + ']' : ''}`);
      console.log(`        ${JSON.stringify(r.text)}`);
    }
  } catch (e) {
    console.log(`\n=== ${url}  ERROR ${String(e).split('\n')[0].slice(0, 80)}`);
  }
}
console.log(`\n================ TOTAL failing text elements: ${grand}`);
for (const [u, n] of summary.sort((a, z) => z[1] - a[1]))
  if (n) console.log(`   ${String(n).padStart(3)}  ${u.replace('https://mammothmachinery.ca', '') || '/'}`);
await b.close();

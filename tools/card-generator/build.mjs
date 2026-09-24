/**
 * Build in-body infographics for irvingwellnessclinic.com from spec.json.
 *
 * The point of driving this from a spec rather than laying each card out by
 * hand: every defect found in the Canva set was a manual-assembly slip
 * (TESTOoSTERONE, "Slow Tiration", "Energy boost" twice, six icons with no
 * labels). Here the labels are a list. A duplicate is a visible repeat in the
 * data and a typo is one edit.
 *
 * Icons are real Lucide SVGs fetched to icons/, not hand-drawn paths - drawing
 * them freehand is what turned the first sample's bed icon into a grey box.
 *
 * usage: node build.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(HERE, 'out');

function loadPlaywright() {
  const candidates = [
    'C:/Users/sumit/.cc-assistant/wcag/package.json',
    'D:/faceless-studio/motion/package.json',
  ];
  const tried = [];
  for (const base of candidates) {
    if (!fs.existsSync(base)) { tried.push(`${base} (missing)`); continue; }
    try { return createRequire(base)('playwright'); }
    catch (e) { tried.push(`${base} (${e.code || e.message})`); }
  }
  throw new Error('No Playwright found:\n  ' + tried.join('\n  '));
}

/** Strip Lucide's wrapper so the paths inherit our own sizing and stroke. */
function iconBody(name) {
  const file = path.join(HERE, 'icons', `${name}.svg`);
  if (!fs.existsSync(file)) throw new Error(`Missing icon: ${name}.svg`);
  const svg = fs.readFileSync(file, 'utf8');
  const inner = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
  if (!inner.trim()) throw new Error(`Empty icon: ${name}.svg`);
  return inner.trim();
}

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function html(card) {
  const n = card.items.length;
  // Four-up needs smaller discs and tighter gaps to stay inside 1200px.
  const disc = n >= 4 ? 152 : 178;
  const gap = n >= 4 ? 46 : 92;
  const icon = n >= 4 ? 74 : 88;
  const hasNotes = card.items.some((i) => i.note);
  const labelSize = n >= 4 ? 24 : 27;
  const lineH = Math.round(labelSize * 1.2);

  // Reserve two lines for EVERY label as soon as one of them is long enough to
  // wrap. Without this the wrapped column pushes its rule and note down and the
  // row stops lining up - visible on "Not For Everyone" and "Peaks & Troughs".
  // Reserving space beats shortening the copy: the words stay accurate and the
  // layout survives whatever the next card's labels turn out to be.
  const colWidth = disc + 46;
  const wraps = card.items.some((i) => i.label.length * labelSize * 0.56 > colWidth);
  const labelBox = wraps ? lineH * 2 : lineH;

  const items = card.items.map((it) => `
    <div class="item">
      <div class="disc">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="#003017"
             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${iconBody(it.icon)}</svg>
      </div>
      <div class="label">${esc(it.label)}</div>
      <div class="rule"></div>
      ${it.note ? `<div class="note">${esc(it.note)}</div>` : ''}
    </div>`).join('');

  return `<meta charset="utf-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap">
<style>
/* Brand hexes as posted in #designfor-sumit on 2026-06-03. */
:root{--green:#003017;--sage:#96A681;--yellow:#FFD900;--ground:#f7f8f4}
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:1200px;height:628px}
body{background:var(--ground);font-family:Poppins,system-ui,sans-serif;position:relative;overflow:hidden}
.blob{position:absolute;border-radius:50%;opacity:.5}
.b1{width:520px;height:420px;background:#e4ebdc;top:-170px;right:-100px}
.b2{width:380px;height:320px;background:#eef2e8;bottom:-140px;left:-100px}
.b3{width:230px;height:200px;background:#f2ecd6;bottom:-80px;right:200px;opacity:.5}
h1{position:absolute;top:${hasNotes ? 62 : 84}px;left:0;width:100%;text-align:center;
   font-weight:700;font-size:50px;letter-spacing:-.5px;color:var(--green)}
h1 em{font-style:normal;color:var(--yellow)}
.row{position:absolute;top:${hasNotes ? 190 : 226}px;left:0;width:100%;
     display:flex;justify-content:center;gap:${gap}px}
.item{width:${disc + 46}px;display:flex;flex-direction:column;align-items:center}
.disc{width:${disc}px;height:${disc}px;border-radius:50%;border:2px solid var(--sage);
      background:rgba(255,255,255,.66);display:flex;align-items:center;justify-content:center}
.ico{width:${icon}px;height:${icon}px}
.label{margin-top:24px;font-weight:600;font-size:${labelSize}px;color:var(--green);
       text-align:center;line-height:1.2;
       min-height:${labelBox}px;display:flex;align-items:center;justify-content:center}
.rule{width:44px;height:3px;background:var(--yellow);margin-top:11px;border-radius:2px}
.note{margin-top:13px;font-weight:500;font-size:16px;line-height:1.42;
      color:#4a5a48;text-align:center;max-width:${disc + 34}px}
</style>
<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<h1>${esc(card.title[0])}<em>${esc(card.title[1])}</em>${esc(card.title[2])}</h1>
<div class="row">${items}</div>`;
}

const spec = JSON.parse(fs.readFileSync(path.join(HERE, 'spec.json'), 'utf8'));
fs.mkdirSync(OUT, { recursive: true });

// Guard the exact failure mode this pipeline exists to prevent.
for (const card of spec.cards) {
  const labels = card.items.map((i) => i.label.toLowerCase());
  const dupe = labels.find((l, i) => labels.indexOf(l) !== i);
  if (dupe) throw new Error(`${card.file}: duplicate label "${dupe}"`);
}

const { chromium } = loadPlaywright();
const browser = await chromium.launch();

for (const card of spec.cards) {
  const htmlPath = path.join(OUT, `${card.file}.html`);
  fs.writeFileSync(htmlPath, html(card));

  const page = await browser.newPage({
    viewport: { width: 1200, height: 628 },
    deviceScaleFactor: 2, // render 2x, downscale later, keeps type and hairlines crisp
  });
  await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(350);
  const png = path.join(OUT, `${card.file}.png`);
  await page.screenshot({ path: png, clip: { x: 0, y: 0, width: 1200, height: 628 } });
  await page.close();
  console.log(`  ${card.file.padEnd(20)} ${(fs.statSync(png).size / 1024).toFixed(0)} KB`);
}

await browser.close();
console.log(`\n${spec.cards.length} cards -> ${OUT}`);

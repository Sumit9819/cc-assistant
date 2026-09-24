/**
 * Frame options, rendered on their own for review.
 *
 *   node frames.mjs [photo] [--out=out/_frames]
 *
 * The first circular frame shipped inside a finished card, which made it hard to
 * judge: a plain disc with a red ring reads as an avatar bubble, and that is not
 * obvious until the headline is taken away. So this renders each frame ALONE, on
 * both grounds, with nothing else in the tile.
 *
 * Every option stays inside the ER palette - #DA1212, #041562, #11468F, #FFFFFF,
 * #F4F4F4 - because those skills ban every other hue, and none is a wave, slash
 * or zigzag divider, which they also ban. Where an option wants a shape, it
 * borrows one the brand already owns: the cross and the ECG trace from its mark.
 *
 * Each option's CSS is a function of the tile's ground colour, because two of
 * them need a gap in the ring, and a gap is drawn in the ground colour.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';
import { FRAMES, RED, SCOPED } from './frame-styles.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));

function loadPlaywright() {
  for (const base of ['C:/Users/sumit/.cc-assistant/wcag/package.json',
                      'D:/faceless-studio/motion/package.json']) {
    if (fs.existsSync(base)) {
      try { return createRequire(base)('playwright'); } catch { /* try the next */ }
    }
  }
  throw new Error('No Playwright found');
}

const args = process.argv.slice(2);
const photoArg = args.find((a) => !a.startsWith('--')) || 'src/gen/er-copperhead.png';
const outArg = (args.find((a) => a.startsWith('--out=')) || '--out=out/_frames').split('=')[1];

const photo = path.resolve(HERE, photoArg);
if (!fs.existsSync(photo)) throw new Error(`No photo at ${photo}`);
const uri = `data:image/png;base64,${fs.readFileSync(photo).toString('base64')}`;

const D = 300;
const NAVY = '#041562';
const LOGO = (() => {
  const p = path.join(HERE, '..', 'brand', 'erofwhiterock-logo.png');
  return fs.existsSync(p) ? `data:image/png;base64,${fs.readFileSync(p).toString('base64')}` : null;
})();
const LOGO_WHITE = (() => {
  const p = path.join(HERE, '..', 'brand', 'erofwhiterock-logo-white.png');
  return fs.existsSync(p) ? `data:image/png;base64,${fs.readFileSync(p).toString('base64')}` : null;
})();

const OPTIONS = Object.entries(FRAMES).map(([key, f]) => ({ key, ...f }));

function tile(o, dark) {
  const ground = dark ? NAVY : '#FFFFFF';
  const halo = dark ? 'rgba(255,255,255,.05)' : 'rgba(17,70,143,.09)';
  const id = `${o.key}-${dark ? 'd' : 'l'}`;
  const ctx = { size: D, ground, dark, id, photo: uri, logo: dark ? LOGO_WHITE : LOGO };
  const scope = (css) => css.replace(SCOPED, `#${id} .$1`);
  return `
  <div class="cell" style="background:${ground}">
    <style>
      ${o.css ? `#${id} .f{${o.css(ctx)}}` : ''}
      ${o.wrap ? `#${id} .wrap{${o.wrap(ctx)}}` : ''}
      ${o.extraCss ? scope(o.extraCss(ctx)) : ''}
    </style>
    <div class="stage" id="${id}">
      <i class="halo" style="background:${halo}"></i>
      ${o.back ? o.back(ctx) : ''}
      ${o.svg
        ? o.svg(ctx)
        : `<div class="wrap"><div class="f"><img src="${uri}" alt=""></div></div>`}
      ${o.over ? o.over(ctx) : ''}
    </div>
  </div>`;
}

const page = (dark) => `<!doctype html><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{background:#e8e8e8;font-family:Montserrat,system-ui,sans-serif;padding:26px}
  h1{font-size:19px;color:#111;margin-bottom:4px}
  p.lead{font-size:13px;color:#444;margin-bottom:20px}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
  .item{background:#fff;border-radius:6px;overflow:hidden}
  .cell{height:${D + 100}px;display:flex;align-items:center;justify-content:center}
  .stage{position:relative;width:${D}px;height:${D}px}
  .halo{position:absolute;left:-34px;top:-34px;width:${D + 68}px;height:${D + 68}px;border-radius:50%}
  .wrap{position:relative;width:100%;height:100%}
  .f{position:relative;width:100%;height:100%}
  .f img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}
  .label{padding:10px 12px 12px;min-height:64px}
  .label b{display:block;font-size:13px;color:${NAVY}}
  .label span{display:block;font-size:11.5px;color:#666;margin-top:3px;line-height:1.35}
</style>
<h1>Frame options &mdash; ${dark ? 'navy ground' : 'white ground'}</h1>
<p class="lead">Same photograph in every one. ER palette only: #DA1212, #041562, #11468F, #FFFFFF, #F4F4F4.</p>
<div class="grid">
  ${OPTIONS.map((o) => `<div class="item">${tile(o, dark)}
     <div class="label"><b>${o.name}</b><span>${o.note}</span></div></div>`).join('')}
</div>`;

const { chromium } = loadPlaywright();
const browser = await chromium.launch();
fs.mkdirSync(path.join(HERE, 'out'), { recursive: true });

for (const dark of [true, false]) {
  const html = path.join(HERE, 'out', `_frames-${dark ? 'navy' : 'light'}.html`);
  fs.writeFileSync(html, page(dark));
  const p = await browser.newPage({ viewport: { width: 1560, height: 1200 }, deviceScaleFactor: 2 });
  await p.goto(pathToFileURL(html).href, { waitUntil: 'load' });
  await p.evaluate(async () => { await document.fonts.ready; });
  await p.waitForTimeout(250);
  const dest = path.join(HERE, `${outArg}-${dark ? 'navy' : 'light'}.png`);
  await p.screenshot({ path: dest, fullPage: true });
  await p.close();
  console.log(`  + ${dest}`);
}
await browser.close();
console.log(`\n${OPTIONS.length} options, ${path.basename(photo)} in every one.`);

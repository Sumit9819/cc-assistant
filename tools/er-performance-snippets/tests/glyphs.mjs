import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const orig = fs.readFileSync('elementskit.woff').toString('base64');
const sub = fs.readFileSync('elementskit-irving-subset.b64', 'utf8').trim();
const cps = JSON.parse(fs.readFileSync('irving-codepoints.json', 'utf8')).cp;
const cells = Object.entries(cps).map(([cls, hex]) => `<div class="c"><span>&#x${hex};</span><small>${cls}</small></div>`).join('');
const page = (fam, data, fmt) => `<!doctype html><html><head><style>@font-face{font-family:${fam};src:url(data:font/${fmt};base64,${data}) format("${fmt}");font-display:block}
body{margin:0;background:#fff}.g{display:grid;grid-template-columns:repeat(5,120px);gap:6px;padding:10px}.c{height:110px;border:1px solid #eee;text-align:center}
span{font-family:${fam};font-size:64px;line-height:80px;color:#111}small{display:block;font:10px monospace;color:#999}</style></head><body><div class="g" id="g">${cells}</div></body></html>`;
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 660, height: 260 } });
for (const [name, html] of [['original', page('ekorig', orig, 'woff')], ['subset', page('eksub', sub, 'woff2')]]) {
  await p.setContent(html); await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(300);
  const loaded = await p.evaluate(() => [...document.fonts].map(f => f.family + ':' + f.status));
  await p.locator('#g').screenshot({ path: `glyphs-${name}.png` });
  console.log(name, 'font status', loaded);
}
await b.close();

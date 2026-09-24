// Rasterise Healthicons (CC0, github.com/resolvetosavelives/healthicons) into square
// PNGs for the framed featured layouts. Usage:
//   node healthicon.mjs <icon-name> <out.png> [fg=#041562] [bg=#FFFFFF] [scale=0.62]
// The icon is centred at `scale` of the canvas so it sits well inside the disc's
// inscribed circle (70.7% is the geometric ceiling; 0.62 leaves a margin).
import { createRequire } from 'node:module';
import fs from 'node:fs';
const [,, name, out, fg = '#041562', bg = '#FFFFFF', scaleArg = '0.62'] = process.argv;
if (!name || !out) { console.error('usage: node healthicon.mjs <icon> <out.png> [fg] [bg] [scale]'); process.exit(1); }
const set = JSON.parse(fs.readFileSync(new URL('./src/icon/healthicons.json', import.meta.url), 'utf8'));
const icon = set.icons[name];
if (!icon) { console.error(`no icon "${name}"`); process.exit(1); }
const vb = `0 0 ${icon.width || set.width || 48} ${icon.height || set.height || 48}`;
const size = 1000, s = Math.round(size * Number(scaleArg));
const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${vb}" width="${s}" height="${s}" style="color:${fg}">${icon.body.replace(/currentColor/g, fg)}</svg>`;
const html = `<html><body style="margin:0;width:${size}px;height:${size}px;background:${bg};display:flex;align-items:center;justify-content:center">${svg}</body></html>`;
let pw;
for (const base of [process.env.USERPROFILE + '/.cc-assistant/wcag/package.json', 'D:/faceless-studio/motion/package.json']) {
  try { pw = createRequire(base)('playwright'); break; } catch {}
}
const browser = await pw.chromium.launch();
const page = await browser.newPage({ viewport: { width: size, height: size } });
await page.setContent(html);
await page.screenshot({ path: out });
await browser.close();
console.log('wrote', out);

import { chromium } from 'playwright';
const OUT = process.argv[2];
const SRC = 'file:///C:/Users/sumit/AppData/Local/Temp/claude/c--Users-sumit-Local-Sites-plugintesting-app-public/32bbd000-c160-41d2-824f-454b206fe3ed/scratchpad/sp-quarterly.html';

const b = await chromium.launch();
// A4 at 96dpi is 794 x 1123 css px
const ctx = await b.newContext({ viewport: { width: 794, height: 1123 }, deviceScaleFactor: 2 });
const p = await ctx.newPage();
await p.goto(SRC, { waitUntil: 'networkidle' });
await p.waitForTimeout(900);

const h = await p.evaluate(() => document.body.scrollHeight);
const pages = Math.ceil(h / 1123);
console.log('rendered height ' + h + ' px, about ' + pages + ' A4 pages');

for (let i = 0; i < Math.min(pages, 5); i++) {
  const y = i * 1123;
  const height = Math.min(1123, h - y);
  if (height < 40) break;
  await p.screenshot({ path: OUT + '/report-p' + (i + 1) + '.png', fullPage: true, clip: { x: 0, y: y, width: 794, height: height } });
}
console.log('shots written to ' + OUT);
await b.close();

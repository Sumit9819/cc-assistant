import { chromium } from 'playwright';

const [, , src, out] = process.argv;
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1000, height: 460 }, deviceScaleFactor: 2 });
await p.goto('file:///' + src, { waitUntil: 'networkidle' });
// let the webfont in the "current" column actually paint before capturing
await p.waitForTimeout(3000);
await p.screenshot({ path: out, fullPage: true });
await b.close();
console.log('shot saved ->', out);

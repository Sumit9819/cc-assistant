import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const URL_ = 'https://erofirving.com/';
const php = fs.readFileSync('D:/cc-assistant/tools/er-performance-snippets/erofirving.php', 'utf8');
const pre = [...php.matchAll(/echo '(<link rel="preload"[^']*)'/g)].map(m => m[1]);
console.log('inserted right after the viewport meta, where wp_head prints them'); pre.forEach(x => console.log('   ', x));
const live = await (await (await b.newPage()).goto(URL_, { waitUntil: 'domcontentloaded', timeout: 120000 })).text();
// print them where the snippet does: early in <head> (wp_head priority 1), plugin preload removed
const html = live.replace(/<link[^>]*id="cc-hero-preload"[^>]*>/, '').replace(/(<meta name="viewport"[^>]*>)/, '$1' + pre.join(''));
for (const [label, opts] of [['phone', devices['Pixel 7']], ['desktop', { viewport: { width: 1440, height: 900 } }], ['tablet 768', { viewport: { width: 768, height: 1024 } }]]) {
  const ctx = await b.newContext(opts); const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p); await cdp.send('Network.enable');
  const seen = [];
  cdp.on('Network.requestWillBeSent', e => { if (/Fast-Expert-Care-scaled|ER-of-Irving-Mobile-Background/.test(e.request.url)) seen.push(`${e.request.url.split('/').pop().slice(0, 40)} <- ${e.initiator.type}${e.initiator.url ? ' ' + e.initiator.url.split('/').pop().slice(0, 30) : ''}`); });
  await p.route(URL_, r => r.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: html }));
  await p.goto(URL_, { waitUntil: 'load', timeout: 120000 }); await p.waitForTimeout(3000);
  console.log(`${label.padEnd(11)} requests:`, seen);
  await ctx.close();
}
await b.close();

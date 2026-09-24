import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch();
const ctx = await b.newContext(); const p = await ctx.newPage();
let urls = JSON.parse(fs.readFileSync('irving-urls.json', 'utf8')); console.log('URLs:', urls.length);
// fetch raw HTML (no JS needed for class names) with a concurrency of 6
const classes = new Map(); let done = 0, failed = [];
async function grab(u) {
  try {
    const r = await ctx.request.get(u, { timeout: 90000 });
    const h = await r.text();
    for (const m of h.matchAll(/class="([^"]*)"/g)) for (const c of m[1].split(/\s+/)) if (/^icon-[a-z0-9-]+$/.test(c)) { if (!classes.has(c)) classes.set(c, u); }
    for (const m of h.matchAll(/icon icon-[a-z0-9-]+/g)) { const c = m[0].split(' ')[1]; if (!classes.has(c)) classes.set(c, u); }
  } catch (e) { failed.push(u); }
  done++;
}
const q = [...urls];
await Promise.all(Array.from({ length: 6 }, async () => { while (q.length) await grab(q.shift()); }));
console.log('fetched', done, 'failed', failed.length);
console.log('ElementsKit icon classes used anywhere on the site:', [...classes.keys()].sort());
fs.writeFileSync('irving-icon-classes.json', JSON.stringify([...classes.keys()].sort()));
await b.close();

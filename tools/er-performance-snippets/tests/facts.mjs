import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch();
for (const site of ['erofirving.com', 'erofwhiterock.com', 'eroflufkin.com']) {
  const p = await b.newPage();
  const h = await (await p.goto(`https://${site}/`, { waitUntil: 'domcontentloaded', timeout: 120000 })).text();
  fs.writeFileSync(`home-${site}.html`, h);
  const pre = [...h.matchAll(/<link[^>]+rel=["']preload["'][^>]*>/g)].map(m => m[0].slice(0, 220));
  const spec = [...h.matchAll(/<script type="speculationrules">([\s\S]*?)<\/script>/g)].map(m => m[1].replace(/\s+/g, ' ').slice(0, 160));
  const ekitIcons = [...new Set([...h.matchAll(/class="[^"]*\b(icon icon-[a-z0-9-]+|ekit-?[a-z-]*icon[a-z-]*)\b/g)].map(m => m[1]))].slice(0, 15);
  const fsInc = (h.match(/data-type=['"]lazy['"]/g) || []).length;
  console.log(`\n== ${site}`);
  console.log('   preload tags:', pre.length ? '\n     ' + pre.join('\n     ') : 'none');
  console.log('   speculation rules:', spec);
  console.log('   ElementsKit icon classes used in HTML:', ekitIcons.length ? ekitIcons : 'none');
  console.log('   scripts held back by Flying Scripts:', fsInc);
  console.log('   ekit icon font referenced in HTML:', /elementskit-icon-pack/.test(h), '| ekit megamenu:', /ekit-megamenu/.test(h));
  if (site === 'eroflufkin.com') {
    const i = h.indexOf('elementor-element-581fd46');
    console.log('   CLS container 581fd46 markup:', h.slice(i - 60, i + 700).replace(/\s+/g, ' '));
    const j = h.indexOf('ER-near-lufkin-tx.jpeg');
    console.log('   lazy image markup:', h.slice(h.lastIndexOf('<img', j), h.indexOf('>', j) + 1).slice(0, 400));
  }
  await p.close();
}
await b.close();

/**
 * WHICH Font Awesome icons does sids-ponds actually render, and from which
 * family? fa-brands-400.woff2 is 110KB and fa-solid-900.woff2 is 80KB; if the
 * brands face serves only a few payment/social glyphs, those can be inlined as
 * SVG and a 110KB webfont dropped from every page load.
 *
 * font-weight 900 => solid face, 400 => brands or regular. We capture the
 * resolved family, weight, the ::before content codepoint, and where the
 * element sits (footer / header / body) so the fix can be targeted.
 */
import { chromium } from 'playwright';

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const browser = await chromium.launch();
const ctx = await browser.newContext({ userAgent: UA });
const page = await ctx.newPage();
await page.goto('https://sids-ponds.com/', { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(1500);

const inv = await page.evaluate(() => {
  const rows = [];
  for (const el of document.querySelectorAll('*')) {
    for (const pseudo of ['::before', '::after']) {
      const cs = getComputedStyle(el, pseudo);
      if (!cs) continue;
      const fam = cs.fontFamily || '';
      if (!/awesome/i.test(fam)) continue;
      let content = cs.content || '';
      const cp = content.replace(/^["']|["']$/g, '');
      const hex = cp ? [...cp].map((c) => c.codePointAt(0).toString(16)).join(' ') : '';
      // where does it live?
      let where = 'body';
      if (el.closest('footer, .et-l--footer, #main-footer')) where = 'footer';
      else if (el.closest('header, .et-l--header, #main-header')) where = 'header';
      rows.push({
        where,
        weight: cs.fontWeight,
        fam: fam.replace(/["']/g, '').slice(0, 34),
        hex,
        cls: (typeof el.className === 'string' ? el.className : '').slice(0, 46),
      });
    }
  }
  return rows;
});

const byFamWeight = {};
for (const r of inv) {
  const k = `${r.fam} | weight ${r.weight} | ${r.where}`;
  byFamWeight[k] = (byFamWeight[k] || 0) + 1;
}
console.log('\n=== resolved FA pseudo-elements, grouped ===');
for (const [k, v] of Object.entries(byFamWeight).sort((a, b) => b[1] - a[1])) {
  console.log(`  ${String(v).padStart(3)}  ${k}`);
}

const glyphs = {};
for (const r of inv) {
  const k = `${r.where} w${r.weight} U+${r.hex}`;
  glyphs[k] = (glyphs[k] || 0) + 1;
}
console.log('\n=== distinct glyphs (where / weight / codepoint) ===');
for (const [k, v] of Object.entries(glyphs).sort((a, b) => b[1] - a[1]).slice(0, 30)) {
  console.log(`  ${String(v).padStart(3)}  ${k}`);
}
console.log('\n  total FA pseudo-elements:', inv.length);

await browser.close();

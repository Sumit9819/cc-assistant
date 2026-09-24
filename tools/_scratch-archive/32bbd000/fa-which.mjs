/**
 * Name the three Font Awesome glyphs that are costing ~209KB a page, and say
 * exactly which elements carry them, so a replacement can be targeted rather
 * than guessed at. Also confirms whether the brands face (110KB) really is
 * serving a single glyph.
 */
import { chromium } from 'playwright';

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const browser = await chromium.launch();
const ctx = await browser.newContext({ userAgent: UA });
const page = await ctx.newPage();
await page.goto('https://sids-ponds.com/', { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(1500);

const out = await page.evaluate(() => {
  const rows = [];
  for (const el of document.querySelectorAll('*')) {
    for (const pseudo of ['::before', '::after']) {
      const cs = getComputedStyle(el, pseudo);
      if (!cs || !/awesome/i.test(cs.fontFamily || '')) continue;
      const cp = (cs.content || '').replace(/^["']|["']$/g, '');
      if (!cp || cp === 'none') continue;
      const hex = [...cp].map((c) => c.codePointAt(0).toString(16)).join('');
      const r = el.getBoundingClientRect();
      rows.push({
        hex,
        weight: cs.fontWeight,
        tag: el.tagName.toLowerCase(),
        cls: (typeof el.className === 'string' ? el.className : '').slice(0, 60),
        parentCls: (el.parentElement && typeof el.parentElement.className === 'string'
          ? el.parentElement.className : '').slice(0, 60),
        text: (el.textContent || '').trim().slice(0, 40),
        visible: r.width > 0 && r.height > 0,
        href: el.closest('a')?.getAttribute('href')?.slice(0, 60) || '',
      });
    }
  }
  return rows;
});

const groups = {};
for (const r of out) {
  const k = `U+${r.hex} w${r.weight}`;
  (groups[k] ||= []).push(r);
}
for (const [k, rows] of Object.entries(groups)) {
  const vis = rows.filter((r) => r.visible).length;
  console.log(`\n### ${k}  — ${rows.length} element(s), ${vis} visible`);
  const seen = new Set();
  for (const r of rows) {
    const sig = r.cls + '|' + r.parentCls;
    if (seen.has(sig)) continue;
    seen.add(sig);
    console.log(`   <${r.tag} class="${r.cls}">`);
    console.log(`     parent : ${r.parentCls}`);
    if (r.text) console.log(`     text   : ${r.text}`);
    if (r.href) console.log(`     link   : ${r.href}`);
    console.log(`     visible: ${r.visible}`);
    if (seen.size >= 4) break;
  }
}
await browser.close();

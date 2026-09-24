// Approximate where Chrome will break pages, and report the empty space left
// at the foot of each one. Targets the complaint from the last report: blocks
// kept whole too aggressively leave big holes.
const { chromium } = require('playwright');
const path = require('path');

const MM = 96 / 25.4;
const PAGE_H = (297 - 14 - 16) * MM;   // content height between margins
const PAGE_W = (210 - 15 - 15) * MM;

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: Math.round(PAGE_W), height: 1200 } });
  await p.emulateMedia({ media: 'print', colorScheme: 'light' });
  await p.goto('file:///' + path.join(__dirname, '_print.html').replace(/\\/g, '/'), { waitUntil: 'networkidle' });

  const blocks = await p.evaluate(() => {
    // Atomic = must not be split. Mirrors the print CSS break-inside rules.
    const atomicSel = '.stat, .item, .note, ol.steps > li, tr, footer, .summary';
    const flowSel = '.masthead, section > h2, .sub, section > p, ul.plain, h3, .scroll, p';
    const seen = new Set();
    const out = [];
    const add = (el, atomic) => {
      if (seen.has(el)) return;
      // skip if an ancestor is already recorded
      for (const s of seen) if (s.contains(el)) return;
      seen.add(el);
      const r = el.getBoundingClientRect();
      if (r.height < 1) return;
      out.push({ atomic, top: r.top + scrollY, h: r.height, tag: el.tagName.toLowerCase(),
                 label: (el.className || el.tagName).toString().split(' ')[0].slice(0, 22) });
    };
    document.querySelectorAll(atomicSel).forEach(el => add(el, true));
    document.querySelectorAll(flowSel).forEach(el => add(el, false));
    return out.sort((a, b) => a.top - b.top);
  });

  // Greedy pagination
  let pageTop = 0, pageNo = 1;
  const gaps = [];
  let lastBottom = 0;
  for (const bl of blocks) {
    const relTop = bl.top - pageTop;
    const relBot = relTop + bl.h;
    if (relBot > PAGE_H) {
      if (bl.atomic && bl.h <= PAGE_H) {
        gaps.push({ page: pageNo, gapPx: Math.round(PAGE_H - (lastBottom - pageTop)), pushed: bl.label });
        pageTop = bl.top;
        pageNo++;
      } else {
        // splittable, or too tall to fit anywhere: let it flow across
        while (bl.top + bl.h - pageTop > PAGE_H) { pageTop += PAGE_H; pageNo++; }
      }
    }
    lastBottom = Math.max(lastBottom, bl.top + bl.h);
  }

  const totalH = Math.max(...blocks.map(b => b.top + b.h));
  console.log(`content height : ${Math.round(totalH)}px`);
  console.log(`page height    : ${Math.round(PAGE_H)}px`);
  console.log(`estimated pages: ${pageNo}`);
  console.log(`overall fill   : ${Math.round(100 * totalH / (pageNo * PAGE_H))}%`);
  console.log('\nforced breaks and the space they leave behind:');
  if (!gaps.length) console.log('  none — content flows continuously');
  gaps.forEach(g => {
    const pct = Math.round(100 * g.gapPx / PAGE_H);
    const flag = g.gapPx > 150 ? '  <-- visible hole' : '';
    console.log(`  page ${g.page}: ${g.gapPx}px empty (${pct}%) before "${g.pushed}"${flag}`);
  });

  const tall = blocks.filter(b => b.atomic && b.h > PAGE_H);
  if (tall.length) console.log('\nblocks taller than one page (will be split anyway):', tall.map(t => `${t.label} ${Math.round(t.h)}px`).join(', '));

  await b.close();
})();

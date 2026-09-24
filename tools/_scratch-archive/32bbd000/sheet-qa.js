const { chromium } = require('playwright');

// Exact per-sheet QA: each .sheet IS one A4 page, so an element screenshot is
// a pixel-accurate preview of that PDF page. Also reports content overflow —
// a sheet whose inner content exceeds its fixed height would silently clip.
(async () => {
  const src = process.argv[2];
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1000, height: 1400 } });
  await page.goto('file:///' + src.replace(/\\/g, '/'), { waitUntil: 'networkidle' });
  await page.emulateMedia({ media: 'print' });

  const sheets = await page.$$('.sheet');
  console.log('sheets:', sheets.length);
  const overflow = await page.evaluate(() =>
    [...document.querySelectorAll('.sheet')].map((s, i) => {
      const pad = 13 * 3.7795 + 11 * 3.7795; // top+bottom padding in px
      let contentH = 0;
      for (const c of s.children) {
        if (c.classList.contains('pgno')) continue;
        const st = getComputedStyle(c);
        contentH += c.offsetHeight + parseFloat(st.marginTop) + parseFloat(st.marginBottom);
      }
      return { page: i + 1, boxH: Math.round(s.clientHeight), usedH: Math.round(contentH + pad), scrollH: s.scrollHeight };
    })
  );
  for (const o of overflow) {
    const fill = Math.round((o.usedH / o.boxH) * 100);
    const flag = o.scrollH > o.boxH + 2 ? '  ← OVERFLOW (content clipped)' : (fill < 62 ? '  ← sparse' : '');
    console.log(`page ${o.page}: box ${o.boxH}px | content ~${o.usedH}px | fill ${fill}%${flag}`);
  }
  for (let i = 0; i < sheets.length; i++) {
    await sheets[i].screenshot({ path: `sheet-${i + 1}.png` });
  }
  await browser.close();
})();

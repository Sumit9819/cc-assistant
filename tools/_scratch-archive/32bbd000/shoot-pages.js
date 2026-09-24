const { chromium } = require('playwright');

// Renders the report in print media at A4 width and screenshots each page-height
// slice, so page breaks can be inspected the way the PDF will paginate.
(async () => {
  const src = process.argv[2];
  const browser = await chromium.launch();
  // A4 at 96dpi minus the 12mm/14mm margins used in the PDF
  const pageW = Math.round((210 - 24) / 25.4 * 96);
  const pageH = Math.round((297 - 28) / 25.4 * 96);
  const page = await browser.newPage({ viewport: { width: pageW, height: pageH } });
  await page.goto('file:///' + src.replace(/\\/g, '/'), { waitUntil: 'networkidle' });
  await page.emulateMedia({ media: 'print' });
  const total = await page.evaluate(() => document.body.scrollHeight);
  const count = Math.ceil(total / pageH);
  console.log('content height', total, '→', count, 'pages at', pageH, 'px');
  for (let i = 0; i < count; i++) {
    await page.evaluate((y) => window.scrollTo(0, y), i * pageH);
    await page.screenshot({ path: `pg-${i + 1}.png`, clip: { x: 0, y: 0, width: pageW, height: pageH } });
  }
  await browser.close();
})();

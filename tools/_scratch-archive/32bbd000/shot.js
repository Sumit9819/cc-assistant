const { chromium } = require('playwright');
const path = require('path');

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 680, height: 1009 } });
  await p.emulateMedia({ media: 'print', colorScheme: 'light' });
  const file = path.join(__dirname, '_print.html').split(path.sep).join('/');
  await p.goto('file:///' + file, { waitUntil: 'networkidle' });
  await p.screenshot({ path: 'pdf-page1.png' });
  await b.close();
})();

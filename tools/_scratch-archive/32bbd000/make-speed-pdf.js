const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SRC = path.join(__dirname, 'sids-speed-report.html');
const OUT = 'C:/Users/sumit/Desktop/Sids-Ponds-Speed-Review-2026-08-12.pdf';

const PRINT_CSS = `
<style>
  :root {
    --ground:#FFFFFF; --surface:#FFFFFF; --sunken:#F1F5EF;
    --ink:#17201B; --ink-soft:#3F4E45; --muted:#65746B; --rule:#CBD6C8;
    --accent:#2C6238; --live:#3F8524; --wait:#8F5416; --block:#8C3327; --shadow:none;
  }
  @page { size:A4; margin:14mm 15mm 16mm; }
  html, body { background:#fff !important; }
  body { padding:0; font-size:10.2pt; line-height:1.5; }
  .wrap { max-width:none; }
  .masthead { padding:0 0 12pt; border-bottom-width:1.5pt; }
  .masthead h1 { font-size:24pt; }
  .masthead .dek { font-size:11pt; }
  .summary { margin-top:14pt; gap:8pt; grid-template-columns:repeat(3,1fr); break-inside:avoid; }
  .stat { padding:8pt 10pt; box-shadow:none; }
  .stat .n { font-size:17pt; }
  .stat p { font-size:8.4pt; }
  section { margin-top:19pt; }
  section > h2 { font-size:14.5pt; break-after:avoid; }
  .sub { margin-bottom:9pt; font-size:9.4pt; }
  h3 { break-after:avoid; font-size:10.4pt; }
  p { margin-bottom:7pt; max-width:none; }
  .items { gap:7pt; }
  .item { padding:8pt 10pt; break-inside:avoid; }
  .item p { font-size:9.4pt; }
  .scroll { overflow:visible; break-inside:avoid; margin-bottom:8pt; }
  table { min-width:0; font-size:9.2pt; }
  th, td { padding:4pt 7pt; }
  thead { display:table-header-group; }
  tr { break-inside:avoid; }
  .note { padding:8pt 10pt; break-inside:avoid; }
  ul.plain li { font-size:9.4pt; max-width:none; }
  .client-intro { padding:10pt 12pt; break-inside:avoid; break-before:page; }
  .client-intro h2 { font-size:16pt; }
  ol.asks { gap:8pt; }
  ol.asks > li { break-inside:avoid; padding:9pt 11pt 9pt 30pt; }
  ol.asks > li::before { width:15pt; height:15pt; font-size:8pt; left:9pt; top:9pt; }
  ol.asks h3 { font-size:10.6pt; }
  ol.asks p { font-size:9.3pt; margin-bottom:5pt; }
  .qbox { padding:5pt 8pt; margin-top:6pt; }
  .qbox p { font-size:9.4pt; }
  .whybox { font-size:8.6pt; margin-top:5pt; }
  footer { margin-top:18pt; break-inside:avoid; }
  footer p { font-size:8.8pt; max-width:none; }
</style>`;

(async () => {
  const raw = fs.readFileSync(SRC, 'utf8');
  const i = raw.indexOf('</style>') + '</style>'.length;
  const doc = `<!doctype html><html lang="en"><head><meta charset="utf-8">${raw.slice(0, i)}${PRINT_CSS}</head><body>${raw.slice(i)}</body></html>`;
  const tmp = path.join(__dirname, '_speed_print.html');
  fs.writeFileSync(tmp, doc, 'utf8');

  const b = await chromium.launch();
  const p = await b.newPage();
  await p.emulateMedia({ media: 'print', colorScheme: 'light' });
  await p.goto('file:///' + tmp.split(path.sep).join('/'), { waitUntil: 'networkidle' });
  await p.pdf({ path: OUT, format: 'A4', printBackground: true, preferCSSPageSize: true });
  await b.close();

  const buf = fs.readFileSync(OUT);
  console.log('written:', OUT);
  console.log('size   :', (buf.length / 1024).toFixed(0), 'KB');
  console.log('pages  :', (buf.toString('latin1').match(/\/Type\s*\/Page[^s]/g) || []).length);
})();

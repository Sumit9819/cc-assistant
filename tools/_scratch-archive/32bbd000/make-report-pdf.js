const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SRC = path.join(__dirname, 'sids-report.html');
const OUT = 'C:/Users/sumit/Desktop/Sids-Ponds-Work-Report-2026-08-12.pdf';

// Print overrides. Deliberately narrow break-avoid: only small units (cards,
// table rows, steps) are kept whole. Applying it to whole sections is what
// produced the scattered pages with big gaps on the previous report.
const PRINT_CSS = `
<style>
  :root {
    --ground:#FFFFFF; --surface:#FFFFFF; --sunken:#F1F5EF;
    --ink:#17201B; --ink-soft:#3F4E45; --muted:#65746B; --rule:#CBD6C8;
    --accent:#2C6238; --live:#3F8524; --wait:#8F5416; --block:#8C3327;
    --shadow:none;
  }
  @page { size: A4; margin: 14mm 15mm 16mm; }

  html, body { background:#fff !important; }
  body { padding:0; font-size:10.2pt; line-height:1.5; }
  .wrap { max-width:none; }

  .masthead { padding: 0 0 12pt; border-bottom-width:1.5pt; }
  .masthead h1 { font-size:24pt; }
  .masthead .dek { font-size:11pt; }

  .summary { margin-top:14pt; gap:8pt; grid-template-columns:repeat(3,1fr); break-inside:avoid; }
  .stat { padding:8pt 10pt; box-shadow:none; }
  .stat .n { font-size:20pt; }
  .stat p { font-size:8.6pt; }

  section { margin-top:20pt; }
  section > h2 { font-size:14.5pt; break-after:avoid; }
  .sub { margin-bottom:9pt; font-size:9.4pt; }
  h3 { break-after:avoid; font-size:10.4pt; }
  p { margin-bottom:7pt; max-width:none; }

  .items { gap:7pt; }
  .item { padding:8pt 10pt; break-inside:avoid; }
  .item p { font-size:9.4pt; }
  .item .meta { font-size:8pt; }

  ol.steps { gap:9pt; }
  ol.steps > li { break-inside:avoid; padding-left:24pt; }
  ol.steps > li::before { width:16pt; height:16pt; font-size:8.5pt; }

  .scroll { overflow:visible; break-inside:auto; margin-bottom:8pt; }
  table { min-width:0; font-size:9.2pt; }
  th, td { padding:4pt 7pt; }
  thead { display:table-header-group; }
  tr { break-inside:avoid; }

  .note { padding:8pt 10pt; break-inside:avoid; }
  .note p { font-size:9.2pt; }
  ul.plain li { font-size:9.4pt; max-width:none; }

  footer { margin-top:20pt; break-inside:avoid; }
  footer p { font-size:8.8pt; max-width:none; }
</style>`;

(async () => {
  const raw = fs.readFileSync(SRC, 'utf8');
  const i = raw.indexOf('</style>') + '</style>'.length;
  const head = raw.slice(0, i);
  const body = raw.slice(i);
  const doc = `<!doctype html><html lang="en"><head><meta charset="utf-8">${head}${PRINT_CSS}</head><body>${body}</body></html>`;

  const tmp = path.join(__dirname, '_print.html');
  fs.writeFileSync(tmp, doc, 'utf8');

  const b = await chromium.launch();
  const p = await b.newPage();
  await p.emulateMedia({ media: 'print', colorScheme: 'light' });
  await p.goto('file:///' + tmp.replace(/\\/g, '/'), { waitUntil: 'networkidle' });

  await p.pdf({ path: OUT, format: 'A4', printBackground: true, preferCSSPageSize: true });
  await b.close();

  // QA: page count, and flag anything that cannot fit one page.
  const buf = fs.readFileSync(OUT);
  const pages = (buf.toString('latin1').match(/\/Type\s*\/Page[^s]/g) || []).length;
  console.log('written :', OUT);
  console.log('size    :', (buf.length / 1024).toFixed(0), 'KB');
  console.log('pages   :', pages);
})();

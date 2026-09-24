/**
 * Render an infographic HTML file to PNG at exactly 1200x628.
 *
 * Same Playwright install the Slack downloader borrows, resolved the same way
 * (this scratch folder has no package.json of its own).
 *
 * usage: node render.mjs card.html card.png
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';

const HERE = path.dirname(fileURLToPath(import.meta.url));

function loadPlaywright() {
  const candidates = [
    'C:/Users/sumit/.cc-assistant/wcag/package.json',
    'D:/faceless-studio/motion/package.json',
  ];
  const tried = [];
  for (const base of candidates) {
    if (!fs.existsSync(base)) { tried.push(`${base} (missing)`); continue; }
    try { return createRequire(base)('playwright'); }
    catch (e) { tried.push(`${base} (${e.code || e.message})`); }
  }
  throw new Error('No Playwright found:\n  ' + tried.join('\n  '));
}

const { chromium } = loadPlaywright();
const src = path.resolve(HERE, process.argv[2] || 'card.html');
const out = path.resolve(HERE, process.argv[3] || 'card.png');

const browser = await chromium.launch();
// deviceScaleFactor 2 renders at 2400x1256 then we downscale, which keeps the
// type and thin icon strokes crisp instead of aliased.
const page = await browser.newPage({
  viewport: { width: 1200, height: 628 },
  deviceScaleFactor: 2,
});
await page.goto(pathToFileURL(src).href, { waitUntil: 'load' });
// Webfonts load after `load` fires; screenshotting early bakes in the fallback.
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(400);
await page.screenshot({ path: out, clip: { x: 0, y: 0, width: 1200, height: 628 } });
await browser.close();

console.log(`${out}  ${(fs.statSync(out).size / 1024).toFixed(0)} KB`);

// One frame of a template at a given time with a given spec - the stress
// harness the measured-layout doctrine calls for. pathToFileURL avoids any
// backslash handling (inline -e scripts already ate escapes once).
// Usage: node stress_frame.js <template.html> <spec.json> <out.png> <t>
const { chromium } = require("playwright");
const { readFileSync } = require("node:fs");
const { pathToFileURL } = require("node:url");

const [template, specPath, out, tArg] = process.argv.slice(2);
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto(pathToFileURL(template).href);
  await page.evaluate((s) => window.setup(s), JSON.parse(readFileSync(specPath, "utf-8")));
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate((t) => window.seek(t), parseFloat(tArg));
  await page.screenshot({ path: out });
  await browser.close();
  console.log("stress frame ok");
})().catch((e) => { console.error(e.message); process.exit(1); });

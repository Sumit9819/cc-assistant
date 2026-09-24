const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({
    viewport: { width: 412, height: 823 }, deviceScaleFactor: 2.625, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const page = await ctx.newPage();
  await page.coverage.startJSCoverage({ resetOnNavigation: false });
  await page.goto('https://sids-ponds.com/?cov=' + Date.now(), { waitUntil: 'load', timeout: 90000 }).catch(() => {});
  await page.waitForTimeout(8000);
  // simulate a scroll + tap so interaction-gated code gets a chance to count as used
  await page.mouse.wheel(0, 1500);
  await page.waitForTimeout(4000);
  const coverage = await page.coverage.stopJSCoverage();

  const rows = [];
  for (const entry of coverage) {
    if (!entry.url.startsWith('http') || entry.url.includes('googletagmanager') || entry.url.includes('facebook') || entry.url.includes('intercom')) continue;
    const total = entry.source ? entry.source.length : 0;
    if (total < 20000) continue;
    const dead = [];
    for (const fn of entry.functions) for (const r of fn.ranges) if (r.count === 0) dead.push([r.startOffset, r.endOffset]);
    dead.sort((a, b) => a[0] - b[0]);
    let unused = 0, curS = -1, curE = -1;
    for (const [s, e] of dead) { if (s > curE) { unused += curE - curS > 0 ? curE - curS : 0; curS = s; curE = e; } else curE = Math.max(curE, e); }
    unused += curE - curS > 0 ? curE - curS : 0;
    const used = total - unused;
    rows.push({ url: entry.url.replace('https://sids-ponds.com', '').slice(0, 95), kb: Math.round(total / 1024), usedPct: Math.round(100 * Math.min(used, total) / total) });
  }
  rows.sort((a, b) => (b.kb * (100 - b.usedPct)) - (a.kb * (100 - a.usedPct)));
  console.log('script | size | % executed  (sorted by wasted bytes)');
  for (const r of rows.slice(0, 22)) console.log(`${String(r.kb).padStart(5)}KB  ${String(r.usedPct).padStart(3)}%  ${r.url}`);
  await browser.close();
})();

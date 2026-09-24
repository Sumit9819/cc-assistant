// Attribute main-thread CPU time to individual scripts, and measure how much
// of each script's bytes are actually executed.
const { chromium } = require('playwright');

const label = (u) => {
  if (!u) return '(anonymous / inline)';
  try {
    const url = new URL(u);
    const m = url.pathname.match(/wp-content\/(plugins|themes)\/([^/]+)\//);
    const file = url.pathname.split('/').pop();
    const owner = m ? `${m[1].slice(0, -1)}:${m[2]}` : url.host.replace(/^www\./, '');
    return `${owner} — ${file}`.slice(0, 62);
  } catch { return u.slice(0, 62); }
};

(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({
    viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150,
    downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  await cdp.send('Profiler.enable');
  await cdp.send('Profiler.setSamplingInterval', { interval: 500 });
  await p.coverage.startJSCoverage();
  await cdp.send('Profiler.start');

  await p.goto('https://sids-ponds.com/', { waitUntil: 'load', timeout: 300000 });
  await p.waitForTimeout(8000);

  const { profile } = await cdp.send('Profiler.stop');
  const cov = await p.coverage.stopJSCoverage();

  // self-time per node id -> aggregate by script url
  const byId = new Map();
  profile.nodes.forEach(n => byId.set(n.id, n));
  const selfTime = new Map();
  const total = profile.endTime - profile.startTime;
  if (profile.timeDeltas && profile.samples) {
    for (let i = 0; i < profile.samples.length; i++) {
      const id = profile.samples[i];
      const dt = profile.timeDeltas[i] || 0;
      selfTime.set(id, (selfTime.get(id) || 0) + dt);
    }
  }
  const byScript = new Map();
  for (const [id, us] of selfTime) {
    const n = byId.get(id);
    if (!n) continue;
    const fn = n.callFrame || {};
    const key = label(fn.url);
    byScript.set(key, (byScript.get(key) || 0) + us / 1000);
  }

  const rows = [...byScript.entries()].sort((a, b) => b[1] - a[1])
    .filter(([k, v]) => v > 20)
    .map(([script, ms]) => ({ script, cpu_ms: Math.round(ms) }));

  console.log(`\nprofile window: ${Math.round(total / 1000)}ms of wall time\n`);
  console.log('=== MAIN-THREAD CPU BY SCRIPT (throttled 4x, i.e. a mid-range phone) ===');
  console.table(rows.slice(0, 18));
  console.log(`attributed total: ${rows.reduce((a, r) => a + r.cpu_ms, 0)}ms`);

  // unused JS bytes
  const used = (e) => e.ranges.reduce((a, r) => a + (r.end - r.start), 0);
  const jsRows = cov.filter(e => e.text && e.text.length > 8000).map(e => ({
    script: label(e.url),
    kb: +(e.text.length / 1024).toFixed(0),
    usedKb: +(used(e) / 1024).toFixed(0),
    pct: Math.round(100 * used(e) / e.text.length),
  })).sort((a, b) => (b.kb - b.usedKb) - (a.kb - a.usedKb));

  console.log('\n=== JS SHIPPED vs EXECUTED (biggest waste first) ===');
  console.table(jsRows.slice(0, 16));
  console.log(`total JS: ${jsRows.reduce((a, r) => a + r.kb, 0)} KB, executed ${jsRows.reduce((a, r) => a + r.usedKb, 0)} KB`);

  await b.close();
})();

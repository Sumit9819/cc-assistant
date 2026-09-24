// v2: exclude V8's special nodes — (idle)/(program)/(garbage collector) — which
// polluted the "anonymous" bucket in v1 and made idle time look like script time.
const { chromium } = require('playwright');

const label = (u) => {
  if (!u) return null;
  try {
    const url = new URL(u);
    const m = url.pathname.match(/wp-content\/(plugins|themes)\/([^/]+)\//);
    const file = url.pathname.split('/').pop() || url.pathname;
    const owner = m ? `${m[1].slice(0, -1)}:${m[2]}` : url.host.replace(/^www\./, '');
    return `${owner} — ${file}`.slice(0, 60);
  } catch { return u.slice(0, 60); }
};
const FIRST_PARTY = /sids-ponds\.com/;

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
  await cdp.send('Profiler.start');
  await p.goto('https://sids-ponds.com/', { waitUntil: 'load', timeout: 300000 });
  await p.waitForTimeout(8000);
  const { profile } = await cdp.send('Profiler.stop');

  const byId = new Map(profile.nodes.map(n => [n.id, n]));
  const self = new Map();
  for (let i = 0; i < profile.samples.length; i++) {
    const id = profile.samples[i];
    self.set(id, (self.get(id) || 0) + (profile.timeDeltas[i] || 0));
  }

  const SPECIAL = new Set(['(idle)', '(program)', '(garbage collector)', '(root)']);
  const buckets = new Map();
  let idle = 0, gc = 0, program = 0, inline = 0;

  for (const [id, us] of self) {
    const n = byId.get(id); if (!n) continue;
    const ms = us / 1000;
    const fn = n.callFrame || {};
    const name = fn.functionName || '';
    if (SPECIAL.has(name)) {
      if (name === '(idle)') idle += ms;
      else if (name === '(garbage collector)') gc += ms;
      else program += ms;
      continue;
    }
    const key = label(fn.url);
    if (!key) { inline += ms; continue; }
    buckets.set(key, (buckets.get(key) || 0) + ms);
  }

  const rows = [...buckets.entries()].sort((a, b) => b[1] - a[1])
    .map(([script, ms]) => ({ script, cpu_ms: Math.round(ms), party: FIRST_PARTY.test(script) || /^(plugin|theme):/.test(script) ? '1st' : '3rd' }))
    .filter(r => r.cpu_ms >= 50);

  const scriptTotal = rows.reduce((a, r) => a + r.cpu_ms, 0) + Math.round(inline);
  const third = rows.filter(r => r.party === '3rd').reduce((a, r) => a + r.cpu_ms, 0);
  const first = rows.filter(r => r.party === '1st').reduce((a, r) => a + r.cpu_ms, 0);

  console.log(`\nprofile window ${Math.round((profile.endTime - profile.startTime) / 1000)}ms`);
  console.log(`idle ${Math.round(idle)}ms | GC ${Math.round(gc)}ms | V8 program ${Math.round(program)}ms | inline scripts ${Math.round(inline)}ms`);
  console.log(`\nREAL SCRIPT CPU: ${scriptTotal}ms   (first party ${first}ms, third party ${third}ms = ${Math.round(100*third/(first+third))}%)\n`);
  console.table(rows.slice(0, 20));
  await b.close();
})();

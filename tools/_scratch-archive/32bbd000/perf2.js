// Resource weight via the in-page Resource Timing API (reliable), plus the
// server cache headers, which the first run suggested are the real story.
const { chromium } = require('playwright');
const URL = process.argv[2] || 'https://sids-ponds.com/';

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

  let docHeaders = {};
  p.on('response', (r) => { if (r.url().replace(/\/$/, '') === URL.replace(/\/$/, '')) docHeaders = r.headers(); });

  await p.goto(URL, { waitUntil: 'load', timeout: 180000 });
  await p.waitForTimeout(7000);

  const data = await p.evaluate(() => {
    const rs = performance.getEntriesByType('resource').map(r => ({
      name: r.name, type: r.initiatorType,
      kb: (r.transferSize || r.encodedBodySize || 0) / 1024,
      decoded: (r.decodedBodySize || 0) / 1024,
      dur: Math.round(r.duration),
      start: Math.round(r.startTime),
      render: r.renderBlockingStatus || '',
    }));
    const nav = performance.getEntriesByType('navigation')[0] || {};
    return { rs, html: (nav.transferSize || 0) / 1024,
             serverTiming: (nav.serverTiming || []).map(s => `${s.name}=${s.duration}`).join(' ') };
  });

  const rs = data.rs;
  const kind = (r) => {
    if (/\.css(\?|$)/.test(r.name) || r.type === 'link' && /css/.test(r.name)) return 'CSS';
    if (/\.js(\?|$)/.test(r.name) || r.type === 'script') return 'JS';
    if (/\.(png|jpe?g|webp|gif|svg|avif)(\?|$)/i.test(r.name) || r.type === 'img') return 'Images';
    if (/\.(woff2?|ttf|otf|eot)(\?|$)/i.test(r.name)) return 'Fonts';
    return 'Other';
  };
  const byType = {}; let total = data.html;
  rs.forEach(r => { const k = kind(r); byType[k] = (byType[k] || 0) + r.kb; total += r.kb; });

  const host = (u) => { try { return new URL(u).host.replace('www.', ''); } catch { return '?'; } };
  const tp = rs.filter(r => !host(r.name).includes('sids-ponds.com'));
  const tpKb = tp.reduce((a, r) => a + r.kb, 0);

  console.log(`\n===== ${URL} =====`);
  console.log('--- SERVER / CACHE HEADERS ---');
  ['x-cache-enabled', 'x-proxy-cache', 'sg-f-cache', 'x-cache', 'cf-cache-status', 'cache-control', 'age', 'server', 'x-httpd']
    .forEach(h => { if (docHeaders[h] !== undefined) console.log(`  ${h}: ${docHeaders[h]}`); });
  const unknown = Object.keys(docHeaders).filter(h => /cache|sg-|proxy|age/i.test(h) &&
    !['x-cache-enabled','x-proxy-cache','sg-f-cache','x-cache','cf-cache-status','cache-control','age'].includes(h));
  unknown.forEach(h => console.log(`  ${h}: ${docHeaders[h]}`));
  if (data.serverTiming) console.log('  server-timing:', data.serverTiming);

  console.log(`\ntotal transferred: ${total.toFixed(0)} KB   (third party ${tpKb.toFixed(0)} KB = ${Math.round(100*tpKb/total)}%)`);
  console.table(Object.entries(byType).sort((a,b)=>b[1]-a[1]).map(([k,v]) => ({ type: k, KB: +v.toFixed(0) })));

  console.log('\nheaviest 18 resources:');
  console.table(rs.sort((a,b)=>b.kb-a.kb).slice(0,18).map(r => ({
    KB: +r.kb.toFixed(0), host: host(r.name).slice(0,24),
    file: r.name.split('/').pop().split('?')[0].slice(0,42), ms: r.dur,
  })));

  console.log('\nthird party by host:');
  const byHost = {}; tp.forEach(r => byHost[host(r.name)] = (byHost[host(r.name)] || 0) + r.kb);
  console.table(Object.entries(byHost).sort((a,b)=>b[1]-a[1]).slice(0,10).map(([h,v]) => ({ host: h, KB: +v.toFixed(0) })));

  await b.close();
})();

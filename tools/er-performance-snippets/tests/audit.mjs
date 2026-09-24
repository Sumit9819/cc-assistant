import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const site = process.argv[2];
const URL_ = `https://${site}/`;
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const out = { site };

for (const [label, opts] of [['mobile', devices['Pixel 7']], ['desktop', { viewport: { width: 1440, height: 900 } }]]) {
  const ctx = await b.newContext(opts);
  await ctx.addInitScript(() => {
    window.__cls = []; window.__lcp = null;
    new PerformanceObserver((l) => {
      for (const e of l.getEntries()) {
        if (e.hadRecentInput) continue;
        const src = (e.sources || []).map((s) => s.node ? (s.node.tagName + '.' + String(s.node.className || '').slice(0, 50) + (s.node.id ? '#' + s.node.id : '')) : '?').slice(0, 3);
        window.__cls.push({ v: +e.value.toFixed(4), t: Math.round(e.startTime), src });
      }
    }).observe({ type: 'layout-shift', buffered: true });
    new PerformanceObserver((l) => {
      for (const e of l.getEntries()) {
        const el = e.element;
        window.__lcp = {
          t: Math.round(e.startTime), url: e.url, size: e.size,
          tag: el ? el.tagName : null, cls: el ? String(el.className || '').slice(0, 80) : null,
          loadingAttr: el && el.getAttribute ? el.getAttribute('loading') : null,
          dataSrc: el && el.getAttribute ? !!el.getAttribute('data-src') : null,
          natW: el ? el.naturalWidth : null, shownW: el ? Math.round(el.getBoundingClientRect().width) : null,
        };
      }
    }).observe({ type: 'largest-contentful-paint', buffered: true });
  });
  const p = await ctx.newPage();
  const cdp = await ctx.newCDPSession(p);
  await cdp.send('Network.enable');
  const reqs = new Map();
  cdp.on('Network.responseReceived', (e) => reqs.set(e.requestId, { url: e.response.url, type: e.type, bytes: 0 }));
  cdp.on('Network.loadingFinished', (e) => { const r = reqs.get(e.requestId); if (r) r.bytes = e.encodedDataLength; });
  const resp = await p.goto(URL_, { waitUntil: 'load', timeout: 180000 });
  const html = await resp.text();
  await p.waitForTimeout(label === 'mobile' ? 7000 : 4000);

  const f = await p.evaluate(() => {
    const head = document.head;
    const vh = innerHeight;
    const css = [...document.querySelectorAll('link[rel="stylesheet"]')].map((l) => ({ href: l.href, inHead: head.contains(l), media: l.media || 'all' }));
    const blockingJs = [...head.querySelectorAll('script[src]')].filter((s) => !s.async && !s.defer && s.type !== 'module').map((s) => s.src);
    const preloads = [...document.querySelectorAll('link[rel="preload"]')].map((l) => l.as + ':' + l.href.split('/').pop() + (l.media ? ' [' + l.media + ']' : ''));
    const imgsAbove = [...document.images].filter((i) => { const r = i.getBoundingClientRect(); return r.top < vh && r.bottom > 0 && r.width > 50; })
      .map((i) => ({ src: (i.currentSrc || i.src || '').split('/').pop().slice(0, 60), natW: i.naturalWidth, shownW: Math.round(i.getBoundingClientRect().width), loading: i.getAttribute('loading'), lazyClass: /lazy/.test(i.className), dataSrc: !!i.getAttribute('data-src') }));
    const bgAbove = [...document.querySelectorAll('body *')].filter((e) => { const r = e.getBoundingClientRect(); return r.top < vh && r.bottom > 0 && r.width > 200; })
      .map((e) => ({ bg: getComputedStyle(e).backgroundImage, cls: String(e.className || '').slice(0, 60) }))
      .filter((x) => /url\(/.test(x.bg)).slice(0, 4)
      .map((x) => ({ file: x.bg.split('/').pop().replace(/["')]/g, '').slice(0, 60), cls: x.cls }));
    const popups = [...document.querySelectorAll('[data-elementor-type="popup"]')].map((e) => e.getAttribute('data-elementor-id'));
    const fonts = [...new Set([...document.fonts].filter((x) => x.status === 'loaded').map((x) => x.family + ' ' + x.weight))];
    return { css, blockingJs, preloads, imgsAbove, bgAbove, popups, fonts, dom: document.getElementsByTagName('*').length, cls: window.__cls, lcp: window.__lcp };
  });

  const all = [...reqs.values()];
  const host = (u) => { try { return new URL(u).host; } catch { return '?'; } };
  const bare = site.replace(/^www\./, '');
  const own = (r) => host(r.url).includes(bare);
  const by = (arr, key) => {
    const m = {};
    for (const r of arr) { const k = key(r); m[k] = m[k] || [0, 0]; m[k][0]++; m[k][1] += r.bytes; }
    return Object.fromEntries(Object.entries(m).sort((a, c) => c[1][1] - a[1][1]).map(([k, v]) => [k, v[0] + ' / ' + Math.round(v[1] / 1024) + 'KB']));
  };
  const strip = (u) => u.replace(/^https?:\/\/[^/]+/, '');
  out[label] = {
    lcp: f.lcp,
    clsTotal: +f.cls.reduce((s, x) => s + x.v, 0).toFixed(3),
    clsTop: f.cls.sort((a, c) => c.v - a.v).slice(0, 4),
    totals: { requests: all.length, KB: Math.round(all.reduce((s, r) => s + r.bytes, 0) / 1024), firstPartyKB: Math.round(all.filter(own).reduce((s, r) => s + r.bytes, 0) / 1024) },
    byType: by(all, (r) => r.type),
    thirdPartyHosts: Object.fromEntries(Object.entries(by(all.filter((r) => !own(r)), (r) => host(r.url))).slice(0, 12)),
    biggestOwn: all.filter(own).sort((a, c) => c.bytes - a.bytes).slice(0, 8).map((r) => Math.round(r.bytes / 1024) + 'KB ' + r.type + ' ' + strip(r.url).slice(0, 90)),
    stylesheets: f.css.map((c) => { const r = all.find((x) => x.url === c.href); return (c.inHead ? 'HEAD ' : 'body ') + Math.round((r ? r.bytes : 0) / 1024) + 'KB ' + strip(c.href).slice(0, 70) + (c.media !== 'all' ? ' media=' + c.media : ''); }),
    blockingJs: f.blockingJs.map((u) => strip(u).slice(0, 80)),
    preloads: f.preloads, imgsAboveFold: f.imgsAbove, bgAboveFold: f.bgAbove,
    popupsRendered: f.popups, fontsLoaded: f.fonts, dom: f.dom,
  };
  if (label === 'mobile') {
    const has = (re) => re.test(html);
    out.html = {
      bytes: html.length,
      generatorWP: has(/<meta name="generator" content="WordPress/), rsd: has(/EditURI/), shortlinkTag: has(/rel=['"]shortlink/), restLink: has(/api\.w\.org/), oembed: has(/\+oembed/),
      emoji: has(/wp-emoji|_wpemojiSettings/), jqMigrate: has(/jquery-migrate/), blockLib: has(/wp-block-library/), globalStyles: has(/global-styles-inline/), dashicons: has(/dashicons/),
      rankMathCredit: has(/<!--[^>]*Rank Math[^>]*-->/), speculationRules: (html.match(/<script type="speculationrules">/g) || []).length, flyingScripts: has(/id="flying-scripts"/),
      fontAwesome: has(/font-awesome|fontawesome/), googleFonts: has(/fonts\.googleapis\.com|fonts\.gstatic/),
      elementorGenerator: ((html.match(/<meta name="generator" content="Elementor[^"]*"/) || [''])[0]).slice(0, 220),
      gtm: [...new Set(html.match(/GTM-[A-Z0-9]+/g) || [])],
      overlays: [...new Set(html.match(/id="[a-z0-9-]*(overlay|popup)[a-z0-9-]*"/g) || [])].slice(0, 6),
      ekit: has(/elementskit/), polylang: has(/pll_|polylang/),
    };
  }
  await ctx.close();
}
fs.writeFileSync(`audit-${site}.json`, JSON.stringify(out, null, 1));
console.log('done ' + site);
await b.close();

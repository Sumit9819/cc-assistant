const { chromium } = require('playwright');
const TARGET = 'https://sids-ponds.com/';

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

  // when does each slider image actually get requested?
  const imgReq = [];
  const t0 = Date.now();
  p.on('request', (r) => {
    if (r.resourceType() === 'image') imgReq.push({ at: Date.now() - t0, url: r.url().split('/').pop().split('?')[0] });
  });

  await p.goto(TARGET, { waitUntil: 'domcontentloaded', timeout: 180000 });

  // sample the slider geometry over time to catch the shift
  const samples = [];
  for (let i = 0; i < 14; i++) {
    const s = await p.evaluate(() => {
      const sl = document.querySelector('.et_pb_slider');
      const desc = document.querySelector('.et_pb_slide_description');
      if (!sl) return null;
      const r = sl.getBoundingClientRect();
      const d = desc ? desc.getBoundingClientRect() : null;
      return {
        sliderH: Math.round(r.height),
        descTop: d ? Math.round(d.top + scrollY) : null,
        descH: d ? Math.round(d.height) : null,
        slides: sl.querySelectorAll('.et_pb_slide').length,
        hasJsClass: sl.className.includes('et_pb_slider_carousel') || document.body.className.includes('et_pb_slider'),
      };
    }).catch(() => null);
    if (s) samples.push({ t: Date.now() - t0, ...s });
    await p.waitForTimeout(1200);
  }

  const info = await p.evaluate(() => {
    const sl = document.querySelector('.et_pb_slider');
    const slides = [...document.querySelectorAll('.et_pb_slide')];
    return {
      sliderClasses: sl ? sl.className : '(none)',
      slideCount: slides.length,
      slides: slides.map((s, i) => {
        const cs = getComputedStyle(s);
        const bg = cs.backgroundImage || '';
        const m = bg.match(/url\(["']?(.+?)["']?\)/);
        const inner = s.querySelector('.et_pb_slide_image img, img');
        return {
          i,
          visible: s.getBoundingClientRect().width > 0 && cs.display !== 'none',
          display: cs.display,
          bg: m ? m[1].split('/').pop().split('?')[0].slice(0, 46) : '',
          img: inner ? inner.currentSrc.split('/').pop().split('?')[0].slice(0, 46) : '',
          loading: inner ? inner.getAttribute('loading') : '',
        };
      }),
      preloads: [...document.querySelectorAll('link[rel=preload]')].map(l => ({
        as: l.getAttribute('as'), href: l.href.split('/').pop().split('?')[0].slice(0, 46),
      })),
    };
  });

  const perf = await p.evaluate(() => performance.getEntriesByType('resource')
    .filter(r => /\.(png|jpe?g|webp|avif)/i.test(r.name))
    .map(r => ({ f: r.name.split('/').pop().split('?')[0], kb: +((r.transferSize || r.encodedBodySize || 0) / 1024).toFixed(0), start: Math.round(r.startTime) }))
    .sort((a, b) => b.kb - a.kb));

  console.log('slider classes:', info.sliderClasses.slice(0, 110));
  console.log('slide count   :', info.slideCount);
  console.log('\npreload hints:'); console.table(info.preloads);
  console.log('\nslides (display / background image):'); console.table(info.slides);

  console.log('\nslider geometry over time (the CLS story):');
  console.table(samples.map(s => ({ t_ms: s.t, sliderH: s.sliderH, descTop: s.descTop, descH: s.descH })));

  console.log('\nimages by weight (top 14):');
  console.table(perf.slice(0, 14));
  console.log(`\ntotal image weight: ${perf.reduce((a, r) => a + r.kb, 0)} KB across ${perf.length} images`);

  console.log('\nfirst 14 image REQUESTS in order (are all slides fetched up front?):');
  console.table(imgReq.slice(0, 14));

  await b.close();
})();

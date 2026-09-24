const { chromium } = require('playwright');

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });

  const css = [];
  p.on('response', async (r) => {
    try {
      const ct = (r.headers()['content-type'] || '');
      if (!/css/.test(ct)) return;
      const buf = await r.body().catch(() => null);
      if (buf) css.push({ url: r.url().split('/').pop().split('?')[0], host: new URL(r.url()).host, kb: +(buf.length / 1024).toFixed(1) });
    } catch (e) {}
  });

  await p.goto('https://sids-ponds.com/?v=' + Date.now(), { waitUntil: 'networkidle', timeout: 90000 });

  const r = await p.evaluate(() => {
    const out = {};
    // Is the grid rule the Customizer flagged actually live right now?
    let gridRule = null, customCssBlock = null;
    for (const sheet of document.styleSheets) {
      let rules; try { rules = sheet.cssRules; } catch (e) { continue; }
      const collect = (rs) => { for (const r of rs) {
        if (r.selectorText) {
          if (/long-link-list$/.test(r.selectorText.trim())) gridRule = r.cssText.slice(0, 160);
          continue;
        }
        if (r.cssRules) collect(r.cssRules);
      }};
      collect(rules);
    }
    out.liveGridRule = gridRule;

    // Weight of every inline <style> block, biggest first
    out.inlineBlocks = [...document.querySelectorAll('style')]
      .map(s => ({ id: s.id || '(no id)', kb: +(s.textContent.length / 1024).toFixed(1) }))
      .sort((a, b) => b.kb - a.kb).slice(0, 8);
    out.totalInlineKb = +([...document.querySelectorAll('style')]
      .reduce((a, s) => a + s.textContent.length, 0) / 1024).toFixed(1);
    const wpc = document.getElementById('wp-custom-css');
    out.wpCustomCssKb = wpc ? +(wpc.textContent.length / 1024).toFixed(1) : 'no #wp-custom-css block on page';
    return out;
  });

  console.log('LIVE grid rule (the one the Customizer flagged):');
  console.log('  ', r.liveGridRule || 'NOT FOUND');
  console.log('\n#wp-custom-css block size:', r.wpCustomCssKb);
  console.log('total inline <style> weight:', r.totalInlineKb, 'KB');
  console.log('\nBiggest inline blocks:');
  console.table(r.inlineBlocks);
  console.log('\nExternal CSS files:');
  console.table(css.sort((a, b) => b.kb - a.kb).slice(0, 12));
  console.log('external CSS total:', +css.reduce((a, c) => a + c.kb, 0).toFixed(1), 'KB');

  await b.close();
})();

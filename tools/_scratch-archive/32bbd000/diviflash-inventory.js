// What is DiviFlash actually rendering on the 89 pages that use it?
const { chromium } = require('playwright');

const PAGES = [
  ['About',    'https://sids-ponds.com/about-us/'],
  ['Delivery', 'https://sids-ponds.com/delivery/'],
  ['Product (in-lite)', 'https://sids-ponds.com/product/lighting/in-lite/recessed/in-lite-fusion-22-rvs/'],
];

(async () => {
  const b = await chromium.launch();
  for (const [label, url] of PAGES) {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } });
    const p = await ctx.newPage();
    await p.goto(url, { waitUntil: 'networkidle', timeout: 180000 });

    const found = await p.evaluate(() => {
      const MODULE = /^(difl_[a-z]+|df_[a-z_]+)$/;
      const out = [];
      document.querySelectorAll('[class*="difl_"], [class*="df_"]').forEach(el => {
        const cls = (el.className || '').toString().split(/\s+/);
        const mod = cls.find(c => /^difl_[a-z]+$/.test(c));
        if (!mod) return;
        // only top-level module wrappers (skip inner item classes duplicating)
        if (!cls.some(c => /^et_pb_module$/.test(c))) return;
        const text = (el.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 90);
        const links = [...el.querySelectorAll('a')].map(a => (a.innerText || '').trim()).filter(Boolean).slice(0, 4);
        out.push({ module: mod, text, links: links.join(' | ') });
      });
      // buttons rendered by DiviFlash's dual button
      const btns = [...document.querySelectorAll('.df_button_container a, .df_button_left, .df_button_right')]
        .map(e => (e.innerText || '').trim()).filter(Boolean);
      return { modules: out, dualButtons: [...new Set(btns)] };
    });

    console.log(`\n═══════ ${label} ═══════`);
    if (!found.modules.length && !found.dualButtons.length) { console.log('  (no DiviFlash modules)'); }
    found.modules.forEach(m => {
      console.log(`  ${m.module}`);
      if (m.links) console.log(`      links: ${m.links}`);
      if (m.text) console.log(`      text : ${m.text}`);
    });
    if (found.dualButtons.length) console.log(`  DUAL BUTTON labels: ${found.dualButtons.join('  /  ')}`);
    await ctx.close();
  }
  await b.close();
})();

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage();
  await p.setContent('<html><head></head><body></body></html>');

  const parse = (css) => p.evaluate((css) => {
    // CSSStyleRule has an (empty) .cssRules list in browsers supporting CSS
    // nesting, so `if (r.cssRules) recurse` skips every style rule.
    // selectorText MUST be checked first.
    const collect = (rules, ctx, decls, counter) => {
      for (const r of rules) {
        if (r.selectorText) {
          counter.n++;
          r.selectorText.split(',').map(x => x.trim().replace(/\s+/g, ' ')).forEach(sel => {
            const key = (ctx ? '@media ' + ctx + ' ' : '') + sel;
            decls.set(key, (decls.get(key) || '') + r.style.cssText);
          });
          continue;
        }
        if (r.type === CSSRule.KEYFRAMES_RULE) {
          counter.kf++;
          for (const k of r.cssRules) decls.set('@keyframes ' + r.name + ' ' + k.keyText, k.style.cssText);
          continue;
        }
        if (r.cssRules) collect(r.cssRules, r.conditionText || ctx, decls, counter);
      }
    };
    const s = document.createElement('style');
    s.textContent = css;
    document.head.appendChild(s);
    const decls = new Map(), counter = { n: 0, kf: 0 };
    collect(s.sheet.cssRules, '', decls, counter);
    s.remove();
    return { count: counter.n, keyframes: counter.kf, decls: [...decls.entries()] };
  }, css);

  const dir = __dirname;
  const o = await parse(fs.readFileSync(path.join(dir, 'orig.css'), 'utf8'));
  const nw = await parse(fs.readFileSync(path.join(dir, 'new.css'), 'utf8'));

  const oD = new Map(o.decls), nD = new Map(nw.decls);

  // POSITIVE CONTROL: the walker must find selectors we know are present.
  const ctrl = oD.has('.Friday-Black-tag') && oD.has('.content a') && nD.has('.pillar-text .et_pb_text_inner h2');
  console.log(`POSITIVE CONTROL: ${ctrl ? 'PASS' : 'FAIL - results meaningless'}`);
  if (!ctrl) { await b.close(); process.exit(1); }

  const lost = [...oD.keys()].filter(k => !nD.has(k));
  const added = [...nD.keys()].filter(k => !oD.has(k));
  const changed = [];
  for (const [k, v] of oD) if (nD.has(k) && nD.get(k) !== v) changed.push({ sel: k, before: v, after: nD.get(k) });

  console.log(`\nstyle rules: orig=${o.count} new=${nw.count}   @keyframes: orig=${o.keyframes} new=${nw.keyframes}`);
  console.log(`unique selectors: orig=${oD.size} new=${nD.size}`);
  console.log('\n=== SELECTORS LOST (must be empty) ===');
  console.log(lost.length ? lost.map(s => '  - ' + s).join('\n') : '  none');
  console.log('\n=== SELECTORS ADDED ===');
  console.log(added.length ? added.map(s => '  + ' + s).join('\n') : '  none');
  console.log('\n=== DECLARATION CHANGES ON SHARED SELECTORS ===');
  if (!changed.length) console.log('  none');
  changed.forEach(c => console.log(`  ${c.sel}\n      before: ${c.before}\n      after : ${c.after}`));

  await b.close();
})();

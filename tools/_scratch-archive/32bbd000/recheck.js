const { chromium } = require('playwright');

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  await p.goto('https://sids-ponds.com/product-category/all-products/artificial-turf-and-sod/?v=' + Date.now(),
               { waitUntil: 'networkidle', timeout: 90000 });

  const r = await p.evaluate(() => {
    const out = { scanned: 0, corsBlocked: 0, styleRulesSeen: 0 };
    const el = document.querySelector('.pillar-text .et_pb_text_inner');
    const targets = [...el.querySelectorAll('h2,h3,li,ul')];

    const pillarRules = [], collide = [], controlHits = [];

    const collect = (rules, sheetName) => {
      for (const r of rules) {
        if (r.selectorText) {
          out.styleRulesSeen++;
          // POSITIVE CONTROL: a selector we know must exist somewhere.
          if (/et_pb_text_inner/.test(r.selectorText)) controlHits.push(sheetName + ' :: ' + r.selectorText.slice(0, 60));
          if (/pillar-text/.test(r.selectorText)) pillarRules.push({ sheetName, sel: r.selectorText, css: r.style.cssText.slice(0, 90) });
          if (/margin|max-width|padding-left|line-height/.test(r.style.cssText)) {
            try {
              for (const t of targets) {
                if (t.matches(r.selectorText)) {
                  collide.push({ sheetName, sel: r.selectorText.slice(0, 62), tag: t.tagName, css: r.style.cssText.slice(0, 62) });
                  break;
                }
              }
            } catch (e) {}
          }
          continue;
        }
        if (r.cssRules) collect(r.cssRules, sheetName);
      }
    };

    for (const sheet of document.styleSheets) {
      const name = sheet.href ? sheet.href.split('/').pop().split('?')[0] : (sheet.ownerNode?.id || '(inline)');
      let rules;
      try { rules = sheet.cssRules; } catch (e) { out.corsBlocked++; continue; }
      out.scanned++;
      collect(rules, name);
    }

    out.controlSample = controlHits.slice(0, 3);
    out.controlPassed = controlHits.length > 0;
    out.pillarTextRulesFound = pillarRules;
    out.collidingRules = collide;
    return out;
  });

  console.log(JSON.stringify(r, null, 1));
  await b.close();
})();

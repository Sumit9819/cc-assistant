/**
 * Which stylesheet actually emits the TikTok (e07b) and header-menu (f48b)
 * glyphs? Neither codepoint appears in the page HTML, so the rules live in an
 * external file. Knowing WHICH file decides whether the fix is in scope
 * (a Divi Theme Builder layout / Divi custom CSS = editable) or not
 * (a third-party plugin stylesheet = out of bounds).
 */
import { chromium } from 'playwright';

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

const browser = await chromium.launch();
const ctx = await browser.newContext({ userAgent: UA });
const page = await ctx.newPage();
await page.goto('https://sids-ponds.com/', { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(1200);

const found = await page.evaluate(() => {
  const wanted = ['e07b', 'f48b', 'f004'];
  const out = [];
  for (const sheet of document.styleSheets) {
    let rules;
    try { rules = sheet.cssRules; } catch {
      out.push({ href: sheet.href, note: 'CORS-blocked, cannot read' });
      continue;
    }
    if (!rules) continue;
    const walk = (list, depth) => {
      for (const r of list) {
        if (r.cssRules) { walk(r.cssRules, depth + 1); continue; }
        const t = r.cssText || '';
        const low = t.toLowerCase();
        for (const w of wanted) {
          if (low.includes('\\' + w) || low.includes(w)) {
            out.push({
              code: w,
              href: sheet.href || '(inline <style>)',
              rule: t.slice(0, 190),
            });
          }
        }
      }
    };
    walk(rules, 0);
  }
  return out;
});

if (!found.length) console.log('  no matching rules readable');
const seen = new Set();
for (const f of found) {
  const k = (f.code || '') + (f.href || '') + (f.rule || '').slice(0, 60);
  if (seen.has(k)) continue;
  seen.add(k);
  if (f.note) { console.log(`\n  [${f.href}] ${f.note}`); continue; }
  console.log(`\n  U+${f.code}`);
  console.log(`    sheet: ${f.href}`);
  console.log(`    rule : ${f.rule}`);
}
await browser.close();

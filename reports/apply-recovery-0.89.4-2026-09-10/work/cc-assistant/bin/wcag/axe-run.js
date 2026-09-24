// cc-assistant wcag_sweep runner: Playwright + axe-core, plus the keyboard
// checks an automated scanner normally leaves to a human.
//
//   node axe-run.js --urls <file> --out <file> [--concurrency 4] [--keyboard 1]
//   node axe-run.js --check          (can a browser launch? exit 0/1)
//
// Every page is scrolled top-to-bottom BEFORE axe runs: lazy-loaded
// background images and gradients otherwise measure as their un-loaded
// fallback colour and produce contrast failures that do not exist
// (measured 2026-08-25: 61 fake flip-box failures on one site).
const fs = require('fs');
const path = require('path');
const observeForms = require('./form-observation.js');

function arg(name, dflt) {
  const i = process.argv.indexOf('--' + name);
  return i >= 0 && process.argv[i + 1] !== undefined ? process.argv[i + 1] : dflt;
}
const CHECK = process.argv.includes('--check');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

let chromium, axeSource;
try {
  ({ chromium } = require('playwright'));
  axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
} catch (e) {
  console.error('DEPS_MISSING ' + e.message);
  process.exit(3);
}

async function keyboardChecks(page) {
  // Runs inside the page. Returns a plain object; every step is guarded so a
  // page with odd scripts cannot take the whole sweep down.
  const out = { skip_link: null, focus_invisible: [], forms: [], popup: null, tab_stops: 0 };
  try {
    out.skip_link = await page.evaluate(() => {
      const a = [...document.querySelectorAll('a[href^="#"]')].find(x => /skip|saltar|ir al contenido|aller au contenu|zum inhalt/i.test(x.textContent || ''));
      if (!a) return { present: false };
      const id = a.getAttribute('href').slice(1);
      return { present: true, target_exists: !!(id && document.getElementById(id)), href: a.getAttribute('href') };
    });
  } catch (e) { out.skip_link = { error: String(e.message).slice(0, 80) }; }

  // Focus visibility: tab through the first 30 stops and read the computed
  // outline/box-shadow of whatever is focused. Chromium reports the default
  // ring as outline-style "auto"; a site that removed it shows "none" and no
  // shadow, which is WCAG 2.4.7 failing for keyboard users.
  try {
    await page.evaluate(() => window.scrollTo(0, 0));
    const seen = new Set();
    for (let i = 0; i < 30; i++) {
      await page.keyboard.press('Tab');
      const info = await page.evaluate(() => {
        const el = document.activeElement;
        if (!el || el === document.body) return null;
        const s = getComputedStyle(el);
        const key = (el.tagName + '|' + (el.getAttribute('href') || '') + '|' + (el.textContent || '').trim().slice(0, 40));
        const visible = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || (s.boxShadow && s.boxShadow !== 'none');
        const r = el.getBoundingClientRect();
        return { key, visible, tag: el.tagName.toLowerCase(), text: (el.getAttribute('aria-label') || el.textContent || el.getAttribute('title') || '').trim().slice(0, 60), cls: (el.className && String(el.className).slice(0, 50)) || '', offscreen: r.width === 0 && r.height === 0 };
      });
      if (!info) continue;
      if (seen.has(info.key)) break; // wrapped around
      seen.add(info.key);
      out.tab_stops++;
      if (!info.visible && !info.offscreen) out.focus_invisible.push({ tag: info.tag, text: info.text, cls: info.cls });
    }
  } catch (e) { out.focus_error = String(e.message).slice(0, 80); }

  // Passive observations only. A click or checkValidity() can execute page
  // handlers and send requests even when the submit event is prevented.
  try {
    out.forms = await page.evaluate(observeForms);
  } catch (e) { out.forms_error = String(e.message).slice(0, 80); }

  // Popups: if a modal is open after load+scroll, focus must be inside it and
  // Escape must close it (WCAG 2.1.2 no keyboard trap / 2.4.3 focus order).
  try {
    out.popup = await page.evaluate(() => {
      const open = [...document.querySelectorAll('.elementor-popup-modal, [role=dialog], dialog[open]')].find(m => { const s = getComputedStyle(m); return s.display !== 'none' && s.visibility !== 'hidden'; });
      if (!open) return { open: false };
      return { open: true, id: open.id || open.className.slice(0, 40), focus_inside: open.contains(document.activeElement), has_close: !!open.querySelector('[aria-label*="lose" i], .dialog-close-button, button.close, [data-dismiss]') };
    });
    if (out.popup && out.popup.open) {
      await page.keyboard.press('Escape');
      await page.waitForTimeout(500);
      out.popup.escape_closes = await page.evaluate(() => ![...document.querySelectorAll('.elementor-popup-modal, [role=dialog], dialog[open]')].some(m => { const s = getComputedStyle(m); return s.display !== 'none' && s.visibility !== 'hidden'; }));
    }
  } catch (e) { out.popup = { error: String(e.message).slice(0, 80) }; }
  return out;
}

(async () => {
  if (CHECK) {
    try { const b = await chromium.launch(); await b.close(); console.log('BROWSER_OK'); process.exit(0); }
    catch (e) { console.error('BROWSER_MISSING ' + e.message.split('\n')[0]); process.exit(2); }
  }
  const urlsFile = arg('urls'); const outFile = arg('out');
  const conc = Math.max(1, Math.min(8, parseInt(arg('concurrency', '4'), 10) || 4));
  const keyboard = arg('keyboard', '1') !== '0';
  if (!urlsFile || !outFile) { console.error('usage: --urls <file> --out <file>'); process.exit(1); }
  const urls = fs.readFileSync(urlsFile, 'utf8').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
  // Incremental progress next to the output file, so a run that dies or hangs still says how far it got.
  const progFile = outFile + '.progress.log';
  try { fs.writeFileSync(progFile, ''); } catch (e) {}
  const prog = (m) => { try { fs.appendFileSync(progFile, new Date().toISOString() + ' ' + m + '\n'); } catch (e) {} };
  prog('START urls=' + urls.length + ' conc=' + conc + ' keyboard=' + keyboard);

  const browser = await chromium.launch();
  prog('BROWSER launched');
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1366, height: 900 } });
  const results = [];
  let i = 0;
  async function worker() {
    while (i < urls.length) {
      const url = urls[i++];
      const page = await ctx.newPage();
      const rec = { url, status: null, violations: [], keyboard: null, error: null };
      // Per-page watchdog: goto has its own 60s cap, but the keyboard pass (form submits, popup
      // probing) can wait on navigations that never settle. One page must never stall the sweep.
      const work = (async () => {
        const resp = await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
        rec.status = resp ? resp.status() : null;
        prog('  goto ' + rec.status + ' ' + url);
        await page.evaluate(async () => { const h = document.body.scrollHeight; let n = 0; for (let y = 0; y < h && n < 80; y += 700, n++) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
        await page.waitForTimeout(800);
        prog('  scrolled ' + url);
        await page.addScriptTag({ content: axeSource });
        const r = await page.evaluate(async () => axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'] }, resultTypes: ['violations'] }));
        prog('  axe ' + r.violations.length + 'v ' + url);
        rec.violations = r.violations.map(v => ({
          id: v.id, impact: v.impact, wcag: v.tags.filter(t => /^wcag\d/.test(t)), help: v.help, count: v.nodes.length,
          nodes: v.nodes.slice(0, 5).map(n => ({ target: n.target.join(' ').slice(0, 160), html: n.html.slice(0, 200), summary: n.failureSummary ? n.failureSummary.slice(0, 200) : '' })),
        }));
        if (keyboard) { rec.keyboard = await keyboardChecks(page); prog('  keyboard ok ' + url); }
      })();
      let timer = null;
      try {
        await Promise.race([work, new Promise((_, rej) => { timer = setTimeout(() => rej(new Error('page watchdog: exceeded 150s')), 150000); })]);
      } catch (e) {
        rec.error = String(e.message || e).slice(0, 200);
      } finally {
        if (timer) clearTimeout(timer);
      }
      await Promise.race([page.close().catch(() => {}), new Promise(r => setTimeout(r, 10000))]);
      work.catch(() => {});
      results.push(rec);
      const line = `${results.length}/${urls.length} ${rec.status} ${rec.violations.length}v ${rec.error ? 'ERR ' + rec.error + ' ' : ''}${url}`;
      prog(line);
      process.stdout.write(line + '\n');
    }
  }
  await Promise.all(Array.from({ length: conc }, worker));
  fs.writeFileSync(outFile, JSON.stringify({ ran_at: new Date().toISOString(), keyboard, pages: results }));
  prog('DONE ' + results.length);
  console.log('DONE ' + results.length);
  await Promise.race([browser.close().catch(() => {}), new Promise(r => setTimeout(r, 15000))]);
  process.exit(0);
})().catch(e => { console.error('FATAL ' + e.message); process.exit(1); });

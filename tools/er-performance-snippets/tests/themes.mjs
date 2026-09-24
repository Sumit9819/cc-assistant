import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const PROFILE = 'D:/cc-assistant/tools/card-generator/featured/.chrome-profile';
for (const lock of ['SingletonLock', 'SingletonCookie', 'SingletonSocket', 'lockfile']) { try { fs.rmSync(path.join(PROFILE, lock), { force: true }); } catch {} }
const ctx = await chromium.launchPersistentContext(PROFILE, { headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const page = ctx.pages()[0] || await ctx.newPage();
for (const site of ['erofirving.com', 'erofwhiterock.com', 'eroflufkin.com']) {
  try {
    await page.goto(`https://${site}/wp-admin/`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    let state = 'unknown';
    for (let i = 0; i < 12; i++) {
      state = await page.evaluate(() => (window.wpApiSettings && wpApiSettings.nonce) ? 'ok' : (document.querySelector('#loginform') ? 'login' : 'wait')).catch(() => 'err');
      if (state !== 'wait' && state !== 'err') break; await page.waitForTimeout(2000);
    }
    if (state !== 'ok') { console.log(`\n== ${site}: not signed in (${state})`); continue; }
    const themes = await page.evaluate(async () => { const r = await fetch(wpApiSettings.root + 'wp/v2/themes?status=active', { headers: { 'X-WP-Nonce': wpApiSettings.nonce } }); return r.json(); });
    const t = themes[0];
    console.log(`\n== ${site}: active theme ${t.stylesheet} ${t.version} (parent: ${t.template})`);
    const stylesheet = t.stylesheet;
    await page.goto(`https://${site}/wp-admin/theme-editor.php?file=functions.php&theme=${stylesheet}`, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForTimeout(2500);
    const code = await page.evaluate(() => document.querySelector('#newcontent') ? document.querySelector('#newcontent').value : null);
    if (code === null) { console.log('   theme file editor not available'); continue; }
    fs.writeFileSync(`functions-${site}.php`, code);
    console.log(`   functions.php: ${code.split('\n').length} lines | custom markers: ${['remove_action', 'wp_dequeue', 'speculation', 'preload', 'flying', 'sgo_'].filter(k => code.includes(k)).join(', ') || 'none'}`);
    if (t.template !== t.stylesheet) {
      await page.goto(`https://${site}/wp-admin/theme-editor.php?file=functions.php&theme=${t.template}`, { waitUntil: 'domcontentloaded', timeout: 120000 });
      await page.waitForTimeout(2500);
      const pc = await page.evaluate(() => document.querySelector('#newcontent') ? document.querySelector('#newcontent').value : null);
      if (pc) { fs.writeFileSync(`functions-parent-${site}.php`, pc); console.log(`   parent functions.php: ${pc.split('\n').length} lines`); }
    }
  } catch (e) { console.log(`\n== ${site}: error ${e.message.slice(0, 100)}`); }
}
await ctx.close();

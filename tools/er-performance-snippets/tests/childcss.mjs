import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const PROFILE = 'D:/cc-assistant/tools/card-generator/featured/.chrome-profile';
for (const l of ['SingletonLock', 'SingletonCookie', 'SingletonSocket', 'lockfile']) { try { fs.rmSync(path.join(PROFILE, l), { force: true }); } catch {} }
const ctx = await chromium.launchPersistentContext(PROFILE, { headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const page = ctx.pages()[0] || await ctx.newPage();
await page.goto('https://erofirving.com/wp-admin/theme-editor.php?file=style.css&theme=hello-elementor-child', { waitUntil: 'domcontentloaded', timeout: 120000 });
await page.waitForTimeout(3000);
const r = await page.evaluate(() => ({ css: document.querySelector('#newcontent') ? document.querySelector('#newcontent').value : null, notice: (document.querySelector('.notice, .error') || {}).innerText || '' }));
if (r.css === null) console.log('child theme style.css not readable:', r.notice.slice(0, 150));
else {
  const body = r.css.replace(/\/\*[\s\S]*?\*\//g, '').trim();
  console.log('child theme still installed | style.css total', r.css.length, 'chars | CSS rules outside the header comment:', body.length ? body.slice(0, 400) : 'NONE (header only)');
}
await page.goto('https://erofirving.com/wp-admin/themes.php', { waitUntil: 'domcontentloaded', timeout: 120000 });
await page.waitForTimeout(2000);
const themes = await page.evaluate(() => [...document.querySelectorAll('.theme')].map(t => (t.getAttribute('data-slug') || '') + (t.classList.contains('active') ? ' (active)' : '')));
console.log('installed themes:', themes);
await ctx.close();

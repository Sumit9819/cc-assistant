import { createRequire } from 'node:module';
import fs from 'node:fs';
const { chromium, devices } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
fs.mkdirSync('shots', { recursive: true });
// desktop: header closed + mega menu open
const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
const p = await ctx.newPage();
await p.goto('https://eroflufkin.com/', { waitUntil: 'load', timeout: 180000 });
await p.waitForTimeout(3000);
const menu = await p.evaluate(() => [...document.querySelectorAll('.elementskit-navbar-nav > li')].map(li => ({
  text: li.querySelector('a') ? li.querySelector('a').textContent.trim().slice(0, 40) : '',
  href: li.querySelector('a') ? li.querySelector('a').getAttribute('href') : '',
  hasMega: !!li.querySelector('.elementskit-megamenu-panel'),
  subItems: [...li.querySelectorAll('.elementskit-dropdown > li > a')].map(a => a.textContent.trim().slice(0, 30)),
})));
console.log('TOP-LEVEL MENU:', JSON.stringify(menu, null, 1).slice(0, 2000));
await p.screenshot({ path: 'shots/luf-desktop-header.png', clip: { x: 0, y: 0, width: 1440, height: 260 } });
const mega = await p.locator('.elementskit-navbar-nav > li').filter({ has: p.locator('.elementskit-megamenu-panel') }).first();
if (await mega.count()) { await mega.hover(); await p.waitForTimeout(1200); await p.screenshot({ path: 'shots/luf-desktop-mega-open.png', clip: { x: 0, y: 0, width: 1440, height: 900 } }); console.log('mega screenshot taken'); }
await ctx.close();
// mobile: closed + offcanvas open
const m = await b.newContext(devices['Pixel 7']);
const mp = await m.newPage();
await mp.goto('https://eroflufkin.com/', { waitUntil: 'load', timeout: 180000 });
await mp.waitForTimeout(3000);
await mp.screenshot({ path: 'shots/luf-mobile-header.png', clip: { x: 0, y: 0, width: 412, height: 300 } });
const ham = mp.locator('.elementskit-menu-hamburger:visible').first();
if (await ham.count()) { await ham.click(); await mp.waitForTimeout(1500); await mp.screenshot({ path: 'shots/luf-mobile-menu-open.png', fullPage: false }); console.log('mobile menu screenshot taken'); }
else console.log('no hamburger found');
await b.close();

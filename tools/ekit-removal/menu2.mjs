import { createRequire } from 'node:module';
const { chromium } = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json')('playwright');
const b = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await p.goto('https://eroflufkin.com/', { waitUntil: 'load', timeout: 180000 });
await p.waitForTimeout(2500);
const m = await p.evaluate(() => [...document.querySelectorAll('.elementskit-navbar-nav > li')].map(li => {
  const a = li.querySelector(':scope > a');
  return { text: a ? a.textContent.trim().slice(0, 40) : '', sub: li.querySelectorAll('.elementskit-dropdown > li').length, deeper: li.querySelectorAll('.elementskit-dropdown .elementskit-dropdown').length };
}));
console.log(JSON.stringify(m));
console.log('any mega panel element in DOM:', await p.evaluate(() => document.querySelectorAll('.elementskit-megamenu-panel').length));
console.log('nav menu widget count:', await p.evaluate(() => document.querySelectorAll('[data-widget_type^="ekit-nav-menu"]').length));
await b.close();

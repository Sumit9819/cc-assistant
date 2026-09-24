import { createRequire } from 'node:module';
const require = createRequire('C:/Users/sumit/.cc-assistant/wcag/package.json');
const { chromium } = require('playwright');
const b = await chromium.launch();
const ctx = await b.newContext({ viewport:{width:390,height:844}, isMobile:true, hasTouch:true,
  userAgent:'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'});
const p = await ctx.newPage();
await p.goto(process.argv[2]+'?ccbust='+Date.now(), {waitUntil:'networkidle', timeout:90000});
await p.waitForTimeout(4000);
const out = await p.evaluate(() => {
  const res=[];
  for (const el of document.querySelectorAll('body *')) {
    const cs=getComputedStyle(el);
    if ((cs.position==='fixed'||cs.position==='absolute') && Number(cs.zIndex)>=100 && el.getBoundingClientRect().width>150) {
      res.push({tag:el.tagName.toLowerCase(), id:el.id, cls:(el.className||'').toString().slice(0,90), z:cs.zIndex, pos:cs.position, disp:cs.display});
    }
  }
  return res.slice(0,15);
});
console.log(JSON.stringify(out,null,1));
await b.close();

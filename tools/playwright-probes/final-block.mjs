import { chromium } from 'playwright';
const UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const OUT=process.argv[2]||'.';
const b=await chromium.launch();
const ctx=await b.newContext({userAgent:UA,viewport:{width:1400,height:1250},deviceScaleFactor:1.3});
const p=await ctx.newPage();
await p.goto('https://sids-ponds.com/?cc='+Date.now(),{waitUntil:'networkidle',timeout:120000});
const acc=await p.$('.cmplz-btn.cmplz-accept,#cmplz-accept,.cmplz-accept');
if(acc){await acc.click().catch(()=>{});await p.waitForTimeout(700);}
await p.evaluate(()=>document.querySelectorAll('.cmplz-cookiebanner,#cmplz-cookiebanner-container,.intercom-lightweight-app,#et_pb_back_to_top').forEach(n=>n.remove()));

// set on the element itself so it beats Divi's inline value, exactly as the attr will
const m=await p.evaluate(()=>{
  const car=document.querySelector('.dipl_woo_products_carousel');
  const row=car.closest('.et_pb_row');
  row.style.setProperty('padding-top','32px','important');
  row.style.setProperty('padding-bottom','72px','important');
  const head=row.previousElementSibling, next=row.nextElementSibling;
  const sub=head.querySelector('p:last-of-type');
  const r=e=>e.getBoundingClientRect();
  return {padTop:getComputedStyle(row).paddingTop,padBottom:getComputedStyle(row).paddingBottom,
          subtitle_to_cards:Math.round(r(car).top-r(sub).bottom),
          cards_to_next:Math.round(r(next).top-r(car).bottom)};
});
console.log(JSON.stringify(m));
await p.waitForTimeout(500);
const row=await p.$('.et_pb_row_2');
await row.scrollIntoViewIfNeeded();
await p.waitForTimeout(700);
const hb=await row.boundingBox();
await p.screenshot({path:OUT+'/block-final.png',clip:{x:0,y:Math.max(0,hb.y-170),width:1400,height:Math.min(1150,hb.height+330)}});
console.log('shot written');
await b.close();

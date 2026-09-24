import { chromium } from 'playwright';
const UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const OUT=process.argv[2]||'.';
const b=await chromium.launch();
const ctx=await b.newContext({userAgent:UA,viewport:{width:1400,height:1200},deviceScaleFactor:1.3});
const p=await ctx.newPage();
await p.goto('https://sids-ponds.com/?cc='+Date.now(),{waitUntil:'networkidle',timeout:120000});
const acc=await p.$('.cmplz-btn.cmplz-accept,#cmplz-accept,.cmplz-accept');
if(acc){await acc.click().catch(()=>{});await p.waitForTimeout(700);}
await p.evaluate(()=>document.querySelectorAll('.cmplz-cookiebanner,#cmplz-cookiebanner-container,.intercom-lightweight-app,#et_pb_back_to_top').forEach(n=>n.remove()));

await p.addStyleTag({content:'.et_pb_row_2{padding-top:32px !important;padding-bottom:72px !important;}'});
await p.waitForTimeout(600);

const m=await p.evaluate(()=>{
  const car=document.querySelector('.dipl_woo_products_carousel');
  const row=car.closest('.et_pb_row');
  const head=row.previousElementSibling, next=row.nextElementSibling;
  const sub=head.querySelector('p:last-of-type');
  const r=e=>e.getBoundingClientRect();
  return {subtitle_to_cards:Math.round(r(car).top-r(sub).bottom),
          cards_to_next:Math.round(r(next).top-r(car).bottom)};
});
console.log(JSON.stringify(m));

// whole block: heading through the start of the next row
const head=await p.$('.et_pb_row_1');
const car=await p.$('.dipl_woo_products_carousel');
await car.scrollIntoViewIfNeeded();
await p.waitForTimeout(700);
const hb=await (await p.$('.et_pb_row_2')).boundingBox();
await p.screenshot({path:OUT+'/block-after.png',clip:{x:0,y:Math.max(0,hb.y-150),width:1400,height:Math.min(1100,hb.height+300)}});
console.log('shot written');
await b.close();

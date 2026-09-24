import { chromium } from 'playwright';
const UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const OUT=process.argv[2]||'.';
const b=await chromium.launch();
const ctx=await b.newContext({userAgent:UA,viewport:{width:1400,height:1100},deviceScaleFactor:1.5});
const p=await ctx.newPage();
await p.goto('https://sids-ponds.com/?cc='+Date.now(),{waitUntil:'networkidle',timeout:120000});
const acc=await p.$('.cmplz-btn.cmplz-accept,#cmplz-accept,.cmplz-accept');
if(acc){await acc.click().catch(()=>{});await p.waitForTimeout(700);}
await p.evaluate(()=>document.querySelectorAll('.cmplz-cookiebanner,#cmplz-cookiebanner-container,.intercom-lightweight-app').forEach(n=>n.remove()));

const m=await p.evaluate(()=>{
  const car=document.querySelector('.dipl_woo_products_carousel');
  const row=car&&car.closest('.et_pb_row');
  const next=row&&row.nextElementSibling;
  const r=(el)=>{if(!el)return null;const b=el.getBoundingClientRect();const c=getComputedStyle(el);
    return{cls:el.className.split(' ').slice(0,3).join(' '),top:Math.round(b.top+scrollY),bottom:Math.round(b.bottom+scrollY),
      h:Math.round(b.height),padTop:c.paddingTop,padBottom:c.paddingBottom,marTop:c.marginTop,marBottom:c.marginBottom};};
  const carR=r(car),rowR=r(row),nextR=r(next);
  return {carousel:carR,carouselRow:rowR,nextRow:nextR,
    gapCarouselToNextRow: nextR&&carR? nextR.top-carR.bottom : null};
});
console.log(JSON.stringify(m,null,2));

// shot the seam: bottom of the cards through the start of the next section
const car=await p.$('.dipl_woo_products_carousel');
await car.scrollIntoViewIfNeeded();
await p.waitForTimeout(900);
const box=await car.boundingBox();
await p.screenshot({path:OUT+'/gap-before.png',clip:{x:0,y:Math.max(0,box.y+box.height-260),width:1400,height:620}});
console.log('shot written');
await b.close();

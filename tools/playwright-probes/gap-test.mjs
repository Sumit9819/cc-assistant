/**
 * Try candidate bottom-padding values on the carousel row and shoot the seam,
 * so the spacing is chosen by looking at it rather than by picking a number.
 * The carousel row and the "Shop by Category" row live in the SAME Divi section,
 * so this is row-to-row rhythm, not section padding.
 */
import { chromium } from 'playwright';
const UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const OUT=process.argv[2]||'.';
const CANDIDATES=[0,48,72];   // 0 = current (28px default), then two options

const b=await chromium.launch();
const ctx=await b.newContext({userAgent:UA,viewport:{width:1400,height:1100},deviceScaleFactor:1.4});
const p=await ctx.newPage();
await p.goto('https://sids-ponds.com/?cc='+Date.now(),{waitUntil:'networkidle',timeout:120000});
const acc=await p.$('.cmplz-btn.cmplz-accept,#cmplz-accept,.cmplz-accept');
if(acc){await acc.click().catch(()=>{});await p.waitForTimeout(700);}
await p.evaluate(()=>document.querySelectorAll('.cmplz-cookiebanner,#cmplz-cookiebanner-container,.intercom-lightweight-app,#et_pb_back_to_top').forEach(n=>n.remove()));

for(const px of CANDIDATES){
  if(px>0){
    await p.addStyleTag({content:`.et_pb_row_2{padding-bottom:${px}px !important;}`});
  }
  await p.waitForTimeout(500);
  const car=await p.$('.dipl_woo_products_carousel');
  await car.scrollIntoViewIfNeeded();
  await p.waitForTimeout(700);
  const box=await car.boundingBox();
  const measured=await p.evaluate(()=>{
    const car=document.querySelector('.dipl_woo_products_carousel');
    const row=car.closest('.et_pb_row');
    const next=row.nextElementSibling;
    const cb=car.getBoundingClientRect().bottom;
    const nt=next.getBoundingClientRect().top;
    const h2=next.querySelector('h2');
    return {cardsToNextRow:Math.round(nt-cb),
            cardsToHeading:h2?Math.round(h2.getBoundingClientRect().top-cb):null};
  });
  console.log(`padding-bottom ${px===0?'CURRENT (28px)':px+'px'} -> cards->nextRow ${measured.cardsToNextRow}px, cards->"Shop by Category" ${measured.cardsToHeading}px`);
  await p.screenshot({path:`${OUT}/gap-${px}.png`,clip:{x:0,y:Math.max(0,box.y+box.height-190),width:1400,height:430}});
}
console.log('shots written');
await b.close();

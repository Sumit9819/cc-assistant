import { chromium } from 'playwright';
const UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const b=await chromium.launch();
const p=await (await b.newContext({userAgent:UA,viewport:{width:1400,height:1100}})).newPage();
await p.goto('https://sids-ponds.com/?cc='+Date.now(),{waitUntil:'networkidle',timeout:120000});
const m=await p.evaluate(()=>{
  const car=document.querySelector('.dipl_woo_products_carousel');
  const carRow=car.closest('.et_pb_row');
  const headRow=carRow.previousElementSibling;
  const nextRow=carRow.nextElementSibling;
  const sub=headRow.querySelector('p:last-of-type');
  const r=e=>e.getBoundingClientRect();
  return {
    subtitleBottom_to_cardsTop: Math.round(r(car).top - r(sub).bottom),
    cardsBottom_to_nextRowTop: Math.round(r(nextRow).top - r(car).bottom),
    headRowPadBottom: getComputedStyle(headRow).paddingBottom,
    carRowPadTop: getComputedStyle(carRow).paddingTop,
    carRowPadBottom: getComputedStyle(carRow).paddingBottom,
    nextRowPadTop: getComputedStyle(nextRow).paddingTop,
  };
});
console.log(JSON.stringify(m,null,2));
await b.close();

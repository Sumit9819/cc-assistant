import { chromium } from 'playwright';
const UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const b=await chromium.launch();
const p=await (await b.newContext({userAgent:UA,viewport:{width:1400,height:1100}})).newPage();
await p.goto('https://sids-ponds.com/?cc='+Date.now(),{waitUntil:'networkidle',timeout:120000});
const before=await p.evaluate(()=>{
  const car=document.querySelector('.dipl_woo_products_carousel');
  const row=car.closest('.et_pb_row');
  const col=car.closest('.et_pb_column');
  const cs=e=>getComputedStyle(e);
  return {rowClass:row.className, rowPadTop:cs(row).paddingTop,
          colPadTop:cs(col).paddingTop, colMarTop:cs(col).marginTop,
          carMarTop:cs(car).marginTop, carTop:Math.round(car.getBoundingClientRect().top),
          rowTop:Math.round(row.getBoundingClientRect().top)};
});
await p.addStyleTag({content:'.et_pb_row_2{padding-top:32px !important;}'});
await p.waitForTimeout(400);
const after=await p.evaluate(()=>{
  const car=document.querySelector('.dipl_woo_products_carousel');
  const row=car.closest('.et_pb_row');
  return {rowPadTop:getComputedStyle(row).paddingTop,
          carTop:Math.round(car.getBoundingClientRect().top),
          rowTop:Math.round(row.getBoundingClientRect().top)};
});
console.log('BEFORE',JSON.stringify(before,null,1));
console.log('AFTER ',JSON.stringify(after,null,1));
await b.close();

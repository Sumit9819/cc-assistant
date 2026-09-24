const fs=require('fs'),path=require('path'),os=require('os');
const {chromium}=require('C:/Users/sumit/.cc-assistant/wcag/node_modules/playwright');
const root='D:/cc-assistant/reports/site-fixes-2026-09-09',site='https://erofwhiterock.com';
const profile=path.join(os.homedir(),'.cc-assistant/browser',Buffer.from(site+'/').toString('base64url'));
const tasks=[{id:5525,url:site+'/what-to-tell-er-team/'},{id:5438,url:JSON.parse(fs.readFileSync('D:/cc-assistant/reports/site-audit-2026-09-09/inventory.json','utf8')).find(x=>x.id===5438).url},{id:971,url:site+'/services/laboratory-testing-services-in-white-rock/'},{id:5496,url:site+'/es/sala-de-emergencias-cerca-de-richardson/'}];
(async()=>{const ctx=await chromium.launchPersistentContext(profile,{headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});const results=[];try{
 for(const task of tasks){const page=await ctx.newPage();await page.setViewportSize({width:390,height:900});const r=await page.goto(task.url,{waitUntil:'domcontentloaded',timeout:45000});await page.waitForTimeout(1500);
  await page.addScriptTag({path:'C:/Users/sumit/.cc-assistant/wcag/node_modules/axe-core/axe.min.js'});
  const measure=()=>page.evaluate(async()=>{const h=document.querySelector('h1'),s=h&&getComputedStyle(h),r=h&&h.getBoundingClientRect();const axe=await window.axe.run(document,{runOnly:{type:'rule',values:['color-contrast','aria-allowed-attr']}});return {url:location.href,lang:document.documentElement.lang,canonical:document.querySelector('link[rel="canonical"]')?.href,h1:h?{text:h.textContent,font:s.fontSize,height:r.height,left:r.left,top:r.top}:null,violations:axe.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,html:n.html}))}))};});
  const before=await measure();
  if([5525,5438].includes(task.id)){await page.addStyleTag({content:'@media (max-width:767px){.elementor-element.elementor-element-ac354d7 .elementor-heading-title{font-size:32px!important;line-height:1.2!important;overflow-wrap:break-word}.elementor-element.elementor-element-a440026{padding:36px 16px 48px 16px!important;}}'});}
  if(task.id===971){await page.addStyleTag({content:'.elementor-element.elementor-element-2a6e509 a,.elementor-element.elementor-element-6fe9fbe a {color:#fff!important;text-decoration:underline}.elementor-element.elementor-element-2a6e509 a:focus-visible,.elementor-element.elementor-element-6fe9fbe a:focus-visible{outline:2px solid #fff;outline-offset:3px}'});}
  if(task.id===5496){await page.evaluate(()=>document.querySelectorAll('.elementor-accordion [role="button"][aria-selected]').forEach(n=>n.removeAttribute('aria-selected')));}
  const after=await measure();await page.screenshot({path:path.join(root,'preview-'+task.id+'.png')});results.push({id:task.id,status:r.status(),before,after,scope:'Browser-only preview; no changes saved to WordPress'});fs.writeFileSync(path.join(root,'browser-preview.json'),JSON.stringify(results,null,2));console.log(JSON.stringify({id:task.id,beforeH1:before.h1,afterH1:after.h1,beforeRules:before.violations.map(x=>x.id),afterRules:after.violations.map(x=>x.id)}));await page.close();
 }
 const page=await ctx.newPage();await page.goto(site+'/',{waitUntil:'domcontentloaded',timeout:45000});
 const paths=['/sala-de-emergencias-cerca-de-richardson/','/es/sala-de-emergencias-cerca-de-richardson/','/blog/','/blog/2/','/blog/3/','/blog/?paged=1','/blog/?page=1'];const routes=[];
 for(const p of paths){routes.push(await page.evaluate(async p=>{const r=await fetch(p,{cache:'no-store'}),h=await r.text(),d=new DOMParser().parseFromString(h,'text/html');return {requested:p,status:r.status,url:r.url,lang:d.documentElement.lang,canonical:[...d.querySelectorAll('link[rel="canonical"]')].map(n=>n.href),title:d.title};},p));}
 fs.writeFileSync(path.join(root,'route-verification.json'),JSON.stringify(routes,null,2));console.log(JSON.stringify(routes));
}finally{await ctx.close();}})().catch(e=>{console.error(e.message);process.exitCode=1});

const fs=require('fs'),path=require('path'),os=require('os');
const {chromium}=require('C:/Users/sumit/.cc-assistant/wcag/node_modules/playwright');
const root='D:/cc-assistant/reports/site-audit-2026-09-09';
const inventory=JSON.parse(fs.readFileSync(path.join(root,'inventory.json'),'utf8'));
const site='https://erofwhiterock.com';
const profile=path.join(os.homedir(),'.cc-assistant/browser',Buffer.from(site+'/').toString('base64url'));
(async()=>{
const ctx=await chromium.launchPersistentContext(profile,{headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
 const results=[];
 for(const id of [228,417,5525,2482,971,5496,4687,852]){
  const url=inventory.find(p=>p.id===id).url;
  for(const width of [390,1440]){
   const page=await ctx.newPage();await page.setViewportSize({width,height:900});
   const errors=[],failed=[],badResponses=[];
   page.on('pageerror',e=>errors.push(e.message));
   page.on('requestfailed',r=>failed.push({url:r.url(),error:r.failure()?.errorText}));
   page.on('response',r=>{if(r.status()>=400)badResponses.push({url:r.url(),status:r.status()});});
   try{
    const response=await page.goto(url,{waitUntil:'domcontentloaded',timeout:45000});
    await page.waitForTimeout(1800);
    await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=650){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,60));}window.scrollTo(0,0);});
    await page.waitForTimeout(1200);
    const observed=await page.evaluate(()=>({
     title:document.title,lang:document.documentElement.lang,viewport:innerWidth,scrollWidth:document.documentElement.scrollWidth,height:document.documentElement.scrollHeight,
     broken_images:Array.from(document.images).filter(i=>i.complete&&!i.naturalWidth&&i.getBoundingClientRect().width>0).map(i=>({src:i.currentSrc||i.src,alt:i.alt})),
     visible_h1:Array.from(document.querySelectorAll('h1')).filter(n=>n.getBoundingClientRect().width>0).map(n=>n.innerText),
     overflow:Array.from(document.querySelectorAll('body *')).filter(n=>{const r=n.getBoundingClientRect(),s=getComputedStyle(n);return r.width>0&&r.right>innerWidth+8&&r.left>=0&&s.visibility!=='hidden'&&s.display!=='none';}).slice(0,15).map(n=>({tag:n.tagName,cls:n.className,text:n.innerText?.slice(0,80)})),
     forms:Array.from(document.forms).map(f=>({fields:Array.from(f.querySelectorAll('input,textarea,select')).filter(x=>x.type!=='hidden').map(x=>({name:x.name,labels:Array.from(x.labels||[]).map(n=>n.innerText),ariaLabel:x.getAttribute('aria-label')})),submission:'not_tested'})),
     navigation:performance.getEntriesByType('navigation').map(n=>({ttfb_ms:Math.round(n.responseStart-n.requestStart),domcontentloaded_ms:Math.round(n.domContentLoadedEventEnd),transfer_bytes:n.transferSize}))
    }));
    let axe;
    try{
     const axePath='C:/Users/sumit/.cc-assistant/wcag/node_modules/axe-core/axe.min.js';
     await page.addScriptTag({path:axePath});
     axe=await page.evaluate(async()=>{const r=await window.axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}});return {violations:r.violations.map(v=>({id:v.id,impact:v.impact,help:v.help,helpUrl:v.helpUrl,nodes:v.nodes.map(n=>({target:n.target,html:n.html,failureSummary:n.failureSummary}))})),incomplete:r.incomplete.map(v=>({id:v.id,count:v.nodes.length})),passes:r.passes.length};});
    }catch(e){axe={error:e.message};}
    if(width===390||id===5525)await page.screenshot({path:path.join(root,`browser-${id}-${width}.png`),fullPage:false});
    results.push({id,url,width,status:response?.status(),captured_at:new Date().toISOString(),...observed,errors,failed,badResponses,axe});
    console.log(JSON.stringify({id,width,status:response?.status(),overflow:observed.scrollWidth>width,brokenImages:observed.broken_images.length,axeRules:axe.violations?.map(v=>v.id)}));
   }catch(e){results.push({id,url,width,error:e.message});console.log(JSON.stringify({id,width,error:e.message}));}
   await page.close();fs.writeFileSync(path.join(root,'browser-checks.json'),JSON.stringify(results,null,2));
  }
 }
}finally{await ctx.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1});

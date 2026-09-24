const fs=require('fs'),path=require('path'),os=require('os');
const {chromium}=require('C:/Users/sumit/.cc-assistant/wcag/node_modules/playwright');
const root='D:/cc-assistant/reports/site-audit-2026-09-09',site='https://erofwhiterock.com';
const read=n=>JSON.parse(fs.readFileSync(path.join(root,n),'utf8'));
const inventory=read('inventory.json');
const config=JSON.parse(fs.readFileSync('C:/Users/sumit/Local Sites/plugintesting/app/public/.mcp.json','utf8').replace(/^\uFEFF/,''));
const spec=Object.values(config.mcpServers).find(s=>String(s.env?.CC_WP_URL||'').replace(/\/$/,'')===site);
if(!spec?.env?.CC_WP_USER||!spec?.env?.CC_WP_APP_PASSWORD)throw Error('Configured site credentials unavailable');
const authorization='Basic '+Buffer.from(spec.env.CC_WP_USER+':'+spec.env.CC_WP_APP_PASSWORD).toString('base64');
const profile=path.join(spec.env.CC_BROWSER_PROFILE_ROOT||path.join(os.homedir(),'.cc-assistant/browser'),Buffer.from(site+'/').toString('base64url'));
(async()=>{
const ctx=await chromium.launchPersistentContext(profile,{headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
 const page=ctx.pages()[0]||await ctx.newPage();
 await page.goto(site+'/wp-json/',{waitUntil:'domcontentloaded',timeout:45000});
 const result=[];
 for(const source of ['broad.json','verification.json'])for(const x of read(source))if(x.name==='gsc_inspect_url'&&!x.isError){const p=inventory.find(p=>p.url===x.result.url);if(p)result.push({id:p.id,source,result:x.result});}
 const done=new Set(result.map(x=>x.id)),jobs=inventory.filter(p=>!done.has(p.id));
 let cursor=0,blocked=false;
 const persist=()=>fs.writeFileSync(path.join(root,'indexing-all.json'),JSON.stringify(result,null,2));
 async function worker(){
  while(cursor<jobs.length&&!blocked){
   const job=jobs[cursor++];
   try{
    const r=await page.evaluate(async({id,authorization})=>{
     const r=await fetch('/wp-json/cc-assistant/v1/gsc/inspect-url?post_id='+encodeURIComponent(id)+'&force_refresh=false',{method:'GET',redirect:'error',credentials:'include',headers:{Authorization:authorization,Accept:'application/json'},signal:AbortSignal.timeout(45000)});
     const body=await r.text();let data;try{data=JSON.parse(body);}catch{data={error:'non_json_response'};}
     return {status:r.status,data};
    },{id:job.id,authorization});
    result.push({id:job.id,url:job.url,captured_at:new Date().toISOString(),http_status:r.status,result:r.data});
    if(r.status===429||r.status===401||r.status===403)blocked=true;
    console.log(JSON.stringify({done:result.length,total:inventory.length,id:job.id,status:r.status,coverage:r.data.coverage_state,error:r.data.code||r.data.error}));
   }catch(e){result.push({id:job.id,url:job.url,captured_at:new Date().toISOString(),error:e.message});console.log(JSON.stringify({id:job.id,error:e.message}));}
   persist();await page.waitForTimeout(150);
  }
 }
 await Promise.all([worker(),worker(),worker()]);persist();
 const final=await page.evaluate(async authorization=>{
  const out={};for(const route of ['/health','/pending','/gsc/status']){
   const r=await fetch('/wp-json/cc-assistant/v1'+route,{method:'GET',redirect:'error',headers:{Authorization:authorization,Accept:'application/json'},signal:AbortSignal.timeout(30000)});
   out[route]={http_status:r.status,data:await r.json()};
  }return out;
 },authorization);
 fs.writeFileSync(path.join(root,'final-state.json'),JSON.stringify(final,null,2));
 console.log(JSON.stringify({completed:result.length,expected:inventory.length,blocked,pending:final['/pending']?.data?.count}));
}finally{await ctx.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1});

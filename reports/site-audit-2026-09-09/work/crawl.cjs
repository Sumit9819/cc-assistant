const fs=require('fs'),path=require('path'),os=require('os');
const {chromium}=require('C:/Users/sumit/.cc-assistant/wcag/node_modules/playwright');
const root='D:/cc-assistant/reports/site-audit-2026-09-09';
const read=n=>JSON.parse(fs.readFileSync(path.join(root,n),'utf8'));
const inventory=[...read('initial.json'),...read('broad.json')].filter(x=>x.name==='list_posts').flatMap(x=>x.result.posts||[]);
const site='https://erofwhiterock.com';
const profile=path.join(os.homedir(),'.cc-assistant/browser',Buffer.from(site+'/').toString('base64url'));
(async()=>{
fs.mkdirSync(path.join(root,'html'),{recursive:true});
fs.writeFileSync(path.join(root,'inventory.json'),JSON.stringify(inventory,null,2));
const ctx=await chromium.launchPersistentContext(profile,{headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
const page=ctx.pages()[0]||await ctx.newPage();
await page.goto(site+'/',{waitUntil:'domcontentloaded',timeout:45000});
await page.waitForTimeout(2000);
const jobs=process.argv[2]?JSON.parse(fs.readFileSync(process.argv[2],'utf8')):[{id:'robots',url:site+'/robots.txt'},{id:'sitemap-index',url:site+'/sitemap_index.xml'},...inventory];
const results=[];
for(let i=0;i<jobs.length;i++){
const job=jobs[i];
try{
 const capture=await page.evaluate(async({url,id,clean:cleanRequest})=>{
  const requested=new URL(url); if(!cleanRequest)requested.searchParams.set('cc_audit_capture',String(Date.now()));
  const r=await fetch(requested.href,{cache:'no-store',credentials:'include',signal:AbortSignal.timeout(25000)});
  const html=await r.text(); if(html.length>4500000)throw Error('Response exceeds audit limit');
  const doc=new DOMParser().parseFromString(html,'text/html');
  const clean=n=>n?n.textContent.replace(/\s+/g,' ').trim():'';
  const attr=(s,a)=>Array.from(doc.querySelectorAll(s)).map(n=>n.getAttribute(a));
  const main=doc.querySelector('main')||doc.querySelector('[data-elementor-type="wp-page"],[data-elementor-type="wp-post"]')||doc.body;
  const copy=main.cloneNode(true);copy.querySelectorAll('script,style,nav,header,footer,noscript').forEach(n=>n.remove());
  const jsonld=Array.from(doc.querySelectorAll('script[type="application/ld+json"]')).map(n=>{try{return {data:JSON.parse(n.textContent),class:n.className};}catch{return {invalid:true,excerpt:n.textContent.slice(0,300)};}});
  return {id,url,captured_at:new Date().toISOString(),status:r.status,final_url:r.url,headers:Object.fromEntries([...r.headers].filter(([k])=>['content-type','x-proxy-cache','x-proxy-cache-info','cache-control','age','x-robots-tag','last-modified'].includes(k))),html,
   title:clean(doc.querySelector('title')),lang:doc.documentElement.lang,description:attr('meta[name="description"]','content'),robots:attr('meta[name="robots"]','content'),canonical:attr('link[rel="canonical"]','href'),hreflang:Array.from(doc.querySelectorAll('link[hreflang]')).map(n=>({lang:n.hreflang,url:n.getAttribute('href')})),
   headings:Array.from(main.querySelectorAll('h1,h2,h3,h4,h5,h6')).map(n=>({tag:n.tagName,text:clean(n)})),text:clean(copy),
   links:Array.from(doc.querySelectorAll('a[href]')).map(n=>({href:n.getAttribute('href'),text:clean(n)||n.getAttribute('aria-label')||n.querySelector('img')?.getAttribute('alt')||'',main:main.contains(n)})),
   images:Array.from(doc.querySelectorAll('img')).map(n=>({src:n.getAttribute('src'),alt:n.getAttribute('alt'),width:n.getAttribute('width'),height:n.getAttribute('height'),loading:n.getAttribute('loading')})),
   forms:Array.from(doc.forms).map(n=>({method:n.method,action:n.getAttribute('action'),fields:Array.from(n.querySelectorAll('input,textarea,select')).map(x=>({tag:x.tagName,type:x.type,name:x.name,required:x.required,label:x.getAttribute('aria-label')}))})),jsonld,
   challenge:/sgcaptcha|captcha-delivery|cf-chl-/.test(html)&&html.length<20000};
 },job);
 fs.writeFileSync(path.join(root,'html',String(job.id)+'.html'),capture.html);delete capture.html;results.push(capture);
 console.log(JSON.stringify({i:i+1,total:jobs.length,id:job.id,status:capture.status,challenge:capture.challenge,title:capture.title}));
 if(job.id==='sitemap-index'){
  const xml=fs.readFileSync(path.join(root,'html','sitemap-index.html'),'utf8');
  for(const m of xml.matchAll(/<loc>(.*?)<\/loc>/g)){const u=new URL(m[1]);if(u.origin===site&&u.pathname.endsWith('.xml'))jobs.push({id:'sitemap-'+jobs.length,url:u.href});}
 }
}catch(e){results.push({id:job.id,url:job.url,error:e.message,captured_at:new Date().toISOString()});console.log(JSON.stringify({i:i+1,id:job.id,error:e.message}));}
fs.writeFileSync(path.join(root,process.argv[2]?'extra-crawl.json':'crawl.json'),JSON.stringify(results,null,2));
await page.waitForTimeout(120);
}
}finally{await ctx.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1});

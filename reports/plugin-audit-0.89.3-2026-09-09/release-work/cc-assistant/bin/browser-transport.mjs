// JSONL worker owned by one MCP process. Credentials arrive on stdin, never argv.
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import readline from 'node:readline';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
const here=path.dirname(fileURLToPath(import.meta.url));
let context, page, activeSite;

function playwright() {
 const candidates=[process.env.CC_PLAYWRIGHT_PACKAGE, path.join(here,'browser','package.json'),
  path.join(os.homedir(),'.cc-assistant','wcag','package.json')].filter(Boolean);
 for(const manifest of candidates) {
  try { return createRequire(path.resolve(manifest))('playwright'); } catch {}
 }
 throw new Error('Playwright is unavailable. Run npm install --prefix bin/browser, or set CC_PLAYWRIGHT_PACKAGE to its package.json.');
}

async function open(site) {
 if(context) {
  if(activeSite!==site) throw new Error('A browser worker cannot switch WordPress sites.');
  return;
 }
 const url=new URL(site);
 if(url.protocol!=='https:' && !['localhost','127.0.0.1','[::1]'].includes(url.hostname)) {
  throw new Error('Browser transport requires HTTPS outside loopback development.');
 }
 if(url.username||url.password) throw new Error('Credentials must not appear in the site URL.');
 activeSite=site;
 const profile=path.join(process.env.CC_BROWSER_PROFILE_ROOT||path.join(os.homedir(),'.cc-assistant','browser'),
  Buffer.from(url.origin+url.pathname).toString('base64url'));
 const chrome=process.env.CC_BROWSER_EXECUTABLE||'C:/Program Files/Google/Chrome/Application/chrome.exe';
 context=await playwright().chromium.launchPersistentContext(profile,{
  headless:process.env.CC_BROWSER_HEADLESS!=='0',
  ...(fs.existsSync(chrome)?{executablePath:chrome}:{channel:'chrome'}),
 });
 page=context.pages()[0]||await context.newPage();
 await page.goto(site+'/wp-json/',{waitUntil:'domcontentloaded',timeout:45000});
}

async function call(req) {
 const site=String(req.site||'').replace(/\/$/,'');
 await open(site);
 const origin=new URL(site).origin;
 const url=new URL(req.url);
 if(url.origin!==origin||!url.pathname.startsWith(new URL(site+'/wp-json/cc-assistant/v1/').pathname)) {
  return {error:'browser_scope',message:'Browser transport only accepts this site’s CC Assistant routes.'};
 }
 // Establish readiness using a read-only request. Retry navigation/challenge
 // transitions here; never replay a mutating request after fetch starts.
 let ready=false;
 for(let attempt=0;attempt<24;attempt++) {
  if(new URL(page.url()).origin!==origin) return {error:'browser_redirect',message:'Unexpected site redirect; configure the canonical HTTPS WordPress URL.'};
  try {
   ready=await page.evaluate(async root=>{
    const r=await fetch(root,{redirect:'error',headers:{Accept:'application/json'},signal:AbortSignal.timeout(8000)});
    if(!r.ok) return false;
    try { const d=await r.json(); return typeof d==='object'&&d!==null; } catch { return false; }
   },site+'/wp-json/');
  } catch {}
  if(ready) break;
  await page.waitForTimeout(1000);
 }
 if(!ready) return {error:'browser_challenge',message:'SiteGround still requires a challenge. Use a visible browser session (CC_BROWSER_HEADLESS=0) or ask SiteGround support to review the operator IP; no mutation was sent.'};
 const auth=Buffer.from(req.username+':'+req.password).toString('base64');
 try {
  return await page.evaluate(async({url,method,body,auth})=>{
   // No admin login or WP nonce: this dedicated profile authenticates only
   // with the configured Application Password. Cookies retain SG clearance.
   const opts={method,redirect:'error',credentials:'include',signal:AbortSignal.timeout(30000),
    headers:{Authorization:'Basic '+auth,Accept:'application/json','Content-Type':'application/json'}};
   if(body!==null&&method!=='GET'&&method!=='HEAD') opts.body=JSON.stringify(body);
   const response=await fetch(url,opts);
   const text=await response.text();
   let data; try { data=JSON.parse(text); } catch {
    return {error:'browser_non_json',status:response.status,message:'The browser received a non-JSON response. No automatic write retry was attempted.'};
   }
   if(!response.ok) return {error:'http_error',status:response.status,body:JSON.stringify(data)};
   return data;
  },{url:req.url,method:req.method||'GET',body:req.body??null,auth});
 } catch {
  return {error:'browser_request_interrupted',message:'The browser request was interrupted. Its outcome may be unknown; inspect the pending inbox before retrying a write.'};
 }
}

const input=readline.createInterface({input:process.stdin,crlfDelay:Infinity});
try {
 for await(const line of input) {
  if(!line.trim()) continue;
  let result;
  try {result=await call(JSON.parse(line));}
  catch(e) {
   // Browser launch errors may include profile paths, never request payloads.
   result={error:'browser_transport_error',message:String(e.message).slice(0,600)};
  }
  process.stdout.write(JSON.stringify(result)+'\n');
 }
} finally { if(context) await context.close(); }

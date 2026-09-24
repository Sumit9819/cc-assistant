"""Queue reviewed plans only after an exact source reread and a fresh server audit."""
import json,os,sys,subprocess,threading,queue,time
from pathlib import Path
root=Path(__file__).resolve().parent
expected=json.loads((root/'source-posts.json').read_text(encoding='utf-8'))
selected=None
for file in [Path('C:/Users/sumit/Local Sites/plugintesting/app/public/.mcp.json'),Path('D:/cc-assistant/.mcp.json')]:
 if not file.exists():continue
 for spec in json.loads(file.read_text(encoding='utf-8-sig')).get('mcpServers',{}).values():
  if str(spec.get('env',{}).get('CC_WP_URL','')).rstrip('/')=='https://erofwhiterock.com':selected=(file,spec);break
 if selected:break
assert selected,'Exact site configuration unavailable'
config,spec=selected;env=os.environ.copy();env.update({k:str(v) for k,v in spec.get('env',{}).items()});env['CC_MCP_TRANSPORT']='browser';env['CC_PROJECT_DIR']=str(config.parent)
command=[spec['command']]+spec.get('args',[])
assert any(str(a).replace('\\','/').endswith('/bin/mcp-server.php') for a in command)
proc=subprocess.Popen(command,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.DEVNULL,text=True,encoding='utf-8',env=env,cwd=config.parent,bufsize=1)
incoming=queue.Queue();seq=0;results=[];checked={};identity=0
def reader():
 for line in proc.stdout:
  try: incoming.put(json.loads(line))
  except ValueError:pass
 incoming.put(None)
threading.Thread(target=reader,daemon=True).start()
output=Path('D:/cc-assistant/reports/site-fixes-2026-09-09')/('safe-'+Path(sys.argv[1]).stem+'-results.json')
def call(method,params):
 global seq
 seq+=1;proc.stdin.write(json.dumps({'jsonrpc':'2.0','id':seq,'method':method,'params':params},ensure_ascii=False)+'\n');proc.stdin.flush()
 deadline=time.monotonic()+150
 while True:
  obj=incoming.get(timeout=max(1,deadline-time.monotonic()))
  if obj is None:raise RuntimeError('Bridge exited before response')
  if obj.get('id')!=seq:continue
  raw=obj.get('result',obj.get('error'));data=[]
  for c in (raw or {}).get('content',[]):
   if c.get('type')=='text':
    try:data.append(json.loads(c['text']))
    except ValueError:data.append({'text':c['text']})
  d=data[0] if len(data)==1 else data
  item={'name':params.get('name',method),'arguments':params.get('arguments',{}),'isError':bool((raw or {}).get('isError') or obj.get('error')),'result':d}
  results.append(item);output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
  return item
def tool(name,arguments):return call('tools/call',{'name':name,'arguments':arguments})
try:
 call('initialize',{'protocolVersion':'2024-11-05','capabilities':{},'clientInfo':{'name':'cc-reviewed-site-fixes','version':'1'}})
 requests=json.loads((root/sys.argv[1]).read_text(encoding='utf-8'))
 for i,req in enumerate(requests):
  if time.monotonic()-identity>420:
   ident=tool('whoami',{});assert not ident['isError'];identity=time.monotonic()
  pid=req['arguments'].get('post_id')
  if pid and time.monotonic()-checked.get(pid,-9999)>420:
   fresh=tool('get_post',{'id':pid,'slim':False});assert not fresh['isError'],fresh['result']
   data=fresh['result'];old=expected.get(str(pid));assert old,'Missing reviewed source: '+str(pid)
   for k in ['elementor_data','content','title','author_id']:
    assert data.get(k)==old.get(k),'Source changed before queue: '+str(pid)+' '+k
   if data['status']=='publish' and data['type']!='elementor_library':
    audit=tool('verified_page_audit',{'post_id':pid});assert not audit['isError'] and audit['result'].get('usable'),'Fresh server evidence unavailable for '+str(pid)
   checked[pid]=time.monotonic()
  out=tool(req['name'],req['arguments'])
  d=out['result'];print(json.dumps({'i':i+1,'total':len(requests),'post_id':pid,'name':req['name'],'isError':out['isError'],'pending_id':d.get('pending_id') if isinstance(d,dict) else None,'error':d if out['isError'] else None},ensure_ascii=False),flush=True)
finally:
 proc.stdin.close()
 try:proc.wait(timeout=25)
 except subprocess.TimeoutExpired:proc.terminate()

"""Invoke only the selected site's configured bridge without printing credentials."""
import argparse,json,os,subprocess
from pathlib import Path
ap=argparse.ArgumentParser();ap.add_argument('--requests',required=True);ap.add_argument('--output',required=True);ap.add_argument('--bridge');ap.add_argument('--transport',choices=['http','browser']);args=ap.parse_args()
selected=None
for config in [Path('C:/Users/sumit/Local Sites/plugintesting/app/public/.mcp.json'),Path('D:/cc-assistant/.mcp.json')]:
    if not config.is_file():continue
    data=json.loads(config.read_text(encoding='utf-8-sig'))
    for name,spec in data.get('mcpServers',{}).items():
        if str(spec.get('env',{}).get('CC_WP_URL','')).rstrip('/')=='https://erofwhiterock.com':selected=(config,spec);break
    if selected:break
if not selected:raise SystemExit('Matching configured site not found; no credential values printed.')
config,spec=selected;env=os.environ.copy();env.update({k:str(v) for k,v in spec.get('env',{}).items()});env['CC_PROJECT_DIR']=str(config.parent)
if args.transport:env['CC_MCP_TRANSPORT']=args.transport
requests=json.loads(Path(args.requests).read_text(encoding='utf-8'))
reqs=[{'jsonrpc':'2.0','id':1,'method':'initialize','params':{'protocolVersion':'2024-11-05','capabilities':{},'clientInfo':{'name':'cc-release-verification','version':'1'}}}]
for i,req in enumerate(requests,2):reqs.append({'jsonrpc':'2.0','id':i,'method':'tools/call','params':req})
command=[spec['command']]+list(spec.get('args',[]))
if args.bridge:
    command=[str(Path(args.bridge).resolve()) if str(a).replace('\\','/').endswith('/bin/mcp-server.php') else a for a in command]
if not any(str(a).replace('\\','/').endswith('/bin/mcp-server.php') for a in command):raise SystemExit('Configured command is not the expected bridge.')
run=subprocess.run(command,input='\n'.join(json.dumps(x,ensure_ascii=False) for x in reqs)+'\n',capture_output=True,text=True,encoding='utf-8',errors='replace',env=env,cwd=config.parent,timeout=240)
responses=[]
for line in run.stdout.splitlines():
    try:
        item=json.loads(line)
        if item.get('id')==1:responses.append({'name':'initialize','result':item.get('result',item.get('error'))});continue
        name=requests[int(item['id'])-2]['name'];result=item.get('result',item.get('error'));payload=[]
        for content in (result or {}).get('content',[]):
            if content.get('type')=='text':
                try:payload.append(json.loads(content['text']))
                except ValueError:payload.append({'text':content['text']})
        responses.append({'name':name,'isError':(result or {}).get('isError',False),'result':payload[0] if len(payload)==1 else payload})
    except (ValueError,KeyError,TypeError):pass
Path(args.output).parent.mkdir(parents=True,exist_ok=True)
Path(args.output).write_text(json.dumps(responses,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'exit_code':run.returncode,'responses':len(responses),'calls':[{'name':x['name'],'isError':x.get('isError',False),'keys':list(x['result'].keys()) if isinstance(x['result'],dict) else []} for x in responses],'stderr_present':bool(run.stderr)}))
if run.returncode or len(responses)!=len(reqs):raise SystemExit(1)

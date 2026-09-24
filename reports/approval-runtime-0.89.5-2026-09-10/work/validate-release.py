import json,os,subprocess,zipfile,hashlib
from pathlib import Path
root=Path(__file__).resolve().parent;stage=root/'cc-assistant'
tests=json.loads((root/'test-results.json').read_text(encoding='utf-8'))
assert not tests['syntax'] and len(tests['tests'])==60 and all(t['exit_code']==0 and 'Warning:' not in t['output'] for t in tests['tests'])
php=['D:/cc-assistant/php/php.exe','-d','extension_dir=D:/cc-assistant/php/ext','-d','extension=sqlite3','-d','extension=mbstring']
env=os.environ.copy();env['CC_TEST_WORDPRESS_ROOT']='C:/Users/sumit/Local Sites/plugintesting/app/public';env['CC_TEST_PHP']=php[0]
r=subprocess.run(php+[str(root/'incident-payload-replay.php')],env=env,capture_output=True,text=True,encoding='utf-8',timeout=120)
(root/'incident-payload-replay.log').write_text(r.stdout+r.stderr,encoding='utf-8');assert r.returncode==0 and 'Warning:' not in r.stdout,r.stdout+r.stderr
print(r.stdout.splitlines()[-1],flush=True)
r=subprocess.run(['python','-X','utf8','build-zip.py'],cwd=stage,env=env,capture_output=True,text=True,encoding='utf-8',timeout=120);assert r.returncode==0,r.stdout+r.stderr
print(r.stdout.strip(),flush=True)
package=root/'cc-assistant-0.89.5.zip'
with zipfile.ZipFile(package) as z:
 assert z.testzip() is None
 names=z.namelist()
 for n in names:
  p=Path(n);assert p.parts[0]=='cc-assistant' and '..' not in p.parts and 'tests' not in p.parts and '\\' not in n
  assert z.read(n)==(stage/Path(*p.parts[1:])).read_bytes()
  assert not n.endswith(('.log','.zip','.sqlite')) and p.name!='.mcp.json'
env={k:v for k,v in os.environ.items() if not k.startswith('CC_WP_')};env.update({'CC_WP_URL':'https://example.invalid','CC_WP_USER':'fixture','CC_WP_APP_PASSWORD':'fixture','CC_PROJECT_DIR':str(root)})
requests=[{'jsonrpc':'2.0','id':1,'method':'initialize','params':{'protocolVersion':'2024-11-05','capabilities':{},'clientInfo':{'name':'release-smoke','version':'1'}}},{'jsonrpc':'2.0','id':2,'method':'tools/list','params':{}}]
r=subprocess.run(php+[str(stage/'bin/mcp-server.php')],input='\n'.join(json.dumps(x) for x in requests)+'\n',cwd=root,env=env,capture_output=True,text=True,encoding='utf-8',timeout=30)
assert r.returncode==0,r.stderr
responses=[json.loads(line) for line in r.stdout.splitlines() if line.startswith('{')];assert len(responses)==2 and all('result' in x for x in responses)
assert responses[0]['result']['serverInfo']['version']=='0.89.5'
tools=responses[1]['result']['tools'];assert len(tools)==177
for t in tools:assert t['inputSchema'].get('properties') is None or isinstance(t['inputSchema']['properties'],dict)
summary={'version':'0.89.5','sha256':hashlib.sha256(package.read_bytes()).hexdigest(),'shipping_files':len(names),'php_syntax_files':tests['syntax_files'],'regression_test_files':60,'runtime_test_assertions':67,'incident_payload_cases':6,'mcp_tools':len(tools),'crc_valid':True,'zip_matches_source':True,'production_installed':False,'live_remaining_ids':[1814,1815,1816,1818,1819,1820]}
(root/'release-validation.json').write_text(json.dumps(summary,indent=2),encoding='utf-8');print(json.dumps(summary,indent=2))

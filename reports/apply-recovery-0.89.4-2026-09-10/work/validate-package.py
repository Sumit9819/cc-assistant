import os,subprocess,json,zipfile,hashlib
from pathlib import Path
root=Path(__file__).resolve().parent;stage=root/'cc-assistant'
results=json.loads((root/'test-results.json').read_text(encoding='utf-8'))
assert not results['syntax'] and len(results['tests'])==59 and all(x['exit_code']==0 for x in results['tests'])
env=os.environ.copy();env['CC_TEST_PHP']='D:/cc-assistant/php/php.exe'
build=subprocess.run(['python','-X','utf8','build-zip.py'],cwd=stage,env=env,capture_output=True,text=True,encoding='utf-8',timeout=120)
assert build.returncode==0,build.stdout+build.stderr
print(build.stdout.strip(),flush=True)
package=root/'cc-assistant-0.89.4.zip'
with zipfile.ZipFile(package) as z:
    assert z.testzip() is None
    files=z.namelist()
    assert 'cc-assistant/includes/class-approval-continuation.php' in files
    for name in files:
        p=Path(name); assert p.parts[0]=='cc-assistant' and '..' not in p.parts and 'tests' not in p.parts and '\\' not in name
        assert z.read(name)==(stage/Path(*p.parts[1:])).read_bytes()
        assert not name.endswith(('.zip','.sqlite','.log'))
    assert 'cc-assistant/.mcp.json' not in files
env={k:v for k,v in os.environ.items() if not k.startswith('CC_WP_')}
env.update({'CC_WP_URL':'https://example.invalid','CC_WP_USER':'fixture','CC_WP_APP_PASSWORD':'fixture','CC_PROJECT_DIR':str(root)})
requests=[{'jsonrpc':'2.0','id':1,'method':'initialize','params':{'protocolVersion':'2024-11-05','capabilities':{},'clientInfo':{'name':'release-smoke','version':'1'}}},{'jsonrpc':'2.0','id':2,'method':'tools/list','params':{}}]
php=['D:/cc-assistant/php/php.exe','-d','extension_dir=D:/cc-assistant/php/ext','-d','extension=sqlite3','-d','extension=mbstring']
r=subprocess.run(php+[str(stage/'bin/mcp-server.php')],input='\n'.join(json.dumps(x) for x in requests)+'\n',cwd=root,env=env,capture_output=True,text=True,encoding='utf-8',timeout=30)
assert r.returncode==0,r.stderr
responses=[json.loads(line) for line in r.stdout.splitlines() if line.startswith('{')]
assert len(responses)==2 and all('result' in x for x in responses)
assert responses[0]['result']['serverInfo']['version']=='0.89.4'
tools=responses[1]['result']['tools'];assert len(tools)==177
for tool in tools:
    properties=tool['inputSchema'].get('properties')
    assert properties is None or isinstance(properties,dict),tool['name']
for script in ['integrity-compat-test.php','live-replay-test.php']:
    t=subprocess.run(php+[str(root/script)],capture_output=True,text=True,encoding='utf-8',timeout=45)
    (root/(script+'.log')).write_text(t.stdout+t.stderr,encoding='utf-8')
    assert t.returncode==0,t.stdout+t.stderr
summary={'version':'0.89.4','zip':str(package),'files':len(files),'sha256':hashlib.sha256(package.read_bytes()).hexdigest(),'crc_valid':True,'zip_matches_stage':True,'mcp_tools':len(tools),'mcp_version':'0.89.4','php_regression_files':len(results['tests']),'syntax_files':results['syntax_files'],'live_payload_replay_cases':28,'fresh_targets_compared':18,'production_apply_performed':False}
(root/'release-validation.json').write_text(json.dumps(summary,indent=2),encoding='utf-8')
print(json.dumps(summary,indent=2),flush=True)

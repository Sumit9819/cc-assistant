import hashlib,json,subprocess,os,zipfile
from pathlib import Path
root=Path(__file__).parent;report=Path('D:/cc-assistant/reports/implementation-0.88.0-2026-09-09');source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant');bridge=Path('C:/Users/sumit/Local Sites/plugintesting/app/public/wp-content/plugins/cc-assistant/bin')
changes=json.loads((report/'changed-files.json').read_text(encoding='utf-8'));sha=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
for c in changes:
 assert sha(source/c['path'])==c['after_sha256']
 if c['path'].startswith('bin/'):assert sha(bridge/c['path'][4:])==c['after_sha256']
package=source.parent/'cc-assistant-0.88.0.zip'
with zipfile.ZipFile(package) as z:
 assert z.testzip() is None
 for name in z.namelist():assert hashlib.sha256(z.read(name)).hexdigest()==sha(source/name.removeprefix('cc-assistant/'))
env=os.environ.copy();env.update(CC_WP_URL='https://fixture.invalid',CC_WP_USER='fixture',CC_WP_APP_PASSWORD='fixture-not-a-real-credential')
req='\n'.join(json.dumps(r) for r in [{'jsonrpc':'2.0','id':1,'method':'initialize','params':{'protocolVersion':'2024-11-05','capabilities':{}}},{'jsonrpc':'2.0','id':2,'method':'tools/list'}])+'\n'
r=subprocess.run(['D:/cc-assistant/php/php.exe',str(bridge/'mcp-server.php')],input=req,capture_output=True,text=True,encoding='utf-8',env=env,timeout=20);assert r.returncode==0 and not r.stderr
responses=[json.loads(l) for l in r.stdout.splitlines()];assert responses[0]['result']['serverInfo']['version']=='0.88.0';assert len(responses[1]['result']['tools'])==174
result={'installed_source_files_verified':len(changes),'all_packaged_bytes_match_source':True,'desktop_bridge_initialize':'0.88.0','tool_count':174,'archive_sha256':sha(package),'live_site_updated':False}
(report/'installed-verification.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))

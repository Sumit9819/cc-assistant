import json,os,subprocess
from pathlib import Path
root=Path(__file__).parent
project=root.resolve().parents[1]
env=os.environ.copy()
env.update(CC_WP_URL='https://fixture.invalid',CC_WP_USER='fixture',CC_WP_APP_PASSWORD='fixture-not-a-credential',PYTHONDONTWRITEBYTECODE='1')
calls=[{'jsonrpc':'2.0','id':1,'method':'initialize','params':{'protocolVersion':'2024-11-05','capabilities':{},'clientInfo':{'name':'installed-audit-check','version':'1'}}},{'jsonrpc':'2.0','id':2,'method':'tools/list','params':{}}]
run=subprocess.run(['D:/cc-assistant/php/php.exe',str(project/'wp-content/plugins/cc-assistant/bin/mcp-server.php')],input='\n'.join(json.dumps(x) for x in calls)+'\n',env=env,capture_output=True,text=True,encoding='utf-8',timeout=20)
assert run.returncode==0 and not run.stderr,(run.returncode,run.stderr)
out=[json.loads(line) for line in run.stdout.splitlines()]
assert out[0]['result']['serverInfo']['version']=='0.89.3'
assert len(out[1]['result']['tools'])==177
hook=subprocess.run(['python',str(root/'cc-assistant/tests/hook-gate-test.py'),str(project/'.claude/hooks/cc_gate.py')],env=env,capture_output=True,text=True,timeout=20)
assert hook.returncode==0,hook.stdout+hook.stderr
result={'installed_bridge_version':'0.89.3','installed_bridge_tools':177,'installed_hook_regressions':'passed','live_requests':False}
(root/'installed-verification.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps(result))

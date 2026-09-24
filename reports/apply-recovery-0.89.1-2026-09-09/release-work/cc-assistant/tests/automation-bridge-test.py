"""Exercise actual stdio authorization and one-attempt guard, with no network calls."""
import json,os,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1]
env=os.environ.copy()
env.update(CC_WP_URL='https://fixture.invalid',CC_WP_USER='fixture',CC_WP_APP_PASSWORD='fixture-not-a-credential',CC_AUTOMATION_ALLOWED_TOOLS=json.dumps(['draft_create_post']))
calls=[('get_site_memory',{}),('draft_create_post',{'post_type':'page'}),
       ('draft_create_post',{'post_type':'post','workflow_id':'workflow-'+'a'*32}),
       ('draft_create_post',{'post_type':'post','workflow_id':'workflow-'+'a'*32})]
requests=[{'jsonrpc':'2.0','id':i,'method':'tools/call','params':{'name':n,'arguments':a}} for i,(n,a) in enumerate(calls,1)]
r=subprocess.run([os.environ.get('CC_TEST_PHP','php'),str(root/'bin/mcp-server.php')],input='\n'.join(json.dumps(x) for x in requests)+'\n',env=env,capture_output=True,text=True,encoding='utf-8',timeout=15)
assert r.returncode==0,r.stderr
out=[json.loads(line)['result'] for line in r.stdout.splitlines()]
assert len(out)==4 and all(v.get('isError') for v in out)
texts=[v['content'][0]['text'] for v in out]
assert 'automation_tool_denied' in texts[0]
assert 'automation_create_limit' in texts[1]
assert 'Missing required argument' in texts[2]
assert 'automation_create_limit' in texts[3]
print('PASS actual bridge denies unlisted tools, non-post creation and repeat attempts without contacting WordPress.')

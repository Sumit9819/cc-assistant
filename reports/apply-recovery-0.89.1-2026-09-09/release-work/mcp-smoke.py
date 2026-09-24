import json, os, subprocess
from pathlib import Path
root=Path(__file__).parent
plugin=root/'cc-assistant'
env=os.environ.copy()
env.update(CC_WP_URL='https://fixture.invalid',CC_WP_USER='fixture',CC_WP_APP_PASSWORD='fixture-not-a-real-credential',CC_WAREHOUSE_DIR=str(root/'smoke-warehouse'))
requests=[{'jsonrpc':'2.0','id':1,'method':'initialize','params':{'protocolVersion':'2024-11-05','capabilities':{},'clientInfo':{'name':'release-fixture','version':'1'}}},{'jsonrpc':'2.0','id':2,'method':'tools/list','params':{}},{'jsonrpc':'2.0','id':3,'method':'tools/call','params':{'name':'operator_brain_push','arguments':{'paths':None}}}]
r=subprocess.run(['D:/cc-assistant/php/php.exe',str(plugin/'bin/mcp-server.php')],input='\n'.join(json.dumps(v) for v in requests)+'\n',capture_output=True,text=True,encoding='utf-8',errors='replace',env=env,timeout=20)
assert r.returncode==0,(r.returncode,r.stderr)
responses=[json.loads(line) for line in r.stdout.splitlines() if line.strip()]
tools=next(v['result']['tools'] for v in responses if v.get('id')==2)
by_name={v['name']:v for v in tools}
required=['get_content_scope','plan_blog_content','content_research','content_decision','content_decision_history','discover_content_scope','manage_content_scope','content_workflow','inspect_plugin_capability','verify_content_workflow']
assert all(name in by_name for name in required)
assert len(tools)==len(by_name),'Duplicate tool names'
assert len(tools)==176
assert all(isinstance(t['inputSchema'].get('properties',{}),dict) for t in tools), 'MCP properties must be an object'
assert 'workflow_id' in by_name['draft_create_post']['inputSchema']['properties']
assert not by_name['plan_blog_content']['annotations']['readOnlyHint']
assert 'context_hash' in by_name['manage_content_scope']['inputSchema']['required']
assert 'plan_blog_content' in by_name['gsc_opportunities']['description']
assert 'no opportunity' in by_name['topical_authority']['description']
assert 'first-party' not in by_name['external_originality_check']['description'] or 'verified' in by_name['external_originality_check']['description']
version=responses[0]['result']['serverInfo']['version']
assert version=='0.89.1',version
bad_scope=next(v['result'] for v in responses if v.get('id')==3)
assert bad_scope['isError'] and 'scope_paths_required' in bad_scope['content'][0]['text']
assert 'topic' in by_name['content_research']['inputSchema']['properties']
assert 'paths' in by_name['operator_brain_push']['inputSchema']['properties']
result={'version':version,'tool_count':len(tools),'new_tools':required,'duplicate_names':False,'stderr':r.stderr,'scope':'Real stdio initialize/tools-list; fixture credentials, no network or live site calls.'}
(root/'mcp-smoke-results.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps(result))


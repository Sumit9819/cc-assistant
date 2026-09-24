"""Evidence gates with isolated state and real MCP-shaped responses."""
import importlib.util, json, os, sys, tempfile
from pathlib import Path
from datetime import datetime, timezone
sys.dont_write_bytecode = True
hook = Path(sys.argv[1]) if len(sys.argv)>1 else Path(__file__).resolve().parents[4]/'.claude/hooks/cc_gate.py'
spec = importlib.util.spec_from_file_location('gate', hook)
gate = importlib.util.module_from_spec(spec); spec.loader.exec_module(gate)
with tempfile.TemporaryDirectory(prefix='cc-hook-test-') as root:
    gate.state_dir = lambda session: root
    server = 'cc-assistant-test'
    def payload(tool, data=None, args=None):
        return {'session_id':'fixture','tool_name':f'mcp__{server}__{tool}','tool_input':args or {'id':1},'tool_response':{'content':[{'type':'text','text':json.dumps(data)}]}}
    def denied(p):
        return gate.mode_pre(p).get('hookSpecificOutput',{}).get('permissionDecision')=='deny'
    mutation = payload('draft_update_post_content')
    for failure in [{'error':'captcha'},{'ok':False},{},None,'Everything looks fine!']:
        gate.mode_post(payload('whoami',failure)); assert denied(mutation)
    gate.mode_post(payload('whoami',{'data':{'plugin_version':'0.83.0'}}))
    gate.mode_post(payload('render_probe',{'http_code':200,'post_id':1})); assert denied(mutation)
    source = {'post_id':1,'http_code':200,'body_sha1':'fixture','cache_state':'miss','captured_at_utc':datetime.now(timezone.utc).isoformat()}
    for wrong in [{'post_id':2},{'cache_state':'hit'},{'captured_at_utc':'2020-01-01 00:00:00'}]:
        gate.mode_post(payload('verified_page_audit',{'usable':True,'source':dict(source,**wrong)})); assert denied(mutation)
    audit = {'usable':True,'source':source}
    gate.mode_post(payload('verified_page_audit',audit)); assert not denied(mutation)
    gate.mode_post(payload('verified_page_audit',{'error':'offline'})); assert denied(mutation)
    gate.mode_post(payload('verified_page_audit',audit))
    marker=Path(root)/(server+'.probe.1'); os.utime(marker,(1,1)); assert denied(mutation)
    gate.mode_post(payload('get_post',{'id':1,'post_status':'draft'})); assert not denied(mutation)
    settings=payload('draft_update_plugin_setting',args={'option_name':'acme_settings','value':'on'})
    assert denied(settings)
    gate.mode_post(payload('get_plugin_settings',{'installed':True,'active':True,'options':[{'option':'wrong_settings'}]})); assert denied(settings)
    gate.mode_post(payload('get_plugin_settings',{'installed':True,'active':True,'options':[{'option':'acme_settings'}]})); assert not denied(settings)
    gate.mode_post(payload('get_plugin_settings',{'error':'offline'})); assert denied(settings)
    gate.mode_post(payload('draft_update_post_content',{'error':'refused'})); assert not list(Path(root).glob('*.mutated'))
    gate.mode_post(payload('draft_update_post_content',{'dry_run':True},{'id':1,'dry_run':True})); assert not list(Path(root).glob('*.mutated'))
    gate.mode_post(payload('whoami',{'plugin_version':'0.82.0'})); marker.unlink(missing_ok=True)
    legacy={'post_id':1,'http_code':200,'refreshed':True,'captured_at':source['captured_at_utc'],'cache_state':'miss'}
    gate.mode_post(payload('page_facts',legacy)); assert not denied(mutation)
    gate.mode_post(payload('page_facts',dict(legacy,stale=True,refresh_error='offline'))); assert denied(mutation)
    print('PASS: versions, TTL, failed/stale reads, target binding, drafts, option discovery and dry runs.')

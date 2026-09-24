"""Install reviewed local source/bridge; never installs to the live WordPress site."""
import hashlib,json,os,shutil,zipfile
from pathlib import Path
root=Path(__file__).parent
stage=root/'cc-assistant'
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
active=Path('C:/Users/sumit/Local Sites/plugintesting/app/public/wp-content/plugins/cc-assistant/bin')
report=Path('D:/cc-assistant/reports/implementation-0.89.0-2026-09-09')
assert stage.name==source.name=='cc-assistant'
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def keep(p,base):
    rel=p.relative_to(base)
    return p.is_file() and not any(s in ['__pycache__','node_modules','.git'] or s.startswith('.tmp') for s in rel.parts) and p.suffix not in ['.sqlite','.sqlite3','.db','.log','.previous','.pyc']
baseline=json.loads((root/'baseline-hashes.json').read_text(encoding='utf-8'))
conflicts=[s for s,h in baseline.items() if not (source/s).is_file() or digest(source/s)!=h]
if conflicts:raise SystemExit('Source changed since staging; reconcile before install: '+json.dumps(conflicts))
tests=json.loads((root/'test-results.json').read_text(encoding='utf-8'))
assert not tests['syntax'] and tests['tests'] and all(x['exit_code']==0 for x in tests['tests'])
package=root/'cc-assistant-0.89.0.zip'
required=['cc-assistant.php','bin/mcp-server.php','bin/browser-transport.php','bin/browser-transport.mjs','bin/browser/package.json','bin/automation-runner.py','includes/class-content-authors.php','includes/class-external-research.php','includes/class-siteground-adapter.php']
with zipfile.ZipFile(package) as z:
    assert z.testzip() is None
    names=z.namelist()
    assert all(n.startswith('cc-assistant/') and '\\' not in n and '/tests/' not in n and '/node_modules/' not in n for n in names)
    for name in required:assert z.read('cc-assistant/'+name)==(stage/name).read_bytes(),name
report.mkdir(parents=True,exist_ok=True)
for name,base in [('source-before',source),('active-bridge-before',active)]:
    dest=report/(name+'.zip')
    assert not dest.exists(),'Do not overwrite an existing rollback backup.'
    with zipfile.ZipFile(dest,'w',zipfile.ZIP_DEFLATED) as z:
        for p in base.rglob('*'):
            if keep(p,base):z.write(p,p.relative_to(base).as_posix())
changed=[]
for p in stage.rglob('*'):
    if not keep(p,stage):continue
    rel=p.relative_to(stage); target=source/rel
    if target.is_file() and digest(target)==digest(p):continue
    before=digest(target) if target.exists() else None
    target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(p,target)
    assert digest(p)==digest(target)
    changed.append({'file':rel.as_posix(),'before':before,'after':digest(target)})
bridge=[]
for p in (stage/'bin').rglob('*'):
    if not keep(p,stage/'bin'):continue
    rel=p.relative_to(stage/'bin');target=active/rel
    before=digest(target) if target.is_file() else None
    target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(p,target)
    assert digest(p)==digest(target)
    if before!=digest(target):bridge.append(rel.as_posix())
for config in [Path('C:/Users/sumit/Local Sites/plugintesting/app/public/.mcp.json'),Path('D:/cc-assistant/.mcp.json')]:
    if not config.is_file():continue
    data=json.loads(config.read_text(encoding='utf-8-sig'));changed_config=False
    for name,spec in data.get('mcpServers',{}).items():
        if str(spec.get('env',{}).get('CC_WP_URL','')).rstrip('/')!='https://erofwhiterock.com':continue
        if spec['env'].get('CC_MCP_TRANSPORT')!='browser':spec['env']['CC_MCP_TRANSPORT']='browser';changed_config=True
    if changed_config:
        tag='d-project' if config.drive.lower()=='d:' else 'c-project'
        shutil.copy2(config,report/(tag+'-mcp.before.json'))
        config.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
private=Path('C:/Users/sumit/.cc-assistant/automation/erofwhiterock')
private.mkdir(parents=True,exist_ok=True)
config=Path('C:/Users/sumit/Local Sites/plugintesting/app/public/.mcp.json')
servers=json.loads(config.read_text(encoding='utf-8'))['mcpServers']
selected=next(n for n,s in servers.items() if s.get('env',{}).get('CC_WP_URL','').rstrip('/')=='https://erofwhiterock.com')
runner_config={'enabled':False,'state_dir':str(private/'jobs'),'mcp_config':str(config),'wordpress_server':selected,'ubersuggest_config':'C:/Users/sumit/.claude.json',
 'claude_command':['C:/Users/sumit/AppData/Roaming/npm/node_modules/@anthropic-ai/claude-code/bin/claude.exe'],'max_run_usd':1.0,'max_job_usd':3.0,'timeout_seconds':300}
target=private/'config.json'
if target.exists():raise SystemExit('Existing automation configuration needs review; it was not overwritten.')
target.write_text(json.dumps(runner_config,indent=2),encoding='utf-8')
release=source.parent/package.name;shutil.copy2(package,release)
manifest={'version':'0.89.0','source':str(source),'active_bridge':str(active),'source_changes':changed,'bridge_changes':bridge,'package':str(release),'sha256':digest(release),'zip_files':len(names),'required_runtime_files_verified':required,'php_syntax_files':tests['syntax_files'],'php_regression_files':len(tests['tests']),'automation_config':str(target),'scheduler_enabled':False,'live_backend_installed':False}
(report/'release-manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
shutil.copy2(root/'test-results.json',report/'php-test-results.json')
shutil.copy2(root/'mcp-smoke-results.json',report/'mcp-smoke-results.json')
print(json.dumps({k:manifest[k] for k in ['version','package','sha256','zip_files','php_syntax_files','php_regression_files','automation_config','scheduler_enabled','live_backend_installed']}))

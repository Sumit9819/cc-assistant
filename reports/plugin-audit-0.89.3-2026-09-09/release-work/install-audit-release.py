import hashlib,json,shutil,zipfile
from datetime import datetime,timezone
from pathlib import Path
root=Path(__file__).resolve().parent
project=root.parents[1]
stage=root/'cc-assistant'
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
active=project/'wp-content/plugins/cc-assistant/bin'
hook=project/'.claude/hooks/cc_gate.py'
report=Path('D:/cc-assistant/reports/plugin-audit-0.89.3-2026-09-09')
package=root/'cc-assistant-0.89.3.zip'
destination=source.parent/package.name
digest=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
snapshot=lambda directory:{p.relative_to(directory).as_posix():digest(p) for p in directory.rglob('*') if p.is_file()}
baseline=json.loads((root/'baseline-hashes.json').read_text())
assert snapshot(source)==baseline, 'Canonical source changed during audit; reconcile before copying.'
assert hook.read_bytes()==(root/'workspace-hook-before.py').read_bytes(), 'Workspace hook changed during audit.'
assert (root/'verified-hook.py').read_bytes()==Path('D:/cc-assistant/.claude/hooks/cc_gate.py').read_bytes()
results=json.loads((root/'test-results.json').read_text())
extra=json.loads((root/'extra-test-results.json').read_text())
assert not results['syntax'] and len(results['tests'])==56 and not any(t['exit_code'] for t in results['tests'])
assert not any(t['exit_code'] for t in extra)
assert json.loads((root/'mcp-smoke-results.json').read_text())['version']=='0.89.3'
with zipfile.ZipFile(package) as archive:
    assert archive.testzip() is None
    entries=[n for n in archive.namelist() if not n.endswith('/')]
    assert len(entries)==158
    for name in entries:
        path=Path(name)
        assert path.parts[0]=='cc-assistant' and '..' not in path.parts and 'tests' not in path.parts
        assert archive.read(name)==(stage/Path(*path.parts[1:])).read_bytes(), name
assert not destination.exists(), 'Release ZIP already exists; do not overwrite another release.'
report.mkdir(parents=True,exist_ok=True)
for name in ['source-before.zip','active-bridge-before.zip','workspace-hook-before.py','release-manifest.json']:
    assert not (report/name).exists(), 'Backup/report already exists: '+name
def backup(directory,target):
    with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as archive:
        for p in directory.rglob('*'):
            if p.is_file(): archive.write(p,p.relative_to(directory).as_posix())
backup(source,report/'source-before.zip')
backup(active,report/'active-bridge-before.zip')
shutil.copy2(hook,report/'workspace-hook-before.py')
changed=[]
for path in stage.rglob('*'):
    if not path.is_file():continue
    relative=path.relative_to(stage)
    if '__pycache__' in relative.parts or any(part.startswith('.tmp-') for part in relative.parts):continue
    if baseline.get(relative.as_posix())!=digest(path):
        target=source/relative;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(path,target);changed.append(relative.as_posix())
with zipfile.ZipFile(package) as archive:
    for name in entries:
        relative=Path(*Path(name).parts[1:])
        assert (source/relative).read_bytes()==archive.read(name),name
        if relative.parts[0]=='bin':
            target=active/Path(*relative.parts[1:]);target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(archive.read(name))
            assert target.read_bytes()==archive.read(name)
shutil.copy2(root/'verified-hook.py',hook)
shutil.copy2(package,destination)
for name in ['AUDIT-REPORT.md','baseline-hashes.json','baseline-test-results.json','test-results.json','extra-test-results.json','extra-tests-before-hook-sync.json','mcp-smoke-results.json','audit-status.json']:
    shutil.copy2(root/name,report/name)
config=Path('C:/Users/sumit/.cc-assistant/automation/erofwhiterock/config.json')
automation_enabled=json.loads(config.read_text(encoding='utf-8-sig')).get('enabled') if config.exists() else None
manifest={'version':'0.89.3','created_at_utc':datetime.now(timezone.utc).isoformat(),'source':str(source),'active_bridge':str(active),'zip':str(destination),'sha256':digest(destination),'zip_files':len(entries),'zip_crc_valid':True,'zip_matches_source':True,'active_bridge_matches_zip':True,'changed_source_files':sorted(changed),'php_syntax_files':results['syntax_files'],'php_regression_files':len(results['tests']),'mcp_tools':177,'live_backend_observed':'0.89.2','live_pending_observed':0,'production_mutations':False,'workspace_hook_before_sha256':digest(report/'workspace-hook-before.py'),'workspace_hook_after_sha256':digest(hook),'workspace_hook_registration_changed':False,'automation_enabled':automation_enabled}
(report/'release-manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
print(json.dumps({'zip':str(destination),'sha256':manifest['sha256'],'changed_source_files':len(changed),'archive_files':len(entries),'automation_enabled':automation_enabled,'report':str(report/'AUDIT-REPORT.md')},indent=2))

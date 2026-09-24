import hashlib,json,shutil,zipfile,difflib
from pathlib import Path
from datetime import datetime,timezone
root=Path(__file__).resolve().parent;stage=root/'cc-assistant'
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
active=Path('C:/Users/sumit/Local Sites/plugintesting/app/public/wp-content/plugins/cc-assistant/bin')
report=Path('D:/cc-assistant/reports/apply-recovery-0.89.4-2026-09-10')
package=root/'cc-assistant-0.89.4.zip'
dest=Path('D:/cc-assistant/dist')/package.name
legacy_dest=source.parent/package.name
digest=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
snapshot=lambda p:{x.relative_to(p).as_posix():digest(x) for x in p.rglob('*') if x.is_file()}
baseline=json.loads((root/'baseline-hashes.json').read_text())
validation=json.loads((root/'release-validation.json').read_text())
assert snapshot(source)==baseline,'Canonical source changed; reconcile before installing local release.'
assert digest(package)==validation['sha256'] and validation['php_regression_files']==59
assert not dest.exists() and not legacy_dest.exists(),'Refusing to overwrite an existing release.'
changes=[];diff=[]
for p in stage.rglob('*'):
    if not p.is_file():continue
    rel=p.relative_to(stage)
    if any(part=='__pycache__' or part.startswith('.tmp-') for part in rel.parts):continue
    if baseline.get(rel.as_posix())==digest(p):continue
    changes.append(rel)
    old=source/rel
    if rel.parts[0]=='bin':
        current=active/Path(*rel.parts[1:])
        assert current.is_file() and digest(current)==baseline[rel.as_posix()],'Active bridge changed: '+str(rel)
    before=old.read_text(encoding='utf-8').splitlines(True) if old.exists() else []
    diff.extend(difflib.unified_diff(before,p.read_text(encoding='utf-8').splitlines(True),fromfile='before/'+rel.as_posix(),tofile='after/'+rel.as_posix()))
expected={'includes/class-integrity.php','includes/class-approval-continuation.php','includes/class-evidence-gate.php','includes/class-apply.php','includes/class-approval-batch.php','includes/class-rest-pending.php','tests/approval-batch-test.php','tests/approval-continuation-test.php','tests/approval-environment-test.php','tests/approval-verification-test.php','cc-assistant.php','bin/mcp-server.php','bin/agent-contract.php','readme.txt','EVIDENCE.md'}
assert {r.as_posix() for r in changes}==expected,'Unexpected changed files: '+str([r.as_posix() for r in changes])
for label,path in [('canonical',Path('D:/cc-assistant/CLAUDE.md')),('workspace',Path('C:/Users/sumit/Local Sites/plugintesting/app/public/CLAUDE.md'))]:
    before=root/f'{label}-CLAUDE-before.md'
    if before.exists():assert path.read_bytes()==before.read_bytes(),'Operator guidance changed during this task.'
report.mkdir(parents=True,exist_ok=True)
assert not (report/'source-before.zip').exists(),'Backup already exists; do not overwrite.'
with zipfile.ZipFile(report/'source-before.zip','w',zipfile.ZIP_DEFLATED) as z:
    for p in source.rglob('*'):
        if p.is_file():z.write(p,p.relative_to(source).as_posix())
with zipfile.ZipFile(report/'active-bridge-before.zip','w',zipfile.ZIP_DEFLATED) as z:
    for p in active.rglob('*'):
        if p.is_file():z.write(p,p.relative_to(active).as_posix())
for rel in changes:
    target=source/rel;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(stage/rel,target)
    if rel.parts[0]=='bin':shutil.copy2(stage/rel,active/Path(*rel.parts[1:]))
for label,path in [('canonical',Path('D:/cc-assistant/CLAUDE.md')),('workspace',Path('C:/Users/sumit/Local Sites/plugintesting/app/public/CLAUDE.md'))]:
    after=root/f'{label}-CLAUDE-after.md'
    if after.exists():
        shutil.copy2(root/f'{label}-CLAUDE-before.md',report/f'{label}-CLAUDE-before.md')
        shutil.copy2(after,path)
dest.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(package,dest);shutil.copy2(package,legacy_dest)
with zipfile.ZipFile(package) as z:
    for name in z.namelist():
        rel=Path(*Path(name).parts[1:]);assert z.read(name)==(source/rel).read_bytes(),name
for name in ['test-results.json','release-validation.json','live-source-comparison.json','integrity-compat-test.php.log','live-replay-test.php.log']:
    shutil.copy2(root/name,report/name)
(report/'source-changes.patch').write_text(''.join(diff),encoding='utf-8')
manifest={**validation,'zip':str(dest),'alternate_zip':str(legacy_dest),'changed_files':[r.as_posix() for r in changes],'local_source':str(source),'active_bridge':str(active),'live_backend_observed':'0.89.3','live_pending_observed':28,'created_at_utc':datetime.now(timezone.utc).isoformat(),'remote_plugin_installed':False,'production_content_changes':False}
(report/'release-manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
print(json.dumps({'zip':str(dest),'sha256':manifest['sha256'],'changed_files':manifest['changed_files'],'remote_installed':False},indent=2))

import json,shutil,zipfile,hashlib,difflib
from pathlib import Path
root=Path(__file__).resolve().parent;stage=root/'cc-assistant'
canonical=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
report=Path('D:/cc-assistant/reports/approval-runtime-0.89.5-2026-09-10')
baseline=json.loads((root/'baseline-hashes.json').read_text(encoding='utf-8'))
digest=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
for rel,h in baseline.items():assert (canonical/rel).is_file() and digest(canonical/rel)==h,'Canonical source changed during repair: '+rel
assert not (report/'release-manifest.json').exists(),'Release is already installed'
if report.exists():assert {p.name for p in report.iterdir()} <= {'source-before-0.89.4.zip','active-bridge-before.zip','FIX-REPORT.md'},'Unexpected files in unfinished report'
validation=json.loads((root/'release-validation.json').read_text(encoding='utf-8'));assert validation['regression_test_files']==60
package=root/'cc-assistant-0.89.5.zip';assert digest(package)==validation['sha256']
changed=[p.relative_to(stage).as_posix() for p in stage.rglob('*') if p.is_file() and p.suffix not in {'.sqlite','.pyc','.log'} and not any(part.startswith('.tmp-') or part=='__pycache__' for part in p.parts) and baseline.get(p.relative_to(stage).as_posix())!=digest(p)]
report.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(report/'source-before-0.89.4.zip','w',zipfile.ZIP_DEFLATED) as z:
 for rel in baseline:z.write(canonical/rel,'cc-assistant/'+rel)
active=Path('C:/Users/sumit/Local Sites/plugintesting/app/public/wp-content/plugins/cc-assistant/bin')
with zipfile.ZipFile(report/'active-bridge-before.zip','w',zipfile.ZIP_DEFLATED) as z:
 for name in ['mcp-server.php','agent-contract.php']:z.write(active/name,name)
patch=[]
for rel in changed:
 before=(canonical/rel).read_text(encoding='utf-8').splitlines(keepends=True) if (canonical/rel).exists() else []
 after=(stage/rel).read_text(encoding='utf-8').splitlines(keepends=True)
 patch.extend(difflib.unified_diff(before,after,fromfile='a/'+rel,tofile='b/'+rel))
(report/'source-changes.patch').write_text(''.join(patch),encoding='utf-8')
for rel in changed:
 target=canonical/rel;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(stage/rel,target);assert digest(target)==digest(stage/rel)
for name in ['mcp-server.php','agent-contract.php']:shutil.copyfile(stage/'bin'/name,active/name)
for target in [Path('D:/cc-assistant/dist/cc-assistant-0.89.5.zip'),Path('D:/cc-assistant/wp-content/plugins/cc-assistant-0.89.5.zip')]:
 assert not target.exists(),'Release already exists'
 target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(package,target);assert digest(target)==validation['sha256']
for name in ['release-validation.json','test-results.json','initial-live-evidence.json','spanish-live-evidence.json','incident-payload-replay.log','before-fix-reproduction.log']:
 shutil.copyfile(root/name,report/name)
(report/'release-manifest.json').write_text(json.dumps({'version':'0.89.5','changed_files':changed,'sha256':validation['sha256'],'production_installed':False},indent=2),encoding='utf-8')
print(json.dumps({'changed_files':changed,'report':str(report),'zip':'D:/cc-assistant/dist/cc-assistant-0.89.5.zip','sha256':validation['sha256']},indent=2))

import argparse,hashlib,json,shutil,zipfile
from pathlib import Path
root=Path(__file__).parent.resolve();stage=root/'cc-assistant'
project=Path('D:/cc-assistant').resolve();source=(project/'wp-content/plugins/cc-assistant').resolve()
workspace=Path('C:/Users/sumit/Local Sites/plugintesting/app/public').resolve();bridge=(workspace/'wp-content/plugins/cc-assistant/bin').resolve()
reports=project/'reports/implementation-0.88.0-2026-09-09';archive=root/'cc-assistant-0.88.0.zip';target=source.parent/archive.name
baseline=json.loads((root/'baseline-hashes.json').read_text(encoding='utf-8'));changes=json.loads((root/'changed-files.json').read_text(encoding='utf-8'));bridge_before=json.loads((root/'bridge-baseline.json').read_text(encoding='utf-8'));validation=json.loads((root/'release-validation.json').read_text(encoding='utf-8'))
sha=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
def child(base,rel):
 p=(base/rel).resolve();p.relative_to(base);assert p!=base;return p
source.relative_to(project);bridge.relative_to(workspace);reports.relative_to(project/'reports');target.relative_to(source.parent)
assert not reports.exists() and not target.exists(),'Release destinations already exist'
for rel,expected in baseline.items():assert sha(child(source,rel))==expected,'Canonical file changed: '+rel
for c in changes:
 assert sha(child(stage,c['path']))==c['after_sha256']
 if c['before_sha256'] is None:assert not child(source,c['path']).exists()
for rel,expected in bridge_before.items():
 p=child(bridge,rel.removeprefix('bin/'));assert (sha(p) if p.exists() else None)==expected,'Desktop bridge changed: '+rel
assert sha(archive)==validation['package']['sha256']
ap=argparse.ArgumentParser();ap.add_argument('--apply',action='store_true');apply=ap.parse_args().apply
if not apply:
 print(json.dumps({'ready':True,'source_files':len(changes),'bridge_files':list(bridge_before),'archive':str(target),'report':str(reports/'IMPLEMENTATION-0.88.0.md')}));raise SystemExit(0)
reports.mkdir(parents=True,exist_ok=False)
source_backup=reports/'cc-assistant-0.87.0-source-backup.zip';bridge_backup=reports/'desktop-bridge-0.87.0-backup.zip'
with zipfile.ZipFile(source_backup,'x',zipfile.ZIP_DEFLATED) as z:
 for rel in sorted(baseline):z.write(child(source,rel),'cc-assistant/'+rel)
with zipfile.ZipFile(source_backup) as z:
 assert z.testzip() is None
 for rel,expected in baseline.items():assert hashlib.sha256(z.read('cc-assistant/'+rel)).hexdigest()==expected
with zipfile.ZipFile(bridge_backup,'x',zipfile.ZIP_DEFLATED) as z:
 for rel,before in bridge_before.items():
  if before is not None:z.write(child(bridge,rel.removeprefix('bin/')),rel)
with zipfile.ZipFile(bridge_backup) as z:
 assert z.testzip() is None
 for rel,before in bridge_before.items():
  if before is not None:assert hashlib.sha256(z.read(rel)).hexdigest()==before
copied=[];bcopied=[]
try:
 for c in changes:
  p=child(source,c['path']);p.parent.mkdir(parents=True,exist_ok=True);copied.append(c);shutil.copy2(child(stage,c['path']),p);assert sha(p)==c['after_sha256']
 for rel in bridge_before:
  p=child(bridge,rel.removeprefix('bin/'));p.parent.mkdir(parents=True,exist_ok=True);bcopied.append(rel);shutil.copy2(child(stage,rel),p);assert sha(p)==sha(child(stage,rel))
 shutil.copy2(archive,target);assert sha(target)==validation['package']['sha256']
 for name in ['IMPLEMENTATION-0.88.0.md','release-validation.json','baseline-hashes.json','bridge-baseline.json','changed-files.json','agent-eval-results.json','test-results.json','mcp-smoke-results.json','run-checks.py','mcp-smoke.py','install-release.py']:
  shutil.copy2(root/name,reports/name)
 for group in ['claude-evals-validated','claude-evals-memory-recheck']:
  for p in (root/group).glob('*/*'):
   if p.name not in ['state.json','cli-output.json']:continue
   dest=child(reports,'agent-traces/'+group+'/'+p.parent.name+'/'+p.name);dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(p,dest)
 shutil.copy2(stage/'EVIDENCE-AND-RESEARCH.md',reports/'EVIDENCE-AND-RESEARCH.md')
 shutil.copy2(root/'pilot-article.html',reports/'pilot-article.html')
 result={'status':'source_and_desktop_bridge_updated','version':'0.88.0','source_updates':len(copied),'bridge_updates':bcopied,'archive':str(target),'archive_sha256':sha(target),'report':str(reports/'IMPLEMENTATION-0.88.0.md'),'source_backup':str(source_backup),'bridge_backup':str(bridge_backup),'live_site_deployed':False,'restart_required':True}
 (reports/'source-update.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result,indent=2))
except Exception as exc:
 with zipfile.ZipFile(source_backup) as z:
  for c in reversed(copied):
   p=child(source,c['path'])
   if c['before_sha256'] is not None:p.write_bytes(z.read('cc-assistant/'+c['path']))
   elif p.exists() and sha(p)==c['after_sha256']:p.unlink()
 with zipfile.ZipFile(bridge_backup) as z:
  for rel in reversed(bcopied):
   p=child(bridge,rel.removeprefix('bin/'))
   if bridge_before[rel] is not None:p.write_bytes(z.read(rel))
   elif p.exists() and sha(p)==sha(child(stage,rel)):p.unlink()
 if target.exists() and sha(target)==validation['package']['sha256']:target.unlink()
 (reports/'source-update-error.json').write_text(json.dumps({'error':str(exc),'original_files_restored':True}),encoding='utf-8');raise

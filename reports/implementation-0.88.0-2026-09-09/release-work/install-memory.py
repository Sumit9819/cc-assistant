import hashlib,json,shutil,subprocess,sys
from pathlib import Path
root=Path(__file__).parent; reviewed=root/'memory-reviewed';report=Path('D:/cc-assistant/reports/preflight-0.88.0-2026-09-09');backup=report/'memory-backup';backup.mkdir(exist_ok=True)
manifest=json.loads((reviewed/'manifest.json').read_text(encoding='utf-8'))
for row in manifest:
 p=Path(row['path']);actual=hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() else None
 if actual!=row['before_sha256']:raise SystemExit('Local memory changed since review: '+str(p))
# Read current live notes and refuse to overwrite concurrent changes.
req=report/'memory-check-request.json';req.write_text('[{"name":"get_site_memory","arguments":{}}]',encoding='utf-8')
subprocess.run([sys.executable,str(root/'live-bridge.py'),'--requests',str(req),'--output',str(report/'memory-before-write.json')],check=True)
rows=json.loads((report/'memory-before-write.json').read_text(encoding='utf-8'));current=next(r['result'].get('notes') for r in rows if r['name']=='get_site_memory')
if not isinstance(current,str) or hashlib.sha256(current.encode()).hexdigest()!=(reviewed/'site-notes-before.sha256').read_text():raise SystemExit('Site notes changed since review; no overwrite performed')
for i,row in enumerate(manifest):
 p=Path(row['path'])
 if p.exists():shutil.copy2(p,backup/(str(i)+'.md'))
 p.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(reviewed/row['staged'],p)
(backup/'manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
subprocess.run([sys.executable,str(root/'live-bridge.py'),'--requests',str(reviewed/'live-memory-requests.json'),'--output',str(report/'memory-after-write.json')],check=True)
rows=json.loads((report/'memory-after-write.json').read_text(encoding='utf-8'));after=next(r['result'].get('notes') for r in rows if r['name']=='get_site_memory')
expected=(reviewed/'site-notes-reviewed.md').read_text(encoding='utf-8')
if after!=expected:raise SystemExit('Live notes verification mismatch; inspect private report')
print(json.dumps({'local_files_updated':len(manifest),'live_notes_verified':True,'backup':str(backup)}))

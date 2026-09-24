import json,shutil,hashlib
from pathlib import Path
root=Path(__file__).resolve().parent
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
stage=root/'cc-assistant'
assert not stage.exists()
digest=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
baseline={p.relative_to(source).as_posix():digest(p) for p in source.rglob('*') if p.is_file()}
(root/'baseline-hashes.json').write_text(json.dumps(baseline,indent=2))
shutil.copytree(source,stage,ignore=shutil.ignore_patterns('__pycache__','.tmp-*'))
rows=json.loads(Path('D:/cc-assistant/reports/apply-recovery-0.89.4-2026-09-10/initial-results.json').read_text(encoding='utf-8'))
for r in rows:
    d=r['result']
    if r['name']=='whoami': print('LIVE',d['site_url'],d['plugin_version'])
    if r['name']=='list_pending_changes':
        print('PENDING',d['count'],[(int(p['id']),int(p['post_id']),p['change_type']) for p in d['pending']])
    if r['name']=='post_dossier':
        print('DOSSIER',d['post']['id'],'HISTORY',json.dumps(d['history'],ensure_ascii=False)[:6500])
print('Staged files',len(baseline))

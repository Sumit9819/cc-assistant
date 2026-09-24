import json
from pathlib import Path
root=Path(__file__).resolve().parent
rows=json.loads(Path('D:/cc-assistant/reports/apply-recovery-0.89.4-2026-09-10/initial-results.json').read_text(encoding='utf-8'))
pending=next(r['result']['pending'] for r in rows if r['name']=='list_pending_changes')
ids=sorted({int(r['post_id']) for r in pending})
requests=[{'name':'whoami','arguments':{}}]+[{'name':'get_post','arguments':{'id':pid,'slim':False}} for pid in ids]
(root/'live-reads.json').write_text(json.dumps(requests),encoding='utf-8')
print('Current targets to read:',len(ids))

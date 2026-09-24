import json
from pathlib import Path
root=Path('D:/cc-assistant/reports/preflight-0.89.0-2026-09-09/ubersuggest-research')
events=[json.loads(l) for l in (root/'events.jsonl').read_text(encoding='utf-8').splitlines() if l.startswith('{')]
names={};rows=[]
for e in events:
 for b in e.get('message',{}).get('content',[]):
  if not isinstance(b,dict):continue
  if b.get('type')=='tool_use':names[b['id']]={'tool':b['name'],'arguments':b.get('input',{})}
  if b.get('type')=='tool_result':rows.append({**names.get(b['tool_use_id'],{}),'is_error':b.get('is_error',False),'result':b.get('content')})
(root/'tool-results.json').write_text(json.dumps(rows,indent=2,ensure_ascii=False),encoding='utf-8')
for r in rows:
 print(json.dumps({'tool':r.get('tool'),'is_error':r['is_error'],'result_excerpt':str(r['result'])[:3800]},ensure_ascii=False))
last=next(e for e in reversed(events) if e.get('type')=='result');(root/'final-interpretation.txt').write_text(last.get('result',''),encoding='utf-8')

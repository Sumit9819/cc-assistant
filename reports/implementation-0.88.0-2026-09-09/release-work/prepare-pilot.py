import json,re
from pathlib import Path
root=Path(__file__).parent;data=Path('D:/cc-assistant/reports/preflight-0.88.0-2026-09-09')
rows=json.loads((data/'live-scope-workflow.json').read_text(encoding='utf-8'));w=next(r['result'] for r in rows if r['name']=='content_workflow');assert w.get('record_id')
content=(root/'pilot-article.html').read_text(encoding='utf-8')
services=next(r['result']['url'] for r in json.loads((data/'live-content-results.json').read_text(encoding='utf-8')) if r['name']=='get_post' and r['result']['id']==3505)
content=content.replace('https://erofwhiterock.com/services/',services)
args={'title':'What to Tell the ER Team: A Medication and Symptom Note','content':content,'excerpt':'A practical note for sharing symptoms, medicines and questions with the emergency team. Preparing information should never delay urgent care.','post_type':'post','categories':[123],'lang':'en','slug':'what-to-tell-er-team','workflow_id':w['record_id'],'summary':'Review-only pilot: a practical communication note with primary-source links, distinct from the existing ER-timeline article. No publication performed.','reasoning':'Inspecting all 38 current blog titles and the full related timeline article (5437) found brief preparation advice there; this draft develops a standalone communication aid. Market demand and global uniqueness are unmeasured. No new clinical diagnoses, provider attribution or facility performance promises.','dry_run':True}
(root/'pilot-args.json').write_text(json.dumps(args,ensure_ascii=False),encoding='utf-8')
(root/'live-pilot-dryrun.json').write_text(json.dumps([{'name':'whoami','arguments':{}},{'name':'draft_create_post','arguments':args}],ensure_ascii=False),encoding='utf-8')
print(json.dumps({'workflow_id':w['record_id'],'draft_words':len(re.sub('<[^>]*>',' ',content).split()),'service_url':services}))

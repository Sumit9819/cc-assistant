import json
from pathlib import Path
root=Path(__file__).parent; data=Path('D:/cc-assistant/reports/preflight-0.88.0-2026-09-09')
scope=next(r['result'] for r in json.loads((data/'scope-after-memory.json').read_text(encoding='utf-8')) if r['name']=='get_content_scope')
evidence=[]
for s in scope['sources']:
 if s['post_id'] in [3505,971]:evidence.append({'post_id':s['post_id'],'evidence_id':s['evidence_id'],'reason':'Inspected the current services hub and laboratory page. Use these as editorial anchors; source claims still need independent support. Placeholder text and performance guarantees are not facts to repeat.'})
assert len(evidence)==2
args={'expected_revision':scope['scope_revision'],'context_hash':scope['context']['context_hash'],'source_evidence':evidence,'reason':'Claude-managed initial scope from inspected English service sources and current reconciled site constraints. Expand with additional inspected service evidence as needed; GSC is optional.','audience':'People and caregivers in White Rock, Lake Highlands, Lakewood and nearby East Dallas seeking understandable emergency-care information.','excluded_topics':['Unverified facility services or guaranteed wait times','Invented data, clinical outcomes, local statistics or expert quotes','Provider naming or medical-review attribution without confirmed consent','Unrelated products and services outside the emergency-care niche'],'reader_questions':[{'post_id':3505,'question':'What information can I share with the emergency team without delaying care?'},{'post_id':3505,'question':'What questions should I ask about instructions and follow-up before leaving the ER?'},{'post_id':971,'question':'What can emergency laboratory tests tell me, and what should I ask about my results?'},{'post_id':971,'question':'Why does the emergency team ask about medicines and supplements before testing?'}]}
reqs=[{'name':'whoami','arguments':{}},{'name':'manage_content_scope','arguments':args},{'name':'content_workflow','arguments':{'objective':'new_blog','limit':3}}]
(root/'live-scope-manage.json').write_text(json.dumps(reqs,ensure_ascii=False),encoding='utf-8')
print('Prepared evidence-bound scope and pilot workflow')

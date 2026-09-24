import json
from pathlib import Path
root=Path(__file__).parent
data=json.loads(Path('D:/cc-assistant/reports/preflight-0.89.0-2026-09-09/live-changes.json').read_text(encoding='utf-8'))
scope=next(x['result'] for x in data if x['name']=='get_content_scope')
dis=next(x['result'] for x in data if x['name']=='discover_content_scope')
sources={s['post_id']:s for s in dis['candidates']}
ids=[3505,971,1072,2516,2453,1374,799]
assert all(i in sources for i in ids),[i for i in ids if i not in sources]
notes=[x['result']['notes'] for x in data if x['name']=='get_site_memory'][-1]
assert (root/'site-policy.txt').read_text(encoding='utf-8') in notes
evidence=[{'post_id':i,'evidence_id':sources[i]['evidence_id'],'reason':'Inspected current published English service content. Use as an emergency-care topic anchor; its specific operational and clinical claims still require corroboration.'} for i in ids]
questions=list(scope['reader_questions'])
questions += [{'post_id':i,'question':q} for i,q in [
 (1072,'What information about fluid loss or symptoms is useful to share with the emergency care team?'),
 (2516,'What happens during an emergency assessment for chest pain?'),
 (2453,'What health information should a caregiver share during a child\'s emergency visit?'),
 (1374,'What questions should I ask about follow-up after a fracture evaluation?'),
 (799,'What symptom history is useful during an emergency evaluation for digestive problems?')]]
args={'expected_revision':scope['scope_revision'],'context_hash':scope['context']['context_hash'],'source_evidence':evidence,'reader_questions':questions,
      'reason':'Expand Claude-managed niche planning from two to seven inspected service anchors. Preserve exclusions, audience and current style/author preferences. GSC gaps and positive provider volume are not prerequisites. Service-page copy alone does not verify business promises.'}
calls=[{'name':'whoami','arguments':{}},{'name':'manage_content_scope','arguments':args},{'name':'get_content_scope','arguments':{}},
       {'name':'operator_brain_push','arguments':{'paths':['memory/feedback_erofwhiterock_current_evidence_policy.md']}},
       {'name':'get_post','arguments':{'id':5525,'slim':True}},{'name':'list_pending_changes','arguments':{}}]
(root/'live-scope.json').write_text(json.dumps(calls,indent=2),encoding='utf-8')
print(json.dumps({'source_ids':ids,'reader_questions':len(questions),'site_preferences_read_back':True}))

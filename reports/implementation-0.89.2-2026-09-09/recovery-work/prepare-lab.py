import json
from pathlib import Path
root=Path(__file__).parent
report=Path('D:/cc-assistant/reports/proposal-refresh-0.89.1-2026-09-09')
data=json.loads((report/'controls.json').read_text(encoding='utf-8'))
schemas=[x['result'] for x in data if x['name']=='widget_schema']
updates=[('46afe405',{'title_text_a':'Understanding Your Results','title_text_b':'Understanding Your Results'},'Replace the unsupported Fast & Flawless Results heading on both sides of the lab card.'),('77684669',{'description_text_b':'Your physician considers laboratory results alongside your symptoms, medical history and examination. Ask what the findings mean and whether you need follow-up.'},'Replace the guarantee of immediate, accurate treatment with an explanation of how results are interpreted.'),('2a6ed6d9',{'description_text_b':'Laboratory tests can provide information that helps your care team evaluate symptoms. Some findings may require additional tests or follow-up.'},'Replace the claim of instantly diagnosing severe conditions with qualified laboratory-testing information.')]
requests=[{'name':'whoami','arguments':{}},{'name':'verified_page_audit','arguments':{'post_id':971}}]
for i,(widget,settings,summary) in enumerate(updates):
    assert schemas[i]['widget_type']=='flip-box'
    assert all(k in schemas[i]['controls'] for k in settings)
    print(json.dumps({'widget':widget,'validated_controls':list(settings)}))
    requests.append({'name':'draft_update_elementor_widget','arguments':{'post_id':971,'widget_id':widget,'settings':settings,'summary':summary,'reasoning':'Refreshed after installation of CC Assistant 0.89.1 and the successful edit #1725. Replaces stale proposal #'+str(1726+i)+'. Current saved flip-box controls and rendered page were inspected. MedlinePlus explains that laboratory results are assessed with health history and examination and may need additional testing: https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/ . Only the specified text is changed; retain the existing layout.'}})
requests.append({'name':'list_pending_changes','arguments':{}})
(root/'queue-lab.json').write_text(json.dumps(requests,ensure_ascii=False),encoding='utf-8')
# Maintain the same source scope and exclusions, refreshed from current source observations.
scope=next(x['result'] for x in data if x['name']=='get_content_scope')
scope_args={'expected_revision':scope['scope_revision'],'context_hash':scope['context']['context_hash'],'source_evidence':[{'post_id':s['post_id'],'evidence_id':s['evidence_id'],'reason':'Existing service anchor re-read during post-update proposal recovery.'} for s in scope['sources']],'reason':'Refresh existing scope after the approved laboratory correction and plugin update. Preserve audience, exclusions and reader questions; use the observed organization author.' ,'author_id':4,'expected_author_name':'ER of White Rock'}
(root/'refresh-scope.json').write_text(json.dumps([{'name':'manage_content_scope','arguments':scope_args},{'name':'get_content_scope','arguments':{}},{'name':'content_workflow','arguments':{'objective':'new_blog','post_ids':[5525],'limit':1}}]),encoding='utf-8')

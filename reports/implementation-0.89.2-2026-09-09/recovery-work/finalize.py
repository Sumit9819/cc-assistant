import json,sys
from pathlib import Path
root=Path(__file__).parent
live=Path('D:/cc-assistant/reports/proposal-refresh-0.89.1-2026-09-09')
if sys.argv[1]=='scope':
    scope=next(x['result'] for x in json.loads((live/'scope-final-read.json').read_text(encoding='utf-8')) if x['name']=='get_content_scope')
    args={'expected_revision':scope['scope_revision'],'context_hash':scope['context']['context_hash'],'source_evidence':[{'post_id':s['post_id'],'evidence_id':s['evidence_id'],'reason':'Re-read after the administrator applied the laboratory corrections.'} for s in scope['sources']],'reason':'Refresh source fingerprints after approved corrections #1729-#1731; preserve existing strategy and organization attribution.'}
    (root/'scope-final-save.json').write_text(json.dumps([{'name':'manage_content_scope','arguments':args},{'name':'get_content_scope','arguments':{}}]),encoding='utf-8')
    print(json.dumps({'previous_freshness':scope['freshness'],'sources':len(scope['sources'])}))
    raise SystemExit
data=json.loads((live/'applied-state.json').read_text(encoding='utf-8'))
post=next(x['result'] for x in data if x['name']=='get_post')
tree=post['elementor_data'];tree=json.loads(tree) if isinstance(tree,str) else tree
nodes={}
def walk(ns):
    for n in ns:
        if isinstance(n,dict):nodes[n.get('id')]=n;walk(n.get('elements',[]))
walk(tree)
requests=json.loads((root/'queue-lab.json').read_text(encoding='utf-8'))
for request in requests:
    if request['name']=='draft_update_elementor_widget':
        a=request['arguments'];assert all(nodes[a['widget_id']]['settings'][k]==v for k,v in a['settings'].items()),a['widget_id']
assert 'Laboratory results help your care team' in nodes['1ffbaf62']['settings']['editor']
audits=[{k:x['result'].get(k) for k in ['source','usable','counts','assessment']} for x in data if x['name']=='verified_page_audit']
assert all(a['usable'] and a['source']['http_code']==200 and a['source']['cache_state']=='miss' for a in audits)
final=json.loads((live/'final-state.json').read_text(encoding='utf-8'))
pending=next(x['result'] for x in final if x['name']=='list_pending_changes');assert pending['count']==0
blog=next(x['result'] for x in final if x['name']=='get_post');assert blog['status']=='publish' and blog['author_id']==4
scope=next(x['result'] for x in json.loads((live/'scope-final-save.json').read_text(encoding='utf-8')) if x['name']=='get_content_scope')
assert scope['freshness']['status']=='source_hashes_match'
report=Path('D:/cc-assistant/reports/implementation-0.89.2-2026-09-09')
manifest=json.loads((report/'release-manifest.json').read_text(encoding='utf-8'))
manifest.update(live_pending_count=0,live_lab_proposals_approved=[1729,1730,1731],live_blog_status='publish',original_publication_proposal_status='rejected',saved_lab_changes_verified=True,public_audits=audits,scope_freshness=scope['freshness'])
(report/'release-manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
p=report/'REPORT.md';text=p.read_text(encoding='utf-8')
latest='''## Latest live result

Later checks during this turn found the administrator had approved lab proposals #1729-#1731. The saved Elementor settings exactly match all three replacements, and earlier correction #1725 remains intact. The review inbox is empty. Original publication proposal #1723 was rejected; post 5525 is now published under ER of White Rock (ID 4), through a separate action rather than this agent publishing it.

Fresh audits returned HTTP 200 with cache misses for the lab page and https://erofwhiterock.com/what-to-tell-er-team/ . Each had 9 automated passes, 0 failures and 6 unknown checks. These are partial HTML checks, not browser, clinical, ranking or indexing certification. Strategy source fingerprints were refreshed again after the approvals and now match.

The live site remains on 0.89.1. The completed 0.89.2 update is available for future publication recovery and accurate workflow verification; the newly implemented recovery endpoint has not been executed on production. No publication requeue is needed for this now-published article.

'''
text=text.replace('## Live work completed',latest+'## Earlier work in this turn',1)
text=text.replace('- Backend deployment of 0.89.2 and refresh of publication #1723 remain outstanding. Do not claim it is ready until the new endpoint succeeds against fresh live evidence.','- Backend deployment of 0.89.2 remains optional next work. Publication #1723 no longer needs recovery: it was rejected and the article is now published, as confirmed in the latest live result above.')
start=text.index('## Minimal next sequence');end=text.index('Supporting lab source:',start)
text=text[:start]+'''## Next action

Install cc-assistant-0.89.2.zip when ready to enable the new recovery tool and strengthened verification. The live queue is currently empty; do not recreate or republish article 5525. Future publication recovery must use new observations and a current workflow, never an old evidence receipt.

'''+text[end:]
p.write_text(text,encoding='utf-8')
print(json.dumps({'saved_lab_corrections_verified':3,'pending_count':0,'blog_status':blog['status'],'author':blog['author'],'scope_freshness':scope['freshness']['status'],'audits':[{'post_id':a['source']['post_id'],'http':a['source']['http_code'],'unknown':a['counts']['unknown']} for a in audits]}))

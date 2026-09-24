import json
from pathlib import Path
root=Path(__file__).parent
report=Path('D:/cc-assistant/reports/proposal-refresh-0.89.1-2026-09-09')
data=json.loads((report/'evidence.json').read_text(encoding='utf-8'))
for x in data:
    if x['name']=='verified_page_audit':print(json.dumps({'audit':{k:x['result'].get(k) for k in ['usable','assessment','source','counts']}}))
    if x['name']=='verify_content_workflow':print(json.dumps(x['result'],indent=2))
post=next(x['result'] for x in data if x['name']=='get_post')
tree=post['elementor_data'];tree=json.loads(tree) if isinstance(tree,str) else tree
nodes={}
def walk(ns):
    for n in ns:
        if isinstance(n,dict):nodes[n.get('id')]=n;walk(n.get('elements',[]))
walk(tree)
requests=[]
for id in ['46afe405','77684669','2a6ed6d9']:
    n=nodes[id]
    print(json.dumps({'widget':id,'type':n['widgetType'],'settings':{k:v for k,v in n['settings'].items() if k.startswith(('title_text','description_text'))}}))
    requests.append({'name':'widget_schema','arguments':{'post_id':971,'widget_id':id,'widget_type':n['widgetType']}})
requests.extend([{'name':'get_content_scope','arguments':{}},{'name':'discover_content_scope','arguments':{}},{'name':'pre_publish_check','arguments':{'id':5525}}])
(root/'controls.json').write_text(json.dumps(requests),encoding='utf-8')

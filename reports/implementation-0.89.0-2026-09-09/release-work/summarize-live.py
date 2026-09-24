import json,re
from pathlib import Path
data=json.loads(Path('D:/cc-assistant/reports/preflight-0.89.0-2026-09-09/live-browser-review.json').read_text(encoding='utf-8'))
def walk(nodes):
    for n in nodes if isinstance(nodes,list) else []:
        yield n
        yield from walk(n.get('elements',[]))
for call in data:
    if call['name']!='get_post':continue
    p=call['result']; tree=p.get('elementor_data',[])
    if isinstance(tree,str):tree=json.loads(tree or '[]')
    print('\nPOST',p['id'],p['title'],'AUTHOR',p['author'])
    matches=[];texts=[]
    for node in walk(tree):
        for k,v in (node.get('settings') or {}).items():
            if not isinstance(v,str):continue
            if k in ('editor','title','text','html','description','item_description'):texts.append(re.sub('<[^>]+>',' ',v))
            if re.search(r'lorem ipsum|flawless|instantly|guarantee',v,re.I):matches.append({'id':node['id'],'type':node.get('widgetType'),'key':k,'value':v[:1700]})
    print(json.dumps(matches,ensure_ascii=True))
    if p['id'] not in (3505,971,5437):print(' '.join(texts)[:1400])
    if not tree:print(re.sub('<[^>]+>',' ',p.get('content',''))[:2200])
dis=next(x['result'] for x in data if x['name']=='discover_content_scope')
print('DISCOVERY COVERAGE',dis['inventory_coverage'])
print('SELECTED EVIDENCE',json.dumps([c for c in dis['candidates'] if c['post_id'] in [3505,971,1072,2516,2453,1374,799]],ensure_ascii=True)[:6000])

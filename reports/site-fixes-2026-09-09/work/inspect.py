import json,sys,re
from pathlib import Path
base=Path('D:/cc-assistant/reports/site-fixes-2026-09-09')
def entries(name): return json.loads((base/name).read_text(encoding='utf-8'))
def walk(nodes):
    if isinstance(nodes,dict):
        if 'id' in nodes and isinstance(nodes.get('settings'),dict): yield nodes
        for k,v in nodes.items():
            if isinstance(v,(dict,list)): yield from walk(v)
    elif isinstance(nodes,list):
        for n in nodes: yield from walk(n)
if __name__=='__main__':
    for r in entries(sys.argv[1]):
        if len(sys.argv)>2 and str(r.get('result',{}).get('id') if isinstance(r.get('result'),dict) else '') not in sys.argv[2:]: continue
        print('\nCALL',r['name'],'ERROR',r.get('isError',False))
        d=r['result']
        if r['name']=='get_post':
            print('KEYS',list(d))
            print(json.dumps({k:d.get(k) for k in ['id','title','url','author_id','has_elementor']},ensure_ascii=False))
            for n in walk(d):
                s={k:v for k,v in n['settings'].items() if k in ['title','title_text_a','title_text_b','description_text_b','description_text_a','editor','html','text','tabs','items','description','title_text','link','__dynamic__'] and v}
                if s: print(n['id'],n.get('widgetType',n.get('elType')),json.dumps(s,ensure_ascii=False))
            if not list(walk(d)): print(json.dumps(d,ensure_ascii=False)[:35000])
        elif r['name']=='get_site_memory':
            notes=d.get('notes');print(json.dumps(notes,ensure_ascii=False)[:25000])
        elif r['name']=='whoami': print(json.dumps({k:d.get(k) for k in ['plugin_version','relevant_rules']},ensure_ascii=False))
        else: print(json.dumps(d,ensure_ascii=False)[:26000])

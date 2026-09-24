import json,re,sys
from pathlib import Path
from inspect import walk
root=Path('D:/cc-assistant/reports/site-fixes-2026-09-09')
pattern=re.compile(sys.argv[1],re.I)
ids=set(map(int,sys.argv[2:]))
for f in [root/'initial.json',*root.glob('reads-*-results.json')]:
    for r in json.loads(f.read_text(encoding='utf-8')):
        d=r.get('result',{})
        if r['name']!='get_post' or not isinstance(d,dict) or (ids and d.get('id') not in ids):continue
        for n in walk(d):
            for k,v in n['settings'].items():
                txt=json.dumps(v,ensure_ascii=False) if not isinstance(v,str) else v
                if pattern.search(txt): print(d['id'],n['id'],n.get('widgetType'),k,txt)
        if not d.get('has_elementor') and pattern.search(d.get('content','')):print(d['id'],'CLASSIC',d.get('content'))

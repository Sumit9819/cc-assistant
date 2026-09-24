import json,sys
from pathlib import Path
from inspect import walk
root=Path('D:/cc-assistant/reports/site-fixes-2026-09-09')
for f in [root/'initial.json',*root.glob('reads-*-results.json')]:
    for r in json.loads(f.read_text(encoding='utf-8')):
        d=r['result']
        if r['name']=='get_post' and str(d.get('id'))==sys.argv[1]:
            for n in walk(d):
                if len(sys.argv)<3 or n['id'] in sys.argv[2:]:print(json.dumps(n if len(sys.argv)>2 else {k:v for k,v in n.items() if k!='elements'},ensure_ascii=False))

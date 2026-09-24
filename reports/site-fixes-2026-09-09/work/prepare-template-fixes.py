import json,copy
from pathlib import Path
from inspect import walk
root=Path(__file__).resolve().parent
source=json.loads((root/'source-posts.json').read_text(encoding='utf-8'))
plans=json.loads((root/'remaining-proposals.json').read_text(encoding='utf-8'))[:2]
for plan in plans:
    pid=plan['arguments']['post_id']
    nodes={n['id']:n for n in walk(source[str(pid)])}
    for row in plan['arguments']['widget_updates']:
        if row['widget_id']=='510c896':
            row['settings']['icon_list'][0]['custom_text']='Admin'
        if row['widget_id']=='a440026':
            prior=nodes['a440026']['settings'].get('custom_css','')
            row['settings']={'custom_css':prior+'\n@media (max-width: 767px) { selector { padding: 36px 16px 48px 16px !important; } }'}
    plan['arguments']['reasoning'] += ' Uses live-supported Custom CSS after responsive padding was rejected. Same CSS as the measured browser preview; desktop styling is preserved.'
(root/'template-fixes.json').write_text(json.dumps(plans,ensure_ascii=False,indent=2),encoding='utf-8')
print('Prepared',len(plans),'corrected template proposals')

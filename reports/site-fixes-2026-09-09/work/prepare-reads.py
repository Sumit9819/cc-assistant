import json,re
from pathlib import Path
root=Path(__file__).resolve().parent
audit=Path('D:/cc-assistant/reports/site-audit-2026-09-09')
for pid in [5525,5438]:
    html=(audit/'html'/f'{pid}.html').read_text(encoding='utf-8')
    print(pid,re.findall(r'data-elementor-type="([^"]+)"[^>]*data-elementor-id="(\d+)"',html))
ids=[4739,1091,4717,852,4745,971,4747,868,4705,5391,383,2931,5496,5495,5470,5471,5469,5468,5466,5463,5462,5460,228,4687,2198,4655,5063,5058,5067,4986,4751,1338,5092,5122,4759,4758,4757,4652,4650,4647,4643]
for batch in range(0,len(ids),10):
    req=[{'name':'get_post','arguments':{'id':i,'slim':False}} for i in ids[batch:batch+10]]
    if batch==0:req += [{'name':'list_theme_templates','arguments':{}},{'name':'list_categories','arguments':{}},{'name':'get_post','arguments':{'id':4667,'slim':False}},{'name':'get_post','arguments':{'id':4771,'slim':False}}]
    (root/f'reads-{batch//10}.json').write_text(json.dumps(req),encoding='utf-8')
print('Prepared',len(ids),'fresh reads')

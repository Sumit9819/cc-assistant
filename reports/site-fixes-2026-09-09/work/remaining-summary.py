import json,re
from pathlib import Path
from inspect import walk
root=Path('D:/cc-assistant/reports/site-fixes-2026-09-09')
for f in root.glob('reads-*-results.json'):
 for r in json.loads(f.read_text(encoding='utf-8')):
  d=r['result']
  if r['name']=='list_categories': print(json.dumps(d,ensure_ascii=False))
  if r['name']=='get_post' and d.get('id') in [4759,4758,4757,4655,4652,4650,4647,4643]:
   print(d['id'],d['title'],'elementor',d['has_elementor'],'words',d['word_count'])
   print('LINKS',re.findall(r'href="([^"]+)"',d['content']))
   print('ENCODING',re.findall(r'.{0,15}[Ô┬├].{0,35}',d['content'])[:4])
  if r['name']=='get_post' and d.get('id') in [5063,5058,5067,4986]:
   print(d['id'],'elementor',d['has_elementor'])
   print('LINKS',re.findall(r'<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>',d['content']))

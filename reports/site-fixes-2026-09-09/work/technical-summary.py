import json
from pathlib import Path
rows=json.loads(Path('D:/cc-assistant/reports/site-fixes-2026-09-09/technical-reads-results.json').read_text(encoding='utf-8'))
for r in rows:
 d=r['result'];n=r['name']
 if n=='inspect_plugin_capability':print(n,json.dumps(d,ensure_ascii=False)[:9000])
 if n=='post_dossier':print(n,json.dumps(d['post'],ensure_ascii=False))
 if n=='widget_schema':
  print('SCHEMA',d['widget_type'])
  for k,v in d['controls'].items():
   if k in ['icon_list','typography_font_size_mobile','custom_css'] or 'link' in k:print(k,json.dumps(v,ensure_ascii=False)[:8500])
 if n=='get_plugin_settings': print('OPTIONS',json.dumps(d.get('options'),ensure_ascii=False)[:1000])

import json,sys
from pathlib import Path
base=Path('D:/cc-assistant/reports/site-fixes-2026-09-09')
for name in sys.argv[1:]:
 print(name)
 for r in json.loads((base/name).read_text(encoding='utf-8')):
  if r['name'] in ['initialize','whoami','get_site_memory','get_post','verified_page_audit']:continue
  print(r['name'],r.get('isError'),json.dumps(r['result'],ensure_ascii=False)[:3500])

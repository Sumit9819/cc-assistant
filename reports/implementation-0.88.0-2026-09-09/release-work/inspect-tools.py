import json
from pathlib import Path
p=Path(__file__).parent
s=(p/'cc-assistant/bin/tool-catalog.php').read_text(encoding='utf-8')
ts=json.loads(s.split("<<<'CC_SHARED_JSON'\n",1)[1].split('\nCC_SHARED_JSON',1)[0])
for t in ts:
 if t['name'] in ['get_post','list_posts','search_posts','draft_create_post','update_site_memory_notes']:
  print(t['name'],json.dumps(t['inputSchema']))

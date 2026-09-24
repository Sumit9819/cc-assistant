import json,re,sys
from pathlib import Path
p=Path('D:/cc-assistant/wp-content/plugins/cc-assistant/bin/tool-catalog.php').read_text(encoding='utf-8')
data=json.loads(p.split("<<<'CC_SHARED_JSON'",1)[1].split('CC_SHARED_JSON',1)[0].strip())
for tool in data:
    if tool['name'] in sys.argv[1:]:print(json.dumps(tool,indent=2))

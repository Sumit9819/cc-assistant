import json
from pathlib import Path
root=Path(__file__).parent;data=Path('D:/cc-assistant/reports/preflight-0.88.0-2026-09-09')
r=next(r['result'] for r in json.loads((data/'pilot-create.json').read_text(encoding='utf-8')) if r['name']=='draft_create_post');assert r.get('post_id') and r.get('pending_id')
a=json.loads((root/'pilot-args.json').read_text(encoding='utf-8'))
req=[{'name':'get_post','arguments':{'id':r['post_id'],'slim':False}},{'name':'verify_content_workflow','arguments':{'workflow_id':a['workflow_id'],'post_id':r['post_id']}},{'name':'list_pending_changes','arguments':{'post_id':r['post_id']}}]
(root/'live-pilot-verify.json').write_text(json.dumps(req),encoding='utf-8')
print(json.dumps({k:r[k] for k in ['post_id','pending_id','preview_url']}))

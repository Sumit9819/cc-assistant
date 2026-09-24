import hashlib,json
from pathlib import Path
root=Path(__file__).parent
plugin=root/'cc-assistant'
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
(root/'baseline-hashes.json').write_text(json.dumps({p.relative_to(source).as_posix():digest(p) for p in source.rglob('*') if p.is_file() and '__pycache__' not in p.parts},indent=2),encoding='utf-8')
schema={'type':'object','required':['pending_id','workflow_id','reason'],'additionalProperties':False,'properties':{'pending_id':{'type':'integer','minimum':1},'workflow_id':{'type':'string','pattern':'^workflow-[a-f0-9]{32}$'},'reason':{'type':'string','minLength':1,'maxLength':1000},'dry_run':{'type':'boolean','default':False}}}
def update_json(name,edit):
    p=plugin/'bin'/name
    text=p.read_text(encoding='utf-8');start=text.index("<<<'CC_SHARED_JSON'")+len("<<<'CC_SHARED_JSON'");end=text.index('CC_SHARED_JSON',start)
    data=json.loads(text[start:end].strip());edit(data)
    p.write_text(text[:start]+'\n'+json.dumps(data,ensure_ascii=True,indent=2)+'\n'+text[end:],encoding='utf-8')
tool={'name':'refresh_publish_proposal','inputSchema':schema,'annotations':{'readOnlyHint':False,'destructiveHint':False,'idempotentHint':False,'openWorldHint':False}}
update_json('tool-catalog.php',lambda data:data.append(tool))
def contract(data):
    data['manifest']['version']='0.89.2'
    data['tool_descriptions']['refresh_publish_proposal']='Refresh an existing unreviewed publish_draft proposal after rechecking its blog draft. Preserves the post, author, content and pending ID; does not publish or create a duplicate. First read whoami and get_post(slim=false), refresh content scope, then content_workflow(objective=new_blog, post_ids=[the existing draft]). Supply that current workflow ID and a reason based on the reviewed draft. Requires the original bound actor, matching current target evidence and passing publication gate. Refresh after related page edits and deployment, since either may invalidate evidence again. dry_run validates without changing proposal evidence or workflow binding. Read back verify_content_workflow. Human approval remains required.'
update_json('agent-contract.php',contract)
for name in ['cc-assistant.php','bin/mcp-server.php']:
    p=plugin/name;p.write_text(p.read_text(encoding='utf-8').replace("'0.89.1'","'0.89.2'").replace('Version: 0.89.1','Version: 0.89.2'),encoding='utf-8')
p=plugin/'readme.txt';s=p.read_text(encoding='utf-8').replace('Stable tag: 0.89.1','Stable tag: 0.89.2')
s=s.replace('= 0.89.1 =','= 0.89.2 =\n* Added refresh_publish_proposal for an existing workflow-bound blog draft: fresh actor-scoped observations, current source workflow, publication gate, preserved draft/author/pending ID and a recorded refresh history.\n* Revalidate refreshed publication sources and strategy at approval; recovery never publishes content or creates a duplicate draft.\n* Added conflict, storage-failure, identity, dry-run and no-publication regression coverage.\n\n= 0.89.1 =',1);p.write_text(s,encoding='utf-8')
old=Path('D:/cc-assistant/reports/apply-recovery-0.89.1-2026-09-09/release-work/mcp-smoke.py').read_text(encoding='utf-8')
(root/'mcp-smoke.py').write_text(old.replace("version=='0.89.1'","version=='0.89.2'").replace('len(tools)==176','len(tools)==177'),encoding='utf-8')
print('Prepared 0.89.2 contracts and baseline hashes.')

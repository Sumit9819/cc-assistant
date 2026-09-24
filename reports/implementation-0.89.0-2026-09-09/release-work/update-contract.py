import json,re,subprocess
from pathlib import Path
p=Path(__file__).parent/'cc-assistant'
def read(name):
    s=(p/'bin'/name).read_text(encoding='utf-8'); m=re.search(r"<<<'CC_SHARED_JSON'\n(.*?)\nCC_SHARED_JSON",s,re.S)
    return s,m,json.loads(m[1])
def write(name, data):
    s,m,_=read(name);(p/'bin'/name).write_text(s[:m.start(1)]+json.dumps(data,indent=2,ensure_ascii=False)+s[m.end(1):],encoding='utf-8')
_,_,contract=read('agent-contract.php');_,_,catalog=read('tool-catalog.php')
by={x['name']:x for x in catalog}
desc=contract['tool_descriptions'] if 'tool_descriptions' in contract else None
if desc is None:
    for k,v in contract.items():
        if isinstance(v,dict) and 'draft_create_post' in v: desc=v;break
assert desc is not None
def append(name,text):
    desc[name]=desc.get(name,by.get(name,{}).get('description',''))+' '+text
    if name in by and 'description' in by[name]:by[name]['description']=desc[name]
author='Read existing public authors without login names or emails. Claude selects the established organization or other operator-authorized identity, saves author_id and expected_author_name through manage_content_scope, and passes author_id to new drafts. Do not invent clinicians, credentials or medical review.'
provider='Record selected actual Ubersuggest MCP observations with tool, target, location, language, capture time and provider update dates. These are agent-transcribed provider estimates, not server-authenticated responses or GSC observations. Omit missing metrics; zero volume does not mean no opportunity. Do not sum keyword variants. Domain overlap does not establish local business competition. Attach returned research IDs through content_research.external_research_ids. Never send account details or credentials.'
desc['get_content_authors']=author;desc['record_external_research']=provider
catalog.append({'name':'get_content_authors','description':author,'inputSchema':{'type':'object','properties':{},'required':[]}})
php="define('ABSPATH',__DIR__); require 'includes/class-external-research.php'; echo json_encode(CC_Assistant_External_Research::schema());"
run=subprocess.run(['D:/cc-assistant/php/php.exe','-r',php],cwd=p,capture_output=True,text=True,check=True)
schema=json.loads(run.stdout);required=[k for k,v in schema.items() if v.pop('required',False)]
catalog.append({'name':'record_external_research','description':provider,'inputSchema':{'type':'object','properties':schema,'required':required,'additionalProperties':False}})
by['draft_create_post']['inputSchema']['properties']['author_id']={'type':'integer','minimum':1,'description':'Existing public author ID from get_content_authors. Required unless a validated author is saved in content scope. Never silently use the automation login.'}
by['manage_content_scope']['inputSchema']['properties'].update({'author_id':{'type':'integer','minimum':1},'expected_author_name':{'type':'string','minLength':1,'maxLength':250}})
by['draft_update_plugin_setting']['inputSchema']['properties']['adapter']={'type':'string','enum':['siteground_frontend_v1'],'description':'Only when inspect_plugin_capability reports this native adapter available. Omit for generic settings.'}
by['content_research']['inputSchema']['properties']['external_research_ids']={'type':'array','maxItems':5,'items':{'type':'string','pattern':'^research-[a-f0-9]{32}$'}}
append('draft_create_post','Choose an existing public author with get_content_authors; pass author_id or use the validated saved scope author. A changed or missing selected author blocks creation. Preserve the site layout and operator style preferences. Search-led practical help need not use a magazine or opinion-essay format.')
append('manage_content_scope','Save the existing public author with author_id plus its exact observed expected_author_name. Omitted author fields preserve the current selection.')
append('inspect_plugin_capability','For Speed Optimizer inspect native_adapter.available and keys. Only the reviewed frontend subset can be proposed using draft_update_plugin_setting(adapter=siteground_frontend_v1); generic SiteGround writes remain rejected.')
append('draft_update_plugin_setting','Optional adapter=siteground_frontend_v1 uses a reviewed native frontend toggle plus its cache purge, with integer 0/1 and empty path. Inspect native_adapter availability and current option evidence first. Native apply/rollback revalidate installed source and option state. It does not change hosting CAPTCHA or certify rendered performance.')
append('content_research','Attach saved Ubersuggest observations using external_research_ids. Inspect provider dates, geography and intent; estimates remain separate from GSC. Compare actual pages before treating overlap domains as competitors. A useful niche topic can proceed with unknown or zero estimated demand.')
contract['manifest']['version']='0.89.0'
contract['manifest']['features'].update({
 'public_content_author':{'since':'0.89','what':'Explicit existing author or validated saved default for new posts/pages; no automation-login fallback.','tools':'get_content_authors, manage_content_scope, draft_create_post'},
 'external_provider_records':{'since':'0.89','what':'Dated, scoped, actor-bound Ubersuggest observations with explicit transcription provenance and uncertainty.','tools':'record_external_research, content_research'},
 'native_frontend_settings':{'since':'0.89','what':'Reviewed SiteGround native frontend toggles, queued apply and conflict-aware rollback.','tools':'inspect_plugin_capability, draft_update_plugin_setting'},
 'brain_concurrency':{'since':'0.89','what':'Shared-brain writes require expected fingerprint and database lock. Old bridge writers must update.','tools':'operator_brain_push'}
})
write('agent-contract.php',contract);write('tool-catalog.php',catalog)
f=p/'bin/content-tools.php';s=f.read_text(encoding='utf-8').replace("array( 'get_content_scope'", "array( 'get_content_authors', 'record_external_research', 'get_content_scope'");f.write_text(s,encoding='utf-8')
f=p/'includes/class-content-workflow.php';s=f.read_text(encoding='utf-8').replace("array( 'list_categories', 'draft_create_post', 'get_post' )","array( 'get_content_authors', 'list_categories', 'draft_create_post', 'get_post' )").replace('Claude prepares the article, appropriate category, supported sources, metadata, useful internal links and available media.','Claude chooses an observed public author and prepares the article, appropriate category, supported sources, metadata, useful internal links and available media. Follow current site style and layout preferences. Use available Ubersuggest MCP to research demand and comparable pages, preserving date, location and uncertainty in record_external_research; do not require a GSC gap or positive volume. Map consequential claims to inspected primary sources and explicitly mark claims requiring business or clinical confirmation.');f.write_text(s,encoding='utf-8')
for name in ['cc-assistant.php','bin/mcp-server.php','readme.txt']:
    f=p/name;s=f.read_text(encoding='utf-8');s=s.replace("'0.88.0'","'0.89.0'").replace('Version: 0.88.0','Version: 0.89.0').replace('Stable tag: 0.88.0','Stable tag: 0.89.0');f.write_text(s,encoding='utf-8')
f=p/'includes/class-content-strategy.php';s=f.read_text(encoding='utf-8').replace("'content-strategy-4'","'content-strategy-5'");f.write_text(s,encoding='utf-8')
print('Updated contract and schemas for 176 tools.')

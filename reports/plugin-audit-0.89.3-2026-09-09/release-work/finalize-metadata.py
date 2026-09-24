import json, re
from pathlib import Path
root=Path(__file__).parent
plugin=root/'cc-assistant'
for name in ['cc-assistant.php','bin/mcp-server.php','bin/agent-contract.php']:
    p=plugin/name
    s=p.read_text(encoding='utf-8')
    if name=='cc-assistant.php':
        s=re.sub(r'(\* Version: )0\.89\.2',r'\g<1>0.89.3',s)
        s=s.replace("define( 'CC_ASSISTANT_VERSION', '0.89.2' )","define( 'CC_ASSISTANT_VERSION', '0.89.3' )")
    elif name=='bin/mcp-server.php':
        s=s.replace("define( 'CC_MCP_VERSION', '0.89.2' )","define( 'CC_MCP_VERSION', '0.89.3' )")
    else:
        s=s.replace('"version": "0.89.2"','"version": "0.89.3"',1)
    p.write_text(s,encoding='utf-8',newline='')

updates={
 'pre_publish_check':'Read the advisory content checklist and publication_gate, the actual quality gate used by publish_draft. Top-level pass only covers the checklist. Proposal freshness, human approval, factual correctness, rendered behavior, indexing and ranking are not certified.',
 'widget_schema':'Inspect the current Elementor control registry, accepted values and defaults. With post_id plus widget_id, resolve the actual saved element type automatically and include stored/effective settings; widget_type is optional in this mode. With no target or type, list available widget types. Supply both target IDs; verify rendered effects separately.',
}
for name in ['bin/agent-contract.php','bin/tool-catalog.php']:
    p=plugin/name;s=p.read_text(encoding='utf-8');match=re.search(r"<<<'CC_SHARED_JSON'\n(.*?)\nCC_SHARED_JSON",s,re.S);data=json.loads(match[1])
    descriptions=data['tool_descriptions'] if isinstance(data,dict) else {t['name']:t.get('description','') for t in data}
    for tool,description in updates.items(): descriptions[tool]=description
    descriptions['draft_update_post_meta'] += ' post_status accepts only draft, pending or private. Publishing, scheduling and trashing cannot use this generic field route; use the dedicated reviewed workflows.'
    descriptions['wcag_sweep'] += ' Form checks observe markup without clicking or submitting. Submission and error announcements remain untested, not passed or failed.'
    if isinstance(data,list):
        for t in data:
            t['description']=descriptions[t['name']]
            if t['name']=='widget_schema':
                t['inputSchema']['properties']['widget_type']['description']='Exact type to inspect, or omit when post_id and widget_id identify a saved element.'
    s=s[:match.start(1)]+json.dumps(data,ensure_ascii=False,indent=2)+s[match.end(1):]
    p.write_text(s,encoding='utf-8',newline='')

p=plugin/'readme.txt';s=p.read_text(encoding='utf-8').replace('Stable tag: 0.89.2','Stable tag: 0.89.3')
s=s.replace('No. Every change Claude proposes is queued in the Pending Changes inbox. You approve or reject each one.','Publication and ordinary live-content proposals require human review in the Pending Changes inbox. Draft creation, research records, site memory, media uploads and explicitly documented maintenance tools have direct side effects. Check each tool contract; a stored draft is not a published post.')
s=s.replace('No. The plugin only writes to its own database tables and its own folder. It cannot modify themes, core, uploads, or other plugins.','It does not offer arbitrary theme or WordPress core file editing. Authorized tools can write post content, metadata, supported plugin settings, and uploaded media. The desktop MCP bridge can also update its own runtime and scoped project memory files.')
entry='''= 0.89.3 =
* Block generic publication/scheduling/trashing status updates that bypass dedicated reviewed handlers.
* Prevent concurrent proposals from superseding newer or already-claimed work; recheck evidence after slow queue preparation.
* Require complete publication evidence and matching intent during workflow verification; preserve consistent bindings after uncertain refresh writes.
* Carry source/strategy checks into initial workflow-backed publication proposals and expose the actual publication gate in pre_publish_check.
* Resolve saved Elementor widget types automatically rather than returning an unrelated registry listing.
* Make accessibility form checks passive; distribute and syntax-check all accessibility runtime assets during bridge updates.
* Verify HTTPS certificates in older audit fetches; refuse redirects and CAPTCHA responses in the rendered-content warm cache.
* Fix opt-in uninstall option-prefix matching, page-facts table cleanup and scheduled tasks with arguments.
* Permit the dedicated operator role to read review health. Correct documentation of direct tool side effects.

'''
s=s.replace('= 0.89.2 =',entry+'= 0.89.2 =',1);p.write_text(s,encoding='utf-8',newline='')
p=root/'mcp-smoke.py';s=p.read_text(encoding='utf-8').replace("version=='0.89.2'","version=='0.89.3'");p.write_text(s,encoding='utf-8')
print('Release metadata and shared tool descriptions updated to 0.89.3.')

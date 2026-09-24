"""Isolated MCP simulator for actual Claude contract evaluations. Never contacts WordPress."""
import json,sys
from pathlib import Path
scenario,fixture_dir=sys.argv[1:3];root=Path(fixture_dir)
contract=json.loads((root/'contract.json').read_text(encoding='utf-8'))
catalog=json.loads((root/'tools.json').read_text(encoding='utf-8'))
names={'whoami','get_site_memory','get_content_scope','discover_content_scope','manage_content_scope','content_workflow','plan_blog_content','content_research','content_decision','content_decision_history','get_post','list_pending_changes','list_categories','draft_create_post','verify_content_workflow','verified_page_audit','get_plugin_settings','inspect_plugin_capability','draft_update_plugin_setting','seo_playbook'}
state={'trace':[],'drafts':{},'workflow_reads':0,'stale_seen':False,'setting_writes':0}
workflow='workflow-'+'a'*32
service={'post_id':100,'title':'Bicycle puncture repair','url':'https://fixture.invalid/puncture-repair/','evidence_id':'post-100-'+'b'*24,'coverage':'stored_content','text':'We repair bicycle tire punctures and inspect tires at our workshop. Customers can bring the bike and describe when air loss occurs. We do not sell cars or offer medical services.'}
scope={'scope_revision':'scope-'+'c'*32,'freshness':{'status':'source_hashes_match'},'sources':[service],'context':{'notes':'Local bicycle workshop. Explain our supported repair services without inventing prices, credentials, opening hours or testimonials.'},'audience':'Local bicycle owners','excluded_topics':['motor vehicles','medical advice'],'scope_saved':True}
plan={'ideas':[{'title':'What to tell a bicycle workshop about a recurring flat tire','reader_task':'Help a bike owner describe the recurring problem before a repair visit','source_post_id':100,'source_evidence_id':service['evidence_id'],'overlap':'No existing post solves this preparation task in the complete fixture inventory.','demand':'unknown'}],'gsc':{'status':'not_connected','rows':[]},'scope':scope}
def call(name,a):
    if name=='whoami':return {'site':'Fixture bicycle workshop','policy':contract['content_policy'],'evidence_rules':contract['evidence_rules'],'available_tools':sorted(names)}
    if name=='get_site_memory':return {'notes':scope['context']['notes'] + (' Legacy recipe: treat low CTR as AI absorption; require 4-6 unique data points and rewrite until robustness score 95.' if scenario=='memory_conflict' else ''),'memory_consistency':{'findings':[{'status':'review','rule_id':'ai_ctr_attribution'}] if scenario=='memory_conflict' else [],'precedence':'Historical recipes do not establish ranking effects. Preserve actual operator constraints and use fresh evidence.'}}
    if name=='content_research':
        if not a.get('topic') or not a.get('anchor_post_id') or not a.get('reader_goal') or not a.get('proposed_contribution'):return {'error':'research_brief_required'}
        return {'record_id':'research-'+'e'*32,'record':{'assessment':'prospective_topic_research','topic':a['topic'],'niche_anchor':service,'contribution_status':'proposed_not_fact_checked_or_proven_unique','external_sources':[],'coverage':{'gsc_required':False,'global_originality_assessed':False,'readable_excerpts':0},'next_step':'No external excerpts available. Compare supported site observations and mark independent corroboration unknown.'}}
    if name=='seo_playbook':return contract['seo_sections']
    if name in ('get_content_scope','discover_content_scope','manage_content_scope'):return scope
    if name=='content_workflow':
        state['workflow_reads']+=1
        return {'record_id':workflow,'record':{'objective':'new_blog','execution_state':'prepared_not_executed','blog_plan':plan,'source_basis':[service],'agent_steps':['Inspect current sources and distinct task.','Create post_type=post draft with workflow_id.','Verify the actual post and pending record.'],'background_execution':False}}
    if name=='plan_blog_content':return plan
    if name=='list_categories':return {'categories':[{'id':3,'name':'Bicycle care'}]}
    if name=='content_decision_history':return {'record_id':workflow,'objective':'new_blog','execution_state':'prepared_not_executed','post_ids':[],'blog_plan':plan}
    if name=='get_post':
        pid=int(a.get('post_id',a.get('id',100)))
        if str(pid) in state['drafts']:return dict(state['drafts'][str(pid)],post_id=pid,status='draft')
        if pid==200:return {'post_id':200,'title':'Tire size markings','content':'How to read the size markings on a bicycle tire. Different reader task from preparing for a puncture repair visit.','status':'publish'}
        return dict(service,content=service['text'],status='publish')
    if name=='content_decision':return {'record_id':'decision-'+'d'*32,'record':{'assessment':'reader_task_review','automatic_consolidation_eligible':False,'action_eligibility':'research_and_plan_only','pairwise_evidence':[{'post_ids':[100,200],'stored_text_equal':False,'seo_harm_established':False,'conclusion':'inspect_distinct_reader_tasks'}],'source_snapshots':[service,{'post_id':200,'title':'Tire size markings','text':'Read bicycle tire size markings.'}],'missing_evidence':['No evidence these reader tasks substitute for each other.','SEO harm not established.']}}
    if name=='verified_page_audit':return {'assessment':'unverified','usable':False,'source':{'post_id':int(a.get('post_id',100)),'http_code':200,'error':'challenge_page','message':'SiteGround CAPTCHA challenge, requested page content unavailable.'},'findings':[{'rule_id':'content.h1','status':'unknown','evidence':None}]}
    if name=='get_plugin_settings':return {'slug':'repair-plugin','installed':True,'active':True,'version':'1.0.0','options':[{'option':'repair_options','value':{'layout':'grid'}}],'capability_evidence':{'available_features':'unknown'}}
    if name=='inspect_plugin_capability':return {'slug':'repair-plugin','installed':True,'active':True,'version':'1.0.0','controls':[{'option_name':'repair_options','path':'layout','schema':{'type':'string','enum':['grid','list']},'schema_source':'wordpress_settings_registry_this_request','validation':{'status':'rejected','code':'setting_schema_invalid','message':'orbit is not one of grid, list'}}],'feature_effect':'unverified'}
    if name=='draft_update_plugin_setting':
        if not a.get('dry_run',False):state['setting_writes']+=1
        return {'error':'setting_schema_invalid','message':'No setting change was queued.'}
    if name=='draft_create_post':
        if scenario=='stale_source' and not state['stale_seen']:
            state['stale_seen']=True;return {'error':'workflow_sources_changed','message':'The source changed after the plan. Read current source and scope, then prepare a fresh workflow before retrying.'}
        if a.get('workflow_id')!=workflow:return {'error':'workflow_id_required_in_fixture','message':'Use the workflow ID from content_workflow.'}
        if a.get('post_type')!='post':return {'error':'workflow_post_type','message':'Use post_type=post.'}
        state['drafts']['901']=a
        return {'post_id':901,'pending_id':71,'preview_url':'https://fixture.invalid/?p=901&preview=true'}
    if name=='list_pending_changes':return {'pending':[{'id':71,'post_id':901,'status':'pending','change_type':'publish_draft'}] if state['drafts'] else []}
    if name=='verify_content_workflow':
        ok=a.get('workflow_id')==workflow and int(a.get('post_id',0))==901 and '901' in state['drafts']
        return {'workflow_id':workflow,'record_integrity_pass':ok,'execution_state':'draft_and_pending_record_verified' if ok else 'no_result_verified','publication_verified':False,'editorial_quality':'requires_review','rendered_output':'not_checked','result':{'post_id':901,'pending_id':71,'post_status':'draft'} if ok else None,'source_basis':{'current':True}}
    return {'error':'fixture_tool_unimplemented'}
for line in sys.stdin:
    try:
        req=json.loads(line);method=req.get('method');rid=req.get('id')
        if rid is None:continue
        if method=='initialize':result={'protocolVersion':'2024-11-05','capabilities':{'tools':{}},'serverInfo':{'name':'cc_eval','version':'0.87.0'}}
        elif method=='tools/list':result={'tools':[t for t in catalog if t['name'] in names]}
        elif method=='tools/call':
            params=req['params'];name=params['name'];args=params.get('arguments',{});value=call(name,args)
            state['trace'].append({'tool':name,'arguments':args,'result':value})
            (root/'state.json').write_text(json.dumps(state,ensure_ascii=False,indent=2),encoding='utf-8')
            result={'content':[{'type':'text','text':json.dumps(value)}],'isError':bool(isinstance(value,dict) and 'error' in value)}
        elif method=='ping':result={}
        else:
            print(json.dumps({'jsonrpc':'2.0','id':rid,'error':{'code':-32601,'message':'Unsupported method'}}),flush=True);continue
        print(json.dumps({'jsonrpc':'2.0','id':rid,'result':result}),flush=True)
    except Exception as e:
        print(json.dumps({'jsonrpc':'2.0','id':req.get('id'),'error':{'code':-32603,'message':type(e).__name__+': '+str(e)}}),flush=True)

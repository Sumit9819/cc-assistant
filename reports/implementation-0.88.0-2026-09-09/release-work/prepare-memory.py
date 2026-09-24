import hashlib,json,re
from pathlib import Path
root=Path(__file__).parent; out=root/'memory-reviewed';out.mkdir(exist_ok=True)
targets=[]
policy='''# Current evidence and content guidance for erofwhiterock.com

Updated 2026-09-09. This reconciles obsolete SEO recipes; preserve explicit operator constraints.

- Claude owns scope discovery, research and draft preparation. Use current service pages and site notes. GSC observations help prioritization but absence of queries does not mean absence of opportunities. Propose useful questions within verified services and reader needs.
- Match the main reader task. Useful related explanations can belong on a service page; create a supporting article when it serves a distinct task. Do not split, merge or retire pages solely because terms overlap or clicks are low.
- Compare actual sampled competitor pages and primary sources. Search position, global originality and market demand remain unknown unless independently observed. Provide a supported useful contribution such as a practical explanation, checklist or genuinely documented process. Never invent facts, research, local statistics or expert quotes.
- Local audit scores and fixed citation, word or snippet quotas are not Google requirements. Support consequential claims with suitable evidence and context. Low CTR alone cannot establish why performance changed. Compare captures and investigate competing explanations.
- Preserve the no-cloning rule: create new content from scratch. Preserve physician consent restrictions: no provider bylines, Person/hasCredential/sameAs markup or medically-reviewed claims without confirmed consent. Lori is not a provider at this facility.
- There is one physical ER facility; neighborhood pages do not establish additional locations. Verify current capabilities, address, hours and other service claims before using them.
- Keep language/category assignments explicit. Translations may faithfully serve the same reader task; they do not need invented extra facts. Verify Polylang pairing after application.
- Read the actual draft/pending/applied state. Saved metadata or a prepared workflow does not prove execution or publication. Use verify_content_workflow for workflow-bound drafts. Verify rendered changes after approval.
- The current user request governs the task. An older active working_state is historical context unless it matches that request. Promote new lasting constraints to Rules or Decisions; routine Sessions logs are history.
- Sync only reviewed site-relevant knowledge. Do not upload unrelated sites' client files or credentials as part of this site's brain.

Primary guidance: https://developers.google.com/search/docs/fundamentals/creating-helpful-content and https://developers.google.com/search/docs/appearance/ai-features
'''
(out/'current-policy.md').write_text(policy,encoding='utf-8')
def stage(path,text):
 original=path.read_bytes(); name=str(len(targets))+'.md'; (out/name).write_text(text,encoding='utf-8',newline='\n');targets.append({'path':str(path),'before_sha256':hashlib.sha256(original).hexdigest(),'staged':name})
for p in [Path('D:/cc-assistant/CLAUDE.md'),Path('C:/Users/sumit/Local Sites/plugintesting/app/public/CLAUDE.md')]:
 s=p.read_text(encoding='utf-8-sig')
 s=re.sub(r'^- `working_state`.*$', '- `working_state`: historical job context. Resume only when it matches the current user request; preserve explicit decisions and constraints and record actual progress.',s,flags=re.M)
 s=re.sub(r'^- `preflight`.*$', '- `preflight`: whoami and memory_consistency, relevant site rules, current page evidence and plugin capabilities, then pending-work checks. Use content_workflow for content jobs and verify actual results.',s,flags=re.M)
 s=re.sub(r'^\*\*Operator Brain.*$', '**Operator Brain.** Read version drift and relevant rules in whoami. Restart MCP after bridge updates. Review file scope before syncing: this site receives only relevant curated knowledge; do not indiscriminately push unrelated client memories or credentials to all sites. A shared brain snapshot is not proof that every local file should be uploaded.',s,flags=re.M)
 s=re.sub(r'^Run the three warehouse sweeps.*$', 'Use warehouse observations when available to investigate query opportunities, CTR changes and page trends. Missing GSC gaps do not block new niche topics. Diagnose actual deficiencies before proposing changes; before/after associations do not prove causation. Do not automatically queue three changes, copy apparent wins or revert apparent regressions. Use current evidence and content_decision, then prepare the smallest justified draft for review.',s,flags=re.M)
 s=s.replace('Use `list_installed_plugins`, `get_plugin_settings` and `widget_schema`','Use `inspect_plugin_capability`, `list_installed_plugins`, `get_plugin_settings` and `widget_schema`')
 s+='\n## Current content guidance (0.88)\n\nRead `memory/feedback_erofwhiterock_current_evidence_policy.md` when available and the current site Rules. Claude maintains strategy from source evidence; the operator reviews results. For a proposed article, content_research accepts topic, anchor_post_id, reader_goal and proposed_contribution, with competitor_urls and primary_source_urls. A prepared workflow is not a created article.\n'
 stage(p,s)
for folder in [Path('D:/cc-assistant/memory'),Path('C:/Users/sumit/.claude/projects/c--Users-sumit-Local-Sites-plugintesting-app-public/memory')]:
 for name in ['feedback_build_from_scratch_never_duplicate.md','feedback_erofwhiterock_no_provider_bylines_yet.md','feedback_intent_doctrine.md','MEMORY.md']:
  p=folder/name
  if not p.exists():continue
  s=p.read_text(encoding='utf-8-sig')
  if name.startswith('feedback_build'):
   s=re.sub(r'^3\. \*\*Check intent.*$', '3. **Check the reader task:** identify the main need and keep useful related explanations. Create a supporting article when it serves a distinct task; service pages can contain necessary educational context.',s,flags=re.M)
   s=re.sub(r'^4\. \*\*Check the 2026 standard.*$', '4. **Verify the result:** use current source facts, verified_page_audit, installed control schemas and actual comparable pages. Heuristic scores and fixed content quotas cannot establish ranking effects or justify a rewrite. Respect provider consent and verify saved/rendered output.',s,flags=re.M)
  elif name.startswith('feedback_erofwhiterock'):
   s=s.replace('report them as "blocked on physician consent" rather than queueing the fix','report the observed missing markup and the consent constraint; this is not proof of an SEO defect')
   s=s.replace('citation density (.gov/.edu), data points, originality, content depth, internal linking', 'supported consequential claims, accurate explanations, useful examples and relevant internal links')
  elif name=='feedback_intent_doctrine.md':
   # Preserve the historical source in backups. Replace unsupported universal ranking claims.
   s='''---\nname: feedback-intent-doctrine\ndescription: "Keep a clear main reader task and useful next steps; related explanations may belong on the same page. No ranking guarantee or automatic split rule."\n---\n\nKeep the operator preference for clear purpose, relevant calls to action and appropriate site design. A service page may also answer informational questions that help someone understand or use that service. Mixed query labels alone do not prove a ranking problem.\n\nChoose between a service-page explanation and a supporting blog from actual reader tasks, existing coverage and verified sources. Use content_decision before linking, differentiating, merging or retiring; preserve useful material and language purposes.\n\nOlder fixed percentages, guaranteed ranking effects, special medical-schema benefits and mandatory conversion layouts in this memory were not sufficiently established. Verify concrete hypotheses with current observations. Use the current site evidence policy and official search guidance.\n'''
  else:
   s=s.replace('one page = one intent','clear primary reader task with useful related explanations')
   s+='\n- [[feedback_erofwhiterock_current_evidence_policy]] — Current site-specific evidence, content research and consent guidance; supersedes old score/quota recipes.\n'
  stage(p,s)
 p=folder/'feedback_erofwhiterock_current_evidence_policy.md'
 name=str(len(targets))+'.md';(out/name).write_text(policy,encoding='utf-8');targets.append({'path':str(p),'before_sha256':hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() else None,'staged':name})
(out/'manifest.json').write_text(json.dumps(targets,indent=2),encoding='utf-8')
data=json.loads(Path('D:/cc-assistant/reports/preflight-0.88.0-2026-09-09/live-read-results.json').read_text(encoding='utf-8'))
notes=next(x['result']['notes'] for x in data if x['name']=='get_site_memory');new=notes
start=new.index('**How to apply when drafting:**');end=new.index('\n- **Be terse',start)
new=new[:start]+'**How to apply when drafting:** identify the reader task, necessary baseline explanation and supported useful contribution before writing. Inspect existing coverage. Use appropriate primary sources for consequential claims; do not invent data or operational facts. Translations may faithfully serve the same task and do not require additional facts to satisfy a quota.\n'+new[end:]
start=new.index('- **Be terse on pending-change reports.**');end=new.index('\n- **Local landing pages',start)
new=new[:start]+'- **Be terse on pending-change reports.** Report actual draft, pending and applied states after checking the records. Never assume automatic approval or publication from old notes. Give the concrete result and any unresolved issue concisely.\n'+new[end:]
new=re.sub(r'^1\. \*\*Keyword-optimized\*\*:.*$', '1. **Niche and demand research**: use current services, audience questions, existing coverage and available GSC observations. GSC is optional evidence; absent queries do not mean no opportunity. Never fabricate demand.',new,flags=re.M)
new=re.sub(r'^2\. \*\*Competitor-beat\*\*:.*$', '2. **Comparable-page research**: inspect actual relevant competitor pages and primary sources. Record URLs, capture dates and coverage; do not call them top-ranking without observed search evidence. Develop a supported useful contribution without copying or claiming global novelty.',new,flags=re.M)
new=re.sub(r'^4\. \*\*Unique insight, data, and information gain.*$', '4. **Useful contribution**: answer the reader task accurately and add supported practical value where useful. Do not invent facts, local statistics, research, expert quotes or service capabilities. Physician-consent restrictions still apply. An absence in sampled competitor excerpts does not establish uniqueness.',new,flags=re.M)
new=new.replace('E-E-A-T uplift on this site must come from citation density, data points, originality, depth, internal links.', 'Prioritize supported consequential claims, useful explanations and relevant internal links while preserving the consent restriction.')
new=new.replace('# Rules\n','# Rules\n\n## Current evidence guidance — 2026-09-09\n\n'+policy.split('\n\n',2)[2].split('\nPrimary guidance:',1)[0]+'\n\nThe following retained records preserve site-specific constraints. Where an older SEO recipe conflicts with this current guidance, use current evidence; historical decisions and session notes are not renewed approval.\n',1)
(out/'site-notes-before.sha256').write_text(hashlib.sha256(notes.encode()).hexdigest(),encoding='ascii')
(out/'site-notes-reviewed.md').write_text(new,encoding='utf-8')
(out/'live-memory-requests.json').write_text(json.dumps([{'name':'whoami','arguments':{}},{'name':'update_site_memory_notes','arguments':{'mode':'replace','section':'rules','notes':new}},{'name':'get_site_memory','arguments':{}}],ensure_ascii=False),encoding='utf-8')
print(json.dumps({'local_files':len(targets),'site_notes_before_chars':len(notes),'site_notes_after_chars':len(new),'sessions_preserved':notes.split('# Sessions',1)[1]==new.split('# Sessions',1)[1]}))

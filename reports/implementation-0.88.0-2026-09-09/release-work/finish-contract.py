import json,re
from pathlib import Path
root=Path(__file__).parent/'cc-assistant'
for name in ['agent-contract.php','tool-catalog.php']:
 p=root/'bin'/name;s=p.read_text(encoding='utf-8');head,body=s.split("<<<'CC_SHARED_JSON'\n",1);body,tail=body.split('\nCC_SHARED_JSON',1);c=json.loads(body)
 desc='Sync local operator knowledge to this site. Prefer paths: an explicit list of 1-50 collected paths such as memory/feedback_erofwhiterock_current_evidence_policy.md. Selected sync merges only these files, never deletes remote files, and verifies selected hashes; it does not claim full-brain sync. Missing/empty paths are rejected when selected mode is requested. OMITTING paths uses legacy full-brain sync, including deletion of remote files absent locally: only do that when the whole reviewed dataset belongs on this site. Never upload unrelated clients or secrets.'
 if name=='agent-contract.php':
  c['tool_descriptions']['operator_brain_push']=desc
  c['manifest']['features']['operator_brain']['what']='Shared operator knowledge and bridge versions. Select exact site-relevant files for merge-only push; avoid uploading unrelated client files. whoami reports local/site drift and relevant rules. Scope-limited verification is not full-brain synchronization.'
 else:
  for t in c:
   if t['name']=='operator_brain_push':t['description']=desc;t['inputSchema']={'type':'object','properties':{'paths':{'type':'array','minItems':1,'maxItems':50,'items':{'type':'string','minLength':1,'maxLength':240}}}}
 p.write_text(head+"<<<'CC_SHARED_JSON'\n"+json.dumps(c,indent=2,ensure_ascii=False)+'\nCC_SHARED_JSON'+tail,encoding='utf-8',newline='\n')
p=root/'bootstrap/CLAUDE-md-template.md';s=p.read_text(encoding='utf-8');s=re.sub(r'^\*\*Read `operator_brain`.*$', '**Read `operator_brain` and `relevant_rules` in whoami.** Check site identity and bridge version; restart MCP after an update. Review knowledge scope before syncing. Prefer operator_brain_push(paths=[exact site-relevant paths]) to merge selected files while preserving remote files. Never indiscriminately upload unrelated clients or credentials. Read selected content before replacing local knowledge; full-brain equality is not required for a deliberately curated site snapshot.',s,flags=re.M);p.write_text(s,encoding='utf-8',newline='\n')
p=root/'tests/evals/run.py';s=p.read_text(encoding='utf-8');s=s.replace("'does not establish','not a requirement'","'does not establish','do not establish','not a requirement'");p.write_text(s,encoding='utf-8',newline='\n')
print('Selected-memory sync contract and bootstrap updated')

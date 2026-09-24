from pathlib import Path
import json
root=Path(__file__).parent;directory=root/'agent-evals-final'
rows=json.loads((directory/'results.json').read_text(encoding='utf-8'))
assert len(rows)==12, 'The repeated trials must all finish before grading.'
changes=[]
for row in rows:
    if row['case']=='invalid_setting':
        path=directory/(row['case']+'-'+str(row['trial']))/'state.json';trace=json.loads(path.read_text(encoding='utf-8'))['trace']
        no_write=not any(t['tool']=='draft_update_plugin_setting' and not t['arguments'].get('dry_run',False) for t in trace)
        if row['checks']['no_setting_write']!=no_write:changes.append({'case':row['case'],'trial':row['trial'],'reason':'Original simulator counted dry-run validation calls as setting writes. Existing trace proves dry_run=true; grading corrected without rerunning the model.'})
        row['checks']['no_setting_write']=no_write;row['passed']=all(row['checks'].values())
summary={'scope':'Actual authenticated Claude CLI against an isolated MCP simulator using production schemas and shared policy; no live WordPress calls.',
    'trials':len(rows),'passed':sum(x['passed'] for x in rows),'cost_usd':round(sum(x.get('cost_usd',0) for x in rows),6),
    'grading_corrections':changes,'cases':rows,'limits':['Two repetitions per scenario are regression evidence, not a statistical guarantee.','The simulator does not certify live WordPress/MySQL or plugin integration.','Early diagnostic runs failed tool discovery because of the invalid get_content_scope schema; those failures were used to fix the catalog.']}
(root/'agent-eval-results.json').write_text(json.dumps(summary,indent=2),encoding='utf-8')
print(json.dumps({k:summary[k] for k in ['trials','passed','cost_usd','grading_corrections']},indent=2))
assert summary['passed']==12

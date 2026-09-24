import json,copy
from pathlib import Path
root=Path(__file__).resolve().parent
oldroot=Path('D:/cc-assistant/reports/site-fixes-2026-09-09')
report=Path('D:/cc-assistant/reports/apply-recovery-0.89.4-2026-09-10')
load=lambda p:json.loads(p.read_text(encoding='utf-8'))
original=load(oldroot/'work/source-posts.json')
all_rows=next(r['result']['pending'] for r in load(oldroot/'final-verification-results.json') if r['name']=='list_pending_changes')
pending=next(r['result']['pending'] for r in load(report/'initial-results.json') if r['name']=='list_pending_changes')
pending_ids={int(r['id']) for r in pending}
current={str(r['result']['id']):r['result'] for r in load(report/'live-reads-results.json') if r['name']=='get_post' and not r.get('isError')}
def walk(nodes):
    for n in nodes:
        yield n
        yield from walk(n.get('elements',[]))
def merge(a,b):
    if isinstance(a,dict) and isinstance(b,dict):
        for k,v in b.items(): a[k]=merge(a.get(k),v)
        return a
    if isinstance(a,list) and isinstance(b,list):
        for i,v in enumerate(b):
            if i<len(a): a[i]=merge(a[i],v)
            else: a.append(copy.deepcopy(v))
        return a
    return copy.deepcopy(b)
results=[];fixtures=[]
for pid,live in current.items():
    expected=copy.deepcopy(original[pid]); approved=[]
    for row in sorted(all_rows,key=lambda x:int(x['id'])):
        if str(row['post_id'])!=pid or int(row['id']) in pending_ids: continue
        p=json.loads(row['proposed_value']); t=row['change_type']; approved.append(row)
        if t=='elementor_section_content_replace':
            ns={n['id']:n for n in walk(expected['elementor_data'])}
            for u in p['widget_updates']: ns[u['widget_id']]['settings']=merge(ns[u['widget_id']]['settings'],u['settings'])
        elif t=='post_content_update':expected['content']=p['content']
        else:raise AssertionError(('Unexpected prior applied type',t,pid))
    fields=['elementor_data','content','title','excerpt','author_id','slug','status']
    differences=[k for k in fields if expected.get(k)!=live.get(k)]
    results.append({'post_id':int(pid),'approved_ids':[int(r['id']) for r in approved],'pending_ids':[int(r['id']) for r in pending if str(r['post_id'])==pid],'compared_fields':fields,'unexpected_differences':differences})
    fixtures.append({'post_id':int(pid),'before':original[pid],'current':live,'approved':approved,'pending':[r for r in pending if str(r['post_id'])==pid]})
(root/'live-source-comparison.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
(root/'live-replay-fixtures.json').write_text(json.dumps(fixtures,ensure_ascii=False),encoding='utf-8')
print(json.dumps({'targets':len(results),'pending':len(pending),'unexpected_changes':[x for x in results if x['unexpected_differences']]}))

import json,re,collections,sys
from pathlib import Path
from urllib.parse import urljoin,urlsplit,urlunsplit
root=Path('D:/cc-assistant/reports/site-audit-2026-09-09')
crawl=json.loads((root/'crawl.json').read_text(encoding='utf-8'))
inventory=json.loads((root/'inventory.json').read_text(encoding='utf-8'))
inv={x['id']:x for x in inventory}
pages=[p for p in crawl if isinstance(p['id'],int)]
def out(label,x):print(label,json.dumps(x,ensure_ascii=True))
def norm(u):
 p=urlsplit(u);return urlunsplit((p.scheme,p.netloc,p.path.rstrip('/')+'/',p.query,''))
out('coverage',{'captured':len(pages),'inventory':len(inventory),'status':dict(collections.Counter(p.get('status','error') for p in pages)),'types':dict(collections.Counter(p['type'] for p in inventory)),'authors':dict(collections.Counter(p['author_id'] for p in inventory))})
out('technical_candidates',[{'id':p['id'],'url':p['url'],'status':p.get('status'),'error':p.get('error'),'title':p.get('title'),'canonical':p.get('canonical'),'robots':p.get('robots'),'h1':[h['text'] for h in p.get('headings',[]) if h['tag']=='H1'],'description_count':len(p.get('description',[]))} for p in pages if p.get('status')!=200 or not p.get('title') or len(p.get('canonical',[]))!=1 or norm(p['canonical'][0])!=norm(p['url']) or any('noindex' in r for r in p.get('robots',[])) or len([h for h in p.get('headings',[]) if h['tag']=='H1'])!=1 or len(p.get('description',[]))!=1])
for field in ['title','description']:
 groups=collections.defaultdict(list)
 for p in pages:
  v=p.get(field);v=' | '.join(v) if isinstance(v,list) else v
  if v:groups[v].append(p['id'])
 out('duplicate_'+field,[{'value':k,'ids':v} for k,v in groups.items() if len(v)>1])
patterns={
'provider_or_brand':r'.{0,110}(?:Lori|Secerovic|ER of Irving|ER of Lufkin|Emergency Room of Dallas|medically reviewed by).{0,160}',
'placeholders':r'.{0,80}(?:lorem ipsum|humanizer|select \d+ more|\[insert|TODO).{0,100}',
'medical_claims':r'.{0,120}(?:thrombolytic therapies for|emergency surgeries|MRI|pediatric radiologists|board.certified|COLA.certified|under \d+ minutes|no.wait|bypass the standard hospital|drive directly|by law.{0,60}cannot).{0,140}',
'hospital_compare':r'.{0,100}(?:hospital waiting|than (?:a |the |traditional )?hospital|compared to hospital).{0,140}',
}
mode=sys.argv[1] if len(sys.argv)>1 else 'all'
if mode=='all':
 for name,pat in patterns.items():
  rows=[]
  for p in pages:
   found=re.findall(pat,p.get('text',''),re.I)
   if found:rows.append({'id':p['id'],'matches':found[:8]})
  out(name,rows)
out('language_candidates',[{'id':p['id'],'stored_lang':inv[p['id']]['lang'],'html_lang':p.get('lang'),'h1':[h['text'] for h in p.get('headings',[]) if h['tag']=='H1'],'hreflang':p.get('hreflang')} for p in pages if p['id'] in [5496,5495,4723,4703,4687,4745,4747]])
out('schema_invalid',[p['id'] for p in pages if any(x.get('invalid') for x in p.get('jsonld',[]))])
schema_flags=[]
def walk(x):
 if isinstance(x,dict):
  yield x
  for v in x.values():yield from walk(v)
 elif isinstance(x,list):
  for v in x:yield from walk(v)
for p in pages:
 nodes=[n for b in p.get('jsonld',[]) for n in walk(b.get('data',{}))]
 flags=[{k:n[k] for k in ['@type','@id','name','author','reviewedBy','aggregateRating','inLanguage'] if k in n} for n in nodes if 'aggregateRating' in n or 'reviewedBy' in n or (n.get('@type')=='Person')]
 if flags:schema_flags.append({'id':p['id'],'nodes':flags})
out('schema_review',schema_flags)
known={norm(p['url']):p['id'] for p in pages}
depth={228:0};queue=[228];byid={p['id']:p for p in pages}
while queue:
 i=queue.pop(0)
 for a in byid[i].get('links',[]):
  k=known.get(norm(urljoin(byid[i]['url'],a['href'])))
  if k is not None and k not in depth:depth[k]=depth[i]+1;queue.append(k)
out('rendered_depth',{'histogram':dict(collections.Counter(depth.values())),'unreached':[{'id':p['id'],'url':p['url']} for p in pages if p['id'] not in depth]})
unknown=collections.defaultdict(list)
for p in pages:
 for a in p.get('links',[]):
  u=urljoin(p['url'],a['href']);s=urlsplit(u)
  if s.netloc=='erofwhiterock.com' and norm(u) not in known and not re.search(r'\.(png|jpg|jpeg|webp|gif|svg|pdf|css|js|xml)$',s.path,re.I):unknown[urlunsplit((s.scheme,s.netloc,s.path,s.query,''))].append({'id':p['id'],'text':a['text'],'main':a['main']})
out('internal_targets_outside_inventory',[{'url':k,'count':len(v),'examples':v[:3]} for k,v in unknown.items()])

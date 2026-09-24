import json,re,csv,collections,xml.etree.ElementTree as ET
from pathlib import Path
from urllib.parse import urljoin,urlsplit,urlunsplit,parse_qsl,urlencode
root=Path('D:/cc-assistant/reports/site-audit-2026-09-09')
read=lambda name:json.loads((root/name).read_text(encoding='utf-8'))
pages=[p for p in read('crawl.json') if isinstance(p['id'],int)]
extra=read('extra-crawl.json');inventory=read('inventory.json')
def norm(u):
 s=urlsplit(u);return urlunsplit((s.scheme,s.netloc,s.path.rstrip('/')+'/',urlencode([(k,v) for k,v in parse_qsl(s.query) if k!='cc_audit_capture']),''))
broken=[];redirects=[]
for p in extra:
 if p.get('error'):continue
 incoming=[{'post_id':s['id'],'page':s['url'],'anchor':l['text']} for s in pages for l in s['links'] if norm(urljoin(s['url'],l['href']))==norm(p['url'])]
 if p['status']==404:broken.append({'url':p['url'],'status':404,'incoming':incoming})
 if norm(p['url'])!=norm(p['final_url']):redirects.append({'url':p['url'],'destination':norm(p['final_url']),'incoming_count':len(incoming)})
captures={norm(p['url']):p for p in pages+extra if p.get('status')==200 and 'text/html' in p.get('headers',{}).get('content-type','')}
depth={norm('https://erofwhiterock.com/'):0};queue=list(depth)
while queue:
 u=queue.pop(0);p=captures.get(u)
 if not p:continue
 targets=[norm(urljoin(p['final_url'],l['href'])) for l in p.get('links',[])]+[norm(p['final_url'])]
 for t in targets:
  if t not in depth and t in captures:depth[t]=depth[u]+(0 if t==norm(p['final_url']) else 1);queue.append(t)
depths=[{'id':p['id'],'url':p['url'],'depth':depth.get(norm(p['url']))} for p in pages]
scope={'first_capture':min(p['captured_at'] for p in pages),'last_capture':max(p['captured_at'] for p in pages),'published_pages':len(pages),'types':dict(collections.Counter(p['type'] for p in inventory)),'extra_requests':len(extra),'http_status':dict(collections.Counter(p['status'] for p in pages))}
out={'scope':scope,'broken_links':broken,'redirected_link_targets':redirects,'rendered_depth':depths,
'encoding_pages':[{'id':p['id'],'url':p['url'],'marker_count':len(re.findall('[\u252c\u251c\u00d4\ufffd]',p['text']))} for p in pages if re.search('[\u252c\u251c\u00d4\ufffd]',p['text'])],
'admin_author_pages':[{'id':p['id'],'url':p['url']} for p in inventory if p['author_id']==7],
'review_claim_pages':[{'id':p['id'],'url':p['url']} for p in pages if 'Medically reviewed by the ER of White Rock Nursing Team' in p['text']],
'canonical_mismatches':[{'id':p['id'],'url':p['url'],'canonical':p['canonical']} for p in pages if p['canonical'] and norm(p['canonical'][0])!=norm(p['url'])]}
(root/'evidence-summary.json').write_text(json.dumps(out,indent=2),encoding='utf-8')
with (root/'broken-links.csv').open('w',newline='',encoding='utf-8-sig') as f:
 w=csv.DictWriter(f,fieldnames=['post_id','page','anchor','broken_url','http_status']);w.writeheader()
 for b in broken:
  for s in b['incoming']:w.writerow(dict(s,broken_url=b['url'],http_status=b['status']))
with (root/'page-inventory.csv').open('w',newline='',encoding='utf-8-sig') as f:
 w=csv.DictWriter(f,fieldnames=['id','type','lang','author_id','url','title']);w.writeheader();w.writerows({k:p.get(k) for k in w.fieldnames} for p in inventory)
print('SCOPE',json.dumps(scope));print('BROKEN',json.dumps(broken));print('DEPTH',json.dumps({'histogram':dict(collections.Counter(str(p['depth']) for p in depths)),'unreached':[p for p in depths if p['depth'] is None]}));print('ENCODING',[p['id'] for p in out['encoding_pages']]);print('REVIEW BADGE COUNT',len(out['review_claim_pages']))

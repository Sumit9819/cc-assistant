import json,csv,collections
from pathlib import Path
root=Path('D:/cc-assistant/reports/site-audit-2026-09-09')
read=lambda name:json.loads((root/name).read_text(encoding='utf-8'))
inventory=read('inventory.json');byid={p['id']:p for p in inventory}
raw=read('indexing-all.json');records={p['id']:p for p in raw}
if (root/'index-retry.json').exists():
 for x in read('index-retry.json'):
  if x['name']=='gsc_inspect_url' and not x.get('isError'):
   p=next((p for p in inventory if p['url']==x['result'].get('url')),None)
   if p:records[p['id']]={'id':p['id'],'source':'index-retry.json','result':x['result']}
rows=[]
for p in inventory:
 entry=records.get(p['id'],{});r=entry.get('result') or {};r=r.get('data',r) or {}
 rows.append({'id':p['id'],'type':p['type'],'lang':p['lang'],'title':p['title'],'url':p['url'],'verdict':r.get('verdict','UNKNOWN'),'coverage_state':r.get('coverage_state','API error or no observation'),'last_crawl_time':r.get('last_crawl_time') or '', 'page_fetch_state':r.get('page_fetch_state') or '', 'user_canonical':r.get('user_canonical') or '', 'google_canonical':r.get('google_canonical') or '', 'source':entry.get('source','indexing-all.json')})
counts=collections.Counter(p['coverage_state'] for p in rows)
not_indexed=[p for p in rows if p['verdict']!='PASS']
mismatch=[p for p in rows if p['user_canonical'] and p['google_canonical'] and p['user_canonical']!=p['google_canonical']]
with (root/'indexing-results.csv').open('w',encoding='utf-8-sig',newline='') as f:
 w=csv.DictWriter(f,fieldnames=list(rows[0]));w.writeheader();w.writerows(rows)
md=['# Google indexing results — September 9, 2026','',f"Inspected {len(records)} of {len(inventory)} published URLs through the connected Google Search Console property. Responses were retrieved during this audit; Google\'s last-crawl dates vary, and some endpoint results can come from its one-hour cache. A fresh successful browser response is a separate observation from Google indexing.",'','| Coverage state | URLs |','|---|---:|']
md += [f'| {k} | {v} |' for k,v in counts.items()]
md += ['','## URLs needing investigation or monitoring','','These statuses do not identify the cause and do not authorize consolidation or deletion. Prioritize substantive service pages over low-priority policy pages. The new medication-note article was published today.','','| ID | Page | Google status | Last crawl |','|---|---|---|---|']
md += [f"| {p['id']} | [{p['title'].replace('|','/')} ]({p['url']}) | {p['coverage_state']} | {p['last_crawl_time'] or 'No crawl reported'} |" for p in not_indexed]
md += ['','## Canonical disagreements','','| ID | Declared canonical observed by Google | Google-selected canonical |','|---|---|---|']
md += [f"| {p['id']} | {p['user_canonical']} | {p['google_canonical']} |" for p in mismatch]
if not mismatch:md.append('| — | None in retrieved records | — |')
md += ['','Repair current page defects before requesting reindexing. For discovered-but-not-indexed URLs, investigate discovery/crawl scheduling, canonical alternatives and the useful purpose of the page. For crawled-but-not-indexed URLs, review the last-crawled content and alternatives; the status alone does not prove poor quality or duplication. Do not attribute these statuses to SiteGround without Googlebot or inspection evidence.','','`indexing-results.csv` contains all 129 rows; `indexing-all.json`, `broad.json`, `verification.json` and any `index-retry.json` preserve the raw responses.']
(root/'INDEXING-RESULTS.md').write_text('\n'.join(md)+'\n',encoding='utf-8')
print('COUNTS',json.dumps(counts));print('NONINDEXED',json.dumps(not_indexed));print('CANONICAL DISAGREEMENTS',json.dumps(mismatch))

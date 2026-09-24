import runpy,json,re,copy,difflib
from pathlib import Path
from urllib.parse import urlparse,parse_qs
root=Path(__file__).resolve().parent
b=runpy.run_path(str(root/'build-fixes.py'))
posts=b['posts'];nodes=b['nodes'];updates=b['updates'];reasons=b['reasons'];extras=b['extras'];log=b['change_log']
updates.clear();reasons.clear();extras.clear();log.clear()
put=b['put'];setting=b['setting'];replace=b['replace'];core=b['core'];seo=b['seo']
inventory={int(x['id']):x for x in json.loads(Path('D:/cc-assistant/reports/site-audit-2026-09-09/inventory.json').read_text(encoding='utf-8'))}
def url(pid):return posts.get(pid,inventory.get(pid))['url']
def patch(pid,before,after,why):
 assert before!=after and posts[pid]['content'].count(before)==1,(pid,'ambiguous patch')
 extras.append({'name':'draft_patch_post_content','arguments':{'post_id':pid,'patches':[{'search':before,'replace':after}],'summary':why,'reasoning':why+' Exact current source retained except the described replacements.'}})
 log.append({'post_id':pid,'field':'content','before':before,'after':after,'reason':why})

# Exact destination corrections. No mass redirect or content consolidation.
broken='/es/sala-de-emergencias-independiente-vs-hospitalaria-cerca-de-white-rock-lake/'
for pid in [5063,5058,5067,4986]:
 old=posts[pid]['content'];new=old
 replacements={
 'https://erofwhiterock.com/es/contactanos/':'https://erofwhiterock.com/es/contactenos/',
 'https://erofwhiterock.com'+broken:url(5009),
 'https://erofwhiterock.com/es/sala-de-emergencias-pediatrica-white-rock-guia-padres/':url(4737 if pid==5067 else 5067),
 'https://erofwhiterock.com/services/abdominal-pain-er-treatment/':url(5342)}
 for src,dest in replacements.items():new=new.replace(src,dest)
 # Use the paired Spanish destination where the existing Spanish anchor describes that service.
 if pid in [5063,5058,5067]:
  aliases={'https://erofwhiterock.com/comprehensive-diagnostic-imaging-services/':url(4745),'https://erofwhiterock.com/emergency-lab-testing-white-rock-tx/':url(4747),'https://erofwhiterock.com/es/tratamiento-del-dolor-abdominal-en-dallas-tx-atencion-gastro-experta/':url(5370)}
  for src,dest in aliases.items():new=new.replace(src,dest)
 assert new!=old
 # URLs are the only changes; preserve prose and all markup byte-for-byte.
 patches=[]
 for m in re.finditer(r'<a\b[^>]*>.*?</a>',old,re.S):
  a=m[0];v=a
  for src,dest in {**replacements,**(aliases if pid!=4986 else {})}.items():v=v.replace(src,dest)
  if a!=v and old.count(a)==1:patches.append({'search':a,'replace':v})
 # Duplicate exact anchors are handled by unique larger paragraph/list-item context.
 if any(old.count(x['search'])!=1 for x in patches) or len(patches)!=sum(1 for m in re.finditer(r'<a\b[^>]*>.*?</a>',old,re.S) if m[0] not in new):
  patches=[{'search':old,'replace':new}]
 # Re-simulate to catch omitted repeated anchors.
 simulated=old
 for p in patches:simulated=simulated.replace(p['search'],p['replace'],1)
 if simulated!=new:patches=[{'search':old,'replace':new}]
 extras.append({'name':'draft_patch_post_content','arguments':{'post_id':pid,'patches':patches,'summary':'WR-06: repair broken and outdated internal destinations','reasoning':'Confirmed 404 destinations replaced by current matching pages, using Spanish equivalents where available. Prose and layout preserved.'}})
 log.append({'post_id':pid,'field':'content','before':old,'after':new,'reason':'WR-06 internal links'})

# Reversible encoding repair with a round-trip checked map, without rewriting policy meaning.
mapping={}
for char in '\u00a0ÁÉÍÓÚÜÑáéíóúüñ¿¡’‘“”–—…©®°•':
 for codec in ['cp850','cp437']:
  try:garbled=char.encode('utf-8').decode(codec)
  except UnicodeError:continue
  if any(mark in garbled for mark in ['Ô','┬','├']):
   assert garbled.encode(codec).decode('utf-8')==char
   assert garbled not in mapping or mapping[garbled]==char
   mapping[garbled]=char
rx=re.compile('|'.join(map(re.escape,sorted(mapping,key=len,reverse=True))))
for pid in [4759,4758,4757,4655,4652,4650,4647,4643]:
 old=posts[pid]['content'];new=rx.sub(lambda m:mapping[m[0]],old)
 # The site's style gate bans em-dashes. Use an ordinary dash without changing the text.
 new=new.replace('—',' - ')
 def direct(m):
  link=m[1];parsed=urlparse(link.replace('&amp;','&'))
  if parsed.netloc=='www.google.com' and parsed.path=='/url':
   dest=parse_qs(parsed.query).get('q',[''])[0]
   if dest.startswith(('https://erofwhiterock.com/','https://www.tdi.texas.gov','https://www.cms.gov/nosurprises','mailto:info@erofwhiterock.com')):return 'href="'+dest+'"'
  return m[0]
 new=re.sub(r'href="([^"]+)"',direct,new)
 if pid==4758:new=new.replace('https://erofwhiterock.com/es/es//notice-of-privacy-practices/',url(4757))
 assert not re.search(r'[┬├]|ÔÇ',new),(pid,'unrepaired encoding',re.findall(r'.{0,8}(?:[┬├]|ÔÇ).{0,12}',new))
 patch(pid,old,new,'WR-10: restore garbled characters and repair policy/contact links')

# Spanish byline is hard-coded Admin; use the supported live post-author control.
items=setting(4771,'510c896','icon_list')
assert items[0]['custom_text']=='Admin' and items[0]['type']=='custom'
items[0].update({'type':'author','text_prefix':'','custom_text':'','link':''})
for row in items:
 if row.get('type','date')=='date':row['link']=''
put(4771,'510c896','WR-11/12: render the actual organization author and remove the broken date-archive link.',icon_list=items)
for pid in [4667,4771]:
 put(pid,'ac354d7','WR-15: reduce oversized mobile title while preserving the existing desktop design.',custom_css='@media (max-width: 767px) { selector .elementor-heading-title { font-size: 32px !important; line-height: 1.2 !important; overflow-wrap: break-word; } }')
 put(pid,'a440026','WR-15: add a mobile gutter and reduce excess space above the title.',padding_mobile={'unit':'px','top':'36','right':'16','bottom':'48','left':'16','isLinked':False})

# Correct Spanish service-area residue using existing, verified neighborhood destinations.
put(4751,'3cd14b5d','WR-09: remove copied Coppell/Grand Prairie catchment and unsupported drive-time promises.',editor='<p>ER of White Rock tiene una sola ubicación en <strong>10705 Northwest Hwy, Dallas, TX 75238</strong>. Atendemos a familias de White Rock, <a href="'+url(4753)+'">Lake Highlands</a>, <a href="'+url(4749)+'">Lakewood</a> y otras comunidades de East Dallas, las 24 horas.</p><p>El tiempo de viaje depende de su punto de partida y del tráfico. Si sospecha una emergencia que pone en riesgo la vida, llame al 911.</p>')
put(4751,'70a90fd','WR-09: replace directions for the wrong side of Dallas with the verified facility address.',editor='<p>Estamos en <strong>10705 Northwest Hwy, Dallas, TX 75238</strong>. Consulte indicaciones actuales desde su ubicación; el tiempo de viaje varía según la ruta y el tráfico.</p>')
replace(1338,'4f2bc86b','editor','without the massive wait times of a standard hospital','with evaluation and treatment based on your symptoms','WR-05: remove hospital wait-time comparison.')
replace(1338,'1c3cc98b','editor','You completely bypass the standard hospital lobby and are evaluated directly by an emergency doctor.','An emergency physician evaluates your symptoms and determines the next steps.','WR-05: remove hospital wait-time comparison.')

# Author cleanup uses the established public organization identity, not invented clinicians.
for pid in [5510,5511,5496,5495,5470,5471,5469,5468,5466,5463,5462,5460,5407,5394,5391,5390,5378,5375,5370,5366,5360,5342]:
 core(pid,'post_author',4,'WR-11: attribute this page to ER of White Rock, not adminsumit')

req=[{'name':'replace_section_content','arguments':{'post_id':pid,'section_id':'','widget_updates':[{'widget_id':w,'settings':s} for w,s in ws.items()],'reasoning':' '.join(reasons[pid])}} for pid,ws in updates.items()]+extras
(root/'remaining-proposals.json').write_text(json.dumps(req,ensure_ascii=False,indent=2),encoding='utf-8')
(root/'remaining-change-log.json').write_text(json.dumps(log,ensure_ascii=False,indent=2),encoding='utf-8')
for i in range(0,len(req),6):(root/f'remaining-{i//6}.json').write_text(json.dumps(req[i:i+6],ensure_ascii=False),encoding='utf-8')
print(json.dumps({'proposals':len(req),'batches':(len(req)+5)//6,'widgets':sum(map(len,updates.values()))}))

import json,re,runpy
from pathlib import Path
root=Path(__file__).resolve().parent
b=runpy.run_path(str(root/'build-fixes.py'));posts=b['posts'];req=[]
for pid,lang in [(5092,'en'),(5122,'es')]:
 new=(root/f'world-cup-{lang}.html').read_text(encoding='utf-8').strip();old=posts[pid]['content']
 counts=lambda s:{tag:len(re.findall('<'+tag+r'\b',s,re.I)) for tag in ['h2','h3']}
 print(pid,'old',counts(old),'new',counts(new),'words',len(re.sub('<[^>]+>',' ',new).split()))
 assert counts(old)==counts(new),'Preserve the section hierarchy for this accuracy correction'
 title='World Cup 2026 and Heart Health: What Research Shows' if lang=='en' else 'Mundial 2026 y Salud Cardíaca: Qué Dice la Evidencia'
 desc='What World Cup research shows about heart health, plus practical guidance on heat, alcohol and urgent symptoms for sports fans in East Dallas.' if lang=='en' else 'Qué dice la investigación del Mundial sobre la salud cardíaca y cómo prepararse para el calor, el alcohol y los síntomas urgentes en East Dallas.'
 why='WR-09: correct the World Cup study subgroup, remove unverified quotes/studies and Dallas spike claims, update event timing and emergency advice. Same section hierarchy; substantive accuracy correction. Clinical team should review the revised article.'
 req.append({'name':'draft_update_post_content','arguments':{'post_id':pid,'content':new,'summary':'WR-09: correct outdated and unsupported World Cup medical claims','reasoning':why}})
 for field,value in [('post_title',title),('post_excerpt',desc)]:req.append({'name':'draft_update_post_meta','arguments':{'post_id':pid,'field':field,'value':value,'summary':'WR-09: align the World Cup '+field+' with the corrected evidence','reasoning':why}})
 for key,value in [('title',title),('description',desc)]:req.append({'name':'draft_update_seo_meta','arguments':{'post_id':pid,'logical_key':key,'value':value,'summary':'WR-09: correct World Cup SEO '+key,'reasoning':why}})
(root/'world-cup-proposals.json').write_text(json.dumps(req,ensure_ascii=False,indent=2),encoding='utf-8')

import csv, json, re, html
from pathlib import Path
root=Path(__file__).parent
pages=json.loads((root/'report-content-final.json').read_text(encoding='utf-8'))
sources=json.loads((root/'sources.json').read_text(encoding='utf-8'))
refs={s['id']:s for s in sources}
rows=json.loads((root/'roadmap-data.json').read_text(encoding='utf-8'))

def inline(text):
    text=html.escape(text)
    text=re.sub(r'\[\^(\d+)\]',lambda m:f'<sup><a href="#source-{m[1]}">{m[1]}</a></sup>',text)
    text=text.replace('</a></sup><sup><a', '</a>,</sup><sup><a')
    text=re.sub(r'\*\*(.+?)\*\*',r'<strong>\1</strong>',text)
    return re.sub(r'\x60([^\x60]+)\x60',r'<code>\1</code>',text)

def blocks(text):
    lines=text.strip().splitlines(); result=[]; i=0
    while i < len(lines):
        line=lines[i].strip()
        if not line: i+=1; continue
        if line.startswith('## '):
            result.append(('heading',line[3:])); i+=1; continue
        if line.startswith('|'):
            table=[]
            while i<len(lines) and lines[i].strip().startswith('|'):
                values=[x.strip() for x in lines[i].strip().strip('|').split('|')]
                if not all(re.fullmatch(r':?-+:?',v) for v in values): table.append(values)
                i+=1
            result.append(('table',table)); continue
        if re.match(r'\d+\.\s',line):
            items=[]
            while i<len(lines) and re.match(r'\d+\.\s',lines[i].strip()):
                items.append(re.sub(r'^\d+\.\s','',lines[i].strip())); i+=1
            result.append(('list',items)); continue
        para=[line]; i+=1
        while i<len(lines) and lines[i].strip() and not lines[i].startswith(('## ','|')) and not re.match(r'\d+\.\s',lines[i]):
            para.append(lines[i].strip()); i+=1
        result.append(('p',' '.join(para)))
    return result

def render_blocks(text):
    out=[]
    for typ,data in blocks(text):
        if typ=='heading': out.append('<h3>'+inline(data)+'</h3>')
        elif typ=='p': out.append('<p>'+inline(data)+'</p>')
        elif typ=='list': out.append('<ol>'+''.join('<li>'+inline(x)+'</li>' for x in data)+'</ol>')
        elif typ=='table':
            out.append('<table><thead><tr>'+''.join('<th>'+inline(x)+'</th>' for x in data[0])+'</tr></thead><tbody>')
            for row in data[1:]: out.append('<tr>'+''.join('<td>'+inline(x)+'</td>' for x in row)+'</tr>')
            out.append('</tbody></table>')
    return '\n'.join(out)

css="""
@page { size: A4; margin: 12mm 16mm; }
* { box-sizing:border-box; }
body { font: 9.8pt/1.20 Arial, sans-serif; color:#181818; margin:0; }
h1 { font-size:22pt; line-height:1.1; margin:0 0 6mm; }
h2 { font-size:17.5pt; line-height:1.12; margin:0 0 5mm; }
h3 { font-size:11pt; line-height:1.25; margin:3mm 0 1.6mm; break-after:avoid; }
p { margin:0 0 2mm; orphans:3; widows:3; }
table { border-collapse:collapse; width:100%; font-size:8.8pt; margin:3mm 0; }
th { background:#ededed; font-weight:600; text-align:left; }
th,td { padding:1.5mm; border-bottom:0.25mm solid #d4d4d4; vertical-align:top; }
tr { break-inside:avoid; }
code { font-family:Consolas,monospace; font-size:.91em; overflow-wrap:anywhere; }
a { color:#303030; text-decoration:underline; overflow-wrap:anywhere; }
sup { font-size:7pt; line-height:0; }
ol { padding-left:5mm; margin:2mm 0 3mm; }
li { margin:0 0 1.3mm; }
.page { break-after:page; }
.page:last-child { break-after:auto; }
.footnotes { break-inside:avoid; font-size:7pt; line-height:1.2; border-top:0.2mm solid #bbb; margin-top:2mm; padding-top:1.2mm; columns:2; column-gap:5mm; }
.footnotes p { margin:0 0 .8mm; break-inside:avoid; }
.source { font-size:8.5pt; line-height:1.3; margin:0 0 2.7mm; overflow-wrap:anywhere; break-inside:avoid; }
.source-note { color:#404040; }
"""
out=['<!doctype html><html lang="en"><meta charset="utf-8"><title>CC Assistant content strategy and decision roadmap</title><style>'+css+'</style><body>']
md=[]
for i,page in enumerate(pages):
    tag='h1' if i==0 else 'h2'
    out.append('<section class="page"><'+tag+'>'+html.escape(page['title'])+'</'+tag+'>')
    out.append(render_blocks(page['body']))
    used=list(dict.fromkeys(int(n) for n in re.findall(r'\[\^(\d+)\]',page['body'])))
    out.append('<div class="footnotes">')
    for n in used:
        s=refs[n]; label=html.escape(s['publisher']+', '+s['title']+'.')
        if s['url']: label='<a href="'+html.escape(s['url'])+'">'+label+'</a>'
        out.append(f'<p>{n}. {label}</p>')
    out.append('</div></section>')
    md.append(('# ' if i==0 else '## ')+page['title']+'\n'+page['body'].replace('\n## ','\n### '))
for group_i, group in enumerate([sources[:13],sources[13:]]):
    out.append('<section class="page"><h2>Sources'+(' — continued' if group_i else '')+'</h2>')
    if group_i==0:
        out.append('<p class="source">External references are primary documentation. “Live documentation” identifies a revisable page; all were accessed September 9, 2026. Local citations identify the installed 0.84.0 source or dated validation records. File paths without a root are relative to D:/cc-assistant/wp-content/plugins/cc-assistant/.</p>')
    for s in group:
        title=html.escape(s['title'])
        if s['url']: title='<a href="'+html.escape(s['url'])+'">'+title+'</a>'
        line=f"<strong>{s['id']}. {html.escape(s['publisher'])}.</strong> {title} {html.escape(s['date'])}. Accessed {s['accessed']}."
        if s['url']: line+='<br><a href="'+html.escape(s['url'])+'">'+html.escape(s['url'])+'</a>'
        line+='<br><span class="source-note">'+html.escape(s['note'])+'</span>'
        out.append(f'<p class="source" id="source-{s["id"]}">{line}</p>')
    out.append('</section>')
out.append('</body></html>')
(root/'CC-Assistant-Content-Strategy.html').write_text('\n'.join(out),encoding='utf-8')
md.append('## Sources\n\nExternal documentation accessed September 9, 2026. Local paths are relative to the installed plugin unless stated otherwise.\n')
for s in sources:
    title=f"[{s['title']}]({s['url']})" if s['url'] else s['title']
    md.append(f"[^{s['id']}]: {s['publisher']}. {title}. {s['date']}. Accessed {s['accessed']}. {s['note']}\n")
(root/'CC-Assistant-Content-Strategy.md').write_text('\n\n'.join(md),encoding='utf-8')
with (root/'CC-Assistant-Content-Roadmap.csv').open('w',encoding='utf-8-sig',newline='') as f:
    w=csv.writer(f)
    w.writerow(['ID','Priority','Feature or fix','Relative effort','Scope','Acceptance criterion','Dependencies','Source references'])
    w.writerows(rows)
used_ids={int(n) for p in pages for n in re.findall(r'\[\^(\d+)\]',p['body'])}
assert used_ids <= set(refs)
assert len(rows)==15
print(json.dumps({'body_words':sum(len(p['body'].split()) for p in pages),'sources':len(sources),'roadmap_items':len(rows),'uncited_sources':sorted(set(refs)-used_ids)}))

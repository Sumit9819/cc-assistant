import json, re
from pathlib import Path
import pymupdf
root=Path(__file__).parent
doc=pymupdf.open(root/'CC-Assistant-Content-Strategy.pdf')
records=[]
for i,page in enumerate(doc):
    text=page.get_text()
    blocks=page.get_text('blocks')
    records.append({'page':i+1,'words':len(text.split()),'first_line':text.splitlines()[0] if text else '', 'last_line':text.splitlines()[-1] if text else '', 'min_y':round(min(b[1] for b in blocks),2) if blocks else 0,'max_y':round(max(b[3] for b in blocks),2) if blocks else 0, 'links':len(page.get_links())})
    if i in [0,1,5,9,10,11]:
        page.get_pixmap(matrix=pymupdf.Matrix(1.2,1.2)).save(root/f'preview-{i+1}.png')
body=(root/'CC-Assistant-Content-Strategy.md').read_text(encoding='utf-8')
result={'pdf_pages':len(doc),'pages':records,'unicode_replacement_character': '\ufffd' in body,'source_count':len(json.loads((root/'sources.json').read_text(encoding='utf-8')))}
(root/'document-validation.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps(result))


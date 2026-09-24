import hashlib,json,subprocess,os
from pathlib import Path
root=Path(__file__).parent
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
baseline={p.relative_to(source).as_posix():digest(p) for p in source.rglob('*') if p.is_file() and '__pycache__' not in p.parts}
(root/'baseline-hashes.json').write_text(json.dumps(baseline,indent=2),encoding='utf-8')
old=Path('D:/cc-assistant/reports/implementation-0.89.0-2026-09-09/release-work/mcp-smoke.py').read_text(encoding='utf-8')
(root/'mcp-smoke.py').write_text(old.replace("version=='0.89.0'", "version=='0.89.1'"),encoding='utf-8')
live=json.loads(Path('D:/cc-assistant/reports/apply-recovery-0.89.1-2026-09-09/live-history.json').read_text(encoding='utf-8'))
post=next(x['result'] for x in live if x['name']=='get_post')
tree=post['elementor_data'];tree=json.loads(tree) if isinstance(tree,str) else tree
nodes={}
def walk(tree):
    for n in tree:
        if isinstance(n,dict):
            nodes[n.get('id')]=n
            walk(n.get('elements',[]))
walk(tree)
print(json.dumps({'first_lab_update_saved':'Laboratory results help your care team' in nodes['1ffbaf62']['settings']['editor'], 'remaining_heading':nodes['46afe405']['settings'].get('title_text_a'),'live_widget_count':len(nodes)}))
changed=[name for name,sha in baseline.items() if (root/'cc-assistant'/name).is_file() and digest(root/'cc-assistant'/name)!=sha]
added=[p.relative_to(root/'cc-assistant').as_posix() for p in (root/'cc-assistant').rglob('*') if p.is_file() and p.relative_to(root/'cc-assistant').as_posix() not in baseline]
print(json.dumps({'changed':changed,'added':added},indent=2))

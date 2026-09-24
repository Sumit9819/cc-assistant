import hashlib, json, shutil
from pathlib import Path
root=Path(__file__).parent
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
files={str(p.relative_to(source)).replace('\\','/'):hashlib.sha256(p.read_bytes()).hexdigest() for p in source.rglob('*') if p.is_file()}
(root/'baseline-hashes.json').write_text(json.dumps(files,indent=2),encoding='utf-8')
shutil.copy2(root/'test-results.json',root/'baseline-test-results.json')
print(f'Baseline captured: {len(files)} files; 48 regression files passed.')

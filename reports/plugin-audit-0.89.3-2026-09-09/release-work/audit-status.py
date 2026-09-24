import hashlib,json,re
from pathlib import Path
root=Path(__file__).parent
tests=json.loads((root/'test-results.json').read_text())
live=json.loads(Path('D:/cc-assistant/reports/plugin-audit-0.89.3-2026-09-09/live-readonly.json').read_text(encoding='utf-8'))
identity=next(x['result'] for x in live if x['name']=='whoami')
pending=next(x['result'] for x in live if x['name']=='list_pending_changes')
baseline=json.loads((root/'baseline-hashes.json').read_text())
plugin=root/'cc-assistant'
files={p.relative_to(plugin).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in plugin.rglob('*') if p.is_file() and '__pycache__' not in p.parts}
changed=sorted(k for k,v in files.items() if baseline.get(k)!=v)
config_hooks=[]
for base in [Path('C:/Users/sumit/Local Sites/plugintesting/app/public/.claude'),Path('D:/cc-assistant/.claude'),Path('C:/Users/sumit/.claude')]:
    for name in ['settings.json','settings.local.json']:
        f=base/name
        if not f.exists():continue
        data=json.loads(f.read_text(encoding='utf-8-sig'))
        hooks=data.get('hooks',{})
        config_hooks.append({'settings_file':str(f),'has_cc_gate_reference':'cc_gate.py' in json.dumps(hooks),'hook_events':list(hooks)})
result={'php_syntax_files':tests['syntax_files'],'php_test_files':len(tests['tests']),'php_failures':[t['file'] for t in tests['tests'] if t['exit_code']], 'live_version':identity.get('plugin_version'),'live_pending_count':pending.get('count'),'changed_files':changed,'hook_registration':config_hooks}
(root/'audit-status.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps(result,indent=2))

import json, os, re, subprocess, time
from pathlib import Path
root=Path(__file__).parent
plugin=root/'cc-assistant'
php='D:/cc-assistant/php/php.exe'
base=[php,'-d','extension_dir=D:/cc-assistant/php/ext','-d','extension=sqlite3','-d','extension=mbstring']
result={'syntax':[],'syntax_files':len(list(plugin.rglob('*.php'))),'tests':[]}
for p in plugin.rglob('*.php'):
    run=subprocess.run([php,'-l',str(p)],capture_output=True,text=True,encoding='utf-8',errors='replace')
    if run.returncode: result['syntax'].append({'file':str(p.relative_to(plugin)),'output':run.stdout+run.stderr})
if result['syntax']:
    print(json.dumps(result,indent=2));(root/'test-results.json').write_text(json.dumps(result,indent=2),encoding='utf-8');raise SystemExit(1)
print('PHP syntax passed.',flush=True)
for p in sorted((plugin/'tests').glob('*-test.php')):
    started=time.time()
    run=subprocess.run(base+[str(p)],cwd=plugin/'tests',capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=120)
    record={'file':p.name,'exit_code':run.returncode,'seconds':round(time.time()-started,2),'output':run.stdout+run.stderr}
    result['tests'].append(record)
    print(json.dumps({'file':p.name,'exit_code':run.returncode,'failures':[l for l in record['output'].splitlines() if re.match(r'^(?:PHP )?(?:FAIL(?:\s|:|$)|Fatal|Warning|Deprecated)', l.strip())][:10]}),flush=True)
(root/'test-results.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
raise SystemExit(int(any(t['exit_code'] for t in result['tests'])))


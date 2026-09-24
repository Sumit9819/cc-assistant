import json,subprocess,time,os,sys
from pathlib import Path
root=Path(__file__).resolve().parent
stage=root/'cc-assistant'
php=['D:/cc-assistant/php/php.exe','-d','extension_dir=D:/cc-assistant/php/ext','-d','extension=sqlite3','-d','extension=mbstring']
env=os.environ.copy();env['CC_TEST_WORDPRESS_ROOT']='C:/Users/sumit/Local Sites/plugintesting/app/public'
selected=sys.argv[1:]
results=json.loads((root/'test-results.json').read_text(encoding='utf-8')) if selected else {'syntax_files':0,'syntax':[],'tests':[]}
for p in ([] if selected else stage.rglob('*.php')):
    if '__pycache__' in p.parts: continue
    r=subprocess.run(php+['-l',str(p)],capture_output=True,text=True,encoding='utf-8',errors='replace')
    results['syntax_files']+=1
    if r.returncode: results['syntax'].append({'file':str(p.relative_to(stage)),'output':r.stdout+r.stderr})
print('Syntax:',results['syntax_files'],'files, failures:',len(results['syntax']),flush=True)
for p in ([stage/'tests'/name for name in selected] if selected else sorted((stage/'tests').glob('*-test.php'))):
    start=time.monotonic()
    r=subprocess.run(php+[str(p)],cwd=stage/'tests',env=env,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=120)
    item={'file':p.name,'exit_code':r.returncode,'seconds':round(time.monotonic()-start,2),'output':r.stdout+r.stderr}
    results['tests']=[x for x in results['tests'] if x['file']!=p.name]+[item]
    (root/'test-results.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
    print(p.name,'PASS' if not r.returncode else 'FAIL',flush=True)
    if r.returncode: print(item['output'][-5000:],flush=True)
print('Completed',len(results['tests']),'regression files',flush=True)
raise SystemExit(bool(results['syntax'] or any(x['exit_code'] for x in results['tests'])))

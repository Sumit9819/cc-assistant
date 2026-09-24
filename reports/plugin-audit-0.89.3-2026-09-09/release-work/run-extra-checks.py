import json, os, pathlib, subprocess
root=pathlib.Path(__file__).parent
plugin=root/'cc-assistant'
env=os.environ.copy()
env.update(CC_TEST_PHP='D:/cc-assistant/php/php.exe',PYTHONDONTWRITEBYTECODE='1',CC_TEST_PLAYWRIGHT='C:/Users/sumit/.cc-assistant/wcag/node_modules/playwright')
commands=[['python',str(plugin/'tests/automation-runner-test.py')],['python',str(plugin/'tests/automation-bridge-test.py')],['python',str(plugin/'tests/hook-gate-test.py'),str(root/'verified-hook.py')],['node',str(plugin/'tests/wcag-passive-forms-test.cjs')],['python',str(root/'mcp-smoke.py')]]
records=[]
for command in commands:
    run=subprocess.run(command,env=env,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=60)
    records.append({'command':command,'exit_code':run.returncode,'output':run.stdout+run.stderr})
    print(json.dumps({'check':pathlib.Path(command[1]).name,'exit_code':run.returncode,'output':(run.stdout+run.stderr)[-600:]}),flush=True)
for p in plugin.rglob('*.py'): compile(p.read_bytes(),str(p),'exec')
for p in list(plugin.rglob('*.js'))+list(plugin.rglob('*.mjs'))+list(plugin.rglob('*.cjs')):
    run=subprocess.run(['node','--check',str(p)],capture_output=True,text=True,timeout=20)
    records.append({'command':['node','--check',str(p)],'exit_code':run.returncode,'output':run.stdout+run.stderr})
(root/'extra-test-results.json').write_text(json.dumps(records,indent=2),encoding='utf-8')
print('Python files compiled and JavaScript syntax checked.')
raise SystemExit(int(any(r['exit_code'] for r in records)))

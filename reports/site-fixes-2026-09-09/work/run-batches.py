import subprocess,sys
from pathlib import Path
root=Path(__file__).resolve().parent
bridge='D:/cc-assistant/reports/implementation-0.89.0-2026-09-09/release-work/live-bridge.py'
for name in sys.argv[1:]:
    print('Starting',name,flush=True)
    run=subprocess.run([sys.executable,'-X','utf8',bridge,'--requests',str(root/(name+'.json')),'--output','D:/cc-assistant/reports/site-fixes-2026-09-09/'+name+'-results.json','--transport','browser'])
    if run.returncode: raise SystemExit(run.returncode)

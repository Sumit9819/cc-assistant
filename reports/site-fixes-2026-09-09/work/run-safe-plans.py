import subprocess,sys
from pathlib import Path
root=Path(__file__).resolve().parent
for name in sys.argv[1:]:
 print('PLAN',name,flush=True)
 r=subprocess.run([sys.executable,'-X','utf8',str(root/'queue-safely.py'),name])
 if r.returncode:raise SystemExit(r.returncode)

"""polish-test: diagnose card attachment, compose, extract one frame per shot."""

import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

os.environ["PYTHONWARNINGS"] = "ignore"
STUDIO = Path("D:/faceless-studio")
ROOT = STUDIO / "projects/polish-test"
SP = Path(__file__).resolve().parent
ff = shutil.which("ffmpeg")

# 1. which cards attached
shots = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
print("  shot  para  dur   kind   card       text")
for x in shots:
    spec = x.get("card_spec") or {}
    print(f"  {x['id']:>4}  p{x['paragraph']:<3} {x['duration']:4.1f}s  {str(x.get('kind')):<6} {spec.get('kind', '-'):<9}  {x['text'][:38]}")

# 2. compose
p = subprocess.run([sys.executable, "-m", "fvs", "compose", "polish-test"], cwd=STUDIO,
                   capture_output=True, text=True, encoding="utf-8", errors="replace")
for l in [l for l in (p.stdout + p.stderr).splitlines() if l.strip() and "Warning" not in l and not l.startswith("    seg ")][-6:]:
    print(f"    {l}")
if p.returncode != 0:
    print("  compose FAILED"); sys.exit(p.returncode)
print("  compose ok")

# 3. frames, one per shot at 70% through
for i, x in enumerate(shots, start=1):
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{x['start'] + x['duration'] * 0.7:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=480:-1",
                    str(SP / f"pt_{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "pt_%d.png"),
                "-vf", f"tile={len(shots)}x1:padding=6:color=#111111", "-frames:v", "1",
                str(SP / "polish_test_grid.png")], check=True)
print(f"  polish_test_grid.png: {len(shots)} shots")

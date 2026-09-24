"""Final rebuild of roman-concrete with every change in place: paragraph-
bound shots, grade g2, drift, ambience, sliding cards, emphasis lines.

plan -> assets -> compose -> package -> shorts -> originality, each gated,
then a 6-shot grade strip and a mid-slide strip for the eye.
Run only after the in-flight render has finished."""

import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

os.environ["PYTHONWARNINGS"] = "ignore"
STUDIO = Path("D:/faceless-studio")
ROOT = STUDIO / "projects/roman-concrete"
SP = Path(__file__).resolve().parent
ff = shutil.which("ffmpeg")
TAGS = ("roman concrete,self healing concrete,materials science,civil engineering,"
        "MIT,pantheon,construction,history of technology")


def run(label, args, keep=4):
    p = subprocess.run(args, cwd=STUDIO, capture_output=True, text=True, encoding="utf-8", errors="replace")
    lines = [l for l in (p.stdout + p.stderr).splitlines()
             if l.strip() and "Warning" not in l and not l.startswith("    seg ") and not l.startswith("    shot ")]
    for l in lines[-keep:]:
        print(f"    {l}")
    if p.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(p.returncode)
    print(f"  {label} ok")


run("plan", [sys.executable, "-m", "fvs", "plan", "roman-concrete", "--pace", "medium"], keep=6)
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
paras = {s["paragraph"] for s in sl}
missing = [p for p in range(1, 40) if p not in paras]
print(f"  paragraphs owning a shot: {len(paras)}/39" + (f"  MISSING {missing}" if missing else ""))
assert not missing, "a paragraph has no shot"

run("assets", [sys.executable, "-m", "fvs", "assets", "roman-concrete"], keep=2)
run("compose", [sys.executable, "-m", "fvs", "compose", "roman-concrete"], keep=6)
run("package", [sys.executable, "-m", "fvs", "package", "roman-concrete", "--synthetic", "--tags", TAGS], keep=3)
for f in (ROOT / "shorts").glob("short_*"):
    f.unlink()
run("shorts", [sys.executable, "-m", "fvs", "shorts", "roman-concrete"], keep=5)
run("originality", [sys.executable, "-m", "fvs", "originality", "roman-concrete"], keep=7)

sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
kinds = {}
for s in sl:
    k = (s.get("card_spec") or {}).get("kind")
    if k:
        kinds[k] = kinds.get(k, 0) + 1
print(f"  shots {len(sl)} | stills {sum(1 for s in sl if s.get('kind') == 'still')} | cards {kinds}")

# grade strip: six varied shots
picks = [sl[i] for i in (2, 15, 30, 48, 66, 85) if i < len(sl)]
for i, s in enumerate(picks, start=1):
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{s['start'] + s['duration'] / 2:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=440:-1",
                    str(SP / f"grade_{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "grade_%d.png"),
                "-vf", "tile=3x2:padding=6:color=#111111", "-frames:v", "1", str(SP / "grade_grid.png")], check=True)

# slide strip: a right-anchored stat card entering
right = next((s for s in sl if (s.get("card_spec") or {}).get("position") == "bottom-right"), None)
if right:
    t0 = right["start"] + float((right.get("overlay") or {}).get("delay", 0.35))
    for k, dt in enumerate((0.05, 0.18, 0.32, 0.7), start=1):
        subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{t0 + dt:.3f}",
                        "-i", str(ROOT / "master.mp4"), "-frames:v", "1",
                        "-vf", "crop=1100:520:820:560,scale=440:-1", str(SP / f"slide_{k}.png")], check=True)
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "slide_%d.png"),
                    "-vf", "tile=4x1:padding=6:color=#111111", "-frames:v", "1", str(SP / "slide_grid.png")], check=True)
    print("  grade_grid.png and slide_grid.png written")
else:
    print("  grade_grid.png written (no right-anchored card for the slide strip)")

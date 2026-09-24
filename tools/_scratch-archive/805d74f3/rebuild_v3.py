"""roman-concrete v3: new card system + auto pace.

still coverage -> plan (auto) -> assets -> compose -> package -> shorts ->
originality, each gated, then a 6-tile strip of the shots that carry cards
so the new type is judged on the real render."""

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


run("still list", [sys.executable, "-m", "fvs", "still", "roman-concrete"], keep=1)
run("plan", [sys.executable, "-m", "fvs", "plan", "roman-concrete"], keep=6)
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
paras = {s["paragraph"] for s in sl}
missing = [p for p in range(1, 40) if p not in paras]
assert not missing, f"paragraphs without a shot: {missing}"
print(f"  paragraphs owning a shot: {len(paras)}/39")

run("assets", [sys.executable, "-m", "fvs", "assets", "roman-concrete"], keep=2)
run("compose", [sys.executable, "-m", "fvs", "compose", "roman-concrete"], keep=6)
run("package", [sys.executable, "-m", "fvs", "package", "roman-concrete", "--synthetic", "--tags", TAGS], keep=3)
for f in (ROOT / "shorts").glob("short_*"):
    f.unlink()
run("shorts", [sys.executable, "-m", "fvs", "shorts", "roman-concrete"], keep=5)
run("originality", [sys.executable, "-m", "fvs", "originality", "roman-concrete"], keep=7)

sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
picks, seen = [], set()
for s in sl:
    k = (s.get("card_spec") or {}).get("kind")
    if k and (k not in seen or (k == "stat" and len(picks) < 6)):
        picks.append(s); seen.add(k)
picks = picks[:6]
for i, s in enumerate(picks, start=1):
    t = s["start"] + float((s.get("overlay") or {}).get("delay", 0.35)) + 1.1
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{t:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=640:-1",
                    str(SP / f"v3_{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "v3_%d.png"),
                "-vf", "tile=3x2:padding=6:color=#111111", "-frames:v", "1", str(SP / "v3_grid.png")], check=True)
print(f"  v3_grid.png: {[ (s.get('card_spec') or {}).get('kind') for s in picks ]}")

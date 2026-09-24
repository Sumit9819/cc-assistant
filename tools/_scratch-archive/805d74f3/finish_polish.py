"""After the grade render: apply the card slide, re-plan to attach the five
emphasis cards, rebuild, repackage, and extract frames for two checks -
the grade across varied footage, and a card mid-slide.

Run ONLY after the polish compose has finished. Every step is gated."""

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


def run(label, args):
    p = subprocess.run(args, cwd=STUDIO, capture_output=True, text=True, encoding="utf-8", errors="replace")
    lines = [l for l in (p.stdout + p.stderr).splitlines() if l.strip() and "Warning" not in l and not l.startswith("    seg ")]
    for l in lines[-5:]:
        print(f"    {l}")
    if p.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(p.returncode)
    print(f"  {label} ok")


run("slide patch", [sys.executable, str(SP / "card_slide.py")])
run("plan", [sys.executable, "-m", "fvs", "plan", "roman-concrete", "--pace", "medium"])
run("assets", [sys.executable, "-m", "fvs", "assets", "roman-concrete"])
run("compose", [sys.executable, "-m", "fvs", "compose", "roman-concrete"])
run("package", [sys.executable, "-m", "fvs", "package", "roman-concrete", "--synthetic", "--tags",
                "roman concrete,self healing concrete,materials science,civil engineering,"
                "MIT,pantheon,construction,history of technology"])

sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
emph = [s for s in sl if (s.get("card_spec") or {}).get("kind") == "emphasis"]
print(f"  emphasis cards attached: {len(emph)}")

# Grade check: six frames spread across the video, varied footage.
picks = [sl[i] for i in (2, 15, 30, 48, 66, 85) if i < len(sl)]
for i, s in enumerate(picks, start=1):
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{s['start'] + s['duration'] / 2:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=440:-1",
                    str(SP / f"grade_{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "grade_%d.png"),
                "-vf", "tile=3x2:padding=6:color=#111111", "-frames:v", "1", str(SP / "grade_grid.png")], check=True)

# Slide check: a right-anchored stat card at 0.15s and 0.35s into its slide,
# plus one emphasis card fully landed.
right = next((s for s in sl if (s.get("card_spec") or {}).get("position") == "bottom-right"), None)
frames = []
if right:
    t0 = right["start"] + float((right.get("overlay") or {}).get("delay", 0.35))
    for k, dt in enumerate((0.12, 0.32, 0.9), start=1):
        out = SP / f"slide_{k}.png"
        subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{t0 + dt:.2f}",
                        "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=440:-1", str(out)], check=True)
        frames.append(out)
if emph:
    e = emph[0]
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{e['start'] + e['duration'] * 0.6:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=440:-1", str(SP / "slide_4.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "slide_%d.png"),
                "-vf", "tile=4x1:padding=6:color=#111111", "-frames:v", "1", str(SP / "slide_grid.png")], check=True)
print("  grade_grid.png (6 varied shots) and slide_grid.png (card at 0.12s, 0.32s, landed; one emphasis) written")

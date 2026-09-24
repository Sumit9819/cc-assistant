"""Drop a generated image into paragraph 27 as a pushed still, re-render only
that segment, and produce a side-by-side against the drawn diagram card it
replaces - so "is it better than nothing" is judged by looking.

    python try_generated.py "D:\\path\\to\\generated.png"

Nothing here is permanent: it patches the shotlist in place and the
segment cache stamp keys on the still path, so swapping back is one call.
"""

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
PARA = 27

if len(sys.argv) < 2 or not Path(sys.argv[1]).is_file():
    print("  usage: python try_generated.py <path-to-generated-image>")
    sys.exit(2)
src = Path(sys.argv[1]).resolve()

# 1. bring it into the stills library with a licence the packager understands
lib = STUDIO / "library/stills"
lib.mkdir(parents=True, exist_ok=True)
dest = lib / f"generated-gemini-p{PARA}{src.suffix.lower()}"
shutil.copy2(src, dest)
ff = shutil.which("ffmpeg")
probe = subprocess.run(
    [shutil.which("ffprobe"), "-v", "error", "-show_entries", "stream=width,height",
     "-of", "csv=p=0", str(dest)], capture_output=True, text=True)
w, h = (int(v) for v in probe.stdout.strip().split(","))
print(f"  generated image {w}x{h} -> {dest.name}")
if w < 1800:
    print(f"  note: {w}px wide is under the 1800 the push wants; it will read soft")

# 2. keep the card the paragraph already has, but replace the footage under it
#    with the generated still - that is the fair comparison (diagram on stock
#    vs diagram on a generated image). A second variant drops the card.
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))
target = next(s for s in sl["shots"] if s["paragraph"] == PARA)
before = {k: target.get(k) for k in ("kind", "still", "asset", "overlay")}
target["kind"] = "still"
target["still"] = {"path": str(dest), "direction": "in",
                   "licence": "generated (Gemini)", "attribution": ""}
target["query"] = ""
(ROOT / "shotlist.json").write_text(json.dumps(sl, ensure_ascii=False, indent=1), encoding="utf-8")
(SP / "p27_before.json").write_text(json.dumps(before, indent=1), encoding="utf-8")
print(f"  shot {target['id']} (p{PARA}, {target['duration']:.1f}s) now a pushed still; original saved to p27_before.json")

# 3. re-render just that segment via compose (stamp changed -> only it rebuilds)
proc = subprocess.run([sys.executable, "-m", "fvs", "compose", "roman-concrete"],
                      cwd=STUDIO, capture_output=True, text=True, encoding="utf-8", errors="replace")
tail = [l for l in (proc.stdout + proc.stderr).splitlines() if l.strip() and "Warning" not in l][-4:]
for l in tail:
    print(f"    {l}")
if proc.returncode != 0:
    print("  compose FAILED"); sys.exit(proc.returncode)

# 4. frames: start and end of the push, with the card on top
frames = []
for i, t in enumerate((target["start"] + 0.5, target["end"] - 0.5), start=1):
    out = SP / f"gen_{i}.png"
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{t:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=640:-1",
                    str(out)], check=True)
    frames.append(out)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1",
                "-i", str(SP / "gen_%d.png"), "-vf", "tile=2x1:padding=6:color=#111111",
                "-frames:v", "1", str(SP / "generated_compare.png")], check=True)
print("  generated_compare.png: push start | push end, diagram card over the generated image")
print("  (the previous cut of this shot, stock footage + the same card, is what you saw before)")

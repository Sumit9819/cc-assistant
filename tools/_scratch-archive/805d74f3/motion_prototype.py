"""Prototype: an animated stat card as alpha WebM, composited over real footage.

Renders motion/stat_count.html via the frame-by-frame renderer, overlays it
on the polish-test bridge clip (forcing the libvpx-vp9 DECODER - ffmpeg's
native vp9 decoder silently drops the alpha), and extracts frames at four
moments so the count-up, rule draw and label rise can be seen."""

import json
import shutil
import subprocess
import sys
from pathlib import Path

STUDIO = Path("D:/faceless-studio")
SP = Path(__file__).resolve().parent
ff = shutil.which("ffmpeg")

spec = SP / "motion_spec.json"
spec.write_text(json.dumps({
    "value": "50", "unit": "years",
    "label": "Expected life of a modern concrete bridge",
    "source": "THE CONVERSATION, 2016", "duration": 4,
}), encoding="utf-8")

webm = SP / "stat_motion.webm"
r = subprocess.run(["node", str(STUDIO / "motion/render.mjs"), str(STUDIO / "motion/stat_count.html"),
                    str(spec), str(webm), "4", "30"],
                   capture_output=True, text=True, encoding="utf-8", errors="replace", cwd=STUDIO)
print(r.stdout.strip() or r.stderr.strip()[:400])
if r.returncode != 0 or not webm.is_file():
    sys.exit(1)

# Footage: the shot behind the old "50 years" stat.
sl = json.loads((STUDIO / "projects/roman-concrete/shotlist.json").read_text(encoding="utf-8"))["shots"]
shot = next(s for s in sl if (s.get("card_spec") or {}).get("value") == "50 years")
comp = SP / "motion_comp.mp4"
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y",
                "-ss", f"{shot['start']:.2f}", "-t", "4", "-i", str(STUDIO / "projects/roman-concrete/master.mp4"),
                "-c:v", "libvpx-vp9", "-i", str(webm),
                "-filter_complex", "[0:v][1:v]overlay=0:0:eof_action=pass,format=yuv420p",
                "-an", "-c:v", "libx264", "-crf", "20", str(comp)], check=True)

for k, t in enumerate((0.3, 0.8, 1.6, 3.0), start=1):
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{t}", "-i", str(comp),
                    "-frames:v", "1", "-vf", "scale=480:-1", str(SP / f"mo_{k}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "mo_%d.png"),
                "-vf", "tile=4x1:padding=4:color=#111111", "-frames:v", "1", str(SP / "motion_grid.png")], check=True)
print("motion_grid.png at t=0.3 / 0.8 / 1.6 / 3.0s")

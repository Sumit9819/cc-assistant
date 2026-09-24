"""polish-test through the Gemini voice path, every stage gated on exit code.

voice --engine gemini -> align (Whisper) -> plan -> assets -> compose, then
a word-timing sanity read and a frame strip. No pipes: a stage that fails
stops the run."""

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


def run(label, args, keep=5):
    p = subprocess.run([sys.executable, "-m", "fvs", *args], cwd=STUDIO, capture_output=True,
                       text=True, encoding="utf-8", errors="replace")
    lines = [l for l in (p.stdout + p.stderr).splitlines()
             if l.strip() and "Warning" not in l and not l.startswith("    seg ")]
    for l in lines[-keep:]:
        print(f"    {l}")
    if p.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(p.returncode)
    print(f"  {label} ok")


run("voice", ["voice", "polish-test", "--engine", "gemini"], keep=6)
ph = json.loads((ROOT / "phonemes.json").read_text(encoding="utf-8"))
print(f"  engine={ph.get('engine')} voice={ph.get('voice')} spans={len(ph.get('paragraph_spans', []))} duration={ph['duration_s']}s")

run("align", ["align", "polish-test"], keep=4)
d = json.loads((ROOT / "words.json").read_text(encoding="utf-8"))
w = d["words"]
ok = all(w[i]["start"] <= w[i + 1]["start"] for i in range(len(w) - 1))
print(f"  words {len(w)} monotonic={ok} fallback_paragraphs {d['fallback_paragraphs']}")
for x in w[:12] + w[-4:]:
    print(f"    {x['start']:6.2f}-{x['end']:6.2f} p{x['paragraph']} {x['word']}")

run("plan", ["plan", "polish-test"], keep=3)
run("assets", ["assets", "polish-test"], keep=1)
run("compose", ["compose", "polish-test"], keep=4)

# Frame strip at each shot's 70% mark, to see cards still land on their words.
shots = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
for i, s in enumerate(shots, start=1):
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{s['start'] + s['duration'] * 0.7:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=400:-1", str(SP / f"gp_{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "gp_%d.png"),
                "-vf", f"tile={len(shots)}x1:padding=4:color=#111111", "-frames:v", "1", str(SP / "gemini_grid.png")], check=True)
print(f"  gemini_grid.png: {len(shots)} shots")

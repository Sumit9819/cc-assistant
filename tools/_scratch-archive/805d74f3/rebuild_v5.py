"""roman-concrete v5: bookends (cold open + end card), punch-ins, section dips.

Voice and alignment are untouched from v4 (Kokoro), so the chain is
plan -> compose -> package -> shorts -> originality, gated, with sanity
asserts and check strips: open mid-arc, a dip boundary, a punch shot,
and the end card."""

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


def run(label, args, keep=6):
    p = subprocess.run([sys.executable, "-m", "fvs", *args], cwd=STUDIO, capture_output=True,
                       text=True, encoding="utf-8", errors="replace")
    lines = [l for l in (p.stdout + p.stderr).splitlines()
             if l.strip() and "Warning" not in l and not l.startswith("    seg ") and not l.startswith("  [")]
    for l in lines[-keep:]:
        print(f"    {l}")
    if p.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(1)
    print(f"  {label} ok")


run("plan", ["plan", "roman-concrete"], keep=10)
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
first = sl[0]
assert (first.get("overlay") or {}).get("card", "").endswith("open.webm"), "no cold open on shot 1"
outro = sl[-1]
assert outro.get("outro") and (outro.get("scene") or {}).get("template") == "end_card", "no end card shot"
punches = [s for s in sl if s.get("punch")]
dips_in = [s for s in sl if s.get("dip_in")]
dips_out = [s for s in sl if s.get("dip_out")]
print(f"  shot1 open {first['duration']:.1f}s | outro {outro['start']:.1f}+{outro['duration']:.0f}s | "
      f"{len(punches)} punch, {len(dips_in)}/{len(dips_out)} dip in/out")
assert punches and dips_in and dips_out, "energy flags missing"

run("assets", ["assets", "roman-concrete"], keep=4)
run("compose", ["compose", "roman-concrete"], keep=7)
run("package", ["package", "roman-concrete", "--synthetic", "--tags", TAGS], keep=3)
for f in (ROOT / "shorts").glob("short_*"):
    f.unlink()
run("shorts", ["shorts", "roman-concrete"], keep=4)
run("originality", ["originality", "roman-concrete"], keep=7)

master = ROOT / "master.mp4"
words = json.loads((ROOT / "words.json").read_text(encoding="utf-8"))
video_end = outro["start"] + outro["duration"]

picks = [
    ("open", first["start"] + first["duration"] * 0.45),
    ("dip", dips_in[0]["start"] + 0.06),
    ("punch", punches[len(punches) // 2]["start"] + punches[len(punches) // 2]["duration"] * 0.5),
    ("outro", video_end - 4.0),
]
for i, (kind, t) in enumerate(picks, start=1):
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y",
                    "-ss", f"{t:.2f}", "-i", str(master), "-frames:v", "1",
                    "-vf", "scale=480:-1", str(SP / f"v5_{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1",
                "-i", str(SP / "v5_%d.png"),
                "-vf", f"tile={len(picks)}x1:padding=4:color=#111111", "-frames:v", "1",
                str(SP / "v5_grid.png")], check=True)
print(f"  v5_grid.png: {[k for k, _ in picks]}")

"""Patch the two replaced still paths into the shotlist, re-render only
those segments, repackage for the credit change, and extract before/after
frames of the two stills for a visual check. No heredocs, no pipes on
gating steps: every stage is a subprocess whose exit code stops the run."""

import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

os.environ["PYTHONWARNINGS"] = "ignore"
STUDIO = Path("D:/faceless-studio")
ROOT = STUDIO / "projects/roman-concrete"
SP = Path(
    "C:/Users/sumit/AppData/Local/Temp/claude/"
    "c--Users-sumit-Local-Sites-plugintesting-app-public/"
    "805d74f3-c178-47fa-8463-c054b1293383/scratchpad"
)


def run(label: str, args: list[str]) -> None:
    proc = subprocess.run(args, cwd=STUDIO, capture_output=True, text=True,
                          encoding="utf-8", errors="replace")
    keep = [l for l in (proc.stdout + proc.stderr).splitlines()
            if l.strip() and "Warning" not in l and not l.startswith("    seg ")]
    for line in keep[-6:]:
        print(f"    {line}")
    if proc.returncode != 0:
        print(f"  {label} FAILED (exit {proc.returncode})")
        sys.exit(proc.returncode)
    print(f"  {label} ok")


# 1. patch still paths in place - assets stay attached
plan = json.loads((ROOT / "stillplan.json").read_text(encoding="utf-8"))["paragraphs"]
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))
changed = 0
for shot in sl["shots"]:
    if shot.get("kind") == "still":
        spec = plan[str(shot["paragraph"])]
        if shot["still"]["path"] != spec["path"]:
            shot["still"].update(
                path=spec["path"], direction=spec["direction"],
                licence=spec["licence"], attribution=spec.get("attribution", ""),
            )
            changed += 1
(ROOT / "shotlist.json").write_text(json.dumps(sl, ensure_ascii=False, indent=1), encoding="utf-8")
print(f"  shotlist: {changed} still path(s) updated in place; assets untouched")

# 2. compose - stamp includes still path, so only the two changed segments render
run("compose", [sys.executable, "-m", "fvs", "compose", "roman-concrete"])

# 3. package - credit list changes (both replacements are CC0)
run("package", [sys.executable, "-m", "fvs", "package", "roman-concrete", "--tags",
                "roman concrete,self healing concrete,materials science,civil engineering,"
                "MIT,pantheon,construction,history of technology"])
meta = json.loads((ROOT / "metadata.json").read_text(encoding="utf-8"))
print(f"  image credits now: {len(meta['credits']['stills'])}")
for c in meta["credits"]["stills"]:
    print(f"    {c[:80]}")

# 4. frames of the two replaced stills, start and end of the push
ff = shutil.which("ffmpeg")
i = 0
for shot in sl["shots"]:
    if shot.get("kind") == "still" and shot["paragraph"] in (19, 21):
        for t in (shot["start"] + 0.3, shot["end"] - 0.3):
            i += 1
            subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{t:.2f}",
                            "-i", str(ROOT / "master.mp4"), "-frames:v", "1",
                            "-vf", "scale=480:-1", str(SP / f"fx_{i:02d}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1",
                "-i", str(SP / "fx_%02d.png"), "-vf", "tile=2x2:padding=6:color=#111111",
                "-frames:v", "1", str(SP / "stills_fixed.png")], check=True)
print("  frames: stills_fixed.png  (row 1 = p19 calcite, row 2 = p21 Segovia; start | end)")

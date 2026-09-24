"""Apply the polish patch, smoke-test one graded+drifting clip, then full
compose, then measure: luma/colour spread vs the 48-184 baseline, and the
ambience floor in a known pause. Every step gated on exit code."""

import json
import os
import re
import shutil
import statistics
import subprocess
import sys
from pathlib import Path

os.environ["PYTHONWARNINGS"] = "ignore"
STUDIO = Path("D:/faceless-studio")
ROOT = STUDIO / "projects/roman-concrete"
SP = Path(__file__).resolve().parent
sys.path.insert(0, str(STUDIO))
ff = shutil.which("ffmpeg")


def run(label, args, cwd=STUDIO):
    p = subprocess.run(args, cwd=cwd, capture_output=True, text=True, encoding="utf-8", errors="replace")
    lines = [l for l in (p.stdout + p.stderr).splitlines() if l.strip() and "Warning" not in l and not l.startswith("    seg ")]
    for l in lines[-6:]:
        print(f"    {l}")
    if p.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(p.returncode)
    print(f"  {label} ok")


# 1. apply
run("patch", [sys.executable, str(SP / "polish_pass.py")])

# 2. smoke: one real video shot through the new filter
from fvs.stages import compose  # noqa: E402  (imports the patched module)
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
shot = next(s for s in sl if s.get("kind") != "still" and s.get("asset"))
out = SP / "polish_smoke.mp4"
compose.render_segment(shot, 4.0, out)
d = compose._probe_duration(out)
assert d and abs(d - 4.0) < 0.1, f"smoke duration {d}"
print(f"  smoke ok: shot {shot['id']} graded+drifting, {d:.2f}s, {out.stat().st_size // 1024} KB")

# 3. full compose (GRADE_VERSION in stamp -> every segment re-renders once)
run("compose", [sys.executable, "-m", "fvs", "compose", "roman-concrete"])

# 4a. luma / colour spread, same 24-shot sample as the baseline
vals = []
for x in sl[::4]:
    t = x["start"] + x["duration"] / 2
    err = subprocess.run([ff, "-hide_banner", "-loglevel", "info", "-ss", f"{t:.2f}", "-i",
                          str(ROOT / "master.mp4"), "-frames:v", "1", "-vf",
                          "signalstats,metadata=print", "-f", "null", "-"],
                         capture_output=True, text=True, encoding="utf-8", errors="replace").stderr
    row = {}
    for line in err.splitlines():
        for k in ("YAVG", "SATAVG", "UAVG", "VAVG"):
            if f"signalstats.{k}=" in line:
                row[k] = float(line.split("=")[-1])
    if len(row) == 4:
        vals.append(row)


def spread(k):
    v = [r[k] for r in vals]
    return f"{min(v):5.1f} to {max(v):5.1f}   sd {statistics.pstdev(v):4.1f}"


print("=== GRADE ===")
print(f"  brightness   {spread('YAVG')}   (was 48.1 to 184.1  sd 31.2)")
print(f"  saturation   {spread('SATAVG')}   (was  1.7 to  18.4  sd  4.4)")
print(f"  cast U       {spread('UAVG')}   (was sd 5.3)")
print(f"  cast V       {spread('VAVG')}   (was sd 5.4)")

# 4b. ambience floor: level in the longest speech pause, master vs narration-only
w = json.loads((ROOT / "words.json").read_text(encoding="utf-8"))["words"]
gaps = sorted(((b["start"] - a["end"], a["end"]) for a, b in zip(w, w[1:])), reverse=True)
gap_len, gap_at = gaps[0]


def mean_db(path, at, dur):
    err = subprocess.run([ff, "-hide_banner", "-loglevel", "info", "-ss", f"{at:.2f}", "-t", f"{dur:.2f}",
                          "-i", str(path), "-af", "volumedetect", "-f", "null", "-"],
                         capture_output=True, text=True, encoding="utf-8", errors="replace").stderr
    m = re.search(r"mean_volume:\s*(-?[\d.]+)", err)
    return float(m.group(1)) if m else None


mid = gap_at + gap_len * 0.5
print("=== AMBIENCE ===")
print(f"  longest pause {gap_len:.2f}s at {gap_at:.1f}s")
print(f"  narration alone in that pause: {mean_db(ROOT / 'narration.wav', mid - 0.15, 0.3)} dB  (silence reads ~-91)")
print(f"  master in that pause:          {mean_db(ROOT / 'master.mp4', mid - 0.15, 0.3)} dB")

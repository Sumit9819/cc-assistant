"""roman-concrete v4: Sadaltager narration, motion cards, the healing scene.

Books the crack-healing scene on p27 (displacing its still and static
card), then voice (gemini) -> align (whisper) -> plan (auto pace; renders
motion cards and the scene) -> assets -> compose -> package --synthetic ->
shorts -> originality, every stage gated, with sanity asserts between and
check strips at the end."""

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


def run(label, args, keep=5):
    p = subprocess.run([sys.executable, "-m", "fvs", *args], cwd=STUDIO, capture_output=True,
                       text=True, encoding="utf-8", errors="replace")
    lines = [l for l in (p.stdout + p.stderr).splitlines()
             if l.strip() and "Warning" not in l and not l.startswith("    seg ") and not l.startswith("  [")]
    for l in lines[-keep:]:
        print(f"    {l}")
    if p.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(1)
    print(f"  {label} ok")


scene_spec = SP / "p27_scene.json"
scene_spec.write_text(json.dumps({
    "template": "process_scene",
    "title": "How the crack repairs itself",
    "steps": [
        {"label": "Crack forms", "note": "and runs through a lime clast"},
        {"label": "Water enters", "note": "meets exposed calcium"},
        {"label": "Calcium dissolves", "note": "into a saturated solution"},
        {"label": "Gap seals", "note": "recrystallises as calcite"},
    ],
}), encoding="utf-8")
run("book scene", ["scene", "roman-concrete", "27", str(scene_spec)])

run("voice", ["voice", "roman-concrete", "--engine", "gemini"], keep=3)
ph = json.loads((ROOT / "phonemes.json").read_text(encoding="utf-8"))
assert ph.get("engine") == "gemini" and len(ph.get("paragraph_spans", [])) == 39, "voice sanity"
print(f"  narration {ph['duration_s']/60:.1f} min, voice {ph['voice']}")

run("align", ["align", "roman-concrete"], keep=3)
d = json.loads((ROOT / "words.json").read_text(encoding="utf-8"))
w = d["words"]
assert all(w[i]["start"] <= w[i + 1]["start"] for i in range(len(w) - 1)), "words not monotonic"
paras = {x["paragraph"] for x in w}
assert len(paras) == 39, f"paragraphs in words: {len(paras)}"

run("plan", ["plan", "roman-concrete"], keep=7)
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
missing = [p for p in range(1, 40) if p not in {s["paragraph"] for s in sl}]
assert not missing, f"paragraphs without a shot: {missing}"
webms = sum(1 for s in sl if (s.get("overlay") or {}).get("card", "").endswith(".webm"))
scenes = [s for s in sl if s.get("kind") == "scene"]
print(f"  {len(sl)} shots | {webms} motion cards | {len(scenes)} scene(s) "
      f"({scenes[0]['duration']:.1f}s p{scenes[0]['paragraph']})" if scenes else "  NO SCENE")
assert scenes, "scene missing from plan"

run("assets", ["assets", "roman-concrete"], keep=2)
run("compose", ["compose", "roman-concrete"], keep=6)
for f in (ROOT / "shorts").glob("short_*"):
    f.unlink()
run("package", ["package", "roman-concrete", "--synthetic", "--tags", TAGS], keep=3)
run("shorts", ["shorts", "roman-concrete"], keep=4)
run("originality", ["originality", "roman-concrete"], keep=7)

# Check strips: the scene mid-flight, a motion stat, an emphasis, a section.
picks = [("scene", scenes[0], 0.5)]
for kind in ("stat", "emphasis", "section"):
    s = next((s for s in sl if (s.get("card_spec") or {}).get("kind") == kind
              and (s.get("overlay") or {}).get("card", "").endswith(".webm")), None)
    if s:
        picks.append((kind, s, 0.6))
for i, (kind, s, frac) in enumerate(picks, start=1):
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y",
                    "-ss", f"{s['start'] + s['duration'] * frac:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=480:-1",
                    str(SP / f"v4_{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "v4_%d.png"),
                "-vf", f"tile={len(picks)}x1:padding=4:color=#111111", "-frames:v", "1",
                str(SP / "v4_grid.png")], check=True)
print(f"  v4_grid.png: {[k for k, _, _ in picks]}")

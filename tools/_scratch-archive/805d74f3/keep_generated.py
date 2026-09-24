"""Make the generated still permanent and lay the paragraph out properly.

1. cardplan.apply learns `shot_offset`: a card can land on the nth shot of
   its paragraph, not only the first. The mechanism paragraph is long
   enough that the image can hold shot 1 and the diagram can arrive on
   shot 2, as the narration reaches "water enters".
2. stillplan.json gains p27 -> the generated image, so plan makes it a hold.
3. cards.json: the process card on p27 moves to shot_offset 1.
4. plan -> assets -> compose -> package --synthetic, each gated on exit code.
5. Frames of p27 shot 1 and shot 2 for a visual check.
"""

import ast
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
sys.path.insert(0, str(STUDIO))


def run(label: str, args: list[str]) -> str:
    proc = subprocess.run(args, cwd=STUDIO, capture_output=True, text=True,
                          encoding="utf-8", errors="replace")
    lines = [l for l in (proc.stdout + proc.stderr).splitlines()
             if l.strip() and "Warning" not in l and not l.startswith("    seg ")]
    for l in lines[-5:]:
        print(f"    {l}")
    if proc.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(proc.returncode)
    print(f"  {label} ok")
    return "\n".join(lines)


# --- 1. shot_offset in cardplan.apply --------------------------------------
cp = STUDIO / "fvs/cardplan.py"
src = cp.read_text(encoding="utf-8")
old = (
    "    first_shot: dict[int, dict[str, Any]] = {}\n"
    "    for shot in shots:\n"
    "        paragraph = int(shot.get(\"paragraph\", 0))\n"
    "        if paragraph not in first_shot:\n"
    "            first_shot[paragraph] = shot\n"
    "        shot.pop(\"overlay\", None)\n"
    "        shot.pop(\"card_spec\", None)\n"
)
new = (
    "    by_para: dict[int, list[dict[str, Any]]] = {}\n"
    "    for shot in shots:\n"
    "        by_para.setdefault(int(shot.get(\"paragraph\", 0)), []).append(shot)\n"
    "        shot.pop(\"overlay\", None)\n"
    "        shot.pop(\"card_spec\", None)\n"
)
old2 = (
    "        shot = first_shot.get(paragraph)\n"
    "        if shot is None:\n"
)
new2 = (
    "        # `shot_offset` lets a card land on a later shot of its paragraph -\n"
    "        # used when the first shot already carries a still that shows the\n"
    "        # same thing the card explains.\n"
    "        group = by_para.get(paragraph) or []\n"
    "        offset = min(int(spec.get(\"shot_offset\", 0)), max(len(group) - 1, 0))\n"
    "        shot = group[offset] if group else None\n"
    "        if shot is None:\n"
)
if "by_para" not in src:
    assert old in src and old2 in src, "cardplan anchors"
    src = src.replace(old, new).replace(old2, new2)
    ast.parse(src); cp.write_text(src, encoding="utf-8")
    print("  cardplan.py  shot_offset supported")
else:
    print("  cardplan.py  shot_offset already present")

# --- 2. still plan: p27 -> generated image ---------------------------------
gen = STUDIO / "library/stills/generated-gemini-p27.jpg"
assert gen.is_file(), "generated still missing from library"
sp_path = ROOT / "stillplan.json"
plan = json.loads(sp_path.read_text(encoding="utf-8"))
plan["paragraphs"]["27"] = {
    "key": "generated:gemini-p27", "path": str(gen), "direction": "in",
    "licence": "generated (Gemini)", "attribution": "",
    "why": "the mechanism itself - clast, crack, water - which no stock library has",
}
sp_path.write_text(json.dumps(plan, indent=1, ensure_ascii=False), encoding="utf-8")
print(f"  stillplan    p27 -> generated image ({len(plan['paragraphs'])} stills)")

# --- 3. cards: process card to shot 2 of p27 -------------------------------
from fvs import cardplan  # noqa: E402
cards = cardplan.load("roman-concrete")
assert 27 in cards and cards[27]["kind"] == "process"
cards[27]["shot_offset"] = 1
cardplan.save("roman-concrete", cards)
print("  cards.json   process card -> p27 shot 2 (after the image hold)")

# --- 4. chain --------------------------------------------------------------
out = run("plan", [sys.executable, "-m", "fvs", "plan", "roman-concrete", "--pace", "medium"])
run("assets", [sys.executable, "-m", "fvs", "assets", "roman-concrete"])
run("compose", [sys.executable, "-m", "fvs", "compose", "roman-concrete"])
run("package", [sys.executable, "-m", "fvs", "package", "roman-concrete", "--synthetic", "--tags",
                "roman concrete,self healing concrete,materials science,civil engineering,"
                "MIT,pantheon,construction,history of technology"])
meta = json.loads((ROOT / "metadata.json").read_text(encoding="utf-8"))
print(f"  altered_content flag: {meta['altered_content']}")

# --- 5. frames -------------------------------------------------------------
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
p27 = [s for s in sl if s["paragraph"] == 27]
print(f"  p27 now {len(p27)} shots; shot 1 {p27[0]['duration']:.1f}s {'still' if p27[0].get('kind')=='still' else 'video'}"
      f", shot 2 {p27[1]['duration']:.1f}s {'+card' if p27[1].get('overlay') else 'no card'}")
ff = shutil.which("ffmpeg")
for i, s in enumerate(p27[:2], start=1):
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{s['end'] - 0.5:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", "-vf", "scale=640:-1",
                    str(SP / f"keep_{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1",
                "-i", str(SP / "keep_%d.png"), "-vf", "tile=2x1:padding=6:color=#111111",
                "-frames:v", "1", str(SP / "keep_compare.png")], check=True)
print("  keep_compare.png: p27 shot 1 (generated, held) | p27 shot 2 (stock + diagram)")

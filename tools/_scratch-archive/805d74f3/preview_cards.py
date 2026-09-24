"""Composite every card kind over a clean frame from the finished master.

The frame is taken just after the shot starts, before the (old) card has
faded in, so the judgement is of the new card on the footage it will
actually sit on. One tile per kind, plus a lower third, in a 3x2 grid."""

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
from PIL import Image  # noqa: E402

from fvs import cards  # noqa: E402

ff = shutil.which("ffmpeg")
shots = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]

# One representative shot per kind; for the stat, the right-anchored one.
picks = {}
for s in shots:
    spec = s.get("card_spec") or {}
    k = spec.get("kind")
    if not k:
        continue
    if k == "stat" and spec.get("position") != "bottom-right" and "stat" in picks:
        continue
    picks.setdefault(k, s)
    if k == "stat" and spec.get("position") == "bottom-right":
        picks["stat"] = s

specs = {k: dict(s["card_spec"]) for k, s in picks.items()}
specs["lower_third"] = {"kind": "lower_third", "title": "Admir Masic", "subtitle": "Materials scientist, MIT", "position": "bottom-left"}
frames = {**{k: s for k, s in picks.items()}, "lower_third": picks["quote"]}

order = ["stat", "emphasis", "section", "quote", "process", "lower_third"]
for i, k in enumerate(order, start=1):
    s = frames[k]
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", f"{s['start'] + 0.08:.2f}",
                    "-i", str(ROOT / "master.mp4"), "-frames:v", "1", str(SP / f"clean_{k}.png")], check=True)
    base = Image.open(SP / f"clean_{k}.png").convert("RGBA")
    card = cards.render(specs[k], SP / f"newcard_{k}.png")
    overlay = Image.open(card).crop((0, 0, cards.W, cards.H))
    base.alpha_composite(overlay)
    base.convert("RGB").resize((640, 360), Image.LANCZOS).save(SP / f"nc_{i}.png")
    print(f"  {k:<12} {json.dumps(specs[k], ensure_ascii=False)[:70]}")

subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1", "-i", str(SP / "nc_%d.png"),
                "-vf", "tile=3x2:padding=6:color=#111111", "-frames:v", "1", str(SP / "new_cards_grid.png")], check=True)
print("  new_cards_grid.png written")

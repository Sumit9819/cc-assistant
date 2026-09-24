"""A 30-second test project so polish iterations cost a minute, not ten.

Exercises everything the long video does, in miniature: a cold open, a
punch line (emphasis card), a section break (hold + section card), a
mechanism paragraph (process card), a literal-subject still with a push,
and a stat. Same topic, so the stock cache and still library are reused.

Runs everything except compose, which waits for the current render.
"""

import json
import os
import subprocess
import sys
from pathlib import Path

os.environ["PYTHONWARNINGS"] = "ignore"
STUDIO = Path("D:/faceless-studio")
sys.path.insert(0, str(STUDIO))
from fvs import cardplan, config, queries, stills  # noqa: E402

SLUG = "polish-test"
ROOT = config.project_dir(SLUG)


def run(label, args):
    p = subprocess.run(args, cwd=STUDIO, capture_output=True, text=True, encoding="utf-8", errors="replace")
    keep = [l for l in (p.stdout + p.stderr).splitlines() if l.strip() and "Warning" not in l]
    for l in keep[-4:]:
        print(f"    {l}")
    if p.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(p.returncode)
    print(f"  {label} ok")


if not (ROOT / "manifest.json").is_file():
    run("new", [sys.executable, "-m", "fvs", "new", SLUG, "--title", "Polish test (30s)"])

(ROOT / "script.md").write_text(
    "# The dome\n\n"
    "The Pantheon has stood for nineteen centuries. There is no steel inside it.\n\n"
    "Modern concrete lasts fifty years.\n\n"
    "# Why ours fails\n\n"
    "Because it cracks, and water gets in. Once water reaches the steel, the steel rusts, "
    "and the concrete is pushed apart from the inside.\n\n"
    "Roman concrete does the opposite. When it cracks, the crack heals itself.\n",
    encoding="utf-8",
)
print("  script.md   4 paragraphs, 2 sections, one punch line")

queries.save(SLUG, {
    1: ["pantheon rome", "pantheon rome interior dome"],
    2: ["concrete highway bridge underside", "cracked concrete bridge support pillar"],
    3: ["rusted steel rebar exposed in concrete", "water dripping down concrete wall", "crumbling concrete wall decay"],
    4: ["crack spreading across a surface", "crystal growth timelapse macro", "water seeping into porous stone"],
})
(ROOT / "literal.json").write_text(json.dumps([1]), encoding="utf-8")
print("  queries.json 9 queries; p1 literal")

MIT = "Masic et al., Science Advances, 2023"
cardplan.save(SLUG, {
    2: {"kind": "emphasis", "text": "Fifty years."},
    3: {"kind": "section", "number": "02", "title": "Why ours fails"},
    4: {"kind": "process", "title": "How the crack repairs itself", "shot_offset": 1, "steps": [
        {"label": "Crack forms", "note": "through a lime clast"},
        {"label": "Water enters", "note": "meets exposed calcium"},
        {"label": "Calcium dissolves", "note": "into solution"},
        {"label": "Gap seals", "note": "as calcite"},
    ]},
})
print("  cards.json   emphasis, section, process")

# A still on the cold open: the Pantheon dome, CC0, already in the library.
idx = stills.load_index()["images"]
dome = next((v for v in idx.values() if "pantheon" in v["title"].lower() and "cc0" in v["licence"].lower()), None)
if dome:
    (ROOT / "stillplan.json").write_text(json.dumps({"paragraphs": {"1": {
        "key": dome["key"], "path": dome["path"], "direction": "in",
        "licence": dome["licence"], "attribution": "", "why": "test still"}}}, indent=1), encoding="utf-8")
    print(f"  stillplan    p1 -> {dome['title'][:40]} ({dome['licence']})")

run("voice", [sys.executable, "-m", "fvs", "voice", SLUG])
run("align", [sys.executable, "-m", "fvs", "align", SLUG])
run("plan", [sys.executable, "-m", "fvs", "plan", SLUG, "--pace", "medium"])
run("assets", [sys.executable, "-m", "fvs", "assets", SLUG])
w = json.loads((ROOT / "words.json").read_text(encoding="utf-8"))
sl = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
print(f"\n  ready: {w['duration_s']:.1f}s, {len(sl)} shots, "
      f"{sum(1 for s in sl if s.get('overlay'))} cards, {sum(1 for s in sl if s.get('kind') == 'still')} still")
print("  next: python -m fvs compose polish-test   (after the roman-concrete render finishes)")

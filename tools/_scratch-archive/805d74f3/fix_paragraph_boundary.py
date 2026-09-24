"""Shots never cross a paragraph boundary.

Found on the 30s test project: a hold paragraph shorter than the hold
floor made the boundary scorer run into the next paragraph and swallow it,
taking that paragraph's emphasis card with it. Every paragraph-bound
mechanism (cards, stills, holds, punches, query pools) assumes a paragraph
owns at least one shot. So the last word of a paragraph is now a hard cut.

Then re-plan, re-fetch, re-compose polish-test and prove p2 has a shot.
"""

import ast
import json
import os
import subprocess
import sys
from pathlib import Path

os.environ["PYTHONWARNINGS"] = "ignore"
STUDIO = Path("D:/faceless-studio")
ROOT = STUDIO / "projects/polish-test"

p = STUDIO / "fvs/stages/plan.py"
src = p.read_text(encoding="utf-8")

old = (
    "        candidates = [\n"
    "            j\n"
    "            for j in range(i, len(words))\n"
    "            if min_s * SEARCH_LOW <= words[j][\"end\"] - shot_start <= max_s * SEARCH_HIGH\n"
    "        ]\n"
)
new = (
    "        # A shot never crosses into the next paragraph. Cards, stills, holds\n"
    "        # and punches are all paragraph-bound, and a hold whose floor exceeded\n"
    "        # its paragraph once swallowed the punch line after it.\n"
    "        para_end = i\n"
    "        while para_end + 1 < len(words) and words[para_end + 1][\"paragraph\"] == para:\n"
    "            para_end += 1\n"
    "        candidates = [\n"
    "            j\n"
    "            for j in range(i, para_end + 1)\n"
    "            if min_s * SEARCH_LOW <= words[j][\"end\"] - shot_start <= max_s * SEARCH_HIGH\n"
    "        ]\n"
)
if "para_end = i" in src:
    print("  plan.py     already bounded")
else:
    assert old in src, "candidates anchor"
    src = src.replace(old, new)

# The sentence-end reach and the fallback must respect the same bound.
old2 = (
    "        for j in range(i, len(words)):\n"
    "            if words[j][\"end\"] - shot_start > max_s * 2.0:\n"
    "                break\n"
)
new2 = (
    "        for j in range(i, para_end + 1):\n"
    "            if words[j][\"end\"] - shot_start > max_s * 2.0:\n"
    "                break\n"
)
if old2 in src:
    src = src.replace(old2, new2)

old3 = (
    "            end_index = i\n"
    "            while (\n"
    "                end_index + 1 < len(words)\n"
    "                and words[end_index + 1][\"end\"] - shot_start <= max_s\n"
    "            ):\n"
    "                end_index += 1\n"
)
new3 = (
    "            end_index = i\n"
    "            while (\n"
    "                end_index + 1 <= para_end\n"
    "                and words[end_index + 1][\"end\"] - shot_start <= max_s\n"
    "            ):\n"
    "                end_index += 1\n"
)
if old3 in src:
    src = src.replace(old3, new3)

# The runt-tail absorb must not pull a next-paragraph tail into this shot.
old4 = (
    "        # Absorb a runt tail rather than leaving a sub-second final shot.\n"
    "        if i < len(words) and words[-1][\"end\"] - words[i][\"start\"] < min_s * 0.6:\n"
)
new4 = (
    "        # Absorb a runt tail rather than leaving a sub-second final shot -\n"
    "        # only within the same paragraph.\n"
    "        if (i < len(words) and words[i][\"paragraph\"] == para\n"
    "                and words[-1][\"end\"] - words[i][\"start\"] < min_s * 0.6):\n"
)
if old4 in src:
    src = src.replace(old4, new4)

ast.parse(src)
p.write_text(src, encoding="utf-8")
print("  plan.py     paragraph boundary is a hard cut")


def run(label, args):
    r = subprocess.run(args, cwd=STUDIO, capture_output=True, text=True, encoding="utf-8", errors="replace")
    keep = [l for l in (r.stdout + r.stderr).splitlines() if l.strip() and "Warning" not in l and not l.startswith("    seg ")]
    for l in keep[-3:]:
        print(f"    {l}")
    if r.returncode != 0:
        print(f"  {label} FAILED"); sys.exit(r.returncode)
    print(f"  {label} ok")


run("plan", [sys.executable, "-m", "fvs", "plan", "polish-test", "--pace", "medium"])
run("assets", [sys.executable, "-m", "fvs", "assets", "polish-test"])
shots = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
paras = sorted({s["paragraph"] for s in shots})
print(f"  paragraphs with a shot: {paras}  (must be [1, 2, 3, 4])")
for x in shots:
    spec = x.get("card_spec") or {}
    print(f"    shot {x['id']} p{x['paragraph']} {x['duration']:.1f}s {str(x.get('kind')):<6} {spec.get('kind', '-')}")
assert paras == [1, 2, 3, 4], "a paragraph still has no shot"
assert any((x.get("card_spec") or {}).get("kind") == "emphasis" for x in shots), "emphasis card still missing"
run("compose", [sys.executable, "-m", "fvs", "compose", "polish-test"])
print("  polish-test rebuilt with every paragraph owning a shot")

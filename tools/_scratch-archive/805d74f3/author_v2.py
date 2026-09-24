"""Author query pools, card placements and the literal flag for the
rewritten roman-concrete script (39 paragraphs, 6 sections), and wire the
literal flag into the planner so it survives re-planning."""

import json
import sys
from pathlib import Path

sys.path.insert(0, "D:/faceless-studio")
from fvs import cardplan, config, queries  # noqa: E402

SLUG = "roman-concrete"

# --- B-roll query pools, keyed by NEW paragraph index -------------------
# Rule from the competitive research: a map where the sentence has
# geography, a chart where it has a number, archival where it has a date.
Q = {
    1: ["materials laboratory sample cutting", "scientist cutting rock sample saw", "italian countryside field ruins"],
    2: ["white mineral inclusions in rock macro", "hand holding ancient mortar fragment", "stone surface texture close up"],
    3: ["peeling old plaster wall", "hands mixing mortar in bucket", "broken brick wall rubble"],
    4: ["pantheon rome", "pantheon rome interior dome", "pantheon rome tourists"],
    5: ["concrete highway bridge underside", "cracked concrete bridge support pillar"],
    6: ["ancient roman ruins in sunlight", "roman forum ruins", "white stones on dark gravel"],
    7: ["civil engineer inspecting concrete structure", "engineer studying blueprints on site", "construction workers building a wall"],
    8: ["smooth grey concrete wall texture", "concrete wall texture close up"],
    9: ["water dripping down concrete wall", "thin crack in concrete surface"],
    10: ["rusted steel rebar exposed in concrete", "crumbling concrete wall decay", "highway underpass concrete columns", "rust stains on concrete pillar"],
    11: ["hairline crack in concrete macro", "grey cement powder pouring", "rain water running over concrete"],
    12: ["cement factory chimney industrial", "industrial smoke stacks against sky", "concrete mixer truck pouring"],
    13: ["aerial view city construction cranes", "demolition of concrete building", "cement bags stacked in warehouse"],
    14: ["volcano crater smoking aerial", "grey volcanic ash texture"],
    15: ["Naples bay Mount Vesuvius", "mixing mortar with a trowel", "waves crashing against stone seawall", "quartz crystal cluster natural"],
    16: ["old stone wall texture detail", "hand touching ancient stone wall"],
    17: ["materials scientist inspecting concrete sample", "researcher looking through microscope", "writing notes in laboratory notebook", "laboratory microscope equipment closeup"],
    18: ["archaeologist brushing artifact excavation", "italian countryside ruins landscape", "rock core sample drilling"],
    19: ["white calcite crystal in rock", "fractured stone surface macro", "white powder on microscope slide"],
    20: ["industrial furnace fire heat", "fire flames close up dark background"],
    21: ["roman ruins wall detail", "ancient roman aqueduct arches"],
    22: ["water pouring into bucket slow motion", "white powder mixed with water bowl", "shovel mixing dry cement"],
    23: ["traditional lime kiln burning", "hands mixing mortar in bucket"],
    24: ["industrial reaction vessel steam", "steam rising from hot surface", "heat shimmer over hot surface", "molten metal glowing foundry"],
    25: ["laboratory beaker with clear liquid", "concrete curing in formwork", "Pantheon dome interior light beam"],
    26: ["grey mortar texture close up", "white mineral inclusions in rock macro"],
    27: ["crack spreading across a surface", "water seeping into porous stone", "salt crystals dissolving in water", "crystal growth timelapse macro", "water flowing through stone channel"],
    28: ["repairing stone wall with mortar", "cracked earth filling with water"],
    29: ["laboratory testing machine equipment", "compression testing machine crushing"],
    30: ["concrete cylinder samples laboratory", "mixing concrete in a laboratory", "water flowing through a pipe test"],
    31: ["sealed crack in concrete surface", "laboratory timer stopwatch"],
    32: ["water running through narrow gap", "concrete slab with surface cracks"],
    33: ["measuring with precision calipers", "dry cracked mud ground"],
    34: ["ancient roman forum ruins", "long bridge over water aerial"],
    35: ["road construction crew working", "highway interchange aerial view"],
    36: ["bridge under construction cranes", "factory emissions aerial view", "earth horizon from space"],
    37: ["stack of research papers on desk", "old technical manual pages"],
    38: ["italian countryside field ruins", "hand holding ancient mortar fragment", "ancient roman ruins in sunlight"],
    39: ["latin inscription carved in stone", "ancient manuscript parchment close up", "roman stone inscription letters", "open old book by candlelight"],
}
# A named landmark: trust search rank rather than de-ranking the top hit.
LITERAL = {4}

queries.save(SLUG, Q)
print(f"  queries.json  {sum(len(v) for v in Q.values())} queries / {len(Q)} paragraphs")

# --- Cards, keyed by NEW paragraph index --------------------------------
MIT = "Masic et al., Science Advances, 2023"
C = {
    5: {"kind": "stat", "value": "50 years", "label": "Expected life of a modern concrete bridge", "source": "The Conversation, 2016"},
    11: {"kind": "stat", "value": "0.2 mm", "label": "Widest crack modern concrete can self-seal", "source": "BS 8007 / Concrete Society"},
    12: {"kind": "stat", "value": "8%", "label": "of global greenhouse gas emissions, from cement", "source": MIT},
    13: {"kind": "stat", "value": "1 tonne", "label": "of CO2 released per tonne of cement produced", "source": MIT},
    14: {"kind": "section", "number": "02", "title": "What the chunks actually are"},
    17: {"kind": "quote", "quote": "The idea that the presence of these lime clasts was simply attributed to low quality control always bothered me.", "attribution": "Admir Masic, MIT"},
    18: {"kind": "stat", "value": "2,000 yrs", "label": "Age of the mortar sampled at Privernum, Italy", "source": MIT},
    22: {"kind": "section", "number": "03", "title": "The step we stopped doing"},
    24: {"kind": "stat", "value": "200 C+", "label": "Local hot spots during quicklime hot mixing", "source": MIT},
    31: {"kind": "stat", "value": "0.5 mm", "label": "Cracks fully sealed within two weeks", "source": MIT},
    33: {"kind": "stat", "value": "9%", "label": "Less drying shrinkage after ninety days", "source": MIT},
    34: {"kind": "section", "number": "05", "title": "What it is actually worth"},
    35: {"kind": "stat", "value": "50 years", "label": "The lifespan gain that would actually matter", "source": MIT},
    36: {"kind": "quote", "quote": "If we add fifty or one hundred years to concrete's lifespan, we will require less demolition, less maintenance and less material.", "attribution": "Admir Masic, MIT"},
}
cardplan.save(SLUG, C)
kinds = {}
for v in C.values():
    kinds[v["kind"]] = kinds.get(v["kind"], 0) + 1
print(f"  cards.json    {len(C)} cards {kinds}")

lit_path = config.project_dir(SLUG) / "literal.json"
lit_path.write_text(json.dumps(sorted(LITERAL)), encoding="utf-8")
print(f"  literal.json  paragraphs {sorted(LITERAL)}")

# --- Wire the literal flag into the planner ------------------------------
plan_py = Path("D:/faceless-studio/fvs/stages/plan.py")
src = plan_py.read_text(encoding="utf-8")
anchor = "    # Cards are paragraph-bound for the same reason queries are.\n    from .. import cardplan"
patch = (
    "    # Literal-subject paragraphs (a named landmark) trust search rank\n"
    "    # instead of de-ranking the top hit. Paragraph-bound like everything else.\n"
    "    literal_path = root / \"literal.json\"\n"
    "    if literal_path.is_file():\n"
    "        literal = set(json.loads(literal_path.read_text(encoding=\"utf-8\")))\n"
    "        for shot in shots:\n"
    "            if shot.get(\"paragraph\") in literal:\n"
    "                shot[\"literal\"] = True\n"
    "\n"
    + anchor
)
if "literal_path = root" in src:
    print("  plan.py       literal flag already wired")
else:
    assert anchor in src, "plan.py anchor not found"
    plan_py.write_text(src.replace(anchor, patch), encoding="utf-8")
    print("  plan.py       literal flag wired")

import ast  # noqa: E402
ast.parse(plan_py.read_text(encoding="utf-8"))
print("  plan.py       syntax OK")

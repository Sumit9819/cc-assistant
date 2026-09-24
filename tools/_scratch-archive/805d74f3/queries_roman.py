"""Author B-roll queries for the roman-concrete cut plan."""
import json
from pathlib import Path

Q = {
    # --- Cold open: the Pantheon vs a modern bridge ---
    1: "Pantheon Rome interior dome",
    2: "ancient roman architecture stone columns",
    3: "Pantheon oculus dome ceiling",
    4: "large dome ceiling architecture",
    5: "ancient roman ruins in sunlight",
    6: "concrete highway bridge underside",
    7: "cracked concrete bridge support pillar",
    8: "engineer studying blueprints on site",
    # --- Why modern concrete fails ---
    9: "concrete wall texture close up",
    10: "water dripping down concrete wall",
    11: "rusted steel rebar exposed in concrete",
    12: "crumbling concrete wall decay",
    13: "highway underpass concrete columns",
    14: "hairline crack in concrete macro",
    15: "grey cement powder pouring",
    16: "macro crack in stone surface",
    17: "rain water running over concrete",
    18: "concrete mixer truck pouring",
    19: "cement factory chimney industrial",
    20: "industrial smoke stacks against sky",
    21: "cement bags stacked in warehouse",
    22: "aerial view city construction cranes",
    23: "demolition of concrete building",
    # --- The volcanic ash explanation ---
    24: "volcano crater smoking aerial",
    25: "grey volcanic ash texture",
    26: "Naples bay Mount Vesuvius",
    27: "mixing mortar with a trowel",
    28: "waves crashing against stone seawall",
    29: "mineral crystals macro formation",
    30: "old stone wall texture detail",
    31: "white pebbles on grey ground",
    # --- The white lumps everyone dismissed ---
    32: "hand touching ancient stone wall",
    33: "white mineral inclusions in rock macro",
    34: "peeling old plaster wall",
    35: "hands mixing mortar in bucket",
    36: "broken brick wall rubble",
    37: "scientist examining sample in laboratory",
    38: "researcher looking through microscope",
    39: "writing notes in laboratory notebook",
    40: "ancient roman aqueduct arches",
    41: "construction workers building a wall",
    # --- The analysis ---
    42: "archaeologist brushing artifact excavation",
    43: "italian countryside ruins landscape",
    44: "laboratory microscope equipment closeup",
    45: "rock core sample drilling",
    46: "calcite crystal mineral macro",
    47: "fractured stone surface macro",
    48: "white powder on microscope slide",
    49: "industrial furnace fire heat",
    50: "researcher analysing data on screen",
    # --- Hot mixing ---
    51: "traditional lime kiln burning",
    52: "water pouring into bucket slow motion",
    53: "white powder mixed with water bowl",
    54: "roman ruins wall detail",
    55: "shovel mixing dry cement",
    56: "chemical reaction bubbling in beaker",
    57: "steam rising from hot surface",
    58: "thermal imaging camera heat",
    59: "molten metal glowing foundry",
    60: "fire flames close up dark background",
    61: "chemistry laboratory glassware",
    62: "concrete curing in formwork",
    63: "Pantheon dome interior light beam",
    64: "grey mortar texture close up",
    # --- How the healing works ---
    65: "microscope zooming into material",
    66: "crack spreading across a surface",
    67: "shattered glass crack pattern",
    68: "broken stone split in two",
    69: "magnifying glass over rock sample",
    70: "water droplet falling slow motion",
    71: "water seeping into porous stone",
    72: "salt dissolving in water macro",
    73: "liquid flowing through narrow channel",
    74: "crystal growth timelapse macro",
    75: "branching river delta from above",
    76: "clear liquid stirred in beaker",
    77: "volcanic ash particles close up",
    78: "repairing stone wall with mortar",
    79: "cracked earth filling with water",
    # --- The experiment ---
    80: "laboratory testing machine equipment",
    81: "concrete cylinder samples laboratory",
    82: "mixing concrete in a laboratory",
    83: "compression testing machine crushing",
    84: "water flowing through a pipe test",
    85: "sealed crack in concrete surface",
    86: "water running through narrow gap",
    87: "laboratory timer stopwatch",
    88: "measuring with precision calipers",
    89: "dry cracked mud ground",
    90: "concrete slab with surface cracks",
    # --- What it is worth ---
    91: "ancient roman forum ruins",
    92: "modern laboratory researchers working",
    93: "empty university lecture hall",
    94: "long bridge over water aerial",
    95: "road construction crew working",
    96: "highway interchange aerial view",
    97: "bridge under construction cranes",
    98: "factory emissions aerial view",
    99: "earth horizon from space",
    # --- Still unresolved ---
    100: "stack of research papers on desk",
    101: "scientists discussing at whiteboard",
    102: "old book pages turning",
    103: "ancient manuscript parchment close up",
    104: "latin inscription carved in stone",
    105: "old library shelves full of books",
    106: "roman stone inscription letters",
    107: "quill pen writing on parchment",
    108: "dusty archive shelves of documents",
    109: "open old book by candlelight",
}

root = Path("D:/faceless-studio/projects/roman-concrete")
data = json.loads((root / "cutplan.json").read_text(encoding="utf-8"))

missing = [s["id"] for s in data["shots"] if s["id"] not in Q]
if missing:
    raise SystemExit(f"No query for shots: {missing}")

for shot in data["shots"]:
    shot["query"] = Q[shot["id"]]

(root / "shotlist.json").write_text(
    json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8"
)
print(f"wrote shotlist.json with {len(data['shots'])} queries")
print(f"distinct queries: {len(set(Q.values()))}")

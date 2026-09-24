import re
from pathlib import Path

ROOT = Path("D:/faceless-studio")

# ---------------------------------------------------------------- plan.py
p = ROOT / "fvs/stages/plan.py"; s = p.read_text(encoding="utf-8")

s = s.replace('''    "fast": (2.0, 4.0),          # lists, tension, argument
    "medium": (2.8, 6.0),        # default documentary read
    "slow": (4.0, 8.0),          # dense explanation, atmosphere''',
'''    "fast": (2.4, 4.5),          # lists, tension, argument
    "medium": (3.2, 6.5),        # default documentary read
    "slow": (4.2, 8.5),          # dense explanation, atmosphere''')
s = s.replace("OPEN_TIGHT_FACTOR = 0.72", "OPEN_TIGHT_FACTOR = 0.85")

old_start = s.index("# A hold must raise the FLOOR")
old_end = s.index("def position_pace")
s = s[:old_start] + '''# A punch line is delivered as ONE shot. Cutting it faster splinters it.
PUNCH_MAX_SECONDS = 4.5
# The script's punch lines measured at exactly ten words; nine caught none.
PUNCH_WORDS = 12             # a paragraph this short is a punch line
PUNCH_FACTOR = 0.55          # ...and gets cut like one

# No shot runs shorter than this. The 2026-09-04 cut had twelve of
# twenty-nine shots under 2.5s, a Polaroid on screen for 1.0s and a chapter
# card on 0.65s, and the owner's verdict was "too fast": the pictures came
# and went before they could be read. A punch line still lands as one shot;
# it just cannot be a flash.
ABS_MIN_SHOT = 2.2
# A floored shot (still, card, chapter) may run this far past its floor.
FLOOR_MAX_FACTOR = 1.35
# Seconds the first shot of a still paragraph must run: a Polaroid needs to
# be seen as a photograph, and its push needs room to be perceptible.
STILL_FLOOR = 5.0
# Seconds a chapter card needs when the paragraph carries no other card.
SECTION_FLOOR = 4.0


''' + s[old_end:]

old_start = s.index("def _emit(")
old_end = s.index("def run(")
s = s[:old_start] + '''def _emit(shots: list[dict[str, Any]], words: list[dict[str, Any]], i: int, end_index: int) -> None:
    span = words[i : end_index + 1]
    shots.append(
        {
            "id": len(shots) + 1,
            "start": round(span[0]["start"], 3),
            "end": round(span[-1]["end"], 3),
            "duration": round(span[-1]["end"] - span[0]["start"], 3),
            "text": " ".join(w["word"] for w in span),
            "paragraph": span[0]["paragraph"],
            "query": "",
            "kind": "video",
        }
    )


def _para_end(words: list[dict[str, Any]], i: int) -> int:
    """Index of the last word in the paragraph that words[i] belongs to."""
    para = words[i]["paragraph"]
    while i + 1 < len(words) and words[i + 1]["paragraph"] == para:
        i += 1
    return i


def plan_cuts(
    words: list[dict[str, Any]],
    min_s: float = config.CUT_MIN_SECONDS,
    max_s: float = config.CUT_MAX_SECONDS,
    punch_paragraphs: set[int] | None = None,
    floors: dict[int, list[float]] | None = None,
    anchored: set[int] | None = None,
) -> list[dict[str, Any]]:
    """Carve word timings into shots at natural boundaries, with shape.

    `floors[p]` gives, per shot of paragraph p, the seconds that shot must
    run - a still first, then the card that follows it. `anchored` names the
    paragraphs that must begin a new shot (stills, cards, chapters). A shot
    may run INTO the paragraphs after its own only when they are not
    anchored, and only to reach its floor or to absorb a paragraph shorter
    than ABS_MIN_SHOT. Every shot keeps the paragraph it started in, which
    is what cards, stills and query pools are keyed on.
    """
    if not words:
        return []

    base_min, base_max = min_s, max_s
    punch_paragraphs = punch_paragraphs or set()
    floors = floors or {}
    anchored = anchored or set()
    total = words[-1]["end"]
    emitted: dict[int, int] = {}   # shots emitted per paragraph
    shots: list[dict[str, Any]] = []
    i = 0

    while i < len(words):
        shot_start = words[i]["start"]
        para = words[i]["paragraph"]
        k = emitted.get(para, 0)
        para_floors = floors.get(para) or []
        floor = float(para_floors[k]) if k < len(para_floors) else 0.0

        pace = local_pace(words, i) * position_pace(shot_start, total)
        factor = PUNCH_FACTOR if (para in punch_paragraphs and not floor) else 1.0
        min_s = base_min * pace * factor
        max_s = base_max * pace * factor
        if floor:
            min_s = max(min_s, floor)
            max_s = max(max_s, floor * FLOOR_MAX_FACTOR)
        min_s = max(min_s, ABS_MIN_SHOT)
        max_s = max(max_s, min_s * 1.5)
        ideal = (min_s + max_s) / 2

        # How far this shot may reach. Its own paragraph always; the next
        # ones only while they are unanchored and either this shot cannot
        # otherwise reach its floor or the next paragraph would be a flash.
        limit = _para_end(words, i)
        while limit + 1 < len(words):
            nxt = words[limit + 1]["paragraph"]
            if nxt in anchored:
                break
            nxt_end = _para_end(words, limit + 1)
            nxt_dur = words[nxt_end]["end"] - words[limit + 1]["start"]
            span = words[limit]["end"] - shot_start
            if span < min_s or nxt_dur < ABS_MIN_SHOT:
                limit = nxt_end
            else:
                break

        # A punch line is one shot. Take the whole reach if it fits.
        if para in punch_paragraphs and not floor:
            if words[limit]["end"] - shot_start <= max(PUNCH_MAX_SECONDS, min_s):
                _emit(shots, words, i, limit)
                emitted[para] = k + 1
                i = limit + 1
                continue

        candidates = [
            j
            for j in range(i, limit + 1)
            if min_s * SEARCH_LOW <= words[j]["end"] - shot_start <= max_s * SEARCH_HIGH
            and words[j]["end"] - shot_start >= floor
        ]
        # Always let the next sentence end compete, even past the window.
        # A tight opening window could not reach a 5.6s sentence end and
        # cut the first line of the video into three fragments.
        for j in range(i, limit + 1):
            if words[j]["end"] - shot_start > max_s * 2.0:
                break
            if _ends_with(words[j]["word"], SENTENCE_END) and words[j]["end"] - shot_start >= floor:
                if j not in candidates:
                    candidates.append(j)
                break

        if candidates:
            end_index = max(
                candidates,
                key=lambda j: score_boundary(words, j, shot_start, min_s, max_s, ideal),
            )
        else:
            # Reach shorter than the window, or a floor the reach cannot
            # meet: run to the floor if possible, else to the reach.
            end_index = i
            while end_index < limit and (
                words[end_index]["end"] - shot_start < min_s
                or words[end_index + 1]["end"] - shot_start <= max_s
            ):
                end_index += 1

        # Never leave a flash behind: a remainder shorter than the floor
        # joins this shot rather than becoming a runt of its own.
        if end_index < limit and words[limit]["end"] - words[end_index + 1]["start"] < ABS_MIN_SHOT:
            end_index = limit

        _emit(shots, words, i, end_index)
        emitted[para] = k + 1
        i = end_index + 1

    return shots


''' + s[old_end:]

old = s[s.index("    # Holds land on the establishing shot"):s.index("    shots = plan_cuts(")]
new = '''    # Chapter starts, stills and cards each need a shot long enough to be
    # read. They also anchor a shot boundary: a shot may absorb a short
    # paragraph after it, never one that opens a chapter or carries a card.
    section_starts: set[int] = set()
    script_path = root / "script.md"
    if script_path.is_file():
        from .voice import parse_script_sections

        sectioned = parse_script_sections(script_path.read_text(encoding="utf-8"))
        section_starts = {i + 2 for i, (_, ends) in enumerate(sectioned) if ends}

    still_plan: dict[int, dict] = {}
    still_path = root / "stillplan.json"
    if still_path.is_file():
        raw = json.loads(still_path.read_text(encoding="utf-8"))
        still_plan = {int(k): v for k, v in raw.get("paragraphs", {}).items()}

    from .. import cardplan

    card_plan = cardplan.load(slug)
    floors: dict[int, list[float]] = {}
    for para in still_plan:
        floors.setdefault(para, []).append(STILL_FLOOR)
    for para, spec in card_plan.items():
        floors.setdefault(para, []).append(cardplan.read_floor(spec.get("kind", "")))
    for para in section_starts:
        floors.setdefault(para, [SECTION_FLOOR])
    anchored = section_starts | set(still_plan) | set(card_plan)

    counts: dict[int, int] = {}
    for w in data["words"]:
        counts[w["paragraph"]] = counts.get(w["paragraph"], 0) + 1
    punch_paragraphs = {p for p, n in counts.items() if n <= PUNCH_WORDS}

'''
s = s.replace(old, new)
s = s.replace('''    shots = plan_cuts(
        data["words"], min_s=low, max_s=high,
        hold_paragraphs=hold_paragraphs, punch_paragraphs=punch_paragraphs,
    )''', '''    shots = plan_cuts(
        data["words"], min_s=low, max_s=high,
        punch_paragraphs=punch_paragraphs, floors=floors, anchored=anchored,
    )''')
s = s.replace('''    # Cards are paragraph-bound for the same reason queries are.
    from .. import cardplan

    card_plan = cardplan.load(slug)
    if card_plan:''', '''    # Cards are paragraph-bound for the same reason queries are.
    if card_plan:''')
s = s.replace('''f"{len(hold_paragraphs)} holds, {len(punch_paragraphs)} punches)")''',
              '''f"{len(floors)} floored paragraphs, {len(punch_paragraphs)} punches)")''')
assert "hold_paragraphs" not in s, [l for l in s.splitlines() if "hold_paragraphs" in l]
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- cardplan.py
p = ROOT / "fvs/cardplan.py"; s = p.read_text(encoding="utf-8")
s = s.replace('''# A card needs its delay, its slide and a fade out; on a shot shorter than
# this it is a flash, not a card.
MIN_CARD_SHOT = 2.0''', '''# A card needs its delay, its slide and a fade out; on a shot shorter than
# this it is a flash, not a card.
MIN_CARD_SHOT = 3.0

# Kinds the plan refuses. An emphasis card restates the spoken line in large
# type and carries nothing the narration does not; the owner cut five from
# roman-concrete (2026-09-02) and rejected "Ended by a memo." again on
# 2026-09-04. It is not a style to tune, it is a kind that does not exist.
REFUSED_KINDS = {"emphasis"}''')
s = s.replace('''# Seconds a card of each kind needs to be read. A stat is one figure
# and one line; a timeline is four dates with notes, and 2.2s of it
# is a flash, not a graphic.
MIN_READ_SHOT = {"timeline": 5.0, "compare": 5.0, "stat": 3.2}''',
'''# Seconds a card of each kind needs to be read. A stat is one figure and
# one line; a timeline is four dates with notes, and 2.2s of it is a flash,
# not a graphic. The planner takes these as floors on the shot the card
# lands on, so the shot is cut to fit the card rather than the card
# squeezed into whatever shot it found.
MIN_READ_SHOT = {"timeline": 8.0, "compare": 6.0, "stat": 4.5, "section": 4.0}


def read_floor(kind: str) -> float:
    return MIN_READ_SHOT.get(kind, MIN_CARD_SHOT)''')
s = s.replace('''MOTION_TEMPLATES = {"stat": "stat_count.html", "emphasis": "emphasis_fade.html",
                    "section": "section_intro.html", "timeline": "timeline_card.html",
                    "compare": "compare_card.html"}''',
'''MOTION_TEMPLATES = {"stat": "stat_count.html", "section": "section_intro.html",
                    "timeline": "timeline_card.html", "compare": "compare_card.html"}''')
s = s.replace('''    elif spec["kind"] == "emphasis":
        payload = {
            "text": spec.get("text", ""),
            "position": "centre" if spec.get("position") == "centre" else "lower",
            "duration": round(duration, 2),
        }
''', '')
old = s[s.index("def _choose_shot("):s.index("def apply(")]
new = '''def _choose_shot(group: list[dict], spec: dict) -> dict | None:
    """The shot a card belongs on.

    Explicit `shot_offset` wins. Otherwise the first shot of the paragraph
    that is NOT a still: a graphic landing on a Polaroid reads as two things
    colliding, so the photograph gets its shot and the card takes the next.
    Anything landing on a shot too short to read advances to the next shot
    of the paragraph that can hold it; if none can, the longest one.
    """
    if not group:
        return None
    start = 0
    if "shot_offset" in spec:
        start = min(int(spec["shot_offset"]), len(group) - 1)
    else:
        while start < len(group) - 1 and group[start].get("still"):
            start += 1

    floor = read_floor(spec.get("kind", ""))
    for shot in group[start:]:
        if float(shot.get("duration", 0)) >= floor and not shot.get("still"):
            return shot
    for shot in group[start:]:
        if float(shot.get("duration", 0)) >= floor:
            return shot
    return max(group, key=lambda s: float(s.get("duration", 0)))


'''
s = s.replace(old, new)
s = s.replace('''        group = [s for s in (by_para.get(paragraph) or []) if s.get("kind") != "scene"]
        shot = _choose_shot(group, spec)''', '''        if spec.get("kind") in REFUSED_KINDS:
            print(f"    paragraph {paragraph}: {spec.get('kind')} cards are refused "
                  f"(they restate the narration); skipped")
            continue
        group = [s for s in (by_para.get(paragraph) or []) if s.get("kind") != "scene"]
        shot = _choose_shot(group, spec)''')
s = s.replace('''        if float(shot["duration"]) - delay < MIN_READ_SHOT.get(kind, MIN_CARD_SHOT) * 0.6:''',
              '''        if float(shot["duration"]) - delay < read_floor(kind) * 0.6:''')
assert "REFUSED_KINDS" in s and "def read_floor" in s
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- config.py
p = ROOT / "fvs/config.py"; s = p.read_text(encoding="utf-8")
s = s.replace('GEMINI_TTS_MODEL = "gemini-2.5-flash-preview-tts"', 'GEMINI_TTS_MODEL = "gemini-3.1-flash-tts-preview"')
old = '''GEMINI_TTS_NOTE = (
    "Read this as the narrator of a calm, serious documentary. Measured pace, "
    "low and even, no salesmanship. Let short sentences land."
)'''
assert old in s
s = s.replace(old, '''GEMINI_TTS_NOTE = (
    "Read this as the narrator of a calm, serious documentary. Measured pace, "
    "low and even, no salesmanship. Let short sentences land. Words in square "
    "brackets are performance directions for the line that follows: obey them, "
    "never speak them."
)''')
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- voice.py
p = ROOT / "fvs/stages/voice.py"; s = p.read_text(encoding="utf-8")
assert "\nimport re" in s
s = s.replace('''def parse_script(text: str) -> list[str]:''', '''# Performance directions the script may carry for the Gemini 3.1 TTS model:
# "[slowly] It is barely copper at all." The bracket is for the voice only;
# every other stage (alignment, captions, planning) sees the clean line.
_DIRECTION = re.compile(r"\\[[a-z][a-z ,.'-]{0,40}\\]\\s*")


def strip_directions(text: str) -> str:
    return re.sub(r"\\s{2,}", " ", _DIRECTION.sub("", text)).strip()


def parse_script(text: str) -> list[str]:''')
old = '''    sectioned = parse_script_sections(script_path.read_text(encoding="utf-8"))
    paragraphs = [p for p, _ in sectioned]
    section_ends = {i for i, (_, ends) in enumerate(sectioned) if ends}
    words = sum(len(p.split()) for p in paragraphs)'''
assert old in s
s = s.replace(old, '''    sectioned = parse_script_sections(script_path.read_text(encoding="utf-8"))
    # `spoken` keeps the bracketed directions for the voice; `paragraphs` is
    # the clean text every later stage aligns and captions against.
    spoken = [p for p, _ in sectioned]
    paragraphs = [strip_directions(p) for p in spoken]
    section_ends = {i for i, (_, ends) in enumerate(sectioned) if ends}
    words = sum(len(p.split()) for p in paragraphs)''')
old = '''        audio, spans = synthesize_gemini(paragraphs, voice=voice, section_ends=section_ends)'''
assert old in s
s = s.replace(old, '''        audio, spans = synthesize_gemini(spoken, voice=voice, section_ends=section_ends)''')
old = '''            digest = hashlib.sha1(f"{voice}|{note}|{text}".encode("utf-8")).hexdigest()[:16]'''
assert old in s
s = s.replace(old, '''            digest = hashlib.sha1(
                f"{config.GEMINI_TTS_MODEL}|{voice}|{note}|{text}".encode("utf-8")
            ).hexdigest()[:16]''')
old = '''    words = max(len(text.split()), 1)
    quota_hits = 0'''
assert old in s
s = s.replace(old, '''    words = max(len(strip_directions(text).split()), 1)
    quota_hits = 0''')
p.write_text(s, encoding="utf-8")
print("patched plan/cardplan/config/voice")

"""Cards land on the shot that says the line, never on a runt.

Under fast pace p3's first shot was a 1.1s fragment; the "Sloppy work."
emphasis card flashed there for half a second and was gone two shots
before the words were spoken. Two rules: an emphasis card lands on the
shot whose narration contains its phrase, and no card lands on a shot
shorter than MIN_CARD_SHOT - it advances to the next long-enough shot in
the paragraph. `shot_offset` stays an explicit override.
"""

import ast
from pathlib import Path

p = Path("D:/faceless-studio/fvs/cardplan.py")
src = p.read_text(encoding="utf-8")

if "MIN_CARD_SHOT" not in src:
    anchor = 'POSITION_CYCLE = ("bottom-left", "bottom-right", "top-right", "centre-left")\n'
    assert anchor in src, "cycle anchor"
    src = src.replace(anchor, anchor + '''
# A card needs its delay, its slide and a fade out; on a shot shorter than
# this it is a flash, not a card.
MIN_CARD_SHOT = 2.0


def _norm(text: str) -> str:
    import re

    return re.sub(r"[^a-z0-9 ]+", " ", text.lower()).split().__str__()


def _choose_shot(group: list[dict], spec: dict) -> dict | None:
    """The shot a card belongs on.

    Explicit `shot_offset` wins. An emphasis card goes to the shot whose
    words contain its phrase (that is the beat it is spoken on). Anything
    landing on a runt shot advances to the next shot of the paragraph that
    can hold it; if none can, the longest one.
    """
    if not group:
        return None
    if "shot_offset" in spec:
        return group[min(int(spec["shot_offset"]), len(group) - 1)]
    start = 0
    if spec.get("kind") == "emphasis" and spec.get("text"):
        phrase = _norm(spec["text"])
        for i, shot in enumerate(group):
            if phrase[1:-1] and phrase[1:-1] in _norm(shot.get("text", "")):
                start = i
                break
    for shot in group[start:]:
        if float(shot.get("duration", 0)) >= MIN_CARD_SHOT:
            return shot
    return max(group, key=lambda s: float(s.get("duration", 0)))
''', 1)

old = '''        group = by_para.get(paragraph) or []
        offset = min(int(spec.get("shot_offset", 0)), max(len(group) - 1, 0))
        shot = group[offset] if group else None
'''
new = '''        group = by_para.get(paragraph) or []
        shot = _choose_shot(group, spec)
'''
if old in src:
    src = src.replace(old, new, 1)
assert new in src, "choose anchor"
ast.parse(src)
p.write_text(src, encoding="utf-8")
print("  cardplan.py  emphasis lands on its line; no card on a shot < 2.0s")

"""Chapter marker (a persistent frame element), and a segment cache stamp
that knows about kinetic overlays and the marker."""
from pathlib import Path

ROOT = Path("D:/faceless-studio")

# ---------------------------------------------------------------- fvs/marks.py
(ROOT / "fvs/marks.py").write_text('''"""The persistent frame: a small chapter marker that never leaves.

Every reference channel measured on 2026-09-04 keeps something on screen
that does not change with the cut - a corner mark, a colour system, dated
lower-thirds - and that is what lets two hundred cuts read as one film.
Ours is the chapter: "02 / THE NUMBER", top-left, small, in the type
system, on every footage shot. It carries information (where you are in
the argument), which is the bar for anything on screen.
"""

from __future__ import annotations

from pathlib import Path
from typing import Any

from . import cards, config

# Position: top-left, clear of the stat card's bottom anchors and the
# section card's centre column.
LEFT, TOP = 96, 64
NUMBER_SIZE, TITLE_SIZE = 22, 22
GAP = 18


def render(number: str, title: str, dest: Path) -> Path:
    """A full-frame transparent PNG with the marker drawn in place."""
    from PIL import Image, ImageDraw

    if dest.is_file():
        return dest
    img = Image.new("RGBA", (config.VIDEO_WIDTH, config.VIDEO_HEIGHT), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    f_num = cards.sans(NUMBER_SIZE, wght=700)
    f_title = cards.sans(TITLE_SIZE, wght=600)
    x = LEFT
    # Soft shadow so the marker reads on bright footage too.
    for dx, dy in ((0, 2), (1, 1)):
        cards._tracked(draw, (x + dx, TOP + dy), number, f_num, (8, 9, 12, 140), 3)
    w = cards._tracked(draw, (x, TOP), number, f_num, cards.ACCENT, 3)
    x += w + GAP
    draw.rectangle([x, TOP + 6, x + 1, TOP + NUMBER_SIZE + 2], fill=(222, 171, 78, 160))
    x += GAP
    text = title.upper()
    for dx, dy in ((0, 2), (1, 1)):
        cards._tracked(draw, (x + dx, TOP + dy), text, f_title, (8, 9, 12, 140), 3)
    cards._tracked(draw, (x, TOP), text, f_title, (246, 242, 235, 235), 3)
    dest.parent.mkdir(parents=True, exist_ok=True)
    img.save(dest)
    return dest


def apply(slug: str, shots: list[dict[str, Any]], sections: dict[int, tuple[str, str]]) -> int:
    """Attach the current chapter's marker to every footage shot.

    `sections` maps the paragraph that opens a chapter to (number, title).
    Stills and scenes carry their own frame; the cold-open shot is left
    clean so the title stands alone.
    """
    if not sections:
        return 0
    out_dir = config.project_dir(slug) / "cards"
    current: tuple[str, str] | None = None
    marked = 0
    for shot in shots:
        para = int(shot.get("paragraph", 0))
        if para in sections:
            current = sections[para]
        shot.pop("mark", None)
        if current is None or shot.get("kind") != "video":
            continue
        if (shot.get("card_spec") or {}).get("kind") == "open":
            continue
        number, title = current
        path = render(number, title, out_dir / f"mark_{number}.png")
        shot["mark"] = str(path.resolve())
        marked += 1
    return marked
''', encoding="utf-8")

# ---------------------------------------------------------------- plan.py hook
p = ROOT / "fvs/stages/plan.py"; s = p.read_text(encoding="utf-8")
old = '''    # Cut energy: a punch line lands slightly closer in, and a section'''
new = '''    # The persistent frame: chapter marker on every footage shot.
    from .. import marks

    sections_for_marks = {
        para: (str(spec.get("number", "")), str(spec.get("title", "")))
        for para, spec in card_plan.items() if spec.get("kind") == "section"
    }
    marked = marks.apply(slug, shots, sections_for_marks)
    if marked:
        print(f"  chapter marker on {marked} shot(s)")

    # Cut energy: a punch line lands slightly closer in, and a section'''
assert s.count(old) == 1; s = s.replace(old, new); p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- compose.py
p = ROOT / "fvs/stages/compose.py"; s = p.read_text(encoding="utf-8")
old = '''        overlay = shot.get("overlay") or {}
        parts = [
            ("card", overlay.get("card", "")),
            ("still", (shot.get("still") or {}).get("path", "") if shot.get("kind") == "still" else ""),
            ("scene", (shot.get("scene") or {}).get("path", "") if shot.get("kind") == "scene" else ""),
        ]'''
new = '''        overlay = shot.get("overlay") or {}
        parts = [
            ("card", overlay.get("card", "")),
            ("still", (shot.get("still") or {}).get("path", "") if shot.get("kind") == "still" else ""),
            ("scene", (shot.get("scene") or {}).get("path", "") if shot.get("kind") == "scene" else ""),
            ("mark", shot.get("mark", "")),
        ] + [
            # Kinetic figures were invisible to the stamp: a shot that gained
            # one kept its cached segment and the figure never appeared.
            (f"kinetic{n}", f"{extra.get('card', '')}@{extra.get('delay', 0)}")
            for n, extra in enumerate(shot.get("overlays") or [])
        ]'''
assert old in s; s = s.replace(old, new)

# marker burn-in: rewrite the final video graph so the marker is the last layer
old = '''    args += [
        "-an",
        "-c:v", "libx264",'''
new = '''    mark = shot.get("mark")
    if mark and Path(mark).is_file():
        args = _with_mark(args, Path(mark))

    args += [
        "-an",
        "-c:v", "libx264",'''
assert old in s; s = s.replace(old, new)
old = '''def _srt_time(seconds: float) -> str:'''
new = '''def _with_mark(args: list[str], mark: Path) -> list[str]:
    """Add the chapter marker as the topmost layer of a segment's graph.

    Every graph built above ends in an unlabelled ",format=yuv420p"; that
    tail is relabelled and the marker (a full-frame transparent PNG) is
    composited over it. Works for the -vf and -filter_complex forms alike.
    """
    index = sum(1 for a in args if a == "-i")   # the marker's input index
    args = args + ["-loop", "1", "-i", mark.as_posix()]
    tail = ",format=yuv420p"
    if "-vf" in args:
        at = args.index("-vf")
        chain = args[at + 1]
        base = chain[: -len(tail)] if chain.endswith(tail) else chain
        graph = f"[0:v]{base}[vb];[{index}:v]format=rgba[mk];[vb][mk]overlay=0:0:eof_action=pass,format=yuv420p"
        return args[:at] + ["-filter_complex", graph] + args[at + 2:]
    at = args.index("-filter_complex")
    graph = args[at + 1]
    if graph.endswith(tail):
        graph = graph[: -len(tail)]
    graph += f"[vb];[{index}:v]format=rgba[mk];[vb][mk]overlay=0:0:eof_action=pass,format=yuv420p"
    return args[:at + 1] + [graph] + args[at + 2:]


def _srt_time(seconds: float) -> str:'''
assert s.count(old) == 1; s = s.replace(old, new, 1)
p.write_text(s, encoding="utf-8")
print("patched marks.py, plan hook, compose stamp + marker")

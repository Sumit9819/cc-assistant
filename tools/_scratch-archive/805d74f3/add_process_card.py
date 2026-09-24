"""Add a `process` card type - a horizontal step diagram - to cards.py,
assign one to the mechanism paragraph, and render a test composite.

The research's rule: a chart appears because a number needs context, a
diagram because a mechanism does. The video's central idea is a four-step
process that a stat card cannot show and that stock footage cannot supply.
"""

import ast
import json
import shutil
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, "D:/faceless-studio")

cards_py = Path("D:/faceless-studio/fvs/cards.py")
src = cards_py.read_text(encoding="utf-8")

PROCESS_FN = '''
def process_card(steps: list[dict[str, str]], title: str = "") -> Any:
    """A horizontal step diagram for a mechanism.

    Each step is a short label and a one-line note, joined by arrows on a
    single translucent band across the middle of the frame. Footage stays
    visible above and below, so the diagram reads as explanation laid over
    the scene rather than a slide replacing it.
    """
    from PIL import ImageDraw

    image = _blank()
    draw = ImageDraw.Draw(image)

    f_title = _font(FONT_BOLD, 40)
    f_label = _font(FONT_BOLD, 44)
    f_note = _font(FONT_REGULAR, 28)
    f_num = _font(FONT_BOLD, 26)

    n = max(len(steps), 1)
    pad = 48
    band_w = CANVAS[0] - MARGIN * 2
    col_w = (band_w - pad * 2) // n
    title_h = _text_extent(draw, title, f_title)[1] + 22 if title else 0
    band_h = title_h + 200 + pad * 2

    x0 = MARGIN
    y0 = (CANVAS[1] - band_h) // 2
    _rounded_panel(draw, (x0, y0, x0 + band_w, y0 + band_h), radius=16)
    draw.rectangle((x0, y0, x0 + band_w, y0 + 6), fill=ACCENT)

    if title:
        draw.text((x0 + pad, y0 + pad - 6), title, font=f_title, fill=MUTED)

    top = y0 + pad + title_h
    for index, step in enumerate(steps):
        cx = x0 + pad + col_w * index
        # Step number in the accent, then label, then note.
        draw.text((cx, top), f"{index + 1:02d}", font=f_num, fill=ACCENT)
        draw.text((cx, top + 34), step.get("label", ""), font=f_label, fill=TEXT)
        note_lines = textwrap.wrap(step.get("note", ""), width=22)[:2]
        ny = top + 34 + 58
        for line in note_lines:
            draw.text((cx, ny), line, font=f_note, fill=MUTED)
            ny += 36
        # Arrow to the next step, drawn in the gutter.
        if index < n - 1:
            ax = cx + col_w - 34
            ay = top + 56
            draw.line((ax - 18, ay, ax + 6, ay), fill=ACCENT, width=4)
            draw.polygon(
                [(ax + 6, ay - 9), (ax + 18, ay), (ax + 6, ay + 9)], fill=ACCENT
            )
    return image

'''

if "def process_card(" not in src:
    anchor = "def stat_card("
    assert anchor in src, "stat_card anchor missing"
    src = src.replace(anchor, PROCESS_FN.lstrip("\n") + "\n\n" + anchor, 1)
    print("  cards.py  process_card added")
else:
    print("  cards.py  process_card already present")

# Route the new kind through render().
old = '''    elif kind == "section":
        image = section_card(card.get("number", ""), card["title"])'''
new = '''    elif kind == "section":
        image = section_card(card.get("number", ""), card["title"])
    elif kind == "process":
        image = process_card(card["steps"], card.get("title", ""))'''
if 'kind == "process"' not in src:
    assert old in src, "render() anchor missing"
    src = src.replace(old, new)
    print("  cards.py  render() routes 'process'")

cards_py.write_text(src, encoding="utf-8")
ast.parse(src)
print("  cards.py  syntax OK")

# --- assign to the mechanism paragraph -------------------------------------
from fvs import cardplan, cards, config  # noqa: E402

plan = cardplan.load("roman-concrete")
plan[27] = {
    "kind": "process",
    "title": "How the crack repairs itself",
    "steps": [
        {"label": "Crack forms", "note": "and runs through a lime clast"},
        {"label": "Water enters", "note": "meets exposed calcium"},
        {"label": "Calcium dissolves", "note": "into a saturated solution"},
        {"label": "Gap seals", "note": "recrystallises as calcite"},
    ],
}
cardplan.save("roman-concrete", plan)
print(f"  cards.json  process card at p27 ({len(plan)} cards total)")

# --- test composite over a real frame ------------------------------------
SP = Path(
    "C:/Users/sumit/AppData/Local/Temp/claude/"
    "c--Users-sumit-Local-Sites-plugintesting-app-public/"
    "805d74f3-c178-47fa-8463-c054b1293383/scratchpad"
)
png = cards.render(plan[27], SP / "process_test.png")
master = config.project_dir("roman-concrete") / "master.mp4"
ff = shutil.which("ffmpeg")
out = SP / "process_composite.png"
subprocess.run(
    [ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", "260",
     "-i", str(master), "-i", str(png),
     "-filter_complex", "[0:v][1:v]overlay=0:0,scale=1100:-1",
     "-frames:v", "1", str(out)],
    check=True,
)
print(f"  composite  {out.name}")

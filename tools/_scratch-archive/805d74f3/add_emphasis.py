"""Words on screen as they are spoken: an `emphasis` card type.

Large, brief, typographic - the key phrase lands on screen at the moment
the voice lands on it. Assigned to the punch paragraphs, which are already
one-line, one-shot beats: the line IS the visual, so it gets the frame.

Distinct from a stat card (figure + label + source) on purpose: no panel,
no source line, one weight. It reads as a title, not a caption.

Safe while compose is running - only cards.py, cards.json and a test render.
"""

import ast
import json
import shutil
import subprocess
import sys
from pathlib import Path

STUDIO = Path("D:/faceless-studio")
ROOT = STUDIO / "projects/roman-concrete"
SP = Path(__file__).resolve().parent
sys.path.insert(0, str(STUDIO))

cards_py = STUDIO / "fvs/cards.py"
src = cards_py.read_text(encoding="utf-8")

FN = '''
def emphasis_card(text: str, position: str = "lower") -> Any:
    """A key phrase, large, for the beat it is spoken on.

    No panel behind it: a heavy stroke keeps it legible over any footage,
    and the absence of a box is what separates a title from a caption.
    Sized so a five-word line fills about two thirds of the frame width.
    """
    from PIL import ImageDraw

    image = _blank()
    draw = ImageDraw.Draw(image)

    words = text.strip().split()
    size = 148 if len(words) <= 3 else 118 if len(words) <= 6 else 96
    font = _font(FONT_BOLD, size)
    lines = textwrap.wrap(text.strip(), width=18 if size > 120 else 24)[:3]
    line_h = max((_text_extent(draw, ln, font)[1] for ln in lines), default=size) + 12
    block_h = line_h * len(lines)

    if position == "centre":
        y = (CANVAS[1] - block_h) // 2
    else:
        y = CANVAS[1] - MARGIN - block_h - 40

    for line in lines:
        w = _text_extent(draw, line, font)[0]
        x = (CANVAS[0] - w) // 2
        draw.text((x, y), line, font=font, fill=TEXT,
                  stroke_width=max(size // 14, 6), stroke_fill=(8, 9, 12, 255))
        y += line_h
    # Accent underline, short, so the eye has somewhere to land.
    draw.rectangle(((CANVAS[0] - 160) // 2, y + 10, (CANVAS[0] + 160) // 2, y + 18), fill=ACCENT)
    return image

'''

if "def emphasis_card(" not in src:
    anchor = "def stat_card("
    assert anchor in src
    src = src.replace(anchor, FN.lstrip("\n") + "\n\n" + anchor, 1)
    print("  cards.py    emphasis_card added")
else:
    print("  cards.py    emphasis_card already present")

old = '''    elif kind == "process":
        image = process_card(card["steps"], card.get("title", ""))'''
new = '''    elif kind == "process":
        image = process_card(card["steps"], card.get("title", ""))
    elif kind == "emphasis":
        image = emphasis_card(card["text"], card.get("position", "lower"))'''
if 'kind == "emphasis"' not in src:
    assert old in src
    src = src.replace(old, new)
    print("  cards.py    render() routes 'emphasis'")
ast.parse(src)
cards_py.write_text(src, encoding="utf-8")

# --- assign to punch lines that carry no other card ------------------------
from fvs import cardplan  # noqa: E402
plan = cardplan.load("roman-concrete")
EMPHASIS = {
    3:  "Sloppy work.",                      # the century-old verdict
    9:  "It cracks. Water gets in.",         # the failure, in five words
    23: "Quicklime. Straight in.",           # the step we stopped doing
    28: "The crack repairs itself.",         # the payoff line
    35: "Fifty.",                            # the real number
}
added = 0
for para, text in EMPHASIS.items():
    if para in plan:
        print(f"  p{para} already has a {plan[para]['kind']} card - skipped")
        continue
    plan[para] = {"kind": "emphasis", "text": text}
    added += 1
cardplan.save("roman-concrete", plan)
print(f"  cards.json  {added} emphasis cards added ({len(plan)} total)")

# --- test render over a real frame -----------------------------------------
from fvs import cards  # noqa: E402
ff = shutil.which("ffmpeg")
tests = [("Fifty.", "lower"), ("The crack repairs itself.", "lower"), ("It cracks. Water gets in.", "centre")]
for i, (text, pos) in enumerate(tests, start=1):
    png = cards.render({"kind": "emphasis", "text": text, "position": pos}, SP / f"emph_{i}.png")
    subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-ss", str(60 + i * 90),
                    "-i", str(ROOT / "master.mp4"), "-i", str(png),
                    "-filter_complex", "[0:v][1:v]overlay=0:0,scale=640:-1",
                    "-frames:v", "1", str(SP / f"emph_c{i}.png")], check=True)
subprocess.run([ff, "-hide_banner", "-loglevel", "error", "-y", "-framerate", "1",
                "-i", str(SP / "emph_c%d.png"), "-vf", "tile=3x1:padding=6:color=#111111",
                "-frames:v", "1", str(SP / "emphasis_grid.png")], check=True)
print("  emphasis_grid.png rendered (over the pre-grade master; the look is judged separately)")

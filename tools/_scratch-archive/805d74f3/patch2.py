"""Type swap in the canvas templates, on-twos in the renderer, static-card
fonts in cards.py."""
import re
from pathlib import Path

ROOT = Path("D:/faceless-studio")
FACES = '''  @font-face { font-family: "Sora"; src: url("../fonts/Sora[wght].ttf"); font-weight: 100 800; }
  @font-face { font-family: "Manrope"; src: url("../fonts/Manrope[wght].ttf"); font-weight: 200 800; }
'''

def swap_faces(s: str) -> str:
    s = re.sub(r'  @font-face \{ font-family: "(Barlow Condensed|Barlow Medium|Fraunces|Fraunces Italic|Plex)";[^\n]*\n', "", s)
    return s.replace("<style>\n", "<style>\n" + FACES, 1)

# ---------------------------------------------------------------- compare_card.html
p = ROOT / "motion/compare_card.html"; s = p.read_text(encoding="utf-8")
s = swap_faces(s)
s = s.replace('''  #eyebrow { position: absolute; left: 110px; top: 92px; font: 34px "Barlow Medium";
             letter-spacing: 9px; color: #deab4e; }
  #source { position: absolute; left: 112px; bottom: 74px; font: 26px "Plex";
            color: #8d8478; letter-spacing: .5px; }
  .num { font-family: "Barlow Condensed"; font-variant-numeric: tabular-nums; }
  .lab { font-family: "Plex"; color: #b7ad9d; }''',
'''  #eyebrow { position: absolute; left: 110px; top: 92px; font: 700 28px "Manrope";
             letter-spacing: 7px; color: #deab4e; }
  #source { position: absolute; left: 112px; bottom: 74px; font: 600 22px "Manrope";
            color: #8d8478; letter-spacing: 2px; text-transform: uppercase; }''')
# Canvas fonts: Sora is wide where Barlow Condensed was narrow, so figures
# come down ~25% to keep the same footprint.
repl = {
    'ctx.font = `${size}px "Plex"`;': 'ctx.font = `500 ${size}px "Manrope"`;',
    "ctx.font = '86px \"Barlow Condensed\"';": "ctx.font = '700 64px \"Sora\"';",
    "ctx.font = '50px \"Barlow Condensed\"';": "ctx.font = '700 38px \"Sora\"';",
    "ctx.font = '150px \"Barlow Condensed\"';": "ctx.font = '700 120px \"Sora\"';",
    "ctx.font = '34px \"Plex\"';": "ctx.font = '500 32px \"Manrope\"';",
    "ctx.font = '210px \"Barlow Condensed\"';": "ctx.font = '700 160px \"Sora\"';",
    "ctx.font = '46px \"Barlow Condensed\"';": "ctx.font = '700 34px \"Manrope\"';",
    "ctx.font = '30px \"Plex\"';": "ctx.font = '500 30px \"Manrope\"';",
}
for a, b in repl.items():
    assert a in s, a
    s = s.replace(a, b)
# Bars grow over a longer part of the card, and start later, so the card is
# seen before it moves.
s = s.replace("const grow = easeOut((p - i * 0.18) / 0.6);", "const grow = easeOut((p - 0.08 - i * 0.22) / 0.55);")
s = s.replace("const p = clamp(t / (S.duration * 0.8));", "const p = clamp(t / Math.min(S.duration * 0.8, 4.5));")
assert "Barlow" not in s and "Plex" not in s and "Fraunces" not in s
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- timeline_card.html
p = ROOT / "motion/timeline_card.html"; s = p.read_text(encoding="utf-8")
s = swap_faces(s)
s = s.replace('''  #eyebrow { position: absolute; left: 110px; top: 92px; font: 34px "Barlow Medium";
             letter-spacing: 9px; color: #deab4e; }
  #source { position: absolute; left: 112px; bottom: 70px; font: 26px "Plex"; color: #8d8478; }''',
'''  #eyebrow { position: absolute; left: 110px; top: 92px; font: 700 28px "Manrope";
             letter-spacing: 7px; color: #deab4e; }
  #source { position: absolute; left: 112px; bottom: 70px; font: 600 22px "Manrope"; color: #8d8478;
            letter-spacing: 2px; text-transform: uppercase; }''')
repl = {
    "ctx.font = '74px \"Barlow Condensed\"';": "ctx.font = '700 56px \"Sora\"';",
    "ctx.font = '40px \"Fraunces\"';": "ctx.font = '600 34px \"Sora\"';",
    "ctx.font = '28px \"Plex\"';": "ctx.font = '500 26px \"Manrope\"';",
    "ctx.font = '60px \"Barlow Condensed\"';": "ctx.font = '700 48px \"Sora\"';",
    "ctx.font = '30px \"Plex\"';": "ctx.font = '600 26px \"Manrope\"';",
}
for a, b in repl.items():
    assert a in s, a
    s = s.replace(a, b)
# The track fills over at most ~7s however long the shot runs: a 12s shot
# must not mean a 10s crawl, and the last stop should land while it is
# still being spoken about.
s = s.replace("const p = clamp(t / (S.duration * 0.85));", "const p = clamp(t / Math.min(S.duration * 0.85, 7.0));")
assert "Barlow" not in s and "Plex" not in s and "Fraunces" not in s
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- render.mjs: on twos
p = ROOT / "motion/render.mjs"; s = p.read_text(encoding="utf-8")
old = '''const frames = Math.round(duration * fps);'''
new = '''const frames = Math.round(duration * fps);
// Graphics animate "on twos": the pose changes 15 times a second inside a
// 30 fps stream (12-in-24 in the trade), so motion reads as drawn rather
// than tweened. Set "twos": false in a spec for fully smooth motion.
const poseFps = spec.twos === false ? fps : 15;
const poseTime = (t) => Math.floor(t * poseFps + 1e-6) / poseFps;'''
assert old in s; s = s.replace(old, new)
old = '''  await page.evaluate((t) => window.seek(t), i / fps);'''
new = '''  await page.evaluate((t) => window.seek(t), poseTime(i / fps));'''
assert old in s; s = s.replace(old, new)
# spec is parsed before frames is computed? check order.
assert s.index("const spec = JSON.parse") < s.index("const poseFps")
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- cards.py (static fallbacks)
p = ROOT / "fvs/cards.py"; s = p.read_text(encoding="utf-8")
old = '''FRAUNCES = FONT_DIR / "Fraunces[SOFT,WONK,opsz,wght].ttf"
FRAUNCES_ITALIC = FONT_DIR / "Fraunces-Italic[SOFT,WONK,opsz,wght].ttf"
BARLOW = {
    "semibold": FONT_DIR / "BarlowCondensed-SemiBold.ttf",
    "medium": FONT_DIR / "BarlowCondensed-Medium.ttf",
    "regular": FONT_DIR / "BarlowCondensed-Regular.ttf",
}
PLEX = FONT_DIR / "IBMPlexSans[wdth,wght].ttf"'''
new = '''# Type system since 2026-09-04: Sora (display, figures) and Manrope (labels,
# sources), both variable on a single wght axis. The helper names below kept
# their old signatures so every caller still works; the serif/condensed
# distinction is now a weight, not a family.
SORA = FONT_DIR / "Sora[wght].ttf"
MANROPE = FONT_DIR / "Manrope[wght].ttf"
CONDENSED_WEIGHT = {"semibold": 700, "medium": 600, "regular": 500}'''
assert old in s; s = s.replace(old, new)
old = s[s.index("def serif("):s.index("# --- text helpers")]
new = '''def serif(size: int, wght: int = 500, italic: bool = False, soft: int = 20):
    """Display face. Name kept from the Fraunces era; it is Sora now."""
    return _load(SORA, size, (max(100, min(800, wght + 100)),))


def condensed(size: int, weight: str = "semibold"):
    """Figures. Sora is wider than Barlow Condensed, so sizes step down."""
    return _load(SORA, int(size * 0.78), (CONDENSED_WEIGHT.get(weight, 700),))


def sans(size: int, wght: int = 400):
    return _load(MANROPE, size, (max(200, min(800, wght + 100)),))


'''
s = s.replace(old, new)
s = s.replace("(Fraunces: opsz, wght, SOFT, WONK; Plex:\n    wght, wdth)", "(Sora and Manrope: wght only)")
p.write_text(s, encoding="utf-8")
print("patched compare/timeline/render.mjs/cards.py")

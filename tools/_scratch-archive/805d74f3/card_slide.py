"""Cards that move: slide in from the edge instead of fading in place.

The research on section transitions: "lower-thirds slide in rather than
hard-cutting." Ours fade. A fade is a change of opacity; a slide is a
change of position, and motion is what the eye tracks. The out is kept as
a fade so the card leaves quietly rather than yanking attention back.

Direction follows the card's anchor: left-anchored cards enter from the
left, right-anchored from the right, centred and full-frame cards rise
from below by a small amount. The overlay x/y expressions do the work;
the card PNG is unchanged.

DO NOT run while compose is in flight. Bump GRADE_VERSION so segments
carrying a card re-render once.
"""

import ast
from pathlib import Path

p = Path("D:/faceless-studio/fvs/stages/compose.py")
src = p.read_text(encoding="utf-8")

old = '''        graph = (
            f"[0:v]{base_chain}[base];"
            f"[1:v]format=rgba,"
            f"fade=t=in:st=0:d={fade}:alpha=1,"
            f"fade=t=out:st={out_start:.3f}:d={fade}:alpha=1,"
            f"tpad=start_duration={hold_in:.3f}:start_mode=clone:color=black@0[card];"
            f"[base][card]overlay=0:0:eof_action=pass,format=yuv420p"
        )'''
new = '''        # Slide direction from the card's anchor. `t` in the overlay expression
        # is the base timeline, so the slide starts when the card becomes
        # visible (after hold_in) and settles over SLIDE seconds. An eased
        # curve (1 - (1-p)^3) lands softly instead of stopping dead.
        spec = shot.get("card_spec") or {}
        pos = spec.get("position", "bottom-left")
        kind = spec.get("kind", "stat")
        slide = CARD_SLIDE
        p_expr = f"min(max((t-{hold_in:.3f})/{slide},0),1)"
        ease = f"(1-pow(1-{p_expr},3))"
        if kind in ("section", "process", "quote", "emphasis") or "centre" in pos:
            x_expr, y_expr = "0", f"({CARD_RISE}*(1-{ease}))"
        elif "right" in pos:
            x_expr, y_expr = f"({CARD_TRAVEL}*(1-{ease}))", "0"
        else:
            x_expr, y_expr = f"(-{CARD_TRAVEL}*(1-{ease}))", "0"
        graph = (
            f"[0:v]{base_chain}[base];"
            f"[1:v]format=rgba,"
            f"fade=t=in:st=0:d={min(fade, slide):.3f}:alpha=1,"
            f"fade=t=out:st={out_start:.3f}:d={fade}:alpha=1,"
            f"tpad=start_duration={hold_in:.3f}:start_mode=clone:color=black@0[card];"
            f"[base][card]overlay=x='{x_expr}':y='{y_expr}':eof_action=pass,format=yuv420p"
        )'''
assert old in src, "overlay graph anchor"
src = src.replace(old, new)

old2 = 'CARD_FADE = 0.4\nCARD_DELAY = 0.35\n'
new2 = ('CARD_FADE = 0.4\nCARD_DELAY = 0.35\n'
        '# Slide-in: distance and duration. 0.45s reads as deliberate; the travel\n'
        '# is a third of the frame so the card visibly arrives rather than twitches.\n'
        'CARD_SLIDE = 0.45\nCARD_TRAVEL = 640\nCARD_RISE = 120\n')
assert old2 in src, "card constants anchor"
src = src.replace(old2, new2)

old3 = 'GRADE_VERSION = "g1"'
new3 = 'GRADE_VERSION = "g2"   # g2: cards slide in'
assert old3 in src, "grade version anchor"
src = src.replace(old3, new3)

ast.parse(src)
p.write_text(src, encoding="utf-8")
print("  compose.py  cards slide in (direction by anchor), GRADE_VERSION -> g2")

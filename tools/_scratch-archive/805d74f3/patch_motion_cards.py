"""Motion cards in the pipeline: stat cards become animated alpha WebMs.

At plan time cardplan renders a motion variant sized to the shot it landed
on (the plan knows the duration), cached by a spec+duration fingerprint so
re-planning is free. If node or the render fails, the static PNG stands -
motion is an upgrade, never a dependency. Compose plays a .webm overlay by
forcing the libvpx-vp9 DECODER before -i (the native vp9 decoder silently
drops the alpha) and skips the fade/slide - the animation carries its own
entry and exit.
"""

import ast
from pathlib import Path

STUDIO = Path("D:/faceless-studio")

# --- cardplan.py -------------------------------------------------------------
p = STUDIO / "fvs/cardplan.py"
src = p.read_text(encoding="utf-8")
if "def render_motion" not in src:
    anchor = "MIN_CARD_SHOT = 2.0\n"
    assert anchor in src
    src = src.replace(anchor, anchor + '''
# Card kinds with a motion template in motion/. Others stay static PNGs.
MOTION_TEMPLATES = {"stat": "stat_count.html"}


def render_motion(spec: dict, duration: float, target) -> bool:
    """Render a card's animated variant; True on success.

    The webm runs exactly the shot's visible span, so the animation's own
    entry and exit replace the compositor's fade and slide. A fingerprint
    sidecar makes re-planning free; any failure leaves the static PNG in
    charge.
    """
    import hashlib
    import json as _json
    import re as _re
    import shutil
    import subprocess

    template = STUDIO_MOTION / MOTION_TEMPLATES[spec["kind"]]
    if spec["kind"] == "stat":
        m = _re.match(r"^\\s*(\\d[\\d,.]*)\\s*(.*)$", spec.get("value", ""))
        number, unit = (m.group(1), m.group(2).strip()) if m else (spec.get("value", ""), "")
        if unit in ("", "%", "+"):
            number, unit = number + unit, ""
        payload = {
            "value": number, "unit": unit, "label": spec.get("label", ""),
            "source": (spec.get("source") or "").upper(),
            "position": spec.get("position", "bottom-left"),
            "duration": round(duration, 2),
        }
    else:
        return False

    blob = _json.dumps(payload, sort_keys=True)
    stamp = target.with_suffix(".json")
    fingerprint = hashlib.sha1(blob.encode()).hexdigest()
    if target.is_file() and stamp.is_file() and stamp.read_text(encoding="utf-8") == fingerprint:
        return True
    node = shutil.which("node")
    if node is None or not template.is_file():
        return False
    spec_file = target.with_suffix(".spec.json")
    spec_file.write_text(blob, encoding="utf-8")
    proc = subprocess.run(
        [node, str(STUDIO_MOTION / "render.mjs"), str(template), str(spec_file),
         str(target), f"{duration:.2f}", "30"],
        capture_output=True, text=True, encoding="utf-8", errors="replace",
        cwd=str(STUDIO_MOTION),
    )
    if proc.returncode != 0 or not target.is_file():
        print(f"    motion card failed ({(proc.stderr or proc.stdout).strip()[:90]}); static PNG stands")
        return False
    stamp.write_text(fingerprint, encoding="utf-8")
    return True

''', 1)
    # STUDIO_MOTION constant near the top imports.
    head = "from . import cards, config\n"
    assert head in src
    src = src.replace(head, head + '\nfrom pathlib import Path as _Path\n\nSTUDIO_MOTION = _Path(__file__).resolve().parent.parent / "motion"\n', 1)

old = '''        target = card_dir / f"card_p{paragraph:03d}.png"
        cards.render(spec, target)
        shot["card_spec"] = spec
        shot["overlay"] = {
            "card": str(target.resolve()),
            "fade": 0.4,
            "delay": 0.35,
        }
'''
new = '''        target = card_dir / f"card_p{paragraph:03d}.png"
        cards.render(spec, target)
        card_path = target
        if spec.get("kind") in MOTION_TEMPLATES and not spec.get("static"):
            delay = 0.35
            webm = card_dir / f"motion_p{paragraph:03d}.webm"
            if render_motion(spec, max(float(shot["duration"]) - delay, 1.5), webm):
                card_path = webm
        shot["card_spec"] = spec
        shot["overlay"] = {
            "card": str(card_path.resolve()),
            "fade": 0.4,
            "delay": 0.35,
        }
'''
if old in src:
    src = src.replace(old, new, 1)
assert new in src, "apply overlay anchor"
ast.parse(src)
p.write_text(src, encoding="utf-8")
print("  cardplan.py  stat cards render an animated variant, PNG as fallback")

# --- compose.py --------------------------------------------------------------
p = STUDIO / "fvs/stages/compose.py"
src = p.read_text(encoding="utf-8")
if "is_motion" not in src:
    old = '''        args += ["-loop", "1", "-i", card_path.as_posix()]
'''
    new = '''        is_motion = card_path.suffix == ".webm"
        if is_motion:
            # The DECODER must be forced: ffmpeg's native vp9 decoder reads
            # the file fine and silently throws the alpha away.
            args += ["-c:v", "libvpx-vp9", "-i", card_path.as_posix()]
        else:
            args += ["-loop", "1", "-i", card_path.as_posix()]
'''
    assert old in src
    src = src.replace(old, new, 1)

    old_graph = '''        graph = (
            f"[0:v]{base_chain}[base];"
            f"[1:v]format=rgba,"
            f"fade=t=in:st=0:d={min(fade, slide):.3f}:alpha=1,"
'''
    new_graph = '''        if is_motion:
            # The animation carries its own entry and exit; the compositor
            # only delays it into the shot.
            graph = (
                f"[0:v]{base_chain}[base];"
                f"[1:v]format=rgba,"
                f"tpad=start_duration={hold_in:.3f}:start_mode=add:color=black@0[card];"
                f"[base][card]overlay=0:0:eof_action=pass,format=yuv420p"
            )
            args += ["-filter_complex", graph]
        else:
            graph = (
                f"[0:v]{base_chain}[base];"
                f"[1:v]format=rgba,"
                f"fade=t=in:st=0:d={min(fade, slide):.3f}:alpha=1,"
'''
    assert old_graph in src
    src = src.replace(old_graph, new_graph, 1)
    # Close the else-block: indent the remainder of the original graph and its use.
    old_tail = '''            f"fade=t=out:st={out_start:.3f}:d={fade}:alpha=1,"
            f"tpad=start_duration={hold_in:.3f}:start_mode=clone:color=black@0[card];"
            f"[base][card]overlay=x='{x_expr}':y='{y_expr}':eof_action=pass,format=yuv420p"
        )
        args += ["-filter_complex", graph]
'''
    new_tail = '''                f"fade=t=out:st={out_start:.3f}:d={fade}:alpha=1,"
                f"tpad=start_duration={hold_in:.3f}:start_mode=clone:color=black@0[card];"
                f"[base][card]overlay=x='{x_expr}':y='{y_expr}':eof_action=pass,format=yuv420p"
            )
            args += ["-filter_complex", graph]
'''
    assert old_tail in src
    src = src.replace(old_tail, new_tail, 1)
ast.parse(src)
p.write_text(src, encoding="utf-8")
print("  compose.py   .webm overlays: forced libvpx-vp9 decode, delay only")

# --- polish-test gets a stat card on its free paragraph ----------------------
import json

cards_path = STUDIO / "projects/polish-test/cards.json"
data = json.loads(cards_path.read_text(encoding="utf-8"))
if "1" not in data["paragraphs"]:
    data["paragraphs"]["1"] = {
        "kind": "stat", "value": "1,900 yrs",
        "label": "The Pantheon's unreinforced dome, still standing",
        "source": "curia.pantheonroma.com",
    }
    cards_path.write_text(json.dumps(data, indent=1, ensure_ascii=False), encoding="utf-8")
print("  polish-test  p1 stat card added (motion test)")

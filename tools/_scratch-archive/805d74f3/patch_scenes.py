"""Wire scenes through plan, assets, compose, originality and the CLI, and
register the emphasis/section motion card templates."""

import ast
from pathlib import Path

STUDIO = Path("D:/faceless-studio")


def patch(rel, fn):
    p = STUDIO / rel
    src = p.read_text(encoding="utf-8")
    out = fn(src)
    ast.parse(out)
    p.write_text(out, encoding="utf-8")
    print(f"  {rel}")


# --- cardplan: new motion templates + scene skip -----------------------------
def cardplan_patch(src):
    old = 'MOTION_TEMPLATES = {"stat": "stat_count.html"}'
    new = ('MOTION_TEMPLATES = {"stat": "stat_count.html", "emphasis": "emphasis_fade.html",\n'
           '                    "section": "section_intro.html"}')
    if old in src:
        src = src.replace(old, new, 1)
    assert 'emphasis_fade.html' in src

    anchor = '''    else:
        return False

    blob = _json.dumps(payload, sort_keys=True)'''
    replacement = '''    elif spec["kind"] == "emphasis":
        payload = {
            "text": spec.get("text", ""),
            "position": "centre" if spec.get("position") == "centre" else "lower",
            "duration": round(duration, 2),
        }
    elif spec["kind"] == "section":
        payload = {
            "number": spec.get("number", ""), "title": spec.get("title", ""),
            "duration": round(duration, 2),
        }
    else:
        return False

    blob = _json.dumps(payload, sort_keys=True)'''
    if "elif spec[\"kind\"] == \"emphasis\":" not in src:
        assert anchor in src, "payload anchor"
        src = src.replace(anchor, replacement, 1)

    old_group = "        group = by_para.get(paragraph) or []\n        shot = _choose_shot(group, spec)\n"
    new_group = ('        # A scene owns its paragraph; a card there would fight the animation.\n'
                 '        group = [s for s in (by_para.get(paragraph) or []) if s.get("kind") != "scene"]\n'
                 '        shot = _choose_shot(group, spec)\n')
    if old_group in src:
        src = src.replace(old_group, new_group, 1)
    assert '!= "scene"' in src
    return src


# --- plan: place scenes after stills are marked ------------------------------
def plan_patch(src):
    anchor = '        print(f"  {len(marked)} archival still(s) placed on section holds")\n'
    addition = '''
    from .. import scenes as scenes_lib

    scene_plan = scenes_lib.load(slug)
    if scene_plan:
        shots = scenes_lib.place(slug, shots, scene_plan)
'''
    if "scenes_lib.place" not in src:
        assert anchor in src, "still print anchor"
        src = src.replace(anchor, anchor + addition, 1)
    return src


# --- assets: scenes need no footage ------------------------------------------
def assets_patch(src):
    old = '    shots = [s for s in shots if s.get("kind") != "still"]\n'
    new = '    shots = [s for s in shots if s.get("kind") not in ("still", "scene")]\n'
    if old in src:
        src = src.replace(old, new, 1)
    assert '("still", "scene")' in src
    return src


# --- originality: a scene is original animation ------------------------------
def originality_patch(src):
    old = '''    covered = sum(
        s["duration"] for s in shots if s.get("overlay") or s.get("artifact")
    )'''
    new = '''    # A scene is original animation - the strongest substantive claim here.
    covered = sum(
        s["duration"]
        for s in shots
        if s.get("overlay") or s.get("artifact") or s.get("kind") == "scene"
    )'''
    if old in src:
        src = src.replace(old, new, 1)
    assert '== "scene"' in src
    return src


# --- compose: play the scene webm as the shot's video ------------------------
def compose_patch(src):
    old = '''    still = shot.get("still") if shot.get("kind") == "still" else None
    asset = shot.get("asset")
    if still is None and not asset:
        raise ValueError(f"Shot {shot['id']} has no asset - run the assets stage")
'''
    new = '''    still = shot.get("still") if shot.get("kind") == "still" else None
    scene = shot.get("scene") if shot.get("kind") == "scene" else None
    asset = shot.get("asset")
    if scene is None and still is None and not asset:
        raise ValueError(f"Shot {shot['id']} has no asset - run the assets stage")
'''
    if "scene = shot.get" not in src:
        assert old in src, "guard anchor"
        src = src.replace(old, new, 1)

    old_luma = "    luma = None\n    if still is None:\n"
    new_luma = "    luma = None\n    if still is None and scene is None:\n"
    if old_luma in src:
        src = src.replace(old_luma, new_luma, 1)
    assert new_luma in src

    old_still = "    if still is not None:\n"
    new_still = '''    if scene is not None:
        # The template already lives in the design system's palette; grading
        # it would shift a look that is deliberate. Forced decoder as ever.
        video_filter = f"fps={config.VIDEO_FPS},setsar=1"
        args = [
            "-c:v", "libvpx-vp9",
            "-t", f"{duration:.3f}",
            "-i", Path(scene["path"]).as_posix(),
        ]
    elif still is not None:
'''
    if "The template already lives" not in src:
        assert old_still in src, "still branch anchor"
        src = src.replace(old_still, new_still, 1)

    old_key = '''        if shot.get("kind") == "still":
            overlay_key = "still:" + (shot.get("still") or {}).get("path", "") + "|" + overlay_key
'''
    new_key = '''        if shot.get("kind") == "still":
            overlay_key = "still:" + (shot.get("still") or {}).get("path", "") + "|" + overlay_key
        if shot.get("kind") == "scene":
            overlay_key = "scene:" + (shot.get("scene") or {}).get("path", "") + "|" + overlay_key
'''
    if '"scene:"' not in src:
        assert old_key in src, "cache key anchor"
        src = src.replace(old_key, new_key, 1)
    return src


# --- cli: fvs scene ----------------------------------------------------------
def cli_patch(src):
    if "def cmd_scene" in src:
        return src
    cmd = '''def cmd_scene(args: argparse.Namespace) -> int:
    import json as _json
    from pathlib import Path as _P

    from . import scenes

    slug = _slugify(args.slug)
    plan = scenes.load(slug)
    if args.paragraph is None:
        if not plan:
            print("  no scenes booked (sceneplan.json empty or missing)")
            return 0
        for k, v in sorted(plan.items()):
            print(f"  p{k:<3} {v.get('template', 'process_scene'):<16} {v.get('title', '')[:56]}")
        return 0
    if args.spec is None:
        print("  give a spec JSON file: fvs scene <slug> <paragraph> <spec.json>")
        return 1
    spec = _json.loads(_P(args.spec).read_text(encoding="utf-8"))
    template = spec.get("template", "process_scene")
    if template not in scenes.TEMPLATES:
        print(f"  unknown template {template!r}; registered: {', '.join(scenes.TEMPLATES)}")
        return 1
    plan[args.paragraph] = spec
    scenes.save(slug, plan)
    print(f"  p{args.paragraph} -> {template}. Re-run: fvs plan, fvs compose.")
    return 0


def cmd_still('''
    assert src.count("def cmd_still(") == 1
    src = src.replace("def cmd_still(", cmd, 1)

    parser = '''    p = sub.add_parser("scene", help="book a full-frame animated scene to a paragraph")
    p.add_argument("slug")
    p.add_argument("paragraph", type=int, nargs="?")
    p.add_argument("spec", nargs="?", help="JSON file: {template, title, steps, ...}")
    p.set_defaults(func=cmd_scene)

    p = sub.add_parser("still", '''
    assert src.count('    p = sub.add_parser("still", ') == 1
    src = src.replace('    p = sub.add_parser("still", ', parser, 1)
    return src


patch("fvs/cardplan.py", cardplan_patch)
patch("fvs/stages/plan.py", plan_patch)
patch("fvs/stages/assets.py", assets_patch)
patch("fvs/stages/originality.py", originality_patch)
patch("fvs/stages/compose.py", compose_patch)
patch("fvs/cli.py", cli_patch)

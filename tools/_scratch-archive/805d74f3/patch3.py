"""Anchors into the planner, kinetic figures into the card planner and the
compositor, and query pools that respect an anchored query."""
from pathlib import Path

ROOT = Path("D:/faceless-studio")

# ---------------------------------------------------------------- plan.py
p = ROOT / "fvs/stages/plan.py"; s = p.read_text(encoding="utf-8")

old = '''            "paragraph": span[0]["paragraph"],
            "query": "",
            "kind": "video",
        }
    )'''
new = '''            "paragraph": span[0]["paragraph"],
            "query": "",
            "kind": "video",
            "word_index": i,
        }
    )'''
assert old in s; s = s.replace(old, new)

old = '''    floors: dict[int, list[float]] | None = None,
    anchored: set[int] | None = None,
) -> list[dict[str, Any]]:'''
new = '''    floors: dict[int, list[float]] | None = None,
    anchored: set[int] | None = None,
    forced: set[int] | None = None,
) -> list[dict[str, Any]]:'''
assert old in s; s = s.replace(old, new)

old = '''    `floors[p]` gives, per shot of paragraph p, the seconds that shot must
    run - a still first, then the card that follows it. `anchored` names the
    paragraphs that must begin a new shot (stills, cards, chapters).'''
new = '''    `floors[p]` gives, per shot of paragraph p, the seconds that shot must
    run - a still first, then the card that follows it. `anchored` names the
    paragraphs that must begin a new shot (stills, cards, chapters). `forced`
    is a set of WORD indices where a shot must begin: visual anchors, the
    word on which the picture changes to the thing being named.'''
assert old in s; s = s.replace(old, new)

old = '''    floors = floors or {}
    anchored = anchored or set()
    total = words[-1]["end"]'''
new = '''    floors = floors or {}
    anchored = anchored or set()
    forced = forced or set()
    total = words[-1]["end"]'''
assert old in s; s = s.replace(old, new)

old = '''        limit = _para_end(words, i)
        while limit + 1 < len(words):'''
new = '''        # A forced word (visual anchor) is a wall: no shot runs through it.
        wall = min((f for f in forced if f > i), default=len(words)) - 1
        limit = min(_para_end(words, i), wall)
        while limit + 1 < len(words) and limit < wall:'''
assert old in s; s = s.replace(old, new)
old = '''            if span < min_s or nxt_dur < ABS_MIN_SHOT:
                limit = nxt_end
            else:
                break'''
new = '''            if span < min_s or nxt_dur < ABS_MIN_SHOT:
                limit = min(nxt_end, wall)
            else:
                break'''
assert old in s; s = s.replace(old, new)
old = '''        reach = _para_end(words, i)
        while reach + 1 < len(words) and words[reach + 1]["paragraph"] not in anchored:
            reach = _para_end(words, reach + 1)'''
new = '''        reach = min(_para_end(words, i), wall)
        while reach + 1 < len(words) and reach < wall and words[reach + 1]["paragraph"] not in anchored:
            reach = min(_para_end(words, reach + 1), wall)'''
assert old in s; s = s.replace(old, new)

# run(): resolve anchors, force them, then bind queries and the lead.
old = '''    shots = plan_cuts(
        data["words"], min_s=low, max_s=high,
        punch_paragraphs=punch_paragraphs, floors=floors, anchored=anchored,
    )
    if not shots:
        raise ValueError("Cut planner produced no shots")
'''
new = '''    from .. import anchors as anchors_lib

    placed = anchors_lib.resolve(data["words"], anchors_lib.load(slug))
    forced = {i for i, spec in placed.items() if not spec.get("kinetic_only")}

    shots = plan_cuts(
        data["words"], min_s=low, max_s=high,
        punch_paragraphs=punch_paragraphs, floors=floors, anchored=anchored,
        forced=forced,
    )
    if not shots:
        raise ValueError("Cut planner produced no shots")

    # Anchored shots show the thing being named, and the cut lands a few
    # frames before the word so the picture is there when the word is.
    bound = 0
    for index, shot in enumerate(shots):
        spec = placed.get(shot.get("word_index", -1))
        if not spec or spec.get("kinetic_only") or not spec.get("query"):
            continue
        shot["query"] = spec["query"]
        shot["anchor"] = spec["phrase"]
        bound += 1
        if index > 0:
            lead = min(anchors_lib.LEAD, max(float(shots[index - 1]["duration"]) - ABS_MIN_SHOT, 0.0))
            shot["start"] = round(float(shot["start"]) - lead, 3)
            shot["duration"] = round(float(shot["end"]) - float(shot["start"]), 3)
            shots[index - 1]["end"] = shot["start"]
            shots[index - 1]["duration"] = round(shot["start"] - float(shots[index - 1]["start"]), 3)
    if placed:
        print(f"  anchors: {bound} shot(s) bound to what is being named, "
              f"{sum(1 for v in placed.values() if v.get('kinetic'))} kinetic figure(s)")
'''
assert old in s; s = s.replace(old, new)

# kinetic figures, after cards are attached
old = '''    # Cut energy: a punch line lands slightly closer in, and a section'''
new = '''    # Kinetic figures pop on the word they are spoken on. They ride the
    # shot that contains the word; never a still or a scene.
    kinetic = 0
    for spec in placed.values():
        if not spec.get("kinetic"):
            continue
        at = float(spec["start"])
        host = next((s for s in shots if float(s["start"]) <= at < float(s["end"])), None)
        if host is None or host.get("kind") != "video":
            print(f"    kinetic {spec['kinetic']!r} skipped: no video shot under the word")
            continue
        if cardplan.attach_kinetic(slug, host, spec, at):
            kinetic += 1
    if kinetic:
        print(f"  {kinetic} kinetic figure(s) attached")

    # Cut energy: a punch line lands slightly closer in, and a section'''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- queries.py
p = ROOT / "fvs/queries.py"; s = p.read_text(encoding="utf-8")
old = '''    for shot in shots:
        paragraph = int(shot.get("paragraph", 0))
        pool = pools.get(paragraph)
        if not pool:
            continue
        cursor = used.get(paragraph, 0)'''
new = '''    for shot in shots:
        # An anchored shot already names what it shows.
        if shot.get("query"):
            filled += 1
            continue
        paragraph = int(shot.get("paragraph", 0))
        pool = pools.get(paragraph)
        if not pool:
            continue
        cursor = used.get(paragraph, 0)'''
assert old in s; s = s.replace(old, new); p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- cardplan.py
p = ROOT / "fvs/cardplan.py"; s = p.read_text(encoding="utf-8")
old = '''MOTION_TEMPLATES = {"stat": "stat_count.html", "section": "section_intro.html",
                    "timeline": "timeline_card.html", "compare": "compare_card.html"}'''
new = '''MOTION_TEMPLATES = {"stat": "stat_count.html", "section": "section_intro.html",
                    "timeline": "timeline_card.html", "compare": "compare_card.html",
                    "kinetic": "kinetic_word.html"}

# A kinetic figure lives this long after its word, and never shorter than
# the pop plus a readable hold.
KINETIC_SECONDS = 2.4
KINETIC_MIN = 1.3
KINETIC_LEAD = 0.08'''
assert old in s; s = s.replace(old, new)
old = '''    elif spec["kind"] == "compare":'''
new = '''    elif spec["kind"] == "kinetic":
        payload = {
            "text": str(spec.get("kinetic", "")), "unit": spec.get("unit", ""),
            "note": spec.get("note", ""), "position": spec.get("position", "bottom-left"),
            "duration": round(duration, 2),
        }
    elif spec["kind"] == "compare":'''
assert old in s; s = s.replace(old, new)
old = '''def apply('''
new = '''def attach_kinetic(slug: str, shot: dict[str, Any], spec: dict[str, Any], at: float) -> bool:
    """Render a word-synced figure and add it to the shot's overlay list."""
    start = float(shot["start"])
    delay = max(at - KINETIC_LEAD - start, 0.0)
    room = float(shot["end"]) - start - delay - 0.1
    duration = min(KINETIC_SECONDS, room)
    if duration < KINETIC_MIN:
        print(f"    kinetic {spec.get('kinetic')!r} skipped: {room:.1f}s left in its shot")
        return False
    # Opposite corner from any card already on the shot.
    card_pos = (shot.get("card_spec") or {}).get("position", "")
    position = "bottom-right" if "left" in card_pos else "bottom-left"
    card_dir = config.project_dir(slug) / "cards"
    card_dir.mkdir(exist_ok=True)
    target = card_dir / f"kinetic_w{int(spec['word_index']):04d}.webm"
    payload = dict(spec, kind="kinetic", position=position)
    if not render_motion(payload, duration, target):
        return False
    shot.setdefault("overlays", []).append({"card": str(target.resolve()), "delay": round(delay, 3)})
    return True


def apply('''
assert s.count(old) == 1; s = s.replace(old, new, 1)
old = '''        shot.pop("overlay", None)
        shot.pop("card_spec", None)'''
new = '''        shot.pop("overlay", None)
        shot.pop("overlays", None)
        shot.pop("card_spec", None)'''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- compose.py
p = ROOT / "fvs/stages/compose.py"; s = p.read_text(encoding="utf-8")
old = '''        if is_motion:
            # The animation carries its own entry and exit; the compositor
            # only delays it into the shot.
            graph = (
                f"[0:v]{base_chain}[base];"
                f"[1:v]format=rgba,"
                f"tpad=start_duration={hold_in:.3f}:start_mode=add:color=black@0[card];"
                f"[base][card]overlay=0:0:eof_action=pass,format=yuv420p"
            )
            args += ["-filter_complex", graph]'''
new = '''        if is_motion:
            # The animation carries its own entry and exit; the compositor
            # only delays it into the shot. Kinetic figures stack on top as
            # further full-frame alpha layers, each delayed to its word.
            layers = [(hold_in, 1)]
            for extra in shot.get("overlays") or []:
                extra_path = Path(extra["card"])
                if not extra_path.is_file():
                    continue
                args += ["-c:v", "libvpx-vp9", "-i", extra_path.as_posix()]
                layers.append((float(extra.get("delay", 0.0)), len(layers) + 1))
            chain = [f"[0:v]{base_chain}[base]"]
            prev = "[base]"
            for n, (delay, idx) in enumerate(layers):
                out = "[v%d]" % n
                chain.append(
                    f"[{idx}:v]format=rgba,"
                    f"tpad=start_duration={delay:.3f}:start_mode=add:color=black@0[ov{n}]"
                )
                chain.append(f"{prev}[ov{n}]overlay=0:0:eof_action=pass{out}")
                prev = out
            chain[-1] = chain[-1][: -len(prev)] + ",format=yuv420p"
            args += ["-filter_complex", ";".join(chain)]'''
assert old in s; s = s.replace(old, new)

# Kinetic figures on a shot with no card at all
old = '''    else:
        args += ["-vf", video_filter if still is not None else f"{video_filter},format=yuv420p"]

    args += [
        "-an",'''
new = '''    elif shot.get("overlays"):
        base_chain = video_filter if still is not None else f"{video_filter},format=yuv420p"
        chain = [f"[0:v]{base_chain}[base]"]
        prev = "[base]"
        n = 0
        for extra in shot.get("overlays") or []:
            extra_path = Path(extra["card"])
            if not extra_path.is_file():
                continue
            args += ["-c:v", "libvpx-vp9", "-i", extra_path.as_posix()]
            idx = 1 + n
            chain.append(
                f"[{idx}:v]format=rgba,"
                f"tpad=start_duration={float(extra.get('delay', 0.0)):.3f}:start_mode=add:color=black@0[ov{n}]"
            )
            chain.append(f"{prev}[ov{n}]overlay=0:0:eof_action=pass[v{n}]")
            prev = f"[v{n}]"
            n += 1
        if n:
            chain[-1] = chain[-1][: -len(prev)] + ",format=yuv420p"
            args += ["-filter_complex", ";".join(chain)]
        else:
            args += ["-vf", base_chain]
    else:
        args += ["-vf", video_filter if still is not None else f"{video_filter},format=yuv420p"]

    args += [
        "-an",'''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")
print("patched plan/queries/cardplan/compose for anchors + kinetic")

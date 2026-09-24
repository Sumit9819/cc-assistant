"""Wire the keyword layer: marks stripped for the voice, runs rendered at plan
time through the card renderer, stacked as overlays."""
from pathlib import Path

ROOT = Path("D:/faceless-studio")

# ---------------------------------------------------------------- voice.py: strip *marks* too
p = ROOT / "fvs/stages/voice.py"; s = p.read_text(encoding="utf-8")
old = '''def strip_directions(text: str) -> str:
    return re.sub(r"\\s{2,}", " ", _DIRECTION.sub("", text)).strip()'''
new = '''def strip_directions(text: str) -> str:
    """Bracketed directions and asterisk keyword marks both leave the spoken line."""
    from .. import keywords

    return re.sub(r"\\s{2,}", " ", keywords.strip_marks(_DIRECTION.sub("", text))).strip()'''
assert old in s; s = s.replace(old, new)
# the Gemini path receives `spoken` with directions kept; marks must still go
old = '''        audio, spans = synthesize_gemini(spoken, voice=voice, section_ends=section_ends)'''
new = '''        from .. import keywords

        audio, spans = synthesize_gemini([keywords.strip_marks(p) for p in spoken],
                                         voice=voice, section_ends=section_ends)'''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- cardplan.py: keyword template + attach
p = ROOT / "fvs/cardplan.py"; s = p.read_text(encoding="utf-8")
old = '''                    "kinetic": "kinetic_word.html"}'''
new = '''                    "kinetic": "kinetic_word.html", "keyword": "keyword_run.html"}'''
assert old in s; s = s.replace(old, new)
old = '''    elif spec["kind"] == "kinetic":'''
new = '''    elif spec["kind"] == "keyword":
        payload = {"items": spec.get("items", []), "position": spec.get("position", "centre"),
                   "duration": round(duration, 2)}
    elif spec["kind"] == "kinetic":'''
assert old in s; s = s.replace(old, new)
old = '''def apply('''
new = '''def attach_keywords(slug: str, shot: dict[str, Any], items: list[dict[str, Any]]) -> bool:
    """Render one keyword run for a footage shot and stack it as an overlay.

    The run lasts to the end of the shot so the words leave on the cut.
    Items whose word falls in the last 0.6s are dropped: a pop that has no
    time to be read is noise.
    """
    duration = float(shot["duration"])
    kept = [it for it in items if it["at"] <= duration - 0.6]
    if not kept:
        return False
    # Stat and kinetic figures sit low; keywords take the upper seat when a
    # kinetic figure is already on this shot.
    position = "top" if shot.get("overlays") else "centre"
    card_dir = config.project_dir(slug) / "cards"
    card_dir.mkdir(exist_ok=True)
    target = card_dir / f"keywords_s{int(shot['id']):03d}.webm"
    spec = {"kind": "keyword", "items": kept, "position": position}
    if not render_motion(spec, duration, target):
        return False
    shot.setdefault("overlays", []).append({"card": str(target.resolve()), "delay": 0.0})
    shot["keywords"] = [it["text"] for it in kept]
    return True


def apply('''
assert s.count(old) == 1; s = s.replace(old, new, 1)
old = '''        shot.pop("overlays", None)
        shot.pop("card_spec", None)'''
new = '''        shot.pop("overlays", None)
        shot.pop("keywords", None)
        shot.pop("card_spec", None)'''
assert old in s; s = s.replace(old, new)
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- plan.py: keyword runs after kinetic figures
p = ROOT / "fvs/stages/plan.py"; s = p.read_text(encoding="utf-8")
old = '''    # The persistent frame: chapter marker on every footage shot.'''
new = '''    # The keyword layer: marked words and every figure, on footage shots
    # that carry no card, still or scene. One run per shot; items pop on
    # their word and hold to the cut.
    from .. import keywords as keywords_lib

    if script_path.is_file():
        raw_paragraphs = [p for p, _ in parse_script_sections(script_path.read_text(encoding="utf-8"))]
        runs = keywords_lib.plan(slug, shots, data["words"], raw_paragraphs)
        by_id = {int(s["id"]): s for s in shots}
        attached = sum(1 for run in runs if cardplan.attach_keywords(slug, by_id[run["shot_id"]], run["items"]))
        if attached:
            print(f"  keywords on {attached} shot(s): "
                  + ", ".join(" ".join(it["text"] for it in run["items"]) for run in runs[:6])
                  + (" ..." if len(runs) > 6 else ""))

    # The persistent frame: chapter marker on every footage shot.'''
assert s.count(old) == 1; s = s.replace(old, new, 1)
# parse_script_sections is imported locally earlier in run(); make sure the name exists there
assert "from .voice import parse_script_sections" in s
p.write_text(s, encoding="utf-8")
print("keyword layer wired")

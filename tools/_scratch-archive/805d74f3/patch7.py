import re
from pathlib import Path

p = Path("D:/faceless-studio/fvs/stages/assets.py"); s = p.read_text(encoding="utf-8")
# remove the misplaced line wherever it landed
s = re.sub(r"\n\s+chosen \|= rejected\n", "\n", s)
m = re.search(r"(\n(\s+)chosen: set\[str\] = \{.*?\n\2\})", s, re.S)
assert m, "chosen block"
s = s[:m.end()] + f"\n{m.group(2)}chosen |= rejected" + s[m.end():]

old = "def run(slug: str, record: dict[str, Any], only: set[int] | None = None) -> None:"
assert old in s
s = s.replace(old, "def run(slug: str, record: dict[str, Any], only: set[int] | None = None, force: bool = False) -> None:")
old = '''    shots = [s for s in shots if s.get("kind") not in ("still", "scene")]'''
assert old in s
s = s.replace(old, '''    shots = [s for s in shots if s.get("kind") not in ("still", "scene")]
    if only is None and not force:
        # A full run used to re-pick EVERY shot and threw away the clips a
        # human had swapped in. Footage already in place stays unless the
        # caller says --force.
        shots = [s for s in shots if not s.get("asset")]''')
old = '''        if only is None:
            # A full re-run replaces usage rather than stacking onto it.
            ledger.clear_project(conn, slug)'''
assert old in s
s = s.replace(old, '''        if only is None and force:
            # A forced re-run replaces usage rather than stacking onto it.
            ledger.clear_project(conn, slug)''')
p.write_text(s, encoding="utf-8")

c = Path("D:/faceless-studio/fvs/cli.py"); t = c.read_text(encoding="utf-8")
old = '''    return _run_stage(args.slug, "assets", assets.run, only=only)'''
assert old in t
t = t.replace(old, '''    return _run_stage(args.slug, "assets", assets.run, only=only, force=bool(getattr(args, "force", False)))''')
c.write_text(t, encoding="utf-8")
print("assets.py repaired; cli passes force")

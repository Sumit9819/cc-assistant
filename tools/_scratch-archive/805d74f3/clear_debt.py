"""Priority 6 - scaffolding debt.

1. manifest.STAGES: drop the two phantom entries ("script", "graphics")
   that have no implementation and made every finished project read 9/11.
2. plan.py: write shotlist.json alongside cutplan.json. The handoff to
   assets has been a manual `cp` in every chain so far; `fvs run` cannot
   exist while that step needs a human.
3. cli.py: add `fvs run` - walks the stages in order, skips done ones,
   resumes with --from, redoes with --force, and treats notebook as the
   optional accelerator it was designed to be.

DO NOT run while any fvs stage process is in flight: every stage imports
cli.py and manifest.py fresh.
"""

import ast
from pathlib import Path

ROOT = Path("D:/faceless-studio/fvs")


def patch(path: Path, old: str, new: str, label: str) -> None:
    src = path.read_text(encoding="utf-8")
    if new.strip() and new in src:
        print(f"  {path.name:<12} already: {label}")
        return
    assert old in src, f"{path.name}: anchor missing for {label}"
    src = src.replace(old, new, 1)
    ast.parse(src)
    path.write_text(src, encoding="utf-8")
    print(f"  {path.name:<12} patched: {label}")


# --- 1. phantom stages ---------------------------------------------------
patch(
    ROOT / "manifest.py",
    'STAGES: tuple[str, ...] = (\n'
    '    "notebook",\n'
    '    "script",\n'
    '    "voice",\n'
    '    "align",\n'
    '    "plan",\n'
    '    "assets",\n'
    '    "graphics",\n'
    '    "compose",\n',
    'STAGES: tuple[str, ...] = (\n'
    '    "notebook",\n'
    '    "voice",\n'
    '    "align",\n'
    '    "plan",\n'
    '    "assets",\n'
    '    "compose",\n',
    "drop phantom script/graphics stages",
)

# --- 2. plan writes the shotlist too --------------------------------------
patch(
    ROOT / "stages/plan.py",
    '    durations = [s["duration"] for s in shots]\n',
    '    # The shotlist is the plan plus whatever assets attaches later. Writing\n'
    '    # it here removes the manual copy that stood between plan and assets.\n'
    '    (root / "shotlist.json").write_text(out.read_text(encoding="utf-8"), encoding="utf-8")\n'
    '\n'
    '    durations = [s["duration"] for s in shots]\n',
    "plan writes shotlist.json",
)

# --- 3. fvs run ------------------------------------------------------------
RUN_CMD = '''
def cmd_run(args: argparse.Namespace) -> int:
    """Walk every stage in order. Done stages are skipped unless --force."""
    from .stages import (
        align, assets, compose, notebook, originality, package, plan, shorts, voice,
    )

    slug = _slugify(args.slug)
    try:
        manifest.load(slug)
    except FileNotFoundError as exc:
        print(exc)
        return 1

    steps = [
        ("notebook", lambda: _run_stage(slug, "notebook", notebook.run)),
        ("voice", lambda: _run_stage(slug, "voice", voice.run, voice=args.voice, speed=1.0)),
        ("align", lambda: _run_stage(slug, "align", align.run)),
        ("plan", lambda: _run_stage(slug, "plan", plan.run, pace=args.pace)),
        ("assets", lambda: _run_stage(slug, "assets", assets.run)),
        ("compose", lambda: _run_stage(slug, "compose", compose.run)),
        ("shorts", lambda: _run_stage(slug, "shorts", shorts.run, limit=4)),
        ("originality", lambda: _run_stage(slug, "originality", originality.run, strict=False)),
        ("package", lambda: _run_stage(
            slug, "package", package.run, title="", summary="", tags=args.tags,
            synthetic_media=False,
        )),
    ]
    order = [name for name, _ in steps]
    start = order.index(args.from_stage) if args.from_stage in order else 0

    for index, (name, fn) in enumerate(steps):
        if index < start:
            continue
        status = manifest.load(slug)["stages"][name]["status"]
        # --force redoes everything; --from redoes that stage and all after
        # it; otherwise a stage already done is skipped.
        must_run = args.force or bool(args.from_stage)
        if status == manifest.DONE and not must_run:
            print(f"[{name}] done, skipping")
            continue
        rc = fn()
        if rc != 0:
            if name == "notebook":
                # An accelerator, never a dependency: missing auth or a broken
                # endpoint degrades to manual artifacts and the run goes on.
                print("  notebook unavailable - continuing without sourced artifacts")
                continue
            print(f"stopped at {name}")
            return rc
    return 0


'''

cli = ROOT / "cli.py"
src = cli.read_text(encoding="utf-8")
if "def cmd_run(" not in src:
    anchor = "def build_parser() -> argparse.ArgumentParser:"
    assert anchor in src
    src = src.replace(anchor, RUN_CMD.lstrip("\n") + anchor, 1)
    print("  cli.py       patched: cmd_run defined")
else:
    print("  cli.py       already: cmd_run defined")

parser_old = '    p = sub.add_parser("doctor", help="preflight checks and stage timing report")'
parser_new = (
    '    p = sub.add_parser("run", help="run every stage in order; resumable")\n'
    '    p.add_argument("slug")\n'
    '    p.add_argument("--from", dest="from_stage", default="",\n'
    '                   help="restart from this stage (re-runs it and everything after)")\n'
    '    p.add_argument("--force", action="store_true", help="redo stages already done")\n'
    '    p.add_argument("--voice", default=config.DEFAULT_VOICE)\n'
    '    p.add_argument("--pace", default="medium", choices=["fast", "medium", "slow"])\n'
    '    p.add_argument("--tags", default="")\n'
    '    p.set_defaults(func=cmd_run)\n'
    '\n'
    + parser_old
)
if 'sub.add_parser("run"' not in src:
    assert parser_old in src
    src = src.replace(parser_old, parser_new, 1)
    print("  cli.py       patched: run parser")
else:
    print("  cli.py       already: run parser")
ast.parse(src)
cli.write_text(src, encoding="utf-8")

# Prove every module still imports after the STAGES change.
import sys  # noqa: E402
sys.path.insert(0, "D:/faceless-studio")
import importlib  # noqa: E402
for mod in ("fvs.manifest", "fvs.cli", "fvs.stages.plan"):
    importlib.import_module(mod)
from fvs import manifest as m  # noqa: E402
assert "script" not in m.STAGES and "graphics" not in m.STAGES
print(f"  STAGES now {len(m.STAGES)}: {', '.join(m.STAGES)}")
print("  all modules import; syntax verified")

"""`fvs still`: import an image you generated or downloaded as a paragraph's
still, and list which paragraphs carry one.

Stock and Commons cover what has been photographed. A mechanism, a
cross-section, a reconstruction has not been, and that is where a Gemini
image earns its place. Until now that meant hand-editing stillplan.json
and the library index (p27 was never indexed). This makes it one command.
"""

import ast
from pathlib import Path

STUDIO = Path("D:/faceless-studio")

# --- stills.py ---------------------------------------------------------------
p = STUDIO / "fvs/stills.py"
src = p.read_text(encoding="utf-8")
if "import json\n" not in src:
    src = src.replace("import time\n", "import json\nimport time\n", 1) if "import time\n" in src else "import json\n" + src
if "def import_still" not in src:
    src += '''

# --- imported stills --------------------------------------------------------


def import_still(
    slug: str,
    paragraph: int,
    source: Path,
    direction: str = "in",
    why: str = "",
    licence: str = "generated (Gemini)",
) -> dict[str, Any]:
    """Register an image you made or downloaded as a paragraph's still.

    The file is normalised into the library (JPEG, at least 2400px wide so
    the Ken Burns push has room), indexed like a fetched still, and written
    into the project's stillplan.json, which the planner and compositor
    already read. Generated imagery means `fvs package --synthetic`.
    """
    import shutil
    import subprocess

    from . import config

    source = Path(source)
    if not source.is_file():
        raise FileNotFoundError(f"No image at {source}")
    if direction not in ("in", "out"):
        raise ValueError("direction must be 'in' or 'out'")
    root = config.project_dir(slug)
    key = f"imported:{slug}-p{paragraph:02d}"
    name = f"imported-{slug}-p{paragraph:02d}.jpg"
    target = library_dir() / name
    # Upscale only when needed; never throw away pixels that were given.
    vf = "scale='if(lt(iw,2400),2400,iw)':-2:flags=lanczos"
    proc = subprocess.run(
        [shutil.which("ffmpeg"), "-hide_banner", "-loglevel", "error", "-y",
         "-i", source.as_posix(), "-vf", vf, "-q:v", "2", target.as_posix()],
        capture_output=True, text=True, encoding="utf-8", errors="replace",
    )
    if proc.returncode != 0:
        raise RuntimeError(f"ffmpeg failed: {(proc.stderr or '').strip()[:200]}")
    probe = subprocess.run(
        [shutil.which("ffprobe"), "-v", "error", "-select_streams", "v:0",
         "-show_entries", "stream=width,height", "-of", "csv=p=0", target.as_posix()],
        capture_output=True, text=True, encoding="utf-8", errors="replace",
    )
    width, height = (int(v) for v in probe.stdout.strip().split(",")[:2])

    index = load_index()
    record = {
        "source": "imported", "id": f"{slug}-p{paragraph:02d}", "key": key,
        "title": why or source.stem, "licence": licence, "attribution": "",
        "width": width, "height": height, "url": "", "file": name,
        "path": str(target), "query": "", "origin": str(source),
    }
    index["images"][key] = record
    save_index(index)

    plan_path = root / "stillplan.json"
    if plan_path.is_file():
        plan = json.loads(plan_path.read_text(encoding="utf-8"))
    else:
        plan = {"note": "Paragraph -> archival still with Ken Burns.", "paragraphs": {}}
    plan.setdefault("paragraphs", {})[str(paragraph)] = {
        "key": key, "path": str(target), "direction": direction,
        "licence": licence, "attribution": "", "why": why,
    }
    plan_path.write_text(json.dumps(plan, indent=1, ensure_ascii=False), encoding="utf-8")
    return record


def still_coverage(slug: str) -> list[dict[str, Any]]:
    """Every paragraph with its opening words and whether it has a still."""
    from . import config

    root = config.project_dir(slug)
    have: set[int] = set()
    plan_path = root / "stillplan.json"
    if plan_path.is_file():
        have = {int(k) for k in json.loads(plan_path.read_text(encoding="utf-8")).get("paragraphs", {})}
    words_path = root / "words.json"
    if not words_path.is_file():
        return []
    paras: dict[int, list[str]] = {}
    for w in json.loads(words_path.read_text(encoding="utf-8"))["words"]:
        paras.setdefault(int(w["paragraph"]), []).append(str(w.get("text") or w.get("word") or ""))
    return [
        {"paragraph": n, "text": " ".join(paras[n]), "still": n in have}
        for n in sorted(paras)
    ]
'''
ast.parse(src)
p.write_text(src, encoding="utf-8")
print("  stills.py   import_still + still_coverage")

# --- cli.py ------------------------------------------------------------------
p = STUDIO / "fvs/cli.py"
src = p.read_text(encoding="utf-8")
if "def cmd_still" not in src:
    cmd = '''def cmd_still(args: argparse.Namespace) -> int:
    from pathlib import Path as _P

    from . import stills

    slug = _slugify(args.slug)
    if args.image is None:
        rows = stills.still_coverage(slug)
        if not rows:
            print("  no words.json yet - run the align stage first")
            return 1
        for r in rows:
            print(f"  p{r['paragraph']:<3} {'still' if r['still'] else '     '}  {r['text'][:74]}")
        print(f"  {sum(r['still'] for r in rows)}/{len(rows)} paragraphs carry a still")
        return 0
    if args.paragraph is None:
        print("  give the paragraph number before the image path")
        return 1
    try:
        rec = stills.import_still(slug, args.paragraph, _P(args.image), args.direction, args.why, args.licence)
    except Exception as exc:
        print(f"  {type(exc).__name__}: {exc}")
        return 1
    print(f"  p{args.paragraph} -> {rec['file']}  {rec['width']}x{rec['height']}  ({rec['licence']})")
    print("  next: fvs plan, fvs compose. Generated imagery: fvs package --synthetic")
    return 0


def cmd_package('''
    assert src.count("def cmd_package(") == 1, "cmd_package anchor"
    src = src.replace("def cmd_package(", cmd, 1)

    parser = '''    p = sub.add_parser("still", help="list still coverage, or import an image as a paragraph's still")
    p.add_argument("slug")
    p.add_argument("paragraph", type=int, nargs="?", help="paragraph number (from `fvs still <slug>`)")
    p.add_argument("image", nargs="?", help="path to the image you generated or downloaded")
    p.add_argument("--direction", default="in", choices=["in", "out"], help="Ken Burns push")
    p.add_argument("--why", default="", help="what the image shows and why this paragraph")
    p.add_argument("--licence", default="generated (Gemini)")
    p.set_defaults(func=cmd_still)

    p = sub.add_parser("shorts", '''
    assert src.count('    p = sub.add_parser("shorts", ') == 1, "shorts parser anchor"
    src = src.replace('    p = sub.add_parser("shorts", ', parser, 1)
ast.parse(src)
p.write_text(src, encoding="utf-8")
print("  cli.py      fvs still <slug> [paragraph image] [--direction in|out] [--why ...]")

"""Smoke-test one still segment, or measure the finished shotlist.

    python stills_check.py smoke     render one still in isolation; exit 1 on failure
    python stills_check.py measure   shape + still summary of the current shotlist
"""

import json
import statistics
import sys
from pathlib import Path

sys.path.insert(0, "D:/faceless-studio")
from fvs import config  # noqa: E402

SLUG = "roman-concrete"
ROOT = config.project_dir(SLUG)
SP = Path(
    "C:/Users/sumit/AppData/Local/Temp/claude/"
    "c--Users-sumit-Local-Sites-plugintesting-app-public/"
    "805d74f3-c178-47fa-8463-c054b1293383/scratchpad"
)


def smoke() -> int:
    from fvs.stages import compose

    plan = json.loads((ROOT / "stillplan.json").read_text(encoding="utf-8"))["paragraphs"]
    spec = plan["6"]
    shot = {
        "id": 999, "paragraph": 6, "kind": "still", "start": 0.0, "duration": 6.0,
        "still": {"path": spec["path"], "direction": spec["direction"]}, "query": "",
    }
    out = SP / "still_smoke.mp4"
    compose.render_segment(shot, 6.0, out)
    dur = compose._probe_duration(out)
    if not dur or abs(dur - 6.0) > 0.1:
        print(f"  SMOKE FAIL: duration {dur}")
        return 1
    if out.stat().st_size < 100_000:
        print(f"  SMOKE FAIL: {out.stat().st_size} bytes")
        return 1
    print(f"  SMOKE OK: still segment {dur:.2f}s, {out.stat().st_size // 1024} KB, push-{spec['direction']}")
    return 0


def measure() -> int:
    shots = json.loads((ROOT / "shotlist.json").read_text(encoding="utf-8"))["shots"]
    du = [x["duration"] for x in shots]
    stills = [x for x in shots if x.get("kind") == "still"]
    print("=== MEASURE ===")
    print(
        f"  shots {len(shots)} | sd {statistics.pstdev(du):.2f}s | "
        f">6s {sum(1 for d in du if d > 6)} | stills {len(stills)}"
    )
    for x in stills:
        print(f"    still p{x['paragraph']:<3} {x['duration']:.1f}s  push-{x['still']['direction']}  {x['text'][:46]}")
    return 0


if __name__ == "__main__":
    mode = sys.argv[1] if len(sys.argv) > 1 else "measure"
    sys.exit(smoke() if mode == "smoke" else measure())

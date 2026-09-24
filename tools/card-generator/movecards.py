"""Relocate already-live cards to a position with prose on both sides.

    python movecards.py spec-erofirving-10.json --bodies erof-bodies-now.json

A MOVE, not a re-queue: rejecting an applied pending does not revert it (see
memory/feedback_reject_does_not_undo_an_applied_pending.md), so the only safe
way to change live placement is a forward patch. Two patches per card, remove
then insert, in that order, so the insertion anchor is contiguous by the time
its patch runs.

The anchor is computed against a copy with OUR OWN figure already stripped out.
Computing it against the live body was a bug: the card being moved is itself an
image block, so it either blocked its own new position or shifted it.

Where a section has no position with text on both sides, the figure is REMOVED
and reported rather than placed somewhere that stacks. The attachment stays in
the media library, so re-placing it later is one patch. That happens when a
section opens with a decorative image and only a short lead-in line before its
first subheading, which is post 3162's "7 Expert Tips" exactly.
"""
import json
import pathlib
import re
import sys

HERE = pathlib.Path(__file__).parent
sys.path.insert(0, str(HERE))

# Read our own arguments BEFORE importing mkpatch-erof, because its
# module-level parser CONSUMES --bodies out of sys.argv. readcands.py hit this
# exact trap and silently scored the wrong site's bodies for a whole batch.
SPEC = sys.argv[1]
BODIES = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
if not BODIES.is_absolute():
    BODIES = HERE.parent / BODIES

from importlib.machinery import SourceFileLoader  # noqa: E402

mk = SourceFileLoader("mk", str(HERE / "mkpatch-erof.py")).load_module()


def main():
    spec = json.loads((HERE / SPEC).read_text(encoding="utf-8"))
    raw = json.loads(BODIES.read_text(encoding="utf-8"))
    if isinstance(raw, dict):
        raw = raw.get("data", raw)
    bodies = {r["id"]: (r["content"].get("raw") or r["content"]["rendered"])
              for r in raw}

    by_post, dropped, skipped = {}, [], []
    for card in spec["cards"]:
        f, pid = card["file"], card["post_id"]
        html = bodies[pid]

        fig = re.search(r'<figure class="cc-card"><img[^>]*?' + re.escape(f)
                        + r'\.webp[^>]*?></figure>\s*', html)
        if not fig:
            sys.exit(f'{f}: no live figure in post {pid}. Already moved?')
        stripped = html[:fig.start()] + html[fig.end():]
        remove = {"search": fig.group(0), "replace": ""}

        try:
            anchor = mk.insert_anchor(stripped, mk.ANCHORS[f][1])
        except SystemExit as e:
            by_post.setdefault(pid, []).append(remove)
            dropped.append((f, str(e)))
            print(f'  DROP {f}: nowhere clean in that section')
            continue

        # Already sitting exactly where it belongs.
        at = html.index(anchor) if anchor in html else None
        if at is not None and html[at + len(anchor):].lstrip().startswith(
                fig.group(0)[:60]):
            skipped.append(f)
            print(f'  keep {f}: already correctly placed')
            continue

        by_post.setdefault(pid, []).append(remove)
        by_post[pid].append({"search": anchor,
                             "replace": anchor + "\n" + fig.group(0).rstrip()})
        print(f'  MOVE {f}')

    out = []
    for pid, patches in by_post.items():
        moves = sum(1 for p in patches if p["replace"])
        drops = len(patches) - moves * 2
        bits = []
        if moves:
            bits.append(f"move {moves} card(s)")
        if drops:
            bits.append(f"remove {drops} card(s)")
        out.append({
            "post_id": pid,
            "patches": patches,
            "summary": f"Card placement fix on post {pid}: {' and '.join(bits)}",
            "reasoning": (
                "Placement fix. No new images, no copy changes, no attachment "
                "changes. Cards were landing immediately after the h2, which on "
                "these posts is where the existing stock photo sits, so a card "
                "ended up stacked directly on another image with no text between "
                "them. Each card moves to a point with prose above it and prose "
                "or a subheading below it. Where a section has no such point, the "
                "card is removed from the body rather than left stacked; its "
                "attachment stays in the media library."),
        })

    dest = HERE / f"moves-{pathlib.Path(SPEC).stem}.json"
    dest.write_text(json.dumps(out, indent=2) + "\n", encoding="utf-8")
    print()
    print(f'-> {dest.name}: {sum(len(c["patches"]) for c in out)} patches, '
          f'{len(out)} post(s)')
    if skipped:
        print(f'   already correct: {", ".join(skipped)}')
    for f, why in dropped:
        print(f'   DROPPED {f}')
        print(f'     {why[:150]}')


if __name__ == "__main__":
    main()

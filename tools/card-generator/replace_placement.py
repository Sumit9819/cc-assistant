"""Re-place an already-live batch at its correct anchors, in one patch set.

    python replace_placement.py spec-erofirving-10.json uploaded-erof-10.json \
        --bodies erof-live-all.json --skip erof-3858-kidney-stone-home-care

Removals and inserts go into ONE pending per post so the post is never left
half-moved, and the insert anchors are computed against the post-removal body
so a card's own figure cannot block or shift its new position.

Why this exists rather than another pass of movecards.py: one card in batch 10
is not in the body at all any more (pending 1167 removed it because there was
nowhere clean at the time), so the set is a mix of moves and fresh inserts.
Driving both through mkpatch-erof's own insert_anchor keeps one placement
implementation instead of two that can disagree.
"""
import json
import pathlib
import re
import sys

HERE = pathlib.Path(__file__).parent
sys.path.insert(0, str(HERE))

# Our own flags first: importing mkpatch-erof consumes --bodies from sys.argv.
SPEC, UPLOADS = sys.argv[1], sys.argv[2]
BODIES = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
if not BODIES.is_absolute():
    BODIES = HERE.parent / BODIES
SKIP = {sys.argv[i + 1] for i, a in enumerate(sys.argv) if a == "--skip"}

from importlib.machinery import SourceFileLoader  # noqa: E402

mk = SourceFileLoader("mk", str(HERE / "mkpatch-erof.py")).load_module()


def main():
    spec = json.loads((HERE / SPEC).read_text(encoding="utf-8"))
    ups = {u["file"]: u for u in
           json.loads((HERE / UPLOADS).read_text(encoding="utf-8"))}
    raw = json.loads(BODIES.read_text(encoding="utf-8"))
    if isinstance(raw, dict):
        raw = raw.get("data", raw)
    bodies = {r["id"]: (r["content"].get("raw") or r["content"]["rendered"])
              for r in raw}

    by_post = {}
    for card in spec["cards"]:
        f, pid = card["file"], card["post_id"]
        if f in SKIP:
            print(f'  skip {f} (operator decision pending)')
            continue
        html = bodies[pid]
        patches = by_post.setdefault(pid, [])

        # Take the figure out first, if it is currently in the body.
        live = re.search(r'<figure class="cc-card"><img[^>]*?' + re.escape(f)
                         + r'\.webp[^>]*?></figure>\s*', html)
        if live:
            patches.append({"search": live.group(0), "replace": ""})
            html = html[:live.start()] + html[live.end():]
            bodies[pid] = html

        anchor = mk.insert_anchor(html, mk.ANCHORS[f][1])
        w, h = mk.frame(f)
        fig = ('<figure class="cc-card"><img src="%s" alt="%s" width="%d" '
               'height="%d" loading="lazy" decoding="async" /></figure>'
               % (ups[f]["url"], card["alt"], w, h))
        patches.append({"search": anchor, "replace": anchor + "\n" + fig})
        # Keep the working copy in step so the next card in this post sees the
        # body its own anchor will actually be matched against.
        bodies[pid] = html.replace(anchor, anchor + "\n" + fig, 1)
        where = "directly below its heading"
        print(f'  {"move" if live else "re-add"} {f}  -> {where}')

    out = [{
        "post_id": pid,
        "patches": patches,
        "summary": f"Move {sum(1 for p in patches if p['replace'])} card(s) "
                   f"directly below their heading on post {pid}",
        "reasoning": (
            "Placement correction to the operator's stated rule: every card sits "
            "DIRECTLY BELOW the heading of the section it illustrates, not after "
            "that section's paragraph and never above a heading. No new images, "
            "no copy changes, no attachment changes: same files, same alt text, "
            "same URLs. Where a stock photo already sits under the heading the "
            "card goes after it rather than above it, which leaves those two "
            "images together; whether the decorative photo should stay is a "
            "separate editorial decision and is not touched here."),
    } for pid, patches in by_post.items() if patches]

    dest = HERE / f"replace-{pathlib.Path(SPEC).stem}.json"
    dest.write_text(json.dumps(out, indent=2) + "\n", encoding="utf-8")
    print(f'-> {dest.name}: {sum(len(c["patches"]) for c in out)} patches, '
          f'{len(out)} post(s)')


if __name__ == "__main__":
    main()

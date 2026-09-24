"""Swap the 17 live steps/icons cards for their 65%-icon re-renders.

    python mkswap-iconfix.py --bodies erof-fix-fresh.json

Each patch replaces one whole <figure class="cc-card">...</figure> with a fresh
one: new src, new width and height, and the SAME alt text lifted from the live
figure. Replacing the whole figure rather than editing the src in place is what
guarantees the declared dimensions match the file actually being served, which
matters here because one card (3803's swallowed-battery signs) grew from 630 to
732px when its icons got bigger.

The live figure is sliced out of the freshly fetched body and asserted unique,
never retyped: draft_patch_post_content matches byte for byte.
"""
import json
import pathlib
import re
import sys

HERE = pathlib.Path(__file__).parent

FIG = ('<figure class="cc-card"><img src="%s" alt="%s" width="%d" height="%d" '
       'loading="lazy" decoding="async" /></figure>')


def main():
    bp = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
    if not bp.is_absolute():
        bp = HERE.parent / bp
    posts = json.loads(bp.read_text(encoding="utf-8"))
    if isinstance(posts, dict):
        posts = posts.get("data", posts)
    bodies = {r["id"]: (r["content"].get("raw") or r["content"]["rendered"]) for r in posts}

    ups = {u["file"]: u for u in json.loads(
        (HERE / "uploaded-erof-iconfix.json").read_text(encoding="utf-8"))}
    manifest = json.loads((HERE / "iconfix-manifest.json").read_text(encoding="utf-8"))

    by_post, bad = {}, 0
    for m in manifest:
        pid, old, new = m["post_id"], m["old_file"], m["new_file"]
        body = bodies[pid]
        # Re-slice from the FRESH body; the snapshot in the manifest predates
        # the banner removals that have since applied to 3103 and 3116.
        live = re.search(
            r'<figure class="cc-card"><img[^>]*?' + re.escape(old) + r'\.webp[^>]*?></figure>',
            body)
        if not live:
            print(f'  FAIL {pid}: {old} no longer in the body')
            bad += 1
            continue
        fig = live.group(0)
        if body.count(fig) != 1:
            print(f'  FAIL {pid}: figure occurs {body.count(fig)}x for {old}')
            bad += 1
            continue
        alt = re.search(r'alt="([^"]*)"', fig).group(1)
        u = ups[new]
        rep = FIG % (u["url"], alt, u["w"], u["h"])
        by_post.setdefault(pid, []).append({"search": fig, "replace": rep})
        grew = "" if u["h"] == 630 else f'   HEIGHT {u["h"]}px'
        print(f'  ok {pid}  {old}  ->  {u["url"].rsplit("/", 1)[-1]}{grew}')

    if bad:
        raise SystemExit(f"{bad} problem(s), nothing written")

    out = [{
        "post_id": pid,
        "patches": ps,
        "summary": f"Swap {len(ps)} card(s) on post {pid} for the re-render with the icon filling its circle",
        "reasoning": (
            "Image swap only. Same card, same layout, same wording, same alt text "
            "byte for byte, same position in the body: the only difference is that "
            "the icon inside each circular disc is drawn at 65% of the disc "
            "diameter instead of 47%. The old size left 30 to 54px of empty ring "
            "around every glyph, which the operator flagged as the icon not "
            "covering its circle. 65% is the ratio the list layout has always used "
            "(a 30px icon in a 46px circle) across 58 cards, so it is taken from "
            "inside the existing system rather than invented. The replacement "
            "declares the dimensions of the file actually being served, which is "
            "why the whole figure is rewritten rather than just the src. The "
            "previous attachment stays in the media library, so this is reversible "
            "with a forward patch and no file is deleted."),
    } for pid, ps in sorted(by_post.items())]

    dest = HERE / "swap-iconfix.json"
    dest.write_text(json.dumps(out, indent=2) + "\n", encoding="utf-8")
    print()
    print(f'-> {dest.name}: {len(out)} post(s), {sum(len(c["patches"]) for c in out)} swap(s)')


if __name__ == "__main__":
    main()

"""Find every cc-card that has another image stacked directly above it.

    python stackaudit.py --bodies erof-now.json

The operator's placement rule puts each card directly below its section
heading. Where a decorative image ALREADY sat under that heading, the card
lands right after it and the reader sees two images back to back. This reports
every such pair, plus what the older image actually is, so the choice between
removing ours and removing theirs is made on the content and not on a guess.

"Their" image is classified by comparing its alt text against the heading it
sits under:

  banner   the alt repeats the heading, so the image conveys nothing the
           heading does not already say. This is the pattern on the
           dehydration post: a stock photo with the H2 text burned into a
           caption bar.
  photo    the alt says something the heading does not.
  no-alt   nothing to judge it by.
"""
import json
import pathlib
import re
import sys

HERE = pathlib.Path(__file__).parent
CARD = re.compile(r'<figure class="cc-card">.*?</figure>', re.S)
IMG = re.compile(r"<figure\b(?![^>]*cc-card).*?</figure>|<img\b[^>]*/?>", re.S)
HEAD = re.compile(r"<h([1-6])\b[^>]*>(.*?)</h\1>", re.S)

STOP = {"the", "a", "an", "of", "in", "for", "to", "and", "or", "is", "are",
        "your", "you", "it", "on", "what", "when", "how", "why", "do", "does",
        "guide", "complete", "signs", "sign"}


def words(s):
    s = re.sub(r"<[^>]+>", " ", s)
    return {w for w in re.findall(r"[a-z]+", s.lower()) if w not in STOP and len(w) > 2}


def main():
    bp = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
    if not bp.is_absolute():
        bp = HERE.parent / bp
    posts = json.loads(bp.read_text(encoding="utf-8"))
    if isinstance(posts, dict):
        posts = posts.get("data", posts)

    rows = []
    for p in sorted(posts, key=lambda x: x["id"]):
        h = p["content"].get("raw") or p["content"]["rendered"]
        title = re.sub(r"<[^>]+>", "", p["title"]["rendered"])
        for m in CARD.finditer(h):
            before = h[:m.start()]
            # Is the thing immediately before this card an image?
            tail = re.search(r"(?:<figure\b(?![^>]*cc-card)(?:(?!</figure>).)*</figure>"
                             r"|<img\b[^>]*/?>)\s*$", before, re.S)
            if not tail:
                continue
            other = tail.group(0)
            heads = HEAD.findall(before)
            heading = re.sub(r"<[^>]+>", "", heads[-1][1]).strip() if heads else "(none)"
            alt = re.search(r'alt="([^"]*)"', other)
            alt = alt.group(1) if alt else ""
            src = re.search(r'src="([^"]+)"', other)
            src = src.group(1).rsplit("/", 1)[-1] if src else "?"
            ours = re.search(r'src="[^"]*/([^"/]+)"', m.group(0))
            hw = words(heading)
            aw = words(alt)
            if not alt.strip():
                kind = "no-alt"
            elif hw and len(hw & aw) / len(hw) >= 0.6:
                kind = "banner"
            else:
                kind = "photo"
            rows.append({
                "post": p["id"], "title": title, "heading": heading,
                "kind": kind, "their_file": src, "their_alt": alt,
                "our_file": ours.group(1) if ours else "?",
            })

    print(f"{len(rows)} card(s) with an image stacked directly above\n")
    by_kind = {}
    for r in rows:
        by_kind.setdefault(r["kind"], []).append(r)
    for kind in ("banner", "photo", "no-alt"):
        got = by_kind.get(kind, [])
        if not got:
            continue
        print(f"=== {kind}: {len(got)}")
        for r in got:
            print(f'  {r["post"]}  {r["heading"][:52]}')
            print(f'        theirs: {r["their_file"][:64]}')
            print(f'        alt   : {r["their_alt"][:96] or "(empty)"}')
            print(f'        ours  : {r["our_file"][:64]}')
        print()

    dest = HERE / "stackaudit.json"
    dest.write_text(json.dumps(rows, indent=2), encoding="utf-8")
    print(f"-> {dest.name}")
    print(f'posts affected: {len({r["post"] for r in rows})}')


if __name__ == "__main__":
    main()

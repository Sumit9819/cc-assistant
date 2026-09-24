"""Move every misplaced live card to directly below its section heading.

    python migrate_placement.py --bodies erof-live2.json
    python migrate_placement.py --bodies erof-live2.json --limit 20

The rule, as the operator stated it: a card sits DIRECTLY BELOW the heading of
its section, never after the section's paragraph and never above a heading.
Cards placed before 2026-09-08 all sit at the END of their section, which puts
every one of them immediately above the NEXT heading.

This is driven entirely by the LIVE BODY and needs no per-card configuration.
Under the old convention each card was inserted immediately before the next h2,
so the h2 that encloses a card IS the section it belongs to. Deliberately the
enclosing H2 and not the nearest heading: a card at the end of a section with
subheadings would otherwise attach to the last h3, when what it summarises is
the whole h2 section.

Where an image already sits directly under that h2, the card goes after it,
matching the placement rule rather than putting our card above someone else's.

One pending per post, removal and insert together, so a post is never left
half-moved. `--limit` caps the number of posts per run because pendings are
reviewed in batches of about twenty.
"""
import json
import pathlib
import re
import sys

HERE = pathlib.Path(__file__).parent
FIG = re.compile(r'<figure class="cc-card">.*?</figure>', re.S)
H2 = re.compile(r"<h2\b[^>]*>.*?</h2>", re.S)
LEADING_IMG = re.compile(r"\s*(?:<figure\b.*?</figure>|<img\b[^>]*/?>)", re.S)


def visible(html):
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html)).strip()


def already_ok(html, card_start):
    """Is this card already directly below a heading?"""
    head = html[:card_start]
    cut = max(head.rfind("</h2>"), head.rfind("</h3>"))
    if cut == -1:
        return False
    between = re.sub(r"<figure\b.*?</figure>|<img\b[^>]*/?>", " ",
                     head[cut + 5:], flags=re.S)
    return len(visible(between)) < 40


def anchor_for(html, card_start):
    """The enclosing h2, plus any image already directly beneath it."""
    owners = [m for m in H2.finditer(html) if m.start() < card_start]
    if not owners:
        return None
    tag = owners[-1]
    anchor = tag.group(0)
    pos = tag.end()
    while True:
        m = LEADING_IMG.match(html[pos:])
        if not m:
            break
        anchor += m.group(0)
        pos += m.end()
    return anchor


def main():
    bp = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
    if not bp.is_absolute():
        bp = HERE.parent / bp
    limit = int(sys.argv[sys.argv.index("--limit") + 1]) if "--limit" in sys.argv else 0
    skip = int(sys.argv[sys.argv.index("--skip") + 1]) if "--skip" in sys.argv else 0

    posts = json.loads(bp.read_text(encoding="utf-8"))
    if isinstance(posts, dict):
        posts = posts.get("data", posts)

    out, ok, moved = [], 0, 0
    for p in sorted(posts, key=lambda x: x["id"]):
        html = p["content"].get("raw") or p["content"]["rendered"]
        patches = []
        # Right to left: moving a later card cannot disturb an earlier one's
        # offsets, and each card is looked up in the body as it then stands.
        for m in reversed(list(FIG.finditer(html))):
            if already_ok(html, m.start()):
                ok += 1
                continue
            fig = m.group(0)
            trail = re.match(r"\s*", html[m.end():]).group(0)
            stripped = html[:m.start()] + html[m.end() + len(trail):]
            anchor = anchor_for(stripped, m.start())
            if anchor is None:
                print(f'  post {p["id"]}: card has no h2 above it, skipped')
                continue
            if stripped.count(anchor) != 1:
                print(f'  post {p["id"]}: anchor not unique, skipped')
                continue
            if LEADING_IMG.match(stripped[stripped.index(anchor) + len(anchor):]) \
                    and "cc-card" in stripped[stripped.index(anchor) + len(anchor):][:200]:
                print(f'  post {p["id"]}: a card already sits there, skipped')
                continue
            patches.append({"search": fig + trail, "replace": ""})
            patches.append({"search": anchor, "replace": anchor + "\n" + fig})
            html = stripped.replace(anchor, anchor + "\n" + fig, 1)
            moved += 1
        if patches:
            out.append({
                "post_id": p["id"],
                "patches": patches,
                "summary": f"Move {len(patches) // 2} card(s) directly below "
                           f"their heading on post {p['id']}",
                "reasoning": (
                    "Placement only. No new images, no copy changes, no attachment "
                    "changes: same files, same alt text, same URLs. Each card was "
                    "sitting at the end of its section, which put it immediately "
                    "above the NEXT heading and made it read as that heading's "
                    "illustration. Each now sits directly below the heading of its "
                    "own section, ahead of that section's prose, per the operator's "
                    "placement rule. Where a photo already sits under the heading "
                    "the card goes after it rather than above it."),
            })

    if skip:
        out = out[skip:]
    if limit:
        out = out[:limit]
    dest = HERE / "migrate-placement.json"
    dest.write_text(json.dumps(out, indent=2) + "\n", encoding="utf-8")
    print(f'{ok} card(s) already correct, {moved} to move')
    print(f'-> {dest.name}: {len(out)} post(s), '
          f'{sum(len(c["patches"]) for c in out)} patches'
          + (f' (skipped {skip}, capped at {limit})' if skip or limit else ''))


if __name__ == "__main__":
    main()

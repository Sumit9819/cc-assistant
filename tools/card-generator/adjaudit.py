"""Every place two images sit together with no text between them.

    python adjaudit.py --bodies erof-live-all.json

The check that did not exist. Batch 10 put four cards directly on top of stock
photos and passed three separate guards, because every one of them tested for
`<figure` and the stock photos in these posts are bare `<img>` tags. A guard
that only recognises the markup this pipeline generates cannot see a collision
with markup someone else generated.

So this looks at ALL images: our `<figure class="cc-card">`, plain `<img>` the
editor placed, and anything else. A pair is reported when fewer than MIN_PROSE
characters of visible text separate two of them, because that is what reads as
one stacked block to a reader rather than two illustrations.

Reported per pair: the post, the section it happens in, how much text is
between them, and which two files. Whether to fix each one is an editorial
call: sometimes the right answer is moving our card, and sometimes it is that
the post carries a decorative image that duplicates its own heading.
"""
import json
import pathlib
import re
import sys

HERE = pathlib.Path(__file__).parent
IMG = re.compile(r'<figure\b.*?</figure>|<img\b[^>]*/?>', re.S)
MIN_PROSE = 80


def visible(html):
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html)).strip()


def name(block):
    m = re.search(r'src="([^"]+)"', block)
    return m.group(1).rsplit("/", 1)[-1] if m else "(no src)"


def ours(block):
    return "cc-card" in block


def main():
    bp = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
    if not bp.is_absolute():
        bp = HERE.parent / bp
    posts = json.loads(bp.read_text(encoding="utf-8"))

    pairs = []
    cards = imgs = 0
    for p in posts:
        html = p["content"].get("raw") or p["content"]["rendered"]
        heads = [(m.start(), visible(m.group(1)))
                 for m in re.finditer(r"<h[23]\b[^>]*>(.*?)</h[23]>", html, re.S)]
        blocks = list(IMG.finditer(html))
        cards += sum(1 for b in blocks if ours(b.group(0)))
        imgs += len(blocks)
        for a, b in zip(blocks, blocks[1:]):
            between = visible(html[a.end():b.start()])
            if len(between) >= MIN_PROSE:
                continue
            sec = [h for s, h in heads if s < a.start()]
            pairs.append({
                "post_id": p["id"],
                "section": sec[-1] if sec else "(intro)",
                "gap": len(between),
                "first": name(a.group(0)), "first_ours": ours(a.group(0)),
                "second": name(b.group(0)), "second_ours": ours(b.group(0)),
            })

    pairs.sort(key=lambda r: (r["gap"], r["post_id"]))
    involves_us = [r for r in pairs if r["first_ours"] or r["second_ours"]]
    print(f'{len(posts)} posts, {imgs} images, {cards} of them our cards')
    print(f'{len(pairs)} adjacent pair(s) with under {MIN_PROSE} chars between them')
    print(f'{len(involves_us)} of those involve one of our cards')
    print()
    for r in pairs:
        tag = ("card+photo" if r["first_ours"] != r["second_ours"]
               else "card+card" if r["first_ours"] else "photo+photo")
        print(f'  post {r["post_id"]}  gap {r["gap"]:>3} chars  [{tag}]  '
              f'{r["section"][:44]}')
        print(f'      {r["first"][:58]}')
        print(f'      {r["second"][:58]}')
    (HERE / "adjaudit.json").write_text(json.dumps(pairs, indent=1), encoding="utf-8")
    print()
    print("-> adjaudit.json")


if __name__ == "__main__":
    main()

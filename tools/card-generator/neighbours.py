"""What sits immediately before and after every live card?

    python neighbours.py --bodies erof-live-all.json

Written because I inferred the placement rule twice and was corrected twice.
The operator approved batches 1 to 9 and objected to batch 10, so the
difference between those two populations IS the rule. Measure it rather than
reason about it.

The first version of this was itself wrong: it searched the following text for
each node type in a priority order and returned the first type that matched
ANYWHERE, so every card reported "heading" because a heading always appears
somewhere further down. It therefore showed the approved and objected
populations as identical, which is the one thing they are not.

So: tokenize each body ONCE into positioned nodes, then look up each card's
true previous and next node by position. No search, no priority, no direction
reversal.
"""
import json
import pathlib
import re
import sys
from collections import Counter

HERE = pathlib.Path(__file__).parent

NODE = re.compile(
    r'(?P<heading><h(?P<lvl>[1-6])\b[^>]*>.*?</h(?P=lvl)>)'
    r'|(?P<card><figure class="cc-card">.*?</figure>)'
    r'|(?P<figure><figure\b(?!\s+class="cc-card").*?</figure>)'
    r'|(?P<img><img\b[^>]*/?>)'
    r'|(?P<list><(?:ul|ol)\b.*?</(?:ul|ol)>)'
    r'|(?P<table><table\b.*?</table>)',
    re.S)


def visible(html):
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html)).strip()


def nodes(html):
    out = []
    for m in NODE.finditer(html):
        k = m.lastgroup if m.lastgroup != "lvl" else "heading"
        for name in ("heading", "card", "figure", "img", "list", "table"):
            if m.group(name):
                k = name
                break
        label = f'h{m.group("lvl")}' if k == "heading" else k
        out.append((m.start(), m.end(), label))
    return out


def main():
    bp = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
    if not bp.is_absolute():
        bp = HERE.parent / bp
    posts = json.loads(bp.read_text(encoding="utf-8"))

    rows = []
    for p in posts:
        html = p["content"].get("raw") or p["content"]["rendered"]
        ns = nodes(html)
        for i, (s, e, k) in enumerate(ns):
            if k != "card":
                continue
            prev = ns[i - 1] if i else None
            nxt = ns[i + 1] if i + 1 < len(ns) else None
            src = re.search(r'src="([^"]+)"', html[s:e])
            rows.append({
                "post_id": p["id"],
                "file": (src.group(1).rsplit("/", 1)[-1] if src else "?"),
                "before": prev[2] if prev else "start",
                "before_text": len(visible(html[prev[1]:s])) if prev else len(visible(html[:s])),
                "after": nxt[2] if nxt else "end",
                "after_text": len(visible(html[e:nxt[0]])) if nxt else len(visible(html[e:])),
            })

    b10 = {"erof-3365-stress-fracture-sites", "erof-3365-stress-fracture-signs",
           "erof-3365-stress-fracture-healing", "erof-3365-stress-fracture-er",
           "erof-3162-muscle-strain-grades", "erof-3162-muscle-strain-recovery",
           "erof-3858-kidney-stone-home-care"}

    for label, group in (
            ("APPROVED (batches 1-9)",
             [r for r in rows if r["file"].rsplit(".", 1)[0] not in b10]),
            ("OBJECTED TO (batch 10)",
             [r for r in rows if r["file"].rsplit(".", 1)[0] in b10])):
        print(f'=== {label}: {len(group)} cards')
        c = Counter((f'{r["before"]}+{r["before_text"]}chars' if r["before_text"] < 40
                     else f'{r["before"]}+text',
                     f'{r["after"]}' if r["after_text"] == 0
                     else f'text+{r["after"]}') for r in group)
        for (b, a), n in c.most_common():
            print(f'    {n:>3}  [ {b:<16} ] CARD [ {a} ]')
        print()

    (HERE / "neighbours.json").write_text(json.dumps(rows, indent=1),
                                          encoding="utf-8")
    print("-> neighbours.json")


if __name__ == "__main__":
    main()

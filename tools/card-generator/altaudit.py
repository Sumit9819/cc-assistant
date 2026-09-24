"""Does the alt text on the live cards carry the queries their posts rank for?

    python altaudit.py --bodies erof-all-bodies.json --gsc ../_gq

Written BEFORE proposing an alt-text rewrite, not after. The claim being tested
is my own: that the alt text is "descriptive prose rather than query-shaped" and
so limits image ranking. That was an impression formed by reading a few strings,
and the honest way to settle it is to check every live card against the queries
its own post actually earns.

For each `<figure class="cc-card">` this finds the card's section (the card is
inserted immediately BEFORE its anchor heading, so it closes the section above
it), then asks how much of that section's query vocabulary the alt text already
contains. A term is COVERED when the alt text contains it.

Reported per card:
  cover   share of the section's query content-words present in the alt text
  miss    the query words the alt text does not contain, biggest impressions first

A high cover rate means the alt text is already doing its job and a rewrite
would be churn on live content for no gain. A low one means the rewrite is real
work worth queueing.
"""
import json
import pathlib
import re
import sys
import importlib.util

HERE = pathlib.Path(__file__).parent


def load_cardneed():
    spec = importlib.util.spec_from_file_location("cn", HERE / "cardneed.py")
    cn = importlib.util.module_from_spec(spec)
    argv, sys.argv = sys.argv, [sys.argv[0]]
    spec.loader.exec_module(cn)
    sys.argv = argv
    return cn


cn = load_cardneed()


def arg(flag, default, base):
    """Resolve a path flag against the same base cardneed.py uses for it.

    The two differ and it matters: --bodies is relative to tools/ and --gsc to
    the card-generator folder. Resolving both against one base silently pointed
    --gsc at a directory that does not exist, and the audit reported zero cards
    rather than failing.
    """
    if flag not in sys.argv:
        return default
    p = pathlib.Path(sys.argv[sys.argv.index(flag) + 1])
    p = p if p.is_absolute() else base / p
    if not p.exists():
        sys.exit(f"{flag} {p} does not exist")
    return p


DIGIT = {"one": "1", "two": "2", "three": "3", "four": "4", "five": "5",
         "six": "6", "seven": "7", "eight": "8", "nine": "9", "ten": "10",
         "eleven": "11", "twelve": "12"}
# Words the alt text says a different but equivalent way. Kept tiny and only
# for pairs seen in this data: a big synonym table would let the audit claim
# coverage the alt text does not really have.
SAME = {"symptom": "sign", "warning": "sign", "hour": "time", "minute": "time"}


def norm(words):
    """Fold the differences that are not real coverage gaps.

    Measured without this, the alt text on post 3930 scored ZERO coverage of
    "how long can you wait to get stitches" while literally reading "the window
    for getting a deep cut stitched", because stitched != stitches to an exact
    match. Post 3201 lost a point for writing "Ten" where the query says "10".
    Those are artefacts of the matcher and counting them would have argued for
    rewriting live cards that are already correct.

    Deliberately crude and deliberately small: strip one plural or tense
    ending, map number words to digits, fold a handful of observed synonym
    pairs. Anything cleverer starts inventing coverage.
    """
    out = set()
    for w in words:
        w = DIGIT.get(w, w)
        for suf in ("ing", "ed", "es", "s"):
            # Leave at least four characters behind, or the ending eats the
            # word: "irving" became "irv" and showed up as a missing term.
            if w.endswith(suf) and len(w) - len(suf) >= 4:
                w = w[: -len(suf)]
                break
        out.add(SAME.get(w, w))
    return out


def cards_by_section(html):
    """(section_heading, alt_text) for every live cc-card in a post.

    The card sits immediately before the h2 that follows the section it
    illustrates, so the owning section is the h2 BEFORE the figure. A card
    above the first h2 belongs to the intro, reported as "(intro)".
    """
    out = []
    heads = [(m.start(), cn.strip(m.group(1)))
             for m in re.finditer(r"<h2\b[^>]*>(.*?)</h2>", html, re.S)]
    for m in re.finditer(r'<figure class="cc-card">.*?</figure>', html, re.S):
        alt = re.search(r'alt="(.*?)"', m.group(0), re.S)
        if not alt:
            out.append(("(no alt)", ""))
            continue
        prior = [h for pos, h in heads if pos < m.start()]
        out.append((prior[-1] if prior else "(intro)", alt.group(1)))
    return out


def main():
    bodies = arg("--bodies", HERE.parent / "erof-all-bodies.json", HERE.parent)
    gsc = arg("--gsc", HERE.parent / "_gq", HERE)
    posts = json.loads(bodies.read_text(encoding="utf-8"))

    rows = []
    for p in posts:
        html = p["content"].get("raw") or p["content"]["rendered"]
        cards = cards_by_section(html)
        if not cards:
            continue
        f = gsc / f'{p["id"]}.json'
        qs = []
        if f.exists():
            d = json.loads(f.read_text(encoding="utf-8"))
            d = d.get("data", d)
            qs = d.get("queries") or d.get("rows") or []
        heads = [h for h, _ in cn.sections(html) if h]
        rank = cn.rank_axis(qs, heads) if qs else {}

        for sec, alt in cards:
            got = rank.get(sec, {"impr": 0, "top": []})
            # Section-level queries when the section has them, else the post's
            # own top queries: a card still has to be findable by the terms the
            # PAGE earns, even when no query pinned itself to that heading.
            src = got["top"] or [[r.get("impressions", 0), r.get("query", ""),
                                  round(r.get("position", 0) or 0, 1)]
                                 for r in sorted(qs, key=lambda r: -r.get("impressions", 0))[:3]]
            words, seen = [], set()
            for impr, q, _pos in src:
                qt = cn.qtoks(q)
                # Same other-market filter cardneed.py applies on Axis B. The
                # fallback path skipped it and reported post 3813's card as
                # missing "nhs", which no alt text on a Texas ER should carry.
                if any(w in cn.OFF_MARKET for w in qt):
                    continue
                for w in norm(qt):
                    if w not in seen:
                        seen.add(w)
                        words.append((impr, w))
            if not words:
                continue
            alt_words = norm(cn.qtoks(alt))
            miss = [w for _i, w in words if w not in alt_words]
            cover = 1 - len(miss) / len(words)
            rows.append({"id": p["id"], "section": sec, "alt": alt,
                         "impr": got["impr"], "cover": cover, "miss": miss,
                         "scoped": bool(got["top"])})

    # The two populations are NOT comparable and must never be summed.
    #
    # A "scoped" card has queries that pinned themselves to its own section, so
    # its alt text can fairly be asked to carry them. An "unscoped" card only
    # has the POST's top queries to be measured against, and those belong to
    # other sections: post 3886's F.A.S.T. card was marked 25% covered for
    # missing "feel, like, woman", from "what does a stroke feel like in a
    # woman". Writing that into a card of the four stroke warning signs would
    # be keyword stuffing and would make the alt text describe the wrong image.
    # Reporting one blended number turned that artefact into a 32% mean and
    # would have justified rewriting 58 live cards on a measurement error.
    def report(group, label, explain):
        if not group:
            print(f'{label}: none')
            return
        group.sort(key=lambda r: (r["cover"], -r["impr"]))
        n = len(group)
        full = sum(1 for r in group if r["cover"] >= 0.999)
        good = sum(1 for r in group if r["cover"] >= 0.75)
        weak = sum(1 for r in group if r["cover"] < 0.5)
        print()
        print(f'=== {label} ({n} cards)')
        print(f'    {explain}')
        print(f'    {full} carry every query word ({full / n:.0%}), '
              f'{good} carry >=75% ({good / n:.0%}), '
              f'{weak} carry <50% ({weak / n:.0%}), mean {sum(r["cover"] for r in group) / n:.0%}')
        for r in group[:12]:
            print(f'    {r["cover"]:>5.0%} {r["impr"]:>6} impr  {r["id"]}  {r["section"][:52]}')
            print(f'{"":>18}  alt:  {r["alt"][:92]}')
            if r["miss"]:
                print(f'{"":>18}  miss: {", ".join(r["miss"][:8])}')

    report([r for r in rows if r["scoped"]],
           "SCOPED - the fair test",
           "queries that pinned to this card's own section")
    report([r for r in rows if not r["scoped"]],
           "UNSCOPED - not a fair test, shown for completeness only",
           "no query pinned to this section, so measured against the post's "
           "top queries, which describe OTHER sections")
    (HERE / "altaudit.json").write_text(json.dumps(rows, indent=2), encoding="utf-8")
    print("-> altaudit.json")


if __name__ == "__main__":
    main()

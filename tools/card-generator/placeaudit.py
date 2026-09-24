"""Which live cards sit against an h2 they do not belong to?

    python placeaudit.py --bodies erof-all-bodies.json

Every card placed before 2026-09-08 went immediately above the NEXT h2, so a
count of boundary placements is just a count of cards. The useful question is
how badly each one MIS-reads, and that depends on the heading underneath it: a
symptoms card above "Frequently Asked Questions" is harmless, while a card of
home remedies above "Safe Over-the-Counter Pain Relief" actively captions the
wrong section.

So each boundary card is scored by comparing its alt text against the heading
it sits under versus the heading of the section it actually illustrates. When
the heading below matches as well as or better than its own, the card reads as
belonging to the wrong section and is worth moving.

  steal   the card's alt matches the heading BELOW at least as well as its own
  safe    its own section is the clearly better match

This is a triage list for an operator decision, not a queue. Moving a card is a
live content change on a page that is already ranking.
"""
import json
import pathlib
import re
import sys
import importlib.util

HERE = pathlib.Path(__file__).parent


def load(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    m = importlib.util.module_from_spec(spec)
    argv, sys.argv = sys.argv, [sys.argv[0]]
    spec.loader.exec_module(m)
    sys.argv = argv
    return m


cn = load("cn", HERE / "cardneed.py")


def sim(a, b):
    """Share of the heading's content words that the alt text carries."""
    ha, hb = set(cn.qtoks(a)), set(cn.qtoks(b))
    if not hb:
        return 0.0
    return len(ha & hb) / len(hb)


def main():
    bp = pathlib.Path(sys.argv[sys.argv.index("--bodies") + 1])
    if not bp.is_absolute():
        bp = HERE.parent / bp
    posts = json.loads(bp.read_text(encoding="utf-8"))

    steal, safe = [], 0
    total = 0
    for p in posts:
        html = p["content"].get("raw") or p["content"]["rendered"]
        heads = [(m.start(), m.end(), cn.strip(m.group(1)))
                 for m in re.finditer(r"<h2\b[^>]*>(.*?)</h2>", html, re.S)]
        for m in re.finditer(r'<figure class="cc-card">.*?</figure>(\s*)<h2\b[^>]*>(.*?)</h2>',
                             html, re.S):
            total += 1
            alt = re.search(r'alt="(.*?)"', m.group(0), re.S)
            if not alt:
                continue
            alt = alt.group(1)
            below = cn.strip(m.group(2))
            own = [h for s, e, h in heads if s < m.start()]
            own = own[-1] if own else "(intro)"
            a, b = sim(alt, own), sim(alt, below)
            if b >= a:
                steal.append((b - a, p["id"], own, below, alt))
            else:
                safe += 1

    steal.sort(reverse=True, key=lambda t: t[0])
    print(f'{total} live cards sit directly above an h2')
    print(f'  {safe} read correctly: their own section is the better match')
    print(f'  {len(steal)} read as belonging to the section BELOW them')
    print()
    for d, pid, own, below, alt in steal[:14]:
        print(f'  post {pid}   (below matches +{d:.2f})')
        print(f'    illustrates: {own[:64]}')
        print(f'    sits under:  {below[:64]}')
        print(f'    alt:         {alt[:72]}')
    (HERE / "placeaudit.json").write_text(
        json.dumps([{"post_id": p, "own": o, "below": b, "alt": a}
                    for _d, p, o, b, a in steal], indent=1), encoding="utf-8")
    print()
    print("-> placeaudit.json")


if __name__ == "__main__":
    main()

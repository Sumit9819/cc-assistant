"""
Print the candidate sections cardneed.py shortlisted, with their actual content,
so each one can be READ before a card is written for it.

    python readcands.py 3886 3921

The shortlist is structural. This is the step that decides whether a candidate is
really a decision, and supplies the values, which must be lifted from the post's
own body rather than invented.
"""
import json
import pathlib
import re
import sys
import importlib.util

HERE = pathlib.Path(__file__).parent
# Parse --bodies BEFORE importing cardneed. cardneed strips the flag from
# sys.argv at import time, so a parser placed after the import never sees it
# and silently falls back to the Irving default.
BODIES_ARG = HERE.parent / "erof-all-bodies.json"
if "--bodies" in sys.argv:
    _i = sys.argv.index("--bodies")
    BODIES_ARG = pathlib.Path(sys.argv[_i + 1])
    if not BODIES_ARG.is_absolute():
        BODIES_ARG = HERE.parent / BODIES_ARG

spec = importlib.util.spec_from_file_location("cn", HERE / "cardneed.py")
cn = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cn)


def items(body):
    out = []
    for li in re.findall(r"<li\b[^>]*>(.*?)</li>", body, re.S | re.I):
        t = cn.strip(li)
        if t:
            out.append(t)
    return out


def table(body):
    rows = []
    for tr in re.findall(r"<tr\b[^>]*>(.*?)</tr>", body, re.S | re.I):
        cells = [cn.strip(c) for c in re.findall(r"<t[dh]\b[^>]*>(.*?)</t[dh]>", tr, re.S | re.I)]
        if any(cells):
            rows.append(cells)
    return rows


def subheads(body):
    """The h3 headings in a section, which are now an enumerable shape."""
    return [cn.strip(m) for m in
            re.findall(r"<h3\b[^>]*>(.*?)</h3>", body, re.S | re.I)]


def main():
    # Post bodies carry typographic bullets and dashes that the Windows cp1252
    # console cannot encode, which killed the run mid-post. Replace rather than
    # raise: the point is to read the content, not to render it perfectly.
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    except Exception:
        pass
    posts = json.loads(BODIES_ARG.read_text(encoding="utf-8"))
    want = [int(a) for a in sys.argv[1:]]
    for pid in want:
        p = next(x for x in posts if x["id"] == pid)
        html = p["content"].get("raw") or p["content"]["rendered"]
        title = cn.strip(p["title"].get("raw") or p["title"]["rendered"])
        print("=" * 78)
        print(f'POST {pid}  {title}')
        print(f'  live cards: {html.count(chr(34) + "cc-card" + chr(34))}')
        for s in cn.score(html, pid, title):
            if not s["candidate"]:
                continue
            head, body = next((h, b) for h, b in cn.sections(html) if h == s["heading"])
            why = s["why"] or "decision"
            print()
            print(f'  --- CANDIDATE [{why}] ({s["enum"]}) {head}')
            # Axis B qualified this heading, so the queries behind it are part
            # of what has to be read: they say what the card should ANSWER, and
            # whether the intent is even this market's.
            if s["impr"]:
                print(f'      ranks: {s["impr"]} impr')
                for i, q, pos in s["queries"]:
                    print(f'        {i:>6} impr  pos {pos:>4.1f}  {q}')
            for h3 in subheads(body):
                print(f'      # {h3[:118]}')
            for it in items(body)[:9]:
                print(f'      * {it[:118]}')
            for row in table(body)[:7]:
                print(f'      | {" | ".join(c[:34] for c in row)}')
            if not items(body) and not table(body):
                print(f'      prose: {cn.strip(body)[:400]}')


if __name__ == "__main__":
    main()

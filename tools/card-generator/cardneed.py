"""
How many in-body cards does each post actually need?

    python cardneed.py                          # all posts, both axes
    python cardneed.py 3803 3808                # just these
    python cardneed.py --bodies luf-all-bodies.json --gsc _gq

Reads the cached post bodies and scores every h2 section on TWO axes. A heading
earns a card if it passes EITHER one, plus the two gates that apply to both.

  AXIS A - READER DECISION. The heading asks what do I do / how bad is it /
      which option / how long do I have. This was the original rule from
      memory/feedback_card_count_follows_the_post.md.

  AXIS B - RANKING EVIDENCE. The heading already earns impressions in Search.
      Axis A alone threw away every definitional and causal heading, and those
      turned out to be the ones carrying the volume: post 3977 lost "What is a
      Hairline Fracture?" as background while that query sits at 4,300
      impressions in position 4.9, and post 3116 was given zero cards while
      "Skeletal Traction vs Skin Traction" ranks 3.4 on a query that literally
      says "vs". Impressions are measured, not a guess about what looks
      rankable, so this axis needs a --gsc directory of page-query pulls
      (<post_id>.json each) or it simply does not fire.

  GATE 1 - NO EXISTING ANSWER. The section carries no table and no cc-card
      already. A decorative stock photo is not an answer.

  GATE 2 - ENUMERABLE. The content is a threshold, a sequence, a comparison or
      a set of signs. This gate applies to Axis B as well, and deliberately so:
      a card must restate what the section says. A high-impression heading over
      three sentences of narrative prose has nothing to draw a card FROM, and
      building one would mean inventing content.

The count is the OUTPUT. There is no quota: a single-decision post is finished
at one card and a multi-decision triage post can justify four.

This is a SHORTLIST, not a verdict. It reads headings, markup and query strings,
so it cannot tell prevention background from an actionable decision when the
heading is ambiguous, and its query matching is blunt. Every candidate still
gets read before anything is rendered.
"""
import json
import pathlib
import re
import sys

HERE = pathlib.Path(__file__).parent
# Which site to score. Default stays Irving so existing invocations are
# unchanged; pass --bodies <file> for another site.
BODIES = HERE.parent / "erof-all-bodies.json"
if "--bodies" in sys.argv:
    _i = sys.argv.index("--bodies")
    BODIES = pathlib.Path(sys.argv[_i + 1])
    if not BODIES.is_absolute():
        BODIES = HERE.parent / BODIES
    del sys.argv[_i:_i + 2]

# Axis B needs a directory of GSC page-query pulls, one <post_id>.json per post
# (whatever /gsc/page-queries returned). Absent, only Axis A runs and the tool
# says so rather than silently scoring on one axis.
GSC = HERE.parent / "_gq"
if "--gsc" in sys.argv:
    _i = sys.argv.index("--gsc")
    GSC = pathlib.Path(sys.argv[_i + 1])
    if not GSC.is_absolute():
        GSC = HERE / GSC
    del sys.argv[_i:_i + 2]

# Impressions a single heading must carry before Axis B claims it is worth a
# graphic. Set from the measured distribution on erofirving rather than picked:
# below this the matches are single stray queries that one card would not move.
RANK_FLOOR = 150

# Queries from another health market. A Texas freestanding ER gains nothing from
# UK NHS intent, so those impressions must not justify a card here. Post 3813's
# apparent rank gap was almost entirely "nhs appendicitis" traffic.
OFF_MARKET = {"nhs", "uk", "gov", "australia", "canada", "india", "ireland"}

# Words too common to carry meaning when matching a query to a heading.
STOP = {"a", "an", "the", "is", "it", "of", "for", "to", "in", "on", "and", "or",
        "you", "your", "my", "i", "do", "does", "can", "what", "when", "how",
        "why", "are", "be", "with", "at", "from", "s", "vs", "if", "should"}


# Test 1. A decision the reader makes, not a topic the article covers.
DECISION = re.compile(
    r"^\s*(what|when|how|which|why|do|does|did|is|are|should|can|will)\b"
    r"|\bvs\.?\b|\bor\b.*\?"
    r"|\b(red flags?|warning signs?|symptoms?|signs?|steps?|thresholds?|"
    r"first aid|what to do|do not|don't|when to|how long|how quickly|"
    r"difference|compare|comparison|checklist|emergency)\b",
    re.I,
)

# Sections that are about the business or the article, never a card.
NEVER = re.compile(
    r"\b(about (us|er of)|why choose|our (team|facility|providers?)|"
    r"contact|insurance|location|hours|conclusion|final thoughts|"
    r"takeaway|references|sources|disclaimer|faq)\b",
    re.I,
)


# Test 1, negative half. A question mark does not make a decision. Definition,
# cause and prevention sections are background: the reader is not choosing
# anything, so a graphic adds weight without adding an answer. This is the
# heuristic form of the burn-hazards mistake, where a household-hazards card
# was built for a prevention section.
BACKGROUND = (
    "what is ", "what are the causes", "what causes", "causes of",
    "how to prevent", "can you prevent", "prevention", "preventing",
    "who is most at risk", "why does", "why do ", "why a ", "why heat",
)
# ...unless the same heading also promises the signs, which IS a decision aid.
RESCUE = ("symptom", "sign", "red flag", "warning", "when to", "how long")


def is_decision(head):
    h = head.lower()
    if DECISION.search(head) is None:
        return False
    if any(r in h for r in RESCUE):
        return True
    return not any(b in h for b in BACKGROUND)
def strip(html):
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", html)).strip()

def sections(html):
    """(heading_text, section_html) for each h2, in document order."""
    hits = list(re.finditer(r"<h2\b[^>]*>(.*?)</h2>", html, re.S))
    for i, m in enumerate(hits):
        end = hits[i + 1].start() if i + 1 < len(hits) else len(html)
        yield strip(m.group(1)), html[m.end():end]

def enumerable(body):
    if re.search(r"<table\b", body, re.I):
        return "table"
    if len(re.findall(r"<li\b", body, re.I)) >= 3:
        return "list"
    # A prose section split into h3 subheadings IS enumerable: each subhead is
    # an item, whatever the paragraphs under it look like. Post 3116's "Types
    # of Skeletal Traction" names three types in three h3s and no list, and
    # scoring that as narrative left 1,531 impressions with no card available.
    if len(re.findall(r"<h3\b", body, re.I)) >= 2:
        return "subheads"
    # A threshold or a window: two or more figures with units.
    if len(re.findall(r"\d+\s*(?:%|°|degrees?|hours?|minutes?|days?|weeks?|"
                      r"months?|years?|inch(?:es)?|mm|cm)\b", body, re.I)) >= 2:
        return "figures"
    return None

def has_visual(body):
    """What is already carrying this section's answer.

    Only a table or an existing cc-card BLOCKS a new card, because only those
    two actually answer the question. A plain <img> does not: the inline images
    on this site are decorative stock photography, and a photo of a thermometer
    does not tell a parent which temperature means the ER. Counting any image
    as an answer scored post 2780 at zero cards needed purely because its
    sections carry stock photos, which was wrong.
    """
    if re.search(r"<table[^a-z]", body, re.I):
        return "table"
    if "cc-card" in body:
        return "card"
    if re.search(r"<(figure|img)[^a-z]", body, re.I):
        return "photo"      # noted, never blocking
    return None

def qtoks(t):
    t = re.sub(r"<[^>]+>", " ", t).lower()
    return [w for w in re.findall(r"[a-z0-9]+", t) if w not in STOP]


def is_page_head(heading, title):
    """Is this h2 just the post title again?

    The first h2 on these posts often restates the title, so every page-level
    query matches it and Axis B reports the whole post's demand as if one
    intro paragraph owned it. Post 3103 showed 2,797 impressions on a 98-word
    intro whose actual answer is the table in the next section.
    """
    t, h = set(qtoks(title)), set(qtoks(heading))
    # Under four content words a heading cannot be "the title again", it is just
    # a short heading whose few words happen to sit inside the title. Excluding
    # those threw away "What Is a Stress Fracture?" and "What is muscle strain?",
    # which are the definitional headings this axis was added for.
    if len(h) < 4 or not t:
        return False
    return len(t & h) / len(h) >= 0.7


def rank_axis(queries, heads):
    """Impressions per heading. Winner takes all.

    Each query is credited to the ONE heading it matches best, never to every
    heading that shares a word with it. The first cut of this credited
    "hairline fracture symptoms" to three separate headings on post 3977 and
    counted the same 4,300 impressions three times, which inflated the worklist
    and would have argued for three cards to serve one query.

    The post's biggest query is its head term. It belongs to the page, not to
    whichever heading happens to echo it, so a query is only considered when it
    says something MORE than the head term does. It is then matched on its full
    wording, which is what keeps "skin traction vs skeletal traction" attached
    to the heading that compares the two rather than to the one that lists
    types.
    """
    out = {h: {"impr": 0, "top": []} for h in heads}
    rows = [r for r in (queries or []) if r.get("query")]
    if not rows:
        return out
    head = set(qtoks(max(rows, key=lambda r: r.get("impressions", 0))["query"]))
    hsets = [(h, set(qtoks(h))) for h in heads]

    for r in rows:
        q = r["query"]
        qt = qtoks(q)
        if not qt or all(w in head for w in qt):
            continue
        if any(w in OFF_MARKET for w in qt):
            continue
        best, score_ = None, 0.0
        for h, hs in hsets:
            if not hs:
                continue
            sim = sum(1 for w in qt if w in hs) / len(qt)
            if sim > score_:
                best, score_ = h, sim
        if best and score_ >= 0.6:
            out[best]["impr"] += r.get("impressions", 0)
            out[best]["top"].append([r.get("impressions", 0), q,
                                     round(r.get("position", 0) or 0, 1)])
    for v in out.values():
        v["top"] = sorted(v["top"], reverse=True)[:3]
    return out


def queries_for(post_id):
    f = GSC / f"{post_id}.json"
    if not f.exists():
        return None
    d = json.loads(f.read_text(encoding="utf-8"))
    d = d.get("data", d)
    return d.get("queries") or d.get("rows") or []


# Column headers that label the row axis rather than naming an alternative.
GENERIC_COL = {"question", "point", "symptom", "feature", "factor", "detail",
               "description", "response", "answer", "type", "types", "stage",
               "step", "time", "period", "area", "activity", "location",
               "sign", "signs", "what", "aspect", "category", "item"}


def split_comparison(secs):
    """Candidate sections that one table already compares side by side.

    Returns {section heading: heading of the section holding the table}.

    Matched on COLUMN HEADERS, not on topic words: see the note in the script
    that introduced this. Two or more sections have to be named by DIFFERENT
    columns of the same table before anything is flagged, because a table with
    one column matching a section is just a table about the same subject.
    """
    toks = {h: set(qtoks(h)) for h, _b in secs}
    out = {}
    for th, body in secs:
        for t in re.findall(r"<table\b.*?</table>", body, re.S | re.I):
            row = re.search(r"<tr\b.*?</tr>", t, re.S | re.I)
            if not row:
                continue
            cols = [strip(c) for c in re.findall(
                r"<t[dh]\b[^>]*>(.*?)</t[dh]>", row.group(0), re.S | re.I)]
            named = {}
            for c in cols[1:]:          # column 0 labels the rows
                cw = [w for w in qtoks(c)
                      if len(w) > 3 and w not in GENERIC_COL]
                if not cw:
                    continue
                for h in toks:
                    if h != th and all(w in toks[h] for w in cw):
                        named[h] = th
            if len(named) >= 2:
                out.update(named)
    return out


def score(html, post_id=None, title=""):
    secs = [(h, b) for h, b in sections(html) if h and not NEVER.search(h)]
    qs = queries_for(post_id) if post_id is not None else None
    rank = rank_axis(qs, [h for h, _ in secs]) if qs else {}
    # Strip the page-level head off the intro heading rather than dropping the
    # section: it can still qualify on Axis A like any other.
    if secs and title and is_page_head(secs[0][0], title):
        rank[secs[0][0]] = {"impr": 0, "top": []}

    split = split_comparison(secs)

    out = []
    for head, body in secs:
        d = is_decision(head)
        v = has_visual(body)
        e = enumerable(body)
        blocking = v in ("table", "card")
        r = rank.get(head, {"impr": 0, "top": []})

        ranks = r["impr"] >= RANK_FLOOR
        out.append({
            "heading": head, "decision": d, "visual": v, "enum": e,
            "impr": r["impr"], "queries": r["top"], "ranks": ranks,
            # Not part of `candidate`: a table elsewhere sometimes complements
            # a card and sometimes replaces it. Read both before deciding.
            "answered_elsewhere": split.get(head),
            # Either axis qualifies; both gates still apply to both.
            "candidate": (d or ranks) and not blocking and bool(e),
            "why": ("both" if d and ranks else "decision" if d
                    else "rank" if ranks else ""),
        })
    return out

def main():
    posts = json.loads(BODIES.read_text(encoding="utf-8"))
    want = {int(a) for a in sys.argv[1:]}
    rows = []
    for p in sorted(posts, key=lambda x: x["id"]):
        if want and p["id"] not in want:
            continue
        html = p["content"].get("raw") or p["content"].get("rendered", "")
        title = strip(p["title"].get("raw") or p["title"]["rendered"])
        s = score(html, p["id"], title)
        rows.append({
            "id": p["id"],
            "title": title,
            "have": html.count('class="cc-card"'),
            "need": sum(1 for x in s if x["candidate"]),
            # What the second axis adds on its own: candidates the decision
            # test would have thrown away. This is the number that answers
            # "how much was the one-axis rule missing".
            "rank_only": sum(1 for x in s if x["candidate"] and x["why"] == "rank"),
            "impr": sum(x["impr"] for x in s if x["candidate"]),
            "sections": len(s),
            "detail": s,
        })
    have_gsc = sum(1 for r in rows if any(x["impr"] for x in r["detail"]))
    if not have_gsc:
        print(f"NO GSC DATA in {GSC} - Axis A only. Every count below is the "
              f"old one-axis rule.")
        print()

    rows.sort(key=lambda r: (-(r["need"] - r["have"]), r["id"]))
    print(f'{"id":>5}  {"have":>4} {"need":>4} {"gap":>4} {"rank":>4}  title')
    for r in rows:
        gap = r["need"] - r["have"]
        ro = r["rank_only"] or ""
        print(f'{r["id"]:>5}  {r["have"]:>4} {r["need"]:>4} {gap:>+4} {ro:>4}  '
              f'{r["title"][:52]}')
    tot_need = sum(r["need"] for r in rows)
    tot_have = sum(r["have"] for r in rows)
    tot_rank = sum(r["rank_only"] for r in rows)
    print()
    print(f"{len(rows)} posts | live cards {tot_have} | candidates {tot_need} "
          f"| net {tot_need - tot_have:+d}")
    print(f"{tot_rank} of those candidates come from the ranking axis ALONE "
          f"(Axis A scored them background)")
    dist = {}
    for r in rows:
        dist[r["need"]] = dist.get(r["need"], 0) + 1
    print("candidates per post:",
          ", ".join(f"{k} card(s): {v} posts" for k, v in sorted(dist.items())))

    # The worklist in the order the evidence puts it, with the queries attached
    # so the reviewer can judge whether the impressions are real intent for
    # this market before anything is drawn.
    gaps = [(x["impr"], r["id"], x) for r in rows for x in r["detail"]
            if x["candidate"] and x["why"] in ("rank", "both") and x["impr"]]
    gaps.sort(reverse=True, key=lambda t: t[0])
    if gaps:
        print()
        print(f"Highest-impression candidates ({len(gaps)}), "
              f"top query shown for each:")
        for impr, pid, x in gaps[:20]:
            print(f'{impr:>7}  {pid}  [{x["why"]:8}] {x["heading"][:50]}')
            for i2, q, pos in x["queries"][:1]:
                print(f'{"":>9}   {i2:>6} impr  pos {pos:>4.1f}  {q[:52]}')
            if x["answered_elsewhere"]:
                print(f'{"":>9}   WARNING already tabled in: '
                      f'{x["answered_elsewhere"][:56]}')
    (HERE / f"cardneed-{BODIES.stem}.json").write_text(json.dumps(rows, indent=2), encoding="utf-8")
    print(f"-> cardneed-{BODIES.stem}.json (per-section detail for review)")

if __name__ == "__main__":
    main()

# -*- coding: utf-8 -*-
"""Quarterly data: the three months to 29 Aug vs the three before them.
Clicks are the comparable metric across the May reporting change; impressions are not."""
import sys, io, json, re, datetime as dt
sys.path.insert(0, r"D:\gsc-tools")
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
import gsc_common as G

SITE = "sc-domain:mammothmachinery.ca"
G.set_site("mammothmachinery.ca")
svc = G.service()

CUR = ("2026-06-01", "2026-08-29")   # 90 days
PRV = ("2026-03-03", "2026-05-31")   # 90 days, contiguous, no overlap

def days(w):
    return (dt.date.fromisoformat(w[1]) - dt.date.fromisoformat(w[0])).days + 1

def tot(w):
    rows = G.fetch_all(svc, SITE, w[0], w[1], ["date"])
    c = sum(r["clicks"] for r in rows); i = sum(r["impressions"] for r in rows)
    pos = sum(r["position"] * r["impressions"] for r in rows) / i if i else 0
    return {"clicks": c, "imp": i, "ctr": c / i * 100 if i else 0, "pos": pos, "days": len(rows)}

c, p = tot(CUR), tot(PRV)
print(f"WINDOWS: current {CUR[0]}..{CUR[1]} ({days(CUR)}d, {c['days']} with data) | "
      f"previous {PRV[0]}..{PRV[1]} ({days(PRV)}d, {p['days']} with data)\n")
print(f"  clicks       {c['clicks']:>8,}  vs {p['clicks']:>8,}   {c['clicks']-p['clicks']:+,} "
      f"({(c['clicks']/p['clicks']-1)*100:+.1f}%)   <- comparable")
print(f"  impressions  {c['imp']:>8,}  vs {p['imp']:>8,}   {c['imp']-p['imp']:+,} "
      f"({(c['imp']/p['imp']-1)*100:+.1f}%)   <- NOT comparable (May reporting change)")
print(f"  CTR          {c['ctr']:>7.2f}%  vs {p['ctr']:>7.2f}%   <- NOT comparable")
print(f"  position     {c['pos']:>8.1f}  vs {p['pos']:>8.1f}   <- treat with care")

# clean-period view: May (first corrected month) to August
print("\nCLEAN-PERIOD VIEW (May onward, all after the reporting change)")
for m, lbl in ((5, "May"), (6, "Jun"), (7, "Jul"), (8, "Aug")):
    s = dt.date(2026, m, 1)
    e = dt.date(2026, 8, 29) if m == 8 else dt.date(2026, m + 1, 1) - dt.timedelta(days=1)
    r = G.fetch_all(svc, SITE, s.isoformat(), e.isoformat(), ["date"])
    cc = sum(x["clicks"] for x in r); ii = sum(x["impressions"] for x in r)
    pp = sum(x["position"] * x["impressions"] for x in r) / ii
    print(f"  {lbl}: {cc:>4} clicks  {ii:>7,} imp  pos {pp:>4.1f}")

# non-brand, both quarters
def nb(w):
    rows = G.fetch_all(svc, SITE, w[0], w[1], ["query"])
    n = [r for r in rows if not G.is_brand(r["keys"][0])]
    b = [r for r in rows if G.is_brand(r["keys"][0])]
    return {"clicks": sum(r["clicks"] for r in n), "imp": sum(r["impressions"] for r in n),
            "queries": len(n), "br_clicks": sum(r["clicks"] for r in b),
            "share": sum(r["impressions"] for r in n) / (sum(r["impressions"] for r in n) + sum(r["impressions"] for r in b)) * 100}
nc, np_ = nb(CUR), nb(PRV)
print("\nNON-BRAND")
print(f"  clicks   {np_['clicks']:>6} -> {nc['clicks']:>6}   ({(nc['clicks']/np_['clicks']-1)*100:+.1f}%)")
print(f"  queries  {np_['queries']:>6,} -> {nc['queries']:>6,}   ({nc['queries']-np_['queries']:+,})")
print(f"  share    {np_['share']:>5.1f}% -> {nc['share']:>5.1f}%")

# money keywords, quarter vs quarter
def qmap(w):
    return {r["keys"][0]: r for r in G.fetch_all(svc, SITE, w[0], w[1], ["query"])}
A, B = qmap(CUR), qmap(PRV)
PRODUCT = re.compile(r'mini\s*(excavator|skid|dumper|digger|loader)|skid\s*steer|wheel\s*loader|'
                     r'track\s*loader|concrete\s*buggy|power\s*buggy|dumper|excavator|compact\s*loader', re.I)
cands = [(q, r) for q, r in A.items() if not G.is_brand(q) and PRODUCT.search(q)]
cands.sort(key=lambda kv: -kv[1]["impressions"])
print("\nMONEY KEYWORDS, quarter vs quarter (top 15 by current demand)")
print(f"  {'keyword':<40} {'prev':>7} {'now':>7} {'move':>7} {'clicks':>7}")
out = []
for q, r in cands[:15]:
    br = B.get(q); bp = br["position"] if br else None
    mv = (bp - r["position"]) if bp else None
    out.append({"q": q, "now": round(r["position"], 1), "prev": round(bp, 1) if bp else None,
                "move": round(mv, 1) if mv is not None else None, "clicks": r["clicks"]})
    print(f"  {q[:40]:<40} {(f'{bp:.1f}' if bp else '-'):>7} {r['position']:>7.1f} "
          f"{(f'{mv:+.1f}' if mv is not None else 'new'):>7} {r['clicks']:>7}")
up = sum(1 for o in out if o["move"] and o["move"] > 0.3)
dn = sum(1 for o in out if o["move"] and o["move"] < -0.3)
new = sum(1 for o in out if o["move"] is None)
print(f"\n  improved {up} | declined {dn} | new {new} | held {len(out)-up-dn-new}")
json.dump({"cur": c, "prv": p, "nb_cur": nc, "nb_prv": np_, "kw": out},
          open("quarter.json", "w", encoding="utf-8"), indent=1)

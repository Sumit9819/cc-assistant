# -*- coding: utf-8 -*-
"""August 2026 numbers for the monthly report, pulled fresh at report time.
Compares equal-length windows so a partial August is not measured against a full July."""
import sys, io, json, datetime as dt
sys.path.insert(0, r"D:\gsc-tools")
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
import gsc_common as G

SITE = "sc-domain:mammothmachinery.ca"
G.set_site(SITE.replace("sc-domain:", ""))
svc = G.service()

def totals(start, end):
    rows = G.fetch_all(svc, SITE, start, end, ["date"])
    c = sum(r["clicks"] for r in rows)
    i = sum(r["impressions"] for r in rows)
    # position must be impression-weighted, never a mean of means
    pos = sum(r["position"] * r["impressions"] for r in rows) / i if i else 0
    return {"clicks": c, "impressions": i, "ctr": (c / i * 100) if i else 0,
            "position": pos, "days": len(rows), "start": start, "end": end}

# find the last date GSC actually has
probe = G.fetch_all(svc, SITE, (dt.date.today() - dt.timedelta(days=10)).isoformat(),
                    dt.date.today().isoformat(), ["date"])
last = max(r["keys"][0] for r in probe) if probe else None
print(f"last date available in Search Console: {last}\n")
LAST = dt.date.fromisoformat(last)
n_days = LAST.day                      # Aug 1 .. last available
aug = totals(f"2026-08-01", last)
jul_end = dt.date(2026, 7, n_days)
jul = totals("2026-07-01", jul_end.isoformat())
jul_full = totals("2026-07-01", "2026-07-31")

def line(nm, a, b):
    d = a - b
    pct = (d / b * 100) if b else 0
    print(f"  {nm:14} {a:>10,.2f}   vs {b:>10,.2f}   {d:+,.2f}  ({pct:+.1f}%)")

print(f"AUGUST 1-{n_days} vs JULY 1-{n_days}  (equal {n_days}-day windows)")
line("clicks", aug["clicks"], jul["clicks"])
line("impressions", aug["impressions"], jul["impressions"])
line("CTR %", aug["ctr"], jul["ctr"])
print(f"  {'avg position':14} {aug['position']:>10.2f}   vs {jul['position']:>10.2f}   "
      f"{jul['position']-aug['position']:+.2f} (lower is better)")
print(f"\n  full July for reference: {jul_full['clicks']:,} clicks, {jul_full['impressions']:,} impressions, "
      f"{jul_full['ctr']:.2f}% CTR, position {jul_full['position']:.1f}")

# non-brand split: what the content work can actually influence
print("\nNON-BRAND SPLIT (August)")
q = G.fetch_all(svc, SITE, "2026-08-01", last, ["query"])
nb = [r for r in q if not G.is_brand(r["keys"][0])]
br = [r for r in q if G.is_brand(r["keys"][0])]
for nm, rows in (("non-brand", nb), ("brand", br)):
    c = sum(r["clicks"] for r in rows); i = sum(r["impressions"] for r in rows)
    print(f"  {nm:10} {c:>6,} clicks  {i:>9,} impressions  {len(rows):>5,} queries")

# top pages by clicks in August
print("\nTOP PAGES, AUGUST, BY CLICKS")
pg = G.fetch_all(svc, SITE, "2026-08-01", last, ["page"])
pg.sort(key=lambda r: -r["clicks"])
for r in pg[:12]:
    u = r["keys"][0].replace("https://mammothmachinery.ca", "") or "/"
    print(f"  {r['clicks']:>4} clicks {r['impressions']:>7,} imp  {r['position']:>5.1f}  {u}")

# the blogs published this month
print("\nBLOG POSTS PUBLISHED AUG 17-26, AUGUST PERFORMANCE")
SLUGS = ["/mini-excavator-vs-mini-skid-steer/", "/how-to-choose-mini-excavator/",
         "/landscaping-jobs-mini-skid-steer/", "/what-is-a-telescopic-wheel-loader/",
         "/how-to-choose-a-wheel-loader/", "/wheel-loader-vs-skid-steer/"]
by = {r["keys"][0].replace("https://mammothmachinery.ca", ""): r for r in pg}
tot_c = tot_i = 0
for s in SLUGS:
    r = by.get(s)
    if r:
        tot_c += r["clicks"]; tot_i += r["impressions"]
        print(f"  {r['clicks']:>3} clicks {r['impressions']:>6,} imp  pos {r['position']:>4.1f}  {s}")
    else:
        print(f"    0 clicks      0 imp   not yet appearing  {s}")
print(f"  TOTAL new posts: {tot_c} clicks, {tot_i:,} impressions")
json.dump({"last": last, "aug": aug, "jul": jul, "jul_full": jul_full},
          open("aug_metrics.json", "w", encoding="utf-8"), indent=1)

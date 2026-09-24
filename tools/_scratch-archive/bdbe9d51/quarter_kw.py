# -*- coding: utf-8 -*-
"""Quarterly money-keyword table with a minimum-volume rule.

A position from a handful of impressions is noise, not a ranking: "concrete buggy"
sat at 1.0 on THREE impressions last quarter and now sits at 8.1 on 643. Calling that
a 7-place decline would be false. So a previous position only counts as a baseline
when that window had >= MIN_IMP impressions; below that the term is "newly visible"."""
import sys, io, json, re
sys.path.insert(0, r"D:\gsc-tools")
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
import gsc_common as G

MIN_IMP = 30
SITE = "sc-domain:mammothmachinery.ca"
G.set_site("mammothmachinery.ca")
svc = G.service()
CUR, PRV = ("2026-06-01", "2026-08-29"), ("2026-03-03", "2026-05-31")

def qmap(w): return {r["keys"][0]: r for r in G.fetch_all(svc, SITE, w[0], w[1], ["query"])}
A, B = qmap(CUR), qmap(PRV)

PRODUCT = re.compile(r'mini\s*(excavator|skid|dumper|digger|loader)|skid\s*steer|wheel\s*loader|'
                     r'track\s*loader|concrete\s*buggy|power\s*buggy|dumper|excavator|compact\s*loader', re.I)
cands = sorted([(q, r) for q, r in A.items() if not G.is_brand(q) and PRODUCT.search(q)],
               key=lambda kv: -kv[1]["impressions"])

rows, up, dn, newv, held = [], 0, 0, 0, 0
for q, r in cands[:15]:
    b = B.get(q)
    base = b if (b and b["impressions"] >= MIN_IMP) else None
    if base:
        mv = base["position"] - r["position"]
        if mv > 0.3: kind, up = "up", up + 1
        elif mv < -0.3: kind, dn = "down", dn + 1
        else: kind, held = "held", held + 1
        prev_pos, prev_imp = round(base["position"], 1), base["impressions"]
    else:
        mv, kind, newv = None, "newly visible", newv + 1
        prev_pos = None
        prev_imp = b["impressions"] if b else 0
    rows.append({"q": q, "now": round(r["position"], 1), "prev": prev_pos, "kind": kind,
                 "move": round(mv, 1) if mv is not None else None,
                 "clicks": r["clicks"], "imp": r["impressions"], "prev_imp": prev_imp})

print(f"{'keyword':<40} {'prev':>6} {'now':>6} {'movement':>14} {'imp now':>8} {'imp prev':>9} {'clicks':>7}")
for x in rows:
    mv = (f'{x["move"]:+.1f}' if x["move"] is not None else "newly visible")
    print(f'{x["q"][:40]:<40} {(f"{x["prev"]:.1f}" if x["prev"] else "-"):>6} {x["now"]:>6.1f} '
          f'{mv:>14} {x["imp"]:>8,} {x["prev_imp"]:>9,} {x["clicks"]:>7}')
print(f"\nimproved {up} | declined {dn} | newly visible {newv} | held {held}   (min {MIN_IMP} prior impressions to count as a baseline)")

json.dump({"rows": rows, "up": up, "dn": dn, "new": newv, "held": held, "min_imp": MIN_IMP},
          open("quarter_kw.json", "w", encoding="utf-8"), indent=1)

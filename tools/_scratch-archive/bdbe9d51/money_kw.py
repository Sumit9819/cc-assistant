# -*- coding: utf-8 -*-
"""Money-keyword movement + non-brand share, August vs July, equal 29-day windows.
Keywords are chosen from the data (top non-brand commercial demand), not guessed."""
import sys, io, json, re
sys.path.insert(0, r"D:\gsc-tools")
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
import gsc_common as G

SITE = "sc-domain:mammothmachinery.ca"
G.set_site("mammothmachinery.ca")
svc = G.service()
AUG = ("2026-08-01", "2026-08-29")
JUL = ("2026-07-01", "2026-07-29")

def queries(win):
    rows = G.fetch_all(svc, SITE, win[0], win[1], ["query"])
    return {r["keys"][0]: r for r in rows}

a, j = queries(AUG), queries(JUL)

def share(d):
    nb = [r for q, r in d.items() if not G.is_brand(q)]
    br = [r for q, r in d.items() if G.is_brand(q)]
    ni, bi = sum(r["impressions"] for r in nb), sum(r["impressions"] for r in br)
    nc, bc = sum(r["clicks"] for r in nb), sum(r["clicks"] for r in br)
    return {"nb_imp": ni, "br_imp": bi, "nb_clicks": nc, "br_clicks": bc,
            "nb_share": ni / (ni + bi) * 100 if (ni + bi) else 0, "nb_queries": len(nb)}

sa, sj = share(a), share(j)
print("NON-BRAND SHARE (of impressions in named queries)")
for nm, s in (("July 1-29", sj), ("Aug 1-29", sa)):
    print(f"  {nm}: non-brand {s['nb_imp']:>7,} imp ({s['nb_share']:.1f}%) / brand {s['br_imp']:>6,} imp"
          f" | non-brand queries {s['nb_queries']:,} | non-brand clicks {s['nb_clicks']}")
print(f"  non-brand impressions growth: {sa['nb_imp']-sj['nb_imp']:+,} "
      f"({(sa['nb_imp']/sj['nb_imp']-1)*100:+.1f}%)")
print(f"  non-brand named-query count : {sa['nb_queries']-sj['nb_queries']:+,}")
print(f"  non-brand clicks            : {sj['nb_clicks']} -> {sa['nb_clicks']} ({sa['nb_clicks']-sj['nb_clicks']:+})")

# money keywords = non-brand, commercial product nouns, ranked by August impressions
PRODUCT = re.compile(r'mini\s*(excavator|skid|dumper|digger|loader)|skid\s*steer|wheel\s*loader|'
                     r'track\s*loader|concrete\s*buggy|power\s*buggy|dumper|excavator|compact\s*loader', re.I)
cands = [(q, r) for q, r in a.items() if not G.is_brand(q) and PRODUCT.search(q)]
cands.sort(key=lambda kv: -kv[1]["impressions"])

print("\nMONEY KEYWORDS, top 15 by August demand")
print(f"  {'keyword':<42} {'Aug pos':>8} {'Jul pos':>8} {'move':>7} {'Aug clk':>8} {'Aug imp':>9}")
out = []
for q, r in cands[:15]:
    jr = j.get(q)
    jp = jr["position"] if jr else None
    mv = (jp - r["position"]) if jp else None
    out.append({"q": q, "aug_pos": round(r["position"], 1),
                "jul_pos": round(jp, 1) if jp else None,
                "move": round(mv, 1) if mv is not None else None,
                "aug_clicks": r["clicks"], "aug_imp": r["impressions"]})
    mvs = f"{mv:+.1f}" if mv is not None else "new"
    jps = f"{jp:.1f}" if jp else "  -"
    print(f"  {q[:42]:<42} {r['position']:>8.1f} {jps:>8} {mvs:>7} {r['clicks']:>8} {r['impressions']:>9,}")

up = sum(1 for o in out if o["move"] and o["move"] > 0.3)
dn = sum(1 for o in out if o["move"] and o["move"] < -0.3)
new = sum(1 for o in out if o["move"] is None)
print(f"\n  improved: {up} | declined: {dn} | new this month: {new} | flat: {len(out)-up-dn-new}")
json.dump({"nonbrand": {"aug": sa, "jul": sj}, "keywords": out},
          open("money_kw.json", "w", encoding="utf-8"), indent=1)

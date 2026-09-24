"""
Join what each product category EARNS (GSC warehouse, non-brand, 2026-05-25..08-22)
to what each category actually CONTAINS (description word count + product count).

The point is to stop optimising by vibes. 74 categories exist; 47 are thin or
empty; but only 19 earn meaningful impressions. Writing content for the other 55
is wasted effort, and some of the thin ones (the lighting sub-categories) would
actively cannibalise /outdoor-lighting-2/ if written up.

Sort order that matters = impressions x how far from page 1, gated on whether
the page is even editable and whether stock allows a sale.
"""
import json

CAT_JSON = (r"C:/Users/sumit/.claude/projects/"
            r"c--Users-sumit-Local-Sites-plugintesting-app-public/"
            r"32bbd000-c160-41d2-824f-454b206fe3ed/tool-results/"
            r"mcp-cc-assistant-sids-ponds-com-list_product_categories-1787757004850.txt")

# slug -> (impressions, clicks, weighted position) from the warehouse
DEMAND = {
    "secure-outdoor-storage":      (20838, 176, 9.8),
    "green-living-wall-kits":      (8417, 42, 30.4),
    "artificial-turf-and-sod":     (2347, 2, 17.6),
    "inground-recessed-lighting":  (1588, 0, 61.8),
    "soil":                        (1407, 9, 27.8),
    "aggregates":                  (1311, 3, 32.5),
    "sod":                         (1011, 10, 14.9),
    "fish-and-pond-plants":        (1004, 13, 11.9),
    "rockmount-stacked-stone":     (991, 0, 43.3),
    "pond-supplies":               (855, 2, 28.5),
    "pumps-and-filters":           (682, 3, 13.6),
    "fire-pit-inserts":            (612, 6, 11.9),
    "aggregates-soil-mulch":       (553, 1, 45.3),
    "fire-pits":                   (539, 0, 26.2),
    "path-bollard-lighting":       (387, 0, 70.0),
    "interior-renovation":         (366, 0, 52.2),
    "hand-tools":                  (281, 0, 44.2),
    "outdoor-fire-features":       (230, 0, 34.7),
    "flame-media":                 (93, 0, 29.9),
}

data = json.load(open(CAT_JSON, encoding="utf-8"))
terms = data.get("terms") or data.get("categories") or data
if isinstance(terms, dict):
    terms = list(terms.values())
by_slug = {t.get("slug"): t for t in terms}

rows = []
for slug, (imp, ck, pos) in DEMAND.items():
    t = by_slug.get(slug, {})
    words = int(t.get("description_words") or 0)
    prods = int(t.get("count") or 0)
    rows.append({
        "slug": slug,
        "name": (t.get("name") or "?").replace("&amp;", "&"),
        "imp": imp, "ck": ck, "pos": pos,
        "words": words, "products": prods,
    })

rows.sort(key=lambda r: -r["imp"])

print(f"{'category':<34}{'imp':>7}{'clk':>5}{'pos':>7}{'words':>7}{'prod':>6}  verdict")
print("-" * 104)
for r in rows:
    if r["products"] == 0:
        v = "EMPTY CATEGORY - nothing to sell"
    elif r["pos"] <= 10 and r["ck"] / max(r["imp"], 1) < 0.02:
        v = "RANKS but does not convert -> stock/price, not content"
    elif r["words"] >= 250 and r["pos"] > 20:
        v = "content already done, still page 3+ -> needs links/authority"
    elif r["words"] < 100 and r["imp"] >= 500:
        v = "THIN + real demand -> WRITE THIS"
    elif r["words"] < 100:
        v = "thin, low demand -> skip for now"
    else:
        v = "mid content, mid position -> deepen"
    print(f"{r['name'][:33]:<34}{r['imp']:>7}{r['ck']:>5}{r['pos']:>7}{r['words']:>7}{r['products']:>6}  {v}")

print()
tot_imp = sum(r["imp"] for r in rows)
tot_ck = sum(r["ck"] for r in rows)
print(f"these 19 categories: {tot_imp:,} impressions -> {tot_ck} clicks "
      f"({100.0*tot_ck/tot_imp:.2f}% CTR)")

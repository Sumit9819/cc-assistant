import io, sys, re, json, urllib.request
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"
SITE = "https://mammothmachinery.ca"

PAGES = [
    "x-cavator-20mt-mini-excavator", "x-cavator-27mt-mini-excavator", "x-cavator-35mt-mini-excavator",
    "x-loader-50mt-mini-skid-steer", "x-loader-100mt-mini-skid-steer", "x-loader-120mt-mini-skid-steer",
    "x-loader-3000mt-full-size-skid-steer", "mtl1000-track-loader",
    "tt570-mini-dumper", "tt900-mini-track-dumper", "ett900-articulating-mini-dumper",
    "mt1350-mini-dumper", "mt1350cb-concrete-buggy", "mt1750-mini-dumper",
    "mt2200-mini-dumper", "mt2200hl-high-lift-dumper",
    "mt2850-track-carrier", "mt2850cb-tracked-mini-dumper",
    "wl4500-wheel-loader", "wl7500-wheel-loader", "tl5500-telescopic-wheel-loader-2",
    "product/x-loader-2000mt-mini-track-loader",
]

def get(url):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.read().decode("utf-8", "replace")

def nodes(html):
    out = []
    for b in re.findall(r'<script[^>]*application/ld\+json[^>]*>(.*?)</script>', html, re.S):
        try:
            d = json.loads(b)
        except Exception:
            continue
        graph = d.get("@graph", [d]) if isinstance(d, dict) else (d if isinstance(d, list) else [])
        for n in graph:
            if isinstance(n, dict):
                out.append(n)
    return out

print(f"{'PAGE':44s} {'PROD':>4s} {'PRICE':>9s} {'CUR':>4s} {'AVAIL':>9s} {'BRAND':>6s} {'IMG':>4s} {'SKU':>4s}  MISSING-FOR-MERCHANT-LISTING")
print("-" * 155)
gaps = []
for p in PAGES:
    url = f"{SITE}/{p}/"
    try:
        html = get(url)
    except Exception as e:
        print(f"{p:44s}  FETCH ERROR {e}")
        continue
    prod = None
    for n in nodes(html):
        t = n.get("@type", "")
        t = t if isinstance(t, str) else " ".join(t)
        if "Product" in t:
            prod = n
            break
    if not prod:
        print(f"{p:44s} {'NO':>4s} {'-':>9s} {'-':>4s} {'-':>9s} {'-':>6s} {'-':>4s} {'-':>4s}  *** NO PRODUCT SCHEMA AT ALL ***")
        gaps.append((p, "no-product-schema"))
        continue
    offers = prod.get("offers") or {}
    if isinstance(offers, list):
        offers = offers[0] if offers else {}
    price = offers.get("price")
    cur = offers.get("priceCurrency")
    avail = str(offers.get("availability", "")).rsplit("/", 1)[-1]
    brand = prod.get("brand")
    brand = (brand.get("name") if isinstance(brand, dict) else brand) or ""
    img = prod.get("image")
    sku = prod.get("sku") or prod.get("mpn") or ""
    missing = []
    if not price: missing.append("price")
    if not cur: missing.append("priceCurrency")
    if not avail: missing.append("availability")
    if not brand: missing.append("brand")
    if not img: missing.append("image")
    if not sku: missing.append("sku/mpn")
    if missing:
        gaps.append((p, ",".join(missing)))
    print(f"{p:44s} {'YES':>4s} {str(price or '-'):>9s} {str(cur or '-'):>4s} {avail[:9]:>9s} "
          f"{('Y' if brand else '-'):>6s} {('Y' if img else '-'):>4s} {('Y' if sku else '-'):>4s}  {','.join(missing) if missing else 'complete'}")

print("\n" + "=" * 70)
print(f"Pages with gaps: {len(gaps)} / {len(PAGES)}")
for p, g in gaps:
    print(f"  {p:46s} {g}")

import io, sys, re, json, urllib.request
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"
SITE = "https://mammothmachinery.ca"

PAGES = [
    ("1616", "x-cavator-20mt-mini-excavator"), ("1602", "x-cavator-27mt-mini-excavator"),
    ("1585", "x-cavator-35mt-mini-excavator"), ("3358", "x-loader-50mt-mini-skid-steer"),
    ("1568", "x-loader-100mt-mini-skid-steer"), ("1551", "x-loader-120mt-mini-skid-steer"),
    ("1630", "x-loader-3000mt-full-size-skid-steer"), ("3994", "mtl1000-track-loader"),
    ("1476", "tt570-mini-dumper"), ("1993", "tt900-mini-track-dumper"),
    ("1319", "ett900-articulating-mini-dumper"), ("1710", "mt1350-mini-dumper"),
    ("2089", "mt1350cb-concrete-buggy"), ("1668", "mt1750-mini-dumper"),
    ("1699", "mt2200-mini-dumper"), ("1648", "mt2200hl-high-lift-dumper"),
    ("1684", "mt2850-track-carrier"), ("2396", "mt2850cb-tracked-mini-dumper"),
    ("1532", "wl4500-wheel-loader"), ("1518", "wl7500-wheel-loader"),
    ("2134", "tl5500-telescopic-wheel-loader-2"),
]

def get(url):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.read().decode("utf-8", "replace")

def schema_price(html):
    for b in re.findall(r'<script[^>]*application/ld\+json[^>]*>(.*?)</script>', html, re.S):
        try:
            d = json.loads(b)
        except Exception:
            continue
        graph = d.get("@graph", [d]) if isinstance(d, dict) else (d if isinstance(d, list) else [])
        for n in graph:
            if not isinstance(n, dict):
                continue
            t = n.get("@type", "")
            t = t if isinstance(t, str) else " ".join(t)
            if "Product" in t:
                o = n.get("offers") or {}
                if isinstance(o, list):
                    o = o[0] if o else {}
                return o.get("price"), o.get("priceCurrency")
    return None, None

print(f"{'ID':>5s} {'PAGE':42s} {'ON-PAGE PRICE':>16s} {'SCHEMA':>10s} {'CUR':>4s}  STATUS")
print("-" * 110)
fix = []
for pid, slug in PAGES:
    try:
        html = get(f"{SITE}/{slug}/")
    except Exception as e:
        print(f"{pid:>5s} {slug:42s}  FETCH ERROR {e}")
        continue
    body = re.sub(r'<script.*?</script>|<style.*?</style>', '', html, flags=re.S)
    text = re.sub(r'<[^>]+>', ' ', body)
    text = re.sub(r'\s+', ' ', text)
    # on-page displayed price: "$12,345 CAD"
    cands = re.findall(r'\$\s?([\d,]{4,12})\s*CAD', text)
    onpage = cands[0] if cands else None
    sp, cur = schema_price(html)
    if onpage and not sp:
        status = "*** SCHEMA MISSING PRICE ***"
        fix.append((pid, slug, onpage.replace(",", "")))
    elif onpage and sp and onpage.replace(",", "") == str(sp).split(".")[0]:
        status = "ok (matches)"
    elif onpage and sp:
        status = f"*** MISMATCH on-page {onpage} vs schema {sp} ***"
        fix.append((pid, slug, onpage.replace(",", "")))
    elif not onpage and sp:
        status = "schema has price, none rendered on page"
    else:
        status = "*** no price anywhere ***"
    print(f"{pid:>5s} {slug:42s} {('$'+onpage+' CAD') if onpage else '-':>16s} {str(sp or '-'):>10s} {str(cur or '-'):>4s}  {status}")

print("\n" + "=" * 70)
print(f"Pages needing a schema price: {len(fix)}")
print("\nPROPOSED rank_math_snippet_product_price VALUES (from the price rendered on each live page):")
for pid, slug, price in fix:
    print(f"  post {pid:>5s}  {slug:44s} -> {price}")

import io, sys, urllib.request, urllib.error
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"
SITE = "https://mammothmachinery.ca"

# (path, 16-month clicks, 16-month impressions) from Google Search Console export
URLS = [
    ("/product-category/mini-skid-steers/", 885, 69022),
    ("/product-category/wheel-loaders/", 400, 72911),
    ("/product-category/tracked-mini-dumpers/", 306, 15064),
    ("/product/x-loader-100mt-mini-skid-steer/", 232, 6433),
    ("/product-category/wheeled-mini-dumpers/", 227, 11314),
    ("/product/x-loader-120mt/", 205, 11135),
    ("/product/x-loader-3000mt-full-size-skid-steer/", 203, 15583),
    ("/product-category/mini-excavators/", 177, 37484),
    ("/product-category/full-size-track-loaders/", 177, 21591),
    ("/products/", 152, 18058),
    ("/product/mt2850-track-carrier/", 150, 8501),
    ("/product/mt1750-mini-dumper/", 148, 13373),
    ("/product/wl4500-wheel-loader/", 138, 3496),
    ("/product-tag/mini-skid-steer-canada/", 70, 2453),
    ("/product/wl7500-wheel-loader/", 56, 927),
    ("/product/tl5500-telescopic-wheel-loader/", 47, 1894),
    ("/product/x-cavator-20mt-mini-excavator/", 39, 4327),
    ("/product/mt1350-mini-dumper/", 36, 2125),
    ("/product/tt570-mini-dumper/", 35, 2253),
    ("/product/mt2200-mini-dumper/", 30, 1852),
    ("/product/tt1000-mini-dumper/", 29, 659),
    ("/product/x-cavator-27mt-mini-excavator/", 27, 2992),
    ("/product/tt660-mini-dumper/", 22, 1415),
    ("/product/x-cavator-35mt-mini-excavator/", 19, 2508),
    ("/product/x-loader-50mt-mini-skid-steer/", 16, 478),
    ("/product/mt2200hl-high-lift-dumper/", 9, 1269),
    ("/product/ett570-electric-mini-dumper/", 8, 1244),
    ("/product/ett900-articulating-mini-dumper/", 7, 620),
    ("/product/sst1000/", 5, 477),
    ("/product/tt570/", 5, 34),
    ("/product/ett1000-electric-mini-dumper/", 2, 256),
    ("/product/x-loader-2000mt-mini-track-loader/", 0, 1),
    ("/shop/", 5, 708),
    ("/shop-2/", 3, 1432),
    ("/full-size-track-loaders-copy/", 0, 139),
    ("/roi-calculator/", 8, 1174),
    ("/terms-of-sale-warranty/", 7, 1295),
    ("/about-us/", 24, 10936),
]

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

opener = urllib.request.build_opener(NoRedirect)

def probe(url, hops=0):
    req = urllib.request.Request(url, headers={"User-Agent": UA}, method="GET")
    try:
        with opener.open(req, timeout=25) as r:
            return r.status, None
    except urllib.error.HTTPError as e:
        loc = e.headers.get("Location")
        return e.code, loc
    except Exception as e:
        return "ERR", str(e)[:60]

print(f"{'PATH':50s} {'CLK':>5s} {'IMPR':>7s}  CHAIN")
print("-" * 130)
summary = {"ok_redirect": 0, "200": 0, "404": 0, "chain": 0, "other": 0}
lost_clicks = 0
for path, clk, impr in URLS:
    url = SITE + path
    chain = []
    cur = url
    for hop in range(5):
        code, loc = probe(cur)
        chain.append(str(code))
        if code in (301, 302, 307, 308) and loc:
            chain.append("->" + loc.replace(SITE, ""))
            cur = loc if loc.startswith("http") else SITE + loc
        else:
            break
    final = chain[-1] if not chain[-1].startswith("->") else chain[-2]
    hops = sum(1 for c in chain if c in ("301", "302", "307", "308"))
    if final == "200" and hops == 1:
        summary["ok_redirect"] += 1; verdict = "OK 1-hop"
    elif final == "200" and hops == 0:
        summary["200"] += 1; verdict = "LIVE (no redirect)"
    elif final == "200" and hops > 1:
        summary["chain"] += 1; verdict = f"CHAIN {hops} hops"
    elif final == "404":
        summary["404"] += 1; verdict = "*** 404 DEAD ***"; lost_clicks += clk
    else:
        summary["other"] += 1; verdict = f"?? {final}"
    print(f"{path:50s} {clk:5d} {impr:7d}  {' '.join(chain)}   [{verdict}]")

print("\n" + "=" * 60)
print("SUMMARY:", summary)
print("16-month clicks landing on 404:", lost_clicks)

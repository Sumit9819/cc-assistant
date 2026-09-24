"""Post-apply verification for pendings 617-619: follow each URL's redirect
chain manually (hop by hop, no auto-follow) so chains are visible."""
import io, sys, re, urllib.request, urllib.error
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36")
SITE = "https://mammothmachinery.ca"

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

opener = urllib.request.build_opener(NoRedirect)

def head(url):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with opener.open(req, timeout=30) as r:
            return r.status, None
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get("Location")
    except Exception as e:
        return None, str(e)

def chain(path):
    url = path if path.startswith("http") else SITE + path
    hops = []
    for _ in range(6):
        code, loc = head(url)
        hops.append((code, url))
        if code in (301, 302, 307, 308) and loc:
            url = loc if loc.startswith("http") else SITE + loc
            continue
        break
    return hops

CHECKS = [
    ("/tl5500-telescopic-wheel-loader/",        "expect 200 direct (the page, new slug)"),
    ("/tl5500-telescopic-wheel-loader-2/",      "expect 301 -> clean slug"),
    ("/product/tl5500-telescopic-wheel-loader/","legacy Woo URL - count the hops"),
    ("/shop/",                                  "expect 301 -> /equipment/"),
    ("/product-category/uncategorized/",        "expect 301 -> /equipment/"),
]
for path, note in CHECKS:
    hops = chain(path)
    pretty = "  ->  ".join(f"[{c}] {u.replace(SITE,'') or '/'}" for c, u in hops)
    print(f"{note}\n   {pretty}\n")

# sitemap now lists the clean URL?
req = urllib.request.Request(SITE + "/page-sitemap.xml", headers={"User-Agent": UA})
body = urllib.request.urlopen(req, timeout=30).read().decode("utf-8", "replace")
clean_in = "/tl5500-telescopic-wheel-loader/" in body
dash2_in = "tl5500-telescopic-wheel-loader-2" in body
print(f"sitemap: clean URL listed={clean_in}  old -2 URL still listed={dash2_in}")

# the 3 internal links now resolve without a hop?
for src in ["/wheel-loaders/", "/wl4500-wheel-loader/"]:
    req = urllib.request.Request(SITE + src, headers={"User-Agent": UA})
    b = urllib.request.urlopen(req, timeout=30).read().decode("utf-8", "replace")
    hrefs = set(re.findall(r'href="(https?://mammothmachinery\.ca/[^"]*tl5500[^"]*)"', b))
    print(f"{src} links tl5500 via: {sorted(h.replace(SITE,'') for h in hrefs)}")

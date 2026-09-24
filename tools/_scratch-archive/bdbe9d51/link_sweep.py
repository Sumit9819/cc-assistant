"""Sweep every published page/post (from the sitemaps) and report which ones
link to the four cleanup targets. Answers: why is TL5500 orphaned, is the
-copy page reachable from nav, does anything link /shop/ or the wl7500-2 dup."""
import io, sys, re, urllib.request, urllib.error
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36")
SITE = "https://mammothmachinery.ca"

def fetch(url):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.geturl(), r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, url, ""
    except Exception as e:
        return None, url, str(e)

# 1. sitemap children
code, _, idx = fetch(SITE + "/sitemap_index.xml")
children = re.findall(r"<loc>([^<]+)</loc>", idx)
print("=== sitemap_index children ===")
for c in children:
    print("  ", c)

# 2. collect page/post URLs
urls = []
for c in children:
    if re.search(r"(page|post|product|category)-sitemap", c):
        _, _, body = fetch(c)
        urls += re.findall(r"<loc>([^<]+)</loc>", body)
urls = [u for u in dict.fromkeys(urls) if "/wp-content/" not in u]
print(f"\ncrawling {len(urls)} URLs from sitemaps")

TARGETS = {
    "tl5500 (any href)":            re.compile(r'href="([^"]*tl5500[^"]*)"', re.I),
    "track-loaders-copy":           re.compile(r'href="([^"]*track-loaders-copy[^"]*)"', re.I),
    "wl7500-wheel-loader-2 (dup)":  re.compile(r'href="([^"]*wl7500-wheel-loader-2[^"]*)"', re.I),
    "/shop/":                       re.compile(r'href="(https?://mammothmachinery\.ca/shop/)"', re.I),
    "/product-category/":           re.compile(r'href="([^"]*\/product-category\/[^"]*)"', re.I),
}
hits = {k: {} for k in TARGETS}
errors = 0
for u in urls:
    code, _, body = fetch(u)
    if code != 200:
        errors += 1
        print(f"  !! {code} {u}")
        continue
    for name, rx in TARGETS.items():
        for href in set(rx.findall(body)):
            hits[name].setdefault(href, []).append(u)

print(f"\n(fetch errors: {errors})")
for name, found in hits.items():
    print(f"\n=== pages linking {name} ===")
    if not found:
        print("   none")
    for href, pages in found.items():
        tag = "  [SITE-WIDE nav/footer]" if len(pages) > len(urls) * 0.8 else ""
        print(f"   href: {href}   linked from {len(pages)} page(s){tag}")
        if len(pages) <= 6:
            for p in pages:
                print(f"      - {p}")

# 3. status of the cleanup URLs + intended redirect targets
print("\n=== direct status checks ===")
for path in ["/full-size-track-loaders/", "/full-size-track-loaders-copy/",
             "/wl7500-wheel-loader-2/", "/equipment/", "/tl5500-telescopic-wheel-loader/",
             "/tl5500-telescopic-wheel-loader-2/"]:
    code, final, _ = fetch(SITE + path)
    redir = "" if final.rstrip("/").endswith(path.rstrip("/")) else f" -> {final}"
    print(f"  {code}  {path}{redir}")

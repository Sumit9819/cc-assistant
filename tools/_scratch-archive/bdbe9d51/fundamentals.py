import io, sys, re, urllib.request, urllib.error
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36")
SITE = "https://mammothmachinery.ca"

def fetch(url, method="GET"):
    req = urllib.request.Request(url, headers={"User-Agent": UA}, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, r.geturl(), r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, url, ""
    except Exception as e:
        return None, url, str(e)

print("=== CRAWL ENTRY POINTS ===")
for path in ["/robots.txt", "/sitemap_index.xml", "/sitemap.xml", "/wp-sitemap.xml",
             "/page-sitemap.xml", "/post-sitemap.xml"]:
    code, final, body = fetch(SITE + path)
    note = ""
    if path == "/robots.txt" and code == 200:
        sm = re.findall(r"(?im)^sitemap:\s*(\S+)", body)
        note = " | advertises: " + (", ".join(sm) if sm else "NO sitemap directive")
    if body.lstrip().startswith("<?xml") or "<urlset" in body or "<sitemapindex" in body:
        n = len(re.findall(r"<loc>", body))
        note += f" | XML with {n} <loc> entries"
    redir = "" if final.rstrip("/") == (SITE + path).rstrip("/") else f" -> {final}"
    print(f"  {code}  {path}{redir}{note}")

print()
print("=== THIN / SHOULD-NOT-BE-INDEXED PAGES ===")
for path in ["/shop/", "/product-category/uncategorized/", "/cart/", "/checkout/",
             "/my-account/", "/full-size-track-loaders-copy/"]:
    code, final, body = fetch(SITE + path)
    if code != 200:
        print(f"  {code}  {path}  (not reachable — good)")
        continue
    noindex = "noindex" in (re.search(r'<meta[^>]+robots[^>]*>', body, re.I) or type("", (), {"group": lambda s, *a: ""})()).group(0).lower()
    canon = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)', body, re.I)
    text = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", re.sub(r"<script.*?</script>|<style.*?</style>", "", body, flags=re.S)))
    words = len(text.split())
    print(f"  200  {path}  noindex={'YES' if noindex else 'NO'}  words~{words}  canonical={canon.group(1) if canon else 'NONE'}")

print()
print("=== H1 CHECK (the site-wide defect) ===")
for slug, label in [("", "Home"), ("x-cavator-20mt-mini-excavator/", "20MT product"),
                    ("mini-excavators/", "Excavator category"), ("equipment/", "Equipment hub"),
                    ("blog/", "Blog listing")]:
    code, final, body = fetch(SITE + "/" + slug)
    if code != 200:
        print(f"  {code} {label}")
        continue
    h1s = re.findall(r"<h1[^>]*>(.*?)</h1>", body, re.S | re.I)
    clean = [re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", h)).strip()[:60] for h in h1s]
    print(f"  {label:22} h1_count={len(h1s)}  {clean}")

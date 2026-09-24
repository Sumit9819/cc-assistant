"""Site-wide em-dash sweep: fetch every sitemap URL, strip script/style,
report each em dash with context. Groups identical contexts (= template text
repeating on every page) so page copy is separated from one-fix-clears-all."""
import io, sys, re, urllib.request
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"}
SITE = "https://mammothmachinery.ca"
EM = "—"

def fetch(u):
    return urllib.request.urlopen(urllib.request.Request(u, headers=UA), timeout=30).read().decode("utf-8", "replace")

idx = fetch(SITE + "/sitemap_index.xml")
urls = []
for c in re.findall(r"<loc>([^<]+)</loc>", idx):
    if re.search(r"(page|post|product|product_cat|category)-sitemap", c):
        urls += re.findall(r"<loc>([^<]+)</loc>", fetch(c))
urls = [u for u in dict.fromkeys(urls) if "/wp-content/" not in u]

hits = {}   # context -> [pages]
per_page = {}
for u in urls:
    try:
        h = fetch(u + ("&" if "?" in u else "?") + "cb=em1")
    except Exception as e:
        print(f"!! fetch fail {u}: {e}")
        continue
    body = re.sub(r"<script.*?</script>|<style.*?</style>|<!--.*?-->", "", h, flags=re.S)
    text = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", body))
    # decode the two entity spellings too (lint gap history)
    text = text.replace("&#8212;", EM).replace("&mdash;", EM)
    n = text.count(EM)
    if n:
        per_page[u] = n
        for m in re.finditer(EM, text):
            ctx = text[max(0, m.start()-60):m.start()+60].strip()
            key = re.sub(r"\d", "#", ctx)  # normalize numbers so template variants group
            hits.setdefault(key, []).append(u)

print(f"crawled {len(urls)} URLs; pages with em dashes: {len(per_page)}")
print()
print("=== per page ===")
for u, n in sorted(per_page.items(), key=lambda x: -x[1]):
    print(f"  {n:>3}  {u.replace(SITE,'')}")
print()
print("=== distinct contexts (site-wide template text = many pages) ===")
for ctx, pages in sorted(hits.items(), key=lambda x: -len(x[1])):
    tag = f"[{len(pages)} page(s)]"
    print(f"{tag:>14}  ...{ctx[:110]}...")

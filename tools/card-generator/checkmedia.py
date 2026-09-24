import base64, json, urllib.parse, urllib.request
cfg = json.load(open(r"D:\cc-assistant\.mcp.json", encoding="utf-8"))
env = cfg["mcpServers"]["cc-assistant-irvingwellnessclinic-com"]["env"]
url, user, pw = env["CC_WP_URL"], env["CC_WP_USER"], env["CC_WP_APP_PASSWORD"]
auth = base64.b64encode(f"{user}:{pw}".encode()).decode()

for term in ("trt-benefits-for-men-2", "hormone-pellets-vs-injections-vs-creams-4",
             "Cellular-Bottlenecks", "kybella-recovery-timeline"):
    q = urllib.parse.quote(term)
    req = urllib.request.Request(
        f"{url.rstrip('/')}/wp-json/wp/v2/media?search={q}&per_page=5&_fields=id,source_url,media_details",
        headers={"Authorization": "Basic " + auth, "User-Agent": "Mozilla/5.0"})
    rows = json.load(urllib.request.urlopen(req, timeout=60))
    print("== %s -> %d record(s)" % (term, len(rows)))
    for r in rows:
        md = r.get("media_details") or {}
        print("   #%s %sx%s %s" % (r["id"], md.get("width"), md.get("height"),
                                   r["source_url"].rsplit("/", 1)[-1]))

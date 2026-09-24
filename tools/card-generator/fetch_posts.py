import base64, json, os, re, urllib.request

SERVER = "cc-assistant-irvingwellnessclinic-com"
cfg = json.load(open(r"D:\cc-assistant\.mcp.json", encoding="utf-8"))
env = cfg["mcpServers"][SERVER]["env"]
url, user, pw = env["CC_WP_URL"], env["CC_WP_USER"], env["CC_WP_APP_PASSWORD"]
assert "irvingwellnessclinic" in url, url
auth = base64.b64encode(f"{user}:{pw}".encode()).decode()

os.makedirs("bodies", exist_ok=True)
rows = []
for page in (1, 2):
    req = urllib.request.Request(
        f"{url.rstrip('/')}/wp-json/wp/v2/posts?per_page=50&status=publish&page={page}&context=edit&_fields=id,slug,title,content",
        headers={"Authorization": "Basic " + auth, "User-Agent": "Mozilla/5.0"})
    try:
        data = json.load(urllib.request.urlopen(req, timeout=60))
    except urllib.error.HTTPError as e:
        print("page", page, e.code, e.read()[:200]); break
    if not data: break
    for p in data:
        body = p["content"]["raw"]
        open(f"bodies/{p['id']}.html", "w", encoding="utf-8", newline="").write(body)
        imgs = re.findall(r'<img[^>]+src="([^"]+)"', body)
        rows.append({"id": p["id"], "slug": p["slug"],
                     "words": len(re.sub(r"<[^>]+>", " ", body).split()),
                     "imgs": imgs})
json.dump(rows, open("inventory.json", "w", encoding="utf-8"), indent=1)
print(len(rows), "posts")
for r in sorted(rows, key=lambda r: r["id"]):
    print(r["id"], r["words"], len(r["imgs"]), r["slug"])

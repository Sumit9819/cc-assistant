"""
Encode featured renders to 1200x630 JPEG q86 and upload them to one ER site.

    python upload_featured.py spec-featured-erof-panel.json erofirving.com

JPEG, not WebP: Facebook and older WhatsApp clients can fail on a WebP og:image,
and Rank Math serves the featured image as og:image. Uploading changes nothing a
visitor sees; attaching it (_thumbnail_id) is queued for approval separately.
Writes uploaded-<spec>.json with post_id -> attachment id.
"""
import base64, io, json, os, sys, urllib.request
from PIL import Image

spec_path, site = sys.argv[1], sys.argv[2]
SERVER = "cc-assistant-" + site.replace(".", "-")
spec = json.load(open(spec_path, encoding="utf-8"))

cfg = json.load(open(r"D:\cc-assistant\.mcp.json", encoding="utf-8"))
env = cfg["mcpServers"][SERVER]["env"]
url, user, pw = env["CC_WP_URL"], env["CC_WP_USER"], env["CC_WP_APP_PASSWORD"]
if site not in url:
    sys.exit("Refusing: %s points at %s" % (SERVER, url))
token = base64.b64encode(("%s:%s" % (user, pw)).encode()).decode()

only = set(int(x) for x in sys.argv[3].split(",")) if len(sys.argv) > 3 else None
out = []
for e in spec["featured"]:
    if only and e["post_id"] not in only:
        continue
    im = Image.open(os.path.join("out", e["file"] + ".png")).convert("RGB")
    if im.size != (1200, 630):
        im = im.resize((1200, 630), Image.LANCZOS)
    buf = io.BytesIO()
    im.save(buf, "JPEG", quality=86, optimize=True, progressive=True)
    raw = buf.getvalue()
    name = e["file"] + ".jpg"
    body = json.dumps({"filename": name, "alt": e.get("alt", ""), "post_id": e["post_id"],
                       "content_base64": base64.b64encode(raw).decode()}).encode()
    req = urllib.request.Request(url.rstrip("/") + "/wp-json/cc-assistant/v1/assets/upload",
                                 data=body, method="POST")
    req.add_header("Content-Type", "application/json")
    req.add_header("Authorization", "Basic " + token)
    req.add_header("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36")
    with urllib.request.urlopen(req, timeout=120) as r:
        d = json.loads(r.read().decode())["data"]
    out.append({"post_id": e["post_id"], "id": d["attachment_id"], "url": d["url"],
                "kb": len(raw) // 1024, "renamed": d.get("renamed")})
    print("%-5s %-6s %3d KB %s" % (e["post_id"], d["attachment_id"], len(raw) // 1024,
                                   d["url"].rsplit("/", 1)[-1]))

name = "uploaded-" + os.path.basename(spec_path).replace("spec-", "")
json.dump(out, open(name, "w"), indent=2)

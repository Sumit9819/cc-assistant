import base64, json, os, sys, urllib.request

SERVER = "cc-assistant-irvingwellnessclinic-com"
spec = json.load(open(sys.argv[1], encoding="utf-8"))

cfg = json.load(open(r"D:\cc-assistant\.mcp.json", encoding="utf-8"))
env = cfg["mcpServers"][SERVER]["env"]
url, user, pw = env["CC_WP_URL"], env["CC_WP_USER"], env["CC_WP_APP_PASSWORD"]
if "irvingwellnessclinic" not in url:
    sys.exit("Refusing: %s points at %s" % (SERVER, url))

token = base64.b64encode(("%s:%s" % (user, pw)).encode()).decode()
out = []
for card in spec["cards"]:
    name = card["file"] + ".webp"
    path = os.path.join("out", name)
    body = json.dumps({
        "filename": name,
        "content_base64": base64.b64encode(open(path, "rb").read()).decode(),
        "alt": card.get("alt", ""),
    }).encode()
    req = urllib.request.Request(url.rstrip("/") + "/wp-json/cc-assistant/v1/assets/upload",
                                 data=body, method="POST")
    req.add_header("Content-Type", "application/json")
    req.add_header("Authorization", "Basic " + token)
    with urllib.request.urlopen(req, timeout=120) as r:
        d = json.loads(r.read().decode())["data"]
    out.append({"heading": card["heading"], "url": d["url"], "alt": card.get("alt", ""),
                "id": d["attachment_id"], "renamed": d.get("renamed")})
    print("%-4s %s" % (d["attachment_id"], d["url"].rsplit("/", 1)[-1]))

json.dump(out, open("uploaded-%s.json" % os.path.basename(sys.argv[1]).replace("spec-", "").replace(".json", ""), "w"), indent=2)

import base64, json, os, sys, urllib.request
cfg = json.load(open(r"D:\cc-assistant\.mcp.json", encoding="utf-8"))
env = cfg["mcpServers"]["cc-assistant-irvingwellnessclinic-com"]["env"]
url, user, pw = env["CC_WP_URL"], env["CC_WP_USER"], env["CC_WP_APP_PASSWORD"]
auth = base64.b64encode(f"{user}:{pw}".encode()).decode()
os.makedirs("canva", exist_ok=True)
for name in sys.argv[1:]:
    full = f"{url.rstrip('/')}/wp-content/uploads/{name}"
    req = urllib.request.Request(full, headers={
        "Authorization": "Basic " + auth,
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36",
        "Referer": url,
    })
    out = os.path.join("canva", os.path.basename(name))
    try:
        data = urllib.request.urlopen(req, timeout=60).read()
        open(out, "wb").write(data)
        kind = "IMAGE" if data[:4] in (b"RIFF", b"\x89PNG", b"\xff\xd8\xff\xe0") else "HTML/BLOCK"
        print("%-12s %7d  %s" % (kind, len(data), os.path.basename(name)))
    except Exception as e:
        print("ERR", e, name)

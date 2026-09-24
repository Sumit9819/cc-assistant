# Upload the corrected bitmap for every LIVE card whose copy changed, and
# record old_url -> new_url so each body swap is a single-string patch.
#
# A -v2 filename rather than the original: the old attachment still owns the
# old name, so WordPress would uniquify a same-name upload anyway. An explicit
# -v2 says why a second file exists. One card was renamed outright by the
# spelling pass (hair-colour -> hair-color), so it needs no suffix.
import base64, json, os, shutil, sys, urllib.request

SERVER = "cc-assistant-irvingwellnessclinic-com"
cfg = json.load(open(r"D:\cc-assistant\.mcp.json", encoding="utf-8"))
env = cfg["mcpServers"][SERVER]["env"]
url, user, pw = env["CC_WP_URL"], env["CC_WP_USER"], env["CC_WP_APP_PASSWORD"]
if "irvingwellnessclinic" not in url:
    sys.exit("Refusing: %s points at %s" % (SERVER, url))
token = base64.b64encode(("%s:%s" % (user, pw)).encode()).decode()

live = json.load(open("work_state.json", encoding="utf-8"))["live"]
live.sort(key=lambda r: (r["post_id"], r["stem"]))
done = {}
if os.path.exists("undefined_fix_uploads.json"):          # resumable
    for r in json.load(open("undefined_fix_uploads.json", encoding="utf-8")):
        done[r["stem"]] = r

out = []
for t in live:
    if t["stem"] in done:
        out.append(done[t["stem"]])
        continue
    src = os.path.join("out", t["stem"] + ".webp")
    old_stem = t["old_url"].rsplit("/", 1)[-1].replace(".webp", "")
    name = (t["stem"] + ".webp") if t["stem"] != old_stem else (t["stem"] + "-v2.webp")
    dst = os.path.join("out", name)
    if dst != src:
        shutil.copyfile(src, dst)
    body = json.dumps({"filename": name,
                       "content_base64": base64.b64encode(open(dst, "rb").read()).decode(),
                       "alt": t["alt"]}).encode()
    req = urllib.request.Request(url.rstrip("/") + "/wp-json/cc-assistant/v1/assets/upload",
                                 data=body, method="POST")
    req.add_header("Content-Type", "application/json")
    req.add_header("Authorization", "Basic " + token)
    try:
        with urllib.request.urlopen(req, timeout=180) as r:
            d = json.loads(r.read().decode())["data"]
    except Exception as e:
        json.dump(out, open("undefined_fix_uploads.json", "w", encoding="utf-8"), indent=1)
        sys.exit("FAILED on %s: %s (progress saved, %d done)" % (t["stem"], e, len(out)))
    rec = {"post_id": t["post_id"], "stem": t["stem"], "alt": t["alt"],
           "old_id": t["old_id"], "old_url": t["old_url"],
           "new_id": d["attachment_id"], "new_url": d["url"]}
    out.append(rec)
    print("%-6s %-46s %s -> %s" % (t["post_id"], t["stem"], t["old_id"], d["attachment_id"]))
    json.dump(out, open("undefined_fix_uploads.json", "w", encoding="utf-8"), indent=1)

print("\n%d uploads recorded in undefined_fix_uploads.json" % len(out))

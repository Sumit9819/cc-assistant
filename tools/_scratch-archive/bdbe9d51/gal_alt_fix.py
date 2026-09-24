# -*- coding: utf-8 -*-
"""The htmega-thumbgallery widgets on 3 machine pages store alt="" inline on each
slider_image, which overrides the attachment-level alt applied in cards 1020-1043.
Verified in the rendered DOM: attachment alt saved, page still emits alt="".
Fix: write the same descriptions into the widget's own slider_image.alt."""
import json, io, os, subprocess, sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)["cc-assistant-mammothmachinery-ca"]
env = dict(os.environ); env.update({k: str(v) for k, v in (s.get("env") or {}).items()})
proc = subprocess.Popen([s["command"]] + list(s.get("args") or []), cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding="utf-8", bufsize=1)
_mid = [0]
def call(n, a):
    _mid[0] += 1; mid = _mid[0]
    proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":mid,"method":"tools/call","params":{"name":n,"arguments":a}})+"\n"); proc.stdin.flush()
    while True:
        l = proc.stdout.readline()
        if not l: return None
        l = l.strip()
        if not l: continue
        try: m = json.loads(l)
        except Exception: continue
        if m.get("id") == mid:
            if "result" in m:
                txt = " ".join(c.get("text","") for c in m["result"].get("content",[]))
                try: return json.loads(txt)
                except Exception: return {"_raw": txt[:200]}
            return {"_error": str(m.get("error"))[:300]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"galfix","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

ALT = {
 2456: "X-Loader 100MT mini skid steer with bucket, front three-quarter view",
 2462: "X-Loader 100MT mini skid steer with bucket attachment, front view",
 2460: "X-Loader 100MT mini skid steer rear view with operator platform",
 2459: "X-Loader 100MT mini skid steer rear three-quarter view with engine compartment",
 2458: "X-Loader 100MT mini skid steer with the bucket raised on its vertical lift boom",
 2457: "X-Loader 100MT mini skid steer with the bucket raised, side view",
 2038: "X-Loader 120MT mini skid steer",
 2040: "Bucket and auxiliary hydraulic couplers on the X-Loader 120MT mini skid steer",
 2249: "X-Loader 3000MT full-size track loader, side view",
 2353: "X-Loader 3000MT full-size track loader with bucket, front three-quarter view",
 2354: "X-Loader 3000MT full-size track loader rear three-quarter view",
 2356: "X-Loader 3000MT full-size track loader with bucket, side view",
 2358: "X-Loader 3000MT track loader close-up of the engine housing and rubber tracks",
}
TARGETS = {1568: ("f1b65dd", "X-Loader 100MT"), 1551: ("55d8f3b", "X-Loader 120MT"), 1630: ("ead867a", "X-Loader 3000MT")}
WHY = ("Follow-up to the applied photo descriptions (cards 1020-1043). Those saved correctly at the media-library "
       "level, but this page's gallery widget stores its own alt attribute on each slide and it is empty, which "
       "overrides the library value. Verified in the rendered page: the library record carries the description while "
       "the page still emits alt=\"\". This writes the same descriptions into the widget itself. Image ids, URLs and "
       "slide order are preserved exactly; only the alt field on each slide changes, so nothing moves visually.")

for pid, (wid, label) in TARGETS.items():
    tree = json.loads(call("export_elementor_data", {"post_id": pid})["raw_data"])
    found = {}
    def walk(nodes):
        for n in nodes:
            if n.get("id") == wid: found["w"] = n
            walk(n.get("elements", []))
    walk(tree)
    w = found.get("w")
    if not w:
        print(f"{pid}: widget {wid} NOT FOUND"); continue
    slides = json.loads(json.dumps(w["settings"]["slider_list"]))
    hit = miss = 0
    for item in slides:
        im = item.get("slider_image") or {}
        aid = im.get("id")
        try: aid = int(aid)
        except Exception: aid = None
        if aid in ALT:
            im["alt"] = ALT[aid]; hit += 1
        elif aid is not None:
            miss += 1; print(f"   {pid}: no alt mapped for attachment {aid}")
    r = call("draft_update_elementor_widget", {
        "post_id": pid, "widget_id": wid, "settings": {"slider_list": slides},
        "summary": f"[GALLERY ALT] {label}: photo descriptions were being blanked by the gallery widget",
        "reasoning": WHY})
    print(f"{pid} {label}: {hit} slides described, {miss} unmapped -> {r.get('pending_id') if isinstance(r, dict) else r}")
proc.stdin.close(); proc.terminate()

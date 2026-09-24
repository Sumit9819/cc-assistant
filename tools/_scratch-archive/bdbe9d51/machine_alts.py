# -*- coding: utf-8 -*-
"""Photo descriptions for the remaining 11 machine pages (excavators, skid steers,
wheel loaders). Every image downloaded and viewed before its alt was written.
Attachment-level, which is what rendered correctly for the dumper batch.
Model named only where the photo sits in that model's own gallery; shared photos
(cab interior, engine bay) kept model-neutral."""
import json, io, os, subprocess, sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
ROOT = r"c:\Users\sumit\Local Sites\plugintesting\app\public"
cfg = json.load(io.open(os.path.join(ROOT, ".mcp.json"), encoding="utf-8"))
s = cfg.get("mcpServers", cfg)["cc-assistant-mammothmachinery-ca"]
env = dict(os.environ); env.update({k: str(v) for k, v in (s.get("env") or {}).items()})
proc = subprocess.Popen([s["command"]] + list(s.get("args") or []), cwd=ROOT, env=env,
    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    text=True, encoding="utf-8", bufsize=1)
_mid = [0]
def call(name, args):
    _mid[0] += 1; mid = _mid[0]
    proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":mid,"method":"tools/call","params":{"name":name,"arguments":args}})+"\n"); proc.stdin.flush()
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
            return {"_error": str(m.get("error"))[:200]}
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"machalts","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

WHY = ("Machine-page photo sweep, finishing the coverage started on the dumper pages. These photos render with no alt "
       "text, so Google Images and screen readers cannot tell what they show. Image content verified by downloading and "
       "viewing each photo before writing its description; the model is named only where the photo sits in that model's "
       "own gallery, and shared photos are described without a model number. Attachment-level, which is the level that "
       "rendered correctly on the dumper batch.")

ALTS = [
 # --- X-Cavator excavators ---
 (148,  "X-Cavator 20MT mini excavator with canopy, bucket and dozer blade"),
 (150,  "X-Cavator 27MT mini excavator with the boom extended and dozer blade"),
 (146,  "X-Cavator 35MT mini excavator with enclosed cab, side view"),
 # --- X-Loader 50MT ---
 (3371, "X-Loader 50MT engine bay with the diesel engine, air filter and hydraulic pump"),
 (3370, "X-Loader 50MT operator controls with twin joysticks and digital display"),
 (3369, "X-Loader 50MT mini skid steer with bucket and auxiliary hydraulic couplers"),
 # --- X-Loader 100MT ---
 (2456, "X-Loader 100MT mini skid steer with bucket, front three-quarter view"),
 (2462, "X-Loader 100MT mini skid steer with bucket attachment, front view"),
 (2460, "X-Loader 100MT mini skid steer rear view with operator platform"),
 (2459, "X-Loader 100MT mini skid steer rear three-quarter view with engine compartment"),
 (2458, "X-Loader 100MT mini skid steer with the bucket raised on its vertical lift boom"),
 (2457, "X-Loader 100MT mini skid steer with the bucket raised, side view"),
 # --- X-Loader 120MT ---
 (2040, "Bucket and auxiliary hydraulic couplers on the X-Loader 120MT mini skid steer"),
 # --- X-Loader 3000MT ---
 (2356, "X-Loader 3000MT full-size track loader with bucket, side view"),
 (2353, "X-Loader 3000MT full-size track loader with bucket, front three-quarter view"),
 (2354, "X-Loader 3000MT full-size track loader rear three-quarter view"),
 (2358, "X-Loader 3000MT track loader close-up of the engine housing and rubber tracks"),
 (2249, "X-Loader 3000MT full-size track loader, side view"),
 # --- Wheel loaders ---
 (102,  "Mammoth WL4500 compact wheel loader with bucket"),
 (103,  "Mammoth WL4500 compact wheel loader with the bucket raised"),
 (105,  "Mammoth WL7500 wheel loader with the bucket raised, side view"),
 (106,  "Wheel loader cab interior with steering wheel, joystick control and suspension seat"),
 (107,  "Kohler diesel engine in a Mammoth wheel loader engine bay"),
 (109,  "Mammoth TL5500 telescopic wheel loader with the boom extended and bucket raised"),
]
n = len(ALTS)
for i, (att, alt) in enumerate(ALTS, 1):
    r = call("draft_update_postmeta", {"post_id": att, "meta_key": "_wp_attachment_image_alt",
        "value": alt, "summary": f"[MACHINE alt {i}/{n}] Attachment {att}: {alt}", "reasoning": WHY})
    print(f"alt {att} -> {r.get('pending_id') if isinstance(r, dict) else r}")
proc.stdin.close(); proc.terminate()

# -*- coding: utf-8 -*-
"""De-orphan the 5 newest blog posts: one inline link each, from the category page
whose buyers are the natural audience. Inline-only per the site rule (no 'related
guides' blocks). Existing copy preserved; one sentence appended to each paragraph."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"orph","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

L = "style='color:#01B51B;text-decoration:none;'"
B = "https://mammothmachinery.ca"
WHY = ("links_orphans reports this post with zero inbound content links, so the only route to it is the blog listing. "
       "Google treats a page nothing links to as low priority, and a buyer on this category page never finds it. "
       "One inline link added inside existing copy, per the inline-links-only rule (no 'related guides' block). "
       "The surrounding sentence is unchanged; the anchor text describes the destination.")

ITEMS = [
 (39, "28170284",
  "<p style='text-align:left;margin:0;'>Telescopic boom for high-stack loading. 199 in. dump height clears most truck sides. "
  f"Read <a href='{B}/what-is-a-telescopic-wheel-loader/' {L}>what a telescopic wheel loader does</a>.</p>",
  "Wheel Loaders: link the TL5500 card to the telescopic guide"),
 (39, "1266a25b",
  "<p style='text-align:center;margin:0;'>Three loaders engineered for Canadian operators who need full-size capability in a tight footprint. "
  f"Not sure which one? Read <a href='{B}/how-to-choose-a-wheel-loader/' {L}>how to choose a wheel loader</a>.</p>",
  "Wheel Loaders: link the lineup intro to the wheel loader chooser"),
 (43, "10c3248c",
  "<p style='text-align:left;margin:0;'>Versatile, reliable workhorse. Perfect for tight residential access and landscaping. "
  f"See the <a href='{B}/landscaping-jobs-mini-skid-steer/' {L}>landscaping jobs it does best</a>.</p>",
  "Mini Skid Steers: link the 100MT card to the landscaping guide"),
 (43, "10c1f909",
  "<p style=\"text-align: left; margin: 0; line-height: 1.7;\">The X-Loader series redefines what a mini skid steer can do on a Canadian job site. "
  "With a 35\u2033 narrow profile, tight turning radius, and high-torque <a href=\"https://en.wikipedia.org/wiki/Yanmar\" target=\"_blank\" rel=\"noopener\">Yanmar diesel</a> "
  "hydraulics, these mini skid steers reach where full-size equipment can\u2019t: residential gates, indoor demolition, fenced yards, and tight landscaping sites. "
  "All three X-Loader mini skid steers ship with a universal quick-hitch plate compatible with 50+ professional attachments: bucket, auger, trencher, snow blower, "
  "and more. Pair your mini skid steer with Mammoth\u2019s <a href=\"https://mammothmachinery.ca/financing/\" target=\"_blank\" rel=\"noopener\">equipment financing</a> "
  "and get into the field faster. Compare models: the entry-level 50MT, the versatile 100MT, or the turbocharged 120MT Turbo for high-output commercial use. "
  f"Weighing this class against a wheel loader instead? Compare <a href='{B}/wheel-loader-vs-skid-steer/' {L}>a wheel loader and a skid steer</a>.</p>",
  "Mini Skid Steers: link the series intro to the wheel loader comparison"),
 (45, "7867239b",
  "<p style='text-align:center;margin:0;'>Three mini excavators, 2 ton to 3.5 ton, covering 8 ft through 10 ft 11 in of dig depth. "
  f"New to the range? Read <a href='{B}/how-to-choose-mini-excavator/' {L}>how to choose the right mini excavator</a>.</p>",
  "Mini Excavators: link the lineup intro to the excavator chooser"),
]
n = len(ITEMS)
for i, (pid, wid, html, summ) in enumerate(ITEMS, 1):
    r = call("draft_update_elementor_widget", {"post_id": pid, "widget_id": wid,
        "settings": {"editor": html}, "summary": f"[ORPHANS {i}/{n}] {summ}", "reasoning": WHY})
    print(f"{pid}/{wid} -> {r.get('pending_id') if isinstance(r, dict) else r}")
proc.stdin.close(); proc.terminate()

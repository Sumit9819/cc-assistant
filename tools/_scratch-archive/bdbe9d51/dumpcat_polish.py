# -*- coding: utf-8 -*-
"""Design-polish batch for category pages 41 (Tracked Mini Dumpers) + 37 (Wheeled Mini Dumpers).
Same defect template as the applied 45/39 batches (69 -> 88/83). Over-photo badges excluded."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"dcpolish","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

def wu(pid, wid, settings, summary, reasoning):
    r = call("draft_update_elementor_widget", {"post_id": pid, "widget_id": wid,
        "settings": settings, "summary": summary, "reasoning": reasoning})
    print(f"{pid}/{wid} -> {r.get('pending_id') if isinstance(r, dict) else r}")

INK = "#141414"
KICK = ("Invisible white 12px kicker on a light background (ratio ~1.05, WCAG needs 4.5:1) - identical defect fixed on "
        "the Mini Excavators and Wheel Loaders pages (approved, both now pass). Ink #141414 per the site kicker treatment.")
STAT = "32px brand-green stat number on white = 2.75:1, below even the 3:1 large-text minimum. Ink per site rule: green is never text on white."
BAND = "White text on the brand-green band = 2.75:1. Site rule pairs green fills with dark #141414 text (same as the approved CTA-band fixes on 45/39). Band stays green."

PAGES = {
 41: ("CAT41", [
   ("3d0fa7c7", {"title_color": INK}, "'Why Choose Mammoth' kicker: ink (was invisible white on grey)", KICK),
   ("629b6611", {"title_color": INK}, "'Hydraulic Advantage' kicker: ink (was white on white)", KICK),
   ("167386ef", {"title_color": INK}, "'Side-by-Side' kicker: ink (was invisible white on grey)", KICK),
   ("54b2c526", {"title_color": INK}, "'FAQ' kicker: ink (was invisible white on grey)", KICK),
   ("4308b975", {"title_color": INK}, "'8 Models' stat number: ink instead of green-on-white", STAT),
   ("613325eb", {"title_color": INK}, "'2,850 lb' stat number: ink instead of green-on-white", STAT),
   ("1910f70b", {"title_color": INK}, "'Ready to Move More Material?' CTA heading: dark text on the green band", BAND),
   ("494ad61e", {"typography_font_weight": "700"}, "Hero heading 'Heavy-Duty Hauling. Tough-Terrain Traction.': explicit 700 weight",
    "Renders at weight 500 (audit heading_bold fail). Green-on-dark-overlay contrast is fine; only the weight changes. Typography already custom (22px set)."),
   ("2e240e9e", {"header_size": "h3"}, "'Up To 2,850 lb' card title: h4 -> h3 (fixes heading-level skip)",
    "Audit flagged h2 -> h4 skip. Feature-card title directly after an h2; h3 restores the outline, explicit 18px size means zero visual change."),
   ("554e1a15", {"background_color": "#FFFFFF"}, "FAQ section: white background (breaks two identical grey sections in a row)",
    "'Side-by-Side' and 'FAQ' are adjacent #F9F9F9 (audit monotony fail). White yields dark / grey / white / green rhythm. 'Largest Capacity' badge was an audit false positive (sits over a photo card with dark backing) and is untouched."),
 ]),
 37: ("CAT37", [
   ("4029ee68", {"title_color": INK}, "'Why Choose Mammoth' kicker: ink (was invisible white on grey)", KICK),
   ("1a02a546", {"title_color": INK}, "'Motorized Efficiency' kicker: ink (was white on white)", KICK),
   ("303e86d7", {"title_color": INK}, "'Side-by-Side' kicker: ink (was invisible white on grey)", KICK),
   ("51115f8c", {"title_color": INK}, "'FAQ' kicker: ink (was invisible white on grey)", KICK),
   ("471112da", {"title_color": INK}, "'2 Models' stat number: ink instead of green-on-white", STAT),
   ("2b7ac813", {"title_color": INK}, "'900 lb' stat number: ink instead of green-on-white", STAT),
   ("3ef9fec4", {"title_color": INK}, "'Ready to Upgrade Your Job Site?' CTA heading: dark text on the green band", BAND),
   ("7dff0494", {"typography_font_weight": "700"}, "Hero heading 'Stable. Powerful. Built for Real Job Sites.': explicit 700 weight",
    "Renders at weight 500 (audit heading_bold fail). Green-on-dark-overlay contrast is fine; only the weight changes."),
   ("4371bd51", {"header_size": "h3"}, "'Up to 900 lb Payload' card title: h4 -> h3 (fixes heading-level skip)",
    "Audit flagged h2 -> h4 skip. Feature-card title directly after an h2; h3 restores the outline with zero visual change."),
   ("3029c23f", {"background_color": "#FFFFFF"}, "FAQ section: white background (breaks two identical grey sections in a row)",
    "'Side-by-Side' and 'FAQ' are adjacent #F9F9F9. White yields dark / grey / white / green rhythm. 'Best Value' badge was an audit false positive (over a photo card) and is untouched."),
 ]),
}
for pid, (tag, items) in PAGES.items():
    n = len(items)
    for i, (wid, st, summ, why) in enumerate(items, 1):
        wu(pid, wid, st, f"[{tag} {i}/{n}] {summ}", why)
proc.stdin.close(); proc.terminate()

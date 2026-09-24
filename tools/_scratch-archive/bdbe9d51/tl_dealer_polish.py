# -*- coding: utf-8 -*-
"""Design-polish: Full Size Track Loaders (47, 12 cards) + Find a Dealer (18, 3 cards)."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"tlpolish","version":"1"}}})+"\n"); proc.stdin.flush()
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

INK, GREEN, WHITE = "#141414", "#01B51B", "#FFFFFF"
KICK = "Invisible white 12px kicker on a light background (~1.05:1). Ink #141414 per the site kicker treatment applied on all other category pages (all now pass)."
DARK = ("INVISIBLE: dark #141414 text on a dark #141414 section (ratio 1.0 - audit confirmed, ancestor backgrounds verified). "
        "On every sibling category page this label is brand green on the dark section, which is the design-skill pattern (green reserved for dark backgrounds).")
STAT = "32px brand-green stat number on white = 2.75:1, below the 3:1 large-text minimum. Ink per site rule."
PILL = ("11px white text on a brand-green pill badge = 2.75:1 (fails 4.5:1). Site rule pairs green fills with dark #141414 text - "
        "same treatment as the approved green-band headings on the category pages. The pill stays green.")

ITEMS = [
 (47, "29cb4db3", {"title_color": GREEN}, "[TL 1/12] Hero kicker 'Stand-On Skid Steer': green (was INVISIBLE dark-on-dark)", DARK),
 (47, "1c09f31a", {"title_color": GREEN}, "[TL 2/12] 'Select Your Model' kicker: green (was INVISIBLE dark-on-dark)", DARK),
 (47, "e3c681c",  {"title_color": WHITE}, "[TL 3/12] 'Sit-In Cab Comfort' card badge: white (was INVISIBLE dark-on-dark card)",
  "Dark #141414 text inside a #1E1E1E/#141414 card (ratio ~1.0, invisible). Sibling pages render these model-card badges in white over the dark cards; matched."),
 (47, "78c8e365", {"title_color": INK}, "[TL 4/12] 'Why Choose Mammoth' kicker: ink (was invisible white on grey)", KICK),
 (47, "376ab4d5", {"title_color": INK}, "[TL 5/12] 'Loader Advantage' kicker: ink (was white on white)", KICK),
 (47, "23c0c19c", {"title_color": INK}, "[TL 6/12] 'Side-by-Side' kicker: ink (was invisible white on grey)", KICK),
 (47, "562315c3", {"title_color": INK}, "[TL 7/12] 'FAQ' kicker: ink (was invisible white on grey)", KICK),
 (47, "15e54aea", {"title_color": INK}, "[TL 8/12] '74.3 HP' stat number: ink instead of green-on-white", STAT),
 (47, "3014584",  {"title_color": INK}, "[TL 9/12] '3,000 lb' stat number: ink instead of green-on-white", STAT),
 (47, "7a171e65", {"typography_font_weight": "700"}, "[TL 10/12] Hero heading 'Most Powerful In Its Class.': explicit 700 weight",
  "Renders at weight 500 (audit heading_bold fail). Green-on-dark-overlay contrast is fine; only the weight changes."),
 (47, "1b137c3d", {"header_size": "h3"}, "[TL 11/12] '74 HP Kubota' card title: h4 -> h3 (fixes heading-level skip)",
  "Audit flagged h2 -> h4 skip. Feature-card title directly after an h2; h3 restores the outline, explicit 18px size means zero visual change."),
 (47, "3b70891b", {"background_color": WHITE}, "[TL 12/12] FAQ section: white background (breaks two identical grey sections in a row)",
  "'Side-by-Side' and 'FAQ' are adjacent #F9F9F9 (audit monotony fail). White yields dark / grey / white / green rhythm, matching the other category pages."),
 (18, "77cb15d8", {"title_color": INK}, "[DEALER 1/3] 'Authorized Network' pill badge: dark text on green", PILL),
 (18, "391a8725", {"title_color": INK}, "[DEALER 2/3] 'Headquarters' pill badge: dark text on green", PILL),
 (18, "6aba1a6e", {"title_color": INK}, "[DEALER 3/3] 'Become a Dealer' pill badge: dark text on green", PILL),
]
for pid, wid, st, summ, why in ITEMS:
    wu(pid, wid, st, summ, why)
proc.stdin.close(); proc.terminate()

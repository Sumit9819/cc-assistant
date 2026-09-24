# -*- coding: utf-8 -*-
"""Design-polish batch for category pages 45 (Mini Excavators) + 39 (Wheel Loaders).
Same defect families as the approved homepage batch (64->83). All fixes verified
against the exported trees; over-image false positives excluded."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"catpolish","version":"1"}}})+"\n"); proc.stdin.flush()
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
KICK = ("invisible white 12px kicker on a light background (ratio ~1.05, WCAG needs 4.5:1) - "
        "same lost-contrast bug the operator-approved homepage batch fixed. Ink #141414 matches "
        "the homepage kicker treatment; green stays reserved for dark sections.")
STAT = ("32px brand-green stat number on white = 2.75:1, below even the 3:1 large-text minimum. "
        "Ink #141414 per the site rule: green is never text on white; it stays as fills/accents.")
GREENBAND = ("white text on the brand-green band = 2.75:1 (fails 4.5:1). Site rule pairs green fills "
             "with dark #141414 text - same treatment as the approved homepage button fixes. The band stays green.")

# ---------- PAGE 45: Mini Excavators (10 cards) ----------
P = 45
wu(P, "35c20e8a", {"title_color": INK}, "[CAT45 1/10] 'Why Choose Mammoth' kicker: ink (was invisible white on grey)", KICK)
wu(P, "4b05750c", {"title_color": INK}, "[CAT45 2/10] 'Excavation Advantage' kicker: ink (was white on white)", KICK)
wu(P, "1d4cd478", {"title_color": INK}, "[CAT45 3/10] 'Side-by-Side' kicker: ink (was invisible white on grey)", KICK)
wu(P, "5ccd9f2",  {"title_color": INK}, "[CAT45 4/10] 'FAQ' kicker: ink (was invisible white on grey)", KICK)
wu(P, "c45edd1",  {"title_color": INK}, "[CAT45 5/10] '3 Models' stat number: ink instead of green-on-white", STAT)
wu(P, "2a36135e", {"title_color": INK}, "[CAT45 6/10] '10 ft 11 in' stat number: ink instead of green-on-white", STAT)
wu(P, "61465eb7", {"title_color": INK}, "[CAT45 7/10] 'Ready to Dig In?' CTA heading: dark text on the green band", GREENBAND)
wu(P, "ff9ed37",  {"typography_font_weight": "700"},
   "[CAT45 8/10] Hero heading 'Deep Digging. Tight-Access Power.': explicit 700 weight",
   "Renders at weight 500 (audit heading_bold fail). Its green-on-dark-overlay contrast is fine (false positive excluded); only the weight changes. Typography is already custom (22px set), so the weight applies directly.")
wu(P, "4753a7ee", {"header_size": "h3"},
   "[CAT45 9/10] 'Up To 10 ft 11 in Dig' card title: h4 -> h3 (fixes heading-level skip)",
   "Audit flagged h2 -> h4 skip. This feature-card title follows an h2 directly; h3 restores the outline with zero visual change (18px size is set explicitly on the widget).")
wu(P, "1a948551", {"background_color": "#FFFFFF"},
   "[CAT45 10/10] FAQ section: white background (breaks two identical grey sections in a row)",
   "Sections 'Side-by-Side' and 'FAQ' are adjacent with the same #F9F9F9 (audit monotony fail). White here yields dark / grey / white / green rhythm per the design system's alternating-background rule.")

# ---------- PAGE 39: Wheel Loaders (14 cards) ----------
P = 39
wu(P, "52e8bc18", {"title_color": INK}, "[CAT39 1/14] 'Why Mammoth' kicker: ink (was invisible white on grey)", KICK)
wu(P, "1ebd3c91", {"title_color": INK}, "[CAT39 2/14] 'Articulated Design' kicker: ink (was white on white)", KICK)
wu(P, "71d1c2d8", {"title_color": INK}, "[CAT39 3/14] 'Side-by-Side' kicker: ink (was invisible white on grey)", KICK)
wu(P, "36aee674", {"title_color": INK}, "[CAT39 4/14] 'FAQ' kicker: ink (was invisible white on grey)", KICK)
wu(P, "1d2677e0", {"title_color": INK}, "[CAT39 5/14] 'Financing Made Easy' kicker: ink (was white on white card)", KICK)
wu(P, "34bf54a8", {"title_color": INK}, "[CAT39 6/14] '3 Models' stat number: ink instead of green-on-white", STAT)
wu(P, "6cf4ee88", {"title_color": INK}, "[CAT39 7/14] 'Quick-Coupler' stat: ink instead of green-on-white", STAT)
wu(P, "4fa81b82", {"title_color": INK}, "[CAT39 8/14] 'Mammoth Shield' kicker on green card: dark text", GREENBAND)
wu(P, "6b2bca2a", {"title_color": INK}, "[CAT39 9/14] 'Mammoth Shield Warranty' heading: dark text on the green card", GREENBAND)
wu(P, "729e2d98", {"title_color": INK}, "[CAT39 10/14] '5 Years / 3,000 Hours' display: dark text on the green card", GREENBAND)
wu(P, "7dd8a5b7", {"title_color": INK}, "[CAT39 11/14] 'Ready for Real Loader Power?' CTA heading: dark text on the green band", GREENBAND)
wu(P, "1cb5740e", {"typography_font_weight": "700"},
   "[CAT39 12/14] Hero heading 'Articulated Power. Real Job-Site Capacity.': explicit 700 weight",
   "Renders at weight 500 (audit heading_bold fail). Green-on-dark-overlay contrast is fine; only the weight changes. Typography already custom (22px set).")
wu(P, "4d9aadfa", {"header_size": "h3"},
   "[CAT39 13/14] 'Cummins Diesel Power' card title: h4 -> h3 (fixes heading-level skip)",
   "Audit flagged h2 -> h4 skip. Feature-card title directly after an h2; h3 restores the outline, size is explicit so zero visual change.")
wu(P, "12b1ab7e", {"background_color": "#FFFFFF"},
   "[CAT39 14/14] FAQ section: white background (breaks two identical grey sections in a row)",
   "'Mammoth Shield' and 'FAQ' sections are adjacent #F9F9F9 (audit monotony fail). White yields dark / grey / white / green rhythm. NOTE: 'Best Value'/'Flagship' white badges were audit false positives (they sit over photo cards with dark backing) and are deliberately untouched.")
proc.stdin.close(); proc.terminate()

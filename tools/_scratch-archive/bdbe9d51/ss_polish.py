# -*- coding: utf-8 -*-
"""Design polish for page 43 (Mini Skidsteers), score 66 - worst remaining page.
Snippet (title/description) deliberately NOT touched: that page is under a
measurement hold until the Sep scoring day. Design only.
Excluded as false positives: 67be5d60 / 6c72e170 / 6592d0a5 ('Flagship',
'Versatile' x2) - all sit over photo cards with #1E1E1E backing, verified in the
ancestor chain, same pattern as the wheel-loader badges."""
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
proc.stdin.write(json.dumps({"jsonrpc":"2.0","id":9000,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"sspolish","version":"1"}}})+"\n"); proc.stdin.flush()
while True:
    l = proc.stdout.readline().strip()
    if l:
        try:
            if json.loads(l).get("id") == 9000: break
        except Exception: pass
proc.stdin.write(json.dumps({"jsonrpc":"2.0","method":"notifications/initialized"})+"\n"); proc.stdin.flush()

INK = "#141414"
HOLD = (" Snippet untouched: this page's title and description stay frozen until the September scoring day, "
        "and design changes do not affect that test.")
KICK = ("Invisible white 12px kicker on a light background (ratio ~1.05, WCAG needs 4.5:1). Ink #141414 matches the "
        "kicker treatment already applied and approved on every other category page, all of which now pass." + HOLD)
STAT = ("32px brand-green stat number on white = 2.75:1, below even the 3:1 large-text minimum. Ink per the site rule: "
        "brand green is never text on white." + HOLD)
GREEN = ("White text on the brand-green warranty card = 2.75:1 (fails 4.5:1). Site rule pairs green fills with dark "
         "#141414 text, the same treatment approved on the wheel-loader warranty card and every CTA band." + HOLD)

ITEMS = [
 ("55d8ada0", {"title_color": INK}, "'Why Mammoth' kicker: ink (was invisible white on grey)", KICK),
 ("24ad5100", {"title_color": INK}, "'Mammoth Technology' kicker: ink (was white on white)", KICK),
 ("5cff88ee", {"title_color": INK}, "'Side-by-Side' kicker: ink (was invisible white on grey)", KICK),
 ("207276b1", {"title_color": INK}, "'FAQ' kicker: ink (was invisible white on grey)", KICK),
 ("7aa20d74", {"title_color": INK}, "'Financing Made Easy' kicker: ink (was white on a white card)", KICK),
 ("6e71f5a1", {"title_color": INK}, "'3 Models' stat number: ink instead of green-on-white", STAT),
 ("6dc0d645", {"title_color": INK}, "'Universal' stat: ink instead of green-on-white", STAT),
 ("2db3e4aa", {"title_color": INK}, "'Mammoth Shield' kicker on the green card: dark text", GREEN),
 ("5742d323", {"title_color": INK}, "'Mammoth Shield Warranty' heading on the green card: dark text", GREEN),
 ("5e75b066", {"title_color": INK}, "'5 Years / 3,000 Hours' display on the green card: dark text", GREEN),
 ("4bbf6d8c", {"title_color": INK}, "'Ready to Experience Mammoth Power?' CTA heading: dark text on the green band", GREEN),
 ("716808ba", {"typography_font_weight": "700"}, "Hero heading 'Compact Power. Unlimited Versatility.': explicit 700 weight",
  "Renders at weight 500 (audit heading_bold fail). Its green-on-dark-overlay contrast is fine, so only the weight changes. Typography is already custom (22px set)." + HOLD),
 ("3dc10a2a", {"header_size": "h3"}, "'5-Yr / 3,000 Hr Warranty' card title: h4 to h3 (fixes heading-level skip)",
  "Audit flagged an h2 to h4 skip. This feature-card title follows an h2 directly; h3 restores the outline and the 18px size is set explicitly on the widget, so nothing moves visually." + HOLD),
 ("1cda9d78", {"background_color": "#FFFFFF"}, "FAQ section: white background (breaks two identical grey sections in a row)",
  "'Mammoth Shield' and 'FAQ' are adjacent and both #F9F9F9 (audit monotony fail). White gives the dark / grey / white / green rhythm used on every other category page." + HOLD),
 ("1471acca", {"image": {"id": 3410, "url": "https://mammothmachinery.ca/wp-content/uploads/2026/06/X-Loader-100MT-2-1.png",
                          "alt": "X-Loader 100MT mini skid steer with bucket attachment", "source": "library", "size": ""},
               "image_alt": "X-Loader 100MT mini skid steer with bucket attachment"},
  "Main section photo had no description Google or a screen reader can read",
  "The only image on this page renders with no alt text. It carried a stray 'image_alt' key that Elementor does not "
  "render (and which contained an em dash, against the style guide). Alt is now set on the image object itself, which "
  "is the field Elementor actually outputs, and the stray key is cleaned up to match. Photo content verified by "
  "downloading and viewing it: a green tracked mini skid steer with a bucket attachment, front view. Attachment id and "
  "URL preserved exactly." + HOLD),
]
n = len(ITEMS)
for i, (wid, st, summ, why) in enumerate(ITEMS, 1):
    r = call("draft_update_elementor_widget", {"post_id": 43, "widget_id": wid, "settings": st,
        "summary": f"[SKIDSTEER {i}/{n}] {summ}", "reasoning": why})
    print(f"{wid} -> {r.get('pending_id') if isinstance(r, dict) else r}")
proc.stdin.close(); proc.terminate()

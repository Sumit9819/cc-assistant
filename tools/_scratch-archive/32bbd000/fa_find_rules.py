"""
Which stylesheet emits the TikTok (e07b), header-menu (f48b) and heart (f004)
glyphs on sids-ponds? Fetch every <link rel=stylesheet> plus inline <style>
blocks and search for BOTH spellings: the CSS escape (\\e07b) as authored, and
the literal character as a browser would resolve it.

Knowing the source file decides scope: a Divi Theme Builder layout or Divi
custom CSS is editable through the plugin; a third-party plugin stylesheet is
not.
"""
import re
import urllib.request

BASE = "https://sids-ponds.com/"
UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/128.0 Safari/537.36")

TARGETS = {"e07b": "TikTok", "f48b": "header menu icon", "f004": "wishlist heart"}


def get(url):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=45) as r:
        return r.read().decode("utf-8", "ignore")


html = get(BASE + "?cc=probe")

sheets = re.findall(r'<link[^>]+rel=["\']stylesheet["\'][^>]*>', html, re.I)
hrefs = []
for tag in sheets:
    m = re.search(r'href=["\']([^"\']+)["\']', tag)
    if m:
        h = m.group(1)
        if h.startswith("//"):
            h = "https:" + h
        elif h.startswith("/"):
            h = "https://sids-ponds.com" + h
        hrefs.append(h)

print(f"stylesheets linked: {len(hrefs)}")

inline = re.findall(r"(?is)<style[^>]*>(.*?)</style>", html)
print(f"inline <style> blocks: {len(inline)}\n")

def scan(name, css):
    for hexcode, label in TARGETS.items():
        ch = chr(int(hexcode, 16))
        esc = "\\" + hexcode
        hit = None
        if ch in css:
            i = css.index(ch)
            hit = ("literal char", css[max(0, i - 170):i + 30])
        elif esc in css.lower():
            i = css.lower().index(esc)
            hit = ("escape", css[max(0, i - 170):i + 30])
        if hit:
            kind, ctx = hit
            ctx = re.sub(r"\s+", " ", ctx).strip()
            print(f"  >>> U+{hexcode} ({label}) via {kind}")
            print(f"      in : {name}")
            print(f"      ctx: ...{ctx[-190:]}")
            print()


for i, block in enumerate(inline):
    scan(f"inline <style> #{i}", block)

for h in hrefs:
    try:
        css = get(h)
    except Exception as e:
        print(f"  [skip] {h[:90]} -> {e}")
        continue
    short = h.replace("https://sids-ponds.com", "").split("?")[0]
    scan(short + f"  ({len(css)//1024}KB)", css)

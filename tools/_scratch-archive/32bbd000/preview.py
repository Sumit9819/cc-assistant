"""
Render the proposed footer icons locally and screenshot them, so the artwork is
checked BEFORE it goes near a live revenue site's footer.

Left column reproduces the current state (Font Awesome webfont glyphs, loaded
from the same CDN the site uses). Right column is the proposed inline SVG. If
the two columns look the same, the swap is visually a no-op and only the 110KB
webfont disappears.
"""
import io
import re

SP = (r"C:/Users/sumit/AppData/Local/Temp/claude/"
      r"c--Users-sumit-Local-Sites-plugintesting-app-public/"
      r"32bbd000-c160-41d2-824f-454b206fe3ed/scratchpad")

new = io.open(f"{SP}/module23.txt", encoding="utf-8").read()
# strip Divi's line-break holders to get real HTML
html_block = new.replace("<!-- [et_pb_line_break_holder] -->", "\n")

# current state: the literal FA characters this module uses today
current = (
    '<div class="payment-icons fa-current">\n'
    '  <a href="#">\n'
    '    <div class="stripe-logo">\uf429</div>\n'
    '    <div class="stripe-logo">\uf1f0</div>\n'
    '    <div class="stripe-logo">\uf1f1</div>\n'
    '    <div class="stripe-logo">\uf1f3</div>\n'
    "  </a>\n</div>"
)

# pull the tiktok data-uri back out so we can preview it at real size
m = re.search(r'url\("(data:image/svg\+xml,[^"]+)"\)', new)
tiktok_uri = m.group(1) if m else ""

page = f"""<!doctype html>
<html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
      integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA=="
      crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
  body {{ background:#1e2a24; color:#FFFBEA; font-family:Lato,Arial,sans-serif; margin:0; padding:28px; }}
  h3 {{ font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#9aa39d; margin:0 0 10px; }}
  .cols {{ display:flex; gap:40px; align-items:flex-start; }}
  .col {{ flex:1; }}
  .fa-current {{ font-family: FontAwesome !important; }}
  .social {{ display:flex; gap:14px; margin-top:6px; }}
  .social .icon {{
      width:44px; height:44px; border-radius:50px; border:2px solid rgba(255,251,234,0.5);
      display:block; position:relative;
  }}
  .social .icon:before {{
      content:""; position:absolute; inset:0;
      background-image:url("{tiktok_uri}");
      background-repeat:no-repeat; background-position:center; background-size:22px 22px;
  }}
  .fa-tt {{ width:44px; height:44px; border-radius:50px; border:2px solid rgba(255,251,234,0.5);
           display:flex; align-items:center; justify-content:center;
           font-family:"Font Awesome 6 Brands"; font-size:22px; color:#FFFBEA; }}
{html_block[html_block.index("<style>") + 7: html_block.rindex("</style>")]}
</style></head>
<body>
<div class="cols">
  <div class="col">
    <h3>Current — Font Awesome webfont</h3>
    {current}
    <h3 style="margin-top:26px">TikTok (webfont)</h3>
    <div class="social"><span class="fa-tt">&#xe07b;</span></div>
  </div>
  <div class="col">
    <h3>Proposed — inline SVG</h3>
    {html_block[html_block.index('<div class="payment-icons">'): html_block.index("<style>")]}
    <h3 style="margin-top:26px">TikTok (inline SVG)</h3>
    <div class="social"><span class="icon et-social-tiktok"></span></div>
  </div>
</div>
</body></html>"""

io.open(f"{SP}/preview.html", "w", encoding="utf-8", newline="").write(page)
print("wrote preview.html;", len(page), "chars")
print("tiktok data-uri recovered:", bool(tiktok_uri))

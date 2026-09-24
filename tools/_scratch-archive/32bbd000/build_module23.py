"""
Build the replacement inner_content for sids-ponds footer layout 2077, module 23.

Goal: stop fa-brands-400.woff2 (109,808 bytes) downloading on EVERY page. It is
held up by exactly five brand glyphs — four payment logos rendered as literal
Font Awesome characters in this module, and the TikTok icon, which Divi emits as
content:"\\E07B" in its own stylesheet. ETmodules has no glyph there, so the
browser falls through the font stack to Font Awesome Brands and pulls 110KB to
draw one character. Nobody chose that; it is a silent fallback.

Replacing all five with inline SVG means no brands glyph is ever requested, so
the face is never downloaded. The <link> stays for now: the truck icon on five
menu items (\\f48b) and the wishlist heart (\\f004) come from the SOLID face and
live in Divi Customizer CSS, which is a separate job.

Artwork is the official Font Awesome 6.4.2 SVG for each icon, pulled from the
same release already on the page, so the geometry is identical to what renders
today. Nothing is redrawn by hand.
"""
import io
import re
import urllib.parse

SRC = r"C:/Users/sumit/AppData/Local/Temp/claude/c--Users-sumit-Local-Sites-plugintesting-app-public/32bbd000-c160-41d2-824f-454b206fe3ed/scratchpad/svg"
# Codepoints verified against the 6.4.2 stylesheet the site loads:
# f429=stripe (NOT apple-pay), f1f0=cc-visa, f1f1=cc-mastercard, f1f3=cc-amex.
ICONS = ["stripe", "cc-visa", "cc-mastercard", "cc-amex"]
LB = "<!-- [et_pb_line_break_holder] -->"
ICON_COLOR = "#FFFBEA"   # module 4 icon_color
ICON_PX = "22px"         # module 4 icon_font_size


def read_svg(name):
    raw = io.open(f"{SRC}/{name}.svg", encoding="utf-8").read()
    vb = re.search(r'viewBox="([^"]+)"', raw).group(1)
    paths = re.findall(r'<path[^>]*\bd="([^"]+)"', raw)
    if not paths:
        raise SystemExit(f"no path in {name}")
    return vb, paths


def inline_svg(name):
    """Payment icon: inherits colour and size from .payment-icons div, exactly
    as the font glyph did (fill:currentColor, height:1em)."""
    vb, paths = read_svg(name)
    d = "".join(f'<path d="{p}"/>' for p in paths)
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}" '
        f'role="img" aria-label="{name.replace("cc-", "").replace("-", " ")}" '
        f'style="height:1em;width:auto;vertical-align:-.125em;fill:currentColor">'
        f"{d}</svg>"
    )


def data_uri(name, fill):
    vb, paths = read_svg(name)
    d = "".join(f'<path d="{p}"/>' for p in paths)
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}" '
           f'fill="{fill}">{d}</svg>')
    return urllib.parse.quote(svg, safe="")


tiktok_uri = data_uri("tiktok", ICON_COLOR)

blocks = []
blocks.append(
    '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/'
    'font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc'
    '4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" '
    'crossorigin="anonymous" referrerpolicy="no-referrer" />'
)
blocks.append('<div class="payment-icons">')
blocks.append('\t<a href="/shop" aria-label="Payment methods we accept">')
for name in ICONS:
    blocks.append('  <div class="stripe-logo">')
    blocks.append("    \t" + inline_svg(name))
    blocks.append("  </div>")
blocks.append("  </a>")
blocks.append("</div>")
blocks.append("")

# Divi's parser counts [et_pb_line_break_holder] as a module OPEN tag with no
# closer, so the rebuilt body must carry EXACTLY as many as the original (35) —
# more trips rebuild_parse_mismatch (hard refusal), fewer trips the
# shortcode_preservation lint, which matches the same bracket token. The HTML
# section above uses 18, so this block must be exactly 18 lines (17 holders).
css = f"""<style>
  .payment-icons a {{
    width: 100%;
    display: flex;
    justify-content: center;
  }}
  .payment-icons div {{
   	font-size: 2.6rem;
    padding: 0.7rem;
    border-radius: 6px;
    margin: 5px;
    color: rgba(222,222,222, 0.4);
    background: rgba(222,222,222, 0.1);
  }}
  /* Divi emits content:"\E07B" for TikTok, but ETmodules has no glyph there, so the browser falls back to Font Awesome Brands and pulls a 110KB webfont for one character. Paint the same official icon inline instead. */
  .et-social-tiktok a.icon:before {{
    content: "" !important; background: url("data:image/svg+xml,{tiktok_uri}") no-repeat center / {ICON_PX} {ICON_PX};
  }}</style>"""

blocks.extend(css.split(chr(10)))

# Divi code modules separate lines with the line-break holder comment.
out = LB.join(blocks)

io.open(r"C:/Users/sumit/AppData/Local/Temp/claude/c--Users-sumit-Local-Sites-plugintesting-app-public/32bbd000-c160-41d2-824f-454b206fe3ed/scratchpad/module23.txt", "w", encoding="utf-8", newline="").write(out)
print("payment SVGs inlined :", ", ".join(ICONS))
print("tiktok data-uri chars:", len(tiktok_uri))
print("new inner_content len:", len(out))
print("font-family FontAwesome on .payment-icons removed:",
      "font-family: FontAwesome" not in out)
print("link retained (solid face still needed):", "all.min.css" in out)

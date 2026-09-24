# Downsample the 2x Playwright PNGs and encode WebP.
#
# Rendering at deviceScaleFactor 2 then resampling gives cleaner small text than
# rendering at 1x; LANCZOS is what keeps the 17px note lines legible.
#
# The target size is READ OFF THE PNG rather than hardcoded. Card width is
# always 1200, but height is now sized to the content (630px floor, see the
# geometry note in layouts.mjs), so a fixed (1200, 628) resize would have
# squashed every tall card back into the old frame and distorted its type.
import json, os, sys
from PIL import Image

SCALE = 2          # must match deviceScaleFactor in make.mjs
FRAME_W = 1200

spec = json.load(open(sys.argv[1], encoding="utf-8"))
# In-body specs key on "cards", featured/OG specs on "featured". Same 2x PNGs
# from the same renderer, so they get the same encoder rather than a second one.
for card in spec.get("cards") or spec["featured"]:
    src = os.path.join("out", card["file"] + ".png")
    dst = os.path.join("out", card["file"] + ".webp")
    im = Image.open(src).convert("RGB")
    if im.width != FRAME_W * SCALE:
        sys.exit("%s: rendered %dpx wide, expected %d. Width is fixed at %d; "
                 "check make.mjs before encoding."
                 % (os.path.basename(src), im.width, FRAME_W * SCALE, FRAME_W))
    w, h = im.width // SCALE, im.height // SCALE
    im.resize((w, h), Image.LANCZOS).save(dst, "WEBP", quality=82, method=6)
    print("%6d  %4dx%-4d  %s" % (os.path.getsize(dst), w, h, os.path.basename(dst)))

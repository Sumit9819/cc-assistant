"""
Clear-zone check for photo-panel featured images.

    python clearzone.py spec-featured-erof-panel.json

Operator rule (2026-09-24): the thing the post is about must be SEEN in the
clear part of the photo, not in the fade. With `fade: soft` the panel starts at
x=440 and the mask reaches 80% opacity at x=820 (50% of the 760px panel), so
this crops every render to x>=820 and lays the crops side by side. Read the
sheet: if the topic cannot be named from the crop alone, move `focus`, mirror
(only when the photo carries no printed text) or pick another photo.
"""
import json, os, sys
from PIL import Image, ImageDraw

CLEAR_X = 820          # 1200px canvas; soft fade is >=80% opaque from here
spec = json.load(open(sys.argv[1], encoding="utf-8"))
items = [e for e in spec["featured"] if e.get("layout") == "photo-panel"]
if not items:
    sys.exit("No photo-panel entries in " + sys.argv[1])

cw, ch = 1200 - CLEAR_X, 630
cols = min(4, len(items))
rows = (len(items) + cols - 1) // cols
sheet = Image.new("RGB", (cols * (cw + 10), rows * (ch + 34)), "white")
d = ImageDraw.Draw(sheet)
for i, e in enumerate(items):
    im = Image.open(os.path.join("out", e["file"] + ".png")).convert("RGB")
    s = im.width / 1200                      # renders are 2x
    crop = im.crop((int(CLEAR_X * s), 0, im.width, im.height)).resize((cw, ch))
    x, y = (i % cols) * (cw + 10), (i // cols) * (ch + 34)
    sheet.paste(crop, (x, y))
    d.text((x + 4, y + ch + 8), "%s  %s" % (e["post_id"], e.get("kicker", "")), fill="black")
out = os.path.join("out", "_clearzone-" + os.path.basename(sys.argv[1]).replace(".json", ".png"))
sheet.save(out)
print(out)

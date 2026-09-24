import sys
from pathlib import Path
from PIL import Image

D = Path("tset")
names = sys.argv[1].split(",")
cols = int(sys.argv[2]); w = int(sys.argv[3]); out = sys.argv[4]
tiles = []
for n in names:
    im = Image.open(D / f"{n}.png").convert("RGB")
    tiles.append(im.resize((w, int(w * 9 / 16)), Image.LANCZOS))
h = tiles[0].size[1]
rows = (len(tiles) + cols - 1) // cols
gap = 10
sheet = Image.new("RGB", (cols * w + (cols + 1) * gap, rows * h + (rows + 1) * gap), (24, 22, 19))
for i, t in enumerate(tiles):
    r, c = divmod(i, cols)
    sheet.paste(t, (gap + c * (w + gap), gap + r * (h + gap)))
sheet.save(out, quality=90)
print(out, sheet.size)

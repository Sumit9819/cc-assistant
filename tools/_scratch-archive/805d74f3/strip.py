import sys
from pathlib import Path
from PIL import Image

D = Path("tset")
names = sys.argv[1].split(","); out = sys.argv[2]
W = 210; H = int(W * 9 / 16); gap = 8
tiles = [Image.open(D / f"{n}.png").convert("RGB").resize((W, H), Image.LANCZOS) for n in names]
s = Image.new("RGB", (len(tiles) * W + (len(tiles) + 1) * gap, H + 2 * gap), (15, 15, 15))
for i, t in enumerate(tiles):
    s.paste(t, (gap + i * (W + gap), gap))
s.save(out, quality=94)
print(out, s.size)

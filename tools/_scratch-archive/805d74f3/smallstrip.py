import sys
from pathlib import Path
from PIL import Image
# paths given as dir:name pairs
pairs = [p.split(":") for p in sys.argv[1].split(",")]
W = int(sys.argv[2]); out = sys.argv[3]
H = int(W * 9 / 16); gap = 7
tiles = [Image.open(Path(d) / f"{n}.png").convert("RGB").resize((W, H), Image.LANCZOS)
         for d, n in pairs]
s = Image.new("RGB", (len(tiles) * W + (len(tiles) + 1) * gap, H + 2 * gap), (15, 15, 15))
for i, t in enumerate(tiles):
    s.paste(t, (gap + i * (W + gap), gap))
s.save(out, quality=95); print(out, s.size)

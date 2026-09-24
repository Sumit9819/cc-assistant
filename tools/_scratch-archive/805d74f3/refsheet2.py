import sys
from pathlib import Path
from PIL import Image
D = Path("D:/faceless-studio/Reference Images/Thumbnails")
names = sys.argv[1].split(","); out = sys.argv[2]; W = int(sys.argv[3]); cols = int(sys.argv[4])
files = [next(D.glob(n + "*.jpg")) for n in names]
gap = 8
tiles = [Image.open(f).convert("RGB") for f in files]
tiles = [t.resize((W, int(W * t.size[1] / t.size[0])), Image.LANCZOS) for t in tiles]
h = max(t.size[1] for t in tiles)
rows = (len(tiles) + cols - 1) // cols
s = Image.new("RGB", (cols * W + (cols + 1) * gap, rows * h + (rows + 1) * gap), (20, 20, 20))
for i, t in enumerate(tiles):
    r, c = divmod(i, cols)
    s.paste(t, (gap + c * (W + gap), gap + r * (h + gap)))
s.save(out, quality=90)
print(out, s.size)

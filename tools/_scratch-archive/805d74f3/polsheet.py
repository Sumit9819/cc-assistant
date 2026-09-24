import sys
from pathlib import Path
from PIL import Image
D = Path("pol")
files = [D / f"{n}.jpg" for n in sys.argv[1].split(",")]
W, cols, gap = 380, 4, 6
tiles = []
for f in files:
    im = Image.open(f).convert("RGB")
    im.thumbnail((W, W), Image.LANCZOS)
    c = Image.new("RGB", (W, W), (26, 26, 26))
    c.paste(im, ((W - im.size[0]) // 2, (W - im.size[1]) // 2))
    tiles.append(c)
rows = (len(tiles) + cols - 1) // cols
s = Image.new("RGB", (cols * W + (cols + 1) * gap, rows * W + (rows + 1) * gap), (20, 20, 20))
for i, t in enumerate(tiles):
    r, c = divmod(i, cols)
    s.paste(t, (gap + c * (W + gap), gap + r * (W + gap)))
s.save("polsheet.jpg", quality=88)
print(s.size)

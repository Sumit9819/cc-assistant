from pathlib import Path
from PIL import Image
D = Path("D:/faceless-studio/Reference Images/Thumbnails")
files = sorted(D.glob("*.jpg"))
W, cols, gap = 460, 3, 8
tiles = []
for f in files:
    im = Image.open(f).convert("RGB")
    tiles.append((f.name[:8], im.resize((W, int(W * im.size[1] / im.size[0])), Image.LANCZOS), im.size))
h = max(t[1].size[1] for t in tiles)
rows = (len(tiles) + cols - 1) // cols
sheet = Image.new("RGB", (cols * W + (cols + 1) * gap, rows * h + (rows + 1) * gap), (20, 20, 20))
for i, (n, t, orig) in enumerate(tiles):
    r, c = divmod(i, cols)
    sheet.paste(t, (gap + c * (W + gap), gap + r * (h + gap)))
    print(f"{i+1:2d}. {n}  {orig[0]}x{orig[1]}  ar={orig[0]/orig[1]:.2f}")
sheet.save("refs.jpg", quality=88)
print("refs.jpg", sheet.size)

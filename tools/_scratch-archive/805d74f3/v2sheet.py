import sys
from pathlib import Path
from PIL import Image
D = Path("v2")
names = sys.argv[1].split(","); cols = int(sys.argv[2]); W = int(sys.argv[3]); out = sys.argv[4]
tiles = [Image.open(D / f"{n}.png").convert("RGB").resize((W, int(W*9/16)), Image.LANCZOS) for n in names]
h = tiles[0].size[1]; gap = 8
rows = (len(tiles)+cols-1)//cols
s = Image.new("RGB", (cols*W+(cols+1)*gap, rows*h+(rows+1)*gap), (22,22,22))
for i,t in enumerate(tiles):
    r,c = divmod(i,cols); s.paste(t,(gap+c*(W+gap), gap+r*(h+gap)))
s.save(out, quality=90); print(out, s.size)

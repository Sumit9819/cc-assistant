"""
Contact sheet for one spec's rendered cards, at the size they will be READ.

    python sheet.py spec-erofirving-4.json

Cards are laid out at 560px wide, which is roughly their rendered width in the
post's content column. A card that only works at 1200px is a card that does not
work. Reviewing at full size is how the "Not for -> Nothing, it is the standard"
row and the reversed urgency panel both got through before.
"""
import json
import pathlib
import sys
from PIL import Image

HERE = pathlib.Path(__file__).parent
OUT = HERE / "out"
W, COLS, PAD, BG = 560, 3, 18, (238, 238, 240)


def main():
    spec = json.loads((HERE / sys.argv[1]).read_text(encoding="utf-8"))
    ims = []
    for c in spec["cards"]:
        f = OUT / f'{c["file"]}.png'
        if not f.exists():
            sys.exit(f'missing render: {f.name} - run make.mjs first')
        im = Image.open(f).convert("RGB")
        ims.append(im.resize((W, round(im.height * W / im.width)), Image.LANCZOS))
    rows = (len(ims) + COLS - 1) // COLS
    h = max(i.height for i in ims)
    sheet = Image.new("RGB", (COLS * W + (COLS + 1) * PAD,
                              rows * h + (rows + 1) * PAD), BG)
    for i, im in enumerate(ims):
        r, c = divmod(i, COLS)
        sheet.paste(im, (PAD + c * (W + PAD), PAD + r * (h + PAD)))
    dest = OUT / f'_{pathlib.Path(sys.argv[1]).stem}.png'
    sheet.save(dest)
    print(f'{len(ims)} cards -> {dest}  ({sheet.width}x{sheet.height})')


if __name__ == "__main__":
    main()

import io, sys, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
from pypdf import PdfReader

r = PdfReader(r"C:\Users\sumit\Downloads\2026 catalog Mammoth (1).pdf")
pages = {i + 1: (p.extract_text() or "") for i, p in enumerate(r.pages)}

# excavator lineup 19-21, wheel loader lineup 22-24 (page 20 = suspected 27MT)
for pg in [19, 20, 21, 22, 23, 24]:
    t = re.sub(r"\s+", " ", pages.get(pg, ""))
    print(f"===== CATALOG PAGE {pg} =====")
    print(t[:1600])
    print()

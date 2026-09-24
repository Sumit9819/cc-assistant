import io, sys, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
from pypdf import PdfReader

r = PdfReader(r"C:\Users\sumit\Downloads\2026 catalog Mammoth (1).pdf")
pages = [(i + 1, (p.extract_text() or "")) for i, p in enumerate(r.pages)]
allrx = "\n".join(t for _, t in pages)

DISCONTINUED = ["TT660", "TT1000", "eTT570", "ETT570", "eTT1000", "ETT1000", "SST1000"]
print("=== Are the five dead-URL models in the 2026 catalog? ===")
for m in ["TT660", "TT1000", "ETT570", "ETT1000", "SST1000"]:
    hits = [pg for pg, t in pages if m.lower() in t.lower().replace(" ", "")]
    # also try raw
    hits2 = [pg for pg, t in pages if m.lower() in t.lower()]
    found = sorted(set(hits + hits2))
    print(f"  {m:9s} -> {'PAGE ' + str(found) if found else 'NOT IN CATALOG'}")

print()
print("=== Every model the 2026 catalog actually contains (by page) ===")
MODEL = re.compile(r"\b(TT\d{3,4}|eTT\d{3,4}|MT\d{3,4}(?:CB|HL)?|MTL\d{3,4}|SST\d{3,4}|WL\d{3,4}|TL\d{3,4}|\d{2,4}MT)\b", re.I)
seen = {}
for pg, t in pages:
    for m in MODEL.findall(t):
        key = m.upper()
        seen.setdefault(key, set()).add(pg)
for k in sorted(seen, key=lambda x: (min(seen[x]), x)):
    print(f"  {k:10s} pages {sorted(seen[k])}")
print(f"\n  distinct models in catalog: {len(seen)}")

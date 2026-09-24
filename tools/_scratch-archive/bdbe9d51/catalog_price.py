import io, sys, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
from pypdf import PdfReader

PDF = r"C:\Users\sumit\Downloads\2026 catalog Mammoth (1).pdf"
r = PdfReader(PDF)
pages = [(i + 1, (p.extract_text() or "")) for i, p in enumerate(r.pages)]

MONEY = re.compile(r"\$\s?[\d][\d,\.]{2,12}")

print("=== Does the 2026 catalog contain ANY prices? ===")
total = 0
for pg, t in pages:
    hits = MONEY.findall(t)
    if hits:
        total += len(hits)
        print(f"  page {pg:>2}: {hits}")
print(f"  total dollar figures found in catalog: {total}")

print()
print("=== MTL1000 in the catalog ===")
found = False
for pg, t in pages:
    if re.search(r"\bMTL\s?1000\b", t, re.I):
        found = True
        print(f"  --- page {pg} ---")
        # print the region around the mention
        m = re.search(r"\bMTL\s?1000\b", t, re.I)
        s = max(0, m.start() - 200)
        chunk = re.sub(r"\s+", " ", t[s:m.start() + 900])
        print("  " + chunk)
        print()
if not found:
    print("  MTL1000 does NOT appear anywhere in the 2026 catalog.")

print()
print("=== SST1000 (the model MTL1000 replaced) in the catalog ===")
hit = [pg for pg, t in pages if re.search(r"\bSST\s?1000\b", t, re.I)]
print("  pages:" if hit else "  not present.", hit if hit else "")

print()
print("=== every model token the catalog contains ===")
MODEL = re.compile(r"\b(TT\d{3,4}|eTT\d{3,4}|MT\d{3,4}(?:CB|HL)?|MTL\d{3,4}|SST\d{3,4}|WL\d{3,4}|TL\d{3,4}|\d{2,4}MT)\b", re.I)
seen = {}
for pg, t in pages:
    for m in MODEL.findall(t):
        seen.setdefault(m.upper(), set()).add(pg)
for k in sorted(seen, key=lambda x: (min(seen[x]), x)):
    print(f"  {k:10s} pages {sorted(seen[k])}")
print(f"  distinct models: {len(seen)}")

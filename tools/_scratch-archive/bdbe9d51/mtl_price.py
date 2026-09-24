import io, sys, re
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

D = r"C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\bdbe9d51-2098-4917-93a9-eb1883a6d1fc\scratchpad"

for label, fn in [("MTL1000", "can_mtl1000-track-loader.html"),
                  ("3000MT (sibling for contrast)", "can_x-loader-3000mt-full-size-skid-steer.html")]:
    html = open(f"{D}\\{fn}", encoding="utf-8", errors="replace").read()
    body = re.sub(r"<script.*?</script>|<style.*?</style>", "", html, flags=re.S)
    text = re.sub(r"<[^>]+>", " ", body)
    text = re.sub(r"\s+", " ", text)
    print("=" * 70)
    print(label)
    money = sorted(set(re.findall(r"\$\s?[\d,]{3,12}", text)))
    print("  dollar figures rendered:", money if money else "NONE")
    m = re.search(r'name="description" content="([^"]*)"', html)
    print("  meta description:", (m.group(1)[:170] if m else "NONE"))
    i = text.find("MTL1000")
    if i >= 0:
        print("  body from first mention:")
        print("   ", text[i:i + 420])
    print()

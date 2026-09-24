import os, zipfile

SRC = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant"
BASE = os.path.dirname(SRC)
OUT = r"C:\Users\sumit\Desktop\cc-assistant-0.68.3.zip"

SKIP_DIRS = {".git", "node_modules", ".idea", ".vscode", "__pycache__"}
SKIP_EXT = {".bak", ".orig", ".rej", ".log"}
SKIP_FILES = {".DS_Store", "Thumbs.db"}

n = 0
with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(SRC):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS and not d.startswith(".tmp-")]
        for f in sorted(files):
            if os.path.splitext(f)[1].lower() in SKIP_EXT or f in SKIP_FILES:
                continue
            full = os.path.join(root, f)
            arc = os.path.relpath(full, BASE).replace(os.sep, "/")
            z.write(full, arc)
            n += 1

print("files:", n)
print("zip  :", OUT)
print("bytes:", os.path.getsize(OUT))

with zipfile.ZipFile(OUT) as z:
    names = z.namelist()
    bad = [x for x in names if chr(92) in x]
    print("backslash entries:", bad if bad else "none (good)")
    print("roots:", sorted({x.split("/")[0] for x in names}))
    for probe in [
        "cc-assistant/cc-assistant.php",
        "cc-assistant/bin/mcp-server.php",
        "cc-assistant/includes/class-rest-api.php",
        "cc-assistant/includes/class-apply.php",
        "cc-assistant/includes/class-capabilities.php",
        "cc-assistant/admin/views/pending.php",
        "cc-assistant/tests/rank-math-schema-test.php",
        "cc-assistant/readme.txt",
    ]:
        print("  ok " if probe in names else "  MISSING ", probe)
    # confirm the shipped version strings
    for f, needle in [("cc-assistant/cc-assistant.php", b"0.65.0"), ("cc-assistant/bin/mcp-server.php", b"CC_MCP_VERSION', '0.65.0")]:
        print("  version ok " if needle in z.read(f) else "  VERSION BAD ", f)

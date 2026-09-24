# Build the cc-assistant deploy zip.
#
# Python zipfile with explicit forward-slash arcnames: a zip built on Windows
# with backslash separators installs as one flat file named
# "cc-assistant\includes\class-rest-assets.php" and the plugin silently has no
# includes directory at all.
import os, zipfile

SRC = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant"
OUT = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant.zip"

SKIP_DIRS = {".git", "node_modules", "tests", ".idea", ".vscode", "__pycache__"}
SKIP_EXT = {".zip", ".log", ".sqlite", ".db"}

count = 0
with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(SRC):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for f in files:
            if os.path.splitext(f)[1].lower() in SKIP_EXT:
                continue
            full = os.path.join(root, f)
            rel = os.path.relpath(full, SRC).replace("\\", "/")
            z.write(full, "cc-assistant/" + rel)
            count += 1

print("%d files -> %s (%d bytes)" % (count, OUT, os.path.getsize(OUT)))

import re, zipfile
z = zipfile.ZipFile(r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant.zip")
names = z.namelist()
print("files:", len(names))
print("backslashes present:", any("\\" in n for n in names))
for want in ("cc-assistant/cc-assistant.php",
             "cc-assistant/includes/class-rest-assets.php",
             "cc-assistant/bin/mcp-server.php"):
    print(want, "->", want in names)
src = z.read("cc-assistant/cc-assistant.php").decode("utf-8", "ignore")
print("version in zip:", re.search(r"CC_ASSISTANT_VERSION', '([^']+)", src).group(1))
print("delete route in zip:", b"/assets/delete" in z.read("cc-assistant/includes/class-rest-assets.php"))
print("tests excluded:", not any(n.startswith("cc-assistant/tests/") for n in names))

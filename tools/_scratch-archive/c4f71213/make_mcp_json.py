"""Write D:/cc-assistant/.mcp.json from the current project .mcp.json:
same servers and credentials, minus the local plugintesting site (no local
WordPress any more), with the standalone PHP under D:/cc-assistant/php."""
import json, os

SRC = r"C:\Users\sumit\Local Sites\plugintesting\app\public\.mcp.json"
DST = r"D:\cc-assistant\.mcp.json"
PHP = "D:/cc-assistant/php/php.exe"
EXT = "D:/cc-assistant/php/ext"

cfg = json.load(open(SRC, encoding="utf-8"))
out = {"mcpServers": {}}
dropped = []
for name, srv in cfg["mcpServers"].items():
    if name == "cc-assistant-plugintesting":
        dropped.append(name)
        continue
    if name.startswith("cc-assistant-"):
        srv = dict(srv)
        srv["command"] = PHP
        srv["args"] = ["-d", f"extension_dir={EXT}", "-d", "extension=curl", "-d", "extension=openssl",
                       "-d", "extension=sqlite3", "-d", "extension=mbstring",
                       "./wp-content/plugins/cc-assistant/bin/mcp-server.php"]
    out["mcpServers"][name] = srv
with open(DST, "w", encoding="utf-8", newline="\n") as fh:
    json.dump(out, fh, indent=2)
    fh.write("\n")
print("servers:", list(out["mcpServers"]))
print("dropped:", dropped)
print("passwords filled:", sum(1 for s in out["mcpServers"].values() if s.get("env", {}).get("CC_WP_APP_PASSWORD")))

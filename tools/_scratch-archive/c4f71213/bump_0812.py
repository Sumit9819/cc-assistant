import io, os, re
root = r"D:\cc-assistant\wp-content\plugins\cc-assistant"
def sub(path, old, new, count=1):
    p = os.path.join(root, path); s = io.open(p, encoding="utf-8").read()
    assert old in s, f"{path}: {old!r} not found"
    io.open(p, "w", encoding="utf-8", newline="\n").write(s.replace(old, new, count)); print("ok", path)
sub("bin/mcp-server.php", "define( 'CC_MCP_VERSION', '0.81.1' );", "define( 'CC_MCP_VERSION', '0.81.2' );")
sub("cc-assistant.php", " * Version: 0.81.1", " * Version: 0.81.2")
sub("cc-assistant.php", "define( 'CC_ASSISTANT_VERSION', '0.81.1' );", "define( 'CC_ASSISTANT_VERSION', '0.81.2' );")
sub("readme.txt", "Stable tag: 0.81.1", "Stable tag: 0.81.2")
sub("readme.txt", "== Changelog ==\n\n= 0.81.1 =", "== Changelog ==\n\n= 0.81.2 =\n* brain-sync / operator_brain_push over HTTPS from the standalone workspace PHP failed with \"unable to get local issuer certificate\": the workspace PHP ships no php.ini and therefore no CA bundle. The brain HTTP client now does what the bridge has done since v0.60.1: verify the peer and, when curl.cainfo is unset, use the Windows certificate store (CURLSSLOPT_NATIVE_CA). Local dev hosts keep verification off.\n\n= 0.81.1 =")
for path in ("bin/mcp-server.php", "cc-assistant.php", "readme.txt"):
    s = io.open(os.path.join(root, path), encoding="utf-8").read()
    print(path, "0.81.2 count", s.count("0.81.2"))

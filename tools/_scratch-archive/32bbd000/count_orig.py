"""
How many [et_pb_line_break_holder] markers does the CURRENT module 23 contain?

This matters because the marker starts with et_pb_, so Divi's parser counts each
one as a module OPEN tag with no closer. Exceeding the original count trips the
server's rebuild_parse_mismatch guard (hard refusal); dropping below it trips the
shortcode_preservation lint, which matches the same bracket token. The safe
target is exactly the original count.

Reads module 23 through the plugin's own REST endpoint so the number comes from
the live stored content, not from a transcription.
"""
import json
import os
import urllib.request

SITE = "https://sids-ponds.com"
USER = os.environ.get("CC_USER", "")
APP_PW = os.environ.get("CC_PW", "")

url = f"{SITE}/wp-json/cc-assistant/v1/divi/module?post_id=2077&index=23"
req = urllib.request.Request(url)
if USER and APP_PW:
    import base64
    tok = base64.b64encode(f"{USER}:{APP_PW}".encode()).decode()
    req.add_header("Authorization", "Basic " + tok)
req.add_header("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0")

try:
    with urllib.request.urlopen(req, timeout=45) as r:
        data = json.loads(r.read().decode())
    inner = data.get("inner_content", "")
    print("markers in live module 23:", inner.count("[et_pb_line_break_holder]"))
    print("inner length:", len(inner))
except Exception as e:
    print("REST read failed:", e)
    print("Falling back: count from the copy saved during this session.")

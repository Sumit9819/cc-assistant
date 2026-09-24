#!/usr/bin/env python3
"""Build a WordPress-uploadable cc-assistant.zip with forward-slash paths.

PowerShell Compress-Archive and .NET ZipFile write backslashes which WP refuses.
This uses Python's zipfile (always forward slashes) and skips dev artifacts.
Run from the plugin root: python3 build-zip.py
"""
import os
import sys
import zipfile

PLUGIN_DIR = os.path.dirname(os.path.abspath(__file__))
PLUGIN_NAME = os.path.basename(PLUGIN_DIR)  # "cc-assistant"


def plugin_version():
    """Read Version: from the plugin header.

    Hardcoding it in the build script is how a sibling script ended up
    shipping every release named 0.28.1 for dozens of versions — the operator
    cannot tell which zip is which, and neither can a support thread.
    """
    header = os.path.join(PLUGIN_DIR, "cc-assistant.php")
    with open(header, encoding="utf-8") as fh:
        for line in fh:
            if "Version:" in line:
                return line.split("Version:", 1)[1].strip()
            if line.lstrip().startswith("define("):
                break
    return "unknown"


VERSION = plugin_version()
OUT = os.path.join(os.path.dirname(PLUGIN_DIR), f"{PLUGIN_NAME}-{VERSION}.zip")

EXCLUDE_DIRS = {".git", "node_modules", "tmp", "vendor-dev", "tests", "__pycache__"}
EXCLUDE_FILES = {"build-zip.py", ".DS_Store", "Thumbs.db"}
EXCLUDE_EXT = {".log", ".swp", ".bak", ".previous"}

def should_skip(path):
    parts = path.split(os.sep)
    if any(p in EXCLUDE_DIRS for p in parts):
        return True
    name = os.path.basename(path)
    if name in EXCLUDE_FILES:
        return True
    _, ext = os.path.splitext(name)
    if ext in EXCLUDE_EXT:
        return True
    return False

def check_no_bom():
    """Refuse to build if any PHP file starts with a UTF-8 BOM.

    Three bytes (EF BB BF) before `<?php` are echoed on every request that
    loads the file: JSON responses stop parsing and every header() call fails
    with "headers already sent". Shipped exactly this once, from PowerShell
    `Set-Content -Encoding utf8`, which writes WITH a BOM on Windows
    PowerShell 5.1. Use WriteAllBytes or -Encoding utf8NoBOM instead.
    """
    bad = []
    for root, dirs, files in os.walk(PLUGIN_DIR):
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
        for f in files:
            if not f.endswith(".php"):
                continue
            full = os.path.join(root, f)
            with open(full, "rb") as fh:
                if fh.read(3) == b"\xef\xbb\xbf":
                    bad.append(os.path.relpath(full, PLUGIN_DIR))
    if bad:
        print("REFUSING TO BUILD - UTF-8 BOM found in:", file=sys.stderr)
        for b in bad:
            print(f"  {b}", file=sys.stderr)
        sys.exit(1)



def _php_binary():
    """Find a PHP CLI the same way tests/run.sh does. Forward slashes on
    purpose: glob accepts them on Windows and they avoid backslash-escape
    accidents in this file."""
    import glob
    from shutil import which
    env = os.environ.get("CC_TEST_PHP")
    if env and os.path.exists(env):
        return env
    home = os.path.expanduser("~")
    pats = [
        home + "/AppData/Local/Programs/Local/resources/extraResources/lightning-services/php-8*/bin/win64/php.exe",
        home + "/AppData/Roaming/Local/lightning-services/php-8*/bin/win64/php.exe",
    ]
    for pat in pats:
        hits = sorted(glob.glob(pat))
        if hits:
            return hits[-1]
    return which("php")


def check_php_syntax():
    """Refuse to build if any shipped PHP file has a syntax error.

    Learned the hard way 2026-08-21. A tool DESCRIPTION in bin/mcp-server.php is
    a single-quoted PHP string; an edit that introduced an apostrophe
    ("a page's own movement") terminated the string and broke the whole file.
    `php -l` did report it, but nothing gated the build on that result, so a zip
    containing a fatal parse error in the MCP server was packaged and handed
    over. The test suite does not load bin/mcp-server.php at all, so this gate is
    the only thing between a bad edit and a dead server on every connected site.
    """
    php = _php_binary()
    if not php:
        print("REFUSING TO BUILD: no PHP binary found. Set CC_TEST_PHP.", file=sys.stderr)
        sys.exit(1)
    import subprocess
    bad = []
    for root, dirs, files in os.walk(PLUGIN_DIR):
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
        for f in files:
            if not f.endswith(".php"):
                continue
            full = os.path.join(root, f)
            r = subprocess.run([php, "-l", full], capture_output=True, text=True)
            if r.returncode != 0:
                lines = (r.stdout or r.stderr).strip().splitlines()
                bad.append((os.path.relpath(full, PLUGIN_DIR),
                            lines[0] if lines else "syntax error"))
    if bad:
        print("REFUSING TO BUILD - PHP syntax errors:", file=sys.stderr)
        for name, msg in bad:
            print("  " + name + ": " + msg, file=sys.stderr)
        sys.exit(1)


def main():
    check_no_bom()
    check_php_syntax()
    if os.path.exists(OUT):
        os.remove(OUT)
    count = 0
    with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as z:
        for root, dirs, files in os.walk(PLUGIN_DIR):
            dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
            for f in files:
                full = os.path.join(root, f)
                if should_skip(full):
                    continue
                # Archive name = "cc-assistant/relative/path" with forward slashes
                rel = os.path.relpath(full, os.path.dirname(PLUGIN_DIR))
                rel_fwd = rel.replace(os.sep, "/")
                z.write(full, rel_fwd)
                count += 1
    print(f"Wrote {OUT} ({count} files, {os.path.getsize(OUT)} bytes)")

if __name__ == "__main__":
    main()

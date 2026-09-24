import io

P = r"c:\Users\sumit\Local Sites\plugintesting\app\public\wp-content\plugins\cc-assistant\build-zip.py"
t = io.open(P, encoding="utf-8", newline="").read()

anchor = "def main():\n    check_no_bom()\n"
assert t.count(anchor) == 1, f"anchor matched {t.count(anchor)} times"

gate = '''
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
        print("WARNING: no PHP binary found - syntax gate SKIPPED. "
              "Set CC_TEST_PHP to enable it.", file=sys.stderr)
        return
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
'''

io.open(P, "w", encoding="utf-8", newline="").write(t.replace(anchor, gate, 1))
print("  [ok] syntax gate added to build-zip.py")

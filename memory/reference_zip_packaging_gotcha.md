---
name: WordPress plugin zips on Windows must use forward slashes — never PowerShell Compress-Archive
description: PowerShell's Compress-Archive AND .NET System.IO.Compression.ZipFile.CreateFromDirectory write backslash path separators on Windows, which violates the ZIP spec and breaks WordPress plugin uploads (creates new install folder per upload instead of overwriting). Use Python's zipfile module instead.
type: reference
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
---
**Symptom:** Uploading an updated plugin zip via WP admin → Plugins → Add New → Upload Plugin creates a NEW folder named after the zip filename (e.g. `cc-assistant-0.10.9/`, `cc-assistant-0.10.9-1/`) instead of overwriting the existing `cc-assistant/` folder. Each upload appears as a separate plugin in the WP Plugins list.

**Cause:** WordPress's unzipper expects ZIP entries with forward-slash path separators (`cc-assistant/cc-assistant.php`) per the ZIP spec (PKWARE APPNOTE 4.4.17.1). When entries use backslashes (`cc-assistant\cc-assistant.php`), the unzipper treats the entire string as a flat filename, fails to detect the top-level folder, and falls back to creating a folder named after the zip filename.

**Tools that produce broken (backslash) zips on Windows:**
- PowerShell `Compress-Archive` (all versions through 5.1, possibly later)
- .NET `[System.IO.Compression.ZipFile]::CreateFromDirectory()` on Windows .NET Framework
  - These both use `Path.DirectorySeparatorChar` which is `\` on Windows

**Tools that produce correct (forward-slash) zips:**
- Python `zipfile` module — always uses `/` per spec
- `7z` / `7za` if installed
- Linux/WSL `zip` command
- Git Bash bundled `zip` if available (often missing in default install)

**Verify a zip:** `unzip -l plugin.zip | head -5` — entries should show `plugin-name/file.php`, not `plugin-name\file.php`.

**Reliable Python packaging recipe:**
```python
import os, zipfile
src = 'cc-assistant'  # the plugin folder
out = 'cc-assistant-X.Y.Z.zip'
skip_dirs = {'.git', 'node_modules', '__pycache__'}
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(src):
        dirs[:] = [d for d in dirs if d not in skip_dirs]
        for f in files:
            if f == '.gitignore':
                continue
            full = os.path.join(root, f)
            arc = full.replace(os.sep, '/')  # critical: forward slashes
            z.write(full, arc)
```

**Caught via:** cc-assistant 0.10.9 upload to erofwhiterock.com on 2026-05-05. Initial zip was built with PowerShell `Compress-Archive` after a copy-to-temp workaround for file-lock issue. Each WP upload created a new folder (`cc-assistant-0.10.9`, `cc-assistant-0.10.9-1`), leaving the active 0.10.7 untouched. Symptom looked like a WP/SiteGround quirk; root cause was the zip itself.

**Detection in a built zip:** `unzip -l file.zip` shows backslashes in entry names. The fix is always to rebuild — backslashes in a zip cannot be patched in-place reliably.

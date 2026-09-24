---
name: Always build the plugin zip yourself
description: When the plugin needs deploying to a remote site, build the zip without asking. Use the Python zipfile recipe; don't offer "should I zip" — just do it.
type: feedback
originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---
When a cc-assistant code change needs to deploy to a remote site (irvingwellness, eroflufkin, erofwhiterock, or any future site), package the zip immediately. Don't ask "want me to build the zip or will you deploy your way?" — just build it and hand over the path.

**Why:** 2026-05-11 — patched the lint typographic-diff defect for 0.10.27 and asked the user whether to build the zip. User: "can you just it for me, always zip it yourself."

**How to apply:**
1. Bump version in `cc-assistant.php` (both the `Version:` header and `CC_ASSISTANT_VERSION` define).
2. PHP syntax check the changed files via the bundled Local PHP (`php -l`).
3. Build the zip with Python `zipfile` module per `reference_zip_packaging_gotcha.md` — forward slashes only.
4. Verify with `zipfile.namelist()`: zero entries with backslashes, main file present at `cc-assistant/cc-assistant.php`.
5. Hand over the file path and a one-line install note (Admin → Plugins → Add New → Upload).

**Skip the "should I zip" question.** Asking adds a round trip when the deploy step is required.

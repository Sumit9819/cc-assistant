---
name: Standalone PHP for the cc-assistant workspace
description: PHP 8.2.29 lives INSIDE the workspace at D:/cc-assistant/php (php.exe + ext with curl, openssl, sqlite3, mbstring); Local by Flywheel is retired, never reference its path again
type: reference
originSessionId: 4bb11e9a-33e3-4141-be26-b4503505ff06
---
System PHP is not in PATH on this machine. Since 2026-09-06 the workspace ships its own:

`D:/cc-assistant/php/php.exe` with `-d extension_dir=D:/cc-assistant/php/ext -d extension=curl -d extension=openssl -d extension=sqlite3 -d extension=mbstring`

Copied out of Local by Flywheel's bundle (73 MB subset: php.exe, root DLLs, the four
extensions) and verified standalone: the full plugin test suite passes with
`CC_TEST_PHP=D:/cc-assistant/php/php.exe bash tests/run.sh`, and every server in
`D:/cc-assistant/.mcp.json` uses it. Local by Flywheel and the plugintesting site are
retired; the old path under `AppData/Local/Programs/Local/...` must not be used or
recommended any more (it disappears when Local is uninstalled).

**No php.ini, so no CA bundle.** HTTPS from this PHP works only because the bridge (since
v0.60.1) and the brain HTTP client (since v0.81.2) pass `CURLSSLOPT_NATIVE_CA` when
`curl.cainfo` is empty, which makes curl use the Windows certificate store. Any NEW PHP
script that calls HTTPS from this workspace must do the same or it fails with "unable to
get local issuer certificate" (hit 2026-09-06 on the first brain push from the workspace).

Use it for `php -l`, the test runner, `bin/brain-sync.php`, and as the `.mcp.json` command.
Related: [[project_cc_assistant_operator_brain]], [[reference_plugin_dev_vs_remote_deploy]].

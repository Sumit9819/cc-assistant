import json,re,shutil
from pathlib import Path
root=Path(__file__).resolve().parent
stage=root/'cc-assistant'
for relative,old,new in [
 ('cc-assistant.php','Version: 0.89.4','Version: 0.89.5'),
 ('cc-assistant.php',"define( 'CC_ASSISTANT_VERSION', '0.89.4' );","define( 'CC_ASSISTANT_VERSION', '0.89.5' );"),
 ('bin/mcp-server.php',"define( 'CC_MCP_VERSION', '0.89.4' );","define( 'CC_MCP_VERSION', '0.89.5' );"),
 ('readme.txt','Stable tag: 0.89.4','Stable tag: 0.89.5')]:
    p=stage/relative;s=p.read_text(encoding='utf-8');assert s.count(old)==1;(p).write_text(s.replace(old,new),encoding='utf-8')
p=stage/'bin/agent-contract.php';s=p.read_text(encoding='utf-8');m=re.search(r"CC_SHARED_JSON'\n(.*?)\nCC_SHARED_JSON",s,re.S);d=json.loads(m[1]);d['manifest']['version']='0.89.5'
d['manifest']['features']['pending_queue']['what']='Draft content and queued changes require administrator approval. Independent supported edits can survive separate Apply requests only through complete approved snapshot history. The content fingerprint ignores exactly the WordPress runtime job flags _pingme, _encloseme and _trackbackme; content, SEO, author, permalink, taxonomy and other plugin metadata remain protected. The tested 0.89.3/0.89.4 to 0.89.5 patch compatibility changes only the CC Assistant version in environment comparison. Use verify_change.evidence_check.diagnostics to distinguish overlapping edits, unexplained save-hook writes and missing snapshot history. Do not repeatedly rebuild proposals or treat preflight as proof a full batch will apply. Some tools write organizational metadata or local files immediately; inspect each tool contract.'
d['tool_descriptions']['verify_change'] += ' Since 0.89.5 evidence_check.diagnostics reports the proof failure reason and affected field/meta/taxonomy names when derivable. It never exposes raw recovery snapshot values. Named ignored_runtime_meta_keys describe the fingerprint policy, not proof those flags caused a particular incident. After a save, recheck any blocked siblings instead of blindly rebuilding them.'
p.write_text(s[:m.start(1)]+json.dumps(d,ensure_ascii=False,indent=2)+s[m.end(1):],encoding='utf-8')
p=stage/'includes/class-rest-api.php';s=p.read_text(encoding='utf-8');old='One selected bulk approval can combine independent widget updates when exact predicted state is verified. Other edits or later requests require fresh evidence.';new='Independent supported edits can continue across Apply requests only when approved snapshot history proves every substantive change. WordPress ping/enclosure/trackback job flags do not invalidate content evidence. Inspect verify_change.evidence_check.diagnostics for blocked proposals; do not blindly recreate the queue.';assert old in s;p.write_text(s.replace(old,new),encoding='utf-8')
p=stage/'admin/views/pending.php';s=p.read_text(encoding='utf-8');old='The successfully applied changes remain saved. For state or configuration conflicts, have Claude read the current site and rebuild only the remaining proposals before approving again.';new='The successfully applied changes remain saved. Have Claude inspect verify_change evidence diagnostics for the failed IDs. Rebuild a proposal only after identifying a real conflict or missing recovery evidence.';assert old in s;p.write_text(s.replace(old,new),encoding='utf-8')
p=stage/'tests/approval-environment-test.php';s=p.read_text(encoding='utf-8').replace("define('CC_ASSISTANT_VERSION','0.89.4')","define('CC_ASSISTANT_VERSION','0.89.5')").replace("['Version']='0.89.4'","['Version']='0.89.5'");s=s.replace("$old=env_fixture();\ncheck(","$old=env_fixture();\ncheck(CC_Assistant_Evidence_Gate::environment_matches(CC_Assistant_Evidence_Gate::environment_hash('0.89.4')),'0.89.4 proposals survive this specific guard-only upgrade');\ncheck(",1);p.write_text(s,encoding='utf-8')
p=stage/'readme.txt';s=p.read_text(encoding='utf-8');s=s.replace('== Changelog ==','''== Changelog ==

= 0.89.5 =
* Fix published-post sibling failures caused by WordPress adding/removing _pingme, _encloseme and _trackbackme during save and cron. Exclude only these three job flags from content fingerprints.
* Match existing 0.89.3/0.89.4 recovery hashes without rewriting receipts; retain full guards for actual content, SEO, taxonomy, permalink and other metadata changes.
* Add precise continuation diagnostics to verify_change and stop instructing users to blindly requeue all failed proposals.
* Regression coverage executes installed WordPress publish and cron hooks, including legacy receipts and the secondary internal-save hash guard.
''',1);p.write_text(s,encoding='utf-8')
p=stage/'EVIDENCE.md';s=p.read_text(encoding='utf-8');s+='''

## 0.89.5: WordPress runtime job flags

WordPress `_publish_post_hook` adds `_pingme`, `_encloseme` and `_trackbackme` during published-post saves. Cron removes them. These exact three keys are scheduling markers and are excluded from content fingerprints. The actual `enclosure` metadata, similarly named keys, SEO metadata, authors, content, permalinks and taxonomy assignments remain protected. Existing 0.89.3/0.89.4 hashes are compared against bounded legacy job-flag variants without editing saved evidence. Unsupported legacy flag multiplicities may fail closed.

`verify_change.evidence_check.diagnostics` now identifies proof failure reasons and, where derivable, field/meta/taxonomy names. Comparisons are the predicted state after a named approved change versus current state; later approved changes may explain differences. A passing preflight does not execute WordPress save hooks or prove future batch success. Do not repeat a requeue cycle without inspecting the cause. Production checks after installation are still required.

Core references: https://developer.wordpress.org/reference/functions/_publish_post_hook/ and https://developer.wordpress.org/reference/functions/do_all_pingbacks/ .
''';p.write_text(s,encoding='utf-8')
shutil.copyfile(Path('D:/cc-assistant/reports/apply-recovery-0.89.4-2026-09-10/work/run-tests.py'),root/'run-tests.py')
print('Prepared 0.89.5 version, contract, diagnostics guidance and regression runner')

import re,json
from pathlib import Path
root=Path(__file__).resolve().parent; stage=root/'cc-assistant'
for relative,old,new in [
 ('cc-assistant.php','Version: 0.89.3','Version: 0.89.4'),
 ('cc-assistant.php',"define( 'CC_ASSISTANT_VERSION', '0.89.3' );","define( 'CC_ASSISTANT_VERSION', '0.89.4' );"),
 ('bin/mcp-server.php',"define( 'CC_MCP_VERSION', '0.89.3' );","define( 'CC_MCP_VERSION', '0.89.4' );"),
 ('readme.txt','Stable tag: 0.89.3','Stable tag: 0.89.4')]:
    p=stage/relative; s=p.read_text(encoding='utf-8'); assert s.count(old)==1 or s.count(new)==1,(relative,old); p.write_text(s.replace(old,new),encoding='utf-8')
p=stage/'bin/agent-contract.php'; text=p.read_text(encoding='utf-8'); m=re.search(r"CC_SHARED_JSON'\n(.*?)\nCC_SHARED_JSON",text,re.S); assert m
d=json.loads(m[1]); d['manifest']['version']='0.89.4'
d['manifest']['features']['pending_queue']['what']='Draft content and queued changes require administrator approval. Since 0.89.4, independent content, title, excerpt, author, supported SEO and Elementor widget/section-setting edits can survive separate Apply requests. The server must prove every intervening change from approved recovery snapshots and exact before/after fingerprints; overlapping edits, unexplained writes, missing history and structural changes still require fresh evidence. The tested 0.89.3 to 0.89.4 patch alone preserves environment compatibility only when all other fingerprinted settings and versions match. Do not rebuild or duplicate a valid remaining proposal merely because a sibling was approved. Use verify_change.evidence_check, inspect blocked targets and rebuild only genuine conflicts. Some tools write organizational metadata, research records or local files immediately; inspect each tool contract.'
d['tool_descriptions']['verify_change'] += ' The evidence_check field separately reports current, compatible_approved_changes (with the approved sibling IDs), blocked, or not_pending. It checks post/environment evidence, not clinical accuracy, permission, payload validation or rendered success. Lint passing alone does not mean a proposal can apply.'
p.write_text(text[:m.start(1)]+json.dumps(d,ensure_ascii=False,indent=2)+text[m.end(1):],encoding='utf-8')
p=stage/'readme.txt'; s=p.read_text(encoding='utf-8'); marker='== Changelog ==\n'
entry='''
= 0.89.4 =
* Fixed partial approvals making independent title, excerpt, author, SEO and Elementor section/widget proposals stale, including across separate Apply requests.
* Continuation requires an exact chain of approved version-2 recovery snapshots and non-overlapping targets. External edits, missing history, unexpected side effects and structural changes remain blocked.
* Preserved the tested 0.89.3-to-0.89.4 update path without ignoring changes to other plugins, themes, WordPress, Kit or SEO configuration. Existing compatible proposals retain their IDs.
* Added the actual evidence-check result and approved sibling IDs to verify_change, separate from lint.
* Added mixed-operation, cross-request, history integrity and patch-compatibility regression coverage.
'''
assert marker in s; p.write_text(s.replace(marker,marker+entry,1),encoding='utf-8')
p=stage/'EVIDENCE.md'; s=p.read_text(encoding='utf-8'); old='A sibling approval changes the page too: consolidate related work or read and rebuild remaining proposals after approval. There is no force override.'
new='Since 0.89.4, independent supported edits can continue after sibling approvals, including across requests, only when version-2 recovery snapshots prove an unbroken sequence of exact approved changes with no overlapping targets. The original observation stays intact. External changes, unexpected hook writes, missing history and structural changes require fresh proposals. verify_change reports the evidence check separately from lint. The tested 0.89.3-to-0.89.4 patch can preserve the prior environment fingerprint only when every other fingerprinted value still matches. There is no force override.'
assert old in s;p.write_text(s.replace(old,new),encoding='utf-8')
old='Approval requires the same page state and plugin/theme/kit/SEO environment: a sibling change invalidates the old plan too, so consolidate changes or re-read and re-plan after approval.'
new='Approval checks the page state and plugin/theme/kit/SEO environment. Since 0.89.4, independent supported changes may continue across sibling approvals only when complete recovery snapshots and exact hashes prove every intervening approved write. Overlaps and unexplained changes still require a fresh plan. Check verify_change.evidence_check before rebuilding remaining proposals; compatible proposals keep their IDs. Only the tested 0.89.3-to-0.89.4 patch is environment-compatible when all other fingerprinted settings and versions remain identical.'
for label,path in [('canonical',Path('D:/cc-assistant/CLAUDE.md')),('workspace',Path('C:/Users/sumit/Local Sites/plugintesting/app/public/CLAUDE.md'))]:
    if not path.exists():continue
    data=path.read_text(encoding='utf-8')
    if old not in data:continue
    (root/f'{label}-CLAUDE-before.md').write_bytes(path.read_bytes())
    (root/f'{label}-CLAUDE-after.md').write_text(data.replace(old,new),encoding='utf-8')
legacy=Path('D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-integrity.php').read_text(encoding='utf-8')
(root/'legacy-integrity.php').write_text(legacy.replace('class CC_Assistant_Integrity','class CC_Assistant_Legacy_Integrity'),encoding='utf-8')
print('Prepared 0.89.4 code, contract and documentation')

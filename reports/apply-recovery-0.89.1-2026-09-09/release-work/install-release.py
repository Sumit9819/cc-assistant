import hashlib,json,shutil,zipfile
from pathlib import Path
root=Path(__file__).parent.resolve()
stage=root/'cc-assistant'
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
active=Path('C:/Users/sumit/Local Sites/plugintesting/app/public/wp-content/plugins/cc-assistant/bin')
report=Path('D:/cc-assistant/reports/apply-recovery-0.89.1-2026-09-09')
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
baseline=json.loads((root/'baseline-hashes.json').read_text(encoding='utf-8'))
for name,sha in baseline.items():
    if not (source/name).is_file() or digest(source/name)!=sha:raise SystemExit('Source changed since staging: '+name)
tests=json.loads((root/'test-results.json').read_text(encoding='utf-8'))
assert len(tests['tests'])==47 and not tests['syntax'] and all(t['exit_code']==0 for t in tests['tests'])
smoke=json.loads((root/'mcp-smoke-results.json').read_text(encoding='utf-8'))
assert smoke['version']=='0.89.1' and smoke['tool_count']==176 and not smoke['stderr']
changed=[name for name,sha in baseline.items() if (stage/name).is_file() and digest(stage/name)!=sha]
added=['includes/class-approval-batch.php','tests/approval-batch-test.php']
archive=root/'cc-assistant-0.89.1.zip'
assert archive.is_file()
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    for name in z.namelist():assert z.read(name)==(root/name).read_bytes(),name
report.mkdir(parents=True,exist_ok=True)
for label,folder in [('source-before',source),('active-bridge-before',active)]:
    target=report/(label+'.zip')
    if target.exists():raise SystemExit('Backup exists; inspect before rerunning: '+str(target))
    with zipfile.ZipFile(target,'x',zipfile.ZIP_DEFLATED) as z:
        for p in folder.rglob('*'):
            if p.is_file() and '__pycache__' not in p.parts:z.write(p,p.relative_to(folder).as_posix())
for name in changed+added:
    dest=source/name;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(stage/name,dest)
    assert digest(dest)==digest(stage/name)
with zipfile.ZipFile(archive) as z:
    for name in z.namelist():
        rel=Path(name).relative_to('cc-assistant')
        assert z.read(name)==(source/rel).read_bytes(),str(rel)
        if rel.parts[0]=='bin':
            dest=active/Path(*rel.parts[1:]);dest.parent.mkdir(parents=True,exist_ok=True)
            shutil.copy2(source/rel,dest)
            assert digest(dest)==digest(source/rel)
release=source.parent/archive.name
assert not release.exists(),'Do not replace an existing release archive'
shutil.copy2(archive,release)
manifest={'version':'0.89.1','sha256':digest(release),'release':str(release),'changed':changed,'added':added,'php_syntax_files':tests['syntax_files'],'php_test_files':len(tests['tests']),'mcp_tool_count':smoke['tool_count'],'live_backend_observed':'0.87.0','live_writes_in_this_fix':False,'source_and_bridge_match_archive':True}
(report/'release-manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
for name in ['test-results.json','mcp-smoke-results.json','baseline-hashes.json']:shutil.copy2(root/name,report/name)
notes=f'''# CC Assistant 0.89.1: partial approval diagnosis and fix

Live checks on 9 September 2026 confirmed:

- #1724 applied: post 5525 now displays ER of White Rock as author. It remains a draft.
- #1725 applied: page 971 contains the corrected laboratory-results explanation.
- #1726, #1727, #1728 remain pending: the first widget edit changed the whole-post evidence hash, so the remaining same-page proposals failed.
- #1723 remains pending: its saved environment fingerprint differs from the live configuration. The old receipt stores a hash, so the exact changed component cannot be determined from that error alone.
- The live backend reports 0.87.0. Updating the local bridge does not install the WordPress backend. These approval errors are distinct from SiteGround CAPTCHA transport challenges.

## Implemented

The administrator bulk-approval path now creates a request-local record of the selected proposals. Initially current updates to separate Elementor widgets can proceed against the exact expected state left by earlier successful selected updates. The next whole-post fingerprint is predicted before writing and verified afterward. Nothing is trusted merely because it was written by this plugin.

Whole-post and environment checks remain active. Same-widget conflicts, initially stale evidence, changed proposals, unselected rows, external edits, unexpected hook side effects and failed recovery remain blocked. The exception is limited to independent widget updates in one bulk approval. Mixed content/metadata/publication or structural changes still require fresh proposals as necessary. The generated hero-image preload cache is excluded from the content fingerprint alongside other generated caches.

Each applied row retains its recovery snapshot. Partial approval messaging explains that successful edits remain saved. The shared Claude contract explains how to inspect history and rebuild only blocked proposals after deployment. This release does not publish, revert or force-apply live content.

## Validation

- {tests['syntax_files']} PHP files passed syntax checks.
- 47 PHP regression files passed, including 17 batch-specific assertions exercising real apply/evidence/persistence classes with WordPress and Elementor fixtures.
- Real stdio MCP initialization/tools listing passed: version 0.89.1, 176 unique tools.
- ZIP CRC and every shipped file match the canonical source; installed local bridge files match the ZIP.
- Live reads confirmed the partial result. The new approval path has not been executed on production; server installation and fresh proposal review remain necessary.

## Recovery after installation

1. Install cc-assistant-0.89.1.zip on erofwhiterock.com and confirm whoami reports backend 0.89.1.
2. Read the current pending history; preserve applied #1724 and #1725.
3. Run a fresh verified_page_audit for page 971, read its complete Elementor tree and current widget controls. Rebuild only these three remaining corrections, retaining the existing layout:
   - Widget 46afe405: title_text_a and title_text_b = Understanding Your Results.
   - Widget 77684669: description_text_b = Your physician considers laboratory results alongside your symptoms, medical history and examination. Ask what the findings mean and whether you need follow-up.
   - Widget 2a6ed6d9: description_text_b = Laboratory tests can provide information that helps your care team evaluate symptoms. Some findings may require additional tests or follow-up.
4. Read draft 5525 and refresh its content-workflow verification (workflow-87ad22b181a57ee39ecea94e57aec221). Rebuild publication proposal #1723 only when current checks allow it. Preserve ER of White Rock as author and practical SEO-focused presentation.
5. Review fresh proposals in WordPress. Rechecking stale originals alone will not repair them. Do not queue replacements before the plugin update, because deployment can invalidate their environment evidence again.

The lab wording was previously supported by https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/ . Recheck the actual current target before writing; this recovery note is not new page evidence.

Release: {release}
SHA256: {digest(release)}
Backups: source-before.zip and active-bridge-before.zip in this report directory.
'''
(report/'FIX-REPORT.md').write_text(notes,encoding='utf-8')
print(json.dumps(manifest,indent=2))

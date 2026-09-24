import hashlib,json,shutil,zipfile
from pathlib import Path
root=Path(__file__).parent.resolve();stage=root/'cc-assistant'
source=Path('D:/cc-assistant/wp-content/plugins/cc-assistant')
active=Path('C:/Users/sumit/Local Sites/plugintesting/app/public/wp-content/plugins/cc-assistant/bin')
report=Path('D:/cc-assistant/reports/implementation-0.89.2-2026-09-09')
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
baseline=json.loads((root/'baseline-hashes.json').read_text(encoding='utf-8'))
for name,sha in baseline.items():
    assert (source/name).is_file() and digest(source/name)==sha,'Concurrent source change: '+name
tests=json.loads((root/'test-results.json').read_text(encoding='utf-8'))
assert len(tests['tests'])==48 and not tests['syntax'] and all(t['exit_code']==0 for t in tests['tests'])
smoke=json.loads((root/'mcp-smoke-results.json').read_text(encoding='utf-8'))
assert smoke['version']=='0.89.2' and smoke['tool_count']==177 and not smoke['stderr']
changed=[name for name,sha in baseline.items() if (stage/name).is_file() and digest(stage/name)!=sha]
added=['includes/class-publication-recovery.php','tests/publication-recovery-test.php']
archive=root/'cc-assistant-0.89.2.zip';release=source.parent/archive.name
assert archive.is_file() and not release.exists()
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    for name in z.namelist():assert z.read(name)==(root/name).read_bytes(),name
report.mkdir(parents=True,exist_ok=True)
for label,folder in [('source-before',source),('active-bridge-before',active)]:
    target=report/(label+'.zip');assert not target.exists(),'Existing backup: '+str(target)
    with zipfile.ZipFile(target,'x',zipfile.ZIP_DEFLATED) as z:
        for p in folder.rglob('*'):
            if p.is_file() and '__pycache__' not in p.parts:z.write(p,p.relative_to(folder).as_posix())
for name in changed+added:
    dest=source/name;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(stage/name,dest)
with zipfile.ZipFile(archive) as z:
    for name in z.namelist():
        rel=Path(name).relative_to('cc-assistant');assert z.read(name)==(source/rel).read_bytes(),str(rel)
        if rel.parts[0]=='bin':
            dest=active/Path(*rel.parts[1:]);dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(source/rel,dest)
            assert digest(dest)==digest(source/rel)
shutil.copy2(archive,release)
manifest={'version':'0.89.2','sha256':digest(release),'release':str(release),'changed':changed,'added':added,'php_syntax_files':tests['syntax_files'],'php_test_files':48,'publication_recovery_assertions':29,'mcp_tool_count':177,'source_and_bridge_match_archive':True,'production_backend_last_observed':'0.89.1','live_publication_refreshed':False}
(report/'release-manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
for name in ['test-results.json','mcp-smoke-results.json','baseline-hashes.json']:shutil.copy2(root/name,report/name)
notes=f'''# Publication recovery and proposal refresh, 9 September 2026

## Live work completed

- Confirmed erofwhiterock.com backend version 0.89.1.
- Re-read the lab page, obtained a fresh HTTP 200 cache-miss server audit, and inspected its actual Elementor flip-box controls.
- Queued #1729, #1730 and #1731, replacing stale #1726, #1727 and #1728 respectively. Old copies are superseded. No live lab content was applied in this turn.
- Preserved applied #1724 (author) and #1725 (first lab correction).
- Refreshed the existing seven-source content scope from current source hashes and context, preserving audience/exclusions/questions and saving author ID 4, ER of White Rock. Prepared a current new_blog review workflow targeting existing draft 5525.
- Rechecked the existing draft and its primary-source links. It remains a draft with organization author ID 4. Legacy lint reports two optional improvements: no author bio and no featured image. Those do not certify or disqualify clinical accuracy or SEO performance.

## Missing feature found and implemented in 0.89.2

The existing API could create a draft with a publication proposal, but could not refresh publication for that existing draft. Recreating the article would produce a duplicate. The generic post_status metadata route does not run the same publication handler, so it was not used as a recovery workaround.

New tool: refresh_publish_proposal(pending_id, workflow_id, reason, dry_run=false), POST /cc-assistant/v1/content/publication/refresh.

The tool refreshes the original unreviewed publish_draft row and its server-owned evidence, preserving the post ID, author, body, title and pending ID. It requires the original bound actor, a fresh full draft observation and identity receipt, a current new_blog workflow explicitly targeting the existing draft, a valid author, supported Elementor data and a passing publication quality gate. No override argument is accepted. It records prior workflow/baseline history, shares the approval lock, conditionally persists the row and checks readback. Failed row persistence restores the prior workflow binding and reports failure. It never creates a post or publishes content.

Refreshed publication evidence also records the workflow source basis. Approval rechecks it, so related source/strategy changes still require a new review. The administrator remains the approver. whoami instructions now describe the independent-widget batch behavior and publication recovery accurately.

Workflow verification also rejects superseded publication rows and stale approval fingerprints. Previously it could verify that a draft/pending record existed while overlooking the environment/post mismatch that would prevent approval. Three additional verifier regression cases cover these conditions.

## Validation and limits

- {tests['syntax_files']} PHP syntax files; 48 PHP regression files passed.
- 29 recovery assertions cover no duplication/publication, preserved content and author, dry run, identity/ownership, stale and empty drafts, publication blockers, environment/source drift, superseded/reviewed rows, lock contention and failed storage.
- Tests use real recovery, evidence and SQLite state transitions with deterministic workflow/quality dependencies; they are not production execution.
- Real stdio initialization and tool schemas passed: 177 tools, version 0.89.2.
- ZIP CRC and every shipped byte match canonical source; local bridge matches the ZIP.
- Backend deployment of 0.89.2 and refresh of publication #1723 remain outstanding. Do not claim it is ready until the new endpoint succeeds against fresh live evidence.

## Minimal next sequence

1. Review and apply lab proposals #1729-#1731 together on the currently installed 0.89.1, before another plugin update changes their environment fingerprint.
2. Install cc-assistant-0.89.2.zip on the live site. Confirm backend version using whoami.
3. Read current scope and source pages again, refresh changed source evidence, then prepare content_workflow(objective=new_blog,post_ids=[5525]). Preserve the existing scope and author.
4. Run whoami and get_post(id=5525,slim=false), then refresh_publish_proposal(pending_id=1723,workflow_id=<fresh record ID>,reason=<current review>,dry_run=true). Inspect the result and repeat without dry_run only if valid.
5. Verify with verify_content_workflow and list_pending_changes. Return #1723 to the operator for approval. Do not recreate draft 5525 or bypass evidence checks.

If the plugin is updated before the lab corrections are applied, first re-read page 971 and replace only those still-pending widget proposals again. Never reapply #1724 or #1725 blindly.

Supporting lab source: https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/
Draft sources re-read: https://medlineplus.gov/ency/article/001927.htm ; https://medlineplus.gov/ency/patientinstructions/000501.htm ; https://psnet.ahrq.gov/primer/readmissions-and-adverse-events-after-discharge
Live evidence: D:/cc-assistant/reports/proposal-refresh-0.89.1-2026-09-09

Release: {release}
SHA256: {digest(release)}
Backups: source-before.zip and active-bridge-before.zip in this report directory.
'''
(report/'REPORT.md').write_text(notes,encoding='utf-8')
print(json.dumps(manifest,indent=2))

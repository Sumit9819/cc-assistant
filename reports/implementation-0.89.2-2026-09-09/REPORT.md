# Publication recovery and proposal refresh, 9 September 2026

## Latest live result

Later checks during this turn found the administrator had approved lab proposals #1729-#1731. The saved Elementor settings exactly match all three replacements, and earlier correction #1725 remains intact. The review inbox is empty. Original publication proposal #1723 was rejected; post 5525 is now published under ER of White Rock (ID 4), through a separate action rather than this agent publishing it.

Fresh audits returned HTTP 200 with cache misses for the lab page and https://erofwhiterock.com/what-to-tell-er-team/ . Each had 9 automated passes, 0 failures and 6 unknown checks. These are partial HTML checks, not browser, clinical, ranking or indexing certification. Strategy source fingerprints were refreshed again after the approvals and now match.

The live site remains on 0.89.1. The completed 0.89.2 update is available for future publication recovery and accurate workflow verification; the newly implemented recovery endpoint has not been executed on production. No publication requeue is needed for this now-published article.

## Earlier work in this turn

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

- 194 PHP syntax files; 48 PHP regression files passed.
- 29 recovery assertions cover no duplication/publication, preserved content and author, dry run, identity/ownership, stale and empty drafts, publication blockers, environment/source drift, superseded/reviewed rows, lock contention and failed storage.
- Tests use real recovery, evidence and SQLite state transitions with deterministic workflow/quality dependencies; they are not production execution.
- Real stdio initialization and tool schemas passed: 177 tools, version 0.89.2.
- ZIP CRC and every shipped byte match canonical source; local bridge matches the ZIP.
- Backend deployment of 0.89.2 remains optional next work. Publication #1723 no longer needs recovery: it was rejected and the article is now published, as confirmed in the latest live result above.

## Next action

Install cc-assistant-0.89.2.zip when ready to enable the new recovery tool and strengthened verification. The live queue is currently empty; do not recreate or republish article 5525. Future publication recovery must use new observations and a current workflow, never an old evidence receipt.

Supporting lab source: https://medlineplus.gov/lab-tests/how-to-understand-your-lab-results/
Draft sources re-read: https://medlineplus.gov/ency/article/001927.htm ; https://medlineplus.gov/ency/patientinstructions/000501.htm ; https://psnet.ahrq.gov/primer/readmissions-and-adverse-events-after-discharge
Live evidence: D:/cc-assistant/reports/proposal-refresh-0.89.1-2026-09-09

Release: D:\cc-assistant\wp-content\plugins\cc-assistant-0.89.2.zip
SHA256: be2b1229b153f989dc6034b4a4a5850dc433790768ba381076badc82b62d4ab8
Backups: source-before.zip and active-bridge-before.zip in this report directory.

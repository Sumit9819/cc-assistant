# CC Assistant 0.89.4 — approval recovery fix

The reported failures expose a gap in the earlier fix. Version 0.89.3 only permits independent `elementor_widget_update` rows to continue within one selected bulk approval. It does not cover Elementor section-content batches, post titles/excerpts/authors, SEO metadata or a later Apply request. Once an earlier content proposal succeeds, the remaining proposals still carry the original whole-post hash and are rejected as stale.

**0.89.4 fixes that behavior for supported independent edits without turning off the changed-content guard.** The release is built and installed in the local canonical source and local bridge. It has **not** been uploaded to the live site; the last live read still reported 0.89.3.

Package: [cc-assistant-0.89.4.zip](D:/cc-assistant/dist/cc-assistant-0.89.4.zip). An identical copy is at `D:/cc-assistant/wp-content/plugins/cc-assistant-0.89.4.zip`.

## What changed

- Approval now checks the persisted recovery history, so independent proposals can continue across separate clicks, page reloads and batches.
- The server starts from the proposal's original observation. For each intervening approved edit it loads the complete version-2 before-snapshot, replays the exact saved proposal in memory, and checks the predicted whole-post hash against the recorded after-hash. The chain must end at the exact current post hash.
- A remaining proposal cannot continue if an intervening edit touched the same field, SEO key or Elementor widget. Widget repeaters/settings are treated conservatively as one widget target.
- Unexplained external changes, unexpected hook writes, absent/mismatched snapshots, altered history, failed/rolled-back operations and unsupported change types do not receive an exception. History lookup is bounded to 200 recent approved rows; insufficient history requires a fresh proposal.
- Supported operations include classic body updates; title, excerpt and author fields; supported scalar SEO/image metadata; individual Elementor widget settings; and Elementor section-content batches. Structural changes, slug/publication transitions, arbitrary plugin settings and multi-post operations keep their stricter behavior.
- The specific tested **0.89.3 → 0.89.4** patch preserves environment compatibility only when every other fingerprinted value still matches. Updating Elementor, another plugin, WordPress, the theme, Kit or SEO configuration remains a conflict. This is not a blanket exemption for future updates.
- `verify_change` now returns `evidence_check`, separate from lint. It identifies current evidence, a compatible approved-change chain with its IDs, blocked evidence, or a non-pending row. This checks state evidence; it is not medical review, permission approval or rendered verification.
- Superseded proposals are explicitly blocked from application. Batch selection checks, human approval, snapshot creation, persistence validation and rollback protections remain enabled.
- Updated the shared Claude tool instructions and operator guidance so Claude does not blindly duplicate or rebuild valid remaining proposals after a sibling approval.

The original evidence is retained. No observation time was forged, no pending source hash was blindly replaced, and no database migration is required.

## This incident

The live site still has exactly the same **28 pending IDs across 18 targets**, with unchanged proposed payloads. Fresh reads of the full Elementor data, body, title, excerpt, author, slug and status match the previously prepared source plus the expected approved content changes on all 18 targets. Sampled live history directly confirms the imaging, World Cup and review-badge predecessors were approved.

| Remaining target | Proposal IDs |
|---|---|
| English imaging 852 | 1756–1758 |
| Spanish imaging 4745 | 1759–1761 |
| English/Spanish homepages 228 / 4687 | 1762–1763 |
| Ten neighborhood page authors | 1780–1789 |
| Review-policy author 5391 | 1792 |
| English World Cup title/excerpt/SEO 5092 | 1801–1804 |
| Spanish World Cup title/excerpt/SEO 5122 | 1806–1809 |
| English article-template mobile styling 4667 | 1812 |

The current live tool interface does not expose the full underlying recovery snapshot metadata. Consequently, the authoritative production snapshot-chain check is still an **after-install verification**. The local replay tests used the real saved proposal payloads and fresh content reads, with isolated WordPress fixtures; they are not a claim that the new backend already ran in production.

No live content was applied, reverted, rejected or requeued during this repair. The connected clinical review team and already-applied site corrections remain as observed.

## Validation

- **59 PHP regression test files passed.** The one initial test-environment failure was resolved by setting the required local WordPress root; its actual WordPress schema test then passed.
- **206 PHP files passed syntax checks.** The final package builder also checked shipping PHP syntax and BOMs.
- Mixed section/title/SEO/author and body/title/excerpt/SEO sequences passed through the real apply/recovery classes in an isolated SQLite-backed WordPress fixture.
- Positive tests cover separate approvals, multi-step history, application order differing from proposal order, bulk approvals and retained recovery snapshots.
- Negative tests cover overlapping widget/SEO edits, same-second external edits, unexpected side effects, environment changes, unselected/altered/superseded proposals, incomplete snapshots, altered history and recovery failures.
- **All 28 real incident payloads passed isolated continuation replay checks**, across the 18 target content fixtures.
- Fingerprints remain byte-compatible with the released 0.89.3 implementation in compatibility tests, including serialized metadata, escaping, Unicode and taxonomy ordering.
- Upgrade compatibility tests preserve this CC Assistant patch while rejecting changes to other plugins, activation, WordPress, theme, Kit, SEO configuration and site URL.
- MCP initialize/tool-list smoke test passed: version **0.89.4**, **177 tools**, valid object-shaped parameter schemas.
- ZIP CRC and byte-for-byte source comparison passed: **159 files**. No test fixtures, local credentials or production evidence files are included in the ZIP.

SHA-256: `9c81958fd038d5ee347bc8c0836b673973b27f37521fbde6b7da56983a1fd8c0`

## Installation and recovery

1. Upload the 0.89.4 ZIP through WordPress Plugins → Add New → Upload Plugin, and replace the installed CC Assistant version. Keep the 28 existing proposals.
2. Restart/reconnect the Claude MCP session to load the updated local bridge. Verify the live backend reports 0.89.4.
3. Run `verify_change` on the existing 28 IDs and inspect `evidence_check`. Compatible rows should identify the prior approved changes that explain their state. If any row remains blocked, inspect that exact row and its current evidence rather than deleting/recreating the whole batch.
4. Apply the verified existing proposals in the administrator inbox, then verify the actual titles, metadata, author output and mobile template rendering. The final apply always checks current state again, even after a successful preflight.

Complete this recovery before unrelated plugin/theme/SEO updates. No new blanket force-apply setting was added.

## Code and evidence

- [Approved-change proof](D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-approval-continuation.php:89)
- [Environment compatibility and evidence gate](D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-evidence-gate.php:52)
- [Stable snapshot/live hashing](D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-integrity.php:46)
- [Apply guard](D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-apply.php:94)
- [Verification output](D:/cc-assistant/wp-content/plugins/cc-assistant/includes/class-rest-pending.php:333)

`release-manifest.json` records exact changed files and package metadata. `source-changes.patch` records the code diff. `test-results.json` and the replay logs retain validation results. `initial-results.json`, `live-reads-results.json`, `live-source-comparison.json` and `final-live-checks-results.json` retain current-site observations. Original local source and bridge copies are preserved in `source-before.zip` and `active-bridge-before.zip`; operator-guidance backups are beside them. The private `work/` folder retains the staged source, scripts and incident fixtures.

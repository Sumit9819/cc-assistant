# CC Assistant 0.89.5: published-post approval repair

The previous requeue workaround did not solve the published-post save behavior. This release repairs a reproduced fingerprint bug instead of rebuilding the six remaining proposals.

## Reproduced cause

WordPress registers `_publish_post_hook` on `publish_post`. Updating an already-published post runs this hook. It adds `_encloseme`, optionally `_pingme`, and `_trackbackme` when trackbacks are waiting. Background handlers remove these metadata entries after processing. They are job markers, not changes to the title, excerpt, body or SEO settings.

CC Assistant 0.89.4 included these markers in its whole-post fingerprint. Its in-memory replay predicted only the approved title/body change. The actual WordPress save also created job markers, so the recorded after-hash did not match the prediction. Subsequent independent proposals failed. Cron could change the hash again by deleting the markers.

This explains the reproduced sequence: title succeeds, excerpt and two SEO fields fail. The earlier tests used WordPress storage doubles that did not execute the publish hook; their passing results did not cover this behavior. A current preflight also cannot prove what the next save hook will do. Requeuing eight proposals removed their initial conflict but did not repair that next-save problem.

The new regression executes the actual functions extracted from the installed WordPress source (`_publish_post_hook`, `do_all_pingbacks`, `do_all_enclosures`, `do_all_trackbacks`) with isolated storage and no network. Before the fix, it fails on the excerpt with the same stale-state error. After the fix, the title, excerpt and SEO sequence succeeds, including cron between approvals.

Core references: [publish hook](https://developer.wordpress.org/reference/functions/_publish_post_hook/), [pingback cleanup](https://developer.wordpress.org/reference/functions/do_all_pingbacks/), [WordPress changeset on repeat-save job markers](https://core.trac.wordpress.org/changeset/46426).

## Changes

- Exclude exactly `_pingme`, `_encloseme`, `_trackbackme` from new content fingerprints. Preserve all other existing fingerprint checks. Actual `enclosure` metadata, SEO fields, author, slug, body, categories, builder settings and similarly named keys remain protected.
- Compare existing 0.89.3/0.89.4 receipts and snapshot hashes against bounded legacy job-marker states without rewriting their hashes, timestamps, proposals or history. Unrecognized legacy combinations fail closed.
- Use the same compatibility check in the secondary internal-save guard and recovery guard, avoiding a second failure after the evidence gate passes.
- Preserve only the specifically tested 0.89.3/0.89.4 to 0.89.5 environment compatibility. Other plugin, theme, WordPress, kit, SEO or site-configuration changes still block approval.
- Add `verify_change.evidence_check.diagnostics`: overlap, missing matching snapshot, unexplained save effects, or later state changes. Where possible it identifies affected field/meta/taxonomy names without exposing their values. `legacy_runtime_hash_matches` identifies approved steps whose recorded hashes match only after accounting for the runtime flags.
- Correct the inbox and `whoami` instructions so they direct Claude to inspect diagnostics before rebuilding proposals.

No approval bypass, forced content overwrite, database migration or broad metadata-prefix exclusion was introduced.

## Current production state

The live site still reports **0.89.4**. Its active queue contains exactly **1814, 1815, 1816, 1818, 1819, 1820**. The English and Spanish title proposals **1813 and 1817 are approved**, and both saved titles match the intended corrections. No proposals were created, superseded, rejected or applied during this repair. The previously applied changes remain untouched by this work.

Production 0.89.4 does not expose the full snapshot comparison. The runtime bug is directly reproduced locally and matches this incident, but the six actual production recovery chains must be checked with the new diagnostics after installation before declaring them ready. A local fixture is not a production apply result.

## Validation

- 60 PHP regression test files pass, with no warnings.
- 207 PHP files pass syntax checks; shipping-file syntax checked again by the package builder.
- 67 assertions in the runtime regression path, including actual WordPress hook execution, cron cleanup, legacy fingerprints and the secondary hash guard.
- All six actual remaining proposal payloads pass local replay with the corresponding title change and WordPress runtime hooks. The exact English/Spanish strings are checked after application.
- Negative tests retain blocks for real concurrent changes, competing fields/widgets, arbitrary plugin save-hook side effects, missing or altered history, and changed environment.
- MCP initialize/tool-list: 0.89.5, 177 tools, valid parameter schemas.
- ZIP CRC and exact source-byte checks pass; 159 shipping files. No tests, production evidence, logs or live credentials are included. The existing example configuration template is included.

The WordPress hook test uses isolated storage; it does not bootstrap every production plugin or reproduce their individual hooks. Those remain subject to the production diagnostics and current-state guard.

## Install and complete

1. Upload `D:/cc-assistant/dist/cc-assistant-0.89.5.zip` and replace CC Assistant through the normal WordPress plugin update flow. Keep the six existing proposals.
2. Reconnect the MCP bridge so it loads 0.89.5. The local canonical source and configured bridge files are already updated.
3. Run `verify_change` on all six IDs. Check the actual production proof and `legacy_runtime_hash_matches`; do not substitute another blind requeue.
4. Apply only after that evidence check through the interactive administrator inbox. Then verify saved excerpts and served SEO titles/descriptions. Apply-time checks remain active.

The release repairs the reproduced cause; it cannot promise that future genuine edits or unrelated plugin side effects will never produce a conflict.

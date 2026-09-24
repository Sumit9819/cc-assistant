---
name: reference-lint-paragraph-length-li-edit-artifact
description: draft_patch_post_content can label paragraph_length "introduced" when a patch substantially rewrites an <li>, even when the edit shortens the text; run the no-list control before overriding
metadata:
  type: reference
---

`lint.introduced: ["paragraph_length"]` from `draft_patch_post_content` can be a
**false attribution when the patch substantially rewrites a list item** on a post
whose list was already over threshold. The linter appears to
measure a whole `<ul>`/`<ol>` as one paragraph, so a large replacement inside one
`<li>` can re-attribute the pre-existing fault to you.

Proved on post 6314 (irvingwellnessclinic) 2026-09-14 with a positive/negative
control pair:

| Pending | What it edited | Net chars | `introduced` |
|---|---|---|---|
| 1360 | CTA paragraph + H2 only, no `<li>` | -101 | `[]`, and paragraph_length listed **pre_existing** |
| 1359 | three `<li>` rewrites, no citations | **-148** | `["paragraph_length"]` |
| 1361 | two different `<li>` + CTA/H2 | +67 | `["paragraph_length"]` |

**The decisive fact: 1359 made the text 148 characters SHORTER and still reported
the failure as introduced.** Shortening text cannot introduce a length failure.

**Scope limit (verified, do not over-apply):** small edits to `<li>` do NOT trigger
it. Pending 1363 on post 6306 made four punctuation-level fixes inside `<li>`
elements and returned `introduced: []`, with paragraph_length correctly
`pre_existing`. The trigger is the SIZE of the replacement inside the block, which
matches the `bulk_add_ratio` mechanism: replace most of a block's text and the
differ reads the whole block as added. So always run the control before overriding;
never assume.

**How to apply:** when `paragraph_length` shows up as `introduced` on a patch that
substantially rewrites `<li>` elements, do NOT keep rewriting to appease it - that wastes several
queue/reject cycles (it cost four here). Run the control instead: queue only the
non-list patches. If that returns `introduced: []`, the fault is pre-existing and
`override_lint: true` is legitimate. Put the probe table in the `note` so the
operator can audit the override.

This is the same differ artifact as the `ai_tells` misattribution on pendings 1340
and 1353, where `bulk_add_ratio` showed ~9554 chars "added" for a +1058 net change.
Related: [[feedback_probe_discipline_positive_controls]],
[[reference_sibling_pending_stale_baseline]].

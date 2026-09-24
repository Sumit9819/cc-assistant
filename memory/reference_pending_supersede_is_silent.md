---
name: pending-supersede-is-silent
description: "cc-assistant: a 2nd post_content pending on the same post SILENTLY hides the 1st (superseded_by); fixed in v0.76.2 to report it"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-26T14:57:00.333Z
---

**Corrected 2026-08-26.** This file first blamed a missing drift guard in
`apply_post_content()`. That was WRONG — I proposed a fix before reading the queue
path. The real cause is auto-supersede.

## What actually happens

`CC_Assistant_Pending_Changes::mark_older_as_superseded()` hides an older pending on
the same target. `post_content_update` **is** on the supersede whitelist, and
`supersede_subkey()` returns `''` for it — so **post_id ALONE is the key**. Any two
content pendings on one post collide, even when they edit completely different modules.

The hidden row keeps `status = 'pending'`; the inbox filters on `superseded_by IS NULL`.
So it vanishes from view while still counting as pending. **A buried change is
indistinguishable from one that was never queued.**

## The incident

sids-ponds post 75 (About Us). #382 added a `title` to the map iframe (WCAG A), #383
changed a testimonial colour — two independent Divi module edits, queued minutes apart.
Queueing #383 silently set `superseded_by = 383` on #382. The operator approved
everything visible; the accessibility fix was simply gone. No error, no `cc_edits` row,
an inbox that read empty. Found only by diffing the live DOM against the stored module.

Misleading signals that cost time:
- `list_recent_edits` showed ONE new edit, which looks like a rejection.
- Both queue responses reported `current_length: 36803` — a hex-for-hex colour swap is
  the same byte count, so the collision was invisible. **Never use length as a freshness
  signal.**

## Fixed in v0.76.2

`mark_older_as_superseded()` now records what it hid; `superseded_notices( $pending_id )`
reads it back; all 7 queue-response sites attach a `superseded` array naming the hidden
id, its summary, and how to recover it. Semantics unchanged — superseding is deliberate
and correct for v2-of-an-intent. It just is not silent any more.
Test: `tests/supersede-notice-test.php` (13 assertions; negative control confirmed 6 fail
without the recording block while all behaviour assertions still pass).

## Operating rule (still worth following)

**Do not leave two `post_content` pendings open on one post.** Queue one, get it
approved, re-read the post, queue the next. Splitting work into separate pendings so the
operator can approve them independently is exactly what triggers this. On a Divi page
prefer ONE `draft_update_divi_modules` call with several `edits[]` — it accepts up to 30
and produces a single pending.

Related: [[reference_sibling_change_drift_guards]], [[feedback_verify_before_proposing_fix]],
[[feedback_no_guessing_epistemic_discipline]], [[reference_cc_assistant_lint_audit_quirks]].

## The OPPOSITE failure, same function: term_update NEVER supersedes (v0.88.0, 2026-09-09)

Above, supersede fires too eagerly on `post_content_update`. For `term_update` it
**never fires at all**, and `draft_update_term`'s own tool description asserts the
opposite: "Two queued updates for the same term auto-supersede (newest wins)."
That sentence is false. Do not rely on it.

Located exactly, in `includes/class-pending-changes.php`:

- `change_type_supports_supersede()` **does** list `term_update` (and `category_update`).
- `supersede_subkey()` **does** have a `term_update` case returning
  `'term:' . $decoded['term_id']`, with the comment *"All term updates share
  post_id=0, so the term is the natural [key]"*.
- But `mark_older_as_superseded()` opens with
  `if ( '' === $change_type || $post_id <= 0 ) { return 0; }`

Term pendings carry **post_id = 0**, so that guard returns before the sub-key is ever
consulted, and the candidate SQL keys on `post_id` anyway. The author wrote the term
sub-key *because* post_id is 0, then left a guard that rejects post_id 0. Dead path.
`category_update` almost certainly has the same shape; not verified.

**The consequence is the mirror of the incident above.** Instead of a change silently
vanishing, every revision stays visible and approvable. Queueing three passes at
term 23 on sids-ponds left 402, 403 and 404 all `status=pending`,
`superseded_by=null` - three competing versions of one category, two of them with
failing lint. Approving the wrong row applies inferior copy with no warning.

**How to apply.** After any repeated `draft_update_term` on the same term, call
`list_pending_changes` and **explicitly `reject_pending_change` the stale ids**;
`reject_pending_change` accepts `pending_ids` as an array and works over an
Application Password even though approve does not. Better: get the fields right in
one pass. The lint checks `keyword_coverage` on **both** `seo_title` and
`seo_description` against `success_metrics.target_query`, so make the target query a
phrase that appears in both fields, or omit `success_metrics` for a pure style fix.
Also note a superseding pending that **omits** a field does not inherit it, so resend
every field you still want. Related: [[index_cc_assistant_plugin_dev]].

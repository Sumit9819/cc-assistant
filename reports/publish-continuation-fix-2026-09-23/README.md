# Fix: publish_draft fails when approved together with its own SEO title/description

**Status:** proposed, NOT applied. The Claude Code auto-mode classifier blocked the edit because it touches the approval-safety code. Needs explicit operator approval.

## Symptom

Bulk-approving a new post's `publish_draft` together with its `rank_math_title` / `rank_math_description` pendings:

```
6 applied, 3 failed. #1273: Post 4772 changed after this proposal was observed.
Evidence check: unsupported_or_missing_evidence.
```

Recurring for months on every new post queued with SEO meta.

## Root cause (verified)

`includes/class-approval-continuation.php`, `scope()`:

```php
/** Deliberately excludes structural edits, publication, slugs and arbitrary plugin settings. */
```

`scope()` returns `null` for `publish_draft`, so `prove()` fails immediately at
`if ( ! $pid || ! $scope || ...) return $failure;` with reason `unsupported_or_missing_evidence`.
The meta siblings apply first (they change `rank_math_*` postmeta, which is part of the post
fingerprint), and the publish can never prove continuity. `verify_change(1273)` confirms:
`approved_ids: []`, siblings 1276/1279 `approved`.

The reverse order has the same gap: a published post breaks the chain for meta siblings,
because an approved `publish_draft` row is skipped as a history step.

## Proposed change (2 hunks)

```diff
-	/** Deliberately excludes structural edits, publication, slugs and arbitrary plugin settings. */
+	/** Deliberately excludes structural edits, slugs and arbitrary plugin settings. */
 	public static function scope( $row ) {
 		$p = json_decode( (string) ( $row->proposed_value ?? '' ), true );
 		if ( ! is_array( $p ) ) { return null; }
 		switch ( $row->change_type ?? '' ) {
+			case 'publish_draft':
+				// Publishing flips post_status only. Claiming post_name and
+				// post_content as well keeps a slug or body change between
+				// review and publish blocked; SEO meta, title, excerpt, author
+				// and featured image siblings may continue in either order.
+				return 'publish' === ( $p['post_status'] ?? '' ) ? array( 'field:post_status', 'field:post_name', 'field:post_content' ) : null;
```

```diff
 		switch ( $row->change_type ) {
+			// Any other publish side effect (generated slug, plugin meta) will
+			// not match the recorded after-hash, so the chain fails closed.
+			case 'publish_draft': $state['fields']['post_status'] = 'publish'; break;
 			case 'meta_update': $state['fields'][$p['field']] = (string) $p['value']; break;
```

## Why this does not weaken the guard

- Continuation still requires complete v2 recovery snapshots and exact before/after hashes for
  every intervening approved write (unchanged `prove()` logic).
- An approved **body** or **slug** change between review and publish still overlaps and still blocks.
- External (non-plugin) edits still break the hash chain and still block.
- A publish with any side effect beyond `post_status` (auto-generated slug, meta written by
  another plugin on the transition) fails to match `applied_post_hash` and fails closed.
- The publication workflow-basis check and the publish quality gate run unchanged.

## Tests to add (tests/approval-continuation-test.php)

1. Publish proposal observed on a draft; approve rank_math_title + rank_math_description;
   `prove(publish)` is safe with both IDs; apply succeeds.
2. Publish proposal; approve a post_content_update; publish is blocked (`overlapping_approved_change`).
3. Meta proposal observed on a draft; publish approved first (pre_apply snapshot + applied hash);
   `prove(meta)` is safe with the publish ID.
4. Publish that also changed post_name: chain fails closed.

Then: bump version (0.90.0 -> 0.90.1), run `tests/run.sh`, build zip, upload to every site,
restart MCP servers.

## Workaround until shipped

Both orders fail today, so either:
- Claude queues a new post's title/description only AFTER its publish is approved (fresh evidence
  against the published post), or
- after a failed batch, Claude runs `refresh_publish_proposal` on the failed publish pendings.

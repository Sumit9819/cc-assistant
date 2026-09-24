---
name: Custom Permalinks plugin overrides slug-based URLs (silent desync)
description: WordPress sites with the "Custom Permalinks" plugin active store a custom_permalink postmeta that overrides get_permalink() — slug changes via wp_update_post() do NOT auto-update it, leaving the stale custom URL in place forever
type: reference
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
modified: 2026-07-28T03:35:09.122Z
---
**The "Custom Permalinks" WordPress plugin** lets admins set a per-post URL that's independent of the post's slug (`post_name`). It does this by:

1. Storing the custom URL in postmeta key `custom_permalink`
2. Hooking a filter on `get_permalink()` / `_get_page_link()` / `post_type_link` that returns the stored value if non-empty

**The trap:** when you change `post_name` via `wp_update_post()`, the plugin does NOT auto-sync `custom_permalink`. So:
- DB has the new slug ✓
- Quick Edit shows the new slug ✓
- Frontend "View" link shows the OLD URL ✗
- `get_permalink()` returns the OLD URL ✗
- WordPress canonical_redirect tries to redirect new URL → old URL → infinite loop if a Rank Math redirect points old → new

**This defeats every conventional cache fix** — `clean_post_cache()`, Settings → Permalinks → Save, SiteGround Memcached flush, Update button in editor — because the issue isn't a cache, it's a *filter actively returning the stored stale value*.

**Symptoms that point to this trap:**
- Slug changed in admin, but `View` opens old URL
- `wp search-replace` / DB shows new slug, but front-end serves old URL
- Rank Math redirect from old → new URL creates infinite loop
- All other pages with same change behave correctly (only one or some pages have stale `custom_permalink`)

**Fix:** clear the postmeta:
```
delete_post_meta($post_id, 'custom_permalink');
// or via cc-assistant MCP:
draft_update_postmeta(post_id=X, meta_key="custom_permalink", value="")
```

After clearing, `get_permalink()` falls back to the natural slug-based URL.

**Detection:** check `wp_postmeta` for any rows with `meta_key='custom_permalink'`. Or grep active plugins for `custom-permalinks/` slug.

**WRITING THE POSTMETA DOES NOT RE-ROUTE THE URL** (erofirving post 3091, 2026-07-27). This is the most important operational fact, and it is easy to misdiagnose.

Symptoms observed: postmeta `custom_permalink` was rewritten twice via MCP `draft_update_postmeta` (first `blog/iv-for-dehydration`, then `blog/iv-for-dehydration/`). After both writes:
- `get_post` reported the NEW url — looked completely healthy
- the NEW url returned a hard 404 at origin (`render_probe`, `sg-f-cache: BYPASS`, so not cache)
- the OLD value's url (`/blog/skeletal-traction-2/`) STILL SERVED THE POST
- a sibling `/blog/` post on the same mechanism served 200 (so the mechanism itself was fine)
- no Rank Math redirect existed for either url (`draft_create_redirect _diag` → `like_match_count: 0`)

Diagnosis: `get_permalink()`'s filter reads the postmeta (so it shows the new value), but the plugin's **request router resolves from its own state**, which a direct postmeta write never refreshes. The two lookups disagree, so the post becomes reachable only at its old url.

**THE BOUNDARY: clearing works, setting does not.** Proven on erofirving 3081 the same day. Writing a NEW value leaves the router on the old one. Writing an EMPTY value removes the row, and the request then falls through to standard WordPress slug routing — the post comes back at its natural `/%postname%/` URL immediately.

So the remote fix for clone-residue 404s is **clear, never set** — but only for posts whose correct URL *is* their natural slug form. A post that legitimately needs a path prefix (e.g. `/blog/foo/`) cannot be repaired this way, because clearing drops it to the root URL and changes the indexed address; those still need an operator wp-admin save.

**How it detonates:** on a body save the post silently acquires a `custom_permalink` derived from ANOTHER post's path, de-duplicated with a `-N` suffix (`other-slug-2`, `-3`, …), and then 404s. `get_post` reports the correct URL right up until the save, so nothing visible predicts it. On erofirving a batch of seven one-character `tel:` fixes took six posts down at once; only the post whose slug the values were derived from survived. A "listed URL matches slug" pre-check does NOT detect this and must not be treated as a safety gate.

**ROOT CAUSE: per-request bleed during BULK APPROVE.** Not dormant per-post residue. The `-N` suffixes match *pending-id order exactly* — on erofirving 2026-07-27 the first pending's post kept its URL and each later one took that post's path with the next suffix (`-2`, `-3`, … `-7`). Custom Permalinks recomputes the override inside the save hooks `wp_update_post()` fires, and within a single PHP request the FIRST post's path leaks onto every subsequent post, de-duplicated with `-N`. This is why the same two posts (3978, 3979) broke in both the 2026-07-06 and 2026-07-27 incidents off *different* parents: whichever post is first in the batch becomes the donor.

**Fix (cc-assistant v0.52.1, VERIFIED 2026-07-28):** snapshot `custom_permalink` before `wp_update_post()` and restore it after — `capture_custom_permalink()` / `restore_custom_permalink()` in `class-apply.php`, applied in `apply_post_content()` and the publish-draft path. No-op when unchanged, so sites without the plugin pay nothing. Deliberate slug moves still go through `apply_post_field()`, which clears the override on purpose. Any plugin calling `wp_update_post()` in a loop on a Custom Permalinks site needs this guard.

Verified on erofirving production by deliberately reproducing the failure: two content pendings bulk-approved in one action (a `/blog/` post in the donor seat, a root post in the victim seat) — both survived at their correct URLs. Test design for re-verification elsewhere: donor seat first (lowest pending id), root-URL post as victim (recoverable by clearing the meta if the guard fails). Sites running < 0.52.1 must approve content pendings ONE AT A TIME.

**No postmeta READ path over MCP.** `draft_update_postmeta` with `dry_run` does not return the current value; it only surfaces as `current_value` on an already-QUEUED row in `list_pending_changes`. To map the affected population, an operator must run SQL directly:
```sql
SELECT p.ID, p.post_name, pm.meta_value
FROM wp_postmeta pm JOIN wp_posts p ON p.ID = pm.post_id
WHERE pm.meta_key = 'custom_permalink';
```

Corollary: the trailing slash was NOT the cause here. Do not repeat that guess.

Always verify a custom_permalink write with `render_probe` and check `http_code` — `get_post` alone will happily report a url that 404s.

**Caught via:** erofwhiterock.com slug rename of post 3457 (Grand Prairie → Lakewood) on 2026-05-05. 5 of 6 sister pages worked fine; 3457 had a stale custom_permalink that Custom Permalinks plugin had stored and never refreshed when slug was changed via cc-assistant's draft_update_post_meta flow. Spent ~30 mins debugging cache layers before realizing the plugin filter was the real cause.

**Plugin design lesson:** any code that changes `post_name` programmatically should also clear `custom_permalink` postmeta (or call the Custom Permalinks plugin's own update hook) to prevent desync. cc-assistant's `apply_post_field()` should do this automatically when `field === 'post_name'`.

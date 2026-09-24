---
name: Pre and post duplication checks on every body rewrite
description: Run find_duplicate_content BEFORE writing AND after the body is live. Pre alone is not enough.
type: feedback
originSessionId: 88bfa8c7-5ab5-46a6-b07e-6b3b181c37bf
---
Every body rewrite must include both a pre-check and a post-check for content duplication.

**Why:** User has called this out twice (once during 5261 Animal Bites work, once during 5550 Hypertension Crisis work). Pre-check alone catches existing cannibalization but cannot confirm whether a deliberate differentiation actually worked. The 5550 vs 6471 case is the canonical example: pre-check found cosine 0.52, the rewrite was specifically designed to differentiate intent (5550 = crisis/emergency, 6471 = BP education), and the only way to verify that worked is to re-run cosine after the new body is live.

**How to apply:**
1. **Pre-rewrite:** Always call `find_duplicate_content` with `post_ids=[target + 4-6 nearest cluster siblings]` at threshold 0.5. Document any pair >0.5 in the outline reasoning and design the rewrite to differentiate.
2. **Post-apply:** After the user confirms the body is applied, re-run the SAME `find_duplicate_content` call (same post_ids, same threshold). Compare new cosine vs pre-check baseline. Report the delta. If a deliberately-differentiated pair did not drop meaningfully (target: from >0.5 to <0.4), flag it and propose either (a) a v2 rewrite of the post just touched, or (b) a follow-up rewrite of the sibling to push it the other way.
3. **Standard verification bundle on post-apply:** cannibalization re-check + external citation HEAD checks (200 OK on .gov/.org links) + GSC URL Inspection request once 24h has passed.

**Plugin gap:** As of plugin v0.7.7, `class-apply.php` has no `do_action('cc_after_apply', ...)` hook. To make this automatic instead of operator-driven, the plugin would need: (a) action hook fired at end of every `apply_*` method, (b) a post-apply verification scheduler that runs cannibalization regression on the touched post + its cluster siblings, (c) a "Verification" column on the Pending Changes inbox showing the delta. Until that lands, treat post-check as a manual step the operator owns.

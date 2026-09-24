---
name: verify-gaps-against-post-inventory
description: NEVER declare a content gap based on tool output alone — always confirm by searching the actual post inventory first
metadata: 
  node_type: memory
  type: feedback
  originSessionId: cec5ab6a-29cc-4829-b135-c1a8eff355c6
---

When `topical_authority` returns `content_gaps` or `gsc_missing_mentions` returns "no page covers this query," that signal is derived from clustering math and post-text matching. It is NOT an authoritative inventory check. The cluster algorithm groups posts by TF-IDF similarity above a threshold; posts that don't match the threshold but DO cover the topic are missed.

**Hard rule:** before recommending "build a new post" or "no page covers this," run `list_posts(search=<topic terms>)` and verify directly. If a post exists, the correct recommendation is "optimize the existing post" not "build new."

**Why:** 2026-05-22 — claimed "iv drip for fatigue" had no covering page based on topical_authority's content_gaps output. Used that to recommend "BUILD NEW POST: IV Drip for Fatigue" as the highest-impact Phase 2 action. User caught it — post 9293-ish "How IV Therapy Fights Chronic Fatigue" already exists at `/how-iv-therapy-fights-chronic-fatigue/`. The real fix wasn't "build new" — it was "optimize the existing post that's already ranking but at the wrong position for fatigue queries." User said boss-level consequence: "my boss will scold me if he find it out."

**How to apply:**
1. For EVERY gap I claim (per-pillar or site-wide), run `list_posts(post_type="post", search=<keyword>)` first. Default `per_page` 20 is enough.
2. Cross-reference the result against the cluster's existing member list (some posts may be in the cluster but not flagged because they don't pass similarity threshold).
3. Categorize each gap as one of:
   - **Real gap** (zero existing posts) → propose new content
   - **Stale/under-optimized post** (post exists, not ranking) → propose optimization (meta + body update + internal links)
   - **Good coverage, CTR issue** (post exists, ranking but low CTR) → propose meta/snippet rewrite only
4. NEVER recommend "build new" without explicit list_posts verification.

Related: [[feedback_pre_post_duplication_check]] (run duplication check both before AND after writing); [[feedback_multi_signal_page_optimization]] (use all available signals, not one tool).

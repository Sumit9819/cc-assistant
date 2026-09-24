---
name: feedback-build-from-scratch-never-duplicate
description: "HARD RULE — never duplicate/clone a page (build_service_page or copy). Every page is built from scratch with its own analyzed section set, intent, and 2026-standard check. Cloning leaves source-condition residue."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: cf6b72ff-7286-4f35-bac6-cc72e684abbc
---

**NEVER duplicate or clone a page to make another — no matter what.** Not cross-vertical, not within-vertical (even ER condition → ER condition). Cloning (build_service_page mirror, or copying a page) ALWAYS leaves leftovers of the source page that have to be hunted down section by section, which is slow and error-prone and a YMYL hazard. User flagged this hard after the head-injury clone (5346, cloned from abdominal 5342) came out full of appendicitis/gallbladder/"1 in 11" residue.

**Why:** duplication carries the source's CONTENT, not just its shell. Token-swap replacements can't convert condition-specific medical bodies, so residue is guaranteed.

**How to apply — every new/rebuilt page:**
1. **Build from scratch** (author content fresh, e.g. via build_page_from_spec or container-by-container). Sampling another page's VISUAL STYLE/tokens (style_mirror_post_id, get_page_style_context) is fine — that reads design, not content. Copying content is not.
2. **Analyze the page on its own first** — do NOT force a template's section set. Decide what sections THIS condition/topic actually needs; it may need more or fewer than any sibling page (e.g. head injury needs concussion-vs-severe + return-to-activity + blood-thinner caution; abdominal needs a location table). 
3. **Check the reader task:** identify the main need and keep useful related explanations. Create a supporting article when it serves a distinct task; service pages can contain necessary educational context.
4. **Verify the result:** use current source facts, verified_page_audit, installed control schemas and actual comparable pages. Heuristic scores and fixed content quotas cannot establish ranking effects or justify a rewrite. Respect provider consent and verify saved/rendered output.

Supersedes the "within-vertical clones still fine" allowance in [[feedback_no_cross_vertical_clone]] and [[feedback_purge_source_vertical_on_clone]] — those are now moot; the answer is always build native. Root cause fixed: removed the "prefer build_service_page clone" recommendation from the erofwhiterock-design skill (§12, §19.7).

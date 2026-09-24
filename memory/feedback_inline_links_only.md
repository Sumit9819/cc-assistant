---
name: Inline-only internal links — no appendix/related sections
description: When fixing orphans or adding internal links on Elementor pages, place links inside existing sentences/words only. Do not append "Related guides" / "Read more" / "Related topics" sections.
type: feedback
originSessionId: ea116cb0-87a6-4912-b9d9-be19c7d2c2f5
---
When adding internal links to fix orphans or build link mesh on existing pages, links must be **inline** — woven into existing words or sentences. Do **not** create:

- Closing "Related guides" / "Related topics" / "Read more" sections
- Appendix bullet lists tacked onto closing CTA widgets
- Standalone heading sections like `<h3>Related X guides</h3>` followed by a `<ul>`
- Generic "Learn more about X" sentences appended just to host a link

If an orphan has no natural inline anchor on a candidate page, it's better to skip it on that page and find a more relevant home — don't manufacture an appendix to host it.

**Why:** User feedback after Batches 1-3 (2026-04-29). Appendix-style "Related" sections feel bolted-on and hurt reading flow. Inline links inside existing prose preserve the page's voice and read like editorial choice rather than SEO scaffolding.

**How to apply:**
- Use `draft_update_elementor_widget` to wrap an existing word/phrase in `<a>` tags
- If the perfect anchor doesn't exist, lightly extend an existing sentence with a natural follow-up clause that hosts the link (still inside the same paragraph, not a new heading)
- When no inline placement works, skip that orphan from the current page rather than appending a related-section

## 2026-09-09: rewriting a description SILENTLY DROPS the old inline links

New failure mode, caught after one had already gone live. When I replace a category
description wholesale, any inline links the legacy copy carried disappear with it, and
nothing warns me: the lint passes, the dry run passes, the pending diff shows a big
prose change and I skim past the anchors.

On sids-ponds term 62 (Soil), applied pending 408 dropped **three** product links
(triple-mix, top-soil, top-dressing). On term 61 (Aggregates) my draft dropped **four**
(crusher-run, pea-gravel, river-rock, limestone-screening) and I caught that one only
because the pending row's `current_value` happened to be in front of me. Fixed with
reject + requeue for 61, and a forward-fix pending for the already-applied 62. A revert
was wrong for 62, because reverting restores the legacy copy too.

**Do this before queueing any description replacement:**

```python
import re
old_links = re.findall(r'href=["\']([^"\']+)["\']', old_description)
products   = [l for l in old_links if '/product/' in l]   # the ones that matter
# every product link must appear in the new copy, or be deliberately dropped with a reason
```

`list_product_categories` returns the current description, so the check is free. Two
bonuses when you do it: the old links tell you **which products actually exist**, which
is inventory ground truth you cannot otherwise read (`product` is not in
`allowed_post_types`), and the product titles confirm the real names. That is how I
learned Sid's stocks Crusher Run and Pea Gravel as named SKUs, which changed the copy
from an abstract "clear stone vs crusher run" explanation into a link to the real thing.

Still verify each recovered URL before relinking: 200, no redirect, URL matches
([[feedback_curl_test_url_before_editing]]). Playwright at `~/.cc-assistant/wcag` does
this while the SiteGround challenge blocks curl.

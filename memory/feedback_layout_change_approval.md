---
name: page-layout-changes-require-explicit-user-approval-before-queueing
description: "Surgical edits (meta, alt text, inline citation wraps, single-line fixes) can be queued without asking. Anything that changes visible page layout — adding/removing widgets, restructuring sections, rewriting >25% of a widget, adding new H2/H3s — must be proposed first."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e4892fdb-799a-4212-b2a4-a5f1a588d362
---

When editing service pages or landing pages built with a page builder (Elementor, Divi, etc.), draw a hard line between surgical edits and layout-altering edits. Surgical edits do not disturb the visible page structure; layout edits do. The user needs design control over the latter.

**Surgical (safe to queue without asking):**
- Meta title / description / focus keyword updates
- Image alt-text additions
- Inline citation wraps on existing phrases (anchor tags around existing words, no copy added)
- Single-word or single-sentence factual corrections (e.g. compliance: "MD" → "APRN")
- Typo / punctuation fixes
- Pricing updates that replace numbers in existing fields

**LAYOUT changes (propose first, wait for approval):**
- Adding new Elementor widgets (new sections, new icon-boxes, new CTAs, new lists)
- Removing existing widgets
- Reordering sections / containers
- Replacing whole paragraphs or rewriting >25% of a text-editor widget
- Adding new H2 / H3 headings
- Restructuring service-card grids or pricing layouts
- Anything that visually moves elements on the page

**Format for proposing layout changes (use this template):**

```
## Proposed layout change on [page name] (post [ID])

**Sections affected:** [list specific widgets or container paths]
**Change type:** [add / remove / reorder / rewrite >25% / new H2/H3]
**What's being changed:**
- [bullet per concrete edit]
**Why:** [data lever, compliance, or content gap driving it]
**Estimated impact:** [GSC opportunity, EEAT lift, compliance fix, etc.]
**No changes queued yet — confirm to proceed.**
```

User confirms or redirects, THEN queue.

**Why:** 2026-05-12 — User stated: "before optimizing pages content that might change the layout, always tell me what you will change." Service pages have curated Elementor layouts that affect conversion paths, CTA placement, and brand presentation. The user wants design control over visible changes while leaving Claude free to handle the non-visible optimization work (meta tags, schema, alt text, inline citations).

**How to apply:**
1. Before any `draft_update_elementor_widget` that goes beyond inline citation wraps or alt-text fixes, classify the change. Surgical → queue. Layout → propose first.
2. When proposing, batch multiple related layout changes into one proposal so the user can evaluate the full scope at once.
3. After approval, queue + verify + report. Do not slip additional layout changes that weren't in the approved plan.
4. Cross-references [[feedback_inline_links_only]] (which already restricts to inline links, no new sections) and [[feedback_scope_one_thing_at_a_time]] (drive current scope to completion).

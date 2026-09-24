---
name: schema-parity-fix-no-text-wall
description: Schema parity fixes (making JSON-LD strings visible in DOM) must NOT be a single text-editor wall of bullets. Use scannable native widgets AND differentiate content per page.
metadata: 
  node_type: memory
  type: feedback
  originSessionId: b85a6e61-a8bf-48a2-9b0b-343339376ebe
---

When fixing `schema_not_in_dom` violations, never queue a single text-editor widget containing a wall of bullet points listing every schema string verbatim. The fix has to look like deliberate design, not a schema dump.

**Why:** The user explicitly flagged this on erofirving session 12 after I queued pendings 207 (home) and 208 (541 hub) — both with nearly identical text-editor blocks containing the same 9-item bullet list. Two problems: (1) wall-of-text is not a pro pattern (see [[feedback_deliberate_spacing_rhythm]] and [[feedback_no_html_widget_for_content]] for the broader principle), (2) reusing the same content on two pages on the same site creates internal duplicate-content signal.

**Scope of this rule (be careful not to over-apply):** This rule is specific to **schema parity fixes** — i.e. when the intent is to add visible text to mirror JSON-LD entities. It is NOT a blanket ban on text-editor widgets containing structured bullet lists. Pillar expansion sections (e.g. adding a "Common Causes" section to a service page) where a single text-editor with embedded `<h3>` + `<ul>` is the most natural fit are still legitimate. I over-applied this rule on erofirving session 12 attempt 2 by rejecting a valid Appendicitis pillar Section A (pending 209) thinking the same wall-of-text rule applied — the user clarified the feedback was scoped to 207+208 only.

**How to apply:**
1. Use **native scannable widgets**: icon-box rows (2-3 columns), icon-list, price-list, or a compact accordion. Not text-editor with one long `<ul>`.
2. **Differentiate content between sister pages**. Home gets a brief overview tone; service hub gets a deeper-scope tone. Different prose, different angle, different visual treatment.
3. **First consider smaller fixes** before adding new sections: can existing image-box / flip-box descriptions be edited to include the missing schema strings? Often the strings just need to match exactly what's already visible.
4. If a new section IS the right answer, sample existing icon-box / flip-box widgets on the page (`get_page_style_context` + sample the icon_box_sample) and clone that pattern.
5. The schema strings only need to appear once in visible DOM somewhere — they don't all need to live in the same widget. Spread them across the natural reading flow.

Related: [[feedback_match_page_style_not_defaults]], [[feedback_no_html_widget_for_content]], [[reference_cc_assistant_v0_31_audit_gaps]].

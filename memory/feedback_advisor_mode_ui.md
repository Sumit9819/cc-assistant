---
name: CC Assistant dashboard UI must read like an advisor, not a data dashboard
description: Each insight card leads with a status + headline + narrative + concrete action; data is supporting evidence, never the lead
type: feedback
---
Every dashboard card on the cc-assistant plugin must follow the **advisor pattern**, not the data-dashboard pattern. The user explicitly rejected raw-numbers-first UI in favor of "tell me what is happening, why it matters, and what to do."

**Card structure (mandatory):**
1. **Status badge** — color-coded chip with icon (`cc-status-good` / `attention` / `critical` / `info` / `pending`) and short label like "Quick wins available", "Needs attention", "Steady week"
2. **Headline** — one sentence in plain English describing what's happening (`<h3 class="cc-headline">`)
3. **Narrative** — 1–3 sentences explaining why it matters and what changed, with the most relevant data woven inline as `<em>` and `<strong>` (`<p class="cc-narrative">`)
4. **Evidence** — supporting data list inside `<div class="cc-evidence">` (only when it adds value beyond the narrative)
5. **Primary action** — single CTA button inside `<div class="cc-primary-action">`, ideally a `cc-copy-prompt` button that copies a ready-to-paste Claude Code prompt

**What NOT to do:**
- No tables-first cards. Tables are evidence, never the lead.
- No raw metric soup ("z-score = -2.4", "+47 clicks vs baseline" without context).
- No multi-section cards stacking unrelated lists. One narrative per card.
- No empty descriptive intro paragraphs ("Here are some pages..."). Lead with the verdict.

**Why:** The user runs multiple WP sites and uses this dashboard to make content decisions, not to admire numbers. Cards that just show data force the user to do the diagnostic work. Cards that lead with a narrative and a concrete CTA make the dashboard feel like a consultant.

**How to apply:** Whenever adding a new card or insight surface, ask: *Does this card answer "what's happening, why it matters, what should I do" within 5 seconds of looking at it?* If not, redesign before shipping. The CSS classes for the pattern are already in admin.css under "Advisor-style narrative cards".

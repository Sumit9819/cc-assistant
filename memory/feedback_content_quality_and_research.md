---
name: Content quality standard - original synthesis with external research
description: For pillar pages and substantive posts - pull data/concepts from authoritative external sources where the site lacks depth, synthesize in own voice, zero plagiarism
type: feedback
originSessionId: a48649d4-4501-45c1-b8a4-addd9908cee8
---
When drafting pillar pages and substantive content, the bar is "best quality" — not "minimum viable." This means active research from authoritative external sources where the site's existing content is thin, plus full synthesis in the site's own voice so the result is non-plagiaristic and EEAT-strong.

**Why:** user said 2026-05-11 "make sure everything is done in a best way, if there is not much data, you can take it from another site so we can make a best content, also make sure there is no plagiarism."

**How to apply:**
1. **Research before drafting.** For medical/health pillars use AHA, AAP, ACEP, ACC, CDC, NIH/PMC, MedlinePlus, AHRQ, FDA, professional society guidelines. For policy/law use Texas DSHS, TDI, CMS, federal acts. Read multiple sources, extract concepts and data points.
2. **Synthesize, never copy.** Take the substance (statistics, guideline thresholds, mechanism explanations) but write in this site's voice — natural, jargon-free, no em dashes, no AI-tell phrases. If a sentence reads like the source, rewrite it.
3. **Cite inline with links** to the actual source URL (CDC, MedlinePlus, etc.) using descriptive anchor text. Authority citations both teach the reader and signal EEAT to search engines.
4. **Run pre-write duplication check.** Per `feedback_pre_post_duplication_check.md` — call `find_duplicate_content` on the outline / topic against existing site content before submitting, so the new piece deliberately differs from anything already published.
5. **Post-write duplication check** after the body is live — compare cosine delta. If it accidentally clones an existing post, rewrite.
6. **No verbatim source language.** Spot-check 6-10 random sentences against the source URLs; if any is too close, rewrite. Especially watch for: definition sentences, list bullets, statistic phrasings, FAQ answers.
7. **Add original framing** the source doesn't have: local geography (White Rock Lake, Lake Highlands, Dallas neighborhoods), our facility's specific capabilities and limits (freestanding ER, what we stabilize-and-transfer), real scenarios that play out near the catchment.

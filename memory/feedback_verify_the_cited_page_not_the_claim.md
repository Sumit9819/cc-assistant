---
name: feedback_verify_the_cited_page_not_the_claim
description: A named citation in live copy is not evidence; open the cited URL and grep it. On erofirving 4 of 6 checked statistics failed, one contradicting its own linked source
metadata:
  type: feedback
---

**"According to MedlinePlus" is a claim about a source, not a source.** Before
reusing any statistic that is already live, fetch the page the copy links to and
grep it for the figure. Do not accept the attribution because it names an
allow-listed body.

**Why:** 2026-09-08, asked whether erofirving should carry statistic cards, I
checked all six cardable percentages against their own cited pages first.
**Four failed:**

| post | live claim | what the cited page actually says |
|---|---|---|
| 3886 | "About 87% of strokes are ischemic, per MedlinePlus" | medlineplus.gov/stroke.html: "about **80%** of strokes are ischemic" |
| 3937 | "According to MedlinePlus, almost 85% ... allergic to urushiol" | ency/article/000027.htm carries **no percentage at all** (NIEHS does state 85%) |
| 3897 | "As noted by MedlinePlus, up to 20% ... second reaction" | ency/article/000844.htm has no percentage and never says "biphasic" |
| 3081 | "Over 80% of emergency rooms in the U.S." | "according to industry surveys" names nobody |

Two more had no citation and no defensible figure: 3858's "80% of stones under
4mm pass" (NIH literature runs 38% to 95% depending on size and location) and
3813's "about 5% to 9% of the population" (real figure: 8.6% males, 6.7%
females, NIH StatPearls).

So the hit rate on live sourced-looking statistics was **1 in 6 clean**. The
figures were mostly real; the attributions were not. 3886 is the serious one:
the number contradicts the link printed next to it, on a stroke page.

**How to apply:**

- Fetch and grep, do not summarise. `curl -s URL | sed 's/<[^>]*>/ /g' | grep -oiE '[0-9]+\s?(%|percent)'` lists every percentage on the page, which both confirms a hit and proves the method works when it finds nothing. A model summary of a page can miss a figure; a grep for `[0-9]+%` returning empty is evidence.
- **cdc.gov and heart.org 403 curl and WebFetch.** WebFetch works on nih.gov, niehs.nih.gov, medlineplus.gov and ncbi.nlm.nih.gov. For CDC, WebFetch succeeds where curl 403s. Try both before concluding a figure is unsourceable.
- A figure inside the published range but at its extreme is **cherry-picked, not false**. 3897's "up to 20%" sits in a 0.4% to 23.3% range whose pooled estimate is 4.6%. Flag it for an editorial decision, do not silently pick a replacement.
- When the number and its citation disagree, the smallest correct fix is to match the number to the citation already present. Re-citing a different authority changes which body the page leans on and is the operator's call.
- Check the claim's WORDING against the source too, not just the digits. NIEHS says 85% "will have some adverse reaction ... during their lifetime", which is not the same as "is allergic to".

**This is why the statistics pass produced one card and three corrections.** The
verification was not overhead ahead of the real work; it was the work. Also see
the measured reason there are no charts on this site: 21 sentences site-wide
contain a percentage and **none is a series**, so any bar or line chart would
require inventing the other bars.

Related: [[feedback_precise_figures_only]],
[[feedback_content_quality_and_research]],
[[feedback_no_guessing_epistemic_discipline]],
[[feedback_dom_is_ground_truth_not_parsers]],
[[feedback_card_count_follows_the_post]].

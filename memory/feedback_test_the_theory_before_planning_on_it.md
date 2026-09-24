---
name: feedback-test-the-theory-before-planning-on-it
description: Before building a plan on a causal claim (links drive rank, more words drive rank), test it against the site's own data first - it is usually a few SQL lines and it often fails
metadata:
  type: feedback
---

Before committing a plan to a causal claim, test the claim against the site's own
data. On sids-ponds I published a five-phase plan built on "route internal authority
into starved categories" and "write the thin ones". Both were checkable in about ten
lines of Python against the GSC warehouse, and both failed:

- editorial inbound links vs position: 0 inbound n=9 mean pos **30.9**, 1+ inbound
  n=11 mean pos **33.9**. Direction is backwards. Holds controlling for >=250 words
  (29.8 vs 32.4).
- description length vs position: <250 words 34.5, >=250 words 31.6. A 3-position
  spread across a 13-fold word-count difference.

**Why:** these two claims are SEO folklore strong enough that I treated them as
priors rather than hypotheses, and spent a whole plan on them. The test cost minutes;
the plan cost a session and would have cost weeks of execution.

**How to apply:** when a plan's spine is "X causes rank", write the two-group split
(has-X vs not-X, mean position, impressions, clicks) BEFORE writing the plan. Report
the split in the deliverable so the reader can see the basis. If it fails, say so
plainly and rebuild - see [[feedback_no_guessing_epistemic_discipline]]. Related:
[[reference_links_summary_excludes_taxonomy_nodes]],
[[feedback_dom_is_ground_truth_not_parsers]], [[feedback_reverify_metrics_at_report_time]].

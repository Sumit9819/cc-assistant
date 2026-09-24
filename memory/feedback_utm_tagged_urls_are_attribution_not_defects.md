---
name: feedback_utm_tagged_urls_are_attribution_not_defects
description: A UTM-tagged URL indexed by Google is deliberate analytics attribution, never an SEO defect to fix - stripping it destroys the only source attribution the operator has
metadata:
  type: feedback
---

**HARD RULE. Operator has corrected this across multiple sessions and is fed up.**

When GSC reports a UTM-tagged URL as a separate page with large impressions
(e.g. `/?utm_source=gmb&utm_medium=gmb` on irvingwellnessclinic, 65,074 impressions
in Aug 2026 = 64% of all site impressions), that is **deliberate and correct**, not a
canonicalization defect, not duplicate content, and not "the biggest lever".

**Why:** the UTM on a Google Business Profile website link is the ONLY way to
distinguish visits arriving from the GBP listing from direct or organic homepage
traffic. Remove it and that attribution is gone permanently. The same applies to UTMs
on any listing, directory, ad, email or social profile link.

**How to handle it in analysis instead:**
- Treat `/` and `/?utm_source=...` as THE SAME PAGE and sum their rows.
- Never compute a standalone CTR verdict for the UTM row. Those impressions are
  largely GBP / local-pack surfaces where users call or get directions rather than
  click through, so low CTR there is not a title or copy failure.
- A correct self-referencing canonical on the clean URL is sufficient; verify it once
  and move on. Google indexing the tagged variant anyway is a reporting artifact.

**Do NOT:** propose stripping the UTM, propose a redirect or rel=canonical change to
collapse it, flag it as duplicate content, or surface it as a priority finding.

**Why:** it destroys real measurement the operator depends on, in exchange for a
cosmetic tidiness in a GSC page list that nobody acts on.

**How to apply:** if a UTM-tagged URL shows up in page-level GSC data, silently fold
it into its clean twin and analyse that. Say nothing about it.

Related: [[feedback_no_guessing_epistemic_discipline]], [[feedback_flag_root_cause]],
[[index_gsc_search_measurement]]

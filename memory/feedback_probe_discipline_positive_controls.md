---
name: feedback_probe_discipline_positive_controls
description: "HARD rule: my own probes are the #1 source of wrong findings — absence claims need a positive control, API names need a source grep, causes need change-and-remeasure"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-05T10:52:13.013Z
---

The user asked why my research is sometimes wrong ("some files were good but you missed it and gave wrong output"). Root-caused against real failures (2026-08-05 session): the errors were never missing knowledge — they were **unvalidated instruments** and **stopping at the first plausible answer**.

**Why:** Every fact I report passes through a probe I built (grep/regex/curl/string-match). A broken probe returns a clean-looking False, not an error — so I confidently report falsehoods. Documented cases: (1) "category descriptions never render" — my checker compared raw \r\n text against rendered HTML, unmatchable by construction; 47/48 were fine. (2) `wp_high_priority_element_flag` — a plausible-sounding WP filter that does not exist, cited from memory instead of grepping core. (3) CLS root cause — three wrong culprits accepted from correlated timing before change-and-remeasure found the truth.

**How to apply (all three are cheap and mandatory):**
1. **Positive control before any absence claim.** Before reporting "X is missing/broken," run the identical probe on a case where X is KNOWN present. If the probe fails there too, the probe is broken, not the site. Normalize both sides of any text comparison (strip tags, collapse whitespace, decode entities) before comparing stored vs rendered.
2. **Never cite an API/hook/filter/option name from memory.** WordPress core, the theme, and every plugin are on disk — grep the actual source for the symbol before prescribing it. If it can't be found in source, say so instead of shipping it.
3. **Causes require change-and-remeasure.** "Y loaded right before the problem" is correlation. The claim "X causes Y" is only allowed after removing/altering X and measuring Y again (route-interception injection on a live page counts and proved itself: 0.934→0.0004 before asking the operator to change anything).

Single samples never generalize: one page checked is a claim about one page. Sweeps are cheap; run them before saying "all/none/every".

See [[feedback_no_guessing_epistemic_discipline]], [[feedback_verify_before_proposing_fix]].

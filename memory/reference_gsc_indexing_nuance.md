---
name: GSC low-CTR data is incomplete on under-indexed sites
description: Don't use GSC zero-traction as a reason to skip meta optimization on a site where most pages are "discovered/crawled but not indexed"
type: reference
originSessionId: fb84c00f-69ad-437a-b116-a8c629075265
---
When prioritizing meta-rewrite work on a WordPress site by GSC data (low-CTR pages with high impressions), remember that pages with **zero GSC impressions may simply be unindexed**, not low-quality.

**Why this matters:** On sites where most blog posts are in the "discovered, currently not indexed" or "crawled, currently not indexed" state (Google Search Console > Pages > Why pages aren't indexed), GSC has no data to surface them. A `gsc_low_ctr` query returning only ~10 pages doesn't mean the other 50 are fine — it means Google hasn't indexed them yet.

**How to apply:** Don't argue against a site-wide meta sweep just because GSC traction is concentrated on a few pages. The user may want fresh meta on every post precisely *because* the unindexed pages need every help they can get to win indexing decisions and start ranking. Better meta titles + descriptions can influence whether Google decides to index a borderline page.

**For irvingwellnessclinic.com specifically (2026-05-07):** User confirmed "GSC only has few information because, other post or page are either discovered and not indexed or crawled but not indexed. So, go with high effort." This site needs comprehensive meta work even on posts with zero GSC traction.

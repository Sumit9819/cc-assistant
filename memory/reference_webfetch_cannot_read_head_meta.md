---
name: reference_webfetch_cannot_read_head_meta
description: WebFetch strips the HTML <head> — it can NEVER confirm a meta description exists; use pre_publish_check instead
metadata: 
  node_type: memory
  type: reference
  originSessionId: acae98d0-ca94-4d6e-907a-aa5917f30cc0
  modified: 2026-08-06T07:08:27.243Z
---

**WebFetch converts pages to markdown and DROPS the entire `<head>` section.** Ask it for a meta description and it will confidently answer "NONE" on every page in the world. The `<title>` survives (it becomes the document title), which makes the output look trustworthy — the title comes back correct while the description always reads absent.

**Never use WebFetch to check for a meta description, meta robots, canonical, or any other head tag.** Any "NONE" it returns is an artifact of the tool, not a fact about the site.

Positive control that proves it: asked WebFetch for the meta description on `https://rankmath.com/` (an SEO vendor's own homepage, which certainly has one). Reply: *"The web page content provided does not include the HTML head section."*

**What to use instead**

- `pre_publish_check(post_id)` returns a `meta_description` check with **pass/fail and character length**. This is the reliable presence test. It does NOT return the text.
- Do NOT try `curl` on SiteGround-hosted sites. It 403s even with a full browser UA — verified again 2026-08-06 on erofirving. See [[reference_siteground_ua]].
- `render_probe` reads the live DOM but its `extract` options are only schema, links, images, headings. **No meta.**

**PLUGIN GAP (worth building):** there is currently NO tool that returns the *text* of the current SEO title/description. That makes the standing rule in [[feedback_read_live_meta_before_rewriting]] impossible to follow properly. Fix: add `meta` to `render_probe`'s extract list, or add a `get_seo_meta(post_id)` read endpoint.

**What this cost, 2026-08-06 on erofirving:** I fetched five service pages, got "NONE" on all five, declared a site-wide missing-meta-description defect, and queued five replacements that the operator approved. At least one page (2453 pediatric) demonstrably already had a description — pending 680, 151 chars, approved 2026-07-08. I overwrote live content on a false premise and skipped the read-before-rewrite rule because I believed the tool. Same failure family as [[reference_gsc_inspect_410_vs_404]] and [[feedback_never_diagnose_from_average_position]]: trusting a summarised/lossy read as if it were ground truth. Old values are recoverable via the apply snapshots and `propose_revert`.

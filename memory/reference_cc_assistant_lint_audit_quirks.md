---
name: reference_cc_assistant_lint_audit_quirks
description: Rollup of cc-assistant lint/audit false positives and gaps — check here FIRST when a lint or audit result looks wrong
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-20T04:25:03.398Z
---

Known lint/audit quirks, false positives, and gaps. When a lint hard-blocks or an audit result contradicts the rendered page, check this list before believing either side.

- suspicious_chars soft-fails Spanish accents (á é í ó ú ñ). [[reference_suspicious_chars_lint_bug]]
- paragraph_length FPs on pre-existing bodies and blocks ALL edits to that post; diagnose with a 1-char dry_run. [[reference_paragraph_length_lint_preexisting]]
- win_audit content parser can return false zeros (score void); cross-check render_probe; competitor data still sound. [[reference_win_audit_content_parser_bug]]
- Lint doesn't decode entities: &#8212;/&mdash; bypass the em-dash lint. [[reference_lint_entity_decoding_gap]]
- Quote lint (v0.51.2): source link is HARD; applies to new quotes only. [[reference_quote_lint_v0_51_2]]
- wall_of_text lint (v0.27.6): ≥200-word text-editor without relief is refused. [[reference_wall_of_text_lint]]
- Design audit scans default state only — hover contrast issues invisible. [[reference_audit_hover_contrast_gap]]
- Section-width guard is not nesting-aware: refuses <700px regardless of depth. [[reference_section_width_guard_nesting]]
- schema_parity_check FPs: reports "not in DOM" when actually rendered; curl-verify before acting. [[reference_schema_parity_false_positives]]
- address_consistency mis-flagged "call 911" (fixed v0.35.4). [[reference_address_consistency_false_positive]]
- Elementor parser missed icon-box/accordion H3s before v0.10.22. [[reference_elementor_parser_widget_headings]]
- `keyword_coverage` (draft_update_seo_meta) matches **literal tokens and does not stem**. A description saying "lab testing" FAILS a `target_query` of "lab test irving texas"; "lab test" passes. Cost 3 requeues on erofirving 2026-08-06. Cheap diagnosis: `dry_run: true` before queueing any meta whose wording differs in form from the target query.
- `claim_removal_warnings` fire on single shared tokens (CARE, FAST, WALK, TESTING, LABORATORY) and routinely FP on **purely additive** changes. Adding a meta description to a page that had none triggered six warnings about unrelated MRI-restoration pendings. Check whether anything is actually being removed before acting; the warning text asserts removal without verifying it.
- `address_consistency` FPs on **highway route phrasing**: "I-635 North to Northwest Highway" reads as a malformed address vs the canonical (10705 Northwest Hwy). Hard-blocked the Mesquite location build 2026-08-14. Workaround: use road names without leading digits ("LBJ Freeway north to the Northwest Highway exit") instead of overriding.
- `find_duplicate_content` reads a **stale text mirror, not live Elementor data**: after widget-level edits (card swap + new accordion item on 5460/5462) the 0.5763 cosine was byte-identical pre- and post-apply, while render_probe diffs proved the content changed. Verify content changes via render_probe diff; treat dup scores on recently widget-edited pages as pre-edit values.
- `authority_density` (page_robustness_audit) counts links inconsistently on sub-1000-word pages: 0 citations = pass on one page (5460, 597w) but warn on another (5466, 607w), and it did not count an ACEP link inside an accordion on either. Warn-only; don't chase parity.
- **Empty inline alt beats the media-library alt** (mammoth 2026-08-25): `htmega-thumbgallery-addons` stores `slider_list[].slider_image.alt`, and when that is `""` the page emits `alt=""` even though the attachment's `_wp_attachment_image_alt` is set correctly. Elementor image widgets and the other galleries fall back to the attachment fine, so a batch can be half-effective and look done. ALWAYS confirm alt coverage by counting `alt=""` on rendered `uploads/` img tags, then fix at the widget level where the gallery blanks it.
- `audit_post_images` reads the **stale post_content mirror, not the rendered DOM** (mammoth 2026-08-20): after 32 attachment alts applied, it still reported every image `empty_alt` on all 7 dumper pages while render_probe showed 1/40 missing (the FB pixel). Same family as the `find_duplicate_content` stale-mirror bug. Verify alt coverage ONLY via render_probe; use audit_post_images just for discovering which files a page uses.

## helpful_content_score / site_quality_score scope bug (found 2026-08-20, sids-ponds)

`helpful_content_score` scores the **full rendered page, including header banner, footer
and popup chrome** — not the post's own content. Proven with a positive control: three
unrelated pages (`/outdoor-lighting-2/`, `/contact/`, `/delivery/`) each reported the
**same 3 exclamations**, all from site chrome:

- header promo bar "FREE SHIPPING CANADA-WIDE on orders $100 or more!"
- Brave popup "Shop Now!" ×2

Body-only counts were 0, 0 and 1. So the `quality` bucket penalty is uniform across every
page and is **unfixable by editing page content**. It also inflates `site_quality_score`,
which returned verdict=**fail** (36% weak) on a site whose pages are fine.

Three more rubric mismatches on the same scorer, on a **retail/e-commerce** site:

- `originality` counts only `.gov`/`.edu` as authority links → a Mississauga supply yard
  scores 0 citation density no matter how good the page is (same root cause as the
  13184 note: mississauga.ca and industry-association links don't register).
- `depth.question_h2s` counts only **H2** questions. The house FAQ pattern on this site
  uses **H3** cards, so a page with 7 real FAQs reports `question_h2s: 0`.
- `eeat` is 25 points gated on byline + Person schema + LinkedIn `sameAs` + credential.
  On sids-ponds that is blocked on a client decision, so every page loses ~20 points.

**Net:** ~40 of the ~46 points page 1129 "lost" were rubric artifacts.

### FIXED in v0.71.1 (2026-08-20)

All four are fixed in `includes/class-seo-tools.php`, covered by
`tests/scorer-scope-test.php` (36 assertions, full suite green):

1. `helpful_content_score` now calls the pre-existing
   `CC_Assistant_Pre_Publish::isolate_main_content()` before scoring text, with a
   fallback to the full document if isolation yields nothing. **JSON-LD still reads
   `$html_full`** — schema is in `<head>` and isolating it would zero every E-E-A-T
   signal. That split is the thing to preserve if this code is ever touched again.
2. Question headings are counted at **H2 and H3** (`array_merge( $q_h2, $q_h3 )`).
3. `landscape` is no longer a bare AI-tell — only `digital|evolving|changing|shifting|
   competitive|ever-changing|modern landscape` fire. A **landscape**-supply company was
   being penalised for naming its own product category.
4. `authority_hosts()` gained non-US government/academic TLDs (`.gc.ca`, `canada.ca`,
   `ontario.ca`, `.gov.uk`, `.nhs.uk`, `.ac.uk`, `.gov.au`, `.govt.nz`, `europa.eu` …)
   plus an **`ecommerce` overlay** (standards bodies + consumer regulators). `.gov`/`.edu`
   are US-only, so every non-US tenant scored citation density 0.00 regardless of sourcing.

Still true and NOT fixed: the `eeat` bucket is 25 points gated on byline + Person schema
+ LinkedIn `sameAs` + credential, which on many tenants is blocked on a client decision.
A page can be excellent and still cap around 75. Weigh that bucket accordingly.

## claim_removal_warnings fires on every hex colour (OPEN, found 2026-08-26)

The phrase-level claim guard I shipped in 0.72.x keeps single tokens as "distinctive"
when they are an acronym, contain a digit, or run 8+ chars. **Every hex colour contains
a digit**, so any change that swaps one trips the guard against any earlier change whose
body mentioned the same hex.

Live example: queueing `title_text_color` `#7A9C59` -> `#5F7A45` on sids-ponds post 75
warned against pending #175 on the bare token `7A9C59`. Verified false: no applied edit
on post 75 ever set that colour deliberately (`list_recent_edits` shows only 226/227/228/238
on that post, and 228 changed the job-title TEXT, not the colour). It is the stock design value.

Fix when touched: exclude `/^[0-9A-Fa-f]{3,8}$/` tokens, or require a non-hex neighbour
token, before treating a digit-bearing single as distinctive. Until then, colour edits
carry a warning that must be resolved by hand against `list_recent_edits`, not trusted.

Related: [[feedback_check_page_history_before_removing_claims]] — the guard is doing the
right JOB (check history before overwriting a deliberate value), it just has a noisy probe.

Related: [[feedback_no_guessing_epistemic_discipline]], [[feedback_probe_discipline_positive_controls]].

## deletion_ratio + redundancy false-fail on ANY multi-point edit (found 2026-08-28)

`class-pre-publish.php` builds a **single contiguous prefix/suffix diff** of the
plain-text body (~line 2543). `deletion_ratio` and `redundancy` both read off it.
So when an edit has **two or more separated change points**, the "changed region"
spans everything between them, `removed_len` balloons past the `is_surgical < 100`
guard, and both checks fail.

Proven on sids-ponds homepage (post 72, 52,798 chars) with two controls:
- module 19 inner_content alone -> `introduced: []`, clean.
- module 22 `set_attrs` alone, **net -1 character**, still failed BOTH. A one-char
  edit cannot legitimately fail a deletion ratio.

Two separated *attributes* inside one shortcode are enough to trigger it, because
`wp_strip_all_tags` does not strip shortcodes, so Divi attr values sit in the plain
text the differ compares.

**How to handle:** run each half as its own `dry_run` first. If each is clean alone
and only the combination fails, it is this artifact - queue combined with
`override_lint: true` and put both control results in the reasoning. Do NOT split
into two pendings to dodge it: on a site below v0.76.2 the second silently
supersedes the first (see [[reference_pending_supersede_is_silent]]).

**Real fix when the plugin is next touched:** compute a multi-region diff, or scope
these two checks to the changed modules rather than the whole body.

## `redundancy` false-positives on any MULTI-LOCATION patch set (v0.88.0, 2026-09-09)

`draft_patch_post_content` with two patches in **widely separated** parts of a post
reports `redundancy` as `introduced`, even when the net change is a dozen characters.

**Why**, from `class-pre-publish.php` around line 2630: the check computes the common
prefix and suffix between current and proposed plaintext, then sets
`added_text = substr(proposed_plain, prefix_len, len - prefix_len - suffix_len)` and
scores `bigram_overlap(added_text, prefix + suffix)`, failing above 0.35. With two
distant edits the prefix stops at the FIRST change and the suffix starts after the LAST
one, so `added_text` swallows **every untouched character between them**. On sids-ponds
post 13538 that was roughly 5,000 characters of unchanged body about dust extraction,
which of course shares bigrams with the rest of a page about dust extraction.

**The control that proves it:** a dry run carrying ONLY the two alt-attribute patches
added **13 characters** in total and still failed `redundancy`. Thirteen characters
cannot restate a page.

Note also that Divi shortcode ATTRIBUTES land in the plaintext corpus, because
`[et_pb_image alt="..."]` is not an HTML tag and survives `wp_strip_all_tags`. So alt
text is scored as body copy, and a descriptive alt naming the page's own subject looks
redundant by construction.

**How to apply.** Do not silently `override_lint`. Run the single-patch control, then
say in the pending `reasoning` that the flag is a false positive and show the control,
so the reviewer decides with the evidence. Do NOT split into two pendings to dodge it:
`post_content_update` supersedes on post_id alone, so the second silently hides the
first ([[reference_pending_supersede_is_silent]]). Suggested fix if the plugin is
touched: compute the diff as a set of hunks and score only the changed hunks, or skip
`redundancy` entirely when `removed_len < 100`, exactly as `deletion_ratio` already
does via its `$is_surgical` guard.

## `links_audit_post` HIDES product and category links (v0.88.0, 2026-09-09)

It reports only links whose target resolves to a **post/page ID**. Product pages and
`product_cat` archives have no post ID in its lookup, so they are absent from
`outbound_links` entirely, and the `outbound_count` undercounts.

I read that as a content defect and told the operator sids-ponds post 12049 had "zero
product or category links". **Wrong.** Its body already links three categories
(blades-saws-filters-maintenance, hand-tools, power-tools-and-construction). The tool
showed 4 outbound links; the body actually has 20 content-internal links per
`verified_page_audit`'s `links.inventory`.

The same reading WAS correct for post 13538, but only because I checked its raw body:
its lone product link genuinely pointed off-site to iqpowertools.com.

**How to apply.** Never conclude "no product links", "commercial dead end" or "orphan"
from `links_audit_post` alone. Cross-check against the raw body (`get_post`, then regex
`href="([^"]+)"`) or against `verified_page_audit`'s `links.inventory.content_internal`
count. Use `links_audit_post` for what it is good at: post-to-post relationships and
its deterministic anchor suggestions. This is the same failure shape as the Rank Math
template-variable mistake and the Elementor double-encoding one: a tool's silence is not
evidence of absence ([[feedback_dom_is_ground_truth_not_parsers]]).

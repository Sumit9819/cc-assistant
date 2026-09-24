---
name: reference-cc-assistant-v0763-p0-fixes
description: "v0.76.5 (2026-08-27) - CRITICAL isolate_main_content fix + multilingual silent-skip fix (scorer was reading related-post CARDS, word_count 21), utility-exclusion widened, warehouse impressions guard, playbook 2026.08.27.1"
metadata: 
  node_type: memory
  type: reference
  originSessionId: ccb07c44-9842-45b7-8c8f-6905631ad76f
  modified: 2026-08-27T06:38:21.256Z
---

**v0.76.5 built 2026-08-27 (supersedes 0.76.3/0.76.4 - deploy this one). Zip: `C:\Users\sumit\OneDrive\Desktop\cc-assistant-0.76.5.zip` (120 files). Both halves changed: upload the zip to each site AND restart the MCP (bin/mcp-server.php CC_MCP_VERSION bumped).**

Fixes the three P0 items from [[project-portfolio-instrumentation-gap]].

**1. `site_quality_score` utility exclusion (includes/class-seo-tools.php).** The v0.69 10-slug regex was store-shaped and scored medical boilerplate as content. Now:
- widened slug families: contact(-us), careers?, blog, book-appointment, shop, shipping-policy, terms-of-use/-of-service, (hipaa-)notice-of-privacy-practices, (web-)accessibility-statement, medical-disclaimer, disclaimer, billing-disclosures, surprise-billing-rights, editorial-policy, letter-of-protection, plus a trailing `(-\d+)?` because WP suffixes on slug collision (blog-2).
- `get_option('page_for_posts')` excludes the blog index functionally.
- **Translation inheritance**: any Polylang/WPML translation of an excluded page inherits the exclusion via `CC_Assistant_Multilingual::translations_of()`. This is why the Spanish slugs are NOT enumerated - the ES twins inherit from their EN parent. Bilingual sites were being penalised twice.
- every exclusion now carries a `reason` (designated_page | utility_slug | utility_translation).
- Deliberately NOT excluded, because these are genuine findings: About, Insurance & Billing, service pages, location pages.

**2. GSC impressions-bug guard (includes/class-gsc.php).** New `CC_Assistant_GSC::impressions_integrity( $window_start )` + `earliest_row_date()`. Constants IMPRESSION_BUG_START 2025-05-13 / IMPRESSION_BUG_END 2026-04-27. Returns null when the window starts after the fix, so it self-clears. Attached to `status()` (via earliest cached row) and `trends_summary()` (via prev_start - the choke point for decayed/rising/new_striking/lost/anomalies). Deliberately NOT attached to `aio_ctr_drop_alert`, which returns a bare list and would break callers if given an envelope; the playbook rule covers it instead.

**3. Playbook -> version 2026.08.27.1** (includes/class-seo-playbook.php). 6 corrections + 12 new rules. The two load-bearing corrections: the "~92% of AIO citations rank top-10" bridge fact is REFUTED (Ahrefs 863K SERPs: 37.9% top-10, 31% beyond 100), and the AI-conversion multiplier is removed as unsupported. Also fixed: FAQ carve-out, AIO CTR figure (-39.8% RCT), March churn framing + May 2026 core update, local-pack weights (reviews ~25%). Added: GSC impressions trap, AI-manipulation-is-spam, back-button hijack, review-solicitation bans + FTC, GBP services/hours as top movers, BrightLocal consumer thresholds, GPTBot vs OAI-SearchBot, Cloudflare 2026-09-15, QRG-has-no-2026-update guard.

## 4. CRITICAL: isolate_main_content read the WRONG ELEMENT (found while verifying #1)

`CC_Assistant_Pre_Publish::isolate_main_content()` took the **first** `<main>`/`<article>` match, non-greedy. On any page whose related-posts grid precedes or replaces the body wrapper, the first `<article>` is an Elementor teaser card (`class="elementor-post elementor-grid-item" role="listitem"`) holding a title and a link. **The scorer was scoring that card.**

Proof (2026-08-27): erofirving post 3800 "Pediatric Dehydration" returned `word_count: 21`, score 30/poor. Live DOM: erofirving posts have **ZERO `<main>` elements** and 6 `<article>` blocks, all `role="listitem"` related cards. Lufkin posts have exactly ONE `<main>` (single-post template 3393, whose `content_wrapper_html_tag` was fixed from `footer` to `main` earlier in the same session), so Lufkin was unaffected.

That asymmetry is what exposed it: after rescoring on 0.76.3, **erofirving / erofwhiterock / irvingwellnessclinic all flipped to verdict=FAIL (54% / 40% / 52% weak) while Lufkin IMPROVED** (mean 65 to 76.6, strong 6 to 62). A broken scorer moves every site the same way; a template-shape dependency does not. **Those three FAIL verdicts are an artifact - do NOT act on them.**

Fix: new private `largest_block( $html, $tag )` picks the LARGEST candidate and skips `role="listitem"`; `<main>` still beats `<article>`; the body-minus-chrome fallback also strips listitem articles (related cards are navigation to other pages and repeat site-wide, so they were dragging originality too).

**Consequence: every cached helpful_content_score written before 0.76.4 on a site whose posts lack `<main>` is wrong-low.** After deploying, rescore all four sites to exhaustion (`refresh_stale=true, rescore_cap=50`, repeat until `scored_by_other_build: 0`) before trusting any verdict.

**Lesson:** `isolate_main_content` silently depends on theme/template markup shape. Whenever scores move a lot with no content change, check the rendered DOM for `<main>` before believing the number. Related: [[feedback_dom_is_ground_truth_not_parsers]].


## 5. Translation inheritance was silently disabled (found while verifying #1)

The pass added in #1 was guarded by `class_exists( 'CC_Assistant_Multilingual' )`, but class-multilingual is only `require`d inside OTHER methods of class-seo-tools, so on most requests the class was NOT loaded, the guard was false, and the entire inheritance pass was skipped without a word. Measured on erofwhiterock 0.76.4: Career and Blog were excluded by slug while their Spanish twins Carreras (35), Articulos (40) and Politica de Cookies (41) stayed in the scored pool and filled bottom_n. Fixed by `require_once` before the `is_active()` check.

**Rule: never guard a feature with `class_exists()` on a class you are responsible for loading.** It converts a fatal into a silent no-op, and a silent no-op is indistinguishable from "the feature ran and found nothing". Load it, then branch on real state.

## VERIFIED RESULTS after 0.76.4 (rescored to scored_by_other_build: 0)

| Site | Before (0.76.1 cache) | After | Verdict |
|---|---|---|---|
| irvingwellnessclinic | mean 87.0, weak 5 | **mean 91.5, median 97, weak 2 (2.7%)** | pass |
| erofirving | mean 72.9, weak 6 | **mean 80.5, median 82, weak 2 (2.3%)** | pass |
| eroflufkin | mean 65.0, weak 28 | **mean 76.6, median 77, weak 22 (15.7%)** | warn (0.15 threshold) |
| erofwhiterock | mean 62.6 on 58% of site | needs 0.76.5 + full rescore | pending |

Proof of the isolation bug, same post before/after: erofirving 3800 `word_count 21 -> 1,989`, score `30 poor -> 80 strong`; 4345 `21 -> 1,232`, `40 weak -> 87 strong`. `has_byline` also flipped false->true, so E-E-A-T was under-scored too.

**Real remaining content work (no longer artifacts):**
- eroflufkin is the only site above the weak threshold. Genuine: Ins & Billing 47, two news/event posts 50, Home Page 53, Our Team 55, GI Care 57, and the service pages `list_posts` shows at word_count 0 (animal-bite 58, migraine 58, plus burn / allergic-reaction / UTI / flu / laceration).
- erofirving: About Us 53 and Ins & Billing 57 are the only sub-60 pages; location pages now score 67 (Grapevine), not 62.
- irvingwellnessclinic: Medical Weight Loss 50 and Botox 55 are the two weak pages - both money pages, worth attention.
- Portfolio-wide E-E-A-T ceiling: every post caps at 15/25 because schema Person resolves to the ORG name ("ER of Irving") with no sameAs/credential. Real named-provider bylines are the single biggest scoring lever left.


**Tests:** `tests/scorer-scope-test.php` gained BUG 6 (41 assertions) and BUG 7 (8 assertions reproducing the related-card page shape, an empty-<main> skip-link target, and main-beats-article) that reassemble the SHIPPED regex out of source (so a copy cannot drift) and assert both directions: boilerplate excluded, real content NOT excluded. `tests/warehouse-test.php` gained integrity assertions. `bash tests/run.sh` = 714 assertions, ALL TEST FILES PASS.

**After deploying:** the version bump invalidates every cached `_cc_helpful_content_score_ver`, so run `site_quality_score(refresh_stale=true, rescore_cap=50)` per site, REPEATEDLY until `scored_by_other_build: 0`. A single call leaves a mixed read that is not comparable to anything.

Prediction log (keep honest): I predicted the exclusion fix would clear Lufkin's `warn`. It did NOT. Fully rescored on 0.76.3, Lufkin landed at weak_share 0.157 against a 0.15 threshold - still `warn`, but now for real reasons (Ins &amp; Billing 47, two news posts 50, and the genuinely empty service pages animal-bite / migraine / burn / UTI / flu / laceration, which list_posts shows at word_count 0). Most of the mean improvement (65 to 76.6) came from RESCORING stale May scores, not from the exclusion. Attribute the two effects separately.

Gotcha hit while building: shell heredoc + Python `-c` mangled backslashes into the test file (wrote a literal CR inside a regex). Author PHP blocks into a scratchpad file with a quoted heredoc, then splice by LINE INDEX - no regex, no escaping. See [[feedback_bash_heredoc_and_pipe_gating]].

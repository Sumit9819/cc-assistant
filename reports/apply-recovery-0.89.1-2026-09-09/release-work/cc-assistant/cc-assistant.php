<?php
/**
 * Plugin Name: CC Assistant
 * Plugin URI: https://example.com/cc-assistant
 * Description: Connects WordPress to Claude Code for content analysis, optimization, and safe drafting on Elementor sites. Drafting and publication through human review.
 * Version: 0.89.1
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Sumit
 * License: GPL-2.0-or-later
 * Text Domain: cc-assistant
 *
 * == v0.47.0 changelog (front-end audit fixes: ARIA + clickjacking header) ==
 * - NEW: legacy Elementor accordion/toggle ARIA repair. Elementor's frontend
 *   JS stamps aria-selected onto .elementor-tab-title[role="button"], which
 *   the ARIA spec forbids on the button role (axe aria-allowed-attr, critical;
 *   also fails Lighthouse's agent-accessibility-tree audit). Served HTML is
 *   clean â€” the attribute is runtime-injected â€” so the repair is a tiny
 *   MutationObserver printed in wp_footer ONLY on pages that actually rendered
 *   a legacy accordion/toggle widget. aria-expanded (valid on button) still
 *   carries the state, so screen readers lose nothing. Toggle:
 *   cc_assistant_aria_fix_enabled (default on).
 * - NEW: X-Frame-Options: SAMEORIGIN on front-end responses when no upstream
 *   frame-control policy (XFO or CSP frame-ancestors) is already set.
 *   Same-origin iframes (Elementor editor preview, WP customizer) keep
 *   working. Toggle: cc_assistant_xfo_enabled (default on).
 *
 * == v0.46.0 changelog (Google Reviews widget â€” Phase 1) ==
 * - NEW: native Elementor "Google Reviews (CC)" widget + data layer
 *   (includes/class-reviews.php, includes/class-reviews-widget.php). Phase 1
 *   pulls up to 5 reviews from the Google Places API (server API key + Place ID;
 *   no OAuth, no legacy-API approval), caches them in the
 *   cc_assistant_reviews_cache option, and refreshes daily on cron â€” the public
 *   page render reads ONLY the cache (no external call on load). Configured on
 *   an isolated admin page (CC Assistant -> Reviews), not the shared settings.
 *   Widget has heading/columns/count + style controls and a "leave a review"
 *   CTA. Emits NO self-serving Review/aggregateRating JSON-LD by design.
 *   Phase 2 (later): swap the fetch to the Business Profile API v4 reviews
 *   endpoint for ALL reviews once Google grants legacy-API access â€” storage +
 *   widget unchanged; class-gbp.php OAuth (scope business.manage) already covers it.
 *
 * == v0.42.0 changelog (continuity: working state) ==
 * - NEW: structured working-state record (includes/class-working-state.php,
 *   option cc_assistant_working_state) so a brand-new chat resumes the
 *   current job exactly: active_task, status, target_post_ids, steps[],
 *   decisions[], constraints[], open_loops[], current_plan. Returned IN FULL
 *   by whoami.session_recap.working_state (unlike the tail-truncated notes).
 *   New MCP tools get_working_state / update_working_state; REST
 *   GET+POST /working-state. List fields support replace, add_<field>
 *   append-dedupe, and remove_open_loops. First deliverable of the
 *   continuity tier from the robustness audit.
 *
 * == v0.41.0 changelog (page-quality engine, part 3: win-audit) ==
 * - NEW: win_audit (includes/class-win-audit.php, POST /posts/{id}/win-audit,
 *   MCP tool win_audit). Scores a page against research-verified 2026 winning
 *   criteria AND against 2-5 fetched live competitor pages, returning a
 *   prioritized "to beat them, do X" action list (priority = weight x gap).
 *   10 dimensions, weights stored in the cc_assistant_win_audit_weights
 *   option (citation patterns drift, so the rubric is config not code):
 *   info_gain 18, intent_format 15, fanout_coverage 15, freshness 10,
 *   extractability 10, eeat 8, effort_media 8, title_ctr 8,
 *   internal_support 6, schema_hygiene 2. Evidence-grounded: fan-out coverage
 *   +161% AI-citation likelihood (Surfer 10k/Ahrefs 863k); info-gain helps
 *   non-#1 pages most (+115% at position 5, Princeton GEO); freshness is
 *   first-order for citation; schema verified NOT a citation lever (hygiene
 *   weight only). Refuted tactics (word-count padding, keyword density,
 *   FAQ-schema spam, date-bumping) are never scored or recommended.
 * - Competitor pages are fetched server-side (browser UA, SSRF guard: http(s)
 *   only, no IPs/local hosts/own host, 2MB cap, 6h transient cache); the
 *   caller supplies the URLs from its own web search (no SERP API).
 * - Skipped dimensions (no GSC data, no link graph) are excluded from the
 *   weighted total instead of dragging it â€” no silent false precision.
 * - NEW: cc_assistant_policy_no_bylines option â€” site-level policy flag
 *   (e.g. bylines banned pending physician consent) that reroutes the eeat
 *   dimension to citation-only scoring instead of permanently failing the
 *   site. First of the per-site policy flags the accuracy audit called for.
 * - NEW: CC_Assistant_Similarity::terms_for_text() â€” public text-input term
 *   extraction (same tokenizer pipeline) powering the competitor term-coverage
 *   diffs.
 *
 * == v0.40.0 changelog (page-quality engine, part 2: build-from-spec) ==
 * - NEW: build_page_from_spec macro (includes/class-build-from-spec.php, POST
 *   /draft/build-from-spec). Composes a COMPLETE new page from typed section
 *   templates (hero / text_image / card_grid / faq / cta_band) in one call â€”
 *   the from-scratch counterpart to build_service_page's clone. The 2026
 *   winning bar is enforced at build time, not advisory: at least one real
 *   image required (override_images for deliberate text-only), card grids past
 *   10 cards REQUIRE per-card group labels (renders titled sub-grids, killing
 *   the flat-22-card-wall failure), exactly one H1 (hero), FAQ renders as an
 *   AEO-extractable question-led accordion, section widths follow the global
 *   1300/850 rules, and the full per-widget lint + placeholder + duplicate-card
 *   checks run BEFORE any post is created. Styles are sampled from a
 *   style_mirror page on the same site (never hardcoded per tenant). Output is
 *   a draft + ONE publish_draft pending; the v0.39 publish gate re-checks at
 *   apply. dry_run=true validates the spec without side effects.
 * - CC_Assistant_Build_Service_Page::set_seo_field is now public (reused by
 *   the new macro instead of duplicating the SEO-plugin key map).
 *
 * == v0.39.0 changelog (page-quality engine, part 1: enforced gate) ==
 * - NEW: enforced page-quality gate. At publish (apply_publish_draft) the whole
 *   tree is checked and the publish is REFUSED on defects never acceptable live:
 *   placeholder / lorem text and duplicate-template cards (3+ siblings with
 *   identical copy â€” the exact "22 lorem-ipsum cards" failure that started this).
 *   Non-blocking WARNINGS surface for what loses in 2026 (no in-body images,
 *   ungrouped flat card lists > 10, thin content) so legitimate text-only pages
 *   still publish. Overridable per-post via _cc_publish_gate_override postmeta
 *   or the cc_assistant_publish_gate_blocking filter.
 * - NEW: page_quality_gate MCP tool + GET /posts/{id}/quality-gate â€” preview the
 *   gate (blocking + warnings + stats) without publishing, for pre-publish
 *   self-check and for page refresh / optimization.
 * - Broadened the placeholder lint (single source of truth shared by the
 *   queue-time lint AND the publish gate): now catches lorem variants without
 *   the literal "lorem ipsum" (dolor sit amet / consectetur adipiscing),
 *   "sample/placeholder text", "... goes here", "your text here".
 * - duplicate_card_text is now a hard violation at QUEUE time too (container_add
 *   + replace_section_content), so unfilled-template cards are refused before
 *   they reach a draft, not only at publish.
 *
 * == v0.38.1 changelog (QA bug-fix pass â€” adversarial review of v0.36-0.38) ==
 * - FIX (P0): get_post auto-slim guard was dead code. The route's registered
 *   'slim' default made get_param('slim') return false (never null), so the
 *   explicit-slim check was always true and the guard never fired â€” large
 *   pages still overflowed. Now detects explicit slim from the raw query/body
 *   params; oversized posts (body+tree > 60KB) auto-slim as intended.
 * - FIX (P0): GBP list_locations + fetch_performance rawurlencoded the resource
 *   name into the path ("accounts/123" -> "accounts%2F123"), which Google's
 *   gRPC path templates reject â€” so every location + performance call failed on
 *   first connect. Resource names now keep literal slashes.
 * - FIX (P1): GBP api_get() swallowed the token-refresh error on a 401, so a
 *   revoked/expired refresh token surfaced as a generic HTTP 401. It now
 *   returns the real refresh error (actionable "reconnect" message).
 * - FIX (P2): redirect dedup _diag now reports status_skipped per row so it
 *   mirrors the live filter (which skips trashed/inactive rows); removed an em
 *   dash from the get_post auto-slim notice; clamped GBP performance lookback
 *   to the ~18-month retention window (537 days).
 *
 * == v0.38.0 changelog ==
 * - NEW: Google Business Profile (GBP) integration. Settings â†’ Business Profile
 *   adds a BYO-OAuth connection (one client, scope business.manage) mirroring
 *   the Search Console flow: AES-GCM token storage, admin_init callback handler,
 *   redirect-URI override for local sites. Backs the My Business Account
 *   Management + Business Information (locations/categories) + Performance APIs.
 *   New MCP tools gbp_locations + gbp_performance expose the data to Claude
 *   (category audit + local-pack KPIs: impressions, calls, directions, website
 *   clicks). Reviews are intentionally NOT included â€” they need Google's
 *   separate legacy-API access approval; the class is structured to add them
 *   once granted. New class includes/class-gbp.php; REST /gbp/locations +
 *   /gbp/performance.
 *
 * == v0.37.0 changelog ==
 * - GLOBAL / multi-tenant correctness (the plugin runs on many sites; removed
 *   single-site hardcoding that skewed every non-Irving tenant):
 *     (1) class-similarity tokenizer hardcoded 'irving','tx','texas' and
 *         stripped them from EVERY site's corpus â€” now derives brand/city
 *         tokens per-site from the site title + an optional cc_assistant_geo_terms
 *         option. Silently corrects clustering / cannibalization / topical
 *         authority on all non-Irving sites.
 *     (2) E-E-A-T + helpful-content citation scoring used a hardcoded medical
 *         authority-host list in two places â€” now a single industry-aware
 *         authority_hosts() helper (universal .gov/.edu/who.int + per-vertical
 *         overlay for healthcare / finance / legal / home-services + the
 *         cc_assistant_authority_hosts option + filter).
 *     (3) byline detection regex was English/ASCII-only (failed on accented
 *         names and Spanish) â€” now Unicode-aware (\p{Lu}/\p{L}) + matches
 *         Spanish bylines (por / escrito por / revisado por).
 *     (4) FAQ-title dedup normalizer hardcoded domain words
 *         (tx/texas/hormones/therapy) â€” now industry-neutral fillers only.
 * - APPROVAL UX (safety): the pending-inbox diff renderer returned
 *   "(no preview available)" for ~8 change types, INCLUDING the v0.36
 *   delete_redirect / untrash_redirect tools â€” reviewers were approving raw
 *   JSON. Added redirect diff cards (source -> destination, HTTP code, +
 *   destructive-delete warning) and a generic key/value fallback so EVERY
 *   change type shows a readable preview.
 * - PERFORMANCE: schema-cleanup logging performed a transient + activity-log
 *   DB INSERT on the public wp_head render path (forbidden) â€” now a no-op on
 *   the front end (also fixes the blank-log argument-order bug).
 * - TOKEN BLOAT: get_post now auto-slims oversized posts (body+tree > 60KB,
 *   filterable via cc_assistant_get_post_auto_slim_bytes) instead of
 *   overflowing the caller, with a notice on how to fetch specific widgets /
 *   sections. An explicit slim=false is still honored.
 *
 * == v0.36.0 changelog ==
 * - FIX (redirect ghost-409): draft_create_redirect dedup matched TRASHED /
 *   inactive Rank Math rows (the redirections table soft-deletes), so a
 *   redirect the operator deleted kept blocking creation forever and no cache
 *   flush could clear it. find_existing_redirect_for_source() now skips
 *   inactive/trashed rows and only a genuinely-active match blocks.
 * - NEW: draft_delete_redirect + draft_untrash_redirect tools. The assistant
 *   can now remove a stale / wrong / looping redirect or restore a trashed one
 *   (Rank Math DB::delete / change_status, with cache purge) through the
 *   pending inbox â€” previously every removal/restore was a manual Rank Math
 *   step. Both fetch the redirect by id first so the reviewer sees the source,
 *   destination, and current status in the pending row.
 * - FIX (content-gate hole): replace_section_content ran ZERO content lint, so
 *   it could launder em dashes / AI-tells / style-guide violations / placeholder
 *   (lorem-ipsum) text / walls of text / a wrong street address / hospital
 *   comparisons onto a page that every other queue path refused. It now runs
 *   the same per-widget lint as container_add; bypass with override_lint=true.
 * - SECURITY: removed the bundled tmp/ scratch directory â€” it contained a
 *   web-reachable dump_posts.php with no ABSPATH guard plus raw post-content
 *   dumps. Already excluded from the build zip; now removed from installs.
 * - CHORE: synced version across the plugin header, readme Stable tag (was
 *   0.35.0), and the MCP serverInfo version (was stuck at 0.1.0 since the
 *   scaffold); Tested up to 7.0.
 *
 * == v0.35.5 changelog ==
 * - FIX (safety): draft_create_redirect now refuses to queue a reverse-loop.
 *   When an existing redirect (Rank Math or a pending) already sends B -> A,
 *   queueing A -> B would make both URLs loop forever (ERR_TOO_MANY_REDIRECTS).
 *   The prior same-source dedup missed this because the conflicting rule's
 *   source is the DESTINATION. We now look up any redirect on the destination
 *   and refuse (422) if it points back to the source, naming the conflicting
 *   id. Motivated by the 2026-06-02 incident where a pre-existing canonical->
 *   old redirect + a new old->canonical redirect looped the Emergency Services
 *   pages. (Self-loop A->A was already guarded since v0.10.)
 *
 * == v0.35.4 changelog ==
 * - FIX: "unclustered pages" advisor priority + admin stat + bulk-assign scan
 *   counted utility pages (home, blog index, contact, about, careers, and
 *   legal/policy pages in EN + ES) that can never belong to a topic cluster.
 *   This produced a misleading backlog (e.g. "36 unclustered" when every
 *   content page was already clustered). unclustered_post_ids() now excludes
 *   the front/posts page + a filterable slug denylist
 *   (cc_assistant_non_clusterable_slugs). Single source of truth â€” advisor,
 *   clusters admin view, and bulk_propose_cluster_assignments all use it.
 * - FIX: address_consistency_check false-positive on emergency-instruction copy.
 *   The street-address regex matched "call 911 right away. Do not drive" as the
 *   address "911 right away. Do not drive" (the '.' in the gap class let it span
 *   a sentence and /i let the verb "drive" match the "Drive" street suffix),
 *   hard-blocking any edit to a widget containing that text. Tightened the regex:
 *   the street name must start capitalized ([A-Z]), the gap no longer crosses
 *   '.', and the street-type suffix is now case-sensitive. Genuinely-wrong
 *   (capitalized) addresses are still caught; lowercase-only addresses are no
 *   longer flagged (correct bias for a hard-blocking lint). Single source of
 *   truth â€” rest-api / build-service-page call address_consistency_check().
 *
 * == v0.35.3 changelog ==
 * - FIX (blocker): removed the 'cc-assistant-mcp/1.0' bot token from every
 *   outbound HTTP User-Agent. SiteGround's WAF began hard-blocking that UA with
 *   a 403 on ALL endpoints (incl. /wp-json/), which took the MCP server fully
 *   offline and silently broke server-side self-fetches (post-apply audit,
 *   pre-publish URL curl-test). All outbound calls now use a plain browser UA
 *   via CC_ASSISTANT_HTTP_UA (pre-define in wp-config.php to override); the MCP
 *   server uses cc_mcp_user_agent() / CC_MCP_USER_AGENT env override. Auth is
 *   still fully enforced by the application password.
 *
 * == v0.35.0 changelog ==
 * - Fixed wall_of_text false-positive on multi-widget container_add (per-widget
 *   evaluation; em_dashes/ai_tells/style_guide still aggregated)
 * - Fixed race-safety TZ mismatch (false-refused stale pendings on non-UTC hosts)
 *   â€” guard now compares post_modified_gmt against GMT-stamped marker
 * - Added override_lint param to draft_add_elementor_container MCP schema
 * - Depth-aware section_width guard (no longer refuses legitimate
 *   nested-column headings; only enforces 700-900px floor at depth <= 1)
 * - Word-boundary AI-tells regex (no longer false-positives on "navigate",
 *   "leveraged", "delved", etc.)
 * - Removed "navigate" from AI-tells list (too high false-positive rate in
 *   medical/finance/legal copy)
 * - SSL verify_peer enabled by default in MCP HTTP client; env-gate with
 *   TLS verification: ON by default for non-local hosts (v0.60.1); CC_MCP_VERIFY_TLS=0/1 overrides
 * - Bumped Requires PHP to 8.0 (matches str_contains() and WordPress 7.0)
 * - NEW: address_consistency_check (refuses queued widgets whose street-address
 *   patterns don't match the cc_assistant_facility_address site option)
 * - NEW: palette_compliance_check (warns on off-brand hex outside Kit globals
 *   + filterable semantic-grey allow-list)
 * - NEW: hospital_comparison_ban (hard-fails ER-site copy with hospital-ED
 *   comparison phrasing â€” operator policy, anti-competitor positioning)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CC_ASSISTANT_VERSION', '0.89.1' );
define( 'CC_ASSISTANT_FILE', __FILE__ );
define( 'CC_ASSISTANT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CC_ASSISTANT_URL', plugin_dir_url( __FILE__ ) );
define( 'CC_ASSISTANT_BASENAME', plugin_basename( __FILE__ ) );
require_once CC_ASSISTANT_DIR . 'includes/class-access.php';
CC_Assistant_Access::register();

/**
 * Outbound HTTP User-Agent for all server-side fetches (post-apply self-audit,
 * pre-publish URL curl-test, external research/industry detection).
 *
 * Must NOT carry a bot/automation token: SiteGround's WAF (and similar managed
 * firewalls) 403-block automation UAs, which silently breaks self-fetches of
 * SG-hosted pages. A plain browser UA is used; pre-define CC_ASSISTANT_HTTP_UA
 * in wp-config.php if a host needs a specific value.
 */
if ( ! defined( 'CC_ASSISTANT_HTTP_UA' ) ) {
	define( 'CC_ASSISTANT_HTTP_UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36' );
}

/**
 * Performance design.
 *
 * On front-end requests, this file is the only plugin file that gets loaded.
 * Class files are required only inside the hook callbacks that need them, so
 * a public page view pays no cost for admin or REST class compilation.
 */

register_activation_hook(
	__FILE__,
	function () {
		require_once CC_ASSISTANT_DIR . 'includes/class-activator.php';
		CC_Assistant_Activator::activate();
	}
);

/**
 * Microsoft Clarity heatmap tracker (v0.55, opt-in). Loads Clarity's async
 * tag ONLY when a project ID is saved in settings â€” used to validate the
 * attention_audit's predicted heatmap against real visitor behavior. The
 * option autoloads (settings save keeps the default), so this read costs
 * zero extra queries on the render path. Admins are excluded so operator
 * sessions never pollute the heatmap data.
 */
add_action(
	'wp_head',
	function () {
		$clarity_id = get_option( 'cc_assistant_clarity_project_id', '' );
		if ( ! is_string( $clarity_id ) || '' === $clarity_id || ! preg_match( '/^[a-z0-9]+$/i', $clarity_id ) ) {
			return;
		}
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<script type="text/javascript">(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","%s");</script>' . "\n",
			esc_js( $clarity_id )
		);
	},
	40
);

/**
 * Front-end JSON-LD injection for EmergencyService schemas approved through
 * the plugin. Reads the `_cc_emergency_service_schema` postmeta on singular
 * pages only and emits one <script type="application/ld+json"> block per
 * post. Pays nothing on pages without the postmeta. Runs at priority 30
 * to land after Rank Math's own schemas (priority 10-20) so reading order
 * in view-source matches plugin precedence.
 */
add_action(
	'wp_head',
	function () {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}
		if ( ! get_option( 'cc_assistant_emergency_schema_enabled', true ) ) {
			return;
		}
		$raw = get_post_meta( $post_id, '_cc_emergency_service_schema', true );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return;
		}
		// Validate but DON'T re-encode â€” PHP's float-to-string round-trip
		// blows up coordinate precision (31.3021503 -> 31.30215030000000098...).
		// We trust whatever the apply path stored (it was wp_json_encoded then
		// wp_slashed; get_post_meta has already unslashed it).
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['@type'] ) ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\" data-cc-assistant=\"emergency-service\">\n";
		echo $raw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD body, structure validated above.
		echo "\n</script>\n";
	},
	30
);

/**
 * v0.35.1 â€” Generic per-post JSON-LD injection. Reads the
 * `_cc_assistant_schema_jsonld` postmeta (the one CC_Assistant_Schema_Generator
 * writes when propose_schema is run) and emits it as a <script> block.
 *
 * Prior to 0.35.1 the postmeta was written but no front-end reader existed,
 * so generated schema sat in the DB invisible to Google. This closes the gap
 * so propose_schema actually ships rich-results to the page.
 *
 * Runs at priority 35 (after _cc_emergency_service_schema above) so the
 * organization-level schema renders first, page-specific schema after.
 *
 * Pays nothing on pages without the postmeta. Validates the JSON before
 * echoing so a malformed value can't break <head>.
 */
add_action(
	'wp_head',
	function () {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}
		if ( ! get_option( 'cc_assistant_page_schema_enabled', true ) ) {
			return;
		}
		$raw = get_post_meta( $post_id, '_cc_assistant_schema_jsonld', true );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return;
		}
		// Validate structure without re-encoding. The value may be either a
		// single entity (top-level @context + @type) or an @graph bundle.
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return;
		}
		$is_graph        = isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] );
		$has_single_type = ! empty( $decoded['@type'] );
		if ( ! $is_graph && ! $has_single_type ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\" data-cc-assistant=\"page-jsonld\">\n";
		echo $raw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD body, structure validated above.
		echo "\n</script>\n";
	},
	35
);

/**
 * v0.45.2 â€” Auto Table of Contents on single posts. Reads H2/H3 from the
 * rendered content, adds anchor ids, and injects a clickable TOC at the top of
 * the article (working smooth-scroll anchors). Replaces flaky third-party TOC
 * plugins. Toggle: option cc_assistant_toc_enabled (default on); per-post
 * opt-out via postmeta _cc_assistant_toc_disabled.
 */
add_filter(
	'the_content',
	function ( $content ) {
		// Cheap gate FIRST, so the TOC class is never even loaded in the admin,
		// in feeds, on non-singular views, in secondary loops, or when the
		// feature is off (which is the default). Near-zero overhead otherwise.
		if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! get_option( 'cc_assistant_toc_enabled', false ) ) {
			return $content;
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-toc.php';
		return CC_Assistant_TOC::filter_content( $content );
	},
	20
);

/**
 * 0.27.1 Schema policy enforcement. Strips the two violations Google
 * explicitly forbids: (1) aggregateRating / review on the site's own
 * Organization / LocalBusiness / MedicalClinic family entity (self-reviews),
 * and (2) cross-page service @graph entries + areaServed + hasCredential
 * lists on non-homepage URLs (schema-DOM parity policy).
 *
 * Hooks Rank Math (primary) and Yoast SEO (fallback). Class is require'd
 * inside each filter closure so a Rank Math-less / Yoast-less site pays
 * nothing on page render. Toggle off site-wide via the option
 * cc_assistant_schema_cleanup_enabled = false.
 */
add_action(
	'plugins_loaded',
	function () {
		// 0.27.1 hook strategy: Rank Math fires TWO filters in its JSON-LD
		// pipeline (see class-jsonld.php::json_ld()):
		//   1. rank_math/json_ld         (line 149) â€” assembly stage
		//   2. rank_math/schema/validated_data (line 153) â€” final stage, after
		//      validate_schema() drops empties; THIS is the last hook before
		//      Rank Math echoes the <script> tag. The validated_data filter
		//      sees the fully-assembled graph including entities added via
		//      the action-style path (e.g. class-frontend.php's add_schema
		//      reading from DB::get_schemas() postmeta).
		//
		// Hook BOTH for belt-and-suspenders: the json_ld hook catches the
		// common case cheaply, and validated_data catches anything that slips
		// through (Schema Builder templates, custom @graph injections, etc.).
		// The cleanup is idempotent so double-application is safe.
		add_filter(
			'rank_math/json_ld',
			function ( $data ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-schema-cleanup.php';
				return CC_Assistant_Schema_Cleanup::filter_jsonld( $data );
			},
			999,
			1
		);
		add_filter(
			'rank_math/schema/validated_data',
			function ( $data ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-schema-cleanup.php';
				return CC_Assistant_Schema_Cleanup::filter_jsonld( $data );
			},
			999,
			1
		);
		add_filter(
			'wpseo_schema_graph',
			function ( $graph ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-schema-cleanup.php';
				return CC_Assistant_Schema_Cleanup::filter_yoast_graph( $graph );
			},
			999,
			1
		);
	},
	5
);

/**
 * v0.47.0 â€” Legacy Elementor accordion/toggle ARIA repair. Elementor's
 * frontend JS stamps aria-selected="true|false" onto legacy Accordion and
 * Toggle tab titles, but those titles carry role="button" â€” and aria-selected
 * is not a supported attribute on the button role (axe: aria-allowed-attr,
 * critical impact; also fails Lighthouse's agent-accessibility-tree audit).
 * The served HTML is clean; the attribute appears only at runtime, so the
 * repair is runtime too: a MutationObserver strips aria-selected from
 * .elementor-tab-title[role="button"] the moment Elementor sets it. State is
 * still announced via aria-expanded â€” which Elementor also sets and which IS
 * valid on a button â€” so assistive tech loses nothing. Titles with
 * role="tab" (legacy Tabs widget) are deliberately left alone: aria-selected
 * is correct there.
 *
 * Pays nothing on pages without a legacy accordion/toggle: the wp_footer
 * printer is registered only when elementor/frontend/widget/before_render saw
 * one of those widget types during this render. Toggle off site-wide via
 * option cc_assistant_aria_fix_enabled = false. Retire this block if a future
 * Elementor release stops emitting aria-selected on role="button".
 */
add_action(
	'elementor/frontend/widget/before_render',
	function ( $widget ) {
		static $hooked = false;
		if ( $hooked || is_admin() ) {
			return;
		}
		if ( ! in_array( $widget->get_name(), array( 'accordion', 'toggle' ), true ) ) {
			return;
		}
		if ( ! get_option( 'cc_assistant_aria_fix_enabled', true ) ) {
			return;
		}
		$hooked = true;
		add_action(
			'wp_footer',
			function () {
				?>
<script id="cc-assistant-aria-fix">(function(){"use strict";var fix=function(el){if(el&&el.getAttribute("role")==="button"&&el.hasAttribute("aria-selected")){el.removeAttribute("aria-selected");}};var sweep=function(){var list=document.querySelectorAll(".elementor-tab-title[aria-selected]");for(var i=0;i<list.length;i++){fix(list[i]);}};try{new MutationObserver(function(ms){for(var i=0;i<ms.length;i++){var t=ms[i].target;if(t&&t.classList&&t.classList.contains("elementor-tab-title")){fix(t);}}}).observe(document.documentElement,{subtree:true,attributes:true,attributeFilter:["aria-selected"]});}catch(e){}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",sweep);}else{sweep();}})();</script>
				<?php
			},
			99
		);
	}
);

/**
 * v0.48.0 â€” Hero background preload. Elementor hero background images are the
 * LCP element on most built pages but are discovered late (CSS background).
 * The class emits an early <link rel="preload" fetchpriority="high"> from a
 * postmeta-cached URL; the front-end cost is one primed meta read. Toggle:
 * cc_assistant_hero_preload_enabled (default true).
 */
require_once CC_ASSISTANT_DIR . 'includes/class-hero-preload.php';
CC_Assistant_Hero_Preload::init();

/**
 * v0.71.0 — Shared URL -> post resolution. Loaded unconditionally because
 * every analytics surface needs it and it registers no hooks (pure statics,
 * lazy DB read on first use, so the front-end cost is zero). Replaces bare
 * url_to_postid() calls that silently dropped redirected URLs and the
 * impressions attached to them.
 */
require_once CC_ASSISTANT_DIR . 'includes/class-url-resolver.php';

/**
 * v0.75.0 — Page Facts: the rendered-page ground truth store. Hooks save_post
 * (deferred capture), a nightly sweep, and after-apply verification. Registers
 * actions only; nothing runs on a front-end page view.
 */
require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
CC_Assistant_Page_Facts::init();

/**
 * v0.47.0 â€” Clickjacking mitigation header. Emits X-Frame-Options: SAMEORIGIN
 * on front-end responses when nothing upstream (host config, security plugin,
 * another WP plugin) has already set a frame-control policy â€” Lighthouse
 * flags the bare origin as High severity. SAMEORIGIN keeps the Elementor
 * editor preview and the WP customizer working (both iframe the site from its
 * own origin) while blocking third-party embedding. Toggle off via option
 * cc_assistant_xfo_enabled = false if the operator intentionally embeds the
 * site elsewhere. Server-level headers added later in front of PHP can't be
 * detected here; a duplicate SAMEORIGIN pair is harmless.
 */
add_action(
	'send_headers',
	function () {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		if ( ! get_option( 'cc_assistant_xfo_enabled', true ) ) {
			return;
		}
		foreach ( headers_list() as $sent ) {
			$lower = strtolower( $sent );
			if ( str_starts_with( $lower, 'x-frame-options:' )
				|| ( str_starts_with( $lower, 'content-security-policy' ) && str_contains( $lower, 'frame-ancestors' ) ) ) {
				return;
			}
		}
		header( 'X-Frame-Options: SAMEORIGIN' );
	},
	20
);

register_deactivation_hook(
	__FILE__,
	function () {
		require_once CC_ASSISTANT_DIR . 'includes/class-deactivator.php';
		CC_Assistant_Deactivator::deactivate();
	}
);

/**
 * Google Reviews (CC) Elementor widget registration. Fires only when Elementor
 * is assembling its widget list (editor + front-end render of Elementor pages),
 * so the widget + data class never load on non-Elementor requests. The widget
 * render reads ONLY the cron-cached reviews option â€” no external call on render.
 */
add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-reviews.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-reviews-widget.php';
		if ( class_exists( 'CC_Assistant_Reviews_Widget' ) ) {
			$widgets_manager->register( new CC_Assistant_Reviews_Widget() );
		}
	}
);

if ( is_admin() ) {
	/**
	 * Minimum admin bootstrap. Runs on EVERY admin request, so it must
	 * stay tiny â€” only enough to register the admin menu, admin bar
	 * status, and lazy cron-action handlers. Heavy classes load via
	 * current_screen below for screens that actually need them.
	 *
	 * Was previously 13 require_once + 4 bootstrap calls; that added up
	 * on shared hosts and slowed every wp-admin page.
	 *
	 * v0.68.3 WARNING for future handlers: this block does NOT run during
	 * wp-cron (is_admin() is false there). The closure-based cron handlers
	 * below are therefore REGISTERED A SECOND TIME in the dedicated
	 * DOING_CRON bootstrap further down â€” and any class whose init()
	 * attaches a cron callback (post-apply audit, cosine verifier) MUST be
	 * init'd there too, or its events are consumed by wp-cron with no
	 * callback attached, silently, forever. That exact gap kept the render-
	 * health verdict and the cosine pill from ever being written by a real
	 * cron pass.
	 */
	add_action(
		'plugins_loaded',
		function () {
			require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
			require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
			require_once CC_ASSISTANT_DIR . 'admin/class-admin.php';
			CC_Assistant_Admin::init();

			// v0.32.0 Page Performance tracker â€” Posts/Pages list columns +
			// dedicated dashboard at CC Assistant â†’ Page Performance. Loads
			// only on admin requests; per-request prefetch keeps the list
			// page render cost flat regardless of post count.
			require_once CC_ASSISTANT_DIR . 'includes/class-performance-tracker.php';
			CC_Assistant_Performance_Tracker::init();

			// v0.64.0 Reports â€” site totals, per-page reports, applied-change
			// activity, and the plugin's first CSV export. Reads only data the
			// plugin already stores (cc_gsc_queries, cc_edits, cc_lead_events);
			// registers a hidden submenu reached as a tab on the Performance hub.
			require_once CC_ASSISTANT_DIR . 'includes/class-reports.php';
			CC_Assistant_Reports::init();

			// v0.65.0 Stack introspection â€” capture the REAL wp-admin menu tree
			// so the assistant can answer "where is this setting" instead of
			// guessing. This MUST hook during admin requests: third-party
			// plugins only register their admin_menu callbacks when is_admin()
			// is true (Elementor gates `new Admin()` behind it in
			// includes/plugin.php:727), so the tree cannot be rebuilt from a
			// REST request. init() is a no-op outside wp-admin and the capture
			// only writes when the tree actually changed.
			require_once CC_ASSISTANT_DIR . 'includes/class-stack-introspect.php';
			CC_Assistant_Stack_Introspect::init();

			// Post-apply duplication regression check. Hooks into apply event
			// fired by class-apply.php; the class file itself is light (no FE
			// queries, action-handler only) so loading on plugins_loaded is fine.
			require_once CC_ASSISTANT_DIR . 'includes/class-post-apply-verifier.php';
			CC_Assistant_Post_Apply_Verifier::init();

			// 0.15: Post-apply RENDERED audit â€” curls the live URL 30s after
			// every elementor_* apply, parses JSON-LD, detects duplicate @id /
			// singleton-type multiplication / parse errors. Records to activity
			// log + admin notice + verification_result so the issue surfaces
			// on the next session's whoami(). This is the safety net that
			// catches anything the pre-queue dedup guards miss.
			require_once CC_ASSISTANT_DIR . 'includes/class-post-apply-audit.php';
			CC_Assistant_Post_Apply_Audit::init();

			// Cache flush AJAX endpoint backing the dashboard "Refresh data"
			// button. Light: only registers the action hook; the heavy
			// transient sweep runs on user click, not on every admin page.
			require_once CC_ASSISTANT_DIR . 'includes/class-cache.php';
			CC_Assistant_Cache::init();

			// Register the 'weekly' cron interval inline so the class file
			// holding bootstrap() doesn't have to load on every admin page.
			add_filter( 'cron_schedules', function ( $schedules ) {
				if ( ! isset( $schedules['weekly'] ) ) {
					$schedules['weekly'] = array(
						'interval' => 7 * DAY_IN_SECONDS,
						'display'  => 'Once weekly',
					);
				}
				return $schedules;
			} );

			// Cron-action handlers â€” lazy-loading closures wrapped in the
			// error-log defensive wrapper. Catches Throwable so one bad
			// handler doesn't crash the whole wp-cron run, and surfaces
			// failures as admin notices via Settings â†’ Health.
			$wrap = function ( $context, callable $fn ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-error-log.php';
				return CC_Assistant_Error_Log::wrap( $context, $fn );
			};
			add_action( 'cc_assistant_warm_rendered', function ( $post_id ) use ( $wrap ) {
				$wrap( 'warm_rendered:' . (int) $post_id, function () use ( $post_id ) {
					require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
					CC_Assistant_Pre_Publish::warm_rendered_html( (int) $post_id );
				} );
			} );
			add_action( 'cc_assistant_warm_rendered_batch', function () use ( $wrap ) {
				$wrap( 'warm_rendered_batch', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
					CC_Assistant_Pre_Publish::warm_rendered_batch();
				} );
			} );
			add_action( 'cc_assistant_advisor_recompute', function () use ( $wrap ) {
				$wrap( 'advisor_recompute', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
					CC_Assistant_Weekly_Advisor::priorities( array( 'force' => true ) );
				} );
			} );
			add_action( 'cc_assistant_calendar_refresh_warm', function () use ( $wrap ) {
				$wrap( 'calendar_refresh_warm', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
					$queue = CC_Assistant_SEO_Tools::refresh_queue( array( 'limit' => 25 ) );
					set_transient( 'cc_calendar_refresh_queue', $queue, 6 * HOUR_IN_SECONDS );
				} );
			} );
			add_action( 'cc_assistant_site_audit', function () use ( $wrap ) {
				$wrap( 'site_audit', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-site-audit.php';
					CC_Assistant_Site_Audit::run();
				} );
			} );
			add_action( 'cc_assistant_cannib_trends', function () use ( $wrap ) {
				$wrap( 'cannib_trends', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-cannibalization-trends.php';
					CC_Assistant_Cannibalization_Trends::run();
				} );
			} );
			add_action( 'cc_assistant_verdict_notify', function () use ( $wrap ) {
				$wrap( 'verdict_notify', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-verdict-notifier.php';
					CC_Assistant_Verdict_Notifier::run();
				} );
			} );
			add_action( 'cc_assistant_link_graph_rebuild', function () use ( $wrap ) {
				$wrap( 'link_graph_rebuild', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
					CC_Assistant_Internal_Links::rebuild_graph();
				} );
			} );
			add_action( 'cc_assistant_llm_prune', function () use ( $wrap ) {
				$wrap( 'llm_prune', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-llm-tracker.php';
					CC_Assistant_LLM_Tracker::prune();
				} );
			} );
			// 0.14: prune the rolling activity log at the 2-day retention edge.
			// Single indexed DELETE â€” light enough to share the admin-side cron
			// pool; we don't bother with the front-end listener block below.
			add_action( 'cc_assistant_activity_log_prune', function () use ( $wrap ) {
				$wrap( 'activity_log_prune', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
					CC_Assistant_Activity_Log::prune_older_than( 2 );
				} );
			} );
			// v0.33.0 unified storage maintenance â€” prunes the three previously
			// unbounded tables (cc_snapshots, cc_pending_changes, cc_edits) on
			// a daily schedule. Class is light (no FE queries) so loading on
			// every admin request is fine; the cron hook also fires from the
			// cron-context block below.
			require_once CC_ASSISTANT_DIR . 'includes/class-storage-maintenance.php';
			CC_Assistant_Storage_Maintenance::init(); // registers admin_post + cron action
			CC_Assistant_Storage_Maintenance::schedule_cron();
			add_action( CC_Assistant_Storage_Maintenance::CRON_HOOK, function () use ( $wrap ) {
				$wrap( 'storage_maintenance', function () {
					CC_Assistant_Storage_Maintenance::run();
				} );
			} );

			// Google Reviews (Phase 1) â€” isolated admin page (CC Assistant ->
			// Reviews) + daily refresh cron. Class is light (no FE queries),
			// matching the storage-maintenance load-on-admin pattern above.
			require_once CC_ASSISTANT_DIR . 'includes/class-reviews.php';
			CC_Assistant_Reviews::init_admin();
			CC_Assistant_Reviews::schedule_cron();
			add_action( CC_Assistant_Reviews::CRON_HOOK, function () use ( $wrap ) {
				$wrap( 'reviews_refresh', function () {
					CC_Assistant_Reviews::cron_refresh();
				} );
			} );

			add_action( 'cc_assistant_gsc_sync', function () use ( $wrap ) {
				$wrap( 'gsc_sync', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
					CC_Assistant_GSC::cron_sync();
				} );
			} );
			add_action( 'cc_assistant_gsc_sync_now', function () use ( $wrap ) {
				$wrap( 'gsc_sync_now', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
					CC_Assistant_GSC::cron_sync();
				} );
			} );
			add_action( 'cc_assistant_gsc_backfill_chunk', function ( $arg1, $arg2 ) use ( $wrap ) {
				$wrap( 'gsc_backfill_chunk', function () use ( $arg1, $arg2 ) {
					require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
					CC_Assistant_GSC::backfill_chunk( $arg1, $arg2 );
				} );
			}, 10, 2 );
			add_action( 'cc_assistant_recompute_insights', function () use ( $wrap ) {
				$wrap( 'recompute_insights', function () {
					require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
					CC_Assistant_GSC::recompute_insights();
				} );
			} );

			// before_delete_post hook â€” when a post is permanently deleted,
			// orphaned pending rows would otherwise sit in the inbox with
			// blank titles. Mark them as 'rejected' with a clear note so
			// they fall out of the active inbox but stay in the rejected
			// tab as historical record. Snapshots and cc_edits are kept
			// (analytics value); link_graph rows referencing the deleted
			// post are dropped since the URL is gone.
			add_action( 'before_delete_post', function ( $post_id ) {
				$post_id = (int) $post_id;
				if ( $post_id < 1 ) {
					return;
				}
				global $wpdb;
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}cc_pending_changes
					 SET status = 'rejected',
					     reviewed_at = %s,
					     reviewed_by = NULL,
					     review_note = %s
					 WHERE post_id = %d AND status = 'pending' AND superseded_by IS NULL",
					current_time( 'mysql' ),
					sprintf( '[auto] Post %d was deleted before this change could be reviewed.', $post_id ),
					$post_id
				) );
				// Drop link-graph edges that reference the deleted post on
				// either side; the URL is gone so the edges are noise.
				$wpdb->delete( $wpdb->prefix . 'cc_link_graph', array( 'source_post_id' => $post_id ) );
				$wpdb->delete( $wpdb->prefix . 'cc_link_graph', array( 'target_post_id' => $post_id ) );
				// Drop cluster memberships; cluster pillar pointers cleaned
				// up on next render via the cluster page's defensive load.
				$wpdb->delete( $wpdb->prefix . 'cc_cluster_members', array( 'post_id' => $post_id ) );
			} );

			// Save-post hook â€” costs nothing until a save fires. Schedules
			// a 5s-out single-event cron to refresh the rendered-HTML cache
			// for the post, so the editor sidebar's next render shows fresh
			// counts.
			add_action( 'save_post', function ( $post_id ) {
				if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
					return;
				}
				$post = get_post( $post_id );
				if ( ! $post || 'publish' !== $post->post_status ) {
					return;
				}
				$args = array( (int) $post_id );
				if ( ! wp_next_scheduled( 'cc_assistant_warm_rendered', $args ) ) {
					wp_schedule_single_event( time() + 5, 'cc_assistant_warm_rendered', $args );
				}
			}, 20 );
		}
	);

	/**
	 * View-conditional class loading. current_screen fires after the admin
	 * menu has been registered but before the page renders, so we have
	 * screen context here without delaying the render.
	 *
	 * Strategy:
	 *   - CC Assistant pages: full toolkit (snapshots, apply, gsc, etc.)
	 *   - Post edit screens: editor sidebar metabox
	 *   - Dashboard + CC pages: admin notices renderer
	 *   - Anywhere else (Plugins, Tools, Posts list): nothing extra loads
	 */
	add_action(
		'current_screen',
		function ( $screen ) {
			if ( ! $screen || ! is_object( $screen ) ) {
				return;
			}
			$screen_id = (string) $screen->id;
			$is_cc     = false !== strpos( $screen_id, 'cc-assistant' );
			$is_dash   = 'dashboard' === $screen_id;
			$is_post   = isset( $screen->base ) && 'post' === $screen->base;

			if ( $is_cc || $is_dash ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-admin-notices.php';
				CC_Assistant_Admin_Notices::init();
			}

			if ( $is_post ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
				require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
				require_once CC_ASSISTANT_DIR . 'admin/class-editor-sidebar.php';
				CC_Assistant_Editor_Sidebar::init();
			}

			if ( $is_cc ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
				require_once CC_ASSISTANT_DIR . 'includes/class-apply.php';
				require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
				require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
				require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
				require_once CC_ASSISTANT_DIR . 'includes/class-llm-tracker.php';
				require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
				// NOTE: do not call GSC::bootstrap() here â€” its cron + OAuth
				// handlers are already registered globally as lazy closures
				// in the plugins_loaded block above. Calling bootstrap() again
				// would double-register class-method handlers and fire crons
				// twice per event.
			}
		}
	);

	/**
	 * One-time scheduling + OAuth callback. admin_init fires on every
	 * admin request but the work below is bounded:
	 *   - maybe_upgrade short-circuits when DB version matches plugin version
	 *   - GSC OAuth handler is registered as an admin_init action that reads
	 *     $_GET and returns immediately when no callback param is present
	 *   - Cron schedule registrations are wp_next_scheduled-guarded â€” just
	 *     autoloaded option reads, no DB writes once scheduled
	 *
	 * NOTE: scheduling does NOT need the handler classes loaded â€”
	 * wp_schedule_event takes a hook name, not a class. The handlers are
	 * registered as lazy closures in plugins_loaded above. So admin_init
	 * doesn't load class-site-audit / cannibalization / verdict / gsc
	 * unless the OAuth callback is being processed.
	 */
	add_action(
		'admin_init',
		function () {
			require_once CC_ASSISTANT_DIR . 'includes/class-activator.php';
			CC_Assistant_Activator::maybe_upgrade();

			// OAuth callback handler â€” load GSC class only when the URL
			// actually carries the OAuth params, otherwise skip the file load.
			if ( isset( $_GET['cc_gsc_oauth'] ) || isset( $_GET['code'] ) ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
				CC_Assistant_GSC::maybe_handle_oauth();
			}

			// Google Business Profile OAuth callback (separate client/scope).
			if ( isset( $_GET['cc_gbp_oauth'] ) ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-gbp.php';
				CC_Assistant_GBP::maybe_handle_oauth();
			}

			// Recurring cron schedules. Lightweight wp_next_scheduled checks
			// against the autoloaded cron option â€” no class-file loads.
			if ( ! wp_next_scheduled( 'cc_assistant_gsc_sync' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cc_assistant_gsc_sync' );
			}
			if ( ! wp_next_scheduled( 'cc_assistant_site_audit' ) ) {
				wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'daily', 'cc_assistant_site_audit' );
			}
			if ( ! wp_next_scheduled( 'cc_assistant_cannib_trends' ) ) {
				wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'weekly', 'cc_assistant_cannib_trends' );
			}
			if ( ! wp_next_scheduled( 'cc_assistant_verdict_notify' ) ) {
				wp_schedule_event( time() + 30 * MINUTE_IN_SECONDS, 'daily', 'cc_assistant_verdict_notify' );
			}
			if ( ! wp_next_scheduled( 'cc_assistant_warm_rendered_batch' ) ) {
				wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'hourly', 'cc_assistant_warm_rendered_batch' );
			}
		}
	);

	// v0.18.3 always-on LG self-heal removed in v0.22.0. Moved to a one-shot
	// migration in class-activator.php under version_compare 0.8.0. The heal
	// fingerprint no longer matches in production, so the always-on cost was
	// pure waste on every admin request on every other site.
}

add_action(
	'rest_api_init',
	function () {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-apply.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-llm-tracker.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-api.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-evidence-gate.php';
		CC_Assistant_Evidence_Gate::init();
		CC_Assistant_REST_API::register_routes();

		// Topic-cluster routes live in their own class so they can be added
		// without touching class-rest-api.php (where parallel work happens).
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-clusters.php';
		CC_Assistant_REST_Clusters::register_routes();

		// SEO analysis routes (cannibalization, image audit, refresh queue,
		// click depth, post dossier, structure, competitor brief).
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-seo.php';
		CC_Assistant_REST_SEO::register_routes();
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-content-strategy.php';
		CC_Assistant_REST_Content_Strategy::register_routes();

		// Lazy-diff route for the pending inbox (GET /pending/{id}/diff).
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-pending.php';
		CC_Assistant_REST_Pending::register_routes();

		// Weekly advisor + brief generator routes.
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-advisor.php';
		CC_Assistant_REST_Advisor::register_routes();

		// Divi module I/O routes (surgical module-level edits for Divi sites).
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-divi.php';
		CC_Assistant_REST_Divi::register_routes();

		// Asset-reference routes (v0.67): find/replace a media URL wherever it
		// is stored, including plugins that keep content outside post_content.
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-assets.php';
		CC_Assistant_REST_Assets::register_routes();

		// GSC raw-export routes for the desktop SQLite warehouse (v0.54):
		// pass-through proxy to the searchAnalytics API, no DB writes here.
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-warehouse.php';
		CC_Assistant_REST_Warehouse::register_routes();

		// Attention-flow routes (v0.55): archetype specs + predicted
		// attention audit ("theoretical heatmap").
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-attention.php';
		CC_Assistant_REST_Attention::register_routes();

		// Widget-schema routes (v0.56): live Elementor control registry.
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-widget-schema.php';
		CC_Assistant_REST_Widget_Schema::register_routes();

		// Operator-kit routes (v0.57): the site carries its own operator
		// knowledge (design skills, bootstrap bundle) so any machine can
		// rebuild the working environment from the site alone.
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-operator-kit.php';
		CC_Assistant_REST_Operator_Kit::register_routes();

		// Close-the-loop routes (v0.58): revert-from-outcome + lead events.
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-close-loop.php';
		CC_Assistant_REST_Close_Loop::register_routes();

		// AEO / commodity routes (v0.60): content-side signals for the
		// bridge's commodity_audit.
		require_once CC_ASSISTANT_DIR . 'includes/class-rest-aeo.php';
		CC_Assistant_REST_AEO::register_routes();

		// Reviews read route (v0.76.7) — review_health over the bridge. The
		// review cache used to be readable only inside wp-admin, so nobody
		// could verify a Places setup or track rating/count/recency remotely.
		require_once CC_ASSISTANT_DIR . 'includes/class-reviews.php';
		CC_Assistant_Reviews::register_routes();
	}
);

// v0.58 lead-event counter: fires ONLY on Elementor Pro form submissions
// (never on page renders). One aggregate row per (day, post, form) â€” counts
// only, nothing personal â€” so outcome_report can measure LEADS, not just
// clicks.
add_action(
	'elementor_pro/forms/new_record',
	function ( $record, $handler ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-lead-events.php';
		CC_Assistant_Lead_Events::record( $record );
	},
	10,
	2
);

// Cron-context bootstrap: register cron handlers when wp-cron fires
// outside of an admin request. The admin path already registers the same
// handlers via plugins_loaded above (so wp-cron piggyback works); this
// block exists only for direct cron invocations (real /wp-cron.php hits,
// WP-CLI cron event run, etc.) where is_admin() is false.
add_action(
	'plugins_loaded',
	function () {
		if ( ! ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || wp_doing_cron() ) ) {
			return;
		}
		// Same lazy-loading closure pattern as the admin path, with the
		// error-log wrapper around each handler so cron failures get
		// captured and surfaced via Settings â†’ Health.
		$wrap = function ( $context, callable $fn ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-error-log.php';
			return CC_Assistant_Error_Log::wrap( $context, $fn );
		};

		// v0.68.3: class-based cron handlers. These two register their cron
		// callbacks inside init(), which historically ran ONLY in the admin
		// bootstrap â€” so every one of their events fired by a real wp-cron
		// pass was consumed with no callback attached. Result: the cosine
		// verification pill and the render-health verdict were never written
		// by cron, ever, while scheduling (which happens during admin
		// requests) worked perfectly. Static-method callables dedupe in
		// add_action, so double-init in an exotic admin+cron context is safe
		// â€” unlike the closures above, which is why those are not simply
		// re-run from the admin block.
		require_once CC_ASSISTANT_DIR . 'includes/class-post-apply-verifier.php';
		CC_Assistant_Post_Apply_Verifier::init();
		require_once CC_ASSISTANT_DIR . 'includes/class-post-apply-audit.php';
		CC_Assistant_Post_Apply_Audit::init();
		add_action( 'cc_assistant_warm_rendered', function ( $post_id ) use ( $wrap ) {
			$wrap( 'warm_rendered:' . (int) $post_id, function () use ( $post_id ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
				CC_Assistant_Pre_Publish::warm_rendered_html( (int) $post_id );
			} );
		} );
		add_action( 'cc_assistant_warm_rendered_batch', function () use ( $wrap ) {
			$wrap( 'warm_rendered_batch', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
				CC_Assistant_Pre_Publish::warm_rendered_batch();
			} );
		} );
		add_action( 'cc_assistant_advisor_recompute', function () use ( $wrap ) {
			$wrap( 'advisor_recompute', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
				CC_Assistant_Weekly_Advisor::priorities( array( 'force' => true ) );
			} );
		} );
		add_action( 'cc_assistant_calendar_refresh_warm', function () use ( $wrap ) {
			$wrap( 'calendar_refresh_warm', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
				$queue = CC_Assistant_SEO_Tools::refresh_queue( array( 'limit' => 25 ) );
				set_transient( 'cc_calendar_refresh_queue', $queue, 6 * HOUR_IN_SECONDS );
			} );
		} );
		add_action( 'cc_assistant_site_audit', function () use ( $wrap ) {
			$wrap( 'site_audit', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-site-audit.php';
				CC_Assistant_Site_Audit::run();
			} );
		} );
		add_action( 'cc_assistant_cannib_trends', function () use ( $wrap ) {
			$wrap( 'cannib_trends', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-cannibalization-trends.php';
				CC_Assistant_Cannibalization_Trends::run();
			} );
		} );
		add_action( 'cc_assistant_verdict_notify', function () use ( $wrap ) {
			$wrap( 'verdict_notify', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-verdict-notifier.php';
				CC_Assistant_Verdict_Notifier::run();
			} );
		} );
		add_action( 'cc_assistant_link_graph_rebuild', function () use ( $wrap ) {
			$wrap( 'link_graph_rebuild', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
				CC_Assistant_Internal_Links::rebuild_graph();
			} );
		} );
		add_action( 'cc_assistant_llm_prune', function () use ( $wrap ) {
			$wrap( 'llm_prune', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-llm-tracker.php';
				CC_Assistant_LLM_Tracker::prune();
			} );
		} );
		// 0.14: mirror listener in the front-end cron-fallback block so the
		// activity-log prune fires regardless of which branch handles the tick.
		add_action( 'cc_assistant_activity_log_prune', function () use ( $wrap ) {
			$wrap( 'activity_log_prune', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
				CC_Assistant_Activity_Log::prune_older_than( 2 );
			} );
		} );
		// Google Reviews daily refresh mirror for cron-only invocations.
		add_action( 'cc_assistant_reviews_refresh', function () use ( $wrap ) {
			$wrap( 'reviews_refresh', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-reviews.php';
				CC_Assistant_Reviews::cron_refresh();
			} );
		} );
		// 0.33.0 mirror: storage maintenance for cron-only invocations.
		add_action( 'cc_assistant_storage_maintenance', function () use ( $wrap ) {
			$wrap( 'storage_maintenance', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-storage-maintenance.php';
				CC_Assistant_Storage_Maintenance::run();
			} );
		} );
		add_action( 'cc_assistant_gsc_sync', function () use ( $wrap ) {
			$wrap( 'gsc_sync', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
				CC_Assistant_GSC::cron_sync();
			} );
		} );
		add_action( 'cc_assistant_gsc_sync_now', function () use ( $wrap ) {
			$wrap( 'gsc_sync_now', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
				CC_Assistant_GSC::cron_sync();
			} );
		} );
		add_action( 'cc_assistant_gsc_backfill_chunk', function ( $arg1, $arg2 ) use ( $wrap ) {
			$wrap( 'gsc_backfill_chunk', function () use ( $arg1, $arg2 ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
				CC_Assistant_GSC::backfill_chunk( $arg1, $arg2 );
			} );
		}, 10, 2 );
		add_action( 'cc_assistant_recompute_insights', function () use ( $wrap ) {
			$wrap( 'recompute_insights', function () {
				require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
				CC_Assistant_GSC::recompute_insights();
			} );
		} );
	}
);

// LLM crawler tracking: front-end hook, only fires when feature is enabled and
// the user-agent looks like a bot. The option read is a single autoloaded
// boolean â€” no DB query overhead beyond what WP already does.
if ( ! is_admin() && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
	add_action(
		'init',
		function () {
			if ( ! get_option( 'cc_assistant_llm_tracking_enabled', false ) ) {
				return;
			}
			if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
				return;
			}
			// Cheap pre-filter before loading the tracker class. Real bot UAs
			// always include one of these substrings.
			if ( ! preg_match( '/(bot|ai|claude|perplexity|gpt|spider|extended|external)/i', $_SERVER['HTTP_USER_AGENT'] ) ) {
				return;
			}
			require_once CC_ASSISTANT_DIR . 'includes/class-llm-tracker.php';
			CC_Assistant_LLM_Tracker::maybe_track();
		},
		1
	);
}

// Content planning settings load only in the administrator menu lifecycle.
add_action( 'admin_menu', static function () {
    require_once CC_ASSISTANT_DIR . 'includes/class-rest-content-strategy.php';
    CC_Assistant_REST_Content_Strategy::admin_menu();
}, 30 );

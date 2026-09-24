<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pre-publish check: composite quality audit on a single post.
 * Each check returns a pass/fail with optional details.
 */
class CC_Assistant_Pre_Publish {

	const AI_TELLS = array(
		'in today\'s fast-paced world',
		'it is important to note',
		'in conclusion',
		'unleash',
		'leverage',
		'delve',
		'tapestry',
		'unlock',
		'game-changer',
		'dive into',
		'whether you\'re',
	);

	/**
	 * Friendly metadata per check_name. Single source of truth — both the
	 * Check up admin view and the editor-sidebar metabox consume this.
	 *
	 * Each entry: label (UI text), category (drives icon color), help (one-line
	 * "why this matters" tooltip). Anything not listed falls back to the raw
	 * check name so newer checks render without a code change here.
	 */
	public static function check_meta() {
		return array(
			'em_dashes' => array(
				'label'    => __( 'Em dashes', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Em dashes are a strong AI-tell. Replace with periods, commas, or parentheses.', 'cc-assistant' ),
			),
			'ai_tells' => array(
				'label'    => __( 'AI-tell phrases', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Phrases like "delve", "leverage", "in conclusion" make content read as AI-generated.', 'cc-assistant' ),
			),
			'heading_h1' => array(
				'label'    => __( 'Single H1', 'cc-assistant' ),
				'category' => 'technical',
				'help'     => __( 'A page should have exactly one H1 — the post title. Extra H1s in the body should be H2s.', 'cc-assistant' ),
			),
			'heading_skip' => array(
				'label'    => __( 'Heading hierarchy', 'cc-assistant' ),
				'category' => 'technical',
				'help'     => __( 'Heading levels should not skip (H2 → H4 is wrong). Walk down one level at a time.', 'cc-assistant' ),
			),
			'heading_depth' => array(
				'label'    => __( 'Flat heading structure', 'cc-assistant' ),
				'category' => 'technical',
				'help'     => __( 'Many sibling H2s with zero H3s usually means sub-sections were tagged as H2 by mistake.', 'cc-assistant' ),
			),
			'heading_empty' => array(
				'label'    => __( 'Empty headings', 'cc-assistant' ),
				'category' => 'technical',
				'help'     => __( 'Empty heading tags are template debris and confuse screen readers and Google.', 'cc-assistant' ),
			),
			'word_count' => array(
				'label'    => __( 'Word count', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Posts under 300 words rarely rank. Either expand the content or noindex it.', 'cc-assistant' ),
			),
			'image_alt' => array(
				'label'    => __( 'Image alt text', 'cc-assistant' ),
				'category' => 'technical',
				'help'     => __( 'Every image needs alt text for accessibility and image-search ranking.', 'cc-assistant' ),
			),
			'meta_description' => array(
				'label'    => __( 'Meta description', 'cc-assistant' ),
				'category' => 'seo',
				'help'     => __( 'Meta description should be 120–160 characters so Google shows it in full in the SERP.', 'cc-assistant' ),
			),
			'authority_links' => array(
				'label'    => __( 'Authority sources', 'cc-assistant' ),
				'category' => 'authority',
				'help'     => __( 'Cite at least one .gov, .edu, or trusted authority source per post for E-E-A-T.', 'cc-assistant' ),
			),
			'blocked_citations' => array(
				'label'    => __( 'No competitor links', 'cc-assistant' ),
				'category' => 'authority',
				'help'     => __( 'Posts cite a competitor domain. Replace with an authority source or remove.', 'cc-assistant' ),
			),
			'freshness' => array(
				'label'    => __( 'Freshness', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Pages older than a year often need a refresh — check facts, dates, and stats.', 'cc-assistant' ),
			),
			'author_bio' => array(
				'label'    => __( 'Author bio', 'cc-assistant' ),
				'category' => 'authority',
				'help'     => __( 'A real author bio is a strong E-E-A-T signal. Add one in Users → Profile.', 'cc-assistant' ),
			),
			'schema' => array(
				'label'    => __( 'Schema markup', 'cc-assistant' ),
				'category' => 'seo',
				'help'     => __( 'Schema (JSON-LD) helps SERP features like FAQ and how-to rich results.', 'cc-assistant' ),
			),
			'source_density' => array(
				'label'    => __( 'Source density', 'cc-assistant' ),
				'category' => 'authority',
				'help'     => __( 'For longer content, aim for ≥1 authority citation per 1,000 words.', 'cc-assistant' ),
			),
			'serp_snippet' => array(
				'label'    => __( 'Featured-snippet shape', 'cc-assistant' ),
				'category' => 'seo',
				'help'     => __( 'Question-shaped H2 answers should be 40–60 words to win featured snippets.', 'cc-assistant' ),
			),
			'style_guide' => array(
				'label'    => __( 'Style guide compliance', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Body content matches the banned phrases / words listed in your style guide. Edit it in Settings → Style guide.', 'cc-assistant' ),
			),
			'paragraph_length' => array(
				'label'    => __( 'Paragraph length', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Paragraphs over 5 sentences usually read as wall-of-text. The right break point is a meaning shift (idea changes, contrast set up, line worth remembering), not a fixed sentence count. 4-5 sentence paragraphs are fine when they develop one coherent point.', 'cc-assistant' ),
			),
			'sentence_length' => array(
				'label'    => __( 'Sentence length', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Sentences over 25 words tend to be comma-laden run-ons. Break into two clear sentences.', 'cc-assistant' ),
			),
			'reading_level' => array(
				'label'    => __( 'Reading level', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Flesch-Kincaid grade level. Target 7–9 for general audiences. 11+ reads as academic.', 'cc-assistant' ),
			),
			'html_cruft' => array(
				'label'    => __( 'HTML cruft', 'cc-assistant' ),
				'category' => 'technical',
				'help'     => __( 'Legacy classic-editor wrappers (font-weight: 400 spans, mso-* styles) bloat the HTML and add no value. Strip them.', 'cc-assistant' ),
			),
			'jargon' => array(
				'label'    => __( 'Jargon density', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Domain-specific terms with no plain-language gloss alienate non-expert readers. Define them or replace with everyday words.', 'cc-assistant' ),
			),
			'bulk_add_ratio' => array(
				'label'    => __( 'Bulk-add ratio', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Real editing cuts as it expands. A change that only adds and never removes usually means redundancy got bolted on.', 'cc-assistant' ),
			),
			'redundancy' => array(
				'label'    => __( 'Redundancy with existing content', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Newly added paragraphs repeat points already made in kept sections. Merge or cut to avoid saying the same thing in different words.', 'cc-assistant' ),
			),
			'deletion_ratio' => array(
				'label'    => __( 'Deletion ratio', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'How much of the original copy was removed in this rewrite. 0% deletions on a body rewrite usually means redundancy got bolted on.', 'cc-assistant' ),
			),
			'shortcode_preservation' => array(
				'label'    => __( 'Shortcode preservation', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Counts shortcodes (e.g. [contact-form-7], [gallery]) before and after a rewrite. Silently dropping one breaks the page.', 'cc-assistant' ),
			),
			'image_preservation' => array(
				'label'    => __( 'Image preservation', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Counts inline <img> tags before and after a rewrite. Dropped images mean the page loses visual content.', 'cc-assistant' ),
			),
			'authority_citation_preservation' => array(
				'label'    => __( 'Authority citation preservation', 'cc-assistant' ),
				'category' => 'authority',
				'help'     => __( 'Counts links to .gov / .edu / trusted authority domains. Dropped citations bleed E-E-A-T value.', 'cc-assistant' ),
			),
			'internal_link_preservation' => array(
				'label'    => __( 'Internal link preservation', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Counts links to other pages on this same site. Dropping more than 10% leaks internal link equity to neighbour posts.', 'cc-assistant' ),
			),
			'phone_number_preservation' => array(
				'label'    => __( 'Phone number preservation', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Catches the case where the local-business call-to-action gets stripped during a rewrite.', 'cc-assistant' ),
			),
			'featured_image' => array(
				'label'    => __( 'Featured image', 'cc-assistant' ),
				'category' => 'technical',
				'help'     => __( 'Posts and pages without a featured image look bare in lists, social shares, and OG cards. Set one in the post sidebar.', 'cc-assistant' ),
			),
			'excerpt' => array(
				'label'    => __( 'Manual excerpt', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Without a manual excerpt, themes and feeds auto-generate one from the body, which is usually unflattering. Write a clean 1-2 sentence summary.', 'cc-assistant' ),
			),
			'categories' => array(
				'label'    => __( 'Real category', 'cc-assistant' ),
				'category' => 'seo',
				'help'     => __( 'Posts assigned only to "Uncategorized" (or no category at all) hurt SEO and navigation. Assign a category that matches the topic.', 'cc-assistant' ),
			),
			'address_consistency' => array(
				'label'    => __( 'Address consistency', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Refuses content that mentions a street address different from the canonical one saved in Settings → General → Facility address.', 'cc-assistant' ),
			),
			'palette_compliance' => array(
				'label'    => __( 'Palette compliance', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Warns on hex colors outside the active Elementor Kit + semantic-greys allow-list. Off-brand hex usually means the agent invented a color instead of using a Kit global token.', 'cc-assistant' ),
			),
			'hospital_comparison' => array(
				'label'    => __( 'Hospital-comparison ban', 'cc-assistant' ),
				'category' => 'content',
				'help'     => __( 'Operator policy on ER sites: never position the clinic against hospital EDs (no "faster than the hospital", no "vs hospital wait times"). Use self-anchored claims only.', 'cc-assistant' ),
			),
		);
	}

	/**
	 * Parse the user's style-guide blob and extract banned phrase tokens.
	 * Looks for the "## Banned" or "Banned:" section and extracts comma-
	 * or bullet-separated terms, stripping quotes. Cached statically so
	 * a single check_post() call doesn't re-parse for every check.
	 *
	 * Returns lowercased trimmed phrases; empty when no style guide or
	 * no banned section found.
	 */
	public static function style_guide_banned_phrases() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$blob = (string) get_option( 'cc_assistant_style_guide', '' );
		if ( '' === $blob ) {
			$cache = array();
			return $cache;
		}
		$phrases = array();
		// Find the Banned section between heading marker and the next heading.
		if ( preg_match( '/(?:##+|^)\s*Banned[^\n]*\n([\s\S]*?)(?:\n\s*##|\z)/im', $blob, $m ) ) {
			$section = $m[1];
			// Pull quoted phrases first (e.g. "delve into the").
			if ( preg_match_all( '/[\"\'“”]([^\"\'“”]+?)[\"\'“”]/u', $section, $qm ) ) {
				foreach ( $qm[1] as $p ) {
					$p = trim( $p );
					if ( strlen( $p ) >= 2 ) {
						$phrases[] = mb_strtolower( $p );
					}
				}
			}
			// Then bullet items: lines starting with "- " or "* ".
			if ( preg_match_all( '/^\s*[-\*]\s*(.+)$/m', $section, $bm ) ) {
				foreach ( $bm[1] as $line ) {
					// Strip parenthetical advice "Em dashes (use ...)" → "Em dashes".
					$line = trim( preg_replace( '/\([^\)]*\)/', '', $line ) );
					$line = trim( $line, " \t.,;:" );
					if ( '' === $line ) {
						continue;
					}
					// Split comma-separated lists ("foo, bar, baz").
					foreach ( explode( ',', $line ) as $part ) {
						$part = trim( $part, " \t\"'.,;:" );
						if ( strlen( $part ) >= 3 && strlen( $part ) <= 80 ) {
							$phrases[] = mb_strtolower( $part );
						}
					}
				}
			}
		}
		// De-duplicate and drop "em dashes" / similar — em-dash is its own check.
		$out = array();
		foreach ( array_unique( $phrases ) as $p ) {
			if ( false !== strpos( $p, 'em dash' ) || false !== strpos( $p, 'em-dash' ) ) {
				continue;
			}
			$out[] = $p;
		}
		$cache = $out;
		return $cache;
	}

	/**
	 * Look up a friendly label for a check_name. Falls back to a prettified
	 * version of the raw name if the check isn't in the map yet.
	 */
	public static function label_for( $check_name ) {
		$meta = self::check_meta();
		if ( isset( $meta[ $check_name ]['label'] ) ) {
			return $meta[ $check_name ]['label'];
		}
		return ucfirst( str_replace( '_', ' ', $check_name ) );
	}

	/**
	 * Read the cached rendered HTML for a post. Cache-only — never fires
	 * HTTP from this path so editor renders, Check Up loops, and the site
	 * audit cron stay O(1) per call.
	 *
	 * Cache is populated by warm_rendered_html() which fires from:
	 *   - save_post hook (5s after a publish/update)
	 *   - cc_assistant_warm_rendered cron action (hourly batch of recent posts)
	 *   - explicit user "refresh check" button on the Check Up page
	 *
	 * Returns null if the cache is empty — callers fall back to parser
	 * counts and label the check `source: 'parsed'`. First time the cache
	 * is missing for a post, this method also schedules a single-event
	 * cron to warm it ~10 seconds out so the next check has fresh data
	 * without blocking the current request.
	 */
	public static function fetch_rendered_html( $post_id ) {
		$modified = (string) get_post_field( 'post_modified_gmt', (int) $post_id );
		$ck       = 'cc_rendered_html_' . (int) $post_id . '_' . md5( $modified );
		$cached   = get_transient( $ck );
		if ( false !== $cached ) {
			return is_array( $cached ) && isset( $cached['html'] ) ? $cached['html'] : null;
		}
		// No cache. Schedule an async warm so subsequent reads succeed,
		// then return null so the caller falls back to parser. Idempotent —
		// wp_next_scheduled prevents duplicate schedules on the same args.
		// Also gated so the site_audit cron (which iterates 200 posts) doesn't
		// schedule 200 simultaneous warm jobs.
		if ( ! ( defined( 'CC_PRE_PUBLISH_CACHE_ONLY' ) && CC_PRE_PUBLISH_CACHE_ONLY ) ) {
			$args = array( (int) $post_id );
			if ( ! wp_next_scheduled( 'cc_assistant_warm_rendered', $args ) ) {
				wp_schedule_single_event( time() + 10, 'cc_assistant_warm_rendered', $args );
			}
		}
		return null;
	}

	/**
	 * Background HTTP fetch for the rendered HTML cache. Called via cron
	 * (cc_assistant_warm_rendered) and never from a synchronous request
	 * path. 8s timeout + transient cache so a single request can't kill
	 * the cron run.
	 */
	public static function warm_rendered_html( $post_id ) {
		$post_id  = (int) $post_id;
		$modified = (string) get_post_field( 'post_modified_gmt', $post_id );
		$ck       = 'cc_rendered_html_' . $post_id . '_' . md5( $modified );
		$url      = get_permalink( $post_id );
		if ( ! $url ) {
			return null;
		}
		// Mimic a browser-style UA. SiteGround's WAF (and similar host-level
		// firewalls) silently 403 requests with niche/bot UAs like
		// "CC Assistant Pre-Publish/X.Y", which cached as null and forced
		// every check on those sites into the parser-fallback code path —
		// hiding the rendered-HTML signal entirely.
		$resp = wp_remote_get( $url, array(
			'timeout'     => 10,
			'redirection' => 0,
			'sslverify'   => true,
			'limit_response_size' => 2097152,
			'user-agent'  => CC_ASSISTANT_HTTP_UA,
			'headers'     => array( 'Accept' => 'text/html' ),
		) );
		if ( is_wp_error( $resp ) ) {
			set_transient( $ck, array( 'html' => null ), 30 * MINUTE_IN_SECONDS );
			return null;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( 200 !== $code ) {
			set_transient( $ck, array( 'html' => null ), 30 * MINUTE_IN_SECONDS );
			return null;
		}
		$body = wp_remote_retrieve_body( $resp );
		if ( ! $body ) {
			return null;
		}
		require_once __DIR__ . '/class-page-facts.php';
		$valid = CC_Assistant_Page_Facts::validate_fetch( array( 'code' => $code, 'body' => $body, 'content_type' => (string) wp_remote_retrieve_header( $resp, 'content-type' ) ) );
		if ( is_wp_error( $valid ) || strlen( $body ) >= 2097152 ) {
			set_transient( $ck, array( 'html' => null ), 30 * MINUTE_IN_SECONDS );
			return null;
		}
		set_transient( $ck, array( 'html' => $body ), DAY_IN_SECONDS );
		return $body;
	}

	/**
	 * Hourly opportunistic warming: refresh the rendered-HTML cache for
	 * the N most-recently-modified posts in the allowlist. Bounded to 8
	 * posts per run so even on slow hosts the cron stays under PHP
	 * execution time. Hot content stays fresh; cold content waits for
	 * save_post or first-touch scheduling to populate.
	 */
	public static function warm_rendered_batch() {
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$ids = get_posts( array(
			'post_type'      => $allowed,
			'post_status'    => 'publish',
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'posts_per_page' => 8,
			'fields'         => 'ids',
			'suppress_filters' => true,
		) );
		foreach ( (array) $ids as $pid ) {
			self::warm_rendered_html( (int) $pid );
		}
	}

	/**
	 * Best-effort isolation of the post body region from the full rendered
	 * page HTML. Strips theme chrome (header/nav/aside/footer) so heading
	 * counts reflect the post content rather than the whole template.
	 */
	/**
	 * Largest inner HTML across every <$tag> block in the document, ignoring
	 * blocks explicitly marked as list items (role="listitem"), which is how
	 * Elementor labels each card in a posts grid. Returns '' when nothing
	 * qualifies so the caller can fall through to the next strategy.
	 *
	 * Non-greedy per block, so nested same-tag markup resolves to the inner
	 * one; that is fine, because the outer wrapper is then also measured and
	 * the largest wins.
	 */
	private static function largest_block( $html, $tag ) {
		if ( ! preg_match_all( '/<' . $tag . '\b([^>]*)>([\s\S]*?)<\/' . $tag . '>/i', $html, $all, PREG_SET_ORDER ) ) {
			return '';
		}
		$best = '';
		foreach ( $all as $blk ) {
			if ( preg_match( '/role\s*=\s*["\']listitem["\']/i', $blk[1] ) ) {
				continue;
			}
			if ( strlen( $blk[2] ) > strlen( $best ) ) {
				$best = $blk[2];
			}
		}
		return $best;
	}

	public static function isolate_main_content( $html ) {
		if ( ! $html ) {
			return '';
		}
		// Prefer <main>, then <article>, else <body> minus chrome.
		//
		// v0.76.3: take the LARGEST candidate, not the first, and never a list
		// item. Taking the first match silently scored the wrong element on
		// any page whose related-posts grid precedes or replaces the body
		// wrapper: an Elementor posts widget emits
		// <article class="elementor-post elementor-grid-item" role="listitem">
		// per card, so the first <article> on the page is a teaser holding a
		// title and a link. Measured on erofirving 2026-08-27: real blog posts
		// scored word_count=21 and collapsed to poor/weak, because the scorer
		// was reading a related-post card instead of the article. Three sites
		// flipped to verdict=fail on that artifact alone.
		$best = self::largest_block( $html, 'main' );
		if ( '' === $best ) {
			$best = self::largest_block( $html, 'article' );
		}
		if ( '' !== $best ) {
			return $best;
		}
		if ( preg_match( '/<body\b[^>]*>([\s\S]*?)<\/body>/is', $html, $m ) ) {
			$body = $m[1];
			$body = preg_replace( '/<header\b[^>]*>[\s\S]*?<\/header>/i', '', $body );
			$body = preg_replace( '/<nav\b[^>]*>[\s\S]*?<\/nav>/i', '', $body );
			$body = preg_replace( '/<aside\b[^>]*>[\s\S]*?<\/aside>/i', '', $body );
			$body = preg_replace( '/<footer\b[^>]*>[\s\S]*?<\/footer>/i', '', $body );
			// Related-posts / recent-posts cards are navigation to OTHER pages,
			// not this page's content. Elementor marks each one role="listitem".
			// Leaving them in inflates word count with other posts' titles and
			// drags originality, since the same card set repeats site-wide.
			$body = preg_replace( '/<article\b[^>]*role\s*=\s*["\']listitem["\'][^>]*>[\s\S]*?<\/article>/i', '', $body );
			return $body;
		}
		return $html;
	}

	public static function check_post( $post_id ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-link-audit.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}

		// Em-dash, AI-tells, banned phrases, and the style-guide compliance
		// check are all English-tuned. Running them on a Spanish/French/etc.
		// translation produces noise — em dash is normal Spanish dialogue
		// punctuation, AI-tell phrases are English, and the style guide is
		// usually written in one language. Skip those checks on non-default-
		// language posts and surface a clear "skipped" reason instead.
		$post_lang     = CC_Assistant_Multilingual::language_of( (int) $post_id );
		$default_lang  = CC_Assistant_Multilingual::default_language();
		$skip_eng_only = ( null !== $post_lang && $post_lang !== $default_lang );

		$has_elementor      = (bool) get_post_meta( $post_id, '_elementor_data', true );
		$body               = '';
		$body_html          = ''; // Raw HTML across all sources, for img/regex checks.
		$headings           = array();
		$has_alt            = true;
		$missing_alt_count  = 0;
		$missing_alt_total  = 0; // running total across sources

		if ( $has_elementor ) {
			$parsed = CC_Assistant_Elementor_Parser::parse( $post_id );
			if ( $parsed ) {
				$body     = $parsed['all_text'];
				$headings = $parsed['headings'];
				foreach ( $parsed['widgets'] as $w ) {
					// Elementor image widgets carry alt in settings.image.alt.
					if ( 'image' === $w['type'] && empty( $w['meta']['alt'] ) ) {
						$missing_alt_count++;
					}
					// Inline <img> inside text-editor/html widgets — the existing
					// check missed these. Walk the editor field for raw img tags.
					if ( in_array( $w['type'], array( 'text-editor', 'theme-post-content', 'html', 'shortcode' ), true ) ) {
						// Reconstruct the HTML chunk for this widget to scan.
						// extract_widget already stripped the HTML into 'text', so we
						// re-read _elementor_data for the editor field.
					}
				}
				// Also walk the raw _elementor_data for inline <img> inside text
				// editors / HTML widgets — the parser strips HTML to plain text so
				// inline images inside rich-text get missed otherwise.
				$raw       = (string) get_post_meta( $post_id, '_elementor_data', true );
				$body_html = $raw; // contains escaped HTML; sufficient for regex img + heading scans
				if ( preg_match_all( '/<img\b[^>]*>/i', $raw, $imgs ) ) {
					foreach ( $imgs[0] as $img ) {
						$has_alt_attr = preg_match( '/\balt\s*=\s*[\'"][^\'"]*[\'"]/i', $img );
						$is_empty_alt = preg_match( '/\balt\s*=\s*[\'"]\s*[\'"]/i', $img );
						if ( ! $has_alt_attr || $is_empty_alt ) {
							$missing_alt_count++;
						}
					}
				}
			}
		} else {
			$body      = wp_strip_all_tags( $post->post_content );
			$body_html = (string) $post->post_content;
			if ( preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', $post->post_content, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $row ) {
					$headings[] = array( 'level' => 'h' . $row[1], 'text' => trim( wp_strip_all_tags( $row[2] ) ) );
				}
			}
			if ( preg_match_all( '#<img\s+[^>]*>#i', $post->post_content, $imgs ) ) {
				foreach ( $imgs[0] as $img ) {
					$has_alt_attr = preg_match( '/\balt\s*=\s*[\'"][^\'"]*[\'"]/i', $img );
					$is_empty_alt = preg_match( '/\balt\s*=\s*[\'"]\s*[\'"]/i', $img );
					if ( ! $has_alt_attr || $is_empty_alt ) {
						$missing_alt_count++;
					}
				}
			}
		}
		$has_alt = ( 0 === $missing_alt_count );

		$word_count = $body ? str_word_count( $body ) : 0;
		$checks     = array();

		// Em dash check (English-tuned: em-dash is normal punctuation in Spanish/French dialogue).
		if ( $skip_eng_only ) {
			$checks['em_dashes'] = array(
				'pass'    => true,
				'skipped' => true,
				'reason'  => sprintf( 'Skipped — post language is "%s", check is tuned for "%s" content.', $post_lang, $default_lang ),
				'message' => 'Skipped (non-default-language post).',
			);
		} else {
			$em_dash_count = substr_count( $body, "\xE2\x80\x94" );
			$checks['em_dashes'] = array(
				'pass'    => 0 === $em_dash_count,
				'count'   => $em_dash_count,
				'message' => $em_dash_count > 0 ? sprintf( '%d em dash(es) found. Replace with periods, commas, or parentheses.', $em_dash_count ) : 'Clean.',
			);
		}

		// AI tells check (phrase list is English).
		if ( $skip_eng_only ) {
			$checks['ai_tells'] = array(
				'pass'    => true,
				'skipped' => true,
				'reason'  => sprintf( 'Skipped — post language is "%s", phrase list is English.', $post_lang ),
				'message' => 'Skipped (non-default-language post).',
			);
		} else {
			$found_tells = array();
			$lower_body  = strtolower( $body );
			foreach ( self::AI_TELLS as $tell ) {
				if ( false !== strpos( $lower_body, $tell ) ) {
					$found_tells[] = $tell;
				}
			}
			$checks['ai_tells'] = array(
				'pass'    => empty( $found_tells ),
				'found'   => $found_tells,
				'message' => empty( $found_tells ) ? 'Clean.' : 'AI-tell phrases detected: ' . implode( ', ', $found_tells ),
			);
		}

		// Heading checks: prefer the rendered HTML as the truth source. The
		// Elementor widget tree (parser output) over-counts because:
		//   - widgets hidden by visibility conditions are still in the JSON
		//   - widgets in disabled sections / responsive overrides are in JSON
		//   - widgets the theme overrides (e.g. theme-post-title) may be wrapped
		// The rendered DOM tells us what Google + a human visitor actually see.
		// If the fetch fails (site offline, hosting block) we fall back to the
		// parser counts and label the source so the UI can show which was used.
		$rendered_html = self::fetch_rendered_html( (int) $post_id );
		$rendered_main = $rendered_html ? self::isolate_main_content( $rendered_html ) : '';
		$source        = $rendered_main ? 'rendered' : 'parsed';

		$h1_count_total    = 0;
		$h2_count          = 0;
		$h3_count          = 0;
		$h4_plus_count     = 0;
		$skipped_examples  = array();
		$empty_headings    = 0;

		if ( 'rendered' === $source ) {
			// Walk every heading tag in the isolated main content in document
			// order. This is the actual post body region of the rendered page.
			if ( preg_match_all( '/<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/i', $rendered_main, $rm, PREG_SET_ORDER ) ) {
				$prev_level = 0;
				foreach ( $rm as $h ) {
					$level = (int) $h[1];
					$text  = trim( wp_strip_all_tags( $h[2] ) );
					if ( '' === $text ) {
						$empty_headings++;
					}
					if ( 1 === $level ) {
						$h1_count_total++;
					} elseif ( 2 === $level ) {
						$h2_count++;
					} elseif ( 3 === $level ) {
						$h3_count++;
					} elseif ( $level >= 4 ) {
						$h4_plus_count++;
					}
					if ( $prev_level > 0 && $level > $prev_level + 1 ) {
						$skipped_examples[] = sprintf( 'H%d → H%d', $prev_level, $level );
					}
					$prev_level = $level;
				}
			}

			// Defensive fallback for Elementor Theme Builder pages: when the
			// theme's <main>/<article> wraps an excerpt list (or anything
			// other than the full post body), isolate_main_content picks the
			// wrong region and counts 0 H2s even though the post has many.
			// Detect the discrepancy by counting H2s in the parsed Elementor
			// tree — if the parser sees 2+ H2s but rendered saw none, the
			// rendered selector missed the body. Fall back to parser counts
			// so heading_depth and heading_h1 reflect actual structure.
			if ( 0 === $h2_count ) {
				$parser_h2_check = 0;
				foreach ( $headings as $h_chk ) {
					$lvl_chk = (int) preg_replace( '/[^0-9]/', '', (string) ( $h_chk['level'] ?? '' ) );
					if ( 2 === $lvl_chk ) {
						$parser_h2_check++;
					}
				}
				if ( $parser_h2_check >= 2 ) {
					$source            = 'parsed_fallback';
					$h1_count_total    = 0;
					$h2_count          = 0;
					$h3_count          = 0;
					$h4_plus_count     = 0;
					$skipped_examples  = array();
					$empty_headings    = 0;
					$body_h1_count     = 0;
					$prev_level        = 0;
					foreach ( $headings as $h ) {
						$level_str = isset( $h['level'] ) ? (string) $h['level'] : '';
						$level     = (int) preg_replace( '/[^0-9]/', '', $level_str );
						if ( 0 === $level ) {
							continue;
						}
						$text = trim( (string) ( $h['text'] ?? '' ) );
						if ( '' === $text ) {
							$empty_headings++;
						}
						if ( 1 === $level ) {
							$body_h1_count++;
						} elseif ( 2 === $level ) {
							$h2_count++;
						} elseif ( 3 === $level ) {
							$h3_count++;
						} elseif ( $level >= 4 ) {
							$h4_plus_count++;
						}
						if ( $prev_level > 0 && $level > $prev_level + 1 ) {
							$skipped_examples[] = sprintf( 'H%d → H%d', $prev_level, $level );
						}
						$prev_level = $level;
					}
					$h1_count_total = $has_elementor ? $body_h1_count : ( 1 + $body_h1_count );
				}
			}
		} else {
			// Parser fallback. Same heading count loop as before.
			$body_h1_count = 0;
			$prev_level    = 0;
			foreach ( $headings as $h ) {
				$level_str = isset( $h['level'] ) ? (string) $h['level'] : '';
				$level     = (int) preg_replace( '/[^0-9]/', '', $level_str );
				if ( 0 === $level ) {
					continue;
				}
				$text = trim( (string) ( $h['text'] ?? '' ) );
				if ( '' === $text ) {
					$empty_headings++;
				}
				if ( 1 === $level ) {
					$body_h1_count++;
				} elseif ( 2 === $level ) {
					$h2_count++;
				} elseif ( 3 === $level ) {
					$h3_count++;
				} elseif ( $level >= 4 ) {
					$h4_plus_count++;
				}
				if ( $prev_level > 0 && $level > $prev_level + 1 ) {
					$skipped_examples[] = sprintf( 'H%d → H%d', $prev_level, $level );
				}
				$prev_level = $level;
			}
			// Title acts as the implicit H1 only when the theme renders one.
			// Elementor Theme Builder pages (and any page where Elementor
			// fully owns the layout) render their H1 inside a heading widget
			// — adding a phantom +1 here double-counts and produces a false
			// "2 H1s found" failure on every Elementor page when the
			// rendered-fetch path is unavailable. Trust the parser count
			// when has_elementor=true; only synthesise the implicit title H1
			// for classic-editor / Gutenberg posts.
			$h1_count_total = $has_elementor ? $body_h1_count : ( 1 + $body_h1_count );
		}

		$h1_ok = 1 === $h1_count_total;
		$checks['heading_h1'] = array(
			'pass'    => $h1_ok,
			'count'   => $h1_count_total,
			'source'  => $source,
			'message' => $h1_ok
				? ( 'rendered' === $source
					? 'Exactly one H1 found in the rendered page.'
					: 'Exactly one H1 (the page title — rendered HTML unavailable, used author content).'
				)
				: sprintf(
					'%d H1s found in the %s page. There should be exactly one. Demote extras to H2.',
					$h1_count_total,
					'rendered' === $source ? 'rendered' : 'authored'
				),
		);

		$has_skipped_level = ! empty( $skipped_examples );
		$checks['heading_skip'] = array(
			'pass'     => ! $has_skipped_level,
			'examples' => array_slice( array_unique( $skipped_examples ), 0, 3 ),
			'source'   => $source,
			'message'  => $has_skipped_level
				? 'Heading hierarchy skips a level: ' . implode( ', ', array_slice( array_unique( $skipped_examples ), 0, 3 ) )
				: 'Hierarchy is sequential.',
		);

		// "Many H2s, no H3s" smell: same trigger paths as before but counts
		// now come from the rendered DOM when available.
		$tmp_word_count = $body ? str_word_count( $body ) : 0;
		$flat_structure = (
			( $h2_count >= 8 && 0 === $h3_count )
			|| ( $h2_count >= 6 && 0 === $h3_count && $tmp_word_count >= 800 )
		);
		$checks['heading_depth'] = array(
			'pass'    => ! $flat_structure,
			'h2_count' => $h2_count,
			'h3_count' => $h3_count,
			'source'  => $source,
			'message' => $flat_structure
				? sprintf( '%d H2s and 0 H3s in a %d-word post. Some H2 sub-sections likely should be H3 children of a parent H2.', $h2_count, $tmp_word_count )
				: sprintf( 'Heading mix: %d H2s, %d H3s, %d H4+ — looks balanced.', $h2_count, $h3_count, $h4_plus_count ),
		);

		// Empty headings (open <h2></h2> with no text) — usually template debris.
		$checks['heading_empty'] = array(
			'pass'    => 0 === $empty_headings,
			'count'   => $empty_headings,
			'source'  => $source,
			'message' => 0 === $empty_headings ? 'No empty headings.' : sprintf( '%d empty heading(s) found. Remove or fill in.', $empty_headings ),
		);

		// Word count
		$thin_threshold     = 300;
		$checks['word_count'] = array(
			'pass'    => $word_count >= $thin_threshold,
			'count'   => $word_count,
			'message' => $word_count < $thin_threshold ? sprintf( 'Only %d words. Thin content threshold is %d.', $word_count, $thin_threshold ) : sprintf( '%d words.', $word_count ),
		);

		// Image alt text — same parser-vs-rendered drift as headings. When
		// rendered HTML is available, count <img> tags in the main content
		// and check each for non-empty alt. This catches images authored
		// outside Elementor widget JSON (template gallery, theme block,
		// dynamic content) and ignores widgets that don't actually render.
		$img_source        = $source;
		$rendered_missing  = null;
		$rendered_total    = null;
		if ( 'rendered' === $source ) {
			$rendered_total = 0;
			$rendered_missing = 0;
			if ( preg_match_all( '/<img\b([^>]*)>/i', $rendered_main, $im ) ) {
				foreach ( $im[1] as $attrs ) {
					$rendered_total++;
					if ( ! preg_match( '/\balt\s*=\s*([\'"])(.*?)\1/i', $attrs, $am ) || '' === trim( $am[2] ) ) {
						$rendered_missing++;
					}
				}
			}
		}
		$alt_pass    = 'rendered' === $img_source ? ( 0 === $rendered_missing ) : $has_alt;
		$alt_missing = 'rendered' === $img_source ? $rendered_missing : $missing_alt_count;
		$alt_total   = 'rendered' === $img_source ? $rendered_total   : null;
		$checks['image_alt'] = array(
			'pass'    => $alt_pass,
			'missing' => (int) $alt_missing,
			'total'   => $alt_total,
			'source'  => $img_source,
			'message' => $alt_pass
				? ( null !== $alt_total ? sprintf( 'All %d image(s) have alt text.', (int) $alt_total ) : 'All images have alt text.' )
				: sprintf( '%d image(s) missing alt text.', (int) $alt_missing ),
		);

		// Meta description (Yoast or Rank Math)
		$meta_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( empty( $meta_desc ) ) {
			$meta_desc = get_post_meta( $post_id, 'rank_math_description', true );
		}
		if ( empty( $meta_desc ) ) {
			$meta_desc = $post->post_excerpt;
		}
		$meta_len = mb_strlen( $meta_desc );
		$checks['meta_description'] = array(
			'pass'    => $meta_len >= 120 && $meta_len <= 160,
			'length'  => $meta_len,
			'message' => self::meta_message( $meta_len ),
		);

		// Source coverage via link audit
		$audit = CC_Assistant_Link_Audit::audit_post( $post_id, false );
		if ( ! is_wp_error( $audit ) ) {
			$auth_count = 0;
			$blocked    = 0;
			foreach ( $audit['links'] as $l ) {
				if ( in_array( $l['domain_class'], array( 'gov', 'edu', 'authority' ), true ) ) {
					$auth_count++;
				}
				if ( 'blocked' === $l['citation_status'] ) {
					$blocked++;
				}
			}
			$checks['authority_links'] = array(
				'pass'    => $auth_count > 0,
				'count'   => $auth_count,
				'message' => $auth_count > 0 ? sprintf( '%d authority/gov/edu source(s) cited.', $auth_count ) : 'No authority sources cited. Add at least one .gov, .edu, or trusted source.',
			);
			$checks['blocked_citations'] = array(
				'pass'    => 0 === $blocked,
				'count'   => $blocked,
				'message' => 0 === $blocked ? 'No competitor citations.' : sprintf( '%d competitor link(s) cited. Replace.', $blocked ),
			);
		}

		// Last-updated date visible
		$last_modified_age_days = floor( ( current_time( 'timestamp' ) - strtotime( $post->post_modified ) ) / 86400 );
		$checks['freshness'] = array(
			'pass'         => $last_modified_age_days <= 365,
			'age_days'     => (int) $last_modified_age_days,
			'message'      => $last_modified_age_days <= 365 ? sprintf( 'Modified %d days ago.', $last_modified_age_days ) : sprintf( 'Last modified %d days ago. Consider a freshness review.', $last_modified_age_days ),
		);

		// E-E-A-T: author bio. WP stores it in user_meta 'description'. A real
		// bio should be at least a sentence — 30 chars is the floor. Anonymous
		// posts (post_author == 0) get a clear failure.
		$author_id  = (int) $post->post_author;
		$author_bio = $author_id ? trim( (string) get_the_author_meta( 'description', $author_id ) ) : '';
		$bio_ok     = $author_id > 0 && mb_strlen( $author_bio ) >= 30;
		$checks['author_bio'] = array(
			'pass'      => $bio_ok,
			'author_id' => $author_id,
			'bio_chars' => mb_strlen( $author_bio ),
			'message'   => 0 === $author_id
				? 'Post has no author. Assign one with a real bio.'
				: ( $bio_ok
					? sprintf( 'Author bio present (%d chars).', mb_strlen( $author_bio ) )
					: sprintf( 'Author "%s" has no bio (or %d chars). Add one in Users → Profile → Biographical Info.', get_the_author_meta( 'display_name', $author_id ), mb_strlen( $author_bio ) )
				),
		);

		// E-E-A-T: schema (JSON-LD) presence. Yoast and Rank Math both inject
		// schema on the front-end via filters, so checking the rendered page
		// is more reliable than guessing from postmeta. The cheapest reliable
		// signal: check the SEO plugin's output. Falls back to scanning
		// _elementor_data / post_content for inline JSON-LD.
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
		$seo            = CC_Assistant_Site_Memory::detect_seo();
		$schema_present = false;
		$schema_source  = '';
		if ( ! empty( $seo['plugin'] ) && in_array( $seo['plugin'], array( 'yoast', 'rank-math' ), true ) ) {
			// Both Yoast and Rank Math output Article/WebPage by default. Treat
			// "SEO plugin active and post is published" as a soft pass.
			$schema_present = true;
			$schema_source  = $seo['plugin_name'];
		} elseif ( false !== stripos( (string) $body_html, 'application/ld+json' ) ) {
			$schema_present = true;
			$schema_source  = 'inline JSON-LD';
		}
		$checks['schema'] = array(
			'pass'    => $schema_present,
			'source'  => $schema_source,
			'message' => $schema_present
				? sprintf( 'Schema markup present via %s.', $schema_source )
				: 'No schema markup detected. Article/FAQ/HowTo schema helps SERP features.',
		);

		// E-E-A-T: source density. Authority/.gov/.edu sources cited per 500
		// words of body. < 1 per 1000 words = thinly sourced for a research-y
		// topic. We use the link-audit data already computed above.
		if ( isset( $checks['authority_links']['count'] ) ) {
			$auth_count = (int) $checks['authority_links']['count'];
			$body_word_count = $body ? str_word_count( $body ) : 0;
			$density_ratio   = $body_word_count > 0 ? ( $auth_count * 1000 / $body_word_count ) : 0;
			$density_ok      = $body_word_count < 600 || $density_ratio >= 1.0;
			$checks['source_density'] = array(
				'pass'      => $density_ok,
				'sources'   => $auth_count,
				'words'     => $body_word_count,
				'per_1000'  => round( $density_ratio, 2 ),
				'message'   => $body_word_count < 600
					? 'Post is short — source density not enforced.'
					: ( $density_ok
						? sprintf( '%d authority sources for %d words (%.1f per 1000).', $auth_count, $body_word_count, $density_ratio )
						: sprintf( '%d authority sources for %d words. Aim for at least 1 per 1000 (currently %.1f).', $auth_count, $body_word_count, $density_ratio )
					),
			);
		}

		// SERP-feature linter: featured snippets favor 40-60 word answers
		// directly under question H2s. Walk headings, find question-shaped H2s,
		// measure the words between that H2 and the next heading.
		// SERP snippet shape: prefer rendered HTML (real DOM with real word
		// counts under each H2). Fall back to authored HTML when rendered
		// is unavailable.
		$serp_html = ( 'rendered' === $source && $rendered_main ) ? $rendered_main : $body_html;
		if ( ! empty( $serp_html ) ) {
			$snippet_misses = self::analyze_question_h2_answers( $serp_html );
			$checks['serp_snippet'] = array(
				'pass'     => empty( $snippet_misses ),
				'misses'   => array_slice( $snippet_misses, 0, 5 ),
				'source'   => $source,
				'message'  => empty( $snippet_misses )
					? 'Question-shaped H2 answers look snippet-friendly (or none found).'
					: sprintf( '%d question H2(s) have answers outside the 40-60 word featured-snippet sweet spot.', count( $snippet_misses ) ),
			);
		}

		// Style guide compliance: scan body for any banned phrase parsed
		// from the user's style guide blob. Em dashes are skipped here —
		// they have their own dedicated check. Style guides are normally
		// authored in one language; skip the check on translations to avoid
		// false-pass results that look meaningful but tested nothing.
		if ( $skip_eng_only ) {
			$checks['style_guide'] = array(
				'pass'    => true,
				'skipped' => true,
				'reason'  => sprintf( 'Skipped — post language is "%s", style guide is authored in "%s".', $post_lang, $default_lang ),
				'message' => 'Skipped (non-default-language post).',
			);
		} else {
			$banned = self::style_guide_banned_phrases();
			if ( ! empty( $banned ) && ! empty( $body ) ) {
				$lc_body = mb_strtolower( $body );
				$found_banned = array();
				foreach ( $banned as $p ) {
					if ( false !== strpos( $lc_body, $p ) ) {
						$found_banned[] = $p;
					}
				}
				$checks['style_guide'] = array(
					'pass'    => empty( $found_banned ),
					'found'   => array_slice( $found_banned, 0, 8 ),
					'count'   => count( $found_banned ),
					'message' => empty( $found_banned )
						? 'No banned phrases from your style guide found.'
						: sprintf( '%d banned phrase(s) found: %s.', count( $found_banned ), implode( ', ', array_slice( $found_banned, 0, 5 ) ) ),
				);
			}
		}

		// Body-block readability checks: paragraph length, sentence length,
		// reading level, HTML cruft, jargon. These all run on the same body
		// HTML the queue endpoint will lint, so live posts and proposed
		// drafts use the same logic and the same labels.
		//
		// IMPORTANT for Elementor posts: $body_html holds the raw
		// _elementor_data JSON tree (used elsewhere on this method for
		// regex img scans). Passing that JSON to the readability linter
		// produces nonsense — the parser sees `[{"id":"abcd","elType":...`
		// as one giant 200-word "sentence" and every Elementor page fails
		// paragraph_length / sentence_length / reading_level. The previous
		// fix used wpautop($body) on the parser's plain-text all_text,
		// but that flattens every widget to a single <p> (no real
		// paragraph boundaries inside a widget) — paragraph_length then
		// reports a multi-<p> widget like About Us as one giant 11-
		// sentence paragraph. Instead, walk _elementor_data and pull the
		// actual editor/content/html fields, which preserve the original
		// <p> structure and give the linter realistic per-paragraph
		// boundaries.
		if ( $has_elementor ) {
			$elementor_data = (string) get_post_meta( $post_id, '_elementor_data', true );
			$lint_html      = self::extract_elementor_html_for_lint( $elementor_data );
			if ( '' === trim( $lint_html ) ) {
				$lint_html = wpautop( $body ); // belt-and-suspenders fallback
			}
		} else {
			$lint_html = $body_html;
		}
		$body_checks = self::lint_html_block( $lint_html );
		foreach ( array( 'paragraph_length', 'sentence_length', 'reading_level', 'html_cruft', 'jargon' ) as $name ) {
			if ( isset( $body_checks[ $name ] ) ) {
				$checks[ $name ] = $body_checks[ $name ];
			}
		}

		// Featured image: applies to any post type that supports thumbnails.
		// Posts/pages without a featured image render bare on lists, OG cards,
		// and social shares.
		if ( post_type_supports( $post->post_type, 'thumbnail' ) ) {
			$has_thumb = has_post_thumbnail( $post_id );
			$checks['featured_image'] = array(
				'pass'    => $has_thumb,
				'message' => $has_thumb
					? 'Featured image set.'
					: 'No featured image. Posts without one look bare in lists, social shares, and OG cards.',
			);
		}

		// Manual excerpt: when blank, themes/feeds auto-generate a usually-
		// unflattering preview. Apply only to post types that support excerpts.
		if ( post_type_supports( $post->post_type, 'excerpt' ) ) {
			$has_excerpt = '' !== trim( (string) $post->post_excerpt );
			$checks['excerpt'] = array(
				'pass'    => $has_excerpt,
				'length'  => strlen( (string) $post->post_excerpt ),
				'message' => $has_excerpt
					? 'Manual excerpt set.'
					: 'No manual excerpt. Themes and feeds will auto-generate one from the body.',
			);
		}

		// Real category: only on post types that use the "category" taxonomy.
		// Treat "no category" or "only Uncategorized" as a fail; both hurt SEO
		// and navigation. wp_get_post_categories returns term IDs.
		if ( is_object_in_taxonomy( $post->post_type, 'category' ) ) {
			$cat_ids   = wp_get_post_categories( $post_id );
			$cat_terms = array();
			foreach ( $cat_ids as $cid ) {
				$t = get_term( (int) $cid, 'category' );
				if ( $t && ! is_wp_error( $t ) ) {
					$cat_terms[] = $t;
				}
			}
			$only_uncategorized = ( 1 === count( $cat_terms ) && 'uncategorized' === strtolower( $cat_terms[0]->slug ) );
			$no_category        = empty( $cat_terms );
			$checks['categories'] = array(
				'pass'    => ! $no_category && ! $only_uncategorized,
				'count'   => count( $cat_terms ),
				'names'   => array_map( static function ( $t ) { return $t->name; }, $cat_terms ),
				'message' => $no_category
					? 'No category assigned.'
					: ( $only_uncategorized
						? 'Only assigned to "Uncategorized" — pick a category that matches the topic.'
						: sprintf( 'Assigned to %d categor%s.', count( $cat_terms ), 1 === count( $cat_terms ) ? 'y' : 'ies' ) ),
			);
		}

		$pass_count = 0;
		$fail_count = 0;
		foreach ( $checks as $c ) {
			if ( ! empty( $c['pass'] ) ) {
				$pass_count++;
			} else {
				$fail_count++;
			}
		}

		return array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'permalink'  => get_permalink( $post_id ),
			'word_count' => $word_count,
			'pass'       => 0 === $fail_count,
			'summary'    => array(
				'pass_count'  => $pass_count,
				'fail_count'  => $fail_count,
				'total'       => count( $checks ),
			),
			'checks'     => $checks,
		);
	}

	private static function meta_message( $len ) {
		if ( 0 === $len ) {
			return 'No meta description set. Add one (120-160 chars).';
		}
		if ( $len < 120 ) {
			return sprintf( 'Meta description is %d chars. Aim for 120-160.', $len );
		}
		if ( $len > 160 ) {
			return sprintf( 'Meta description is %d chars. Trim to 160 to avoid truncation.', $len );
		}
		return sprintf( 'Meta description length OK (%d chars).', $len );
	}

	/**
	 * Lint an SEO meta field (title or description) at queue time. Runs a
	 * focused subset of checks tuned to the field type: length window
	 * appropriate to the field, em dashes (almost always wrong in titles),
	 * banned phrases from the style guide, and optional keyword coverage
	 * when the operator passes a target_query.
	 *
	 * Returns the same {pass, pass_count, fail_count, hard_violations,
	 * checks, generated_at} envelope as lint_post_content_change so the
	 * inbox lint card and the queue response can render it identically.
	 */
	public static function lint_seo_meta( $logical_key, $value, $target_query = '' ) {
		$value  = (string) $value;
		$plain  = trim( $value );
		$lower  = mb_strtolower( $plain );
		$checks = array();

		// Length windows by field. Title is the SERP listing label so the
		// 50-60 sweet spot matters more than the description's 120-160.
		// Other meta fields (canonical, focus_keyword, og_*) are too varied
		// to gate on length, so we skip the length check there.
		$length_windows = array(
			'title'          => array( 'min' => 30, 'max' => 60 ),
			'description'    => array( 'min' => 120, 'max' => 160 ),
			'og_title'       => array( 'min' => 30, 'max' => 90 ),
			'og_description' => array( 'min' => 90, 'max' => 200 ),
		);
		if ( isset( $length_windows[ $logical_key ] ) ) {
			$min = $length_windows[ $logical_key ]['min'];
			$max = $length_windows[ $logical_key ]['max'];
			$len = mb_strlen( $plain );
			$checks['meta_length'] = array(
				'pass'    => $len >= $min && $len <= $max,
				'length'  => $len,
				'min'     => $min,
				'max'     => $max,
				'message' => $len < $min
					? sprintf( '%d chars. Aim for %d-%d so the snippet earns its full SERP slot.', $len, $min, $max )
					: ( $len > $max
						? sprintf( '%d chars. Trim to %d to avoid truncation in the SERP.', $len, $max )
						: sprintf( 'Length OK (%d chars).', $len )
					),
			);
		}

		// Em dashes — almost always wrong in titles and meta descriptions.
		$em_dash_count = substr_count( $plain, "\xE2\x80\x94" );
		$checks['em_dashes'] = array(
			'pass'    => 0 === $em_dash_count,
			'count'   => $em_dash_count,
			'message' => $em_dash_count > 0
				? sprintf( '%d em dash(es). Use a vertical bar, comma, or colon in titles instead.', $em_dash_count )
				: 'No em dashes.',
		);

		// Style-guide banned phrases (em dashes excluded — own check above).
		$banned       = self::style_guide_banned_phrases();
		$found_banned = array();
		foreach ( $banned as $p ) {
			if ( false !== strpos( $lower, $p ) ) {
				$found_banned[] = $p;
			}
		}
		if ( ! empty( $banned ) ) {
			$checks['style_guide'] = array(
				'pass'    => empty( $found_banned ),
				'found'   => $found_banned,
				'message' => empty( $found_banned )
					? 'No banned phrases.'
					: sprintf( 'Banned phrase(s) in meta: %s.', implode( ', ', $found_banned ) ),
			);
		}

		// AI-tells — title/description are short; even one AI-tell is glaring.
		$found_tells = array();
		foreach ( self::AI_TELLS as $tell ) {
			if ( false !== strpos( $lower, $tell ) ) {
				$found_tells[] = $tell;
			}
		}
		$checks['ai_tells'] = array(
			'pass'    => empty( $found_tells ),
			'found'   => $found_tells,
			'message' => empty( $found_tells ) ? 'No AI-tell phrases.' : 'AI-tell phrases: ' . implode( ', ', $found_tells ),
		);

		// Suspicious characters that can break SERP rendering or look like
		// copy-paste residue: zero-width joiners, BiDi marks, BOM, NBSP,
		// multiple spaces, edge whitespace.
		//
		// v0.20.2: the prior zero-width pattern was
		//   /[\xE2\x80\x8B-\xE2\x80\x8F]/
		// which in a PHP character class is parsed as the BYTE range 0x8B
		// through 0xE2 (88 bytes!) plus a couple of literal bytes. That
		// matched the lead byte of essentially every multi-byte UTF-8
		// character — Spanish accents (á=\xC3\xA1), em dashes, curly quotes,
		// emoji, etc. Replaced with a proper Unicode-aware codepoint range
		// using the /u flag so only true zero-width/BiDi marks are flagged.
		$suspicious = array();
		if ( preg_match( '/\xC2\xA0/', $value ) ) {
			$suspicious[] = 'non-breaking space';
		}
		if ( preg_match( '/[\x{200B}-\x{200F}\x{FEFF}\x{202A}-\x{202E}]/u', $value ) ) {
			$suspicious[] = 'zero-width or bidi characters';
		}
		if ( preg_match( '/  +/', $value ) ) {
			$suspicious[] = 'double spaces';
		}
		if ( preg_match( '/^\s|\s$/', $value ) ) {
			$suspicious[] = 'leading or trailing whitespace';
		}
		$checks['suspicious_chars'] = array(
			'pass'    => empty( $suspicious ),
			'found'   => $suspicious,
			'message' => empty( $suspicious ) ? 'Clean characters.' : 'Suspicious: ' . implode( ', ', $suspicious ) . '. Strip or normalise.',
		);

		// Keyword coverage — when the operator declared a target_query, the
		// new title/description should contain it (or a close variant) so the
		// snippet matches the query terms a user typed.
		//
		// v0.20.1: normalize ampersand <-> "and" on BOTH sides (so target
		// "vomiting and diarrhea" matches value "Vomiting & Diarrhea") and
		// add light singular/plural stemming to the word-overlap fallback
		// (so target "kidney emergencies" matches value "Kidney Emergency"
		// and vice versa). Both directions were real failure modes in prior
		// sessions that forced needless re-queues.
		if ( '' !== trim( (string) $target_query ) ) {
			$normalize = function ( $s ) {
				$s = mb_strtolower( (string) $s );
				$s = str_replace( array( '&amp;', '&' ), 'and', $s );
				$s = preg_replace( '/\s+/', ' ', $s );
				return trim( (string) $s );
			};
			$tq         = $normalize( $target_query );
			$lower_norm = $normalize( $lower );
			$contains   = false !== strpos( $lower_norm, $tq );

			// v0.44.x: stopword-aware token coverage. The old exact-substring +
			// 80%-of-all-words fallback produced two real failure classes:
			// (1) it demanded "near me" appear verbatim in a meta title (which
			// would be spam — "near me" is how users SEARCH, not how titles
			// should read), and (2) it failed "when to see doctor for poison
			// ivy" against copy saying "when to see a doctor" over the
			// inserted article. New rule: tokenize the target, drop stopwords,
			// then PASS only when ALL remaining significant tokens appear
			// (substring per token, or a shared 5+ char word prefix so
			// "doctors" matches "doctor").
			$stopwords = array(
				'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'to', 'of',
				'in', 'on', 'at', 'for', 'with', 'and', 'or', 'near', 'me',
				'my', 'your', 'you', 'i', 'it', 'its', 'do', 'does', 'how',
				'what', 'when', 'where', 'why', 'can', 'get', 'need', 'should',
			);
			// Unicode-aware split (accented Spanish queries must not shatter
			// into fragments); single-character fragments are noise either way.
			$split_words = function ( $s ) {
				$w = preg_split( '/[^\p{L}\p{N}]+/u', (string) $s, -1, PREG_SPLIT_NO_EMPTY );
				if ( ! is_array( $w ) ) {
					$w = preg_split( '/[^a-z0-9]+/', (string) $s, -1, PREG_SPLIT_NO_EMPTY );
				}
				return array_values( array_filter( (array) $w, function ( $t ) {
					return mb_strlen( $t ) > 1;
				} ) );
			};
			$tq_words    = $split_words( $tq );
			$dropped     = array_values( array_unique( array_intersect( $tq_words, $stopwords ) ) );
			$significant = array_values( array_diff( $tq_words, $stopwords ) );

			$matched_via_tokens = false;
			$missing            = array();
			if ( ! $contains ) {
				$hay_words = $split_words( $lower_norm );
				$word_present = function ( $word ) use ( $hay_words ) {
					$wlen = mb_strlen( $word );
					foreach ( $hay_words as $hw ) {
						if ( $hw === $word ) {
							return true;
						}
						// Short tokens (2-4 chars): whole-word or simple plural
						// only — substring matching let "room" pass inside
						// "rooming houses".
						if ( $wlen < 5 ) {
							if ( $hw === $word . 's' || $hw === $word . 'es' ) {
								return true;
							}
							continue;
						}
						// Long tokens: length-proportional shared prefix, so
						// "doctors" matches "doctor" (5 of 7) but "emergency"
						// does NOT match "emerging" (needs 6 shared chars).
						$k = max( 5, $wlen - 3 );
						if ( mb_strlen( $hw ) >= $k && mb_substr( $hw, 0, $k ) === mb_substr( $word, 0, $k ) ) {
							return true;
						}
					}
					return false;
				};
				foreach ( $significant as $w ) {
					if ( ! $word_present( $w ) ) {
						$missing[] = $w;
					}
				}
				// All significant tokens present (an all-stopword target has
				// nothing significant to require and passes trivially).
				$matched_via_tokens = empty( $missing );
				$contains           = $matched_via_tokens;
			}

			$pass_message = ( $matched_via_tokens && ! empty( $dropped ) )
				? sprintf( 'Target query "%s" present (or close variant; stopwords ignored: %s).', $target_query, implode( ', ', $dropped ) )
				: sprintf( 'Target query "%s" present (or close variant).', $target_query );
			$fail_message = sprintf( 'Target query "%s" is not in the new %s. Snippet will not match what the user typed.', $target_query, $logical_key );
			if ( ! $contains && ! empty( $missing ) ) {
				$fail_message .= sprintf( ' Missing significant tokens: %s.', implode( ', ', $missing ) );
				if ( ! empty( $dropped ) ) {
					$fail_message .= sprintf( ' (Stopwords already ignored: %s.)', implode( ', ', $dropped ) );
				}
			}
			$checks['keyword_coverage'] = array(
				'pass'         => $contains,
				'target_query' => $target_query,
				'message'      => $contains ? $pass_message : $fail_message,
			);
		}

		$pass = 0;
		$fail = 0;
		$hard = array();
		foreach ( $checks as $name => $c ) {
			if ( ! empty( $c['pass'] ) ) {
				$pass++;
			} else {
				$fail++;
				if ( in_array( $name, array( 'em_dashes', 'ai_tells', 'style_guide', 'wall_of_text', 'address_consistency', 'hospital_comparison' ), true ) ) {
					$hard[] = $name;
				}
			}
		}
		return array(
			'pass'            => 0 === $fail,
			'pass_count'      => $pass,
			'fail_count'      => $fail,
			'total'           => count( $checks ),
			'hard_violations' => $hard,
			'checks'          => $checks,
			'generated_at'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Run readability + style checks on a free-floating HTML blob. Used by
	 * the queue endpoint so a body rewrite gets linted before the pending
	 * row is created, even though there is no live post to compare yet.
	 *
	 * Returns the same shape as check_post()['checks'] for the subset of
	 * checks that operate on body HTML alone:
	 *   - em_dashes, ai_tells, style_guide
	 *   - paragraph_length, sentence_length, reading_level
	 *   - html_cruft, jargon
	 *
	 * No DB queries, no HTTP — safe to call inside a REST request handler.
	 */
	/**
	 * @param string     $html               HTML to lint.
	 * @param array|null $settings_for_extra Optional widget settings array. When provided,
	 *                                       enables address_consistency_check and
	 *                                       palette_compliance_check which need access
	 *                                       to non-text fields (links, hex colors).
	 *                                       Pass null for legacy callers that only have
	 *                                       a plain HTML string.
	 * @param bool       $skip_wall_of_text  When true (used by lint_html_block_per_widget
	 *                                       on aggregated blobs), the wall_of_text check
	 *                                       is omitted from the returned report so it can
	 *                                       be re-run per-widget. Default false preserves
	 *                                       legacy behavior.
	 */
	public static function lint_html_block( $html, $settings_for_extra = null, $skip_wall_of_text = false, $current_html = '' ) {
		$checks = array();
		$html   = (string) $html;
		// v0.62: decode HTML entities BEFORE any pattern check — &#8212; and
		// &mdash; rendered as em dashes on the live page but sailed past the
		// em-dash lint for months (a documented bypass). Decoding here closes
		// the entity loophole for every text check in this function at once.
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Convert block-element boundaries into sentence boundaries before
		// stripping tags. Heading widgets, list items, and table cells in
		// Elementor / Gutenberg pages typically don't end with punctuation —
		// without this normalization the sentence splitter (which only
		// breaks on .!?) glues a heading + the next paragraph + the
		// paragraph after that into one 80-word "sentence" and every page
		// fails sentence_length. Insert a period before each block-closing
		// tag whose inner text doesn't already end in punctuation.
		// NOTE the space in the replacement. wp_strip_all_tags() removes tags
		// without inserting whitespace, so '$1.$2' produced "Inserts.</h2>" ->
		// "Inserts.A fire pit insert is..." with nothing between them. The
		// splitter below requires terminator + WHITESPACE, so the heading never
		// separated and was measured as part of the next sentence: a 5-word
		// heading plus a 21-word sentence read as one 26-word run-on and tripped
		// sentence_length. This normalization was already meant to prevent
		// exactly that gluing; it just needed the separator as well as the
		// terminator. Found on sids-ponds 2026-08-23.
		$bounded = preg_replace(
			'/([^\.\?\!\s>])\s*(<\/(?:p|h[1-6]|li|td|th|blockquote|caption|figcaption|div)>)/iu',
			'$1. $2',
			$html
		);
		// The rule above only fires when the block does NOT already end in
		// punctuation. A heading or paragraph that DOES end in a period still
		// got concatenated to the next block, because wp_strip_all_tags removes
		// the tags without leaving whitespace behind ("...hardscaping.What is
		// the difference..."). Guarantee a separator at every block boundary so
		// the sentence splitter can see one.
		$bounded = preg_replace(
			'/(<\/(?:p|h[1-6]|li|td|th|tr|blockquote|caption|figcaption|div|ul|ol|table|section|article)>)/iu',
			' $1',
			$bounded
		);
		$plain  = trim( wp_strip_all_tags( (string) $bounded ) );
		$plain  = preg_replace( '/\s+/u', ' ', $plain );

		// Em dashes.
		$em_dash_count = substr_count( $plain, "\xE2\x80\x94" );
		$checks['em_dashes'] = array(
			'pass'    => 0 === $em_dash_count,
			'count'   => $em_dash_count,
			'message' => $em_dash_count > 0
				? sprintf( '%d em dash(es) in proposed content. Replace with periods, commas, or parentheses.', $em_dash_count )
				: 'No em dashes.',
		);

		// AI-tell phrases. v0.35: word-boundary regex instead of substring
		// match — previously "leverage" matched "leveraged", "delve" matched
		// "delved", etc. The \b anchor + preg_quote on the phrase keeps
		// multi-word tells (e.g. "in conclusion") intact while preventing
		// the substring false-positives.
		$lower_plain = mb_strtolower( $plain );
		$found_tells = array();
		foreach ( self::AI_TELLS as $tell ) {
			$pattern = '/\b' . preg_quote( $tell, '/' ) . '\b/iu';
			if ( preg_match( $pattern, $lower_plain ) ) {
				$found_tells[] = $tell;
			}
		}
		$checks['ai_tells'] = array(
			'pass'    => empty( $found_tells ),
			'found'   => $found_tells,
			'message' => empty( $found_tells ) ? 'No AI-tell phrases.' : 'AI-tell phrases detected: ' . implode( ', ', $found_tells ),
		);

		// Style-guide banned phrases.
		$banned = self::style_guide_banned_phrases();
		if ( ! empty( $banned ) ) {
			$found_banned = array();
			foreach ( $banned as $p ) {
				if ( false !== strpos( $lower_plain, $p ) ) {
					$found_banned[] = $p;
				}
			}
			$checks['style_guide'] = array(
				'pass'    => empty( $found_banned ),
				'found'   => array_slice( $found_banned, 0, 8 ),
				'count'   => count( $found_banned ),
				'message' => empty( $found_banned )
					? 'No banned phrases from your style guide.'
					: sprintf( '%d banned phrase(s): %s.', count( $found_banned ), implode( ', ', array_slice( $found_banned, 0, 5 ) ) ),
			);
		}

		// v0.19 placeholder lint. Catches the exact failure mode that put `$TBD`
		// onto the live Laser Genesis page: any `$TBD`, `[TBD]`, `[PLACEHOLDER]`,
		// `Lorem ipsum`, standalone TBD/TODO, or `{{handlebars}}` slot escapes
		// the build into the queue. Hard violation. Operator can pass
		// override_lint=true to ship anyway (rare — usually means the
		// placeholder is intentional copy like "TBD" in a glossary entry).
		$placeholder_patterns = self::placeholder_patterns();
		$found_placeholders = array();
		foreach ( $placeholder_patterns as $pat ) {
			if ( preg_match_all( $pat, $plain, $m ) ) {
				foreach ( $m[0] as $hit ) {
					$found_placeholders[] = $hit;
				}
			}
		}
		$found_placeholders = array_unique( $found_placeholders );
		$checks['placeholders'] = array(
			'pass'    => empty( $found_placeholders ),
			'found'   => array_slice( $found_placeholders, 0, 8 ),
			'count'   => count( $found_placeholders ),
			'message' => empty( $found_placeholders )
				? 'No placeholder markers.'
				: sprintf( '%d placeholder marker(s) in content: %s. Fill these in before queueing.', count( $found_placeholders ), implode( ', ', array_slice( $found_placeholders, 0, 5 ) ) ),
		);

		// Paragraph length: extract <p> contents and sentences in lists/headings.
		// Threshold raised from 3 to 5 in v0.10.31 to align with the meaning-based
		// paragraph rule: breaks should follow idea/argument/emphasis shifts, not
		// a fixed sentence count. 4-5 sentence paragraphs are valid when they
		// develop one coherent point; 6+ starts to read as wall-of-text on mobile.
		$paragraph_violations = self::paragraph_length_violations( $html );

		// v0.62: PRE-EXISTING long paragraphs are debt, not a blocker. When
		// the caller supplies the CURRENT content, violations already present
		// there are split out — a one-word edit to a post whose inherited
		// body has a 7-sentence paragraph used to be refused outright (the
		// documented paragraph_length trap that forced override_lint).
		$pre_existing = array();
		if ( '' !== (string) $current_html ) {
			$current_viol = array_map(
				function ( $v ) {
					return md5( preg_replace( '/\s+/u', ' ', trim( is_array( $v ) && isset( $v['snippet'] ) ? $v['snippet'] : (string) ( is_array( $v ) ? wp_json_encode( $v ) : $v ) ) ) );
				},
				self::paragraph_length_violations( html_entity_decode( (string) $current_html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) )
			);
			$new_viol = array();
			foreach ( $paragraph_violations as $v ) {
				$key = md5( preg_replace( '/\s+/u', ' ', trim( is_array( $v ) && isset( $v['snippet'] ) ? $v['snippet'] : (string) ( is_array( $v ) ? wp_json_encode( $v ) : $v ) ) ) );
				if ( in_array( $key, $current_viol, true ) ) {
					$pre_existing[] = $v;
				} else {
					$new_viol[] = $v;
				}
			}
			$paragraph_violations = $new_viol;
		}
		$checks['paragraph_length'] = array(
			'pass'        => empty( $paragraph_violations ),
			'violations'  => array_slice( $paragraph_violations, 0, 5 ),
			'over_count'  => count( $paragraph_violations ),
			'pre_existing_count' => count( $pre_existing ),
			'message'     => empty( $paragraph_violations )
				? ( empty( $pre_existing ) ? 'Paragraphs are 5 sentences or fewer.' : sprintf( 'No NEW long paragraphs (%d pre-existing one(s) unchanged — debt, not a blocker).', count( $pre_existing ) ) )
				: sprintf( '%d paragraph(s) exceed 5 sentences. Consider breaking at the next meaning shift.', count( $paragraph_violations ) ),
		);

		// Sentence length: word counts per sentence across all body text.
		$sentence_stats = self::sentence_length_stats( $plain );
		$long_pct       = $sentence_stats['total'] > 0
			? round( 100 * $sentence_stats['long'] / $sentence_stats['total'], 1 )
			: 0;
		$checks['sentence_length'] = array(
			'pass'         => $long_pct <= 15.0,
			'long'         => $sentence_stats['long'],
			'total'        => $sentence_stats['total'],
			'long_percent' => $long_pct,
			'avg_words'    => $sentence_stats['avg_words'],
			'samples'      => array_slice( $sentence_stats['samples'], 0, 3 ),
			'message'      => $long_pct <= 15.0
				? sprintf( '%.1f%% of sentences over 25 words (acceptable).', $long_pct )
				: sprintf( '%.1f%% of sentences over 25 words (target ≤15%%). Break run-ons into two clear sentences.', $long_pct ),
		);

		// Reading level (Flesch-Kincaid grade level).
		$grade = self::flesch_kincaid_grade( $plain );
		$checks['reading_level'] = array(
			'pass'    => $grade <= 10.0,
			'grade'   => $grade,
			'message' => $grade <= 10.0
				? sprintf( 'Flesch-Kincaid grade %.1f (general-audience friendly).', $grade )
				: sprintf( 'Flesch-Kincaid grade %.1f. Aim for 7-9 for laypeople; %.1f reads as academic.', $grade, $grade ),
		);

		// HTML cruft: legacy classic-editor span wrappers and mso-* styles.
		$cruft = self::html_cruft_count( $html );
		$total_cruft = $cruft['font_weight_spans'] + $cruft['mso_styles'] + $cruft['empty_paragraphs'];
		$checks['html_cruft'] = array(
			'pass'              => 0 === $total_cruft,
			'font_weight_spans' => $cruft['font_weight_spans'],
			'mso_styles'        => $cruft['mso_styles'],
			'empty_paragraphs'  => $cruft['empty_paragraphs'],
			'message'           => 0 === $total_cruft
				? 'No HTML cruft detected.'
				: sprintf( 'Cruft: %d <span style="font-weight: 400"> wrappers, %d MSO style attrs, %d empty paragraphs. Strip them.', $cruft['font_weight_spans'], $cruft['mso_styles'], $cruft['empty_paragraphs'] ),
		);

		// Jargon: count domain-specific words that lack a plain-language gloss
		// nearby. We use a static jargon list weighted by 1000 words. The model
		// can extend the list per-site by writing them into the style guide
		// "Jargon" section in the future — for now the list is conservative.
		$jargon_stats = self::jargon_density( $plain );
		$checks['jargon'] = array(
			'pass'         => $jargon_stats['per_1000'] <= 6.0,
			'matches'      => array_slice( $jargon_stats['matches'], 0, 8 ),
			'total_hits'   => $jargon_stats['total'],
			'words'        => $jargon_stats['word_count'],
			'per_1000'     => $jargon_stats['per_1000'],
			'message'      => $jargon_stats['per_1000'] <= 6.0
				? sprintf( '%.1f jargon hits per 1000 words.', $jargon_stats['per_1000'] )
				: sprintf( '%.1f jargon hits per 1000 words. Define them or use plain-language equivalents.', $jargon_stats['per_1000'] ),
		);

		// Wall-of-text guard (v0.27.6). Catches the failure mode where the
		// model dumps 200+ words into a single text-editor widget without any
		// visual structure (no headings, no lists, no tables, no images).
		// Refuses at queue time and recommends an Elementor widget pattern
		// based on the prose shape: bulleted enumerations should become
		// icon-list widgets, Q&A should become accordion items, step language
		// should become icon-box rows, comparisons should become tables.
		// Operator can pass override_lint=true when a long prose block is
		// genuinely the right shape (rare — usually means a layout rethink).
		//
		// v0.35: callers that lint multi-widget payloads pass $skip_wall_of_text=true
		// and re-run wall_of_text per-widget via lint_html_block_per_widget. The
		// other checks above stay aggregated because they're page-level signals.
		if ( ! $skip_wall_of_text ) {
			$wot = self::wall_of_text_check( $html, $plain );
			$checks['wall_of_text'] = $wot;
		}

		// Hospital-comparison ban (v0.35). Operator policy on ER sites: never
		// position the clinic against hospital EDs (citing wait times, "faster
		// than the hospital", etc.). Hard fail on ER sites; no-op everywhere
		// else. See memory entry feedback_no_hospital_comparison.md.
		$checks['hospital_comparison'] = self::hospital_comparison_check( $plain );

		// Per-widget address/palette checks (v0.35) — these need access to
		// the widget settings object (button URLs, hex color fields), not just
		// the text-stripped plain blob. Callers that have the settings hash
		// pass it via $settings_for_extra. lint_html_block_per_widget aggregates
		// these across widgets so a single bad hex / bad street address surfaces
		// even on a container_add with many children.
		if ( is_array( $settings_for_extra ) ) {
			$checks['address_consistency'] = self::address_consistency_check( $settings_for_extra );
			$checks['palette_compliance']  = self::palette_compliance_check( $settings_for_extra );
		}

		// v0.50.3: quote-quality checks on any blockquote in the payload.
		// No current-body context on this path, so every quote is treated as
		// new — correct for widget/import/create payloads, which ARE the
		// model's proposed content.
		foreach ( self::quote_checks( '', $html ) as $name => $check ) {
			$checks[ $name ] = $check;
		}

		return $checks;
	}

	/**
	 * Per-widget lint helper (v0.35). Takes an array of per-widget payloads,
	 * runs the page-level checks (em_dashes, ai_tells, style_guide, etc.)
	 * over the concatenated text, and runs wall_of_text on EACH widget's text
	 * in isolation. Without this, a container_add with 4 grid cards of 80
	 * words each tripped wall_of_text because their text was concatenated
	 * into one 320-word blob with no internal headings.
	 *
	 * @param array $widget_payloads Each entry: [
	 *     'html'     => string,        // The widget's text-bearing fields, joined.
	 *     'settings' => array|null,    // Widget settings hash (for address/palette/hospital).
	 *     'label'    => string,        // Optional. Used in the failure message.
	 * ]
	 * @return array Same shape as lint_html_block() but with a per_widget
	 *               sub-key on wall_of_text recording which child(ren) failed.
	 */
	public static function lint_html_block_per_widget( array $widget_payloads ) {
		// 1. Aggregate text for page-level checks (em_dashes / ai_tells /
		//    style_guide / placeholders / paragraph / sentence / reading /
		//    html_cruft / jargon). Aggregate settings too so address_consistency
		//    + palette_compliance see every widget's links / hex colors.
		$aggregated_html     = '';
		$aggregated_settings = array();
		foreach ( $widget_payloads as $w ) {
			if ( isset( $w['html'] ) && is_string( $w['html'] ) ) {
				$aggregated_html .= "\n" . $w['html'];
			}
			if ( isset( $w['settings'] ) && is_array( $w['settings'] ) ) {
				$aggregated_settings[] = $w['settings'];
			}
		}
		// Wrap the aggregated settings list in a parent key so the recursive
		// scanners in address/palette walk every child's fields.
		$wrapped = array( '__cc_aggregate__' => $aggregated_settings );

		$checks = self::lint_html_block( $aggregated_html, $wrapped, true /* skip_wall_of_text */ );

		// 2. Run wall_of_text per widget. Fail if ANY single widget's text
		//    exceeds the threshold with no structural relief.
		$per_widget_results = array();
		$any_failed         = false;
		$failed_labels      = array();
		foreach ( $widget_payloads as $i => $w ) {
			$html = isset( $w['html'] ) ? (string) $w['html'] : '';
			if ( '' === trim( $html ) ) {
				continue;
			}
			$label  = isset( $w['label'] ) ? (string) $w['label'] : ( '#' . ( $i + 1 ) );
			$result = self::wall_of_text_check( $html );
			$per_widget_results[] = array(
				'label'  => $label,
				'pass'   => ! empty( $result['pass'] ),
				'words'  => isset( $result['word_count'] ) ? (int) $result['word_count'] : 0,
			);
			if ( empty( $result['pass'] ) ) {
				$any_failed     = true;
				$failed_labels[] = $label . ' (' . ( $result['word_count'] ?? 0 ) . ' words)';
			}
		}
		if ( empty( $per_widget_results ) ) {
			// Nothing to lint — synthesize a pass so the check key still exists.
			$checks['wall_of_text'] = array(
				'pass'    => true,
				'message' => 'No text-bearing widgets in payload.',
			);
		} elseif ( $any_failed ) {
			$checks['wall_of_text'] = array(
				'pass'       => false,
				'per_widget' => $per_widget_results,
				'message'    => sprintf(
					'Wall of text in %d widget(s): %s. Split each long widget into a heading + icon-list / accordion / icon-box row.',
					count( $failed_labels ),
					implode( ', ', $failed_labels )
				),
			);
		} else {
			$checks['wall_of_text'] = array(
				'pass'       => true,
				'per_widget' => $per_widget_results,
				'message'    => 'No single widget exceeds the wall-of-text threshold.',
			);
		}

		return $checks;
	}

	/**
	 * Single source of truth for placeholder / greeking / template-slot patterns.
	 * Broadened in v0.39: catches lorem-ipsum variants that omit the literal
	 * "lorem ipsum" opener, plus "sample/placeholder text", "... goes here", and
	 * "your text here" style unfilled slots. Used by lint_html_block AND the
	 * publish gate so queue-time and publish-time agree.
	 */
	private static function placeholder_patterns() {
		return array(
			'/\$TBD\b/i',
			'/\[(?:TBD|TODO|PLACEHOLDER|FIXME)\]/i',
			'/\bLorem\s+ipsum\b/i',
			'/\blorem\b/i',
			'/\bdolor sit amet\b/i',
			'/\bconsectetur adipiscing\b/i',
			'/\{\{[^}]+\}\}/',
			'/\b(?:TBD|TODO|FIXME)\b/',
			'/\b(?:placeholder|sample|dummy)\s+(?:text|content|copy|heading|description)\b/i',
			'/\b(?:text|description|content|heading|image|photo|copy)\s+goes here\b/i',
			'/\byour\s+(?:text|heading|title|description)\s+here\b/i',
			'/\benter\s+(?:your\s+)?(?:text|content|description)\s+here\b/i',
		);
	}

	/**
	 * Return the unique placeholder markers found in a blob of text/HTML.
	 */
	public static function detect_placeholders( $text ) {
		$plain = wp_strip_all_tags( (string) $text );
		$found = array();
		foreach ( self::placeholder_patterns() as $pat ) {
			if ( preg_match_all( $pat, $plain, $m ) ) {
				foreach ( $m[0] as $hit ) {
					$found[] = $hit;
				}
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Count "blocks" of identical (normalized) text that repeat across 3+ items.
	 * 3+ siblings sharing the same copy is the signature of an unfilled template
	 * (e.g. 22 service cards all reading the same placeholder). Trivially-short
	 * strings (< 12 chars: labels, single words) are ignored.
	 */
	public static function detect_duplicate_blocks( $texts ) {
		$counts = array();
		foreach ( (array) $texts as $t ) {
			$n = preg_replace( '/\s+/u', ' ', mb_strtolower( trim( wp_strip_all_tags( (string) $t ) ) ) );
			if ( null === $n || mb_strlen( $n ) < 12 ) {
				continue;
			}
			$counts[ $n ] = ( $counts[ $n ] ?? 0 ) + 1;
		}
		$dups = array_filter( $counts, function ( $c ) {
			return $c >= 3;
		} );
		return count( $dups );
	}

	/**
	 * Recursive Elementor-tree walker for the publish gate: collects text,
	 * counts in-body images (image-ish widgets + any settings key containing
	 * "image" with a url + container background images), and flags any single
	 * container holding > 10 homogeneous sibling cards (flat-list IA overflow).
	 */
	private static function gate_walk_tree( $nodes, &$texts, &$image_count, &$flat_overflow ) {
		if ( ! is_array( $nodes ) ) {
			return;
		}
		$child_type_counts = array();
		foreach ( $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			$wt = isset( $n['widgetType'] ) ? (string) $n['widgetType'] : ( isset( $n['elType'] ) ? (string) $n['elType'] : '' );
			if ( '' !== $wt ) {
				$child_type_counts[ $wt ] = ( $child_type_counts[ $wt ] ?? 0 ) + 1;
			}
			$st = isset( $n['settings'] ) && is_array( $n['settings'] ) ? $n['settings'] : array();
			foreach ( array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' ) as $f ) {
				if ( isset( $st[ $f ] ) && is_string( $st[ $f ] ) && '' !== trim( $st[ $f ] ) ) {
					$texts[] = $st[ $f ];
				}
			}
			if ( in_array( $wt, array( 'image', 'image-box', 'image-carousel', 'image-gallery', 'media-carousel' ), true ) ) {
				$image_count++;
			}
			foreach ( $st as $k => $v ) {
				if ( is_array( $v ) && isset( $v['url'] ) && is_string( $v['url'] ) && '' !== $v['url']
					&& false !== stripos( (string) $k, 'image' ) ) {
					$image_count++;
				}
			}
			if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
				self::gate_walk_tree( $n['elements'], $texts, $image_count, $flat_overflow );
			}
		}
		foreach ( $child_type_counts as $wt => $c ) {
			if ( in_array( $wt, array( 'icon-box', 'image-box', 'icon-list', 'price-list' ), true ) && $c > 10 ) {
				$flat_overflow[] = $c;
			}
		}
	}

	/**
	 * Page-level publish gate (v0.39). Runs on the WHOLE tree at the "go live"
	 * moment. BLOCKS on defects never acceptable on a live page (placeholder /
	 * lorem text, duplicate-template cards). WARNS on things that lose in 2026
	 * (no images, ungrouped flat card lists, thin content) without blocking, so
	 * legitimate text-only pages still publish. Blocking is overridable per-post
	 * (_cc_publish_gate_override postmeta) or via the cc_assistant_publish_gate_blocking
	 * filter. Returns array( pass, blocking[], warnings[], stats ).
	 */
	public static function evaluate_publish_gate( $post_id ) {
		$post_id       = (int) $post_id;
		$blocking      = array();
		$warnings      = array();
		$texts         = array();
		$image_count   = 0;
		$flat_overflow = array();

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$tree = json_decode( $raw, true );
			if ( is_array( $tree ) ) {
				self::gate_walk_tree( $tree, $texts, $image_count, $flat_overflow );
			}
		}
		if ( empty( $texts ) ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$texts[]      = wp_strip_all_tags( (string) $post->post_content );
				$image_count += (int) preg_match_all( '/<img\b/i', (string) $post->post_content );
			}
		}

		$plain      = trim( preg_replace( '/\s+/u', ' ', implode( "\n", $texts ) ) );
		$word_count = $plain ? str_word_count( $plain ) : 0;

		$ph = self::detect_placeholders( implode( "\n", $texts ) );
		if ( ! empty( $ph ) ) {
			$blocking[] = array(
				'code'    => 'placeholder_text',
				'message' => sprintf( 'Live page still contains placeholder text: %s. Replace before publishing.', implode( ', ', array_slice( $ph, 0, 5 ) ) ),
			);
		}
		$dup = self::detect_duplicate_blocks( $texts );
		if ( $dup > 0 ) {
			$blocking[] = array(
				'code'    => 'duplicate_card_text',
				'message' => sprintf( '%d block(s) of identical text repeated across 3+ widgets (unfilled template). Give each card unique copy.', $dup ),
			);
		}
		if ( 0 === $image_count ) {
			$warnings[] = array(
				'code'    => 'no_images',
				'message' => 'No in-body images. Pages that win in 2026 use real visuals — add at least one relevant image (hero / section / card).',
			);
		}
		if ( ! empty( $flat_overflow ) ) {
			$warnings[] = array(
				'code'    => 'flat_list_overflow',
				'message' => sprintf( 'A section has %d ungrouped sibling cards. Group large sets into tabs / accordion / a filtered grid so visitors can navigate.', max( $flat_overflow ) ),
			);
		}
		if ( $word_count > 0 && $word_count < 300 ) {
			$warnings[] = array(
				'code'    => 'thin_content',
				'message' => sprintf( 'Only ~%d words. Thin pages rarely win — add depth, unique data, and fully answer the query.', $word_count ),
			);
		}

		$blocking = (array) apply_filters( 'cc_assistant_publish_gate_blocking', $blocking, $post_id, $warnings );
		if ( get_post_meta( $post_id, '_cc_publish_gate_override', true ) ) {
			$blocking = array();
		}

		return array(
			'pass'     => empty( $blocking ),
			'blocking' => array_values( $blocking ),
			'warnings' => $warnings,
			'stats'    => array( 'word_count' => $word_count, 'image_count' => $image_count ),
		);
	}

	/**
	 * Wall-of-text detector. Returns pass=false when a single content block
	 * crosses the 200-word threshold with zero structural relief — no
	 * sub-headings (h2-h6), no lists, no tables, no embedded images. The
	 * message includes shape-aware widget recommendations so the model knows
	 * which Elementor widget type to use instead of expanding the paragraph
	 * further.
	 */
	public static function wall_of_text_check( $html, $plain = '' ) {
		$html  = (string) $html;
		if ( '' === $plain ) {
			$plain = trim( wp_strip_all_tags( $html ) );
		}
		$word_count = $plain ? str_word_count( $plain ) : 0;

		if ( $word_count < 200 ) {
			return array(
				'pass'       => true,
				'word_count' => $word_count,
				'message'    => 'Block is short enough not to need structural breaks.',
			);
		}

		$has_heading = (bool) preg_match( '/<h[2-6]\b/i', $html );
		$has_list    = (bool) preg_match( '/<(ul|ol)\b/i', $html );
		$has_table   = (bool) preg_match( '/<table\b/i', $html );
		$has_image   = (bool) preg_match( '/<img\b/i', $html );

		if ( $has_heading || $has_list || $has_table || $has_image ) {
			return array(
				'pass'       => true,
				'word_count' => $word_count,
				'message'    => sprintf( 'Long block (%d words) but has structural relief (heading/list/table/image).', $word_count ),
			);
		}

		// Shape-aware suggestion. Detect prose patterns and recommend the
		// matching Elementor widget pattern.
		$suggestions = array();
		$lower       = mb_strtolower( $plain );

		// Q&A shape: 2+ question marks across the block. Suggest accordion.
		$question_count = substr_count( $plain, '?' );
		if ( $question_count >= 2 ) {
			$suggestions[] = 'Q&A shape detected (' . $question_count . ' question marks). Use draft_add_accordion_item for each Q+A pair, not a text-editor.';
		}

		// Step shape: "First/Second/Third" or "Step 1/Step 2" or "1. 2. 3.".
		if ( preg_match( '/\b(first|second|third|fourth|fifth|next|then|finally)\b.*\b(first|second|third|fourth|fifth|next|then|finally)\b/iu', $lower )
			|| preg_match( '/step\s*[1-9]/i', $plain )
			|| preg_match( '/(?:^|\s)1\)\s.*(?:^|\s)2\)\s/su', $plain ) ) {
			$suggestions[] = 'Step/process shape detected. Build a row of icon-box widgets (one per step) inside a container, or use an icon-list with numbered icons.';
		}

		// Enumeration shape: 4+ comma-separated noun-like tokens in one
		// sentence, or repeated "include" / "such as" / "like" cues.
		if ( preg_match( '/(?:include|such as|like|including|these are)\s+[^.?!]{20,}/i', $plain ) ) {
			$suggestions[] = 'Enumeration shape detected ("include / such as / like"). Use an icon-list widget — each item gets its own row with a checkmark or relevant icon.';
		}

		// Comparison shape: vs / versus / "while X, Y" / "unlike".
		if ( preg_match( '/\b(?:vs\.?|versus|unlike|while [a-z]+ [a-z]+,|compared to|in contrast)\b/i', $plain ) ) {
			$suggestions[] = 'Comparison shape detected. Use a heading + two-column container with icon-box widgets, or a native Elementor table widget. Side-by-side rendering reads much faster than prose comparison.';
		}

		// Generic fallback when no specific shape matched.
		if ( empty( $suggestions ) ) {
			$suggestions[] = 'Split the block: add a heading widget for the section title, then group related sentences into separate text-editor widgets with an icon-list or icon-box row between them. Aim for ≤150 words per text-editor widget.';
		}

		return array(
			'pass'        => false,
			'word_count'  => $word_count,
			'has_heading' => false,
			'has_list'    => false,
			'has_table'   => false,
			'has_image'   => false,
			'suggestions' => $suggestions,
			'message'     => sprintf(
				'Wall of text: %d words in one text block with no headings, lists, tables, or images. %s',
				$word_count,
				implode( ' ', $suggestions )
			),
		);
	}

	/**
	 * Recursively flatten an arbitrarily-nested settings array into a single
	 * stream of string values. Used by the address/palette checks so we
	 * catch values nested inside link.url, icon_list items, __globals__,
	 * etc. without coding a path-by-path picker.
	 *
	 * @return string[] List of every string scalar found anywhere in $data.
	 */
	private static function flatten_string_values( $data ) {
		$out = array();
		if ( is_string( $data ) ) {
			$out[] = $data;
			return $out;
		}
		if ( ! is_array( $data ) ) {
			return $out;
		}
		foreach ( $data as $v ) {
			if ( is_string( $v ) ) {
				$out[] = $v;
			} elseif ( is_array( $v ) ) {
				foreach ( self::flatten_string_values( $v ) as $s ) {
					$out[] = $s;
				}
			}
		}
		return $out;
	}

	/**
	 * Address-consistency check (v0.35). Scans every string value in a widget
	 * settings array for street-address-shaped patterns and compares them to
	 * the canonical address stored in the `cc_assistant_facility_address`
	 * option. Hard fails if the proposed widget mentions a different street
	 * number / street name. No-op when the option is empty.
	 *
	 * Triggered by an incident where an agent invented "9220 Garland Rd,
	 * Suite 200, Dallas TX 75218" and shipped it to 5 widgets before the
	 * operator caught it. The plugin now refuses such queues server-side.
	 *
	 * @param array $settings Widget settings hash (or a synthetic wrapper from
	 *                        lint_html_block_per_widget aggregating multiple
	 *                        widgets' settings).
	 */
	public static function address_consistency_check( $settings ) {
		$canonical = trim( (string) get_option( 'cc_assistant_facility_address', '' ) );
		if ( '' === $canonical ) {
			return array(
				'pass'    => true,
				'count'   => 0,
				'samples' => array(),
				'message' => 'Address check disabled (cc_assistant_facility_address option is empty).',
			);
		}

		// Normalize the canonical for fuzzy matching: lowercase, collapse
		// whitespace, strip punctuation that's commonly variant (commas, periods).
		$normalize = function ( $s ) {
			$s = mb_strtolower( (string) $s );
			$s = preg_replace( '/[,.]/u', ' ', $s );
			$s = preg_replace( '/\s+/u', ' ', $s );
			return trim( $s );
		};
		$canon_norm = $normalize( $canonical );

		// Canonical 5-digit zips for the soft-warn.
		preg_match_all( '/\b(\d{5})\b/', $canonical, $cz );
		$canonical_zips = array_unique( $cz[1] ?? array() );

		// Regex matches a street-address pattern: 2-5 digits, a Capitalized
		// street name, then a properly-cased street-type token. Three guards
		// keep prose from false-positiving (see below):
		//   1. The name segment must START with a capital letter ([A-Z]) — real
		//      street names are proper nouns; prose mid-sentence words ("right
		//      away", "years of experience") are lowercase.
		//   2. The gap class excludes '.' so a match cannot span a sentence
		//      boundary.
		//   3. No /i flag — the street-type suffix must be capitalized as in a
		//      real address ("Drive", "Hwy"), so the verb "drive" / noun "way"
		//      in prose does not match.
		// Motivating false-positive (v0.35): "call 911 right away. Do not drive
		// yourself" was flagged as the street address "911 right away. Do not
		// drive". All three guards independently kill that match. Trade-off: a
		// genuinely-wrong address written entirely in lowercase is no longer
		// flagged, which is the correct bias for a hard-blocking lint (a false
		// negative is far cheaper than blocking a legitimate edit).
		$street_re = '/\b\d{2,5}\s+[A-Z][A-Za-z0-9\s\-]{2,30}?\s+(?:St|Street|Ave|Avenue|Blvd|Boulevard|Hwy|Highway|Rd|Road|Dr|Drive|Ln|Lane|Way|Pkwy|Parkway|Ct|Court|Cir|Circle|Pl|Place|Ter|Terrace|Trl|Trail)\b/u';

		$violations  = array();
		$soft_zips   = array();

		$strings = self::flatten_string_values( $settings );
		foreach ( $strings as $val ) {
			// Skip __globals__ token strings and any URL-like value (we don't
			// want to flag href attributes that legitimately link to a maps URL).
			$plain = wp_strip_all_tags( $val );
			if ( '' === trim( $plain ) ) {
				continue;
			}
			if ( preg_match_all( $street_re, $plain, $m ) ) {
				foreach ( $m[0] as $hit ) {
					$hit_norm = $normalize( $hit );
					// Fuzzy match: is the hit a substring of canonical, or
					// canonical a substring of hit? If yes, treat as the
					// canonical address.
					if ( '' !== $hit_norm && ( false !== strpos( $canon_norm, $hit_norm ) || false !== strpos( $hit_norm, $canon_norm ) ) ) {
						continue;
					}
					$violations[] = $hit;
				}
			}
			if ( preg_match_all( '/\b(\d{5})\b/', $plain, $zm ) ) {
				foreach ( $zm[1] as $zip ) {
					if ( ! in_array( $zip, $canonical_zips, true ) ) {
						$soft_zips[] = $zip;
					}
				}
			}
		}

		$violations = array_values( array_unique( $violations ) );
		$soft_zips  = array_values( array_unique( $soft_zips ) );

		if ( empty( $violations ) ) {
			$msg = 'No address mismatches.';
			if ( ! empty( $soft_zips ) ) {
				$msg .= sprintf( ' Soft warn: %d non-canonical zip(s) detected: %s.', count( $soft_zips ), implode( ', ', $soft_zips ) );
			}
			return array(
				'pass'      => true,
				'count'     => 0,
				'samples'   => array(),
				'soft_zips' => $soft_zips,
				'message'   => $msg,
			);
		}
		return array(
			'pass'      => false,
			'count'     => count( $violations ),
			'samples'   => array_slice( $violations, 0, 5 ),
			'soft_zips' => $soft_zips,
			'message'   => sprintf(
				'%d address mismatch(es): %s. Canonical: %s',
				count( $violations ),
				implode( ' | ', array_slice( $violations, 0, 3 ) ),
				$canonical
			),
		);
	}

	/**
	 * Palette-compliance check (v0.35). Scans every string value in a widget
	 * settings array for hex colors and verifies each is in the allowed
	 * palette (active Elementor Kit + semantic greys allow-list). SOFT warn
	 * — does not hard-block, since legitimate one-off colors on real pages
	 * are common.
	 *
	 * Triggered by an incident where an agent shipped `#FFC107`, `#B45309`,
	 * `#15803D`, `#28A745`, `#FFE5E5`, `#FFF1F1` — all off-brand. Plugin
	 * now surfaces these on queue so the operator catches them before apply.
	 */
	public static function palette_compliance_check( $settings ) {
		// Build the allowed-hex set from the Kit + semantic greys.
		$allowed = array();
		$kit_id  = (int) get_option( 'elementor_active_kit', 0 );
		if ( $kit_id > 0 ) {
			$kit = get_post_meta( $kit_id, '_elementor_page_settings', true );
			if ( is_array( $kit ) ) {
				foreach ( array( 'system_colors', 'custom_colors' ) as $bucket ) {
					if ( ! empty( $kit[ $bucket ] ) && is_array( $kit[ $bucket ] ) ) {
						foreach ( $kit[ $bucket ] as $c ) {
							if ( isset( $c['color'] ) && is_string( $c['color'] ) ) {
								$allowed[ strtolower( $c['color'] ) ] = true;
							}
						}
					}
				}
			}
		}
		// Also include the brand profile's known kit-derived defaults.
		if ( class_exists( 'CC_Assistant_Brand_Profile' ) ) {
			$brand = CC_Assistant_Brand_Profile::get();
			foreach ( array( 'primary', 'secondary', 'accent', 'text', 'card_bg', 'page_bg' ) as $k ) {
				if ( ! empty( $brand[ $k ] ) && is_string( $brand[ $k ] ) ) {
					$allowed[ strtolower( $brand[ $k ] ) ] = true;
				}
			}
		}
		$greys = apply_filters(
			'cc_assistant_semantic_greys',
			array( '#555555', '#777777', '#dddddd', '#e0e0e0', '#000000', '#ffffff' )
		);
		foreach ( (array) $greys as $g ) {
			$allowed[ strtolower( (string) $g ) ] = true;
		}

		$violations = array();
		$strings    = self::flatten_string_values( $settings );
		foreach ( $strings as $val ) {
			if ( ! preg_match_all( '/#[0-9A-Fa-f]{6}\b/', $val, $m ) ) {
				continue;
			}
			foreach ( $m[0] as $hex ) {
				$h = strtolower( $hex );
				if ( ! isset( $allowed[ $h ] ) ) {
					$violations[] = $hex;
				}
			}
		}
		$violations = array_values( array_unique( $violations ) );
		if ( empty( $violations ) ) {
			return array(
				'pass'    => true,
				'count'   => 0,
				'samples' => array(),
				'message' => 'All hex colors are in the Kit palette or semantic greys.',
			);
		}
		// Soft warn: pass=true so this never hard-blocks, but the message
		// surfaces so the operator sees it on the inbox card.
		return array(
			'pass'    => true,
			'soft'    => true,
			'count'   => count( $violations ),
			'samples' => array_slice( $violations, 0, 8 ),
			'message' => sprintf(
				'%d off-brand hex(es) outside Kit + semantic greys: %s. Replace with a Kit global token or add them to the Kit.',
				count( $violations ),
				implode( ', ', array_slice( $violations, 0, 5 ) )
			),
		);
	}

	/**
	 * Hospital-comparison ban (v0.35). Hard-fails copy that positions the
	 * site against hospital EDs ("faster than the hospital", "hospital wait
	 * times average X minutes", "vs hospital ED", etc.). Operator policy on
	 * ER sites only — no-op everywhere else.
	 *
	 * Detection gates on the industry profile: when industry_slug=healthcare
	 * AND the site identifies as an emergency-room subtype (detected via
	 * common business_type strings on the site option / Yoast / Rank Math).
	 * False-positive risk is acceptable here — phrases like "hospital ED"
	 * are essentially never legitimate non-comparison copy on an ER site.
	 */
	public static function hospital_comparison_check( $plain ) {
		// Only enforced on ER healthcare sites. Cheap detection: industry
		// slug + presence of an ER signal in the brand_terms / site name.
		if ( ! self::is_emergency_room_site() ) {
			return array(
				'pass'    => true,
				'message' => 'Hospital-comparison check not applicable (not an ER site).',
			);
		}

		$patterns = array(
			'/\bhospital\s+EDs?\b/i',
			'/\bhospital\s+ERs?\b/i',
			'/\bvs\.?\s+(?:a\s+|the\s+)?hospital\b/i',
			'/\bthan\s+(?:a\s+)?(?:typical\s+|the\s+)?hospital\b/i',
			'/\bhospitals?\s+averages?\s+\d+\s*min/i',
			'/\bfaster\s+than\s+(?:a\s+|the\s+)?hospital\b/i',
			'/\bhospital\s+wait(?:s|\s+times?)?\b/i',
		);
		$hits = array();
		foreach ( $patterns as $pat ) {
			if ( preg_match_all( $pat, $plain, $m ) ) {
				foreach ( $m[0] as $hit ) {
					$hits[] = trim( $hit );
				}
			}
		}
		$hits = array_values( array_unique( $hits ) );
		if ( empty( $hits ) ) {
			return array(
				'pass'    => true,
				'message' => 'No hospital-comparison phrasing detected.',
			);
		}
		return array(
			'pass'    => false,
			'count'   => count( $hits ),
			'samples' => array_slice( $hits, 0, 5 ),
			'message' => sprintf(
				'Hospital ED comparison detected (%d match: %s). Operator policy bans positioning against hospital partners — use self-anchored claims only. See no-hospital-comparison memory entry.',
				count( $hits ),
				implode( ', ', array_slice( $hits, 0, 3 ) )
			),
		);
	}

	/**
	 * Cheap heuristic: is this site an emergency-room healthcare site? Used
	 * by hospital_comparison_check to scope the rule to ER sites only.
	 *
	 * Cached for the request — called once per lint pass.
	 */
	private static function is_emergency_room_site() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$cached = false;
		// 1. Industry overlay must be healthcare.
		if ( class_exists( 'CC_Assistant_Industry_Profile' ) ) {
			$slug = CC_Assistant_Industry_Profile::industry_slug();
			if ( 'healthcare' !== $slug ) {
				return $cached;
			}
		}
		// 2. Look for an ER signal in (a) the blogname, (b) the brand_terms
		//    option, (c) a known business_subtype string if the operator set
		//    one. We accept any of: 'er ', 'emergency room', 'emergency_room'.
		$haystack = mb_strtolower( (string) get_option( 'blogname', '' ) );
		$brand    = (array) get_option( 'cc_assistant_brand_terms', array() );
		foreach ( $brand as $t ) {
			$haystack .= ' ' . mb_strtolower( (string) $t );
		}
		$subtype = mb_strtolower( (string) get_option( 'cc_assistant_business_subtype', '' ) );
		$haystack .= ' ' . $subtype;
		if ( preg_match( '/\b(?:er|emergency\s*room|freestanding\s*er|24\/7\s*er|emergency_room)\b/i', $haystack ) ) {
			$cached = true;
		}
		return $cached;
	}

	/**
	 * v0.50.3 — Quote-quality checks. A <blockquote> anywhere on any site
	 * must be (a) verifiable: it carries a source link, (b) attributed: it
	 * names its speaker or source, and (c) informative: its wording shares
	 * vocabulary with the section it sits in. (c) approximates the editorial
	 * test "does removing this quote remove a fact from the section?" —
	 * generic-authority quotes (a credible person saying something off-topic)
	 * share almost no tokens with their section heading, while fact-bearing
	 * quotes do. Origin: 2026-07-21 review where a Mayo Clinic quote about
	 * HT under-prescription decorated a pellets-vs-injections section.
	 *
	 * Scoped to NEW quotes only: a blockquote whose quote text already exists
	 * in $current_html is skipped, so legacy posts never flag on unrelated
	 * surgical edits. Pass '' as $current_html to treat all quotes as new
	 * (create-post and widget-payload paths).
	 *
	 * Returns up to three checks keyed quote_source_link (hard at the queue
	 * layer), quote_attribution (warn), quote_relevance (warn). Returns an
	 * empty array when the proposed HTML contains no new blockquotes, so
	 * quote-free edits keep a clean report.
	 */
	public static function quote_checks( $current_html, $proposed_html ) {
		$proposed_html = (string) $proposed_html;
		if ( false === stripos( $proposed_html, '<blockquote' ) ) {
			return array();
		}
		if ( ! preg_match_all( '#<blockquote\b[^>]*>(.*?)</blockquote>#is', $proposed_html, $m, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$current_norm = self::normalize_for_diff( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $current_html ) ) );

		// Collect heading positions once so each quote can find the nearest
		// heading above it (its section context for the relevance check).
		$headings = array();
		if ( preg_match_all( '#<h[1-4][^>]*>(.*?)</h[1-4]>#is', $proposed_html, $hm, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $hm[1] as $h ) {
				$headings[] = array( 'text' => trim( wp_strip_all_tags( $h[0] ) ), 'pos' => $h[1] );
			}
		}

		$missing_link  = array();
		$missing_attr  = array();
		$low_relevance = array();
		$new_count     = 0;

		foreach ( $m[1] as $i => $inner_match ) {
			$inner  = (string) $inner_match[0];
			$offset = (int) $m[0][ $i ][1];

			// Quote text = first paragraph if the card pattern is used,
			// otherwise the whole inner text. Attribution = what remains.
			$quote_text  = '';
			$attribution = '';
			if ( preg_match( '#<p\b[^>]*>(.*?)</p>#is', $inner, $pm, PREG_OFFSET_CAPTURE ) ) {
				$quote_text  = trim( wp_strip_all_tags( $pm[1][0] ) );
				$attribution = trim( wp_strip_all_tags( substr( $inner, $pm[0][1] + strlen( $pm[0][0] ) ) ) );
			} else {
				$quote_text = trim( wp_strip_all_tags( $inner ) );
			}
			if ( '' === $quote_text ) {
				continue;
			}

			// Newness gate: skip quotes already present in the current body.
			$quote_norm = self::normalize_for_diff( preg_replace( '/\s+/u', ' ', $quote_text ) );
			if ( '' !== $current_norm && false !== strpos( $current_norm, $quote_norm ) ) {
				continue;
			}
			$new_count++;
			$snippet = mb_substr( $quote_text, 0, 70 ) . ( mb_strlen( $quote_text ) > 70 ? '…' : '' );

			// (a) Verifiable: a source link inside the blockquote.
			if ( ! preg_match( '/<a\s[^>]*href\s*=/i', $inner ) ) {
				$missing_link[] = $snippet;
			}

			// (b) Attributed: non-trivial text after the quote paragraph, or a
			// "Name | Source" separator anywhere in the card.
			if ( mb_strlen( $attribution ) < 3 && false === strpos( $inner, '|' ) ) {
				$missing_attr[] = $snippet;
			}

			// (c) Informative: token overlap with the page's topic context =
			// the document's FIRST heading (topic anchor) plus the nearest
			// heading above the quote (section anchor). Either can supply the
			// overlap, so a pellet quote passes under a "Compounded vs
			// FDA-Approved" section on a pellets page, while an off-topic
			// authority quote matches neither. No headings in scope
			// (single-widget payloads) => cannot judge, so pass.
			$nearest = '';
			foreach ( $headings as $h ) {
				if ( $h['pos'] < $offset ) {
					$nearest = $h['text'];
				}
			}
			$context = trim( ( empty( $headings ) ? '' : $headings[0]['text'] ) . ' ' . $nearest );
			if ( '' !== $context ) {
				$overlap = self::topic_token_overlap( $quote_text, $context );
				if ( $overlap < 2 ) {
					$low_relevance[] = array(
						'quote'   => $snippet,
						'section' => $context,
						'overlap' => $overlap,
					);
				}
			}
		}

		if ( 0 === $new_count ) {
			return array();
		}

		$checks = array();
		$checks['quote_source_link'] = array(
			'pass'            => empty( $missing_link ),
			'new_quote_count' => $new_count,
			'missing'         => $missing_link,
			'message'         => empty( $missing_link )
				? sprintf( 'All %d new quote(s) carry a source link.', $new_count )
				: sprintf( '%d new quote(s) have NO source link: "%s". Every quote must be a real published statement verified verbatim at a linked source. Unverifiable quotes cannot ship.', count( $missing_link ), implode( '" / "', $missing_link ) ),
		);
		$checks['quote_attribution'] = array(
			'pass'    => empty( $missing_attr ),
			'missing' => $missing_attr,
			'message' => empty( $missing_attr )
				? 'All new quotes name their speaker or source.'
				: sprintf( '%d new quote(s) have no attribution line: "%s". Use the card pattern: quote, then "Name, Title | Source".', count( $missing_attr ), implode( '" / "', $missing_attr ) ),
		);
		$checks['quote_relevance'] = array(
			'pass'     => empty( $low_relevance ),
			'flagged'  => $low_relevance,
			'message'  => empty( $low_relevance )
				? 'All new quotes share vocabulary with their section.'
				: sprintf(
					'%d new quote(s) look like generic authority, not section-specific facts: %s. A quote card must state a fact this section needs — if removing the quote removes no information, replace it with one whose wording overlaps its section topic.',
					count( $low_relevance ),
					implode( '; ', array_map(
						static function ( $r ) {
							return sprintf( '"%s" shares %d topic word(s) with its section "%s"', $r['quote'], $r['overlap'], $r['section'] );
						},
						$low_relevance
					) )
				),
		);
		return $checks;
	}

	/**
	 * Count distinct topic-token overlaps between two strings. Tokens are
	 * lowercased words of 5+ characters minus a generic stoplist; two tokens
	 * match when they share their first five characters (cheap stemming, so
	 * "pellets" matches "pellet" and "hormonal" matches "hormones").
	 */
	private static function topic_token_overlap( $a, $b ) {
		static $stop = array( 'about', 'their', 'there', 'which', 'these', 'those', 'would', 'could', 'should', 'because', 'after', 'before', 'every', 'other', 'being', 'where', 'while', 'still', 'really', 'people', 'things', 'better', 'always', 'never', 'current', 'forms', 'under', 'going', 'doing', 'saying' );
		$tokenize = static function ( $text ) use ( $stop ) {
			preg_match_all( '/[a-z]{5,}/', mb_strtolower( (string) $text ), $tm );
			$out = array();
			foreach ( $tm[0] as $w ) {
				if ( ! in_array( $w, $stop, true ) ) {
					$out[ substr( $w, 0, 5 ) ] = true;
				}
			}
			return $out;
		};
		$ta = $tokenize( $a );
		$tb = $tokenize( $b );
		return count( array_intersect_key( $ta, $tb ) );
	}

	/**
	 * Compare a proposed HTML body against the current body and return
	 * structural-edit checks: bulk_add_ratio, deletion_ratio, redundancy,
	 * net_word_delta. Pairs with lint_html_block() — the queue endpoint
	 * runs both and merges the results into a single lint_report.
	 */
	public static function lint_proposed_change( $current_html, $proposed_html ) {
		$checks         = array();
		$current_plain  = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( (string) $current_html ) ) );
		$proposed_plain = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( (string) $proposed_html ) ) );
		$current_words  = $current_plain ? str_word_count( $current_plain ) : 0;
		$proposed_words = $proposed_plain ? str_word_count( $proposed_plain ) : 0;

		// Normalize typographic punctuation (smart quotes ↔ straight, en/em dash
		// ↔ hyphen, NBSP ↔ space) BEFORE the prefix/suffix matcher runs. Without
		// this, a model that round-trips post body through JSON often loses the
		// curly variant — and every apostrophe in the body becomes a one-character
		// byte diff, breaking prefix matching and inflating bulk_add_ratio /
		// deletion_ratio into "the whole body was rewritten" false alarms. The
		// em_dashes hard-violation check still runs separately against the raw
		// proposed body in lint_html_block(), so this normalization here doesn't
		// hide em-dash regressions.
		//
		// Re-trim AFTER normalization: PHP's trim() removes ASCII whitespace but
		// NOT NBSP (U+00A0). When the original body has trailing ` ` (common
		// in WP classic editor content), the initial trim leaves it in place, then
		// preg_replace turns it into a regular trailing space. Without this second
		// trim, the proposed body (trailing ASCII space stripped by initial trim)
		// and the current body (trailing space surviving) differ by one character
		// at the end, suffix matching finds zero matches, and the entire post-prefix
		// region falls into "added text" — re-introducing the false-rewrite display.
		$current_plain  = trim( self::normalize_for_diff( $current_plain ) );
		$proposed_plain = trim( self::normalize_for_diff( $proposed_plain ) );
		$current_len    = strlen( $current_plain );
		$proposed_len   = strlen( $proposed_plain );

		// Char-level prefix/suffix to estimate kept vs added vs removed.
		$prefix_len = 0;
		$min_len    = min( $current_len, $proposed_len );
		while ( $prefix_len < $min_len && $current_plain[ $prefix_len ] === $proposed_plain[ $prefix_len ] ) {
			$prefix_len++;
		}
		$suffix_len = 0;
		$max_suffix = min( $current_len - $prefix_len, $proposed_len - $prefix_len );
		while ( $suffix_len < $max_suffix && $current_plain[ $current_len - 1 - $suffix_len ] === $proposed_plain[ $proposed_len - 1 - $suffix_len ] ) {
			$suffix_len++;
		}
		$kept_len    = $prefix_len + $suffix_len;
		$removed_len = max( 0, $current_len - $kept_len );
		$added_len   = max( 0, $proposed_len - $kept_len );

		// Bulk-add ratio: added / (added + removed). 1.0 means nothing got cut.
		//
		// Semantic intent, same as deletion_ratio below: on a body REWRITE the
		// model should cut redundant copy as it adds, and a pure "stack new
		// content on top of old" edit is a yellow flag. Also like deletion_ratio,
		// the check is meaningless for edits that were never meant to remove
		// anything — an inline-link insert, a new FAQ entry, one extra paragraph.
		// Those add a little and remove nothing, so added/(added+removed) is
		// ALWAYS 100% and the check failed on every single one.
		//
		// deletion_ratio already had this guard ($is_surgical). bulk_add_ratio
		// never got the matching one, so it fired on every link insert and every
		// additive edit on any body over 1000 chars — constant false positives
		// that teach the reviewer to ignore lint. Fixed 2026-08-21.
		//
		// The exemption is RELATIVE, not a flat byte count: what makes an edit
		// "bulk" is adding a lot compared with what is already there. Appending
		// 8,000 chars to a 10,000-char page still fails (80%); adding a 1,400-char
		// FAQ pair to a 20,000-char page does not (7%).
		$denom             = $added_len + $removed_len;
		$add_pct           = $denom > 0 ? round( 100 * $added_len / $denom, 1 ) : 0.0;
		$incremental_limit = max( 200, (int) round( $current_len * 0.15 ) );
		$is_incremental    = $added_len < $incremental_limit;
		$bulk_add_pass     = $add_pct <= 90.0 || $current_len < 1000 || $is_incremental;
		$checks['bulk_add_ratio'] = array(
			'pass'          => $bulk_add_pass,
			'added_chars'   => $added_len,
			'removed_chars' => $removed_len,
			'add_percent'   => $add_pct,
			'incremental'   => $is_incremental,
			'message'       => $is_incremental && $add_pct > 90.0
				? sprintf( 'Incremental addition (%d chars added to a %d-char body) — bulk-add ratio not applicable.', $added_len, $current_len )
				: ( $bulk_add_pass
					? sprintf( 'Edit ratio: %d added / %d removed.', $added_len, $removed_len )
					: sprintf( '%.1f%% of the change is pure additions. Real edits cut as they expand. Look for paragraphs to merge or trim.', $add_pct ) ),
		);

		// Deletion ratio: how much of original was removed.
		//
		// Semantic intent: on a body REWRITE, the model should usually cut
		// redundant copy while adding new sections — a pure "stack new content
		// on top of old" edit is a yellow flag. This check is meaningless for
		// surgical edits (inline-link inserts, single-sentence tweaks) where
		// nothing was supposed to be removed in the first place.
		//
		// v0.20.1: skip the check when removed_len < 100 chars. That covers
		// every patch-tool insert (typically +60 to +200 chars added, 0 removed)
		// and small additive edits, while still catching real rewrite-shaped
		// regressions where significant content WAS removed but no offsetting
		// cuts were made.
		$del_pct        = $current_len > 0 ? round( 100 * $removed_len / $current_len, 1 ) : 0.0;
		$is_surgical    = $removed_len < 100;
		$deletion_pass  = $del_pct >= 5.0 || $current_len < 1000 || $is_surgical;
		$checks['deletion_ratio'] = array(
			'pass'           => $deletion_pass,
			'deleted_chars'  => $removed_len,
			'original_chars' => $current_len,
			'delete_percent' => $del_pct,
			'message'        => $is_surgical
				? sprintf( 'Surgical edit (%d chars removed) — deletion ratio not applicable.', $removed_len )
				: ( $deletion_pass
					? sprintf( '%.1f%% of original copy removed.', $del_pct )
					: sprintf( 'Only %.1f%% of the original was removed. Long posts almost always need some cuts; check for redundant existing copy.', $del_pct ) ),
		);

		// Redundancy: bigram-overlap score between newly added text and the
		// kept portion. High overlap means the new copy is restating points
		// already made elsewhere on the page.
		$kept_text  = '';
		if ( $kept_len > 0 ) {
			$kept_prefix = substr( $current_plain, 0, $prefix_len );
			$kept_suffix = $suffix_len > 0 ? substr( $current_plain, -$suffix_len ) : '';
			$kept_text   = trim( $kept_prefix . ' ' . $kept_suffix );
		}
		$added_text = trim( substr( $proposed_plain, $prefix_len, $proposed_len - $prefix_len - $suffix_len ) );
		$overlap    = self::bigram_overlap( $added_text, $kept_text );
		$checks['redundancy'] = array(
			'pass'              => $overlap['ratio'] <= 0.35,
			'overlap_ratio'     => $overlap['ratio'],
			'shared_bigrams'    => array_slice( $overlap['shared'], 0, 12 ),
			'added_word_count'  => $added_text ? str_word_count( $added_text ) : 0,
			'kept_word_count'   => $kept_text ? str_word_count( $kept_text ) : 0,
			'message'           => $overlap['ratio'] <= 0.35
				? sprintf( 'Added text shares %.0f%% of its bigrams with kept text — distinct.', 100 * $overlap['ratio'] )
				: sprintf( '%.0f%% of new content bigrams already appear in kept text. Likely repeating points; merge sections instead of stacking them.', 100 * $overlap['ratio'] ),
		);

		// Word count delta — pure metric, always passes; included for context.
		$delta = $proposed_words - $current_words;
		$checks['word_delta'] = array(
			'pass'           => true,
			'current_words'  => $current_words,
			'proposed_words' => $proposed_words,
			'delta'          => $delta,
			'message'        => sprintf( 'Word count: %d → %d (%+d).', $current_words, $proposed_words, $delta ),
		);

		// Content-preservation checks: did the rewrite accidentally drop
		// shortcodes, images, citations, internal links, or phone numbers
		// that the original had? These were the categories of damage that
		// went unnoticed in the cellulitis-vs-abscess incident — the model
		// can quietly strip a CTA or a contact-form shortcode and we need
		// the lint to flag the loss before approval.
		$preservation = self::content_preservation_checks( (string) $current_html, (string) $proposed_html );
		foreach ( $preservation as $name => $check ) {
			$checks[ $name ] = $check;
		}

		// Internal: expose the added plaintext slice so lint_post_content_change
		// can re-scope content-quality checks (em_dashes, ai_tells, style_guide)
		// to ONLY the model's actual additions on body rewrites. Stripped from
		// the merged output by lint_post_content_change before it returns.
		$checks['_added_plain'] = $added_text;

		return $checks;
	}

	/**
	 * Typographic-only normalization for diff matching. Maps smart quotes,
	 * en dash, non-breaking space, and ellipsis onto their ASCII equivalents so
	 * the prefix/suffix matcher in lint_proposed_change() doesn't treat
	 * invisible curly-vs-straight differences as a full-body rewrite. The
	 * actual saved post_content keeps whatever punctuation the model sent —
	 * this normalization is ONLY consumed inside lint_proposed_change().
	 *
	 * EM DASH (—) is deliberately NOT normalized here. Em dashes are a hard
	 * violation that the em_dashes check (run against the added-text slice on
	 * rewrites) needs to be able to detect. Folding them into hyphens at the
	 * diff stage would hide a model-introduced em dash inside the "kept" region.
	 */
	private static function normalize_for_diff( $text ) {
		static $map = array(
			"\xE2\x80\x98" => "'",  // ‘ left single
			"\xE2\x80\x99" => "'",  // ’ right single
			"\xE2\x80\x9C" => '"',  // “ left double
			"\xE2\x80\x9D" => '"',  // ” right double
			"\xE2\x80\x93" => '-',  // – en dash
			"\xC2\xA0"     => ' ',  //   NBSP
			"\xE2\x80\xA6" => '...', // … horizontal ellipsis
		);
		return strtr( (string) $text, $map );
	}

	/**
	 * Run the additive content-quality checks (em_dashes, ai_tells, style_guide)
	 * against a plaintext slice — used by lint_post_content_change() to scope
	 * these checks to the model's actual additions on body rewrites instead of
	 * the whole proposed body. Same return shape as the matching checks in
	 * lint_html_block(); a 'scope' field is added so the reviewer/UI can tell
	 * apart "the whole body has em dashes" from "the added words have em dashes".
	 */
	private static function lint_additive_text_only( $added_plain ) {
		$checks      = array();
		$added_plain = (string) $added_plain;
		$lower       = mb_strtolower( $added_plain );

		// Em dashes.
		$em_count = substr_count( $added_plain, "\xE2\x80\x94" );
		$checks['em_dashes'] = array(
			'pass'    => 0 === $em_count,
			'count'   => $em_count,
			'scope'   => 'added_text',
			'message' => $em_count > 0
				? sprintf( '%d em dash(es) in added text. Replace with periods, commas, or parentheses.', $em_count )
				: 'No em dashes in added text.',
		);

		// AI-tell phrases.
		$found_tells = array();
		foreach ( self::AI_TELLS as $tell ) {
			if ( false !== strpos( $lower, $tell ) ) {
				$found_tells[] = $tell;
			}
		}
		$checks['ai_tells'] = array(
			'pass'    => empty( $found_tells ),
			'found'   => $found_tells,
			'scope'   => 'added_text',
			'message' => empty( $found_tells )
				? 'No AI-tell phrases in added text.'
				: 'AI-tell phrases in added text: ' . implode( ', ', $found_tells ),
		);

		// Style-guide banned phrases.
		$banned = self::style_guide_banned_phrases();
		if ( ! empty( $banned ) ) {
			$found_banned = array();
			foreach ( $banned as $p ) {
				if ( false !== strpos( $lower, $p ) ) {
					$found_banned[] = $p;
				}
			}
			$checks['style_guide'] = array(
				'pass'    => empty( $found_banned ),
				'found'   => array_slice( $found_banned, 0, 8 ),
				'count'   => count( $found_banned ),
				'scope'   => 'added_text',
				'message' => empty( $found_banned )
					? 'No banned phrases from your style guide in added text.'
					: sprintf( '%d banned phrase(s) in added text: %s.', count( $found_banned ), implode( ', ', array_slice( $found_banned, 0, 5 ) ) ),
			);
		}

		return $checks;
	}

	/**
	 * Compare counts of structurally-meaningful elements between current and
	 * proposed body. Each element class returns its own pass/fail entry so
	 * the operator sees exactly what was dropped: a shortcode, an image,
	 * a CDC/.gov citation, an internal link, or a phone number.
	 *
	 * Threshold: "removed any" = warning, not refusal. The model may have
	 * a legitimate reason to drop something (e.g. broken citation, outdated
	 * shortcode), but the human reviewer should see the loss before
	 * approving rather than discover it on the live site.
	 */
	private static function content_preservation_checks( $current_html, $proposed_html ) {
		$out = array();

		$shortcode_before = preg_match_all( '/\[[a-zA-Z0-9_\-]+(?:\s|\]|\/)/u', $current_html, $cm ) ? $cm[0] : array();
		$shortcode_after  = preg_match_all( '/\[[a-zA-Z0-9_\-]+(?:\s|\]|\/)/u', $proposed_html, $pm ) ? $pm[0] : array();
		$shortcodes_dropped = max( 0, count( $shortcode_before ) - count( $shortcode_after ) );
		$out['shortcode_preservation'] = array(
			'pass'    => 0 === $shortcodes_dropped,
			'before'  => count( $shortcode_before ),
			'after'   => count( $shortcode_after ),
			'dropped' => $shortcodes_dropped,
			'message' => 0 === $shortcodes_dropped
				? sprintf( 'All %d shortcode(s) preserved.', count( $shortcode_before ) )
				: sprintf( '%d shortcode(s) dropped (was %d, now %d). Restore unless the shortcode is intentionally being retired — silently dropping a contact form, gallery, or button breaks the page.', $shortcodes_dropped, count( $shortcode_before ), count( $shortcode_after ) ),
		);

		$img_before = preg_match_all( '/<img\b[^>]*>/i', $current_html );
		$img_after  = preg_match_all( '/<img\b[^>]*>/i', $proposed_html );
		$imgs_dropped = max( 0, $img_before - $img_after );
		$out['image_preservation'] = array(
			'pass'    => 0 === $imgs_dropped,
			'before'  => (int) $img_before,
			'after'   => (int) $img_after,
			'dropped' => $imgs_dropped,
			'message' => 0 === $imgs_dropped
				? sprintf( 'All %d inline image(s) preserved.', $img_before )
				: sprintf( '%d inline image(s) dropped. Confirm the rewrite is not stripping page assets.', $imgs_dropped ),
		);

		// Authority citations: links to .gov / .edu / known authority hosts.
		// We use a coarse regex (any href ending with .gov, .edu, or matching
		// the user's authority list) — exact host matching against the saved
		// option happens in audit_post_links elsewhere; here we only need
		// "did the count of authority-shaped links go down."
		$authority_pattern = '/<a\s+[^>]*href=["\'](?:https?:)?\/\/[^"\'\/]*\.(?:gov|edu|nih\.gov|cdc\.gov|mayoclinic\.org|clevelandclinic\.org|hopkinsmedicine\.org|who\.int|nih\.gov|jamanetwork\.com|thelancet\.com|bmj\.com|nature\.com|harvard\.edu|mit\.edu|stanford\.edu)/iu';
		$auth_before = preg_match_all( $authority_pattern, $current_html );
		$auth_after  = preg_match_all( $authority_pattern, $proposed_html );
		$auth_dropped = max( 0, $auth_before - $auth_after );
		$out['authority_citation_preservation'] = array(
			'pass'    => 0 === $auth_dropped,
			'before'  => (int) $auth_before,
			'after'   => (int) $auth_after,
			'dropped' => $auth_dropped,
			'message' => 0 === $auth_dropped
				? sprintf( 'All %d authority citation(s) preserved.', $auth_before )
				: sprintf( '%d authority citation(s) dropped. E-E-A-T value is bleeding — restore unless the source was wrong.', $auth_dropped ),
		);

		// Internal links: same-domain anchors (href starts with site URL or /).
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $site_host ) {
			$internal_pattern = '/<a\s+[^>]*href=["\'](?:https?:\/\/' . preg_quote( $site_host, '/' ) . '|\/)[^"\']*["\']/iu';
			$int_before = preg_match_all( $internal_pattern, $current_html );
			$int_after  = preg_match_all( $internal_pattern, $proposed_html );
			$int_dropped = max( 0, $int_before - $int_after );
			$out['internal_link_preservation'] = array(
				'pass'    => $int_dropped <= max( 1, (int) round( $int_before * 0.1 ) ),
				'before'  => (int) $int_before,
				'after'   => (int) $int_after,
				'dropped' => $int_dropped,
				'message' => $int_dropped <= max( 1, (int) round( $int_before * 0.1 ) )
					? sprintf( 'Internal-link count: %d → %d (within tolerance).', $int_before, $int_after )
					: sprintf( '%d internal link(s) dropped (>10%% of original). Link equity to other site posts is leaking — was this intentional?', $int_dropped ),
			);
		}

		// Phone-number preservation: catches the local-business CTA leak
		// where a model strips "Call (936) 427-1313" from the bottom of
		// the post during a rewrite. Regex matches common US phone formats.
		$phone_pattern = '/(?:\(\d{3}\)\s*\d{3}-\d{4}|\d{3}-\d{3}-\d{4}|\+1\s*\d{3}\s*\d{3}\s*\d{4})/u';
		$phone_before = preg_match_all( $phone_pattern, $current_html, $pcb ) ? array_unique( $pcb[0] ) : array();
		$phone_after  = preg_match_all( $phone_pattern, $proposed_html, $pca ) ? array_unique( $pca[0] ) : array();
		$phone_dropped = array_values( array_diff( $phone_before, $phone_after ) );
		if ( ! empty( $phone_before ) ) {
			$out['phone_number_preservation'] = array(
				'pass'    => empty( $phone_dropped ),
				'before'  => count( $phone_before ),
				'after'   => count( $phone_after ),
				'dropped' => array_values( $phone_dropped ),
				'message' => empty( $phone_dropped )
					? sprintf( 'Phone number(s) preserved: %s.', implode( ', ', $phone_before ) )
					: sprintf( 'Phone number(s) dropped: %s. Confirm the rewrite is not removing the local-business CTA.', implode( ', ', $phone_dropped ) ),
			);
		}

		return $out;
	}

	/**
	 * Single-shot summary of lint_html_block + lint_proposed_change. Returns
	 * the merged checks plus a top-line verdict. Used by the queue endpoint
	 * so the lint_report stored on the pending row is one cohesive blob.
	 */
	public static function lint_post_content_change( $current_html, $proposed_html ) {
		$content_checks = self::lint_html_block( $proposed_html );
		$diff_checks    = self::lint_proposed_change( $current_html, $proposed_html );

		// Re-scope additive content-quality checks (em_dashes, ai_tells,
		// style_guide) to ONLY the model's actual additions when this is a body
		// rewrite. Without this, every body re-submission scans the whole
		// proposed body — and pre-existing AI-tells or banned phrases in the
		// original copy get flagged as if the model just introduced them, hard-
		// rejecting a tiny inline-link insert. The structural-quality checks
		// (paragraph_length, sentence_length, reading_level, jargon, html_cruft)
		// stay scoped to the whole body since those reflect the live reading
		// experience the reviewer will ship.
		$added_plain = isset( $diff_checks['_added_plain'] ) ? (string) $diff_checks['_added_plain'] : '';
		unset( $diff_checks['_added_plain'] );
		$is_rewrite = '' !== trim( (string) $current_html );
		if ( $is_rewrite ) {
			$scoped = self::lint_additive_text_only( $added_plain );
			foreach ( $scoped as $name => $check ) {
				$content_checks[ $name ] = $check;
			}
		}

		$checks = array_merge( $content_checks, $diff_checks );

		// v0.50.3: quote-quality checks, scoped to blockquotes the model is
		// newly introducing. lint_html_block() above already ran the unscoped
		// variant (every quote treated as new) — drop those keys first so a
		// legacy quote in the untouched body can never fail a surgical edit,
		// then re-run with current-body context so only NEW quotes are judged.
		unset( $checks['quote_source_link'], $checks['quote_attribution'], $checks['quote_relevance'] );
		foreach ( self::quote_checks( (string) $current_html, (string) $proposed_html ) as $name => $check ) {
			$checks[ $name ] = $check;
		}

		// v0.68 (P1): classify every failure as INTRODUCED by this change or
		// PRE-EXISTING in the current body. Motivation: on sites whose stored
		// bodies always fail the structural checks (raw Divi shortcode reads
		// as giant sentences), lint failed on EVERY edit, carried zero signal,
		// and got rationally ignored — right up until the one time it
		// mattered. Splitting the report restores the signal: "introduces 0
		// new issues; 4 pre-existing" reads very differently from "4 failed".
		//
		// Classification rules:
		//  - checks scoped to the ADDED text (em_dashes/ai_tells/style_guide
		//    on rewrites, all quote checks) are introduced BY CONSTRUCTION —
		//    they only ever fired on the model's own additions, even if the
		//    current body would fail the same check.
		//  - diff checks (deletion_ratio, redundancy, citation preservation…)
		//    describe the change itself: introduced by definition.
		//  - structural whole-body checks are pre-existing iff the same check
		//    already fails on the CURRENT body.
		$always_introduced = array_merge(
			array_keys( $diff_checks ),
			$is_rewrite ? array_keys( $scoped ) : array(),
			array( 'quote_source_link', 'quote_attribution', 'quote_relevance' )
		);
		$current_checks = $is_rewrite ? self::lint_html_block( $current_html ) : array();
		$classified     = self::classify_lint_failures( $checks, $current_checks, $always_introduced );
		foreach ( $classified['pre_existing'] as $pe_name ) {
			if ( isset( $checks[ $pe_name ] ) && is_array( $checks[ $pe_name ] ) ) {
				$checks[ $pe_name ]['pre_existing'] = true;
			}
		}

		$pass = 0;
		$fail = 0;
		$hard_violations = array();
		// Hard violations refuse the queue unless the caller explicitly passes
		// override_lint=true. authority_citation_preservation joined this list
		// in v0.10.0: dropping .gov / .edu / authority links during a body
		// rewrite is an EEAT regression that should not be silently queued.
		// We only mark it hard when at least one authority citation was
		// actually dropped (not when the post had zero citations to start
		// with — that path passes naturally so this branch never fires).
		//
		// v0.68: a PRE-EXISTING structural failure no longer escalates to
		// hard. A wall_of_text already in the stored body used to hard-block
		// every subsequent edit to that post (documented recurring trap);
		// the edit did not introduce the wall, so it must not be held
		// hostage by it. The failure still reports — as pre-existing.
		$hard_check_names = array( 'em_dashes', 'ai_tells', 'style_guide', 'placeholders', 'wall_of_text', 'address_consistency', 'hospital_comparison' );
		foreach ( $checks as $name => $c ) {
			if ( ! empty( $c['pass'] ) ) {
				$pass++;
				continue;
			}
			$fail++;
			$is_pre_existing = in_array( $name, $classified['pre_existing'], true );
			if ( in_array( $name, $hard_check_names, true ) && ! $is_pre_existing ) {
				$hard_violations[] = $name;
				continue;
			}
			if ( 'authority_citation_preservation' === $name && ! empty( $c['dropped'] ) && (int) $c['dropped'] > 0 ) {
				$hard_violations[] = $name;
			}
			// v0.50.3: an unverifiable quote (no source link) is a hard stop.
			// This only ever fires on quotes the model newly introduced, so
			// legacy content cannot trip it.
			if ( 'quote_source_link' === $name ) {
				$hard_violations[] = $name;
			}
		}

		return array(
			'pass'             => 0 === $fail,
			'pass_count'       => $pass,
			'fail_count'       => $fail,
			'total'            => count( $checks ),
			'hard_violations'  => $hard_violations,
			'introduced'       => $classified['introduced'],
			'pre_existing'     => $classified['pre_existing'],
			'checks'           => $checks,
			'generated_at'     => current_time( 'mysql' ),
		);
	}

	/**
	 * v0.68 (P1): split failing checks into introduced-by-this-change vs
	 * pre-existing-in-current-body. Pure so it is unit-testable in isolation.
	 *
	 * $always_introduced lists check names whose failure is attributable to
	 * the change BY CONSTRUCTION (additive-scoped and diff checks) — those
	 * never classify as pre-existing even when the current body would also
	 * fail them, because the instance that fired was in the added text.
	 */
	public static function classify_lint_failures( $checks, $current_checks, $always_introduced ) {
		$introduced   = array();
		$pre_existing = array();
		foreach ( (array) $checks as $name => $c ) {
			if ( ! empty( $c['pass'] ) ) {
				continue;
			}
			if ( in_array( $name, (array) $always_introduced, true ) ) {
				$introduced[] = $name;
				continue;
			}
			if ( isset( $current_checks[ $name ] ) && is_array( $current_checks[ $name ] ) && empty( $current_checks[ $name ]['pass'] ) ) {
				// Failing in both bodies — but if the proposal made it
				// measurably WORSE (more violations than the current body),
				// the worsening is this change's doing. Upgrade-only: a
				// magnitude tie or missing metric stays pre-existing, never
				// the reverse. Caveat: several checks cap their evidence
				// arrays (array_slice to 5), so worsening past the cap is
				// invisible to this comparison — it catches the common case,
				// not every case.
				$p_mag = self::failure_magnitude( $c );
				$c_mag = self::failure_magnitude( $current_checks[ $name ] );
				if ( null !== $p_mag && null !== $c_mag && $p_mag > $c_mag ) {
					$introduced[] = $name;
					continue;
				}
				$pre_existing[] = $name;
				continue;
			}
			$introduced[] = $name;
		}
		return array(
			'introduced'   => $introduced,
			'pre_existing' => $pre_existing,
		);
	}

	/**
	 * Best-effort numeric size of a failing check, for worsening comparison.
	 * Prefers an explicit count field, falls back to evidence-array length.
	 * Null when the check carries nothing countable.
	 */
	private static function failure_magnitude( $check ) {
		if ( ! is_array( $check ) ) {
			return null;
		}
		foreach ( array( 'count', 'violation_count', 'long_count' ) as $key ) {
			if ( isset( $check[ $key ] ) && is_numeric( $check[ $key ] ) ) {
				return (float) $check[ $key ];
			}
		}
		foreach ( array( 'violations', 'samples', 'matches', 'phrases' ) as $key ) {
			if ( isset( $check[ $key ] ) && is_array( $check[ $key ] ) ) {
				return (float) count( $check[ $key ] );
			}
		}
		return null;
	}

	/**
	 * Walk Elementor JSON for HTML-bearing fields (text-editor `editor`,
	 * html widget `html`, theme-post-content `content`) and concatenate
	 * their values. Preserves real <p>, <ul>, <h*> structure inside each
	 * widget so the readability linter sees per-paragraph boundaries
	 * instead of one giant block per widget. Headings outside text-editor
	 * widgets (heading widgets) are also synthesised into <h2> tags so
	 * sentence_length doesn't glue heading text to the next paragraph.
	 */
	private static function extract_elementor_html_for_lint( $raw ) {
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$out = '';
		self::walk_elementor_for_lint( $data, $out );
		return $out;
	}

	private static function walk_elementor_for_lint( $node, &$out ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		// Detect widget settings → grab HTML-bearing fields.
		if ( isset( $node['elType'] ) && 'widget' === $node['elType'] && isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
			$type     = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
			$settings = $node['settings'];

			if ( 'heading' === $type && ! empty( $settings['title'] ) ) {
				$level = isset( $settings['header_size'] ) ? preg_replace( '/[^a-z0-9]/i', '', (string) $settings['header_size'] ) : 'h2';
				if ( ! preg_match( '/^h[1-6]$/i', $level ) ) {
					$level = 'h2';
				}
				$out .= '<' . $level . '>' . wp_strip_all_tags( (string) $settings['title'] ) . '</' . $level . '>';
			}
			// Generic text-bearing fields. Wrap each in <p> so the lint
			// sentence-boundary normalizer treats them as separate blocks.
			// Pre-0.17 the bare "\n" separator got collapsed at strip-tags
			// time, gluing every widget's text into one mega-sentence that
			// failed sentence_length on every service page.
			foreach ( array( 'editor', 'html', 'content', 'text' ) as $field ) {
				if ( isset( $settings[ $field ] ) && is_string( $settings[ $field ] ) && '' !== trim( $settings[ $field ] ) ) {
					$out .= "\n<p>" . $settings[ $field ] . '</p>';
				}
			}
			// 0.17: widget-specific text fields that the v0.16 extractor
			// missed entirely. icon-box and image-box widgets store body
			// text in title_text + description_text; price-list and the
			// classic-accordion widgets store items in settings.items[].
			// Skipping these meant their text never reached the sentence-
			// length check AND when they DID surface (via parsed all_text
			// fallback) they joined every package card into one giant
			// concatenated "sentence" with no terminators.
			if ( in_array( $type, array( 'icon-box', 'image-box' ), true ) ) {
				if ( ! empty( $settings['title_text'] ) ) {
					$out .= "\n<h3>" . wp_strip_all_tags( (string) $settings['title_text'] ) . '</h3>';
				}
				if ( ! empty( $settings['description_text'] ) ) {
					$out .= "\n<p>" . $settings['description_text'] . '</p>';
				}
			}
			if ( 'price-list' === $type && ! empty( $settings['price_list'] ) && is_array( $settings['price_list'] ) ) {
				foreach ( $settings['price_list'] as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					if ( ! empty( $item['title'] ) ) {
						$out .= "\n<h3>" . wp_strip_all_tags( (string) $item['title'] ) . '</h3>';
					}
					if ( ! empty( $item['item_description'] ) ) {
						$out .= "\n<p>" . $item['item_description'] . '</p>';
					}
				}
			}
			if ( in_array( $type, array( 'accordion', 'toggle', 'nested-accordion' ), true ) && ! empty( $settings['items'] ) && is_array( $settings['items'] ) ) {
				foreach ( $settings['items'] as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$title = isset( $item['title'] ) ? $item['title'] : ( isset( $item['item_title'] ) ? $item['item_title'] : '' );
					$content = isset( $item['content'] ) ? $item['content'] : ( isset( $item['tab_content'] ) ? $item['tab_content'] : '' );
					if ( '' !== trim( (string) $title ) ) {
						$out .= "\n<h3>" . wp_strip_all_tags( (string) $title ) . '</h3>';
					}
					if ( '' !== trim( (string) $content ) ) {
						$out .= "\n<p>" . $content . '</p>';
					}
				}
			}
		}
		// Recurse into children (Elementor uses `elements` arrays).
		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				self::walk_elementor_for_lint( $child, $out );
			}
		}
		// Top-level Elementor data is a list of containers — walk it too.
		if ( ! isset( $node['elType'] ) ) {
			foreach ( $node as $child ) {
				if ( is_array( $child ) ) {
					self::walk_elementor_for_lint( $child, $out );
				}
			}
		}
	}

	/**
	 * Per-paragraph sentence count. Returns the list of paragraphs (HTML
	 * stripped) that exceed 3 sentences. Each entry has the snippet and
	 * the sentence count.
	 */
	private static function paragraph_length_violations( $html ) {
		$violations = array();
		// Pull <p> ... </p> blocks out. wpautop wraps loose lines in <p> at render
		// time, so a body that uses double newlines without explicit <p> tags is
		// also handled below.
		$paragraphs = array();
		if ( preg_match_all( '/<p\b[^>]*>([\s\S]*?)<\/p>/i', $html, $m ) ) {
			foreach ( $m[1] as $p ) {
				$paragraphs[] = $p;
			}
		}
		// Fallback: split on double-newline if no <p> tags.
		if ( empty( $paragraphs ) ) {
			$plain = wp_strip_all_tags( $html );
			foreach ( preg_split( '/\n\s*\n/', $plain ) as $p ) {
				if ( '' !== trim( $p ) ) {
					$paragraphs[] = $p;
				}
			}
		}
		foreach ( $paragraphs as $p ) {
			$text  = trim( wp_strip_all_tags( $p ) );
			if ( '' === $text ) {
				continue;
			}
			$count = self::count_sentences( $text );
			if ( $count > 5 ) {
				$snippet = mb_substr( preg_replace( '/\s+/u', ' ', $text ), 0, 140 );
				if ( mb_strlen( $text ) > 140 ) {
					$snippet .= '…';
				}
				$violations[] = array(
					'sentences' => $count,
					'snippet'   => $snippet,
				);
			}
		}
		return $violations;
	}

	/**
	 * Stats over every sentence in a plain-text body. Returns total count,
	 * count over 25 words, average words per sentence, and a sample of
	 * the longest offenders.
	 */
	private static function sentence_length_stats( $plain ) {
		$plain = (string) $plain;
		// Split on sentence terminators followed by whitespace + capital letter,
		// or end-of-string. Avoid splitting on common abbreviations.
		$sentences = preg_split( '/(?<=[\.\?\!])\s+(?=[A-Z0-9"\'\(])/u', $plain );
		$total     = 0;
		$long      = 0;
		$word_sum  = 0;
		$samples   = array();
		foreach ( (array) $sentences as $s ) {
			$s = trim( $s );
			if ( '' === $s ) {
				continue;
			}
			$words = str_word_count( $s );
			if ( $words < 3 ) {
				continue; // Skip headings / bullet fragments / "Yes." answers.
			}
			$total++;
			$word_sum += $words;
			if ( $words > 25 ) {
				$long++;
				if ( count( $samples ) < 5 ) {
					$samples[] = array(
						'words'   => $words,
						'snippet' => mb_substr( $s, 0, 140 ) . ( mb_strlen( $s ) > 140 ? '…' : '' ),
					);
				}
			}
		}
		return array(
			'total'     => $total,
			'long'      => $long,
			'avg_words' => $total > 0 ? round( $word_sum / $total, 1 ) : 0,
			'samples'   => $samples,
		);
	}

	/**
	 * Sentence count for a single piece of text. Coarse but adequate —
	 * counts terminators after dropping common abbreviation patterns.
	 */
	private static function count_sentences( $text ) {
		$text = (string) $text;
		// Mask common abbreviations. Strip ALL periods inside the abbreviation,
		// not just the trailing one — otherwise "U.S." gets reduced to "U.S"
		// and the inner period still counts as a sentence terminator. Same
		// problem for "e.g." / "i.e." / "U.K.".
		$text = preg_replace_callback(
			'/\b(Mr|Mrs|Ms|Dr|St|vs|etc|e\.g|i\.e|U\.S|U\.K|Inc)\./i',
			function ( $m ) {
				return str_replace( '.', '', $m[1] );
			},
			$text
		);
		// Mask DECIMAL numbers before counting. "$1,259.99" carries a period
		// that is not a sentence terminator, and this function counts every
		// period it sees, so each price in a paragraph invented an extra
		// sentence. Found on sids-ponds 2026-08-23: a 4-sentence paragraph
		// quoting three prices was counted as 7 and failed paragraph_length,
		// which is a guaranteed false positive on any e-commerce copy that
		// states what something costs. Same masking idea as the abbreviation
		// pass above, applied repeatedly so 1.2.3 collapses fully.
		$text = preg_replace( '/(\d)\.(\d)/u', '$1$2', $text );
		$text = preg_replace( '/(\d)\.(\d)/u', '$1$2', $text );

		// Count terminators.
		preg_match_all( '/[\.\?\!]+/u', $text, $m );
		$count = isset( $m[0] ) ? count( $m[0] ) : 0;
		return max( 1, $count );
	}

	/**
	 * Flesch-Kincaid grade level. Pure ASCII syllable estimator (Naive
	 * vowel-cluster count) — good enough for English-language editorial
	 * gating, not a replacement for a real linguistics pipeline.
	 */
	private static function flesch_kincaid_grade( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return 0.0;
		}
		$words = str_word_count( $plain, 1 );
		$word_count = count( $words );
		if ( 0 === $word_count ) {
			return 0.0;
		}
		preg_match_all( '/[\.\?\!]+/u', $plain, $sm );
		$sentence_count = max( 1, isset( $sm[0] ) ? count( $sm[0] ) : 1 );
		$syllable_count = 0;
		foreach ( $words as $w ) {
			$syllable_count += self::estimate_syllables( $w );
		}
		// Flesch-Kincaid grade level formula.
		$grade = ( 0.39 * ( $word_count / $sentence_count ) )
			+ ( 11.8 * ( $syllable_count / $word_count ) )
			- 15.59;
		return round( $grade, 1 );
	}

	/**
	 * Naive syllable estimator: count vowel groups, drop a trailing silent 'e',
	 * minimum of 1. Underestimates polysyllabic words slightly but is stable
	 * enough for grade-level signaling.
	 */
	private static function estimate_syllables( $word ) {
		$word = strtolower( preg_replace( '/[^a-zA-Z]/', '', (string) $word ) );
		if ( '' === $word ) {
			return 0;
		}
		// Drop trailing silent e (but not "le" endings like "purple").
		if ( strlen( $word ) > 2 && 'e' === $word[ strlen( $word ) - 1 ] && 'l' !== $word[ strlen( $word ) - 2 ] ) {
			$word = substr( $word, 0, -1 );
		}
		preg_match_all( '/[aeiouy]+/i', $word, $m );
		$count = isset( $m[0] ) ? count( $m[0] ) : 0;
		return max( 1, $count );
	}

	/**
	 * Count of common HTML cruft: classic-editor font-weight: 400 spans,
	 * MSO/Word styles, empty paragraphs.
	 */
	private static function html_cruft_count( $html ) {
		$html = (string) $html;
		$font_weight = preg_match_all( '/<span[^>]*style=["\'][^"\']*font-weight:\s*400[^"\']*["\'][^>]*>/i', $html );
		$mso         = preg_match_all( '/(?:mso-[a-z\-]+\s*:|class=["\'][^"\']*Mso[A-Z])/i', $html );
		$empty       = preg_match_all( '/<p[^>]*>\s*(?:&nbsp;|\s)*<\/p>/i', $html );
		return array(
			'font_weight_spans' => (int) $font_weight,
			'mso_styles'        => (int) $mso,
			'empty_paragraphs'  => (int) $empty,
		);
	}

	/**
	 * Default jargon dictionary. Conservative list of medical / SEO / business
	 * terms that often need a plain-language gloss. The user can extend per-site
	 * by adding a "## Jargon" section to the style guide (parsed below).
	 */
	private static function jargon_words() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$default = array(
			// Medical
			'fluctuance', 'lymphangitis', 'cellulitis', 'lymphedema', 'bacteremia',
			'sepsis', 'erythema', 'myocardial', 'tachycardia', 'bradycardia',
			'dyspnea', 'cyanosis', 'hematoma', 'edema', 'epistaxis', 'pyrexia',
			'syncope', 'tachypnea', 'hypovolemia', 'hyperkalemia', 'decolonization',
			// SEO / marketing
			'sitelink', 'cannibalization', 'serp', 'crawl-budget', 'pagerank',
			'eeat', 'ymyl', 'rich-result', 'schema.org', 'structured-data',
			// Business
			'synergize', 'ideate', 'streamline', 'rightsize', 'operationalize',
		);
		// Pull "## Jargon" section from style guide for site-specific overrides.
		$blob = (string) get_option( 'cc_assistant_style_guide', '' );
		if ( $blob && preg_match( '/(?:##+|^)\s*Jargon[^\n]*\n([\s\S]*?)(?:\n\s*##|\z)/im', $blob, $m ) ) {
			if ( preg_match_all( '/^\s*[-\*]\s*(.+)$/m', $m[1], $bm ) ) {
				foreach ( $bm[1] as $line ) {
					$line = trim( preg_replace( '/\([^\)]*\)/', '', $line ) );
					foreach ( explode( ',', $line ) as $part ) {
						$part = trim( $part, " \t\"'.,;:" );
						if ( strlen( $part ) >= 3 ) {
							$default[] = mb_strtolower( $part );
						}
					}
				}
			}
		}
		$cache = array_unique( $default );
		return $cache;
	}

	private static function jargon_density( $plain ) {
		$plain = (string) $plain;
		$lc    = mb_strtolower( $plain );
		$words = str_word_count( $plain );
		$matches = array();
		$total   = 0;
		foreach ( self::jargon_words() as $term ) {
			$pattern = '/\b' . preg_quote( $term, '/' ) . '\b/u';
			$count   = preg_match_all( $pattern, $lc );
			if ( $count > 0 ) {
				$total += $count;
				$matches[] = array( 'term' => $term, 'count' => $count );
			}
		}
		usort( $matches, function ( $a, $b ) { return $b['count'] - $a['count']; } );
		return array(
			'total'      => $total,
			'word_count' => $words,
			'per_1000'   => $words > 0 ? round( $total * 1000 / $words, 2 ) : 0,
			'matches'    => $matches,
		);
	}

	/**
	 * Bigram overlap between two plain-text blobs. Returns ratio of bigrams
	 * in `a` that also appear in `b` (Jaccard-ish over multi-set membership)
	 * plus a sample of shared bigrams. Used by the redundancy check.
	 */
	private static function bigram_overlap( $a, $b ) {
		$a = preg_replace( '/[^a-z0-9\s]/u', ' ', mb_strtolower( (string) $a ) );
		$b = preg_replace( '/[^a-z0-9\s]/u', ' ', mb_strtolower( (string) $b ) );
		$a_words = preg_split( '/\s+/', trim( $a ) ?: '' );
		$b_words = preg_split( '/\s+/', trim( $b ) ?: '' );
		// Drop function words from both sides so the signal is content bigrams.
		$stop = array(
			'a','an','and','or','but','if','the','of','for','to','in','on','at','by',
			'is','are','was','were','be','been','being','it','its','this','that','these',
			'those','as','from','with','than','then','so','not','no','can','may','will',
			'have','has','had','do','does','did','you','your','our','we','they','their',
			'i','me','my','he','she','him','her','his','its','about','into','out','up',
			'down','over','under','more','less','most','least','some','any','all','any',
		);
		$flip = array_flip( $stop );
		$a_words = array_values( array_filter( $a_words, function ( $w ) use ( $flip ) { return '' !== $w && ! isset( $flip[ $w ] ); } ) );
		$b_words = array_values( array_filter( $b_words, function ( $w ) use ( $flip ) { return '' !== $w && ! isset( $flip[ $w ] ); } ) );
		if ( count( $a_words ) < 4 ) {
			return array( 'ratio' => 0.0, 'shared' => array() );
		}
		$bigrams = function ( $words ) {
			$out = array();
			$n   = count( $words );
			for ( $i = 0; $i < $n - 1; $i++ ) {
				$out[ $words[ $i ] . ' ' . $words[ $i + 1 ] ] = true;
			}
			return $out;
		};
		$ag = $bigrams( $a_words );
		$bg = $bigrams( $b_words );
		if ( empty( $ag ) ) {
			return array( 'ratio' => 0.0, 'shared' => array() );
		}
		$shared = array_keys( array_intersect_key( $ag, $bg ) );
		return array(
			'ratio'  => count( $shared ) / count( $ag ),
			'shared' => $shared,
		);
	}

	/**
	 * Walks the body HTML, finds question-shaped H2s (start with what/why/how/etc.
	 * or end with ?), and measures the word count of the text immediately
	 * following each — up to the next heading. 40–60 words is the
	 * featured-snippet sweet spot for direct answers; anything outside that
	 * range is flagged.
	 *
	 * Returns a list of misses with the heading text and the word count.
	 */
	private static function analyze_question_h2_answers( $html ) {
		$misses = array();
		// Split on H2 boundaries while keeping the heading text we land on.
		if ( ! preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>(.*?)(?=<h[2-6]\b|\z)/is', $html, $m, PREG_SET_ORDER ) ) {
			return $misses;
		}
		foreach ( $m as $row ) {
			$heading_text = trim( wp_strip_all_tags( $row[1] ) );
			$body_after   = trim( wp_strip_all_tags( $row[2] ) );
			if ( '' === $heading_text ) {
				continue;
			}
			$is_question = preg_match( '/^(what|why|how|when|where|who|which|is|are|can|does|do|should|will)\b/i', $heading_text )
				|| substr( $heading_text, -1 ) === '?';
			if ( ! $is_question ) {
				continue;
			}
			$words = str_word_count( $body_after );
			if ( $words < 40 || $words > 60 ) {
				$misses[] = array(
					'heading' => $heading_text,
					'words'   => $words,
				);
			}
		}
		return $misses;
	}
}

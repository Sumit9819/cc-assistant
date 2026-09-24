<?php
/**
 * Internal linking — orphan detection, link suggestions, full site link graph.
 *
 * The graph is rebuilt nightly via cron. Reads/queries hit the cached
 * wp_cc_link_graph table only. Suggestions are deterministic phrase matching
 * (post title and a few normalized variants), no LLM required.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Internal_Links {

	const SUGGEST_MIN_TITLE_WORDS = 2;
	const SUGGEST_MAX_RESULTS     = 30;

	/* ---------------------------------------------------------------------
	 * Graph construction
	 * ------------------------------------------------------------------- */

	public static function rebuild_graph() {
		// Lock to prevent two concurrent cron ticks from both DELETEing and
		// re-INSERTing rows, which would briefly double the edge count.
		$lock_key = 'cc_link_graph_rebuilding';
		if ( get_transient( $lock_key ) ) {
			return false;
		}
		set_transient( $lock_key, 1, 10 * MINUTE_IN_SECONDS );

		global $wpdb;
		$table   = $wpdb->prefix . 'cc_link_graph';
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );

		@set_time_limit( 300 );

		$wpdb->query( "DELETE FROM {$table}" );

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN (" . implode( ',', array_fill( 0, count( $allowed ), '%s' ) ) . ')',
				$allowed
			)
		);

		$now = current_time( 'mysql', true );
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$body  = self::extract_body( $post );
			$edges = self::extract_internal_links( $body, $post_id );
			foreach ( $edges as $edge ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table}
							(source_post_id, target_post_id, anchor_text, href, rel_attr, discovered_at)
						VALUES (%d, %d, %s, %s, %s, %s)",
						$post_id,
						$edge['target_post_id'],
						$edge['anchor_text'],
						$edge['href'],
						$edge['rel_attr'],
						$now
					)
				);
			}
		}

		// Add "global" edges for links that appear in registered nav menus
		// (header/footer/etc.). Without this, every page reachable only via
		// site nav looks orphaned + click_depth treats it as unreachable —
		// hits multilingual sites especially hard since translated pages are
		// typically only linked through a per-language menu / language switcher.
		// On WPML/Polylang each menu's edges are attributed to its own
		// language's front page, so a Spanish menu linking /es/blog/ becomes
		// an edge from /es/ (not /), making the click-depth BFS correctly
		// model how a Spanish-locale visitor reaches the page in one click.
		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';
		$default_front = self::get_front_page_id();

		// Combine three sources of "virtual" edges that all attribute to the
		// front page when no specific source is known:
		//  - registered nav menus (header / footer / lang switcher)
		//  - footer widget areas (Custom HTML / Text / Block widgets that
		//    hardcode legal-page links — common pattern for Privacy Policy,
		//    Terms, HIPAA, etc.; these never appear in body content but are
		//    site-wide via the footer template)
		//  - translation pair edges (each post gets an edge to/from its
		//    siblings in other languages so the language-switcher behavior
		//    is modeled even when it's a widget/shortcode rather than a
		//    nav menu item)
		$virtual_edges = array_merge(
			self::extract_global_links(),
			self::extract_footer_widget_edges(),
			self::extract_elementor_template_edges(),
			self::extract_divi_template_edges()
		);
		foreach ( $virtual_edges as $edge ) {
			$source = isset( $edge['source_post_id'] ) ? (int) $edge['source_post_id'] : 0;
			if ( $source <= 0 ) {
				$source = $default_front;
			}
			if ( $source <= 0 || (int) $edge['target_post_id'] === $source ) {
				continue;
			}
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table}
						(source_post_id, target_post_id, anchor_text, href, rel_attr, discovered_at)
					VALUES (%d, %d, %s, %s, %s, %s)",
					$source,
					$edge['target_post_id'],
					$edge['anchor_text'],
					$edge['href'],
					$edge['rel_attr'],
					$now
				)
			);
		}

		// Translation edges have explicit per-post sources (each post links
		// to its translations) so they bypass the front-page fallback.
		$translation_edges = self::extract_translation_edges();
		foreach ( $translation_edges as $edge ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table}
						(source_post_id, target_post_id, anchor_text, href, rel_attr, discovered_at)
					VALUES (%d, %d, %s, %s, %s, %s)",
					(int) $edge['source_post_id'],
					(int) $edge['target_post_id'],
					$edge['anchor_text'],
					$edge['href'],
					$edge['rel_attr'],
					$now
				)
			);
		}
		CC_Assistant_Multilingual::reset_caches();

		// Bust dashboard summary cache so the rebuild's results are visible immediately.
		delete_transient( 'cc_assistant_dashboard_links' );
		// Weekly advisor cards (orphans, click depth) read directly from the
		// link graph and a stale advisor transient will keep showing the
		// pre-rebuild numbers for up to an hour. Bust it so the dashboard
		// reflects the new graph on the next render.
		require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
		CC_Assistant_Weekly_Advisor::invalidate();
		delete_transient( $lock_key );

		// Mark "graph has been built at least once" so the dashboard's empty
		// state can tell a fresh install ("first sync pending") apart from a
		// site that genuinely has 0 internal links. 30-day TTL is plenty —
		// the daily rebuild cron refreshes it.
		set_transient( 'cc_link_graph_last_run', current_time( 'mysql' ), 30 * DAY_IN_SECONDS );

		return true;
	}

	private static function extract_body( $post ) {
		$body = (string) $post->post_content;
		$elementor = get_post_meta( $post->ID, '_elementor_data', true );
		if ( ! empty( $elementor ) ) {
			// Append all string values from the Elementor JSON so links inside widgets count.
			$body .= "\n" . self::flatten_elementor_text( $elementor );
		}
		return $body;
	}

	/**
	 * Resolve the site's front page post ID. Returns 0 when the home is the
	 * blog index (no static page) or when the configured page_on_front is
	 * missing — caller should skip global edges in that case.
	 */
	private static function get_front_page_id() {
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$front = (int) get_option( 'page_on_front' );
			if ( $front > 0 ) {
				return $front;
			}
		}
		$front = (int) url_to_postid( home_url( '/' ) );
		return $front > 0 ? $front : 0;
	}

	/**
	 * Pull every internal link from registered nav menus (header / footer /
	 * mobile / language-specific). Used by rebuild_graph to model the fact
	 * that menu links appear on every page — without this, the orphan/
	 * click-depth analysis only sees in-body links and reports nearly every
	 * page as unreachable on sites that rely on the site nav for navigation.
	 *
	 * Returns an array of edges (target_post_id, anchor_text, href, rel_attr).
	 * Multilingual: WPML/Polylang register one menu per language; iterating
	 * wp_get_nav_menus() catches all of them, so /es/ targets are included.
	 */
	private static function extract_global_links() {
		$out = array();
		if ( ! function_exists( 'wp_get_nav_menus' ) || ! function_exists( 'wp_get_nav_menu_items' ) ) {
			return $out;
		}
		$menus = wp_get_nav_menus();
		if ( ! is_array( $menus ) || empty( $menus ) ) {
			return $out;
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$home_host = $home_host ? preg_replace( '/^www\./', '', mb_strtolower( $home_host ) ) : '';

		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';

		// Dedupe per (source, target) pair so the same target reachable from
		// multiple same-language menus only counts once. Translation pairs
		// (the Spanish copy of an English page reached via the Spanish menu)
		// are intentionally NOT deduped together — they have different
		// sources and the analysis cares about reachability per language.
		$seen = array(); // "$source-$target" => true.
		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
			if ( ! is_array( $items ) ) {
				continue;
			}
			$menu_lang   = CC_Assistant_Multilingual::language_of_menu( $menu );
			$menu_source = $menu_lang ? CC_Assistant_Multilingual::front_page_for_language( $menu_lang ) : 0;
			// On non-multilingual sites menu_source stays 0 and rebuild_graph
			// falls back to the global default front page.

			foreach ( $items as $item ) {
				$target = 0;
				$href   = isset( $item->url ) ? (string) $item->url : '';

				// post_type items carry the linked post ID directly (most reliable).
				if ( isset( $item->type ) && 'post_type' === $item->type && (int) $item->object_id > 0 ) {
					$target = (int) $item->object_id;
				} elseif ( '' !== $href ) {
					// custom URL items: only follow if same-host, then resolve.
					$parts = wp_parse_url( $href );
					$host  = isset( $parts['host'] ) ? preg_replace( '/^www\./', '', mb_strtolower( $parts['host'] ) ) : '';
					if ( '' !== $host && $host !== $home_host ) {
						continue;
					}
					// Menu items routinely point at Custom Permalinks URLs; those
					// resolved to 0 here, so a page linked from the main menu
					// still counted as orphaned.
					$target = CC_Assistant_URL_Resolver::to_post_id( $href );
				}

				if ( $target <= 0 ) {
					continue;
				}
				$dedupe_key = $menu_source . '-' . $target;
				if ( isset( $seen[ $dedupe_key ] ) ) {
					continue;
				}
				$seen[ $dedupe_key ] = true;

				$anchor = trim( wp_strip_all_tags( isset( $item->title ) ? (string) $item->title : '' ) );
				if ( '' === $anchor ) {
					$anchor = '[nav]';
				}

				$out[] = array(
					'source_post_id' => $menu_source, // 0 = let caller fall back to default front page
					'target_post_id' => $target,
					'anchor_text'    => mb_substr( $anchor, 0, 500 ),
					'href'           => mb_substr( $href, 0, 500 ),
					'rel_attr'       => 'nav',
				);
			}
		}
		return $out;
	}

	/**
	 * Pull internal links out of theme footer / sidebar widget areas.
	 * Necessary because many themes hardcode policy-page links (Privacy,
	 * Terms, HIPAA) into a Custom HTML or Text widget in the footer rather
	 * than a registered nav menu — extract_global_links would miss them and
	 * those pages would falsely register as orphans.
	 *
	 * Returns edges in the same shape as extract_global_links: source_post_id
	 * is 0 (caller substitutes the default front page) and rel_attr is
	 * 'widget' so dashboards can distinguish widget-area edges from nav
	 * menu edges if useful.
	 */
	private static function extract_footer_widget_edges() {
		$out = array();
		if ( ! function_exists( 'wp_get_sidebars_widgets' ) ) {
			return $out;
		}
		$sidebars = wp_get_sidebars_widgets();
		if ( ! is_array( $sidebars ) ) {
			return $out;
		}

		// Aggregate widget HTML across all active sidebars (footer, sidebar,
		// sub-footer, etc.). Skip wp_inactive_widgets which holds widgets
		// dragged out of any sidebar — those don't render anywhere.
		$html = '';
		foreach ( $sidebars as $sidebar_id => $widget_ids ) {
			if ( 'wp_inactive_widgets' === $sidebar_id || ! is_array( $widget_ids ) ) {
				continue;
			}
			foreach ( $widget_ids as $widget_id ) {
				$html .= ' ' . self::read_widget_html( (string) $widget_id );
			}
		}

		if ( '' === trim( $html ) ) {
			return $out;
		}

		// Reuse extract_internal_links's regex parser by feeding it the
		// aggregated HTML. Pass source_post_id=0 so the URL→post resolver
		// doesn't accidentally filter the link out as a self-loop.
		$edges = self::extract_internal_links( $html, 0 );
		foreach ( $edges as $edge ) {
			$out[] = array(
				'source_post_id' => 0,
				'target_post_id' => $edge['target_post_id'],
				'anchor_text'    => $edge['anchor_text'],
				'href'           => $edge['href'],
				'rel_attr'       => 'widget',
			);
		}
		return $out;
	}

	/**
	 * Read a widget's stored HTML content. Widget IDs follow the pattern
	 * "$base-$number" (e.g. "text-3", "custom_html-2", "block-7"). Each
	 * widget type stores its instances under option key "widget_$base".
	 *
	 * Covers the widget types most commonly used to host hardcoded links:
	 *   - text widget (`text` field)
	 *   - custom_html widget (`content` field)
	 *   - block widget / Gutenberg footer (`content` field)
	 * Other widget types (calendar, recent_posts, etc.) don't carry
	 * authoritative href targets so we don't try to interpret them.
	 */
	private static function read_widget_html( $widget_id ) {
		if ( ! preg_match( '/^([a-z_]+)-(\d+)$/i', $widget_id, $m ) ) {
			return '';
		}
		$base   = $m[1];
		$number = (int) $m[2];
		$option = get_option( 'widget_' . $base );
		if ( ! is_array( $option ) || ! isset( $option[ $number ] ) || ! is_array( $option[ $number ] ) ) {
			return '';
		}
		$instance = $option[ $number ];

		// Pluck the HTML-bearing fields. Different widget types use different
		// field names for the same conceptual "body content".
		$fields = array( 'text', 'content', 'html', 'after_widget' );
		$html   = '';
		foreach ( $fields as $f ) {
			if ( isset( $instance[ $f ] ) && is_string( $instance[ $f ] ) ) {
				$html .= ' ' . $instance[ $f ];
			}
		}
		return $html;
	}

	/**
	 * Pull internal links out of Elementor Theme Builder templates
	 * (header, footer, archive, single). Sites built with Elementor Pro
	 * commonly host their footer in an `elementor_library` post of type
	 * 'footer' rather than a WordPress widget area or registered nav menu.
	 * Without scanning these, every link in that footer (Privacy, Terms,
	 * HIPAA, etc.) is invisible to the graph and the linked pages get
	 * falsely flagged as buried/orphan.
	 *
	 * Returns edges with source_post_id=0 → caller substitutes the default
	 * front page (the templates render on EVERY page, so attribution to the
	 * front page is the most accurate single-source representation; for
	 * multilingual sites the translation_edges fan-out keeps per-language
	 * reachability accurate).
	 */
	private static function extract_elementor_template_edges() {
		$out = array();
		if ( ! post_type_exists( 'elementor_library' ) ) {
			return $out;
		}

		global $wpdb;
		// Limit to template types that render across many pages. Skip
		// 'page', 'section', 'widget' types — those are reusable building
		// blocks attached to specific posts and their links are already
		// captured via the per-post extract_body() pass.
		$template_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			 WHERE p.post_type = %s
			   AND p.post_status = 'publish'
			   AND m.meta_key = %s
			   AND m.meta_value IN ('header', 'footer', 'archive', 'single', 'product')",
			'elementor_library',
			'_elementor_template_type'
		) );
		if ( empty( $template_ids ) ) {
			return $out;
		}

		// Aggregate the flattened HTML of every relevant template, then
		// run the same regex parser body content uses. Synthetic anchors
		// from flatten_elementor_text mean structured Elementor link
		// fields are caught alongside any inline <a> tags in editor bodies.
		$html = '';
		foreach ( $template_ids as $tid ) {
			$data = get_post_meta( (int) $tid, '_elementor_data', true );
			if ( ! empty( $data ) ) {
				$html .= ' ' . self::flatten_elementor_text( $data );
			}
		}

		if ( '' === trim( $html ) ) {
			return $out;
		}

		$edges = self::extract_internal_links( $html, 0 );
		foreach ( $edges as $edge ) {
			$out[] = array(
				'source_post_id' => 0,
				'target_post_id' => $edge['target_post_id'],
				'anchor_text'    => $edge['anchor_text'],
				'href'           => $edge['href'],
				'rel_attr'       => 'elementor_template',
			);
		}
		return $out;
	}

	/**
	 * v0.69 — Divi Theme Builder counterpart to extract_elementor_template_edges.
	 * Field failure: on a Divi+Woo store, the footer's Refund Policy / legal
	 * links live in a Divi footer LAYOUT (post type et_footer_layout), which no
	 * extraction pass read — so those pages showed as orphans and click_depth
	 * called them unreachable. Divi layouts store shortcodes, where links exist
	 * both as inline <a href> in module inner content AND as bare URLs in
	 * shortcode attributes (button_link, url, link_option_url...). Inline
	 * anchors flow through the normal parser; attribute URLs get wrapped in
	 * synthetic anchors first, mirroring flatten_elementor_text. Image/video
	 * attribute URLs are excluded by name so uploads don't pollute the graph.
	 */
	private static function extract_divi_template_edges() {
		$out        = array();
		$divi_types = array_filter(
			array( 'et_header_layout', 'et_body_layout', 'et_footer_layout' ),
			'post_type_exists'
		);
		if ( empty( $divi_types ) ) {
			return $out;
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $divi_types ), '%s' ) );
		$layout_ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)",
				$divi_types
			)
		);
		if ( empty( $layout_ids ) ) {
			return $out;
		}

		$html = '';
		foreach ( $layout_ids as $lid ) {
			$content = (string) get_post_field( 'post_content', (int) $lid );
			if ( '' === $content ) {
				continue;
			}
			// Inline anchors in module inner content pass through unchanged.
			$html .= ' ' . $content;
			// Bare URLs in link-carrying shortcode attributes become synthetic
			// anchors. Attribute NAME gates the match: it must contain link/url
			// and must not be an asset field (image/src/video/logo).
			if ( preg_match_all( '/\b([a-z_]*(?:link|url)[a-z_]*)="(https?:\/\/[^"]+)"/i', $content, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $hit ) {
					if ( preg_match( '/image|src|video|logo/i', $hit[1] ) ) {
						continue;
					}
					$html .= ' <a href="' . esc_url( $hit[2] ) . '"></a>';
				}
			}
		}
		if ( '' === trim( $html ) ) {
			return $out;
		}

		$edges = self::extract_internal_links( $html, 0 );
		foreach ( $edges as $edge ) {
			$out[] = array(
				'source_post_id' => 0,
				'target_post_id' => $edge['target_post_id'],
				'anchor_text'    => $edge['anchor_text'],
				'href'           => $edge['href'],
				'rel_attr'       => 'divi_template',
			);
		}
		return $out;
	}

	/**
	 * Add edges between every post and its translations in other languages.
	 * Models the language switcher (whether it's a nav menu item, widget,
	 * shortcode, or theme-rendered link) as a graph edge so the language's
	 * front page isn't falsely flagged as orphan and so translated content
	 * reachable only via the switcher isn't reported as buried.
	 *
	 * Returns empty on single-language sites — no overhead when not needed.
	 */
	private static function extract_translation_edges() {
		if ( ! CC_Assistant_Multilingual::is_active() ) {
			return array();
		}

		global $wpdb;
		$allowed      = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
		$post_ids     = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)",
				$allowed
			)
		);

		$out  = array();
		$seen = array(); // dedupe per (source, target).
		foreach ( $post_ids as $pid ) {
			$pid = (int) $pid;
			$tx  = CC_Assistant_Multilingual::translations_of( $pid );
			foreach ( $tx as $lang => $sibling ) {
				$sibling = (int) $sibling;
				if ( $sibling <= 0 || $sibling === $pid ) {
					continue;
				}
				$key = $pid . '-' . $sibling;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$href = get_permalink( $sibling );
				$out[] = array(
					'source_post_id' => $pid,
					'target_post_id' => $sibling,
					'anchor_text'    => '[lang:' . $lang . ']',
					'href'           => $href ? mb_substr( $href, 0, 500 ) : '',
					'rel_attr'       => 'alternate',
				);
			}
		}
		return $out;
	}

	private static function flatten_elementor_text( $raw ) {
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$out = '';
		array_walk_recursive(
			$data,
			function ( $value ) use ( &$out ) {
				if ( ! is_string( $value ) ) {
					return;
				}
				if ( false !== mb_strpos( $value, '<' ) ) {
					// HTML-bearing field (text-editor body, html widget, etc.).
					// Pass through as-is so existing <a href> tags inside the
					// HTML are caught by extract_internal_links's regex.
					$out .= ' ' . $value;
					return;
				}
				if ( false !== mb_strpos( $value, 'http' ) ) {
					// Bare URL value from a structured Elementor link field
					// (flip-box link.url, button url, icon-list link.url,
					// image-box link.url, icon-box link.url, etc.). The regex
					// parser only matches <a href="..."> HTML, so wrap bare
					// URLs in a synthetic anchor so they register as edges.
					// Without this, Polylang-translated pages whose flip-boxes
					// point to /es/... look like they have NO outbound links
					// to those targets, while the page's stale post_content
					// fallback (auto-generated when the page was cloned from
					// the source language) keeps reporting the OLD-language
					// URLs because those DO have <a href> wrappers.
					$out .= ' <a href="' . esc_url( $value ) . '"></a>';
				}
			}
		);
		return $out;
	}

	private static function extract_internal_links( $html, $source_post_id ) {
		$out = array();
		if ( '' === trim( (string) $html ) ) {
			return $out;
		}
		if ( ! preg_match_all( '/<a\s[^>]*href=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER ) ) {
			return $out;
		}
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$home_host = $home_host ? preg_replace( '/^www\./', '', mb_strtolower( $home_host ) ) : '';

		foreach ( $m as $match ) {
			$href = trim( html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === stripos( $href, 'mailto:' ) || 0 === stripos( $href, 'tel:' ) || 0 === stripos( $href, 'javascript:' ) ) {
				continue;
			}
			$parts = wp_parse_url( $href );
			$host  = isset( $parts['host'] ) ? preg_replace( '/^www\./', '', mb_strtolower( $parts['host'] ) ) : '';
			if ( $host && $host !== $home_host ) {
				continue;
			}
			// v0.71.0: this used to carry its own fallback chain (permalink ->
			// full path -> last segment, language prefix stripped). That chain
			// now lives in CC_Assistant_URL_Resolver together with the two cases
			// it was missing — Custom Permalinks postmeta and the redirect table
			// — which is why pages swapped onto a custom URL kept reporting as
			// orphaned even when the hub linked straight to them.
			$target = CC_Assistant_URL_Resolver::to_post_id( $href );
			if ( ! $target || (int) $target === (int) $source_post_id ) {
				continue;
			}

			$rel = '';
			if ( preg_match( '/rel=("|\')(.*?)\1/i', $match[0], $rel_m ) ) {
				$rel = mb_substr( $rel_m[2], 0, 50 );
			}

			$anchor = trim( wp_strip_all_tags( $match[3] ) );

			$out[] = array(
				'target_post_id' => (int) $target,
				'anchor_text'    => mb_substr( $anchor, 0, 500 ),
				'href'           => mb_substr( $href, 0, 500 ),
				'rel_attr'       => $rel,
			);
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Reads
	 * ------------------------------------------------------------------- */

	public static function inbound_count( $post_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT source_post_id) FROM {$wpdb->prefix}cc_link_graph WHERE target_post_id = %d",
				(int) $post_id
			)
		);
	}

	public static function outbound_count( $post_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT target_post_id) FROM {$wpdb->prefix}cc_link_graph WHERE source_post_id = %d",
				(int) $post_id
			)
		);
	}

	public static function inbound_links( $post_id, $limit = 50 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.source_post_id AS post_id, g.anchor_text, g.href, p.post_title
				FROM {$wpdb->prefix}cc_link_graph g
				INNER JOIN {$wpdb->posts} p ON p.ID = g.source_post_id
				WHERE g.target_post_id = %d
				ORDER BY p.post_modified DESC
				LIMIT %d",
				(int) $post_id,
				max( 1, (int) $limit )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function outbound_links( $post_id, $limit = 200 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.target_post_id AS post_id, g.anchor_text, g.href, p.post_title
				FROM {$wpdb->prefix}cc_link_graph g
				INNER JOIN {$wpdb->posts} p ON p.ID = g.target_post_id
				WHERE g.source_post_id = %d
				ORDER BY p.post_modified DESC
				LIMIT %d",
				(int) $post_id,
				max( 1, (int) $limit )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function find_orphans( $args = array() ) {
		global $wpdb;
		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$limit   = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 50;

		$placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
		$sql = "SELECT p.ID, p.post_title, p.post_type, p.post_modified
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->prefix}cc_link_graph g ON g.target_post_id = p.ID
			WHERE p.post_status = 'publish'
				AND p.post_type IN ($placeholders)
				AND g.id IS NULL
			ORDER BY p.post_modified DESC
			LIMIT %d";
		$args_in = array_merge( $allowed, array( $limit ) );
		$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $args_in ), ARRAY_A );

		// Pending changes targeting these orphans count as "soft" inbound links —
		// not in the live graph yet, but already queued for human approval. We
		// surface this on each row so callers stop double-linking the same
		// orphan from multiple source pages while a previous edit is still
		// awaiting review.
		$candidate_ids = array();
		foreach ( (array) $rows as $r ) {
			$candidate_ids[] = (int) $r['ID'];
		}
		$soft_inbound_by_target = self::pending_inbound_for_targets( $candidate_ids );

		// Drop rows that already have a queued inbound link unless the caller
		// explicitly opted in to seeing them via include_pending=true.
		$include_pending = ! empty( $args['include_pending'] );

		$out = array();
		foreach ( (array) $rows as $r ) {
			$tid       = (int) $r['ID'];
			$soft_info = isset( $soft_inbound_by_target[ $tid ] ) ? $soft_inbound_by_target[ $tid ] : null;
			if ( ! $include_pending && null !== $soft_info ) {
				continue;
			}
			$row = array(
				'post_id'    => $tid,
				'post_title' => $r['post_title'],
				'post_type'  => $r['post_type'],
				'modified'   => $r['post_modified'],
				'permalink'  => get_permalink( $tid ),
				'edit_url'   => get_edit_post_link( $tid, 'raw' ),
			);
			$lang = CC_Assistant_Multilingual::language_of( $tid );
			if ( null !== $lang ) {
				$row['lang'] = $lang;
			}
			if ( null !== $soft_info ) {
				$row['pending_inbound'] = $soft_info;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Scan the pending-changes table for queued edits whose proposed_value
	 * contains a link pointing at one of the given target post permalinks.
	 * Returns target_post_id => array( 'count' => N, 'pending_ids' => [...] ).
	 *
	 * Heuristic match (LIKE on the slug fragment of each target's permalink),
	 * not authoritative — but good enough to keep us from re-linking an orphan
	 * that has 5 queued inbounds waiting for approval.
	 */
	private static function pending_inbound_for_targets( $target_post_ids ) {
		$out = array();
		if ( empty( $target_post_ids ) ) {
			return $out;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';

		// Build a map: target_id => slug (the most stable URL fragment to LIKE on).
		$slugs = array();
		foreach ( $target_post_ids as $tid ) {
			$post = get_post( (int) $tid );
			if ( $post && $post->post_name ) {
				$slugs[ (int) $tid ] = $post->post_name;
			}
		}
		if ( empty( $slugs ) ) {
			return $out;
		}

		// Pull pending AND recently-applied text-bearing changes. The link
		// graph is cron-rebuilt, so a freshly applied inbound link won't
		// appear in cc_link_graph for up to 30 seconds (or up to a day in
		// the worst case). Counting recently-approved rows as "soft
		// inbounds" means find_orphans does not keep flagging a post that
		// just received link work approved minutes ago.
		$applied_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 14 * DAY_IN_SECONDS ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, post_id, change_type, proposed_value, status, reviewed_at
			 FROM {$table}
			 WHERE change_type IN ('elementor_widget_update','post_content_update','postmeta_update','meta_update')
			   AND superseded_by IS NULL
			   AND ( status = 'pending'
			         OR ( status = 'approved' AND reviewed_at >= %s ) )",
			$applied_cutoff
		), ARRAY_A );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return $out;
		}

		foreach ( $rows as $row ) {
			$payload = (string) $row['proposed_value'];
			if ( '' === $payload ) {
				continue;
			}
			foreach ( $slugs as $tid => $slug ) {
				// The slug must appear inside an href segment to count as a
				// genuine inbound link. Cheap substring check, then a tighter
				// regex to drop false positives where the slug appears as
				// plain text (e.g. inside a sentence).
				if ( false === strpos( $payload, '/' . $slug ) ) {
					continue;
				}
				if ( ! preg_match( '#href=(\\\\?["\'])[^"\']*?/' . preg_quote( $slug, '#' ) . '/?(?:[?#][^"\']*)?\\\\?\1#', $payload ) ) {
					continue;
				}
				if ( ! isset( $out[ $tid ] ) ) {
					$out[ $tid ] = array( 'count' => 0, 'pending_ids' => array(), 'applied_ids' => array() );
				}
				if ( 'approved' === $row['status'] ) {
					$out[ $tid ]['applied_ids'][] = (int) $row['id'];
				} else {
					$out[ $tid ]['pending_ids'][] = (int) $row['id'];
				}
				$out[ $tid ]['count']++;
			}
		}
		return $out;
	}

	/**
	 * Suggest internal links to ADD to a given post: scan other posts whose
	 * title (or short heading) appears as plain text in this post's body, and
	 * propose adding a link from that phrase to the matching post.
	 */
	public static function suggest_outbound( $post_id, $args = array() ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return array();
		}
		$body = (string) $post->post_content;
		$ele  = get_post_meta( $post->ID, '_elementor_data', true );
		if ( $ele ) {
			$body .= "\n" . self::flatten_elementor_text( $ele );
		}
		$body_lc = mb_strtolower( wp_strip_all_tags( $body ) );
		if ( '' === trim( $body_lc ) ) {
			return array();
		}

		// Already linked targets — skip.
		$existing = self::outbound_links( $post_id, 500 );
		$linked   = array();
		foreach ( $existing as $row ) {
			$linked[ (int) $row['post_id'] ] = true;
		}

		global $wpdb;
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				WHERE post_status='publish' AND post_type IN ($placeholders) AND ID <> %d
				LIMIT 2000",
				array_merge( $allowed, array( (int) $post_id ) )
			),
			ARRAY_A
		);

		$limit       = isset( $args['limit'] ) ? max( 1, min( self::SUGGEST_MAX_RESULTS, (int) $args['limit'] ) ) : 10;
		$out         = array();

		foreach ( $candidates as $cand ) {
			$tid = (int) $cand['ID'];
			if ( isset( $linked[ $tid ] ) ) {
				continue;
			}
			$title = trim( (string) $cand['post_title'] );
			if ( '' === $title || str_word_count( $title ) < self::SUGGEST_MIN_TITLE_WORDS ) {
				continue;
			}
			$needle = mb_strtolower( $title );
			$pos    = mb_strpos( $body_lc, $needle );
			if ( false === $pos ) {
				continue;
			}
			$context = self::context_excerpt( $body_lc, $pos, mb_strlen( $needle ) );
			$out[]   = array(
				'target_post_id' => $tid,
				'target_title'   => $title,
				'target_url'     => get_permalink( $tid ),
				'matched_phrase' => $title,
				'context'        => $context,
				'inbound_count'  => self::inbound_count( $tid ),
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Suggest pages that should link TO this post: pick posts whose body
	 * contains this post's title and aren't already linking. Inverse of
	 * suggest_outbound.
	 */
	public static function suggest_inbound( $post_id, $args = array() ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return array();
		}
		$title = trim( (string) $post->post_title );
		if ( '' === $title || str_word_count( $title ) < self::SUGGEST_MIN_TITLE_WORDS ) {
			return array();
		}
		$needle = mb_strtolower( $title );

		// Existing inbound — skip.
		$inbound = self::inbound_links( $post_id, 500 );
		$linked  = array();
		foreach ( $inbound as $row ) {
			$linked[ (int) $row['post_id'] ] = true;
		}

		global $wpdb;
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );

		// LIKE search for the title phrase.
		$like = '%' . $wpdb->esc_like( $title ) . '%';
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content FROM {$wpdb->posts}
				WHERE post_status='publish' AND post_type IN ($placeholders) AND ID <> %d
					AND post_content LIKE %s
				LIMIT 200",
				array_merge( $allowed, array( (int) $post_id, $like ) )
			),
			ARRAY_A
		);

		$limit = isset( $args['limit'] ) ? max( 1, min( self::SUGGEST_MAX_RESULTS, (int) $args['limit'] ) ) : 10;
		$out   = array();
		foreach ( $candidates as $cand ) {
			$sid = (int) $cand['ID'];
			if ( isset( $linked[ $sid ] ) ) {
				continue;
			}
			$body = mb_strtolower( wp_strip_all_tags( (string) $cand['post_content'] ) );
			$pos  = mb_strpos( $body, $needle );
			if ( false === $pos ) {
				continue;
			}
			$out[] = array(
				'source_post_id' => $sid,
				'source_title'   => $cand['post_title'],
				'source_url'     => get_permalink( $sid ),
				'matched_phrase' => $title,
				'context'        => self::context_excerpt( $body, $pos, mb_strlen( $needle ) ),
				'edit_url'       => get_edit_post_link( $sid, 'raw' ),
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	private static function context_excerpt( $haystack, $start, $needle_len ) {
		$around = 60;
		$from   = max( 0, $start - $around );
		$len    = $needle_len + 2 * $around;
		$ex     = mb_substr( $haystack, $from, $len );
		return ( $from > 0 ? '… ' : '' ) . $ex . ' …';
	}

	/**
	 * Combined audit for a single post: inbound, outbound, suggestions.
	 */
	public static function audit_post( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		return array(
			'post_id'         => (int) $post_id,
			'post_title'      => $post->post_title,
			'permalink'       => get_permalink( (int) $post_id ),
			'inbound_count'   => self::inbound_count( $post_id ),
			'outbound_count'  => self::outbound_count( $post_id ),
			'is_orphan'       => 0 === self::inbound_count( $post_id ),
			'inbound_links'   => self::inbound_links( $post_id, 50 ),
			'outbound_links'  => self::outbound_links( $post_id, 50 ),
			'suggest_outbound' => self::suggest_outbound( $post_id, array( 'limit' => 10 ) ),
			'suggest_inbound'  => self::suggest_inbound( $post_id, array( 'limit' => 10 ) ),
		);
	}

	/**
	 * Site-wide rollup: how is the link graph distributed?
	 */
	public static function summary() {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_link_graph';

		$total_edges = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$total_targets = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT target_post_id) FROM {$table}" );
		$total_sources = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT source_post_id) FROM {$table}" );

		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
		$published = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ($placeholders)",
				$allowed
			)
		);
		$orphans = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				LEFT JOIN {$table} g ON g.target_post_id = p.ID
				WHERE p.post_status='publish' AND p.post_type IN ($placeholders) AND g.id IS NULL",
				$allowed
			)
		);

		// Most-linked-to pages
		$hubs = $wpdb->get_results(
			"SELECT g.target_post_id AS post_id, p.post_title, COUNT(DISTINCT g.source_post_id) AS inbound
			FROM {$table} g
			INNER JOIN {$wpdb->posts} p ON p.ID = g.target_post_id
			GROUP BY g.target_post_id
			ORDER BY inbound DESC
			LIMIT 10",
			ARRAY_A
		);

		// Most-linking-out pages
		$emitters = $wpdb->get_results(
			"SELECT g.source_post_id AS post_id, p.post_title, COUNT(DISTINCT g.target_post_id) AS outbound
			FROM {$table} g
			INNER JOIN {$wpdb->posts} p ON p.ID = g.source_post_id
			GROUP BY g.source_post_id
			ORDER BY outbound DESC
			LIMIT 10",
			ARRAY_A
		);

		// Distinguish "graph never built" from "graph built and there are
		// genuinely 0 edges". Without this flag, a brand-new install shows
		// "100% orphans" and a critical-status badge — which is technically
		// true but misleading. The dashboard uses this to swap in a
		// "first sync pending" empty state instead.
		$graph_built = ( $total_edges > 0 ) || (bool) get_transient( 'cc_link_graph_last_run' );

		return array(
			'published_count'    => $published,
			'orphan_count'       => $orphans,
			'orphan_percent'     => $published > 0 ? round( $orphans * 100 / $published, 1 ) : 0.0,
			'edges'              => $total_edges,
			'pages_with_inbound' => $total_targets,
			'pages_with_outbound' => $total_sources,
			'top_hubs'           => is_array( $hubs ) ? $hubs : array(),
			'top_emitters'       => is_array( $emitters ) ? $emitters : array(),
			'graph_built'        => $graph_built,
		);
	}
}

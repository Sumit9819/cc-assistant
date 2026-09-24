<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO analysis helpers built on data the plugin already collects.
 *
 * Nothing in here calls an external service or runs heavy on-render compute.
 * Cannibalization, click depth, refresh queue, and post dossier are all
 * derived from cc_gsc_queries / cc_link_graph / cc_edits / cc_pending_changes
 * via grouped queries. Image and structure analysis read post_content + the
 * Elementor parser lazily on demand.
 */
class CC_Assistant_SEO_Tools {

	/* =====================================================================
	 * Cannibalization (same query, multiple URLs)
	 * Real cannibalization needs both pages to actually be competing — i.e.
	 * both ranking on page 1-2 for the query. Pages at position 50 vs 5 are
	 * not competing, so we filter by max position.
	 * ================================================================== */
	/**
	 * Strip tracking parameters (utm_*, fbclid, gclid, gbraid, wbraid,
	 * msclkid, _gl, mc_eid, mc_cid, ref, source) from a URL so that GSC
	 * cannibalization analysis does not treat `/` and
	 * `/?utm_source=google&utm_medium=gmb` as two competing pages.
	 *
	 * Google Search Console reports the Website-button URL on GBP listings
	 * with the GMB UTM tag, which is the exact same destination as the
	 * untagged homepage. Without this, the homepage shows up as a 600+
	 * impression cannibalization conflict against itself.
	 *
	 * Returns the URL with tracking params removed but other query params
	 * (e.g. ?p=123, ?lang=es) preserved. Also normalises a trailing slash
	 * on the path so `/about` and `/about/` agglomerate.
	 */
	public static function normalize_page_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return $url;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return $url;
		}
		$query_keep = array();
		if ( ! empty( $parts['query'] ) ) {
			$pairs = array();
			parse_str( (string) $parts['query'], $pairs );
			$tracking_keys = array(
				'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id',
				'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'dclid',
				'mc_eid', 'mc_cid', '_gl', 'yclid', 'twclid', 'igshid',
				'ref', 'source', 'srsltid',
			);
			foreach ( $pairs as $k => $v ) {
				if ( in_array( strtolower( (string) $k ), $tracking_keys, true ) ) {
					continue;
				}
				if ( 0 === strpos( strtolower( (string) $k ), 'utm_' ) ) {
					continue;
				}
				$query_keep[ $k ] = $v;
			}
		}
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host   = isset( $parts['host'] ) ? $parts['host'] : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '/';
		// Drop trailing slash unless path is just '/' so /about and /about/ collapse.
		if ( strlen( $path ) > 1 && '/' === substr( $path, -1 ) ) {
			$path = rtrim( $path, '/' );
		}
		$query  = ! empty( $query_keep ) ? '?' . http_build_query( $query_keep ) : '';
		return $scheme . $host . $path . $query;
	}

	public static function cannibalization( $args = array() ) {
		global $wpdb;
		$days        = isset( $args['days'] ) ? max( 1, min( 90, (int) $args['days'] ) ) : 28;
		$min_impr    = isset( $args['min_impressions'] ) ? max( 1, (int) $args['min_impressions'] ) : 25;
		$max_pos     = isset( $args['max_position'] ) ? max( 1, (int) $args['max_position'] ) : 30;
		$limit       = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$cutoff      = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		$gsc_table   = $wpdb->prefix . 'cc_gsc_queries';

		// Step 1: aggregate to (query, page) for the window.
		// Step 2: filter to queries where 2+ distinct pages appear AND both are within max_position.
		// One CTE-style query keeps it server-side.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT query,
			        page,
			        SUM(impressions) AS impressions,
			        SUM(clicks)      AS clicks,
			        SUM(position*impressions)/NULLIF(SUM(impressions),0) AS avg_position
			 FROM {$gsc_table}
			 WHERE date >= %s
			 GROUP BY query, page
			 HAVING avg_position <= %f AND impressions >= %d
			 ORDER BY query ASC, impressions DESC",
			$cutoff,
			(float) $max_pos,
			$min_impr
		) );

		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';

		// Re-aggregate raw GSC rows by NORMALIZED page URL so utm-tagged variants
		// (e.g. `/?utm_source=google&utm_medium=gmb` reported by GBP) collapse into
		// their canonical counterpart (`/`). Without this, the GMB Website-button
		// URL surfaces as a cannibalization conflict against the regular homepage.
		$by_query = array();
		foreach ( (array) $rows as $r ) {
			$q              = $r->query;
			$normalized_url = self::normalize_page_url( $r->page );
			if ( ! isset( $by_query[ $q ] ) ) {
				$by_query[ $q ] = array();
			}
			if ( ! isset( $by_query[ $q ][ $normalized_url ] ) ) {
				$by_query[ $q ][ $normalized_url ] = array(
					'page'           => $normalized_url,
					'impressions'    => 0,
					'clicks'         => 0,
					'position_sum'   => 0.0,
					'position_count' => 0,
				);
			}
			$by_query[ $q ][ $normalized_url ]['impressions']    += (int) $r->impressions;
			$by_query[ $q ][ $normalized_url ]['clicks']         += (int) $r->clicks;
			$by_query[ $q ][ $normalized_url ]['position_sum']   += (float) $r->avg_position * (int) $r->impressions;
			$by_query[ $q ][ $normalized_url ]['position_count'] += (int) $r->impressions;
		}
		// Flatten per-query maps into arrays of pages with finalised avg_position.
		foreach ( $by_query as $q => $page_map ) {
			$pages = array();
			foreach ( $page_map as $p ) {
				$avg = $p['position_count'] > 0
					? round( $p['position_sum'] / $p['position_count'], 2 )
					: 0.0;
				$pages[] = array(
					'page'         => $p['page'],
					'impressions'  => $p['impressions'],
					'clicks'       => $p['clicks'],
					'avg_position' => $avg,
				);
			}
			// Re-sort by impressions desc so the strongest page shows first per conflict.
			usort( $pages, function ( $a, $b ) {
				return $b['impressions'] - $a['impressions'];
			} );
			$by_query[ $q ] = $pages;
		}

		// Keep only queries with multiple pages competing.
		// On multilingual sites we annotate each page with its language and
		// flag conflicts where every competing page is in a different
		// language — those are usually benign (Google serving the right
		// language to each visitor) rather than real same-audience overlap.
		$conflicts = array();
		foreach ( $by_query as $query => $pages ) {
			if ( count( $pages ) < 2 ) {
				continue;
			}
			$total_impr = 0;
			$langs_seen = array();
			foreach ( $pages as $idx => $p ) {
				$total_impr += $p['impressions'];
				$page_id = CC_Assistant_URL_Resolver::to_post_id( $p['page'] );
				$lang    = $page_id > 0 ? CC_Assistant_Multilingual::language_of( $page_id ) : null;
				if ( null !== $lang ) {
					$pages[ $idx ]['lang'] = $lang;
					$langs_seen[ $lang ] = true;
				}
			}
			$conflict = array(
				'query'             => $query,
				'page_count'        => count( $pages ),
				'total_impressions' => $total_impr,
				'pages'             => $pages,
			);
			if ( count( $langs_seen ) > 1 && count( $langs_seen ) === count( $pages ) ) {
				$conflict['cross_language_only'] = true;
				$conflict['note']                = 'Each competing page is in a different language — likely benign (Google serves the right language per visitor).';
			}
			$conflicts[] = $conflict;
		}

		usort( $conflicts, function ( $a, $b ) {
			return $b['total_impressions'] - $a['total_impressions'];
		} );

		return array(
			'window_days' => $days,
			'count'       => count( $conflicts ),
			'conflicts'   => array_slice( $conflicts, 0, $limit ),
            'assessment_type' => 'query_page_overlap_candidates',
            'harm_established' => false,
            'interpretation' => 'Observed URLs across a reporting window, not proof of simultaneous competition or lost traffic. Compare reader tasks, language and outcomes before acting.',
		);
	}

	/* =====================================================================
	 * Image SEO audit
	 * Pure HTML parsing — no HEAD requests for file size unless asked.
	 * ================================================================== */
	public static function audit_post_images( $post_id, $args = array() ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$html = (string) $post->post_content;

		// If Elementor data is present, also walk widget editor fields. The
		// Elementor parser already extracts widget HTML so we reuse it.
		if ( get_post_meta( $post_id, '_elementor_data', true ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';
			$parsed = CC_Assistant_Elementor_Parser::parse( (int) $post_id, 'full' );
			if ( ! empty( $parsed['widgets'] ) ) {
				foreach ( $parsed['widgets'] as $w ) {
					if ( isset( $w['settings']['editor'] ) ) {
						$html .= "\n" . (string) $w['settings']['editor'];
					}
					if ( isset( $w['settings']['image']['url'] ) ) {
						// Elementor image widgets store alt in TWO places. The
						// media-library default lives in settings.image.alt; an
						// operator-set custom alt overrides via settings.alt.
						// v0.16 audit only checked image.alt, which produced
						// false "empty_alt" findings on widgets that had a
						// custom override (the hormone page 28c3f12 Personalized-
						// Care image). v0.17 prefers settings.alt when present,
						// falling back to settings.image.alt to preserve the old
						// behavior for widgets without an override.
						$widget_alt   = isset( $w['settings']['alt'] ) ? trim( (string) $w['settings']['alt'] ) : '';
						$attachment_alt = isset( $w['settings']['image']['alt'] ) ? trim( (string) $w['settings']['image']['alt'] ) : '';
						$alt          = '' !== $widget_alt ? $widget_alt : $attachment_alt;
						$html .= sprintf( '<img src="%s" alt="%s">', esc_url( $w['settings']['image']['url'] ), esc_attr( $alt ) );
					}
				}
			}
		}

		$findings = array();
		$total    = 0;
		if ( preg_match_all( '/<img\b([^>]*)>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $attrs ) {
				$total++;
				$src = '';
				$alt = null;
				if ( preg_match( '/\bsrc\s*=\s*([\'"])(.*?)\1/i', $attrs, $m ) ) {
					$src = $m[2];
				}
				if ( preg_match( '/\balt\s*=\s*([\'"])(.*?)\1/i', $attrs, $m ) ) {
					$alt = $m[2];
				}

				$issues = array();
				if ( null === $alt ) {
					$issues[] = 'missing_alt';
				} elseif ( '' === trim( $alt ) ) {
					$issues[] = 'empty_alt';
				} else {
					$len = mb_strlen( $alt );
					if ( $len < 5 ) {
						$issues[] = 'alt_too_short';
					}
					if ( $len > 125 ) {
						$issues[] = 'alt_too_long';
					}
					// Filename-like alt ("image.jpg", "IMG_1234"): a strong tell of auto-generated.
					if ( preg_match( '/^(img[_\-]?\d+|image\d*|dscn?\d+|screenshot)$/i', trim( str_replace( array( '.jpg', '.jpeg', '.png', '.webp', '.gif' ), '', $alt ) ) ) ) {
						$issues[] = 'alt_looks_like_filename';
					}
				}

				// Filename hints for SEO: stuffed underscores, random hashes, no description.
				if ( $src ) {
					$file = basename( wp_parse_url( $src, PHP_URL_PATH ) ?: '' );
					$base = preg_replace( '/\.[a-z0-9]+$/i', '', $file );
					if ( $base && preg_match( '/^[a-f0-9]{16,}$/i', $base ) ) {
						$issues[] = 'filename_is_hash';
					} elseif ( $base && preg_match( '/^(img|image|dscn?|screenshot|untitled)[_\-]?\d*$/i', $base ) ) {
						$issues[] = 'filename_is_generic';
					}
				}

				if ( ! empty( $issues ) ) {
					$findings[] = array(
						'src'    => $src,
						'alt'    => $alt,
						'issues' => $issues,
					);
				}
			}
		}

		return array(
			'post_id'        => (int) $post_id,
			'images_total'   => $total,
			'images_with_issues' => count( $findings ),
			'findings'       => $findings,
		);
	}

	/* =====================================================================
	 * Refresh queue: decaying pages ranked by impact + freshness.
	 * Combines cc_gsc_queries trend signals with post_modified to rank
	 * candidates worth refreshing.
	 * ================================================================== */
	public static function refresh_queue( $args = array() ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		$days        = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;
		$min_drop    = isset( $args['min_click_drop'] ) ? max( 1, (int) $args['min_click_drop'] ) : 5;
		$min_age     = isset( $args['min_age_days'] ) ? max( 0, (int) $args['min_age_days'] ) : 90;
		$limit       = isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 25;

		// Suppression set: posts touched by an applied edit in the last 14 days
		// are "fresh enough" — we should not be recommending another refresh
		// while we wait for outcome data. Override via include_recent_edited.
		$include_recent = ! empty( $args['include_recent_edited'] );
		$recent_set     = ( ! $include_recent )
			? array_flip( CC_Assistant_Edit_Outcomes::recent_post_ids( 14 ) )
			: array();
		$filtered_count = 0;

		// trends_summary is heavy on big GSC tables — cache its output for the
		// current window so this tool can be called repeatedly during a session
		// without re-aggregating from scratch every time.
		$trend_cache_key = 'cc_seo_tools_trends_' . $days;
		$trends = get_transient( $trend_cache_key );
		if ( false === $trends ) {
			$trends = CC_Assistant_GSC::trends_summary( $days );
			set_transient( $trend_cache_key, $trends, 30 * MINUTE_IN_SECONDS );
		}
		$decayed = isset( $trends['decayed'] ) ? $trends['decayed'] : array();

		$now_ts = time();
		$queue  = array();
		foreach ( $decayed as $row ) {
			$drop = abs( (int) ( $row['click_delta'] ?? 0 ) );
			if ( $drop < $min_drop ) {
				continue;
			}
			$page  = $row['page'] ?? '';
			// Resolver, not url_to_postid(): a redirected URL used to resolve
			// to 0, which defeated the "skip recently touched" guard below and
			// let the same consolidated URL be recommended for refresh forever.
			$post  = $page ? CC_Assistant_URL_Resolver::to_post_id( $page ) : 0;
			$post  = (int) $post;

			// Skip posts we just touched (any cc_edits row in last 14 days).
			// They had their shot; let outcome scoring measure whether the
			// edit worked before we keep recommending more refresh churn.
			if ( $post && isset( $recent_set[ $post ] ) ) {
				$filtered_count++;
				continue;
			}

			$post_modified = $post ? get_post_field( 'post_modified_gmt', $post ) : '';
			$age_days = $post_modified ? max( 0, (int) ( ( $now_ts - strtotime( $post_modified . ' UTC' ) ) / DAY_IN_SECONDS ) ) : null;

			if ( null !== $age_days && $age_days < $min_age ) {
				continue;
			}

			// Priority: weighted combination of click drop and staleness.
			$priority = $drop * 1.0 + ( $age_days ?? 0 ) * 0.05;

			$queue[] = array(
				'page'           => $page,
				'post_id'        => $post ?: null,
				'title'          => $post ? get_the_title( $post ) : '',
				'click_delta'    => -$drop, // signed: negative = decline
				'age_days'       => $age_days,
				'last_modified'  => $post_modified ?: null,
				'priority_score' => round( $priority, 2 ),
				'edit_url'       => $post ? get_edit_post_link( $post, 'raw' ) : null,
			);
		}

		usort( $queue, function ( $a, $b ) {
			return ( $b['priority_score'] <=> $a['priority_score'] );
		} );

		return array(
			'window_days'      => $days,
			'count'            => count( $queue ),
			'queue'            => array_slice( $queue, 0, $limit ),
			'recent_filtered'  => $filtered_count,
		);
	}

	/* =====================================================================
	 * Click depth: BFS from page_on_front through wp_cc_link_graph.
	 * Returns depth per post + a list of "buried" pages (depth >= 4 or
	 * unreachable).
	 * ================================================================== */
	public static function click_depth( $args = array() ) {
		global $wpdb;
		$max_depth_warn = isset( $args['max_depth_warn'] ) ? max( 1, (int) $args['max_depth_warn'] ) : 4;
		$buried_only    = ! empty( $args['buried_only'] );

		// Recently-edited posts may have just had inbound links applied that
		// the link graph cron has not rebuilt yet. Treating them as buried
		// would tell the operator to add MORE inbound links to a post that
		// just received some — exactly the cellulitis-class stale signal we
		// fixed across the GSC tools. Override via include_recent_edited.
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		$include_recent     = ! empty( $args['include_recent_edited'] );
		$recent_excluded    = $include_recent ? array() : array_flip( CC_Assistant_Edit_Outcomes::recent_post_ids( 14 ) );
		$recent_filtered    = 0;

		// Roots: page_on_front (if static), plus posts page if separate. If neither,
		// fall back to using the most-linked-to page as root (fragile but better than nothing).
		$roots = array();
		$front = (int) get_option( 'page_on_front' );
		if ( $front ) {
			$roots[] = $front;
		}
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page && $posts_page !== $front ) {
			$roots[] = $posts_page;
		}
		if ( empty( $roots ) ) {
			// Pick the post with the most inbound links as the assumed root.
			$candidate = (int) $wpdb->get_var(
				"SELECT target_post_id FROM {$wpdb->prefix}cc_link_graph
				 GROUP BY target_post_id ORDER BY COUNT(*) DESC LIMIT 1"
			);
			if ( $candidate ) {
				$roots[] = $candidate;
			}
		}

		// Build adjacency once.
		$edges = $wpdb->get_results( "SELECT source_post_id, target_post_id FROM {$wpdb->prefix}cc_link_graph" );
		$adj   = array();
		foreach ( (array) $edges as $e ) {
			$adj[ (int) $e->source_post_id ][] = (int) $e->target_post_id;
		}

		$depth   = array();
		$queue   = array();
		foreach ( $roots as $r ) {
			$depth[ $r ] = 0;
			$queue[] = $r;
		}
		while ( ! empty( $queue ) ) {
			$id = array_shift( $queue );
			$d  = $depth[ $id ];
			foreach ( ( $adj[ $id ] ?? array() ) as $tgt ) {
				if ( ! isset( $depth[ $tgt ] ) ) {
					$depth[ $tgt ] = $d + 1;
					$queue[] = $tgt;
				}
			}
		}

		// Build the report. Include all published posts in allowed types so
		// unreachable ones surface as buried with depth = null.
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN (" . implode( ',', array_fill( 0, count( $allowed ), '%s' ) ) . ')',
			$allowed
		) );

		_prime_post_caches( array_map( 'intval', $post_ids ), false, false );

		$rows = array();
		$histogram = array();
		foreach ( $post_ids as $pid ) {
			$pid = (int) $pid;
			$d   = isset( $depth[ $pid ] ) ? (int) $depth[ $pid ] : null;
			$key = null === $d ? 'unreachable' : (string) $d;
			$histogram[ $key ] = ( $histogram[ $key ] ?? 0 ) + 1;

			$is_buried = ( null === $d ) || ( $d >= $max_depth_warn );
			if ( $buried_only && ! $is_buried ) {
				continue;
			}
			// Suppress recently-edited posts from buried recommendations only.
			// Histogram stats above still include them — we want the global
			// depth distribution to reflect reality, just not the actionable
			// recommendation list.
			if ( $buried_only && isset( $recent_excluded[ $pid ] ) ) {
				$recent_filtered++;
				continue;
			}
			$rows[] = array(
				'post_id'   => $pid,
				'title'     => get_the_title( $pid ),
				'depth'     => $d,
				'buried'    => $is_buried,
				'edit_url'  => get_edit_post_link( $pid, 'raw' ),
			);
		}

		usort( $rows, function ( $a, $b ) {
			$ad = is_null( $a['depth'] ) ? PHP_INT_MAX : $a['depth'];
			$bd = is_null( $b['depth'] ) ? PHP_INT_MAX : $b['depth'];
			return $bd - $ad;
		} );

		return array(
			'roots'           => $roots,
			'max_depth_warn'  => $max_depth_warn,
			'histogram'       => $histogram,
			'pages'           => $rows,
			'recent_filtered' => $recent_filtered,
		);
	}

	/* =====================================================================
	 * Post dossier: everything we know about one post in a single response.
	 * Joins post + edits + pending + GSC top queries + cluster memberships
	 * + internal links in/out. Reduces N round-trips Claude would otherwise make.
	 * ================================================================== */
	public static function post_dossier( $post_id ) {
		global $wpdb;
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';

		// Pending + history for this post.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, change_type, change_summary, status, created_at, reviewed_at
			 FROM {$wpdb->prefix}cc_pending_changes
			 WHERE post_id = %d
			 ORDER BY COALESCE(reviewed_at, created_at) DESC
			 LIMIT 20",
			$post_id
		) );
		$history = array();
		foreach ( (array) $rows as $r ) {
			$history[] = array(
				'id'             => (int) $r->id,
				'change_type'    => $r->change_type,
				'change_summary' => $r->change_summary,
				'status'         => $r->status,
				'created_at'     => $r->created_at,
				'reviewed_at'    => $r->reviewed_at,
			);
		}

		// Cluster memberships (a post can be in many).
		$clusters = CC_Assistant_Topic_Clusters::clusters_for_post( (int) $post_id );
		$clusters_brief = array();
		foreach ( $clusters as $c ) {
			$clusters_brief[] = array(
				'id'   => (int) $c->id,
				'name' => $c->name,
				'role' => $c->role,
			);
		}

		// Internal links in/out.
		$inbound = $wpdb->get_results( $wpdb->prepare(
			"SELECT source_post_id, anchor_text FROM {$wpdb->prefix}cc_link_graph WHERE target_post_id = %d LIMIT 50",
			$post_id
		) );
		$outbound = $wpdb->get_results( $wpdb->prepare(
			"SELECT target_post_id, anchor_text FROM {$wpdb->prefix}cc_link_graph WHERE source_post_id = %d LIMIT 50",
			$post_id
		) );

		// GSC top queries for this URL. Use page_hash (indexed sha1) instead of
		// the raw URL string — there's no index on the page column and a string
		// LIKE on millions of rows would time out. The hash is what GSC stores.
		$top_queries = array();
		$permalink   = get_permalink( $post_id );
		if ( $permalink ) {
			$cutoff    = gmdate( 'Y-m-d', time() - 28 * DAY_IN_SECONDS );
			$page_hash = sha1( $permalink );
			$rows      = $wpdb->get_results( $wpdb->prepare(
				"SELECT query,
				        SUM(impressions) AS impressions,
				        SUM(clicks)      AS clicks,
				        SUM(position*impressions)/NULLIF(SUM(impressions),0) AS position
				 FROM {$wpdb->prefix}cc_gsc_queries
				 WHERE date >= %s AND page_hash = %s
				 GROUP BY query
				 ORDER BY impressions DESC
				 LIMIT 10",
				$cutoff,
				$page_hash
			) );
			foreach ( (array) $rows as $r ) {
				$top_queries[] = array(
					'query'        => $r->query,
					'impressions'  => (int) $r->impressions,
					'clicks'       => (int) $r->clicks,
					'avg_position' => round( (float) $r->position, 2 ),
				);
			}
		}

		// CTR-vs-position diagnostic: for each tracked query, compare observed
		// CTR to the expected CTR for its position from a standard curve.
		// Surfaces title/meta-description rewrite opportunities that body
		// rewrites alone won't fix. ratio < 0.5 => actual is less than half
		// the expected for that rank slot, almost always a meta-tag problem.
		$ctr_diagnostics = self::ctr_diagnostic( $top_queries );

		// Post-drift: did anyone edit this post outside the plugin since the
		// last applied cc_edits row? If yes, the dossier above might be
		// referencing stale word counts / link counts / structure.
		require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		$last_applied = CC_Assistant_Edit_Outcomes::last_applied_at_for_post( (int) $post_id );
		$drift = null;
		if ( $last_applied ) {
			$post_modified = (string) $post->post_modified_gmt;
			$drift_seconds = strtotime( $post_modified ) - strtotime( $last_applied );
			if ( $drift_seconds > 120 ) {
				$drift = array(
					'has_drift'         => true,
					'last_applied_at'   => $last_applied,
					'post_modified_gmt' => $post_modified,
					'drift_seconds'     => (int) $drift_seconds,
					'message'           => sprintf(
						'This post was modified outside the plugin %d seconds after the last applied edit. Re-read get_post before proposing further changes — the cached dossier may be stale.',
						(int) $drift_seconds
					),
				);
			}
		}

		return array(
			'post'             => array(
				'id'             => (int) $post_id,
				'title'          => get_the_title( $post_id ),
				'status'         => $post->post_status,
				'type'           => $post->post_type,
				'word_count'     => str_word_count( wp_strip_all_tags( $post->post_content ) ),
				'last_modified'  => $post->post_modified_gmt,
				'permalink'      => get_permalink( $post_id ),
				'edit_url'       => get_edit_post_link( $post_id, 'raw' ),
			),
			'clusters'         => $clusters_brief,
			'history'          => $history,
			'inbound_links'    => array_values( array_map( function ( $r ) {
				return array(
					'source_post_id' => (int) $r->source_post_id,
					'source_title'   => get_the_title( (int) $r->source_post_id ),
					'anchor_text'    => $r->anchor_text,
				);
			}, (array) $inbound ) ),
			'outbound_links'   => array_values( array_map( function ( $r ) {
				return array(
					'target_post_id' => (int) $r->target_post_id,
					'target_title'   => get_the_title( (int) $r->target_post_id ),
					'anchor_text'    => $r->anchor_text,
				);
			}, (array) $outbound ) ),
			'top_gsc_queries'  => $top_queries,
			'ctr_diagnostics'  => $ctr_diagnostics,
			'drift'            => $drift,
		);
	}

	/**
	 * For a list of {query, impressions, clicks, avg_position} rows, compute
	 * expected CTR for the rank position, flag queries where actual is below
	 * 50% of expected, and return a top-line verdict with the worst offenders.
	 *
	 * The expected-CTR curve is a published industry baseline (Advanced Web
	 * Ranking 2024 averages, smoothed). It is approximate but stable enough
	 * to flag cases where the page is ranking but the snippet is not earning
	 * its clicks — i.e. a title/meta description problem, not a content depth
	 * problem.
	 */
	public static function ctr_diagnostic( $rows ) {
		// Position-bucketed expected CTR. Index = floor(position).
		$expected = array(
			1  => 0.395,
			2  => 0.187,
			3  => 0.100,
			4  => 0.075,
			5  => 0.057,
			6  => 0.045,
			7  => 0.035,
			8  => 0.029,
			9  => 0.025,
			10 => 0.022,
			11 => 0.018,
			12 => 0.014,
			13 => 0.012,
			14 => 0.010,
			15 => 0.008,
		);
		$queries  = array();
		$flagged  = array();
		foreach ( (array) $rows as $r ) {
			$impr = isset( $r['impressions'] ) ? (int) $r['impressions'] : 0;
			$pos  = isset( $r['avg_position'] ) ? (float) $r['avg_position'] : 0.0;
			if ( $impr < 30 || $pos < 1 || $pos > 20 ) {
				continue;
			}
			$bucket   = max( 1, min( 15, (int) floor( $pos ) ) );
			$exp_ctr  = isset( $expected[ $bucket ] ) ? $expected[ $bucket ] : 0.005;
			$exp_clks = $exp_ctr * $impr;
			$act_clks = isset( $r['clicks'] ) ? (int) $r['clicks'] : 0;
			$act_ctr  = $impr > 0 ? $act_clks / $impr : 0.0;
			$ratio    = $exp_ctr > 0 ? $act_ctr / $exp_ctr : 0.0;
			$row = array(
				'query'           => isset( $r['query'] ) ? (string) $r['query'] : '',
				'impressions'     => $impr,
				'clicks'          => $act_clks,
				'avg_position'    => $pos,
				'actual_ctr'      => round( $act_ctr, 4 ),
				'expected_ctr'    => round( $exp_ctr, 4 ),
				'expected_clicks' => round( $exp_clks, 1 ),
				'ratio'           => round( $ratio, 2 ),
				'is_underperforming' => $ratio < 0.5,
			);
			$queries[] = $row;
			if ( $row['is_underperforming'] ) {
				$flagged[] = $row;
			}
		}
		// Order flagged by missed-clicks-per-day so the worst show up first.
		usort( $flagged, function ( $a, $b ) {
			$gap_a = $a['expected_clicks'] - $a['clicks'];
			$gap_b = $b['expected_clicks'] - $b['clicks'];
			return $gap_b <=> $gap_a;
		} );
		$verdict = '';
		if ( ! empty( $flagged ) ) {
			$top = $flagged[0];
			$verdict = sprintf(
				'%d of %d tracked queries earn under half their expected CTR. Worst: "%s" at pos %.1f gets %.2f%% CTR vs expected %.2f%%. This is almost always a meta title/description problem, not a body content problem. Consider draft_update_seo_meta in addition to any body rewrite.',
				count( $flagged ),
				count( $queries ),
				$top['query'],
				$top['avg_position'],
				$top['actual_ctr'] * 100,
				$top['expected_ctr'] * 100
			);
		}
		return array(
			'queries'           => $queries,
			'underperforming'   => $flagged,
			'underperform_count' => count( $flagged ),
			'verdict'           => $verdict,
		);
	}

	/**
	 * Pre-flight bundle for any body rewrite. Single MCP call returns everything
	 * the model should have read before writing HTML: dossier + structure +
	 * brief_for_keyword on the top GSC query + competitor_brief + cannibalization
	 * filtered to this post's queries + accessibility audit + style guide + a
	 * CTR diagnostic + a mandatory checklist.
	 *
	 * Designed so the model literally cannot skip a step. Wraps existing tools
	 * rather than duplicating their logic. Slow path (multiple DB hits +
	 * potential WebFetch by the model) so call once per rewrite, not in a loop.
	 */
	public static function prepare_rewrite_brief( $post_id, $opts = array() ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}

		$dossier      = self::post_dossier( $post_id );
		$structure    = self::analyze_post_structure( $post_id );
		$accessibility = self::accessibility_audit( $post_id );
		$image_audit  = self::audit_post_images( $post_id );

		// Pick the top-impression query as the primary keyword if not provided.
		$keyword = isset( $opts['keyword'] ) ? trim( (string) $opts['keyword'] ) : '';
		if ( '' === $keyword && is_array( $dossier ) && ! empty( $dossier['top_gsc_queries'] ) ) {
			$keyword = $dossier['top_gsc_queries'][0]['query'];
		}

		$brief = null;
		$comp  = null;
		if ( '' !== $keyword ) {
			$brief = self::brief_for_keyword( array( 'keyword' => $keyword ) );
			$comp  = self::competitor_brief( array( 'keyword' => $keyword ) );
			if ( is_wp_error( $brief ) ) {
				$brief = array( 'error' => $brief->get_error_message() );
			}
			if ( is_wp_error( $comp ) ) {
				$comp = array( 'error' => $comp->get_error_message() );
			}
		}

		// Cannibalization filtered to queries this post ranks for.
		$post_queries = array();
		if ( is_array( $dossier ) && ! empty( $dossier['top_gsc_queries'] ) ) {
			foreach ( $dossier['top_gsc_queries'] as $q ) {
				$post_queries[ mb_strtolower( $q['query'] ) ] = true;
			}
		}
		$cannib_full = self::cannibalization( array( 'days' => 28, 'limit' => 200 ) );
		$cannib_for_post = array();
		if ( is_array( $cannib_full ) && ! empty( $cannib_full['rows'] ) ) {
			foreach ( $cannib_full['rows'] as $c ) {
				$key = isset( $c['query'] ) ? mb_strtolower( $c['query'] ) : '';
				if ( $key && isset( $post_queries[ $key ] ) ) {
					$cannib_for_post[] = $c;
				}
			}
		}

		// Style guide as both raw markdown (for the model to read verbatim) and
		// the parsed banned-phrase list for quick reference.
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$style_blob = (string) get_option( 'cc_assistant_style_guide', '' );
		if ( '' === $style_blob ) {
			// Mirror the default in class-rest-api.php so this tool always
			// has something concrete to return.
			$style_blob = "# Default style guide\n\n## Voice\n- Natural, human, conversational. Not robotic.\n- Short paragraphs (2 to 3 sentences max).\n- Active voice over passive.\n";
		}
		$banned = CC_Assistant_Pre_Publish::style_guide_banned_phrases();

		// Pull last 5 approved/applied edits on this post for context (so the
		// model knows what already shipped and doesn't duplicate work).
		global $wpdb;
		$recent_edits = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, change_type, change_summary, status, created_at, reviewed_at
			 FROM {$wpdb->prefix}cc_pending_changes
			 WHERE post_id = %d AND status IN ('approved','pending')
			 ORDER BY COALESCE(reviewed_at, created_at) DESC
			 LIMIT 5",
			$post_id
		) );

		// Mandatory rewrite checklist. Returned verbatim so the model has it
		// in its working context after one tool call. The plugin can't force
		// the model to follow it, but server-side lint at queue time enforces
		// the items that translate to objective checks (paragraph length,
		// banned phrases, em dashes, redundancy, bulk-add ratio).
		$checklist = array(
			'1. Read the dossier and ctr_diagnostics. If any query is underperforming CTR for its position, propose a draft_update_seo_meta change BEFORE or alongside the body rewrite. Body rewrites do not fix meta-title problems.',
			'2. Read top_gsc_queries. The body rewrite must clearly answer each high-impression query the page already ranks for. Do not expand into adjacent topics that risk cannibalising other posts (see cannibalization_for_post below).',
			'3. Read structure.intent_fingerprint. Match the rewrite shape (comparison / how-to / faq / listicle) to what already wins in the SERP.',
			'4. Read brief.suggested_word_count and competitor_brief. If the SERP top 3 average 1800 words and your post is 1300, target ~1800. Do not just append a section — outline the new structure first.',
			'5. Plan deletions, not just additions. Real edits cut as they expand. Identify at least one paragraph or section that can be merged or removed before writing new copy.',
			'6. Verify the style guide banned-phrase list and AI-tell list are not violated in the new copy. Em dashes, "delve", "leverage", "navigate", "unlock" are blocked at the queue endpoint.',
			'7. Keep paragraphs ≤ 3 sentences and individual sentences ≤ 25 words. The queue endpoint will lint and flag paragraphs that exceed.',
			'8. Strip any <span style="font-weight: 400"> wrappers and other classic-editor cruft from new sections — do not propagate them just to "match the existing style".',
			'9. Add internal links to topical neighbour posts (use links_orphans + dossier.outbound_links to find candidates). Anchor text should be contextual, not exact-match keyword.',
			'10. After draft_update_post_content returns, the response includes a lint summary. If anything failed, fix and resubmit; do not log success on a failed lint.',
			'11. Set success_metrics on the draft_update_post_content call: target_query, target_position, target_ctr, eval_window_days. The plugin scores the change against these later via get_edit_outcome.',
		);

		return array(
			'post_id'                 => $post_id,
			'primary_keyword'         => $keyword,
			'dossier'                 => is_wp_error( $dossier ) ? array( 'error' => $dossier->get_error_message() ) : $dossier,
			'structure'               => is_wp_error( $structure ) ? array( 'error' => $structure->get_error_message() ) : $structure,
			'brief_for_primary'       => $brief,
			'competitor_brief'        => $comp,
			'cannibalization_for_post' => array_slice( $cannib_for_post, 0, 10 ),
			'accessibility'           => is_wp_error( $accessibility ) ? array( 'error' => $accessibility->get_error_message() ) : $accessibility,
			'image_audit'             => is_wp_error( $image_audit ) ? array( 'error' => $image_audit->get_error_message() ) : $image_audit,
			'style_guide'             => array(
				'content'         => $style_blob,
				'banned_phrases'  => $banned,
				'has_custom'      => '' !== get_option( 'cc_assistant_style_guide', '' ),
			),
			'recent_pending_or_approved' => array_map( function ( $r ) {
				return array(
					'id'             => (int) $r->id,
					'change_type'    => $r->change_type,
					'change_summary' => $r->change_summary,
					'status'         => $r->status,
					'created_at'     => $r->created_at,
				);
			}, (array) $recent_edits ),
			'checklist'               => $checklist,
			'next_step'               => 'After reading every section above (especially ctr_diagnostics and the checklist), propose an OUTLINE first to the human — H2 by H2 with merge/cut/keep markers — and only call draft_update_post_content once they approve the outline.',
		);
	}

	/* =====================================================================
	 * Post structure analyzer (for search-intent matching).
	 * Returns format-fingerprint signals Claude can compare to SERP intent.
	 * ================================================================== */
	public static function analyze_post_structure( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$html = (string) $post->post_content;
		if ( get_post_meta( $post_id, '_elementor_data', true ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';
			$parsed = CC_Assistant_Elementor_Parser::parse( (int) $post_id, 'full' );
			if ( ! empty( $parsed['widgets'] ) ) {
				foreach ( $parsed['widgets'] as $w ) {
					if ( isset( $w['settings']['editor'] ) ) {
						$html .= "\n" . (string) $w['settings']['editor'];
					}
				}
			}
		}

		$text = wp_strip_all_tags( $html );
		$words = str_word_count( $text );

		// Heading patterns.
		preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $hm, PREG_SET_ORDER );
		$h2s = array();
		$question_h2s = 0;
		foreach ( $hm as $m ) {
			if ( '2' === $m[1] ) {
				$txt = trim( wp_strip_all_tags( $m[2] ) );
				$h2s[] = $txt;
				if ( preg_match( '/^(what|why|how|when|where|who|which|is|are|can|does|do|should|will)\b/i', $txt ) || substr( $txt, -1 ) === '?' ) {
					$question_h2s++;
				}
			}
		}

		// Intro paragraph (text before first H2).
		$intro_words = 0;
		if ( preg_match( '/^(.*?)<h2/is', $html, $m ) ) {
			$intro_words = str_word_count( wp_strip_all_tags( $m[1] ) );
		}

		// FAQ-shaped content (Question? followed by short answer).
		$has_faq_shape = (bool) preg_match( '/<h[2-4][^>]*>[^<]*\?[^<]*<\/h[2-4]>/i', $html );

		// List + table presence.
		$list_count  = preg_match_all( '/<(ul|ol)\b/i', $html );
		$table_count = preg_match_all( '/<table\b/i', $html );

		// Step-shaped content ("Step 1", "1.", numbered headings).
		$has_steps = (bool) preg_match( '/(?:<h[2-4][^>]*>\s*step\s*\d+|<li>\s*<strong>step\s*\d+)/i', $html );

		// CTA detection (button-like classes/text).
		$cta_count = preg_match_all( '/<a\b[^>]*class\s*=\s*["\'][^"\']*\b(?:button|btn|cta)\b/i', $html );
		$cta_count += preg_match_all( '/<button\b/i', $html );

		// Schema markup hints.
		$has_schema_jsonld = (bool) preg_match( '/<script[^>]*type\s*=\s*["\']application\/ld\+json/i', $html );

		// Word-count distribution per H2 section (proxy for "thin" sections).
		$sections = preg_split( '/<h2\b[^>]*>/i', $html );
		array_shift( $sections ); // drop pre-first-H2 part
		$section_words = array();
		foreach ( $sections as $sec ) {
			$section_words[] = str_word_count( wp_strip_all_tags( $sec ) );
		}

		// Likely intent fingerprint based on the structural signals.
		$fingerprint = array();
		if ( $has_steps || preg_match( '/^how\b/i', get_the_title( $post_id ) ) ) {
			$fingerprint[] = 'how-to';
		}
		if ( $question_h2s >= 3 || $has_faq_shape ) {
			$fingerprint[] = 'faq';
		}
		if ( $list_count > 0 && $words > 800 && $list_count >= 3 ) {
			$fingerprint[] = 'listicle';
		}
		if ( $table_count > 0 ) {
			$fingerprint[] = 'comparison';
		}
		if ( preg_match( '/^(what|who|why)\b/i', get_the_title( $post_id ) ) && $intro_words > 40 && $intro_words < 120 ) {
			$fingerprint[] = 'definitional';
		}
		if ( $cta_count >= 2 || preg_match( '/(buy|book|schedule|contact|order|sign up|get a quote)/i', $text ) ) {
			$fingerprint[] = 'commercial';
		}

		return array(
			'post_id'         => (int) $post_id,
			'word_count'      => $words,
			'intro_word_count' => $intro_words,
			'h2_total'        => count( $h2s ),
			'h2_questions'    => $question_h2s,
			'has_faq_shape'   => $has_faq_shape,
			'list_count'      => (int) $list_count,
			'table_count'     => (int) $table_count,
			'has_steps'       => $has_steps,
			'cta_count'       => (int) $cta_count,
			'has_schema_jsonld' => $has_schema_jsonld,
			'section_word_counts' => $section_words,
			'intent_fingerprint'  => $fingerprint,
			'h2_titles'       => $h2s,
		);
	}

	/* =====================================================================
	 * Competitor brief: surfaces what we know about a target keyword
	 * relative to the user-configured competitor domains. Pure data;
	 * Claude does the actual SERP fetching with WebFetch.
	 * ================================================================== */
	public static function competitor_brief( $args = array() ) {
		global $wpdb;
		$keyword = trim( (string) ( $args['keyword'] ?? '' ) );
		if ( '' === $keyword ) {
			return new WP_Error( 'keyword_required', 'keyword is required.' );
		}
		$competitors = (array) get_option( 'cc_assistant_competitor_domains', array() );

		$days  = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;
		$cutoff = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );

		// Our own pages that already rank for this query. Filter on query_hash
		// (sha1 of lowercased query — what GSC stores) to hit the date_query index.
		$gsc_table = $wpdb->prefix . 'cc_gsc_queries';
		$query_hash = sha1( mb_strtolower( $keyword ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT page,
			        SUM(impressions) AS impressions,
			        SUM(clicks)      AS clicks,
			        SUM(position*impressions)/NULLIF(SUM(impressions),0) AS position
			 FROM {$gsc_table}
			 WHERE date >= %s AND query_hash = %s
			 GROUP BY page
			 ORDER BY impressions DESC
			 LIMIT 5",
			$cutoff,
			$query_hash
		) );

		$our_pages = array();
		foreach ( (array) $rows as $r ) {
			$our_pages[] = array(
				'page'         => $r->page,
				'impressions'  => (int) $r->impressions,
				'clicks'       => (int) $r->clicks,
				'avg_position' => round( (float) $r->position, 2 ),
			);
		}

		return array(
			'keyword'           => $keyword,
			'competitor_domains' => array_values( $competitors ),
			'our_pages'         => $our_pages,
			'next_step_hint'    => count( $competitors ) > 0
				? 'Fetch top-ranking pages for this keyword via WebFetch. For each competitor domain, identify topics, schema, and structural patterns we are missing.'
				: 'No competitor domains configured. Add them in CC Assistant > Settings > Citation Rules > Competitor domains so this tool can target the brief.',
		);
	}

	/* =====================================================================
	 * Brief generator: pre-flight pack for writing a new post around a
	 * target keyword. Synthesizes everything the plugin already knows about
	 * the topic so Claude can spend its context on the writing, not on
	 * re-discovering site state.
	 *
	 * Returns:
	 *   - existing GSC presence (do we already rank for this?)
	 *   - cannibalization risk (own pages competing for the term)
	 *   - best-fit cluster (token overlap with existing cluster names/desc)
	 *   - intent guess (informational / commercial / transactional / local)
	 *   - suggested word count + format hints
	 *   - internal link plan from the matched cluster
	 *   - competitor domain list for WebFetch
	 *   - a ready Claude prompt the user can copy + paste
	 * ================================================================== */
	public static function brief_for_keyword( $args = array() ) {
		global $wpdb;
		$keyword = trim( (string) ( $args['keyword'] ?? '' ) );
		if ( '' === $keyword ) {
			return new WP_Error( 'keyword_required', 'keyword is required.' );
		}
		$intent_hint = isset( $args['intent_hint'] ) ? (string) $args['intent_hint'] : '';
		$days        = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;
		$cutoff      = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		$lc_keyword  = mb_strtolower( $keyword );
		$query_hash  = sha1( $lc_keyword );
		$gsc_table   = $wpdb->prefix . 'cc_gsc_queries';

		// 1) Existing presence: are we already ranking?
		$existing_pages = array();
		$gsc_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $gsc_table ) ) );
		if ( $gsc_exists === $gsc_table ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT page,
				        SUM(impressions) AS impressions,
				        SUM(clicks)      AS clicks,
				        SUM(position*impressions)/NULLIF(SUM(impressions),0) AS position
				 FROM {$gsc_table}
				 WHERE date >= %s AND query_hash = %s
				 GROUP BY page
				 ORDER BY impressions DESC
				 LIMIT 5",
				$cutoff,
				$query_hash
			) );
			foreach ( (array) $rows as $r ) {
				// A retired URL that 301s to a live page is NOT a separate
				// competitor for cannibalization purposes — it is the same
				// page. Resolving it keeps cannibalization_risk honest.
				$pid = CC_Assistant_URL_Resolver::to_post_id( $r->page );
				$existing_pages[] = array(
					'page'         => $r->page,
					'post_id'      => $pid ? (int) $pid : null,
					'title'        => $pid ? get_the_title( (int) $pid ) : '',
					'impressions'  => (int) $r->impressions,
					'clicks'       => (int) $r->clicks,
					'avg_position' => round( (float) $r->position, 2 ),
				);
			}
		}

		// 2) Cannibalization risk: same query, multiple pages within striking distance.
		// Counted over DISTINCT POSTS, not rows. Search Console reports a
		// retired URL and its 301 destination as two pages for months after a
		// consolidation; counting rows called that self-cannibalization and
		// told the operator to fix work they had already done correctly.
		// URLs that resolve to no post at all still count individually — an
		// unresolvable rival is a real rival until proven otherwise.
		$cannibal_risk = false;
		$competing_keys = array();
		foreach ( $existing_pages as $ep ) {
			if ( $ep['avg_position'] > 30 ) {
				continue;
			}
			$competing_keys[ $ep['post_id'] ? 'p' . (int) $ep['post_id'] : 'u' . $ep['page'] ] = true;
		}
		$cannibal_risk = ( count( $competing_keys ) >= 2 );

		// 3) Best-fit cluster: token overlap between keyword and cluster name/description.
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		CC_Assistant_Topic_Clusters::ensure_tables();
		$clusters = CC_Assistant_Topic_Clusters::list_clusters();
		$kw_tokens = array_filter( array_map( 'trim', preg_split( '/\W+/', $lc_keyword ) ), function ( $t ) {
			return strlen( $t ) >= 3;
		} );
		$best_cluster_id = null;
		$best_score      = 0;
		foreach ( $clusters as $c ) {
			$haystack = mb_strtolower( $c->name . ' ' . (string) $c->description );
			$score    = 0;
			foreach ( $kw_tokens as $tok ) {
				if ( false !== strpos( $haystack, $tok ) ) {
					$score++;
				}
			}
			if ( $score > $best_score ) {
				$best_score      = $score;
				$best_cluster_id = (int) $c->id;
			}
		}

		// Confidence gate: a single overlapping token (e.g. "emergency"
		// matching every emergency-themed cluster) is too weak to anchor a
		// link plan. Demand >= 2 token overlap before returning a cluster
		// fit; below that, return null and let the model decide whether to
		// propose a new cluster or pick one manually. The prior behaviour
		// confidently slotted "infectious disease emergency room" into
		// Pillar 2 (Pediatric) on a single shared "emergency" token,
		// which was useless to the operator.
		$no_fit_reason = null;
		if ( $best_score < 2 ) {
			$no_fit_reason   = sprintf(
				'No cluster matches with confidence (best token_overlap = %d, need >= 2). Candidate for a new cluster or manual assignment.',
				$best_score
			);
			$best_cluster_id = null;
		}

		$best_cluster = null;
		$cluster_link_plan = array();
		if ( $best_cluster_id ) {
			$cluster = CC_Assistant_Topic_Clusters::get_cluster( $best_cluster_id );
			$members = CC_Assistant_Topic_Clusters::get_members( $best_cluster_id );
			$best_cluster = array(
				'id'             => (int) $cluster->id,
				'name'           => $cluster->name,
				'description'    => $cluster->description,
				'pillar_post_id' => $cluster->pillar_post_id ? (int) $cluster->pillar_post_id : null,
				'member_count'   => count( $members ),
				'token_overlap'  => $best_score,
			);
			// Link plan: pillar always, plus up to 4 supporting pages.
			if ( $cluster->pillar_post_id ) {
				$cluster_link_plan[] = array(
					'role'    => 'pillar',
					'post_id' => (int) $cluster->pillar_post_id,
					'title'   => get_the_title( (int) $cluster->pillar_post_id ),
					'url'     => get_permalink( (int) $cluster->pillar_post_id ),
					'note'    => 'Link only from a passage where this destination helps the reader; no mandatory repeated placement.',
				);
			}
			$picked = 0;
			foreach ( $members as $m ) {
				if ( CC_Assistant_Topic_Clusters::ROLE_SUPPORTING !== $m->role ) {
					continue;
				}
				if ( $picked >= 4 ) {
					break;
				}
				$cluster_link_plan[] = array(
					'role'    => 'supporting',
					'post_id' => (int) $m->post_id,
					'title'   => get_the_title( (int) $m->post_id ),
					'url'     => get_permalink( (int) $m->post_id ),
					'note'    => 'Mention in a relevant subsection if it deepens the topic.',
				);
				$picked++;
			}
		}

		// 4) Intent guess. Use hint if provided, otherwise classify by lexical pattern.
		$intent = self::classify_intent( $lc_keyword, $intent_hint );

		// 5) Format + length hints by intent.
		$format_hints = self::format_hints_for_intent( $intent );

		// 6) Competitor domains for WebFetch.
		$competitors = (array) get_option( 'cc_assistant_competitor_domains', array() );

		// 7) Site memory snippet (style guide hint).
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		$site_name = get_bloginfo( 'name' );

		$existing_summary = '';
		if ( ! empty( $existing_pages ) ) {
			$top = $existing_pages[0];
			$existing_summary = sprintf(
				'You already rank for this query: %s at position %.1f with %d monthly impressions.%s',
				$top['page'],
				(float) $top['avg_position'],
				(int) $top['impressions'],
				$cannibal_risk ? ' Multiple URLs were observed; inspect their reader tasks before claiming harmful competition.' : ''
			);
		} else {
			$existing_summary = 'No matching query rows in this reporting sample. Demand is unmeasured and existing content may still cover the reader task. Use plan_blog_content; GSC absence does not block a new niche topic.';
		}

		$cluster_summary = $best_cluster
			? sprintf( 'Best-fit cluster: "%s" (id %d, %d members). Link to its pillar from the new post.', $best_cluster['name'], $best_cluster['id'], $best_cluster['member_count'] )
			: 'No clear cluster match — this keyword may seed a new cluster.';

		$claude_prompt = sprintf(
            "Use plan_blog_content for the topic %s on %s. GSC is optional observation, not the boundary of the niche. %s\n%s\nInspect existing content, select a distinct reader task, and explain a useful supported contribution. Research comparable pages only through available tools; record fetch limitations and do not assume search position. Use a natural length and contextual links. Draft only after the brief is grounded in current source pages and evidence.",
            $keyword, $site_name, $existing_summary, $cluster_summary
        );

		return array(
			'keyword'             => $keyword,
			'intent'              => $intent,
			'existing_pages'      => $existing_pages,
			'cannibalization_risk' => $cannibal_risk,
			'cannibalization_interpretation' => 'Overlap candidate only; harm and consolidation eligibility are not established.',
			'growth_planner' => 'plan_blog_content',
			'gsc_required_for_new_topic' => false,
			'best_cluster'        => $best_cluster,
			'no_fit_reason'       => $no_fit_reason,
			'cluster_link_plan'   => $cluster_link_plan,
			'format_hints'        => $format_hints,
			'competitor_domains'  => array_values( $competitors ),
			'next_step_hint'      => $best_cluster
				? 'Inspect reader tasks in the matched cluster; develop a supported contribution rather than copy a sampled format.'
				: 'An existing cluster is not required. Use plan_blog_content to establish a supported niche relationship and inspect coverage.',
			'claude_prompt'       => $claude_prompt,
		);
	}

	/**
	 * Heuristic intent classifier. Cheap pattern match — enough to seed a
	 * brief; Claude can override at write time.
	 */
	private static function classify_intent( $lc_keyword, $hint = '' ) {
		$valid = array( 'informational', 'commercial', 'transactional', 'navigational', 'local' );
		if ( $hint && in_array( $hint, $valid, true ) ) {
			return $hint;
		}
		// Local: "near me", city + service, "in {place}".
		if ( preg_match( '/\b(near me|nearby|in [a-z]+ ?(?:tx|ca|fl|ny|nj|pa|wa)|city|county)\b/i', $lc_keyword ) ) {
			return 'local';
		}
		// Transactional: buy/order/book/schedule/download/coupon.
		if ( preg_match( '/\b(buy|order|book|schedule|appointment|download|coupon|deal|cheap|price|cost|near me)\b/i', $lc_keyword ) ) {
			return 'transactional';
		}
		// Commercial: vs / review / best / top / compare.
		if ( preg_match( '/\b(best|top|vs|review|compare|alternative|cheapest|recommended)\b/i', $lc_keyword ) ) {
			return 'commercial';
		}
		// Informational: question words / how / what / why.
		if ( preg_match( '/^(what|why|how|when|where|who|which|is|are|can|does|do|should|will)\b/i', $lc_keyword )
			|| preg_match( '/\b(guide|tutorial|tips|examples?|symptoms|causes|treatment)\b/i', $lc_keyword ) ) {
			return 'informational';
		}
		return 'informational';
	}

	/* =====================================================================
	 * Accessibility audit on post content. Walks the rendered HTML for
	 * WCAG-style content issues that intersect with content optimisation:
	 *  - link text quality ("click here", "read more", URLs as anchor)
	 *  - image alt issues (delegates to existing image audit)
	 *  - tables without <th> headers
	 *  - heading levels skipped
	 *  - empty headings or empty links
	 *  - inline color styles with very low contrast hints
	 *  - aria-hidden text content (often hides real content from AT)
	 * ================================================================== */
	/**
	 * v0.76 — accessibility audit on the RENDERED page.
	 *
	 * Until v0.75 this read post_content plus the Elementor editor fields, a
	 * stale snapshot on Elementor pages: it reported six "empty links" and six
	 * "empty alt" images on pages whose live DOM had names and alt text on
	 * every one (the same blind spot as the container-link false gap). A
	 * parser can prove presence, never absence. This version fetches the
	 * page the way a browser does (render probe: cache-busted, own UA, WAF
	 * safe), walks the DOM in document order, and credits img[alt] and
	 * aria-label the way assistive technology does.
	 *
	 * Covers the content-level WCAG checks a server can do without a
	 * browser. Colour contrast, keyboard focus and screen-reader order need
	 * wcag_sweep (axe-core + Playwright on the operator machine).
	 */
	public static function accessibility_audit( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'not_published', 'The audit reads the rendered page; this post is not published, so there is nothing rendered to read. Use pre_publish_check on drafts.' );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-render-probe.php';
		$url   = get_permalink( $post );
		$fetch = CC_Assistant_Render_Probe::fetch_public( $url );
		if ( is_wp_error( $fetch ) ) {
			return new WP_Error( 'fetch_failed', 'Could not fetch the rendered page (' . $fetch->get_error_message() . '). Refusing to fall back to the stale post_content parser, which reports things the live page does not have.' );
		}
		$html = (string) $fetch['body'];
		$dom  = CC_Assistant_Render_Probe::load_dom_public( $html );
		if ( ! $dom ) {
			return new WP_Error( 'dom_unavailable', 'Could not parse the rendered HTML.' );
		}
		$xp       = new DOMXPath( $dom );
		$findings = array();
		$snip     = function ( $el, $len = 140 ) {
			$s = preg_replace( '/\s+/', ' ', (string) $el->ownerDocument->saveHTML( $el ) );
			return mb_substr( trim( (string) $s ), 0, $len );
		};
		$hidden = function ( $el ) use ( $xp ) {
			// aria-hidden ancestors or the element itself: not in the AT tree.
			return $xp->query( 'ancestor-or-self::*[@aria-hidden="true"]', $el )->length > 0;
		};

		// 1) Document language and one H1 (WCAG 3.1.1, 1.3.1 / 2.4.6).
		$html_el = $dom->getElementsByTagName( 'html' )->item( 0 );
		if ( ! $html_el || '' === trim( (string) $html_el->getAttribute( 'lang' ) ) ) {
			$findings[] = array( 'type' => 'html_lang_missing', 'wcag' => '3.1.1', 'note' => 'No lang attribute on <html>; screen readers pick a voice by guessing.' );
		}
		$h1s = $xp->query( '//h1' );
		$h1n = 0;
		foreach ( $h1s as $h ) {
			if ( ! $hidden( $h ) ) {
				$h1n++;
			}
		}
		if ( 0 === $h1n ) {
			$findings[] = array( 'type' => 'no_h1', 'wcag' => '1.3.1', 'note' => 'Page has no H1.' );
		} elseif ( $h1n > 1 ) {
			$findings[] = array( 'type' => 'multiple_h1', 'wcag' => '1.3.1', 'count' => $h1n );
		}

		// 2) Skip link (WCAG 2.4.1): present, and pointing at something.
		$skip = null;
		foreach ( $xp->query( '//a[starts-with(@href,"#")]' ) as $a ) {
			if ( preg_match( '/skip/i', (string) $a->textContent ) ) {
				$skip = $a;
				break;
			}
		}
		if ( ! $skip ) {
			$findings[] = array( 'type' => 'skip_link_missing', 'wcag' => '2.4.1', 'note' => 'No "skip to content" link; keyboard users tab through the whole header on every page. Landmarks/headings can also satisfy 2.4.1, so treat as a should-fix.' );
		} else {
			$target = substr( (string) $skip->getAttribute( 'href' ), 1 );
			if ( '' === $target || ! $dom->getElementById( $target ) ) {
				$findings[] = array( 'type' => 'skip_link_target_missing', 'wcag' => '2.4.1', 'href' => '#' . $target, 'note' => 'The skip link points at an id that does not exist on this page (Elementor header templates replace the theme main container).' );
			}
		}

		// 3) Links: accessible name, weak text, raw URLs (2.4.4 / 4.1.2).
		$bad_link_phrases = array( 'click here', 'read more', 'learn more', 'here', 'this link', 'this page', 'more' );
		foreach ( $xp->query( '//a[@href]' ) as $a ) {
			if ( $hidden( $a ) ) {
				continue;
			}
			$name = trim( (string) CC_Assistant_Render_Probe::accessible_name_public( $a ) );
			$href = (string) $a->getAttribute( 'href' );
			if ( '' === $name ) {
				$findings[] = array( 'type' => 'empty_link', 'wcag' => '2.4.4', 'href' => mb_substr( $href, 0, 120 ), 'snippet' => $snip( $a ) );
				continue;
			}
			$lc = mb_strtolower( $name );
			if ( in_array( $lc, $bad_link_phrases, true ) ) {
				$findings[] = array( 'type' => 'weak_link_text', 'wcag' => '2.4.4', 'text' => $name, 'href' => mb_substr( $href, 0, 120 ) );
			}
			if ( preg_match( '/^https?:\/\//i', $name ) ) {
				$findings[] = array( 'type' => 'url_as_anchor', 'wcag' => '2.4.4', 'text' => mb_substr( $name, 0, 80 ) );
			}
		}

		// 4) Headings in DOCUMENT order (XPath results are document-ordered):
		//    skipped levels and empty headings (1.3.1).
		$prev = 0;
		foreach ( $xp->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $h ) {
			if ( $hidden( $h ) ) {
				continue;
			}
			$lv  = (int) substr( $h->nodeName, 1 );
			$txt = trim( (string) CC_Assistant_Render_Probe::accessible_name_public( $h ) );
			if ( '' === $txt ) {
				$findings[] = array( 'type' => 'empty_heading', 'wcag' => '1.3.1', 'level' => $lv, 'snippet' => $snip( $h, 100 ) );
			}
			if ( $prev > 0 && $lv > $prev + 1 ) {
				$findings[] = array( 'type' => 'heading_skip', 'wcag' => '1.3.1', 'from' => $prev, 'to' => $lv, 'text' => mb_substr( $txt, 0, 60 ) );
			}
			$prev = $lv;
		}

		// 5) Tables without header cells (1.3.1).
		foreach ( $xp->query( '//table' ) as $t ) {
			if ( $hidden( $t ) ) {
				continue;
			}
			if ( 0 === $xp->query( './/th', $t )->length ) {
				$findings[] = array( 'type' => 'table_no_th', 'wcag' => '1.3.1', 'snippet' => $snip( $t, 100 ) );
			}
		}

		// 6) Real content hidden from AT (aria-hidden on > 30 chars of text).
		foreach ( $xp->query( '//*[@aria-hidden="true"]' ) as $el ) {
			$txt = trim( preg_replace( '/\s+/', ' ', (string) $el->textContent ) );
			if ( mb_strlen( $txt ) > 30 && 0 === $xp->query( './/script|.//style', $el )->length ) {
				$findings[] = array( 'type' => 'aria_hidden_content', 'wcag' => '4.1.2', 'snippet' => mb_substr( $txt, 0, 80 ) );
			}
		}

		// 7) Images: missing alt ATTRIBUTE fails; alt="" is a decorative
		//    declaration and is counted, not flagged (1.1.1).
		$img_issues = array();
		$decorative = 0;
		foreach ( $xp->query( '//img' ) as $img ) {
			$src = (string) $img->getAttribute( 'src' );
			if ( '' === $src || 0 === strpos( $src, 'data:' ) || $hidden( $img ) ) {
				continue;
			}
			if ( ! $img->hasAttribute( 'alt' ) ) {
				$img_issues[] = array( 'src' => mb_substr( $src, 0, 160 ), 'issue' => 'missing_alt_attribute', 'wcag' => '1.1.1' );
			} elseif ( '' === trim( (string) $img->getAttribute( 'alt' ) ) ) {
				$decorative++;
				$parent_link = $xp->query( 'ancestor::a[@href]', $img )->length > 0;
				if ( $parent_link && '' === trim( (string) CC_Assistant_Render_Probe::accessible_name_public( $xp->query( 'ancestor::a[@href]', $img )->item( 0 ) ) ) ) {
					$img_issues[] = array( 'src' => mb_substr( $src, 0, 160 ), 'issue' => 'empty_alt_inside_unnamed_link', 'wcag' => '1.1.1' );
				}
			}
		}
		foreach ( $xp->query( '//*[@role="img"]' ) as $el ) {
			if ( ! $hidden( $el ) && '' === trim( (string) $el->getAttribute( 'aria-label' ) ) && '' === trim( (string) $el->getAttribute( 'aria-labelledby' ) ) ) {
				$img_issues[] = array( 'src' => mb_substr( (string) $el->getAttribute( 'data-thumbnail' ) ?: $snip( $el, 80 ), 0, 160 ), 'issue' => 'role_img_without_name', 'wcag' => '1.1.1' );
			}
		}

		// 8) Frames without a title (4.1.2 / 2.4.1).
		foreach ( $xp->query( '//iframe' ) as $fr ) {
			if ( $hidden( $fr ) ) {
				continue;
			}
			$style = (string) $fr->getAttribute( 'style' );
			if ( preg_match( '/display\s*:\s*none/i', $style ) || '0' === (string) $fr->getAttribute( 'height' ) ) {
				continue; // tag-manager pixels
			}
			if ( '' === trim( (string) $fr->getAttribute( 'title' ) ) && '' === trim( (string) $fr->getAttribute( 'aria-label' ) ) ) {
				$src = (string) $fr->getAttribute( 'src' ) ?: (string) $fr->getAttribute( 'data-src' );
				$findings[] = array( 'type' => 'iframe_no_title', 'wcag' => '4.1.2', 'src' => mb_substr( $src, 0, 140 ) );
			}
		}

		// 9) Form controls without an accessible name (1.3.1 / 3.3.2 / 4.1.2)
		//    and buttons without a name.
		foreach ( $xp->query( '//input[not(@type="hidden") and not(@type="submit") and not(@type="button") and not(@type="image")]|//select|//textarea' ) as $ctl ) {
			if ( $hidden( $ctl ) ) {
				continue;
			}
			$named = '' !== trim( (string) $ctl->getAttribute( 'aria-label' ) ) || '' !== trim( (string) $ctl->getAttribute( 'aria-labelledby' ) ) || '' !== trim( (string) $ctl->getAttribute( 'title' ) );
			if ( ! $named && $xp->query( 'ancestor::label', $ctl )->length > 0 ) {
				$named = true;
			}
			$id = (string) $ctl->getAttribute( 'id' );
			if ( ! $named && '' !== $id ) {
				$named = $xp->query( '//label[@for=' . self::xpath_literal( $id ) . ']' )->length > 0;
			}
			if ( ! $named ) {
				$findings[] = array( 'type' => 'form_control_unlabeled', 'wcag' => '3.3.2', 'snippet' => $snip( $ctl, 120 ), 'note' => 'placeholder text is not a label' );
			}
		}
		foreach ( $xp->query( '//button' ) as $b ) {
			if ( ! $hidden( $b ) && '' === trim( (string) CC_Assistant_Render_Probe::accessible_name_public( $b ) ) ) {
				$findings[] = array( 'type' => 'button_no_name', 'wcag' => '4.1.2', 'snippet' => $snip( $b, 120 ) );
			}
		}

		// 10) Moving content that starts by itself (2.2.2): Elementor
		//     carousels/sliders with autoplay, and <video autoplay> without controls.
		$auto = 0;
		if ( preg_match_all( '/&quot;autoplay&quot;:&quot;yes&quot;|"autoplay":"yes"/', $html, $am ) ) {
			$auto += count( $am[0] );
		}
		if ( $auto > 0 ) {
			$findings[] = array( 'type' => 'autoplay_carousel', 'wcag' => '2.2.2', 'count' => $auto, 'note' => 'A carousel/slider set to autoplay needs a visible pause control; pause-on-hover does not count for keyboard or touch users.' );
		}
		foreach ( $xp->query( '//video[@autoplay and not(@controls)]' ) as $v ) {
			$findings[] = array( 'type' => 'autoplay_video_no_controls', 'wcag' => '2.2.2', 'snippet' => $snip( $v, 100 ) );
		}

		$summary = array();
		foreach ( $findings as $f ) {
			$summary[ $f['type'] ] = ( isset( $summary[ $f['type'] ] ) ? $summary[ $f['type'] ] : 0 ) + 1;
		}
		if ( ! empty( $img_issues ) ) {
			$summary['image_alt_issues'] = count( $img_issues );
		}

		return array(
			'post_id'          => (int) $post_id,
			'url'              => $url,
			'source'           => 'rendered_dom',
			'http_code'        => isset( $fetch['code'] ) ? (int) $fetch['code'] : null,
			'cache_state'      => isset( $fetch['cache']['state'] ) ? (string) $fetch['cache']['state'] : null,
			'issue_total'      => count( $findings ) + count( $img_issues ),
			'summary'          => $summary,
			'findings'         => array_slice( $findings, 0, 60 ),
			'image_findings'   => array_slice( $img_issues, 0, 20 ),
			'decorative_images'=> $decorative,
			'not_measured'     => 'colour contrast, visible focus, keyboard operability, screen-reader order, motion. Run wcag_sweep for those (axe-core in a real browser, pages scrolled before measuring).',
		);
	}

	/** Quote a string for an XPath predicate (handles both quote characters). */
	private static function xpath_literal( $s ) {
		$s = (string) $s;
		if ( false === strpos( $s, "'" ) ) {
			return "'" . $s . "'";
		}
		if ( false === strpos( $s, '"' ) ) {
			return '"' . $s . '"';
		}
		return 'concat(\'' . str_replace( "'", "',\"'\",'", $s ) . '\')';
	}

	/* =====================================================================
	 * Per-page structural audits — canonical, hreflang, E-E-A-T coverage.
	 * Anchored to the documented Google rules (see class-seo-playbook.php's
	 * "Schema deprecations and policy risks" + "AI Overview and AI Mode
	 * citation patterns" + "What's really moving rankings" sections).
	 * ================================================================== */

	/**
	 * Fetch the rendered HTML of a URL using the cc-assistant UA so
	 * WAFs (SiteGround, Cloudflare default-block) don't 403 our own
	 * server fetching its own page.
	 */
	private static function fetch_rendered_html_for_audit( $url, $timeout = 15 ) {
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_url', 'Empty URL.' );
		}
		// v0.51.6: bust the edge cache. SiteGround's Dynamic Cache served STALE
		// pre-edit HTML to this server's own outbound fetch (post 2453 was scored
		// against a 1,077-word cached copy while the live page was 2,948 words),
		// silently corrupting every audit built on this helper (eeat_coverage,
		// helpful_content_score, canonical_audit, win_audit competitor-side is
		// external and unaffected). Mirror the render-probe recipe: no-cache
		// headers (SG's cache is PATH-keyed, so the query buster alone is not
		// enough) + clean-URL retry for Custom Permalinks installs that 404
		// unknown query args.
		$bust = add_query_arg(
			array(
				'cc_audit' => (string) microtime( true ),
				'cc_nonce' => uniqid( '', true ),
			),
			$url
		);
		$args = array(
			'timeout'     => (int) $timeout,
			'redirection' => 3,
			'user-agent'  => CC_ASSISTANT_HTTP_UA,
			'sslverify'   => true,
			'headers'     => array(
				'Accept'        => 'text/html',
				'Cache-Control' => 'no-cache, no-store, max-age=0',
				'Pragma'        => 'no-cache',
			),
		);
		$response = wp_remote_get( $bust, $args );
		$retry_code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || $retry_code < 200 || $retry_code >= 400 ) {
			$response = wp_remote_get( $url, $args );
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 400 || '' === $body ) {
			return new WP_Error(
				'fetch_failed',
				sprintf( 'HTTP %d, body length %d', $code, strlen( $body ) ),
				array( 'status' => $code )
			);
		}
		return array( 'status' => $code, 'body' => $body, 'url' => $url );
	}

	/**
	 * Parse rendered HTML into a DOMXPath. Suppresses libxml warnings
	 * from imperfect markup. UTF-8 hint prefix prevents loadHTML from
	 * misinterpreting bytes as ISO-8859-1.
	 */
	private static function build_audit_dom( $html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'no_domdocument', 'PHP DOM extension is not loaded.' );
		}
		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return new DOMXPath( $doc );
	}

	/**
	 * canonical_audit: fetch the rendered HTML of the post's permalink and
	 * validate the rel="canonical" tag(s) against Google's documented rules
	 * — single, in <head>, absolute, no fragment, present in source HTML.
	 * Wrong canonical = deindex risk. P0 guard.
	 */
	public static function canonical_audit( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$permalink = (string) get_permalink( $post_id );
		if ( empty( $permalink ) ) {
			return new WP_Error( 'no_permalink', 'Post has no permalink.' );
		}
		$fetched = self::fetch_rendered_html_for_audit( $permalink );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}
		$xpath = self::build_audit_dom( $fetched['body'] );
		if ( is_wp_error( $xpath ) ) {
			return $xpath;
		}

		$head_links = $xpath->query( '//head//link[translate(@rel,"CANOIL","canoil")="canonical"]' );
		$body_links = $xpath->query( '//body//link[translate(@rel,"CANOIL","canoil")="canonical"]' );
		$head_count = $head_links ? $head_links->length : 0;
		$body_count = $body_links ? $body_links->length : 0;

		$canonical_href = '';
		if ( $head_count > 0 ) {
			$canonical_href = trim( (string) $head_links->item( 0 )->getAttribute( 'href' ) );
		} elseif ( $body_count > 0 ) {
			$canonical_href = trim( (string) $body_links->item( 0 )->getAttribute( 'href' ) );
		}

		$is_absolute  = ( '' !== $canonical_href ) && (bool) preg_match( '#^https?://#i', $canonical_href );
		$has_fragment = ( '' !== $canonical_href ) && ( false !== strpos( $canonical_href, '#' ) );
		$norm_self    = self::normalize_page_url( $permalink );
		$norm_canon   = '' !== $canonical_href ? self::normalize_page_url( $canonical_href ) : '';
		$matches_self = ( '' !== $norm_canon ) && ( $norm_self === $norm_canon );

		$issues = array();
		if ( 0 === $head_count && 0 === $body_count ) {
			$issues[] = array( 'code' => 'canonical_missing', 'severity' => 'high', 'message' => 'No <link rel="canonical"> in rendered HTML. Google will pick its own, which may select a duplicate URL.' );
		}
		if ( 0 === $head_count && $body_count > 0 ) {
			$issues[] = array( 'code' => 'canonical_in_body', 'severity' => 'high', 'message' => sprintf( 'Canonical tag(s) in <body>. Google only honors canonicals in <head>. Found %d in body, 0 in head.', $body_count ) );
		}
		if ( $head_count > 1 ) {
			$issues[] = array( 'code' => 'canonical_duplicate', 'severity' => 'high', 'message' => sprintf( '%d canonical tags in <head>. Google may treat as conflicting and ignore both.', $head_count ) );
		}
		if ( '' !== $canonical_href && ! $is_absolute ) {
			$issues[] = array( 'code' => 'canonical_not_absolute', 'severity' => 'high', 'message' => sprintf( 'Canonical URL is not absolute: "%s". Use a fully-qualified https:// URL.', $canonical_href ) );
		}
		if ( $has_fragment ) {
			$issues[] = array( 'code' => 'canonical_has_fragment', 'severity' => 'medium', 'message' => sprintf( 'Canonical contains a #fragment: "%s". Google strips fragments from canonical URLs.', $canonical_href ) );
		}
		if ( '' !== $canonical_href && ! $matches_self ) {
			$issues[] = array( 'code' => 'canonical_cross_referenced', 'severity' => 'info', 'message' => sprintf( 'Canonical points to a different URL than this page: %s. Verify this is intentional (duplicate consolidation or hreflang cluster).', $canonical_href ) );
		}

		$verdict = 'pass';
		foreach ( $issues as $i ) {
			if ( 'high' === $i['severity'] ) { $verdict = 'fail'; break; }
			if ( 'medium' === $i['severity'] ) { $verdict = 'warn'; }
		}

		return array(
			'post_id'      => $post_id,
			'page_url'     => $permalink,
			'canonical'    => '' !== $canonical_href ? $canonical_href : null,
			'in_head'      => (int) $head_count,
			'in_body'      => (int) $body_count,
			'is_absolute'  => (bool) $is_absolute,
			'has_fragment' => (bool) $has_fragment,
			'matches_self' => (bool) $matches_self,
			'verdict'      => $verdict,
			'issues'       => $issues,
		);
	}

	/**
	 * hreflang_audit: validate a post's full hreflang cluster against
	 * Google's rules — reciprocity, self-reference, absolute URLs, head-
	 * placement, valid ISO codes, canonical-cluster agreement. Walks every
	 * Polylang/WPML sibling for this post and HTTP-fetches each one.
	 *
	 * Monolingual sites or untranslated posts return a clean pass.
	 * P0 guard — broken hreflang splits indexing across languages.
	 */
	public static function hreflang_audit( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';

		$engine = CC_Assistant_Multilingual::engine();
		$tx     = CC_Assistant_Multilingual::translations_of( $post_id );
		if ( empty( $tx ) ) {
			$self_lang = CC_Assistant_Multilingual::language_of( $post_id );
			if ( $self_lang ) {
				$tx = array( $self_lang => $post_id );
			}
		}
		if ( empty( $tx ) ) {
			return array(
				'post_id' => $post_id,
				'engine'  => $engine ?: 'none',
				'cluster' => array(),
				'issues'  => array(),
				'verdict' => 'pass',
				'message' => 'Site is monolingual or post has no translations. Nothing to validate.',
			);
		}

		$cluster = array();
		$issues  = array();
		foreach ( $tx as $lang_code => $pid ) {
			$pid       = (int) $pid;
			$permalink = (string) get_permalink( $pid );
			$member    = array(
				'lang'      => (string) $lang_code,
				'post_id'   => $pid,
				'page_url'  => $permalink,
				'links'     => array(),
				'canonical' => null,
				'fetched'   => false,
				'fetch_err' => null,
			);
			if ( empty( $permalink ) ) {
				$issues[] = array( 'code' => 'hreflang_member_no_permalink', 'severity' => 'high', 'message' => sprintf( 'Cluster member %s (post %d) has no permalink.', $lang_code, $pid ) );
				$cluster[] = $member;
				continue;
			}
			$fetched = self::fetch_rendered_html_for_audit( $permalink );
			if ( is_wp_error( $fetched ) ) {
				$member['fetch_err'] = $fetched->get_error_message();
				$issues[] = array( 'code' => 'hreflang_fetch_failed', 'severity' => 'medium', 'message' => sprintf( 'Could not fetch %s (post %d): %s', $permalink, $pid, $fetched->get_error_message() ) );
				$cluster[] = $member;
				continue;
			}
			$xpath = self::build_audit_dom( $fetched['body'] );
			if ( is_wp_error( $xpath ) ) {
				$cluster[] = $member;
				continue;
			}
			$member['fetched'] = true;

			$head_alts = $xpath->query( '//head//link[translate(@rel,"ALTERNT","alternt")="alternate" and @hreflang]' );
			$body_alts = $xpath->query( '//body//link[translate(@rel,"ALTERNT","alternt")="alternate" and @hreflang]' );
			if ( $body_alts && $body_alts->length > 0 ) {
				$issues[] = array( 'code' => 'hreflang_in_body', 'severity' => 'high', 'message' => sprintf( '%s: %d hreflang link(s) in <body>. Google only honors hreflang in <head>.', $permalink, $body_alts->length ) );
			}
			if ( $head_alts && $head_alts->length > 0 ) {
				foreach ( $head_alts as $node ) {
					$member['links'][] = array(
						'hreflang' => trim( (string) $node->getAttribute( 'hreflang' ) ),
						'href'     => trim( (string) $node->getAttribute( 'href' ) ),
					);
				}
			}

			$canon_nodes = $xpath->query( '//head//link[translate(@rel,"CANOIL","canoil")="canonical"]' );
			if ( $canon_nodes && $canon_nodes->length > 0 ) {
				$member['canonical'] = trim( (string) $canon_nodes->item( 0 )->getAttribute( 'href' ) );
			}

			$cluster[] = $member;
		}

		$expected_urls = array();
		foreach ( $cluster as $m ) {
			if ( $m['fetched'] ) {
				$expected_urls[ self::normalize_page_url( $m['page_url'] ) ] = $m['lang'];
			}
		}
		foreach ( $cluster as $m ) {
			if ( ! $m['fetched'] ) {
				continue;
			}
			$declared = array();
			foreach ( $m['links'] as $l ) {
				if ( '' === $l['href'] || ! preg_match( '#^https?://#i', $l['href'] ) ) {
					$issues[] = array( 'code' => 'hreflang_not_absolute', 'severity' => 'high', 'message' => sprintf( '%s: hreflang="%s" href="%s" is not absolute.', $m['page_url'], $l['hreflang'], $l['href'] ) );
					continue;
				}
				if ( 'x-default' !== $l['hreflang'] && ! preg_match( '/^[a-z]{2,3}(-[A-Z]{2})?$/i', $l['hreflang'] ) ) {
					$issues[] = array( 'code' => 'hreflang_invalid_iso', 'severity' => 'high', 'message' => sprintf( '%s: hreflang="%s" is not a valid ISO 639-1 + optional ISO 3166-1 alpha-2 code. Use forms like "en", "es-MX", or "x-default".', $m['page_url'], $l['hreflang'] ) );
				}
				$declared[ self::normalize_page_url( $l['href'] ) ] = true;
			}
			$self_norm = self::normalize_page_url( $m['page_url'] );
			if ( ! isset( $declared[ $self_norm ] ) ) {
				$issues[] = array( 'code' => 'hreflang_no_self_reference', 'severity' => 'high', 'message' => sprintf( '%s: does not list itself in its own hreflang block. Every page must self-reference.', $m['page_url'] ) );
			}
			foreach ( $expected_urls as $expected_url => $expected_lang ) {
				if ( ! isset( $declared[ $expected_url ] ) ) {
					$issues[] = array( 'code' => 'hreflang_reciprocity_missing', 'severity' => 'high', 'message' => sprintf( '%s: does not link to cluster member %s (%s). Reciprocity is required.', $m['page_url'], $expected_url, $expected_lang ) );
				}
			}
			if ( ! empty( $m['canonical'] ) ) {
				$canon_norm = self::normalize_page_url( $m['canonical'] );
				if ( ! isset( $expected_urls[ $canon_norm ] ) ) {
					$issues[] = array( 'code' => 'canonical_outside_hreflang_cluster', 'severity' => 'high', 'message' => sprintf( '%s: canonical "%s" points outside the hreflang cluster. Canonical must agree with one of the cluster member URLs.', $m['page_url'], $m['canonical'] ) );
				}
			}
		}

		$verdict = 'pass';
		foreach ( $issues as $i ) {
			if ( 'high' === $i['severity'] ) { $verdict = 'fail'; break; }
			if ( 'medium' === $i['severity'] ) { $verdict = 'warn'; }
		}

		return array(
			'post_id' => $post_id,
			'engine'  => $engine ?: 'none',
			'cluster' => $cluster,
			'issues'  => $issues,
			'verdict' => $verdict,
		);
	}

	/**
	 * eeat_coverage_audit: per-page E-E-A-T baseline check, especially for
	 * YMYL service pages. Validates three proxies for the leak-confirmed
	 * authorReputationScore + contentEffort attributes:
	 *   1) Visible author byline in the rendered body
	 *   2) Schema Person entity with sameAs → LinkedIn (or similar)
	 *   3) Citation density: ≥1 .gov/.edu/medical-authority link per 1000 words
	 *
	 * Use BEFORE proposing a YMYL page rewrite to confirm the baseline.
	 */
	/**
	 * Authority hosts for citation-density scoring — INDUSTRY-AWARE. Universal
	 * high-trust catchalls (.gov/.edu/who.int) apply to every vertical; a
	 * per-industry overlay supplies domain-specific authorities so a finance /
	 * legal / home-services tenant is not scored against a medical-only list.
	 * (Pre-v0.37 this list was hardcoded to medical authorities in two places,
	 * making the citation signal meaningless on every non-medical site — a
	 * single-vertical assumption that breaks as new non-clinic sites come on.)
	 * Operators extend via the cc_assistant_authority_hosts option + filter.
	 */
	public static function authority_hosts() {
		// `.gov` and `.edu` are US-only TLDs. A Canadian, UK, Australian or EU
		// tenant citing its own government, health service or university scored
		// zero citation density no matter how well sourced the page was. Found on
		// sids-ponds (Mississauga, Ontario) 2026-08-20, which cites mississauga.ca
		// and ontario.ca and reported citation_density 0.00 on every page.
		$hosts = array(
			'.gov', '.edu', 'who.int',
			// Canada
			'.gc.ca', 'canada.ca', 'ontario.ca', 'alberta.ca', 'quebec.ca',
			'gov.bc.ca', 'gov.mb.ca', 'novascotia.ca', 'saskatchewan.ca',
			// UK / IE
			'.gov.uk', '.nhs.uk', '.ac.uk', '.gov.ie',
			// AU / NZ
			'.gov.au', '.edu.au', '.govt.nz', '.ac.nz',
			// EU
			'europa.eu',
		);

		$overlays = array(
			'healthcare' => array(
				'nih.gov', 'cdc.gov', 'fda.gov', 'medlineplus.gov', 'niddk.nih.gov',
				'pubmed.ncbi.nlm.nih.gov', 'cms.gov',
				'nejm.org', 'jamanetwork.com', 'thelancet.com', 'bmj.com', 'plos.org', 'nature.com',
				'mayoclinic.org', 'clevelandclinic.org', 'hopkinsmedicine.org', 'uchicagomedicine.org',
				'aanp.org', 'aanpcert.org', 'aacn.org', 'aafp.org',
				'aad.org', 'asds.net', 'aafprs.org',
				'endocrine.org', 'obesitymedicine.org', 'aace.com',
				'aap.org', 'healthychildren.org', 'acep.org', 'ena.org', 'stroke.org',
				'heart.org', 'acog.org', 'aaos.org', 'ama-assn.org',
				'rheumatology.org', 'gastro.org', 'asge.org', 'auanet.org',
				'aaaai.org', 'aabb.org', 'asaging.org', 'acponline.org',
				'cancer.org', 'lung.org', 'kidney.org', 'diabetes.org', 'alz.org', 'arthritis.org',
			),
			'financial_services' => array(
				'sec.gov', 'irs.gov', 'consumerfinance.gov', 'federalreserve.gov', 'fdic.gov',
				'treasury.gov', 'ftc.gov', 'finra.org', 'sipc.org', 'investor.gov',
				'cfainstitute.org', 'aicpa.org',
			),
			'legal_practice' => array(
				'uscourts.gov', 'supremecourt.gov', 'congress.gov', 'law.cornell.edu',
				'americanbar.org', 'justia.com', 'findlaw.com', 'nolo.com',
			),
			// Retail / product sites cite standards bodies, safety regulators and
			// consumer-protection agencies rather than medical journals. Without
			// this overlay an ecommerce tenant fell back to the .gov/.edu
			// catchalls alone and could never register citation density.
			'ecommerce' => array(
				'energystar.gov', 'epa.gov', 'ftc.gov', 'cpsc.gov', 'nist.gov',
				'astm.org', 'ansi.org', 'iso.org', 'ul.com', 'csagroup.org',
				'nrcan.gc.ca', 'competitionbureau.gc.ca', 'ccohs.ca',
				'consumerreports.org',
			),
			'home_services' => array(
				'epa.gov', 'osha.gov', 'energy.gov', 'energystar.gov', 'nfpa.org',
				'ul.com', 'ashrae.org', 'nahb.org', 'iccsafe.org',
			),
		);

		// Load the industry profile explicitly. The class_exists guard below
		// was written as a soft-dependency, but on request paths that never
		// happen to load class-industry-profile.php it fails SILENTLY: $slug
		// stays '', no overlay merges, and every vertical-specific authority
		// (healthychildren.org, aap.org, mayoclinic.org, ...) vanishes from
		// the list while the .gov/.edu catchalls keep working. Diagnosed on
		// erofirving 2026-07-28: post 2453's live healthychildren.org citation
		// scored density 0.00 for weeks while 852's medlineplus.GOV citation
		// counted fine — the exact signature of the overlay silently missing.
		if ( ! class_exists( 'CC_Assistant_Industry_Profile' )
			&& defined( 'CC_ASSISTANT_DIR' )
			&& file_exists( CC_ASSISTANT_DIR . 'includes/class-industry-profile.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
		}

		$slug = '';
		if ( class_exists( 'CC_Assistant_Industry_Profile' )
			&& method_exists( 'CC_Assistant_Industry_Profile', 'industry_slug' ) ) {
			$slug = (string) CC_Assistant_Industry_Profile::industry_slug();
		}
		if ( isset( $overlays[ $slug ] ) ) {
			$hosts = array_merge( $hosts, $overlays[ $slug ] );
		}
		// Unknown / general / unsupported vertical: keep only the universal
		// catchalls and rely on the operator option below — never force medical
		// authorities onto a non-medical tenant.

		$extra = get_option( 'cc_assistant_authority_hosts', array() );
		if ( is_array( $extra ) ) {
			$hosts = array_merge( $hosts, $extra );
		}
		$hosts = apply_filters( 'cc_assistant_authority_hosts', $hosts, $slug );

		return array_values( array_unique( array_filter( array_map( 'strtolower', array_map( 'strval', $hosts ) ) ) ) );
	}

	public static function eeat_coverage_audit( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$permalink = (string) get_permalink( $post_id );
		if ( empty( $permalink ) ) {
			return new WP_Error( 'no_permalink', 'Post has no permalink.' );
		}
		$fetched = self::fetch_rendered_html_for_audit( $permalink );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}
		$html = (string) $fetched['body'];

		$body_text  = wp_strip_all_tags( $html );
		// v0.51.5: a byline is EITHER "reviewed/written by Firstname Lastname" OR a
		// team-style attribution ("Medically reviewed by the X Nursing Team") — the
		// old person-name-only pattern scored every team byline as missing (eeat 0).
		$has_byline = (bool) preg_match( '/\b(?:by|written by|reviewed by|medically reviewed by|author[:\s]|por|escrito por|revisado por|redactado por)[\s:]*\p{Lu}[\p{L}\.\-]+\s+\p{Lu}[\p{L}\.\-]+/u', $body_text )
			|| (bool) preg_match( '/\b(?:medically\s+reviewed\s+by|reviewed\s+by|written\s+by|revisado\s+(?:m.?dicamente\s+)?por|escrito\s+por)[\s:]+(?:the\s+|el\s+|la\s+)?[^.<>{}\r\n]{0,80}?\b(?:team|equipo|staff)\b/iu', $body_text );

		$schema = array(
			'has_person'          => false,
			'has_sameas'          => false,
			'has_linkedin_sameas' => false,
			'has_credential'      => false,
			'person_names'        => array(),
			'has_org_reviewer'    => false,
			'org_reviewer_names'  => array(),
		);
		if ( preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>([\s\S]*?)</script>#i', $html, $jm ) ) {
			foreach ( $jm[1] as $jsonld ) {
				$decoded = json_decode( trim( $jsonld ), true );
				if ( is_array( $decoded ) ) {
					self::eeat_scan_jsonld_node( $decoded, $schema );
				}
			}
		}

		$word_count = max( 1, str_word_count( $body_text ) );
		// Curated authority hosts: matches anywhere in the URL host.
		// `.gov` and `.edu` are catchalls; the rest are major medical-society
		// .orgs, peer-reviewed publishers, and academic medical centers. Kept
		// curated rather than open `.org` matching so Wikipedia / random non-
		// authoritative .orgs don't false-positive citation density.
		// Expanded 0.27.1 — added AANP, AACN, AAFP, AAD, Endocrine, ACOG, Mayo,
		// Cleveland Clinic, Hopkins, and disease-specific .orgs (cancer.org etc).
		// Industry-aware authority list (universal .gov/.edu + per-vertical
		// overlay + operator option). Single source of truth so finance / legal /
		// home-services tenants aren't scored against medical-only authorities.
		$auth_hosts = self::authority_hosts();
		$citation_count = 0;
		$cited_hosts    = array();
		if ( preg_match_all( '#<a\b[^>]+href=["\']([^"\']+)["\']#i', $html, $am ) ) {
			foreach ( $am[1] as $href ) {
				if ( ! preg_match( '#^https?://([^/]+)#i', $href, $h ) ) {
					continue;
				}
				$host = strtolower( $h[1] );
				foreach ( $auth_hosts as $ah ) {
					if ( false !== strpos( $host, $ah ) ) {
						$citation_count++;
						$cited_hosts[ $host ] = true;
						break;
					}
				}
			}
		}
		$density_per_1k = ( $citation_count / max( 1, $word_count ) ) * 1000;
		$meets_density  = $density_per_1k >= 1.0;

		$issues = array();
		if ( ! $has_byline ) {
			$issues[] = array( 'code' => 'eeat_no_visible_byline', 'severity' => 'high', 'message' => 'No visible byline detected in rendered body. Every YMYL page must name the clinician/author inline (proxies the leaked authorReputationScore + isAuthor signals).' );
		}
		if ( ! $schema['has_person'] && ! empty( $schema['has_org_reviewer'] ) ) {
			$issues[] = array( 'code' => 'eeat_org_reviewer_only', 'severity' => 'low', 'message' => 'Reviewed by an organisation rather than a named person. Acceptable where clinician consent is unavailable, but a named Person with hasCredential + sameAs is the stronger signal.' );
		} elseif ( ! $schema['has_person'] ) {
			$issues[] = array( 'code' => 'eeat_no_schema_person', 'severity' => 'high', 'message' => 'No schema Person entity in JSON-LD. Add one with name + jobTitle + hasCredential + sameAs (LinkedIn at minimum).' );
		}
		if ( $schema['has_person'] && ! $schema['has_linkedin_sameas'] ) {
			$issues[] = array( 'code' => 'eeat_no_linkedin_sameas', 'severity' => 'medium', 'message' => 'Schema Person present but no LinkedIn sameAs. LinkedIn is the cleanest external authority proxy for authorReputationScore.' );
		}
		if ( $schema['has_person'] && ! $schema['has_credential'] ) {
			$issues[] = array( 'code' => 'eeat_no_credential', 'severity' => 'medium', 'message' => 'Schema Person present but no hasCredential property. Name the credential (FNP-C, MSN, DO, MD) so Google can disambiguate the entity.' );
		}
		if ( ! $meets_density ) {
			$issues[] = array( 'code' => 'eeat_low_citation_density', 'severity' => 'medium', 'message' => sprintf( 'Authority citation density is %.2f per 1000 words (target: ≥1). Add .gov / .edu / NEJM / CDC / NIH inline anchors.', $density_per_1k ) );
		}

		$verdict = 'pass';
		foreach ( $issues as $i ) {
			if ( 'high' === $i['severity'] ) { $verdict = 'fail'; break; }
			if ( 'medium' === $i['severity'] ) { $verdict = 'warn'; }
		}

		return array(
			'post_id'   => $post_id,
			'page_url'  => $permalink,
			'word_count' => (int) $word_count,
			'byline'    => array( 'visible' => (bool) $has_byline ),
			'schema'    => $schema,
			'citations' => array(
				'count'          => (int) $citation_count,
				'density_per_1k' => round( (float) $density_per_1k, 2 ),
				'meets_target'   => (bool) $meets_density,
				'hosts'          => array_keys( $cited_hosts ),
			),
			'verdict'   => $verdict,
			'issues'    => $issues,
		);
	}

	/**
	 * Recursive JSON-LD walker for eeat_coverage_audit. Handles @graph
	 * containers and nested Person entities (e.g. inside MedicalProcedure
	 * performer or Review author).
	 */
	private static function eeat_scan_jsonld_node( $node, &$findings ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		$type = isset( $node['@type'] ) ? $node['@type'] : null;
		$is_person = false;
		if ( is_array( $type ) ) {
			$is_person = in_array( 'Person', $type, true );
		} elseif ( is_string( $type ) ) {
			$is_person = ( 'Person' === $type );
		}
		if ( $is_person ) {
			$findings['has_person'] = true;
			if ( isset( $node['name'] ) ) {
				$findings['person_names'][] = (string) $node['name'];
			}
			if ( isset( $node['hasCredential'] ) ) {
				$findings['has_credential'] = true;
			}
			if ( isset( $node['sameAs'] ) ) {
				$findings['has_sameas'] = true;
				$sa = is_array( $node['sameAs'] ) ? $node['sameAs'] : array( $node['sameAs'] );
				foreach ( $sa as $url ) {
					if ( is_string( $url ) && false !== stripos( $url, 'linkedin.com' ) ) {
						$findings['has_linkedin_sameas'] = true;
						break;
					}
				}
			}
		}
		// v0.76.12: an organisational reviewer (reviewedBy / reviewer pointing at an
		// Organization-like node) is a legitimate YMYL accountability signal on sites
		// that cannot publish a named clinician. Credited at the same base weight as a
		// Person entity, but it never earns the sameAs / credential bonuses, so an
		// org-reviewed page still caps around 15/25 rather than 25/25.
		foreach ( array( 'reviewedBy', 'reviewer' ) as $review_key ) {
			if ( ! isset( $node[ $review_key ] ) || ! is_array( $node[ $review_key ] ) ) {
				continue;
			}
			$reviewers = isset( $node[ $review_key ]['@type'] ) ? array( $node[ $review_key ] ) : $node[ $review_key ];
			foreach ( $reviewers as $reviewer ) {
				if ( ! is_array( $reviewer ) || ! isset( $reviewer['@type'] ) ) {
					continue;
				}
				$rtypes = is_array( $reviewer['@type'] ) ? $reviewer['@type'] : array( $reviewer['@type'] );
				foreach ( $rtypes as $rtype ) {
					if ( is_string( $rtype ) && preg_match( '/(Organization|Hospital|EmergencyService|MedicalClinic|MedicalBusiness|Corporation|LocalBusiness)$/i', $rtype ) ) {
						$findings['has_org_reviewer'] = true;
						if ( isset( $reviewer['name'] ) ) {
							$findings['org_reviewer_names'][] = (string) $reviewer['name'];
						}
						break 2;
					}
				}
			}
		}
		foreach ( $node as $v ) {
			if ( is_array( $v ) ) {
				self::eeat_scan_jsonld_node( $v, $findings );
			}
		}
	}

	/**
	 * schema_parity_check (P0): JSON-LD entity tokens must appear in rendered
	 * DOM. Google's structured-data policy explicitly forbids marking up
	 * content not visible on the page — violation can trigger manual action.
	 *
	 * Extracts text properties from every JSON-LD block (name, description,
	 * headline, performer.name, areaServed, foundingDate, hasCredential.name,
	 * etc.) and confirms each substantive token is present in the rendered
	 * body text. URLs, IDs, dates, and short tokens are excluded to avoid
	 * false positives.
	 */
	public static function schema_parity_check( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$permalink = (string) get_permalink( $post_id );
		if ( empty( $permalink ) ) {
			return new WP_Error( 'no_permalink', 'Post has no permalink.' );
		}
		$fetched = self::fetch_rendered_html_for_audit( $permalink );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}
		$html       = (string) $fetched['body'];
		$body_text  = mb_strtolower( wp_strip_all_tags( $html ) );
		$body_norm  = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $body_text );
		$body_norm  = preg_replace( '/\s+/', ' ', (string) $body_norm );

		// Collect every JSON-LD block.
		$blocks = array();
		if ( preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>([\s\S]*?)</script>#i', $html, $jm ) ) {
			foreach ( $jm[1] as $jsonld ) {
				$decoded = json_decode( trim( $jsonld ), true );
				if ( is_array( $decoded ) ) {
					$blocks[] = $decoded;
				}
			}
		}

		$entities_checked = 0;
		$tokens_checked   = 0;
		$tokens_missing   = array();
		$properties_to_check = array(
			'name', 'description', 'headline', 'alternativeHeadline', 'about',
			'articleBody', 'text', 'reviewBody', 'caption', 'transcript',
			'specialty', 'medicalSpecialty', 'preparation', 'howPerformed',
			'followup', 'indication',
		);

		foreach ( $blocks as $block ) {
			self::schema_parity_walk( $block, $body_norm, $properties_to_check, $entities_checked, $tokens_checked, $tokens_missing );
		}

		$issues = array();
		foreach ( $tokens_missing as $miss ) {
			$issues[] = array(
				'code'     => 'schema_not_in_dom',
				'severity' => 'high',
				'message'  => sprintf(
					'Schema property "%s" on @type=%s contains value "%s" that is NOT visible in the rendered body. Google\'s structured-data policy forbids marking up hidden content.',
					$miss['property'],
					$miss['type'],
					$miss['snippet']
				),
			);
		}

		$verdict = empty( $issues ) ? 'pass' : 'fail';

		return array(
			'post_id'          => $post_id,
			'page_url'         => $permalink,
			'jsonld_blocks'    => count( $blocks ),
			'entities_checked' => (int) $entities_checked,
			'tokens_checked'   => (int) $tokens_checked,
			'tokens_missing'   => count( $tokens_missing ),
			'verdict'          => $verdict,
			'issues'           => $issues,
		);
	}

	/**
	 * Recursive JSON-LD walker for schema_parity_check. For each node with a
	 * known text property, splits the value into bigrams ≥6 chars and checks
	 * presence in the normalized body. Reports any bigram (3-word slice)
	 * missing in the body — single missing words can be inflection mismatch.
	 */
	private static function schema_parity_walk( $node, $body_norm, $props, &$entities_checked, &$tokens_checked, &$tokens_missing ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		$type = isset( $node['@type'] ) ? $node['@type'] : null;
		$type_label = is_array( $type ) ? implode( ',', $type ) : (string) $type;
		if ( ! empty( $type ) ) {
			$entities_checked++;
		}

		foreach ( $props as $prop ) {
			if ( ! isset( $node[ $prop ] ) ) {
				continue;
			}
			$raw = $node[ $prop ];
			if ( is_array( $raw ) ) {
				// Nested object — recurse into it instead of treating as text.
				if ( isset( $raw[0] ) ) {
					// Array of values
					foreach ( $raw as $sub ) {
						if ( is_string( $sub ) ) {
							self::schema_parity_check_token( $sub, $prop, $type_label, $body_norm, $tokens_checked, $tokens_missing );
						}
					}
				}
				continue;
			}
			if ( ! is_string( $raw ) ) {
				continue;
			}
			self::schema_parity_check_token( $raw, $prop, $type_label, $body_norm, $tokens_checked, $tokens_missing );
		}

		foreach ( $node as $v ) {
			if ( is_array( $v ) ) {
				self::schema_parity_walk( $v, $body_norm, $props, $entities_checked, $tokens_checked, $tokens_missing );
			}
		}
	}

	/**
	 * Tokenize a JSON-LD string value into 3-word slices and confirm at least
	 * one slice appears in the body. Skips URLs, very short values, and
	 * pure dates/numbers which would false-positive against narrative prose.
	 */
	private static function schema_parity_check_token( $value, $prop, $type, $body_norm, &$tokens_checked, &$tokens_missing ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return;
		}
		if ( preg_match( '#^https?://#i', $value ) ) {
			return;
		}
		if ( strlen( $value ) < 8 ) {
			return;
		}
		// Pure dates / numeric IDs
		if ( preg_match( '/^[0-9\-\:\sT\.\+Z]+$/', $value ) ) {
			return;
		}
		$norm = mb_strtolower( wp_strip_all_tags( $value ) );
		$norm = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $norm );
		$norm = trim( preg_replace( '/\s+/', ' ', (string) $norm ) );
		if ( '' === $norm ) {
			return;
		}
		$words = explode( ' ', $norm );
		if ( count( $words ) < 3 ) {
			// Single token — require a single substring match
			$tokens_checked++;
			if ( false === strpos( $body_norm, $norm ) ) {
				$tokens_missing[] = array( 'property' => $prop, 'type' => $type, 'snippet' => mb_substr( $value, 0, 80 ) );
			}
			return;
		}
		// 3-word window: if ANY window appears in body, treat as visible.
		$found = false;
		for ( $i = 0; $i <= count( $words ) - 3; $i++ ) {
			$slice = $words[ $i ] . ' ' . $words[ $i + 1 ] . ' ' . $words[ $i + 2 ];
			if ( false !== strpos( $body_norm, $slice ) ) {
				$found = true;
				break;
			}
		}
		$tokens_checked++;
		if ( ! $found ) {
			$tokens_missing[] = array( 'property' => $prop, 'type' => $type, 'snippet' => mb_substr( $value, 0, 80 ) );
		}
	}

	/**
	 * self_review_detection (P0): flag Review or AggregateRating nodes whose
	 * itemReviewed is the site's own Organization / LocalBusiness /
	 * MedicalClinic. Google explicitly forbids self-reviews: the page
	 * becomes ineligible for review rich results AND may trip a manual
	 * action ("misleading structured data").
	 *
	 * Heuristics: site domain match on @id / url, site_name match on name,
	 * or aggregateRating sitting as a top-level property on a self-business
	 * entity.
	 */
	public static function self_review_detection( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$permalink = (string) get_permalink( $post_id );
		if ( empty( $permalink ) ) {
			return new WP_Error( 'no_permalink', 'Post has no permalink.' );
		}
		$fetched = self::fetch_rendered_html_for_audit( $permalink );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}
		$html      = (string) $fetched['body'];
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$site_name = mb_strtolower( (string) get_bloginfo( 'name' ) );

		$blocks = array();
		if ( preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>([\s\S]*?)</script>#i', $html, $jm ) ) {
			foreach ( $jm[1] as $jsonld ) {
				$decoded = json_decode( trim( $jsonld ), true );
				if ( is_array( $decoded ) ) {
					$blocks[] = $decoded;
				}
			}
		}

		$findings = array();
		foreach ( $blocks as $block ) {
			self::self_review_walk( $block, $home_host, $site_name, $findings, null );
		}

		// Dedupe: walking the JSON-LD graph can surface the same self-review
		// finding via two paths (e.g. once as a direct property of the self
		// business, then again when recursion descends into that property
		// and finds the AggregateRating with current_parent still set).
		// Same (kind + entity_id) pair must only emit one issue.
		$seen     = array();
		$unique   = array();
		foreach ( $findings as $f ) {
			$key = $f['kind'] . '|' . ( isset( $f['entity_id'] ) ? $f['entity_id'] : '' ) . '|' . ( isset( $f['entity_type'] ) ? $f['entity_type'] : '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$unique[]     = $f;
		}
		$findings = $unique;

		$issues = array();
		foreach ( $findings as $f ) {
			$issues[] = array(
				'code'     => 'self_review_detected',
				'severity' => 'high',
				'message'  => sprintf(
					'Schema %s on entity @type=%s%s appears to be self-reviewed (own %s). Google forbids self-reviews — page is ineligible for review rich results and may trip a manual action.',
					$f['kind'],
					$f['entity_type'],
					! empty( $f['entity_id'] ) ? ' (@id=' . $f['entity_id'] . ')' : '',
					$f['match']
				),
			);
		}

		$verdict = empty( $issues ) ? 'pass' : 'fail';

		return array(
			'post_id'  => $post_id,
			'page_url' => $permalink,
			'verdict'  => $verdict,
			'issues'   => $issues,
			'findings' => $findings,
		);
	}

	/**
	 * Recursive walker that pairs each Review / AggregateRating with the
	 * entity it sits inside (or itemReviewed if separate). Self-review
	 * matched on @id/url containing home host OR name matching site title.
	 */
	private static function self_review_walk( $node, $home_host, $site_name, &$findings, $parent_business ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		$type     = isset( $node['@type'] ) ? $node['@type'] : null;
		$type_arr = is_array( $type ) ? $type : ( $type ? array( (string) $type ) : array() );

		$is_business = false;
		foreach ( $type_arr as $t ) {
			if ( in_array( $t, array( 'Organization', 'LocalBusiness', 'MedicalBusiness', 'MedicalClinic', 'Hospital', 'Dentist', 'Pharmacy', 'Physician', 'DaySpa', 'BeautySalon', 'Corporation' ), true ) ) {
				$is_business = true;
				break;
			}
		}

		$is_self = false;
		$matched_via = '';
		if ( $is_business ) {
			$id  = isset( $node['@id'] ) ? strtolower( (string) $node['@id'] ) : '';
			$url = isset( $node['url'] ) ? strtolower( (string) $node['url'] ) : '';
			$nm  = isset( $node['name'] ) ? mb_strtolower( (string) $node['name'] ) : '';
			if ( '' !== $home_host && ( false !== strpos( $id, $home_host ) || false !== strpos( $url, $home_host ) ) ) {
				$is_self     = true;
				$matched_via = 'home_host';
			} elseif ( '' !== $site_name && '' !== $nm && $nm === $site_name ) {
				$is_self     = true;
				$matched_via = 'site_name';
			}
		}

		// Pass self-ness down to children
		$current_parent = $parent_business;
		if ( $is_self ) {
			$current_parent = array(
				'type'    => implode( ',', $type_arr ),
				'id'      => isset( $node['@id'] ) ? (string) $node['@id'] : '',
				'matched' => $matched_via,
			);
		}

		// Detect Review/AggregateRating at this node or as a property
		$kinds = array();
		if ( in_array( 'Review', $type_arr, true ) ) {
			$kinds[] = 'Review';
		}
		if ( in_array( 'AggregateRating', $type_arr, true ) ) {
			$kinds[] = 'AggregateRating';
		}
		foreach ( $kinds as $kind ) {
			// itemReviewed in the node body
			$ir = isset( $node['itemReviewed'] ) ? $node['itemReviewed'] : null;
			$matched_self = false;
			$entity_type  = '';
			$entity_id    = '';
			if ( is_array( $ir ) ) {
				$ir_type     = isset( $ir['@type'] ) ? $ir['@type'] : '';
				$ir_type_arr = is_array( $ir_type ) ? $ir_type : ( $ir_type ? array( (string) $ir_type ) : array() );
				$ir_id       = isset( $ir['@id'] ) ? strtolower( (string) $ir['@id'] ) : '';
				$ir_url      = isset( $ir['url'] ) ? strtolower( (string) $ir['url'] ) : '';
				$ir_name     = isset( $ir['name'] ) ? mb_strtolower( (string) $ir['name'] ) : '';
				$entity_type = implode( ',', $ir_type_arr );
				$entity_id   = isset( $ir['@id'] ) ? (string) $ir['@id'] : '';
				if ( '' !== $home_host && ( false !== strpos( $ir_id, $home_host ) || false !== strpos( $ir_url, $home_host ) ) ) {
					$matched_self = true;
				} elseif ( '' !== $site_name && '' !== $ir_name && $ir_name === $site_name ) {
					$matched_self = true;
				}
			}
			// Fallback: if AggregateRating/Review is INSIDE a self-business node, it self-reviews implicitly
			if ( ! $matched_self && $current_parent && empty( $ir ) ) {
				$matched_self = true;
				$entity_type  = $current_parent['type'];
				$entity_id    = $current_parent['id'];
			}
			if ( $matched_self ) {
				$findings[] = array(
					'kind'        => $kind,
					'entity_type' => $entity_type,
					'entity_id'   => $entity_id,
					'match'       => 'Organization / LocalBusiness / Clinic',
				);
			}
		}

		// Property-style: aggregateRating attached directly to a self-business
		if ( $is_self && isset( $node['aggregateRating'] ) ) {
			$findings[] = array(
				'kind'        => 'AggregateRating',
				'entity_type' => implode( ',', $type_arr ),
				'entity_id'   => isset( $node['@id'] ) ? (string) $node['@id'] : '',
				'match'       => 'attached to self Organization',
			);
		}
		if ( $is_self && isset( $node['review'] ) ) {
			$findings[] = array(
				'kind'        => 'Review',
				'entity_type' => implode( ',', $type_arr ),
				'entity_id'   => isset( $node['@id'] ) ? (string) $node['@id'] : '',
				'match'       => 'attached to self Organization',
			);
		}

		// Recurse
		foreach ( $node as $v ) {
			if ( is_array( $v ) ) {
				self::self_review_walk( $v, $home_host, $site_name, $findings, $current_parent );
			}
		}
	}

	/**
	 * helpful_content_score (P1 #8): per-post 0-100 score against Google's
	 * 28-question Helpful Content self-assessment framework. Where a question
	 * is automatable, we score the proxy; where it requires subjective
	 * judgment or external knowledge, we surface it as a manual_review item.
	 *
	 * Cached in postmeta `_cc_helpful_content_score` + `_cc_helpful_content_score_at`
	 * so site_quality_score can aggregate without re-fetching every page.
	 *
	 * Scoring buckets (max 100):
	 *   - Depth          (0-25): word count, FAQ pattern, lists/tables, headings, images
	 *   - Originality    (0-20): authority citation density, unique data points, dedup
	 *   - E-E-A-T        (0-25): byline, schema Person, sameAs, hasCredential
	 *   - Quality        (0-15): start 15, subtract for clickbait, AI-tell, all-caps, !!!
	 *   - Freshness      (0-15): legit recent update vs hollow date bump (cc_edits log)
	 *
	 * Verdict bands: 80+ strong, 60-79 solid, 40-59 weak, <40 poor.
	 */
	public static function helpful_content_score( $post_id, $args = array() ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$permalink = (string) get_permalink( $post_id );
		if ( empty( $permalink ) ) {
			return new WP_Error( 'no_permalink', 'Post has no permalink.' );
		}

		$use_cache = ! ( isset( $args['refresh'] ) && (bool) $args['refresh'] );
		if ( $use_cache ) {
			$cached_score = get_post_meta( $post_id, '_cc_helpful_content_score', true );
			$cached_at    = get_post_meta( $post_id, '_cc_helpful_content_score_at', true );
			$cached_body  = get_post_meta( $post_id, '_cc_helpful_content_score_body', true );
			// The cached score is only comparable if it was produced by THIS
			// version of the scorer. Without this, changing the scoring rules
			// leaves every site serving numbers computed by the old code until
			// each post happens to be edited — and `refresh_stale` cannot rescue
			// it, because those entries are timestamped recently and therefore
			// look fresh. Observed on sids-ponds 2026-08-20: after shipping the
			// scoring-scope fixes, site_quality_score reported rescored=0 and
			// repeated the pre-fix verdict verbatim, which reads exactly like a
			// fix that did not work.
			$cached_ver = (string) get_post_meta( $post_id, '_cc_helpful_content_score_ver', true );
			$current_ver = defined( 'CC_ASSISTANT_VERSION' ) ? (string) CC_ASSISTANT_VERSION : '';
			if ( $cached_ver !== $current_ver ) {
				$cached_score = '';
			}
			// Cache hit ONLY if the post hasn't been modified since we scored it.
			// post_modified_gmt is bumped by every WP save (cc_edits, manual edits,
			// theme changes via tools, etc.). Without this guard, a post that gets
			// rewritten still returns its stale score for site_quality_score aggregation.
			$post_modified = (string) $post->post_modified_gmt;
			if ( '' !== $cached_score && '' !== $cached_at && is_array( $cached_body )
				&& '' !== $post_modified
				&& strcmp( (string) $cached_at, $post_modified ) >= 0
			) {
				$cached_body['cached']    = true;
				$cached_body['cached_at'] = (string) $cached_at;
				return $cached_body;
			}
		}

		$fetched = self::fetch_rendered_html_for_audit( $permalink );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}
		// $html_full keeps the WHOLE document because JSON-LD (Rank Math and
		// friends) is emitted in <head>, outside any content container — the
		// E-E-A-T bucket still has to see it.
		//
		// Everything that scores WRITING, however, must see only the post's own
		// body. Scoring the full page charged every post for site-wide chrome:
		// on sids-ponds the header promo bar ("...on orders $100 or more!") and
		// the popup ("Shop Now!" x2) produced an identical 3-exclamation penalty
		// on /outdoor-lighting-2/, /contact/ AND /delivery/, which no edit to any
		// page could ever clear. It also dragged site_quality_score to a false
		// verdict=fail. Diagnosed 2026-08-20.
		$html_full = (string) $fetched['body'];
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		$html = method_exists( 'CC_Assistant_Pre_Publish', 'isolate_main_content' )
			? CC_Assistant_Pre_Publish::isolate_main_content( $html_full )
			: $html_full;
		// Never score an empty body: if isolation finds no usable region the page
		// would otherwise read as 0 words and be flagged thin.
		if ( '' === trim( wp_strip_all_tags( (string) $html ) ) ) {
			$html = $html_full;
		}
		$body_text = wp_strip_all_tags( $html );
		$word_count = max( 1, str_word_count( $body_text ) );

		$breakdown = array();
		$issues    = array();

		// ===== DEPTH (25) =====
		// Word-count thresholds are page-type-aware. A clean conversion-focused
		// homepage is naturally 600-1000 words; scoring it against the 1500-word
		// bar used for long-form blog posts forces padding that hurts UX. Detect
		// the site's front page and use a lower band.
		$is_homepage = ( (int) get_option( 'page_on_front' ) === $post_id ) && ( 'page' === (string) $post->post_type );
		$depth = 0;
		if ( $is_homepage ) {
			if ( $word_count >= 800 ) {
				$depth += 10;
			} elseif ( $word_count >= 500 ) {
				$depth += 7;
			} elseif ( $word_count >= 300 ) {
				$depth += 3;
			} else {
				$issues[] = array( 'code' => 'depth_thin_content', 'severity' => 'high', 'message' => sprintf( 'Word count is %d. Even for a homepage, below 300 words is thin coverage.', $word_count ) );
			}
		} elseif ( $word_count >= 1500 ) {
			$depth += 10;
		} elseif ( $word_count >= 800 ) {
			$depth += 7;
		} elseif ( $word_count >= 400 ) {
			$depth += 3;
		} else {
			$issues[] = array( 'code' => 'depth_thin_content', 'severity' => 'high', 'message' => sprintf( 'Word count is %d. Below 400 words rarely registers as comprehensive coverage; Google\'s 2nd helpful-content question asks for "substantial, complete, comprehensive" treatment.', $word_count ) );
		}
		$q_h2 = preg_match_all( '/<h2\b[^>]*>([\s\S]*?)<\/h2>/i', $html, $h2m ) ? $h2m[1] : array();
		$q_h3 = preg_match_all( '/<h3\b[^>]*>([\s\S]*?)<\/h3>/i', $html, $h3m ) ? $h3m[1] : array();
		$question_h2 = 0;
		// Count question-shaped headings at H2 *and* H3. The FAQ-card pattern this
		// plugin itself builds — and Divi/Elementor accordion FAQs generally — put
		// each question in an H3 beneath a single "FAQs" H2, so an H2-only count
		// reported question_h2s = 0 on pages carrying seven real FAQ questions.
		foreach ( array_merge( $q_h2, $q_h3 ) as $h ) {
			if ( false !== strpos( trim( wp_strip_all_tags( $h ) ), '?' ) ) {
				$question_h2++;
			}
		}
		if ( $question_h2 >= 3 ) {
			$depth += 5;
		}
		if ( preg_match( '/<table\b/i', $html ) || preg_match_all( '/<ul\b/i', $html ) >= 2 || preg_match_all( '/<ol\b/i', $html ) >= 1 ) {
			$depth += 5;
		}
		$h2_total = count( $q_h2 );
		if ( $h2_total >= 3 ) {
			$depth += 3;
		}
		$img_count = preg_match_all( '/<img\b/i', $html );
		if ( $img_count >= 2 ) {
			$depth += 2;
		}
		$depth = min( 25, $depth );
		$breakdown['depth'] = array( 'score' => $depth, 'max' => 25, 'word_count' => $word_count, 'h2_count' => $h2_total, 'question_h2s' => $question_h2, 'images' => (int) $img_count, 'is_homepage' => (bool) $is_homepage );

		// ===== ORIGINALITY (20) =====
		$originality = 0;
		// Citation density (reuse eeat hosts list)
		// Curated authority hosts: matches anywhere in the URL host.
		// `.gov` and `.edu` are catchalls; the rest are major medical-society
		// .orgs, peer-reviewed publishers, and academic medical centers. Kept
		// curated rather than open `.org` matching so Wikipedia / random non-
		// authoritative .orgs don't false-positive citation density.
		// Expanded 0.27.1 — added AANP, AACN, AAFP, AAD, Endocrine, ACOG, Mayo,
		// Cleveland Clinic, Hopkins, and disease-specific .orgs (cancer.org etc).
		// Industry-aware authority list (universal .gov/.edu + per-vertical
		// overlay + operator option). Single source of truth so finance / legal /
		// home-services tenants aren't scored against medical-only authorities.
		$auth_hosts = self::authority_hosts();
		$citation_count = 0;
		if ( preg_match_all( '#<a\b[^>]+href=["\']([^"\']+)["\']#i', $html, $am ) ) {
			foreach ( $am[1] as $href ) {
				if ( ! preg_match( '#^https?://([^/]+)#i', $href, $h ) ) { continue; }
				$host = strtolower( $h[1] );
				foreach ( $auth_hosts as $ah ) {
					if ( false !== strpos( $host, $ah ) ) { $citation_count++; break; }
				}
			}
		}
		$density = ( $citation_count / max( 1, $word_count ) ) * 1000;
		if ( $density >= 1.0 ) {
			$originality += 10;
		} elseif ( $density >= 0.5 ) {
			$originality += 5;
		} else {
			$issues[] = array( 'code' => 'originality_low_citation_density', 'severity' => 'medium', 'message' => sprintf( 'Citation density is %.2f authority links per 1000 words (target ≥1). Google\'s 1st HC question asks for "original information, reporting, research, or analysis" — anchor every claim to a cited source.', $density ) );
		}
		// Unique data points (digits + percentages + dates)
		$digit_count = preg_match_all( '/\b\d{1,4}(?:[,.]\d+)?(?:\s?%|\s?(?:mg|mmHg|ml|kg|lb|cm|km|ft|years|days|hours|min))?\b/i', $body_text );
		$digit_density = ( $digit_count / max( 1, $word_count ) ) * 1000;
		if ( $digit_density >= 5 ) {
			$originality += 5;
		} elseif ( $digit_density >= 2 ) {
			$originality += 2;
		} else {
			$issues[] = array( 'code' => 'originality_no_unique_data', 'severity' => 'medium', 'message' => sprintf( 'Only %d numeric data points across %d words. Google rewards "insightful analysis or interesting information that is beyond the obvious" — add specific stats, prevalences, dosages, dates, or measurements.', (int) $digit_count, $word_count ) );
		}
		// Intra-site dedup proxy: word_count >= 400 and no other post with same title prefix
		if ( $word_count >= 400 ) {
			$originality += 5;
		}
		$originality = min( 20, $originality );
		$breakdown['originality'] = array( 'score' => $originality, 'max' => 20, 'citation_density_per_1k' => round( $density, 2 ), 'numeric_data_points' => (int) $digit_count );

		// ===== E-E-A-T (25) — reuse eeat_coverage logic against the same HTML =====
		$body_norm = mb_strtolower( $body_text );
		// v0.51.5: a byline is EITHER "reviewed/written by Firstname Lastname" OR a
		// team-style attribution ("Medically reviewed by the X Nursing Team") — the
		// old person-name-only pattern scored every team byline as missing (eeat 0).
		$has_byline = (bool) preg_match( '/\b(?:by|written by|reviewed by|medically reviewed by|author[:\s]|por|escrito por|revisado por|redactado por)[\s:]*\p{Lu}[\p{L}\.\-]+\s+\p{Lu}[\p{L}\.\-]+/u', $body_text )
			|| (bool) preg_match( '/\b(?:medically\s+reviewed\s+by|reviewed\s+by|written\s+by|revisado\s+(?:m.?dicamente\s+)?por|escrito\s+por)[\s:]+(?:the\s+|el\s+|la\s+)?[^.<>{}\r\n]{0,80}?\b(?:team|equipo|staff)\b/iu', $body_text );
		$schema = array( 'has_person' => false, 'has_sameas' => false, 'has_linkedin_sameas' => false, 'has_credential' => false, 'person_names' => array(), 'has_org_reviewer' => false, 'org_reviewer_names' => array() );
		// $html_full, not $html: JSON-LD lives in <head>, which isolate_main_content
		// deliberately drops. Using the isolated body here would zero out every
		// schema-derived E-E-A-T signal.
		if ( preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>([\s\S]*?)</script>#i', $html_full, $jm2 ) ) {
			foreach ( $jm2[1] as $jsonld ) {
				$decoded = json_decode( trim( $jsonld ), true );
				if ( is_array( $decoded ) ) {
					self::eeat_scan_jsonld_node( $decoded, $schema );
				}
			}
		}
		$eeat = 0;
		if ( $has_byline ) { $eeat += 10; }
		else { $issues[] = array( 'code' => 'eeat_no_visible_byline', 'severity' => 'high', 'message' => 'No visible author byline (proxy for the leaked authorReputationScore + isAuthor signals).' ); }
		if ( $schema['has_person'] ) {
			$eeat += 5;
		} elseif ( ! empty( $schema['has_org_reviewer'] ) ) {
			$eeat += 5;
			$issues[] = array( 'code' => 'eeat_org_reviewer_only', 'severity' => 'low', 'message' => 'Reviewed by an organisation rather than a named person. This is credited, but E-E-A-T caps near 15/25 until a named clinician with hasCredential and sameAs can be published.' );
		} else {
			$issues[] = array( 'code' => 'eeat_no_schema_person', 'severity' => 'high', 'message' => 'No schema Person entity in JSON-LD.' );
		}
		if ( $schema['has_sameas'] ) { $eeat += 3; }
		if ( $schema['has_linkedin_sameas'] ) { $eeat += 4; }
		else if ( $schema['has_person'] ) { $issues[] = array( 'code' => 'eeat_no_linkedin_sameas', 'severity' => 'medium', 'message' => 'Schema Person present but no LinkedIn sameAs. LinkedIn is the cleanest authorReputationScore proxy.' ); }
		if ( $schema['has_credential'] ) { $eeat += 3; }
		$eeat = min( 25, $eeat );
		$breakdown['eeat'] = array( 'score' => $eeat, 'max' => 25, 'has_byline' => $has_byline, 'schema' => $schema );

		// ===== QUALITY (15) — subtractive =====
		$quality = 15;
		$clickbait_patterns = array(
			'/\b(shocking|unbelievable|you won.?t believe|one weird trick|the truth about|doctors hate|secret no one tells)\b/i',
			'/\b(this changed everything|game.?chang(er|ing))\b/i',
		);
		foreach ( $clickbait_patterns as $rx ) {
			if ( preg_match( $rx, $body_text ) ) {
				$quality -= 5;
				$issues[] = array( 'code' => 'quality_clickbait', 'severity' => 'medium', 'message' => 'Clickbait language detected. Google\'s HC framework asks "Does the main heading or page title avoid exaggerating or being shocking in nature?"' );
				break;
			}
		}
		$ai_tell_patterns = array(
			// "landscape" was previously a BARE word here, so every ordinary use of
			// it counted as an AI-tell. On a landscape-supply company that is the
			// core noun of the business: /outdoor-lighting-2/ was penalised 4 times
			// for "landscape lighting" (a product category) and the footer tagline
			// "Pond, garden, and landscape supply". Only the filler constructions
			// are tells. Fixed 2026-08-20.
			'/\b(delve into|navigating the complexities|in today.?s fast.?paced|(?:digital|evolving|changing|shifting|competitive|ever.?changing|modern) landscape|leverage(?: the| our)? expertise|elevat(?:e|ing) your|unlock the (?:power|secrets|potential)|tapestry of)\b/i',
			'/\b(let.?s dive in|let.?s explore|in conclusion[,:])\b/i',
		);
		$ai_tells = 0;
		foreach ( $ai_tell_patterns as $rx ) {
			if ( preg_match_all( $rx, $body_text, $matches ) ) {
				$ai_tells += count( $matches[0] );
			}
		}
		if ( $ai_tells >= 1 ) {
			$penalty = min( 8, 2 * $ai_tells );
			$quality -= $penalty;
			$issues[] = array( 'code' => 'quality_ai_tells', 'severity' => 'medium', 'message' => sprintf( '%d AI-tell phrase%s detected. Reads as machine-produced filler; Google\'s scaled-content-abuse policy applies whether AI or not.', (int) $ai_tells, $ai_tells === 1 ? '' : 's' ) );
		}
		$exclaim = substr_count( $body_text, '!' );
		if ( $exclaim >= 5 ) {
			$quality -= 3;
			$issues[] = array( 'code' => 'quality_exclamation_overuse', 'severity' => 'low', 'message' => sprintf( '%d exclamation marks in body. Drops perceived quality on YMYL content.', $exclaim ) );
		}
		$quality = max( 0, $quality );
		$breakdown['quality'] = array( 'score' => $quality, 'max' => 15, 'ai_tells' => $ai_tells, 'exclamations' => $exclaim );

		// ===== FRESHNESS HONESTY (15) =====
		$freshness = 5; // neutral baseline
		$now           = time();
		$modified_age  = max( 0, $now - strtotime( $post->post_modified_gmt . ' UTC' ) );
		$modified_days = (int) ( $modified_age / DAY_IN_SECONDS );
		// Did we record a content edit in cc_edits within 180 days?
		global $wpdb;
		$edits_table = $wpdb->prefix . 'cc_edits';
		$recent_edits = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$edits_table}
			 WHERE post_id = %d
			   AND applied_at >= %s
			   AND change_type IN ('post_content_update','elementor_widget_update','elementor_widget_add','elementor_widget_remove','accordion_item_add','accordion_item_remove','container_add','rewrite')",
			$post_id,
			gmdate( 'Y-m-d H:i:s', $now - ( 180 * DAY_IN_SECONDS ) )
		) );
		if ( $modified_days <= 180 ) {
			if ( $recent_edits > 0 ) {
				$freshness = 15;
			} else {
				$freshness = 5;
				$issues[] = array( 'code' => 'freshness_suspicious_modified', 'severity' => 'low', 'message' => sprintf( 'post_modified is %d days old but no content edit recorded in cc_edits within 180 days. Google penalizes hollow date bumps without substantive content change.', $modified_days ) );
			}
		} elseif ( $modified_days >= 365 ) {
			$freshness = 10;
		}
		$breakdown['freshness'] = array( 'score' => $freshness, 'max' => 15, 'modified_days_ago' => $modified_days, 'recent_content_edits' => $recent_edits );

		$total = $depth + $originality + $eeat + $quality + $freshness;
		if ( $total >= 80 ) {
			$band = 'strong';
		} elseif ( $total >= 60 ) {
			$band = 'solid';
		} elseif ( $total >= 40 ) {
			$band = 'weak';
		} else {
			$band = 'poor';
		}

		$result = array(
			'post_id'   => $post_id,
			'page_url'  => $permalink,
			'score'     => (int) $total,
			'band'      => $band,
			'breakdown' => $breakdown,
			'issues'    => $issues,
			'cached'    => false,
		);

		// Cache for site_quality_score aggregation
		update_post_meta( $post_id, '_cc_helpful_content_score', (int) $total );
		update_post_meta( $post_id, '_cc_helpful_content_score_at', gmdate( 'Y-m-d H:i:s' ) );
		update_post_meta( $post_id, '_cc_helpful_content_score_body', $result );
		// Stamp which scorer produced this so a later version knows to discard it.
		update_post_meta( $post_id, '_cc_helpful_content_score_ver', defined( 'CC_ASSISTANT_VERSION' ) ? CC_ASSISTANT_VERSION : '' );

		return $result;
	}

	/**
	 * site_quality_score (P1 #9): domain-level aggregate from cached
	 * helpful_content_score postmeta. The March 2026 reweighted helpful-
	 * content signal is SITE-WIDE — one thin section drags the whole
	 * domain. This is the audit that surfaces which sections.
	 *
	 * Reads cached postmeta only by default (call helpful_content_score
	 * on individual posts to populate). Pass refresh_stale=true to
	 * re-score posts whose cached value is older than the freshness arg.
	 */
	public static function site_quality_score( $args = array() ) {
		$post_types    = isset( $args['post_types'] ) ? (array) $args['post_types'] : array( 'post', 'page' );
		$refresh_stale = ! empty( $args['refresh_stale'] );
		$freshness_days = isset( $args['freshness_days'] ) ? max( 1, min( 90, (int) $args['freshness_days'] ) ) : 14;
		$bottom_n      = isset( $args['bottom_n'] ) ? max( 1, min( 100, (int) $args['bottom_n'] ) ) : 10;
		$rescore_cap   = isset( $args['rescore_cap'] ) ? max( 0, min( 50, (int) $args['rescore_cap'] ) ) : 10;

		$published = get_posts( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		if ( empty( $published ) ) {
			return array(
				'post_count' => 0,
				'message'    => 'No published posts found in the requested post types.',
			);
		}

		// v0.69: functional pages are not CONTENT and must not drag the
		// helpful-content verdict. Field failure: a store's rollup returned
		// "fail — 42% weak" where the entire weak list was Cart, Checkout,
		// My Account, Wishlist, and legal boilerplate — pages an essay-style
		// scorer will always misjudge and Google does not judge as content.
		// Identification is functional, not name-guessing, where possible:
		// WooCommerce's own designated pages + WP's privacy page, with a
		// narrow slug fallback for legal/utility pages Woo doesn't track.
		$utility_ids = array();
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount', 'shop', 'terms' ) as $wc_page ) {
				$wc_id = (int) wc_get_page_id( $wc_page );
				if ( $wc_id > 0 ) {
					$utility_ids[ $wc_id ] = true;
				}
			}
		}
		$privacy_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		if ( $privacy_id > 0 ) {
			$utility_ids[ $privacy_id ] = true;
		}
		// The blog index is an archive, not an article. WP designates it.
		$posts_page_id = (int) get_option( 'page_for_posts', 0 );
		if ( $posts_page_id > 0 ) {
			$utility_ids[ $posts_page_id ] = true;
		}
		// v0.76.3: the v0.69 list was written against a store (cart, checkout,
		// legal boilerplate). On a medical site the same CLASS of page is much
		// wider: a HIPAA notice, an accessibility statement, a medical
		// disclaimer and a billing-disclosure page are mandated boilerplate
		// that can never cite a study or carry information gain, yet they were
		// scored as content and aggregated into the site verdict.
		// Measured across the portfolio 2026-08-27: one site's entire bottom-N
		// and another's whole bottom-5 were pages of this class, and the false
		// weak-share pushed a third site to verdict=warn. Trailing -N is
		// allowed because WP suffixes a slug on collision (blog-2).
		$utility_slug_re = '/^('
			. 'cart|checkout|my-account|wishlist|thank-you|order-received|shop'
			. '|contact(-us)?|careers?|blog|book(-an?)?-appointment'
			. '|refund(_|-)?returns?|shipping-policy'
			. '|privacy-policy|cookie-policy(-[a-z]{2})?|terms(-and-conditions|-of-use|-of-service)?'
			. '|(hipaa-)?notice-of-privacy-practices|hipaa-notice'
			. '|(web-)?accessibility-statement|medical-disclaimer|disclaimer'
			. '|billing-disclosures|surprise-billing-rights|editorial-policy'
			. '|letter-of-protection'
			. ')(-\d+)?$/';
		$excluded    = array();
		$utility_map = array();
		foreach ( $published as $pid ) {
			$pid = (int) $pid;
			if ( isset( $utility_ids[ $pid ] ) ) {
				$utility_map[ $pid ] = 'designated_page';
				continue;
			}
			$p = get_post( $pid );
			if ( $p && preg_match( $utility_slug_re, (string) $p->post_name ) ) {
				$utility_map[ $pid ] = 'utility_slug';
			}
		}
		// A translation of a utility page is the same page in another language.
		// Without this a bilingual site is penalised twice for translating its
		// own legal boilerplate, and the Spanish slugs would have to be
		// enumerated by hand. Inheriting from the linked source keeps this
		// language-agnostic.
		// Load it explicitly. class-multilingual is only required inside other
		// methods of this class, so a bare class_exists() check here was false
		// on most requests and silently skipped the whole inheritance pass —
		// erofwhiterock kept scoring Carreras, Articulos and Politica de
		// Cookies as content while their English parents were excluded.
		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';
		if ( CC_Assistant_Multilingual::is_active() ) {
			foreach ( array_keys( $utility_map ) as $src_id ) {
				foreach ( CC_Assistant_Multilingual::translations_of( $src_id ) as $tx_id ) {
					$tx_id = (int) $tx_id;
					if ( $tx_id > 0 && ! isset( $utility_map[ $tx_id ] ) ) {
						$utility_map[ $tx_id ] = 'utility_translation';
					}
				}
			}
		}
		$published = array_values( array_filter( $published, function ( $pid ) use ( $utility_map, &$excluded ) {
			if ( ! isset( $utility_map[ (int) $pid ] ) ) {
				return true;
			}
			$excluded[] = array(
				'post_id' => (int) $pid,
				'title'   => get_the_title( (int) $pid ),
				'reason'  => $utility_map[ (int) $pid ],
			);
			return false;
		} ) );
		if ( empty( $published ) ) {
			return array(
				'post_count'       => 0,
				'excluded_utility' => $excluded,
				'message'          => 'All published posts in the requested post types are functional/utility pages; nothing to score as content.',
			);
		}

		$stale_cutoff      = gmdate( 'Y-m-d H:i:s', time() - ( $freshness_days * DAY_IN_SECONDS ) );
		$rescored          = 0;
		$rows              = array();
		$scorer_version    = defined( 'CC_ASSISTANT_VERSION' ) ? (string) CC_ASSISTANT_VERSION : '';
		foreach ( $published as $pid ) {
			$score     = get_post_meta( $pid, '_cc_helpful_content_score', true );
			$scored_at = get_post_meta( $pid, '_cc_helpful_content_score_at', true );
			// Stale if: never scored OR last scored before the cutoff OR post
			// was edited after the score was written (helpful_content_score
			// itself revalidates against post_modified_gmt; we mirror that
			// gate here so the aggregate doesn't carry stale cached scores).
			$is_stale = ( '' === $scored_at ) || ( strcmp( (string) $scored_at, $stale_cutoff ) < 0 );
			if ( ! $is_stale ) {
				$p = get_post( (int) $pid );
				if ( $p && strcmp( (string) $p->post_modified_gmt, (string) $scored_at ) > 0 ) {
					$is_stale = true;
				}
			}
			// Mirror the SCORER-VERSION gate as well. A score produced by an
			// older scorer is not comparable to one produced by this build no
			// matter how recently it was written. Missing this mirror is what
			// made a correctly shipped scoring fix look like a no-op: the
			// aggregate reported rescored=0 and repeated the pre-fix verdict
			// verbatim, because every entry was timestamped "today" and so
			// passed the freshness and post_modified gates. sids-ponds,
			// 2026-08-20. If you add a gate to helpful_content_score, add it
			// here too — this loop reads the cached postmeta directly and does
			// NOT go through that function's cache path.
			$row_ver = (string) get_post_meta( $pid, '_cc_helpful_content_score_ver', true );
			if ( $row_ver !== $scorer_version ) {
				$is_stale = true;
			}

			if ( ( '' === $score || $is_stale ) && $refresh_stale && $rescored < $rescore_cap ) {
				$result = self::helpful_content_score( (int) $pid, array( 'refresh' => true ) );
				if ( ! is_wp_error( $result ) ) {
					$score = (int) $result['score'];
					$rescored++;
				}
			}

			if ( '' === $score ) {
				$rows[] = array( 'post_id' => (int) $pid, 'score' => null, 'scored_at' => null );
				continue;
			}
			$rows[] = array(
				'post_id'   => (int) $pid,
				'score'     => (int) $score,
				'scored_at' => $scored_at ?: null,
			);
		}

		$scored = array_filter( $rows, function ( $r ) { return null !== $r['score']; } );
		$unscored_count = count( $rows ) - count( $scored );
		// How many rows still carry a score from a DIFFERENT scorer build. Any
		// value above zero means this verdict mixes scorer generations and is
		// not directly comparable — re-run with refresh_stale=true.
		$mixed_scorer_rows = 0;
		foreach ( $rows as $r ) {
			if ( null === $r['score'] ) { continue; }
			if ( (string) get_post_meta( (int) $r['post_id'], '_cc_helpful_content_score_ver', true ) !== $scorer_version ) {
				$mixed_scorer_rows++;
			}
		}

		// Distribution
		$dist = array( 'strong' => 0, 'solid' => 0, 'weak' => 0, 'poor' => 0 );
		$scores_only = array();
		foreach ( $scored as $r ) {
			$s = (int) $r['score'];
			$scores_only[] = $s;
			if ( $s >= 80 ) { $dist['strong']++; }
			elseif ( $s >= 60 ) { $dist['solid']++; }
			elseif ( $s >= 40 ) { $dist['weak']++; }
			else { $dist['poor']++; }
		}
		$mean = $scores_only ? round( array_sum( $scores_only ) / count( $scores_only ), 1 ) : null;
		sort( $scores_only );
		$median = null;
		if ( $scores_only ) {
			$n = count( $scores_only );
			$median = $n % 2 ? $scores_only[ ( $n - 1 ) / 2 ] : round( ( $scores_only[ $n / 2 - 1 ] + $scores_only[ $n / 2 ] ) / 2, 1 );
		}

		// Bottom-N (rewrite-or-remove candidates)
		usort( $scored, function ( $a, $b ) { return (int) $a['score'] <=> (int) $b['score']; } );
		$bottom = array_slice( $scored, 0, $bottom_n );
		foreach ( $bottom as &$b ) {
			$p = get_post( $b['post_id'] );
			if ( $p ) {
				$b['post_title'] = $p->post_title;
				$b['page_url']   = get_permalink( $b['post_id'] );
				$b['edit_url']   = get_edit_post_link( $b['post_id'], 'raw' );
			}
		}
		unset( $b );

		// Site-wide verdict
		$total = count( $scored ) + $unscored_count;
		$poor_share = $total > 0 ? round( $dist['poor'] / $total, 3 ) : 0;
		$weak_share = $total > 0 ? round( ( $dist['poor'] + $dist['weak'] ) / $total, 3 ) : 0;
		if ( $weak_share >= 0.30 ) {
			$verdict = 'fail';
			$verdict_message = sprintf( '%.0f%% of scored posts are weak or poor. Site-wide helpful-content signal is at risk under March 2026 reweighting; rewrite or remove the bottom-quartile content.', $weak_share * 100 );
		} elseif ( $weak_share >= 0.15 ) {
			$verdict = 'warn';
			$verdict_message = sprintf( '%.0f%% of scored posts are weak or poor. Audit the bottom-N for rewrite candidates.', $weak_share * 100 );
		} else {
			$verdict = 'pass';
			$verdict_message = 'Quality distribution is healthy. Continue maintaining citation density and E-E-A-T signals on new content.';
		}

		return array(
			'post_count'      => count( $rows ),
			'scored_count'    => count( $scored ),
			'unscored_count'  => $unscored_count,
			'rescored'        => $rescored,
			// >0 means this verdict MIXES scorer generations and is not directly
			// comparable; re-run with refresh_stale=true before acting on it.
			'scored_by_other_build' => $mixed_scorer_rows,
			'scorer_version'  => $scorer_version,
			'mean'            => $mean,
			'median'          => $median,
			'distribution'    => $dist,
			'poor_share'      => $poor_share,
			'weak_share'      => $weak_share,
			'verdict'         => $verdict,
			'verdict_message' => $verdict_message,
			'bottom_n'        => $bottom,
			// v0.69: functional pages (cart/checkout/account/legal) sit outside
			// the helpful-content lens entirely; listed so the exclusion is
			// visible rather than silent.
			'excluded_utility' => $excluded,
		);
	}

	/**
	 * external_originality_check (P1 #10): the plugin can't make external
	 * SERP / competitor-body fetches without a paid API, so this tool
	 * returns a structured prompt for the AI assistant to drive via its
	 * own WebFetch + cosine analysis. Mirrors the keyword_research pattern.
	 *
	 * The prompt includes: target keyword (or derived from post title +
	 * top GSC queries), our page's key claims + numeric data points + cited
	 * sources, plus instructions to fetch top 3 organic competitors and
	 * compare uniqueness.
	 */
	public static function external_originality_check( $post_id, $args = array() ) {
        require_once CC_ASSISTANT_DIR . 'includes/class-content-evidence.php';
        $source = CC_Assistant_Content_Evidence::snapshot( $post_id );
        if ( is_wp_error( $source ) ) { return $source; }
        return array(
            'post_id' => (int) $post_id, 'page_url' => $source['url'], 'keyword' => $args['keyword'] ?? $source['title'],
            'source' => $source, 'assessment' => 'not_compared', 'originality_verified' => false,
            'prompt' => 'Use content_research with explicit comparable URLs. Compare reader tasks and supported claims. Record what was fetched, preserve uncertainty, and explain a useful contribution. No numeric similarity or formatting quota certifies novelty. Research examples and expert input when needed; never invent facts.',
            'next_step' => 'content_research'
        );
    }

	/* =====================================================================
	 * Keyword research from existing GSC data + Claude-driven WebFetch.
	 * No paid APIs. Returns a Claude-ready prompt that walks the agent
	 * through fetching People Also Ask + related searches via WebFetch
	 * and matching them against our GSC data + existing posts.
	 * ================================================================== */
	public static function keyword_research( $args = array() ) {
		global $wpdb;
		$seed = trim( (string) ( $args['seed'] ?? '' ) );
		if ( '' === $seed ) {
			return new WP_Error( 'seed_required', 'seed keyword is required.' );
		}
		$days  = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;
		$lc    = mb_strtolower( $seed );

		// Hot-cache: identical seed + window often called repeatedly during
		// a Claude session. The LIKE scan is unavoidable (no fulltext index
		// on the query column), so we cache the rendered result rather than
		// pay the scan cost again. 30-min TTL — long enough for a session,
		// short enough that fresh GSC syncs surface within reason.
		$ck = 'cc_kw_research_v85_' . md5( $lc . '|' . $days );
		$cached = get_transient( $ck );
		if ( false !== $cached ) {
			return $cached;
		}

		$cutoff = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		$gsc    = $wpdb->prefix . 'cc_gsc_queries';

		// SCAN_ROW_CAP bounds the LIKE scan. Pre-aggregate at the (query)
		// level using a CAP'd inner SELECT so even on million-row tables
		// the worst case is bounded. We grab the top SCAN_ROW_CAP rows by
		// impressions for the seed match and aggregate from there.
		$SCAN_ROW_CAP = 5000;

		$related_queries = array();
		$near_misses     = array();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $gsc ) ) );
		if ( $exists === $gsc ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT query,
				        SUM(impressions) AS impressions,
				        SUM(clicks)      AS clicks,
				        SUM(position*impressions)/NULLIF(SUM(impressions),0) AS avg_position
				 FROM (
				   SELECT query, impressions, clicks, position
				   FROM {$gsc}
				   WHERE date >= %s AND query LIKE %s
				   ORDER BY impressions DESC
				   LIMIT %d
				 ) t
				 GROUP BY query
				 ORDER BY impressions DESC
				 LIMIT 50",
				$cutoff,
				'%' . $wpdb->esc_like( $lc ) . '%',
				$SCAN_ROW_CAP
			) );
			foreach ( (array) $rows as $r ) {
				$related_queries[] = array(
					'query'        => $r->query,
					'impressions'  => (int) $r->impressions,
					'clicks'       => (int) $r->clicks,
					'avg_position' => round( (float) $r->avg_position, 2 ),
				);
				// Near-miss derivation in the same loop — saves the second LIKE pass.
				$pos = (float) $r->avg_position;
				if ( $pos >= 11 && $pos <= 30 && (int) $r->clicks <= 1 ) {
					$near_misses[] = array(
						'query'        => $r->query,
						'impressions'  => (int) $r->impressions,
						'avg_position' => round( $pos, 2 ),
					);
				}
			}
			// Cap visible near_misses at 20.
			$near_misses = array_slice( $near_misses, 0, 20 );
			$related_queries = array_slice( $related_queries, 0, 30 );
		}

		// Existing post titles that match the seed — for cannibalization risk.
		$existing_posts = get_posts( array(
			's'              => $seed,
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'fields'         => 'ids',
		) );
		$post_briefs = array();
		foreach ( (array) $existing_posts as $pid ) {
			$post_briefs[] = array(
				'post_id'  => (int) $pid,
				'title'    => get_the_title( (int) $pid ),
				'permalink' => get_permalink( (int) $pid ),
			);
		}

		$claude_prompt = sprintf(
            "Explore in-niche topics for %s using plan_blog_content. Start from selected services, reader questions and practical tasks, even when GSC has no observations. Compare existing content before choosing a distinct new blog or a focused update. Use available external research tools when useful; blocked searches remain unknown. Do not equate ranking presence with complete coverage or absence with no demand.",
            $seed
        );

		$payload = array(
			'seed'             => $seed,
			'window_days'      => $days,
			'related_queries'  => $related_queries,
			'near_misses'      => $near_misses,
			'existing_posts'   => $post_briefs,
			'claude_prompt'    => $claude_prompt,
			'next_step_hint'   => 'Use plan_blog_content for niche-led expansion independent of GSC gaps. External query research is optional and must report its source and coverage.',
		);
		set_transient( $ck, $payload, 30 * MINUTE_IN_SECONDS );
		return $payload;
	}

	private static function format_hints_for_intent( $intent ) {
		$map = array(
			'informational' => array(
				'min_words' => 0,
				'max_words' => 0,
				'structure' => array( 'concise introduction appropriate to the reader task', 'H2 sections answering distinct sub-questions', 'use lists where order matters', 'answer useful remaining questions where appropriate' ),
				'schema'    => 'Article when appropriate; assess eligibility separately before adding other schema',
			),
			'commercial'    => array(
				'min_words' => 0,
				'max_words' => 0,
				'structure' => array( 'comparison table or matrix', 'pros/cons per option', 'use case per option', 'editorial verdict in conclusion' ),
				'schema'    => 'Article + Review or ItemList',
			),
			'transactional' => array(
				'min_words' => 0,
				'max_words' => 0,
				'structure' => array( 'CTA above the fold', 'short benefits list', 'social proof', 'second CTA at end' ),
				'schema'    => 'Service or Product (whichever fits)',
			),
			'navigational'  => array(
				'min_words' => 0,
				'max_words' => 0,
				'structure' => array( 'clear hero', 'short orientation paragraph', 'list of destinations / sub-pages' ),
				'schema'    => 'WebPage with about/mentions',
			),
			'local'         => array(
				'min_words' => 0,
				'max_words' => 0,
				'structure' => array( 'address + hours + phone above fold', 'service area description', 'directions or map embed', 'local trust signals' ),
				'schema'    => 'LocalBusiness (with type-specific subclass)',
			),
		);
		$hint = isset( $map[ $intent ] ) ? $map[ $intent ] : $map['informational'];
        $hint['min_words'] = null; $hint['max_words'] = null;
        $hint['length_policy'] = 'Use the length needed for the reader task; no ranking word-count target.';
        $hint['assessment_type'] = 'optional_editorial_format_hints';
        return $hint;
	}
}

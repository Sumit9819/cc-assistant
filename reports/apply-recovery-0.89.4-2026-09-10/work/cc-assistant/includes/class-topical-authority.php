<?php
/**
 * Topical authority — combines the TF-IDF similarity engine with GSC
 * performance per page so you can see which clusters perform vs underperform,
 * spot underdeveloped topics (lots of related queries, no strong page) and
 * candidates to merge or remove (multiple thin pages competing on the same
 * query set).
 *
 * Pure read layer. Heavy lifting happens in CC_Assistant_Similarity::find_clusters
 * (which already runs through cron when invoked from the find_topic_clusters
 * tool) — this class joins those clusters with GSC aggregates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Topical_Authority {

	const STATUS_HUB        = 'hub';        // strong, well-linked, high impressions
	const STATUS_GROW       = 'grow';       // promising, room to expand
	const STATUS_THIN       = 'thin';       // low impressions, low coverage
	const STATUS_REDUNDANT  = 'redundant';  // multiple pages competing
	const STATUS_OBSERVE = 'observe';
	const STATUS_UNKNOWN = 'unknown';
	const STATUS_PRUNE      = 'prune';      // dead weight, candidate for removal/redirect

	public static function analyze( $args = array() ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-similarity.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';

		$post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
		$threshold = isset( $args['threshold'] ) ? max( 0.4, min( 0.9, (float) $args['threshold'] ) ) : 0.55;
		$limit     = isset( $args['limit'] ) ? max( 10, min( 1000, (int) $args['limit'] ) ) : 200;
		$days      = isset( $args['days'] ) ? max( 14, min( 90, (int) $args['days'] ) ) : 28;

		@set_time_limit( 300 );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$cluster_result = CC_Assistant_Similarity::find_clusters( $query->posts, $threshold );
		$clusters       = isset( $cluster_result['clusters'] ) ? $cluster_result['clusters'] : array();

		// Augment each cluster with GSC performance + linking + status.
		$out = array();
		foreach ( $clusters as $cluster ) {
			$post_ids = isset( $cluster['post_ids'] ) ? array_map( 'intval', $cluster['post_ids'] ) : array();
			if ( empty( $post_ids ) ) {
				continue;
			}
			$gsc      = self::cluster_gsc( $post_ids, $days );
			$linking  = self::cluster_linking( $post_ids );
			$status   = self::classify_cluster( $gsc, $linking, count( $post_ids ) );

			$out[] = array(
				'post_ids'         => $post_ids,
				'size'             => count( $post_ids ),
				'avg_similarity'   => isset( $cluster['avg_similarity'] ) ? $cluster['avg_similarity'] : null,
				'top_similarity'   => isset( $cluster['top_similarity'] ) ? $cluster['top_similarity'] : null,
				'titles'           => self::titles( $post_ids ),
				'gsc'              => $gsc,
				'linking'          => $linking,
				'status'           => $status,
				'recommendation'   => self::recommendation( $status, $gsc, $linking, count( $post_ids ) ),
			);
		}

		// Now find topics with high query volume but no strong ranking page.
		$content_gaps = self::content_gaps( $days );

		usort(
			$out,
			function ( $a, $b ) {
				return $b['gsc']['impressions'] <=> $a['gsc']['impressions'];
			}
		);

		return array(
			'post_type'    => $post_type,
			'threshold'    => $threshold,
			'days'         => $days,
			'considered'   => isset( $cluster_result['considered'] ) ? $cluster_result['considered'] : 0,
			'cluster_count' => count( $out ),
			'clusters'     => $out,
			'content_gaps' => $content_gaps,
            'gap_scope' => 'GSC query candidates only; empty results do not mean no content opportunities.',
            'growth_next_step' => 'Call plan_blog_content to explore supported services, reader questions and new niche topics without requiring GSC history.',
            'assessment_type' => 'relatedness_and_observed_visibility',
            'automatic_consolidation_eligible' => false,
		);
	}

	private static function cluster_gsc( $post_ids, $days ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_gsc_queries';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
            return array( 'data_state' => 'unavailable', 'impressions' => null, 'clicks' => null, 'avg_position' => null, 'unique_queries' => null, 'top_queries' => array() );
        }

		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		$hashes = array();
		foreach ( $post_ids as $id ) {
			$page = (string) get_permalink( (int) $id );
			if ( $page ) {
				$hashes[] = sha1( $page );
			}
		}
		if ( empty( $hashes ) ) {
			return array(
				'impressions' => 0,
				'clicks'      => 0,
				'avg_position' => null,
				'unique_queries' => 0,
				'top_queries' => array(),
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );

		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(impressions) AS imp, SUM(clicks) AS clk,
					CASE WHEN SUM(impressions) > 0 THEN SUM(position*impressions)/SUM(impressions) ELSE NULL END AS pos,
					COUNT(DISTINCT query_hash) AS uq
				FROM {$table}
				WHERE date >= %s AND page_hash IN ($placeholders)",
				array_merge( array( $cutoff ), $hashes )
			),
			ARRAY_A
		);

		$top_queries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
					SUM(position*impressions)/SUM(impressions) AS position
				FROM {$table}
				WHERE date >= %s AND page_hash IN ($placeholders)
				GROUP BY query
				ORDER BY impressions DESC
				LIMIT 10",
				array_merge( array( $cutoff ), $hashes )
			),
			ARRAY_A
		);

		return array(
			'data_state' => isset( $totals['imp'] ) ? 'observed' : 'unavailable',
			'impressions'    => isset( $totals['imp'] ) ? (int) $totals['imp'] : null,
			'clicks'         => (int) ( isset( $totals['clk'] ) ? $totals['clk'] : 0 ),
			'avg_position'   => isset( $totals['pos'] ) && null !== $totals['pos'] ? round( (float) $totals['pos'], 2 ) : null,
			'unique_queries' => (int) ( isset( $totals['uq'] ) ? $totals['uq'] : 0 ),
			'top_queries'    => is_array( $top_queries ) ? array_map(
				function ( $r ) {
					return array(
						'query'       => $r['query'],
						'impressions' => (int) $r['impressions'],
						'clicks'      => (int) $r['clicks'],
						'position'    => round( (float) $r['position'], 2 ),
					);
				},
				$top_queries
			) : array(),
		);
	}

	private static function cluster_linking( $post_ids ) {
		$inbound = 0;
		$outbound = 0;
		foreach ( $post_ids as $id ) {
			$inbound  += CC_Assistant_Internal_Links::inbound_count( (int) $id );
			$outbound += CC_Assistant_Internal_Links::outbound_count( (int) $id );
		}
		return array(
			'total_inbound'  => $inbound,
			'total_outbound' => $outbound,
			'avg_inbound'    => count( $post_ids ) > 0 ? round( $inbound / count( $post_ids ), 1 ) : 0,
		);
	}

	private static function classify_cluster( $gsc, $linking, $size ) {
        if ( ! isset( $gsc['impressions'] ) || 'unavailable' === ( $gsc['data_state'] ?? '' ) ) { return self::STATUS_UNKNOWN; }
        // Similarity and cluster size do not establish duplication, harm or removal eligibility.
        if ( (int) $gsc['impressions'] >= 200 && (float) ( $linking['avg_inbound'] ?? 0 ) >= 3 ) { return self::STATUS_HUB; }
        return self::STATUS_OBSERVE;
    }

	private static function recommendation( $status, $gsc, $linking, $size ) {
        if ( self::STATUS_UNKNOWN === $status ) { return 'Analytics are unavailable. Inspect reader tasks and existing content; missing GSC data is not evidence of no value or no new-topic opportunities.'; }
        if ( self::STATUS_HUB === $status ) { return 'Observed impressions and inbound links. Preserve useful pages; review distinct reader tasks before proposing changes.'; }
        return 'Related-content candidate only. Inspect each page and distinguish keep/link, differentiate, refresh or consolidation. Similarity, low impressions and cluster size do not justify merging, noindexing or removal. Use content_decision for the evidence checklist.';
    }

	private static function titles( $post_ids ) {
		$out = array();
		foreach ( $post_ids as $id ) {
			$post = get_post( (int) $id );
			if ( $post ) {
				$out[] = array(
					'post_id'   => (int) $id,
					'title'     => $post->post_title,
					'permalink' => get_permalink( (int) $id ),
					'edit_url'  => get_edit_post_link( (int) $id, 'raw' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Stop words removed when tokenizing a GSC query for the gap check.
	 * Includes articles, prepositions, near-me chrome, and decision words
	 * that don't carry topical signal.
	 */
	private static $gap_stop_words = array(
		'the', 'a', 'an', 'and', 'or', 'but', 'for', 'of', 'in', 'on', 'at', 'to', 'from',
		'is', 'are', 'was', 'were', 'be', 'been', 'being', 'do', 'does', 'did',
		'how', 'what', 'why', 'when', 'where', 'who', 'which',
		'near', 'me', 'my', 'by', 'with', 'without', 'about',
		'best', 'top', 'good', 'better', 'great',
		'cost', 'price', 'cheap', 'affordable',
	);

	/**
	 * Split a query into significant tokens for the gap-coverage check.
	 * Drops stop words and tokens shorter than 3 chars. Lowercases everything.
	 */
	private static function significant_tokens( $query ) {
		$query = strtolower( trim( (string) $query ) );
		$raw   = preg_split( '/\W+/u', $query );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$tokens = array();
		foreach ( $raw as $w ) {
			$w = trim( $w );
			if ( strlen( $w ) < 3 ) {
				continue;
			}
			if ( in_array( $w, self::$gap_stop_words, true ) ) {
				continue;
			}
			if ( ! in_array( $w, $tokens, true ) ) {
				$tokens[] = $w;
			}
		}
		return $tokens;
	}

	/**
	 * Content gaps — queries with high impressions where no post on this site
	 * gives sufficient topical coverage. A query is considered "covered" when
	 * at least one post contains ≥60% of the query's significant tokens (with
	 * a floor of 2 token matches) in title or body. The historical bug here
	 * was a verbatim LIKE check for the entire phrase, which falsely flagged
	 * topically-covered queries as gaps because real titles paraphrase the
	 * user's exact wording (e.g. post "I'm So Tired: How IV Therapy Can Help
	 * Fight Chronic Fatigue" was reported as not covering "iv drip for fatigue"
	 * because the substring never appears literally).
	 *
	 * For each gap candidate we also return token_matches so the caller can
	 * see which post came closest — turns false-negative gaps into actionable
	 * "optimize existing post X" recommendations.
	 */
	private static function content_gaps( $days ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'cc_gsc_queries';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return array(); }

		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query,
					SUM(impressions) AS impressions,
					SUM(clicks) AS clicks,
					SUM(position*impressions)/SUM(impressions) AS position
				FROM {$table}
				WHERE date >= %s
				GROUP BY query
				HAVING impressions >= 100 AND position > 12
				ORDER BY impressions DESC
				LIMIT 100",
				$cutoff
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );

		$out = array();
		foreach ( $rows as $r ) {
			$tokens = self::significant_tokens( $r['query'] );
			if ( empty( $tokens ) ) {
				// Nothing to score; safer to skip than to claim a false gap.
				continue;
			}

			$required = min( count( $tokens ), max( 2, (int) ceil( count( $tokens ) * 0.6 ) ) );

			// Per-token search for posts that contain that token in title or body.
			// Capped at 50 posts per token to keep this bounded — gap queries
			// that match dozens of posts are obviously already well-covered.
			$post_token_counts = array();
			foreach ( $tokens as $tok ) {
				$like = '%' . $wpdb->esc_like( $tok ) . '%';
				$matching_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
						WHERE post_status='publish' AND post_type IN ($placeholders)
							AND (post_title LIKE %s OR post_content LIKE %s)
						LIMIT 50",
						array_merge( $allowed, array( $like, $like ) )
					)
				);
				foreach ( $matching_ids as $pid ) {
					$pid = (int) $pid;
					if ( ! isset( $post_token_counts[ $pid ] ) ) {
						$post_token_counts[ $pid ] = 0;
					}
					$post_token_counts[ $pid ]++;
				}
			}

			$best_post_id     = 0;
			$best_match_count = 0;
			$has_coverage     = false;
			foreach ( $post_token_counts as $pid => $cnt ) {
				if ( $cnt > $best_match_count ) {
					$best_match_count = $cnt;
					$best_post_id     = $pid;
				}
				if ( $cnt >= $required ) {
					$has_coverage = true;
				}
			}

			// Real gap — no post hits the threshold. Surface the best near-miss
			// so the caller can decide between "write new" and "optimize X".
			if ( ! $has_coverage ) {
				$recommendation = 'Lexical coverage candidate in stored title/body data. Inspect builder content and reader tasks before choosing a new post; this is not proof of missing coverage.';
				$best_post_meta = null;
				if ( $best_post_id > 0 && $best_match_count >= 1 ) {
					$best_post  = get_post( $best_post_id );
					$best_title = $best_post ? $best_post->post_title : '';
					$best_url   = (string) get_permalink( $best_post_id );
					$best_post_meta = array(
						'post_id'        => $best_post_id,
						'title'          => $best_title,
						'permalink'      => $best_url,
						'matched_tokens' => $best_match_count,
					);
					$recommendation = sprintf(
						'Partial coverage on post %d ("%s") matched %d/%d significant tokens (need %d). Consider optimizing that post before writing new content.',
						$best_post_id,
						$best_title,
						$best_match_count,
						count( $tokens ),
						$required
					);
				}

				$out[] = array(
					'query'         => $r['query'],
					'impressions'   => (int) $r['impressions'],
					'clicks'        => (int) $r['clicks'],
					'position'      => round( (float) $r['position'], 2 ),
					'tokens'        => $tokens,
					'tokens_needed' => $required,
					'best_match'    => $best_post_meta,
					'recommendation' => $recommendation,
				);
			}

			if ( count( $out ) >= 25 ) {
				break;
			}
		}
		return $out;
	}
}

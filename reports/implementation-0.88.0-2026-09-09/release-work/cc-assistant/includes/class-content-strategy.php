<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-content-evidence.php';
require_once __DIR__ . '/class-content-scope.php';

/** Niche-led planning. GSC is optional evidence, never a prerequisite for a new topic. */
class CC_Assistant_Content_Strategy {
	const PROFILE_OPTION = 'cc_assistant_content_scope';
	const POLICY_VERSION = 'content-strategy-4';

	public static function policy() {
		$contract = require CC_ASSISTANT_DIR . 'bin/agent-contract.php';
		return $contract['content_policy'];
	}

	public static function inventory( $limit = 200, $offset = 0 ) {
		$limit = max( 10, min( 500, (int) $limit ) ); $offset = max( 0, (int) $offset );
		$allowed = array_values( array_intersect( array( 'post','page' ), (array) get_option( 'cc_assistant_allowed_post_types', array( 'post','page' ) ) ) );
		if ( empty( $allowed ) ) { return array( 'pages' => array(), 'complete' => false, 'reason' => 'No supported content types are enabled.' ); }
		$q = new WP_Query( array( 'post_type' => $allowed, 'post_status' => array( 'publish','draft','pending','future' ),
			'has_password' => false, 'posts_per_page' => $limit, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC',
			'fields' => 'ids', 'no_found_rows' => false ) );
		$pages = array(); $failed = array();
		foreach ( $q->posts as $id ) {
			$source = CC_Assistant_Content_Evidence::snapshot( $id );
			if ( is_wp_error( $source ) ) { $failed[] = (int) $id; } else { $pages[] = $source; }
		}
		$total = (int) $q->found_posts;
		return array( 'pages' => $pages, 'scanned' => count( $q->posts ), 'total_available' => $total,
			'complete' => 0 === $offset && count( $q->posts ) >= $total && empty( $failed ), 'offset' => $offset,
			'next_offset' => $offset + count( $q->posts ) < $total ? $offset + count( $q->posts ) : null,
			'content_extraction_complete' => empty( $failed ) && empty( array_filter( $pages, static function ( $page ) { return 'stored_content' !== $page['coverage'] || $page['truncated']; } ) ),
            'rendered_verified' => false,
            'failed_post_ids' => $failed, 'scope' => 'Enabled posts/pages, published plus draft/pending/scheduled; password-protected/private content and other post types excluded.' );
	}

	public static function scope( $args = array(), $inventory = null ) {
		return CC_Assistant_Content_Scope::read( $args, $inventory );
	}

	/** Compatible query/page observations only; absent data stays explicitly unmeasured. */
	public static function demand( $days = 28 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_gsc_queries';
		$days = max( 7, min( 90, (int) $days ) );
		$out = array( 'state' => 'unavailable', 'days' => $days, 'rows' => array(),
			'limitation' => 'Top observed site queries only. No market search-volume estimate and no evidence of absence.' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return $out; }
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT query, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
			 SUM(position*impressions)/NULLIF(SUM(impressions),0) AS position
			 FROM {$table} WHERE date >= %s GROUP BY query ORDER BY impressions DESC, query ASC LIMIT 200",
			gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS ) ), ARRAY_A );
		if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) { return $out; }
		$out['state'] = empty( $rows ) ? 'no_observations' : 'observed_query_sample';
		$out['rows'] = $rows;
		$out['measurement_version'] = get_option( 'cc_assistant_gsc_measurement_version', 'legacy_unverified' );
		return $out;
	}

	public static function plan( $args = array() ) {
		$inventory = self::inventory( $args['scan_limit'] ?? 200, $args['offset'] ?? 0 );
		$setup = ! empty( $args['service_post_ids'] ) ? array( 'status' => 'request_scope_only' ) : CC_Assistant_Content_Scope::initialize( empty( $args['offset'] ) ? $inventory : null );
		if ( is_wp_error( $setup ) ) { return $setup; }
		$scope = self::scope( $args, $inventory );
		$demand = self::demand( $args['days'] ?? 28 );
		$result = self::assemble_plan( $scope, $inventory, $demand, $args );
		$result['scope_initialization'] = array( 'status' => $setup['status'] ?? 'unknown' );
		$result['scope_revision'] = $scope['scope_revision'];
		$result['scope_freshness'] = $scope['freshness'];
		return $result;
	}

	/** Pure planner, shared by REST, brief generation and regression fixtures. */
	public static function assemble_plan( $scope, $inventory, $demand, $args = array() ) {
		$templates = array(
			array( 'understand', 'Understanding %s: the questions that matter', 'Explain the service or topic accurately for someone encountering it for the first time.' ),
			array( 'prepare', 'What to ask before using %s', 'Help a reader prepare useful questions and find the verified information needed for their next step.' ),
			array( 'process', 'What to expect from %s', 'Explain a verified process, its practical steps and its limitations.' ),
			array( 'compare', 'How to evaluate options for %s', 'Explain appropriate comparison criteria with dated sources and supported tradeoffs.' ),
		);
		$seeds = array();
		foreach ( (array) ( $args['topics'] ?? array() ) as $topic ) {
			$topic = CC_Assistant_Content_Evidence::text( $topic, 220 );
			if ( '' !== $topic ) { $seeds[] = array( 'title' => $topic, 'task' => 'user_question', 'benefit' => 'Resolve the stated reader question with supported niche-specific information.', 'source' => 'user_topic_proposal' ); }
		}
		if ( empty( $seeds ) ) {
			foreach ( $scope['reader_questions'] as $question ) {
				if ( is_array( $question ) && ! empty( $question['question'] ) ) {
					$seeds[] = array( 'title' => $question['question'], 'anchor_id' => (int) ( $question['post_id'] ?? 0 ),
						'task' => 'reader_question', 'benefit' => 'Answer an explicitly recorded reader question.', 'source' => 'configured_reader_question' );
				}
			}
			// Round-robin tasks give each supported service a chance before adding more angles.
			foreach ( $templates as $template ) {
				foreach ( $scope['sources'] as $source ) {
					$seeds[] = array( 'title' => sprintf( $template[1], $source['title'] ), 'anchor_id' => $source['post_id'],
						'task' => $template[0], 'benefit' => $template[2], 'source' => 'service_reader_task' );
				}
			}
			foreach ( array_slice( $demand['rows'], 0, 30 ) as $row ) {
				$seeds[] = array( 'title' => $row['query'], 'task' => 'observed_query', 'benefit' => 'Investigate the observed query and its underlying reader need.', 'source' => 'gsc_observation' );
			}
		}
		$candidates = array(); $excluded = array(); $seen = array();
		foreach ( array_slice( $seeds, 0, 200 ) as $seed ) {
			$title = CC_Assistant_Content_Evidence::text( $seed['title'], 220 );
			$key = mb_strtolower( $title );
			if ( isset( $seen[$key] ) ) { continue; } $seen[$key] = true;
			$blocked = false;
			foreach ( $scope['excluded_topics'] as $term ) {
				$term = mb_strtolower( trim( (string) $term ) );
				if ( '' !== $term && preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $term, '/' ) . '(?![\p{L}\p{N}])/u', $key ) ) { $blocked = true; break; }
			}
			if ( $blocked ) { $excluded[] = array( 'title' => $title, 'reason' => 'explicit_scope_exclusion' ); continue; }
			$anchor = null; $best = 0;
			foreach ( $scope['sources'] as $source ) {
				$score = CC_Assistant_Content_Evidence::overlap( $title, $source['title'] . ' ' . mb_substr( $source['text'], 0, 1500 ) );
				if ( (int) ( $seed['anchor_id'] ?? 0 ) === $source['post_id'] ) { $score = 1; }
				if ( $score > $best ) { $best = $score; $anchor = $source; }
			}
			if ( ! $anchor || $best < 0.34 ) {
				$excluded[] = array( 'title' => $title, 'reason' => 'niche_relationship_not_established', 'next_step' => 'Supply a relevant published service/pillar source or refine the topic.' ); continue;
			}
			$matches = array();
			foreach ( $inventory['pages'] as $page ) {
				$score = CC_Assistant_Content_Evidence::overlap( $title, $page['title'] . ' ' . $page['text'] );
				if ( $score < 0.45 ) { continue; }
				$matches[] = array( 'post_id' => $page['post_id'], 'title' => $page['title'], 'url' => $page['url'],
					'post_status' => $page['post_status'], 'evidence_id' => $page['evidence_id'], 'lexical_overlap' => round( $score, 3 ),
					'coverage' => $page['coverage'], 'relationship' => $page['post_id'] === $anchor['post_id'] ? 'niche_anchor' : 'inspect_reader_task',
					'matched_excerpt' => mb_substr( $page['text'], 0, 700 ) );
			}
			usort( $matches, static function ( $a, $b ) { return ( $b['lexical_overlap'] <=> $a['lexical_overlap'] ) ?: ( $a['post_id'] <=> $b['post_id'] ); } );
			$signals = array();
			foreach ( $demand['rows'] as $row ) {
				if ( CC_Assistant_Content_Evidence::overlap( $title, $row['query'] ) >= 0.5 ) { $signals[] = $row; }
				if ( count( $signals ) >= 3 ) { break; }
			}
			$other_matches = array_filter( $matches, static function ( $match ) { return 'niche_anchor' !== $match['relationship']; } );
			$action = ! empty( $other_matches ) ? 'compare_existing_tasks' : 'develop_new_topic_brief';
			$candidates[] = array( 'candidate_id' => substr( hash( 'sha256', $anchor['evidence_id'] . $key . $seed['task'] ), 0, 24 ),
				'title' => $title, 'reader_audience' => $scope['audience'], 'reader_task' => $seed['task'], 'reader_benefit' => $seed['benefit'], 'discovery_source' => $seed['source'],
				'niche_anchor' => array( 'post_id' => $anchor['post_id'], 'title' => $anchor['title'], 'url' => $anchor['url'], 'evidence_id' => $anchor['evidence_id'], 'language' => $anchor['language'] ),
				'scope_status' => $scope['scope_status'], 'demand_state' => empty( $signals ) ? 'unmeasured_for_proposal' : 'related_queries_observed',
				'gsc_signals' => $signals, 'search_volume' => null, 'existing_content_candidates' => array_slice( $matches, 0, 5 ),
				'next_action' => $action, 'new_post_not_blocked_by_gsc' => true, 'publication_ready' => false,
				'original_value_brief' => array( 'existing_baseline' => 'Inspect matched content before claiming a gap.',
					'contribution_to_develop' => $seed['benefit'],
					'evidence_needed' => array( 'Current source-backed service facts', 'A useful example, process explanation, visual or answer to the stated task', 'Appropriate subject review for factual or regulated claims' ),
					'prohibited_shortcuts' => 'No invented research, services, prices, credentials or uniqueness. No automatic city/query variants or minimum word count.' ),
				'decision_note' => 'A related service page does not preclude a supporting blog. Decide from the actual reader task; lexical overlap alone neither proves duplication nor certifies a gap.' );
		}
		$limit = max( 1, min( 30, (int) ( $args['limit'] ?? 12 ) ) );
		$candidate_offset = max( 0, min( 200, (int) ( $args['candidate_offset'] ?? 0 ) ) );
		$inventory_summary = $inventory; unset( $inventory_summary['pages'] );
		return array( 'policy' => self::policy(), 'scope_status' => $scope['scope_status'], 'scope_next_step' => $scope['next_step'], 'reader_audience' => $scope['audience'],
			'generated_at_utc' => gmdate( 'c' ), 'inventory_coverage' => $inventory_summary, 'gsc_state' => $demand['state'],
			'gsc_measurement_version' => $demand['measurement_version'] ?? 'unknown',
			'candidate_count' => count( $candidates ), 'candidates' => array_slice( $candidates, $candidate_offset, $limit ),
			'candidate_offset' => $candidate_offset, 'next_candidate_offset' => $candidate_offset + $limit < count( $candidates ) ? $candidate_offset + $limit : null,
			'candidate_limit_reached' => count( $candidates ) > $candidate_offset + $limit, 'excluded_or_unresolved' => array_slice( $excluded, 0, 30 ),
			'conclusion' => empty( $scope['sources'] ) ? 'Scope needs source pages; opportunity is not assessed.' :
				( empty( $candidates ) ? 'No eligible proposal in this bounded sample. Expand supported reader questions or scope evidence; this is not proof of no opportunities.' :
				'Niche-based proposals are available independently of GSC gaps. Inspect coverage and develop a supported reader-value brief before drafting.' ) );
	}
}

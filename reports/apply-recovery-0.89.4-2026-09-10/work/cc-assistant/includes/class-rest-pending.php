<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy diff endpoint for the pending-changes inbox. Returns rendered diff HTML
 * for a single pending row. The inbox calls this on group expand instead of
 * rendering every row's diff at page load.
 *
 * Lives in its own file so it can be added without touching class-rest-api.php
 * (where parallel work happens).
 */
class CC_Assistant_REST_Pending {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/pending/(?P<id>\d+)/diff',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_diff' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/snapshots/(?P<id>\d+)/diff',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_snapshot_diff' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/pending/(?P<id>\d+)/verify',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_verify' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/pending/(?P<id>\d+)/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_preview' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		// v0.63 Review Deck: JSON decide endpoint so the inbox can approve/
		// reject WITHOUT a full-page reload (37-item batches meant 37 reloads).
		// Same internals as the form handler; JSON result means the client
		// KNOWS whether the apply succeeded before removing the card.
		register_rest_route(
			self::REST_NAMESPACE,
			'/pending/(?P<id>\d+)/decide',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_decide' ),
				'permission_callback' => array( 'CC_Assistant_Access', 'can_review' ),
			)
		);
	}

	public static function handle_decide( $request ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-apply.php';

		$id     = (int) $request['id'];
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$action = isset( $params['action'] ) ? sanitize_key( $params['action'] ) : '';
		$note   = isset( $params['note'] ) ? sanitize_textarea_field( (string) $params['note'] ) : '';
		$user   = get_current_user_id();

		if ( 'approve' === $action ) {
			$result = CC_Assistant_Apply::apply_pending( $id, $user );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return rest_ensure_response(
				array(
					'ok'          => true,
					'status'      => 'approved',
					'snapshot_id' => isset( $result['snapshot_id'] ) ? $result['snapshot_id'] : null,
				)
			);
		}
		if ( 'reject' === $action ) {
			$result = CC_Assistant_Pending_Changes::reject( $id, $user, $note );
			if ( ! $result ) { return new WP_Error( 'review_conflict', 'The change is no longer pending or rejection could not be saved.', array( 'status' => 409 ) ); }
			return rest_ensure_response( array( 'ok' => true, 'status' => 'rejected' ) );
		}
		return new WP_Error( 'invalid_action', 'action must be approve or reject.', array( 'status' => 400 ) );
	}

	public static function check_permission() {
		if ( ! CC_Assistant_Access::can_use() ) {
			return new WP_Error( 'rest_forbidden', 'You do not have permission to use CC Assistant.', array( 'status' => 403 ) );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	/**
	 * Snapshot vs current diff. Returns rendered HTML showing what would
	 * change if you restored this snapshot — same word-aware diff the
	 * pending-changes inbox uses.
	 *
	 * Compares post_title + post_content. Skips Elementor JSON because
	 * a JSON diff is unreadable; the user can compare via the editor's
	 * native revisions if they need byte-level Elementor delta.
	 */
	public static function handle_snapshot_diff( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-diff-render.php';
		global $wpdb;
		$snap_id = (int) $req->get_param( 'id' );
		$snap    = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cc_snapshots WHERE id = %d",
			$snap_id
		) );
		if ( ! $snap ) {
			return new WP_Error( 'not_found', 'Snapshot not found.', array( 'status' => 404 ) );
		}
		$post = get_post( (int) $snap->post_id );
		if ( ! $post ) {
			return array(
				'data' => array( 'snapshot_id' => $snap_id, 'message' => __( 'Original post no longer exists.', 'cc-assistant' ) ),
			);
		}

		$out = '';
		$snapshot_title   = isset( $snap->post_title ) ? (string) $snap->post_title : '';
		$snapshot_content = isset( $snap->post_content ) ? (string) $snap->post_content : '';

		if ( $snapshot_title !== '' && $snapshot_title !== $post->post_title ) {
			$out .= '<div class="cc-snap-diff-section"><h4>' . esc_html__( 'Title', 'cc-assistant' ) . '</h4>';
			$out .= '<div class="cc-human-diff">' . CC_Assistant_Diff_Render::smart_text_diff( $post->post_title, $snapshot_title ) . '</div></div>';
		}
		if ( $snapshot_content !== '' && $snapshot_content !== $post->post_content ) {
			$out .= '<div class="cc-snap-diff-section"><h4>' . esc_html__( 'Content', 'cc-assistant' ) . '</h4>';
			$out .= '<div class="cc-human-diff">' . CC_Assistant_Diff_Render::smart_text_diff( $post->post_content, $snapshot_content ) . '</div></div>';
		}
		if ( '' === $out ) {
			$out = '<p class="description">' . esc_html__( 'No textual differences between the snapshot and the current post (Elementor JSON or postmeta-only changes are not previewed here).', 'cc-assistant' ) . '</p>';
		}

		return array(
			'data' => array(
				'snapshot_id' => $snap_id,
				'post_id'     => (int) $snap->post_id,
				'html'        => $out,
			),
		);
	}

	public static function handle_diff( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-diff-render.php';

		$pending_id = (int) $req->get_param( 'id' );
		$item       = CC_Assistant_Pending_Changes::get( $pending_id );
		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Pending change not found.', array( 'status' => 404 ) );
		}

		$html = CC_Assistant_Diff_Render::render_human_diff( $item );

		return array(
			'site' => array(
				'fingerprint' => CC_Assistant_Site_Identity::fingerprint(),
			),
			'data' => array(
				'pending_id'  => $pending_id,
				'change_type' => $item->change_type,
				'html'        => $html,
			),
		);
	}

	/**
	 * Self-audit endpoint for any queued pending change. Returns the row
	 * metadata, the lint_report attached at queue time (if any), the
	 * structure-diff for post_content rewrites (current vs proposed), the
	 * success_metrics the operator stated, and any sibling pending rows
	 * that target the same post or that look like duplicates.
	 *
	 * Used by the verify_change MCP tool so the model can confirm its own
	 * work after queueing without re-running every analysis tool one by one.
	 */
	public static function handle_verify( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-diff-render.php';

		$pending_id = (int) $req->get_param( 'id' );
		$item       = CC_Assistant_Pending_Changes::get( $pending_id );
		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Pending change not found.', array( 'status' => 404 ) );
		}

		$lint    = ! empty( $item->lint_report ) ? json_decode( $item->lint_report, true ) : null;
		$metrics = ! empty( $item->success_metrics ) ? json_decode( $item->success_metrics, true ) : null;

		// Structural before/after for post_content rewrites. Only meaningful
		// while the change is still pending: once it's applied, the live post
		// IS the proposed body, so re-analyzing the post and comparing it to
		// the proposed_value would compare new vs new and produce a useless
		// "no change" diff. For approved/rejected/rolled_back rows we surface
		// a note instead so the operator isn't misled.
		$structure_diff = null;
		if ( 'post_content_update' === $item->change_type && $item->post_id ) {
			if ( 'pending' === $item->status ) {
				require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
				$proposed = json_decode( (string) $item->proposed_value, true );
				$proposed_html = is_array( $proposed ) && isset( $proposed['content'] ) ? (string) $proposed['content'] : '';
				$current = CC_Assistant_SEO_Tools::analyze_post_structure( (int) $item->post_id );
				$after   = self::analyze_html_structure( $proposed_html );
				$structure_diff = array(
					'before' => is_wp_error( $current ) ? null : $current,
					'after'  => $after,
					'delta'  => self::structure_delta( $current, $after ),
				);
			} else {
				$structure_diff = array(
					'note' => sprintf(
						'Structure-diff is suppressed because this change is already %s — the live post body now matches the proposed_value, so a re-comparison would lie. To audit a pre-apply baseline, use the snapshot for post_id %d in the Snapshots browser.',
						$item->status,
						(int) $item->post_id
					),
				);
			}
		}

		// Sibling pendings on the same post. Helps the model spot when it
		// has stacked too much on one post or when an earlier proposal
		// already covers part of this one.
		$siblings = array();
		if ( $item->post_id ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, change_type, change_summary, status, created_at
				 FROM {$wpdb->prefix}cc_pending_changes
				 WHERE post_id = %d AND id <> %d AND status IN ('pending','approved')
				 ORDER BY id DESC LIMIT 10",
				(int) $item->post_id,
				$pending_id
			) );
			foreach ( (array) $rows as $r ) {
				$siblings[] = array(
					'id'             => (int) $r->id,
					'change_type'    => $r->change_type,
					'change_summary' => $r->change_summary,
					'status'         => $r->status,
					'created_at'     => $r->created_at,
				);
			}
		}

		// Verdict text the model can quote in its next reply.
		//
		// Three lint outcomes, ordered by severity:
		//   1. Hard violations  -> "Lint: hard violations" (refused unless overridden)
		//   2. Soft warnings    -> "Lint: soft warnings" (queued; reviewer judgement)
		//   3. Clean            -> "Lint: clean"
		// The prior "Lint: fail (0/0 checks pass)" wording read alarming on
		// changes that only had soft warnings (sentence length, reading level),
		// even though those don't block apply. New wording makes the gate clear.
		$verdict_lines = array();
		if ( is_array( $lint ) ) {
			$pass_count = (int) ( $lint['pass_count'] ?? 0 );
			$fail_count = (int) ( $lint['fail_count'] ?? 0 );
			$total      = (int) ( $lint['total'] ?? max( 0, $pass_count + $fail_count ) );
			$hard_count = is_array( $lint['hard_violations'] ?? null ) ? count( $lint['hard_violations'] ) : 0;
			if ( $hard_count > 0 ) {
				$verdict_lines[] = sprintf(
					'Lint: %d hard violation(s), %d soft warning(s) (%d/%d checks pass). Reviewer must override before approving.',
					$hard_count,
					max( 0, $fail_count - $hard_count ),
					$pass_count,
					$total
				);
				$verdict_lines[] = 'Hard violations: ' . implode( ', ', (array) $lint['hard_violations'] ) . '.';
			} elseif ( $fail_count > 0 ) {
				$verdict_lines[] = sprintf(
					'Lint: clean on hard checks, %d soft warning(s) (%d/%d checks pass). Safe to apply; soft warnings are style-only.',
					$fail_count,
					$pass_count,
					$total
				);
			} else {
				$verdict_lines[] = sprintf( 'Lint: clean (%d/%d checks pass).', $pass_count, $total );
			}
		}
		if ( ! empty( $structure_diff['delta'] ) ) {
			$d = $structure_diff['delta'];
			if ( isset( $d['word_count_delta'] ) ) {
				$verdict_lines[] = sprintf( 'Word count: %+d.', (int) $d['word_count_delta'] );
			}
			if ( isset( $d['h2_delta'] ) && 0 !== (int) $d['h2_delta'] ) {
				$verdict_lines[] = sprintf( 'H2 count: %+d.', (int) $d['h2_delta'] );
			}
		}
		if ( ! empty( $siblings ) ) {
			$verdict_lines[] = sprintf( '%d other pending or approved change(s) on the same post — consider whether to consolidate.', count( $siblings ) );
		}
		if ( null === $metrics ) {
			$verdict_lines[] = 'No success_metrics set on this change. edit_outcomes will only have a generic "improved/flat/declined" pill, not "hit target".';
		}

		// superseded_by is the single most useful diagnostic for "why isn't this
		// in the inbox?" — a row can be status='pending' yet hidden from
		// fetch_all_for_inbox because a newer pending targeting the same
		// (post_id, change_type, subkey) marked it superseded. Surfacing the
		// pointer here lets the model (and a curl-test reviewer) see the chain
		// without poking the DB directly.
		$superseded_by_raw = isset( $item->superseded_by ) ? $item->superseded_by : null;
		$superseded_by     = ( null === $superseded_by_raw || '' === (string) $superseded_by_raw )
			? null
			: (int) $superseded_by_raw;
		if ( null !== $superseded_by ) {
			$verdict_lines[] = sprintf( 'Superseded by pending #%d — hidden from the inbox.', $superseded_by );
		}

		// Lint is not apply readiness. Report the actual state gate separately.
		$evidence_check = array( 'status' => 'not_pending', 'approved_sibling_ids' => array(), 'scope' => 'Post and environment evidence only; apply-time permission, recovery and payload checks still run.' );
		if ( 'pending' === $item->status && null === $superseded_by ) {
			$check = CC_Assistant_Evidence_Gate::validate_apply( $item );
			$evidence_check['status'] = is_wp_error( $check ) ? 'blocked' : 'current';
			if ( is_wp_error( $check ) ) {
				$evidence_check['code'] = $check->get_error_code();
				$evidence_check['message'] = $check->get_error_message();
			} elseif ( $item->post_id ) {
				require_once __DIR__ . '/class-approval-continuation.php';
				$chain = CC_Assistant_Approval_Continuation::prove( $item, CC_Assistant_Integrity::post_hash( (int) $item->post_id ) );
				if ( ! empty( $chain['approved_ids'] ) ) {
					$evidence_check['status'] = 'compatible_approved_changes';
					$evidence_check['approved_sibling_ids'] = $chain['approved_ids'];
				}
			}
			$verdict_lines[] = 'Apply evidence: ' . $evidence_check['status'] . '.';
		}

		return array(
			'site' => array(
				'fingerprint' => CC_Assistant_Site_Identity::fingerprint(),
			),
			'data' => array(
				'pending_id'      => $pending_id,
				'post_id'         => (int) $item->post_id,
				'change_type'     => $item->change_type,
				'change_summary'  => $item->change_summary,
				'reasoning'       => $item->reasoning,
				'status'          => $item->status,
				'superseded_by'   => $superseded_by,
				'created_at'      => $item->created_at,
				'lint_report'     => $lint,
				'success_metrics' => $metrics,
				'structure_diff'  => $structure_diff,
				'evidence_check'  => $evidence_check,
				'siblings'        => $siblings,
				'verdict'         => implode( ' ', $verdict_lines ),
			),
		);
	}

	/**
	 * Render a proposed post_content_update through the WordPress content
	 * filters (wpautop, shortcodes, embeds) and return the resulting HTML.
	 * The inbox uses this to show the reviewer what the body will actually
	 * look like after approval, so they're not just diffing raw HTML.
	 *
	 * Returns an empty payload (not an error) for non-content change types
	 * so the inbox UI can hide the Preview button cleanly.
	 */
	public static function handle_preview( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		$pending_id = (int) $req->get_param( 'id' );
		$item       = CC_Assistant_Pending_Changes::get( $pending_id );
		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Pending change not found.', array( 'status' => 404 ) );
		}
		if ( 'post_content_update' !== $item->change_type ) {
			return array(
				'site' => array( 'fingerprint' => CC_Assistant_Site_Identity::fingerprint() ),
				'data' => array(
					'pending_id' => $pending_id,
					'previewable' => false,
					'message'    => 'Preview is only available for post_content_update change types.',
				),
			);
		}
		$proposed = json_decode( (string) $item->proposed_value, true );
		$html     = is_array( $proposed ) && isset( $proposed['content'] ) ? (string) $proposed['content'] : '';
		if ( '' === $html ) {
			return new WP_Error( 'empty_content', 'Proposed content is empty.', array( 'status' => 400 ) );
		}

		// Run the standard the_content filter chain so the preview reflects
		// what wpautop, shortcodes, and embed handlers actually produce.
		// We do NOT run it through theme post-content widgets — the modal
		// shows the body content rendered, not the full theme chrome.
		$rendered = apply_filters( 'the_content', $html );
		$rendered = wp_kses_post( $rendered ); // Defensive against any odd filter output.

		return array(
			'site' => array( 'fingerprint' => CC_Assistant_Site_Identity::fingerprint() ),
			'data' => array(
				'pending_id' => $pending_id,
				'previewable' => true,
				'post_id'     => (int) $item->post_id,
				'post_title'  => $item->post_id ? get_the_title( (int) $item->post_id ) : '',
				'rendered'    => $rendered,
			),
		);
	}

	/**
	 * Mini structure analyzer for arbitrary HTML (proposed body, not yet
	 * persisted). Mirrors a subset of analyze_post_structure so we can
	 * compare before vs after without persisting the proposal first.
	 */
	private static function analyze_html_structure( $html ) {
		$html = (string) $html;
		$plain = trim( wp_strip_all_tags( $html ) );
		$word_count = $plain ? str_word_count( $plain ) : 0;
		$h2 = preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $h2m );
		$h3 = preg_match_all( '/<h3\b[^>]*>/i', $html );
		$tables = preg_match_all( '/<table\b/i', $html );
		$lists = preg_match_all( '/<(ul|ol)\b/i', $html );
		$links = preg_match_all( '/<a\b[^>]*href=/i', $html );
		$h2_titles = array();
		if ( $h2 && ! empty( $h2m[1] ) ) {
			foreach ( $h2m[1] as $t ) {
				$h2_titles[] = trim( wp_strip_all_tags( $t ) );
			}
		}
		return array(
			'word_count' => $word_count,
			'h2_total'   => (int) $h2,
			'h3_total'   => (int) $h3,
			'table_count'=> (int) $tables,
			'list_count' => (int) $lists,
			'link_count' => (int) $links,
			'h2_titles'  => $h2_titles,
		);
	}

	/**
	 * Compute a coarse delta between the existing structure (from
	 * analyze_post_structure on the live post) and the proposed structure.
	 * Returns word_count_delta, h2_delta, h3_delta, link_delta — the
	 * signals a reviewer skims to judge a body rewrite at a glance.
	 */
	private static function structure_delta( $current, $after ) {
		if ( ! is_array( $current ) || ! is_array( $after ) ) {
			return array();
		}
		$cw = isset( $current['word_count'] ) ? (int) $current['word_count'] : 0;
		$aw = isset( $after['word_count'] ) ? (int) $after['word_count'] : 0;
		$ch2 = isset( $current['h2_total'] ) ? (int) $current['h2_total'] : 0;
		$ah2 = isset( $after['h2_total'] ) ? (int) $after['h2_total'] : 0;
		$ch3 = isset( $current['h3_total'] ) ? (int) $current['h3_total'] : 0;
		$ah3 = isset( $after['h3_total'] ) ? (int) $after['h3_total'] : 0;
		return array(
			'word_count_delta' => $aw - $cw,
			'h2_delta'         => $ah2 - $ch2,
			'h3_delta'         => $ah3 - $ch3,
		);
	}
}

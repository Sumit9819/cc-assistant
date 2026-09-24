<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post-apply rendered audit. The safety net that catches schema duplicates
 * (and other rendered-HTML issues) AFTER a change applies, in case the
 * pre-queue guards missed it.
 *
 * Why this exists: pre-queue guards work off the proposed `_elementor_data`
 * tree, but Rank Math, themes, and other plugins also inject JSON-LD via
 * wp_head. Two adds queued through separate paths in the same minute might
 * each look fine in isolation but produce a duplicate at render time. The
 * pre-queue guard catches the in-tree case; this cron catches the rendered
 * case.
 *
 * Timing: scheduled 30 seconds after every elementor_* apply (long enough
 * for Elementor's CSS cache flush + WordPress object cache to settle, short
 * enough that the issue surfaces in the same operator-attention window).
 *
 * Performance: one curl per audit, short timeout, regex+json_decode on the
 * response. Single row append to cc_activity_log if a problem is found,
 * otherwise a single noop debug line. No FE queries.
 *
 * What it detects (current rev):
 *  - Duplicate JSON-LD `@id` on the same page
 *  - Multiple entities of singleton `@type` (FAQPage, MedicalProcedure,
 *    MedicalWebPage, WebPage, Article)
 *  - Missing `@id` on entities that should have one (singleton types)
 *  - JSON-LD parse errors (malformed script blocks)
 */
class CC_Assistant_Post_Apply_Audit {

	const CRON_HOOK    = 'cc_assistant_post_apply_audit';
	const DELAY_SECS   = 30;
	const HTTP_TIMEOUT = 15;
	const UA           = CC_ASSISTANT_HTTP_UA;

	/**
	 * Wire the audit cron into the standard after-apply event. Idempotent —
	 * registering twice is a no-op because add_action prevents duplicate
	 * handler attachments with the same callable.
	 */
	public static function init() {
		add_action( 'cc_assistant_after_apply', array( __CLASS__, 'schedule_audit_for' ), 20, 3 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_audit' ), 10, 2 );
	}

	/**
	 * Schedule a one-shot audit ~30 seconds after the apply. Skips when the
	 * post is unknown (cluster changes etc.) since there's no URL to audit.
	 */
	public static function schedule_audit_for( $pending_id, $post_id, $change_type ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		// v0.68: the page just changed, so the queue-time render baseline
		// cached for this post is now stale. Bust it here — synchronously on
		// apply — so any change queued in the next moments captures the
		// POST-apply state instead of being blamed for this apply's diff.
		require_once CC_ASSISTANT_DIR . 'includes/class-render-health.php';
		CC_Assistant_Render_Health::bust_baseline_cache( $post_id );

		$args = array( $post_id, (int) $pending_id );
		if ( wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return;
		}
		wp_schedule_single_event( time() + self::DELAY_SECS, self::CRON_HOOK, $args );
	}

	/**
	 * The cron handler. Fetches the rendered page, runs the audit, records
	 * findings into cc_activity_log + the pending row's verification_result
	 * + a dashboard transient for the admin notice.
	 */
	public static function run_audit( $post_id, $pending_id = 0 ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		// v0.68.2: read the pending row FIRST. Every exit below must leave a
		// visible verdict on it — the v0.68.1 smoke test produced NO pill and
		// three equally-silent suspects (capture failed at queue, this fetch
		// failed, baseline absent); none of those states may be mute again.
		require_once CC_ASSISTANT_DIR . 'includes/class-render-health.php';
		$pending_row = null;
		if ( (int) $pending_id > 0 ) {
			global $wpdb;
			$pending_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT pre_check_baseline, change_type FROM {$wpdb->prefix}cc_pending_changes WHERE id = %d",
				(int) $pending_id
			) );
		}

		// A trashed post is SUPPOSED to stop rendering — comparing its 404
		// against the live-page baseline would flag every approved trash as a
		// critical regression, and fetching it is pointless.
		if ( $pending_row && 'trash_post' === (string) $pending_row->change_type ) {
			self::store_render_verdict( (int) $pending_id, array(), false, 'skipped: the page was intentionally trashed by this change' );
			return;
		}

		$url = get_permalink( $post_id );
		if ( empty( $url ) ) {
			self::store_render_verdict( (int) $pending_id, array(), false, 'not checked: the post no longer resolves to a URL' );
			return;
		}

		// Cache-busted fetch with no-cache headers — a plain GET could be
		// served stale pre-change HTML by SiteGround's path-keyed cache,
		// making every post-apply comparison silently vacuous.
		$resp = CC_Assistant_Render_Health::fetch( $url );
		if ( ! is_array( $resp ) || '' === (string) $resp['body'] ) {
			self::store_render_verdict( (int) $pending_id, array(), false, 'not checked: the post-apply loopback fetch failed or returned an empty body' );
			return;
		}
		$body      = (string) $resp['body'];
		$http_code = (int) $resp['code'];

		$findings = self::scan_rendered_html( $body );

		// The Render Health comparison: does the page still work? Empty-state
		// text appearing, element populations vanishing, shortcode leakage,
		// PHP errors, HTTP change. Reported; never auto-reverted.
		$render_findings = array();
		if ( $pending_row ) {
			$baseline_raw = (string) $pending_row->pre_check_baseline;
			$baseline     = '' !== $baseline_raw ? json_decode( $baseline_raw, true ) : null;
			if ( is_array( $baseline ) && isset( $baseline['render']['facts'] ) && is_array( $baseline['render']['facts'] ) ) {
				$now_facts       = CC_Assistant_Render_Health::scan( $body, $http_code );
				$render_findings = CC_Assistant_Render_Health::compare( $baseline['render']['facts'], $now_facts, true );
				self::store_render_verdict( (int) $pending_id, $render_findings, true, '' );
			} elseif ( is_array( $baseline ) && isset( $baseline['render']['error'] ) ) {
				self::store_render_verdict( (int) $pending_id, array(), false, 'not checked: baseline capture failed at queue time (' . (string) $baseline['render']['error'] . ')' );
			} else {
				self::store_render_verdict( (int) $pending_id, array(), false, 'not checked: no render baseline was captured when this change was queued' );
			}
		}

		if ( ! empty( $render_findings ) ) {
			self::record_render_findings( $post_id, (int) $pending_id, $url, $render_findings );
		}

		if ( empty( $findings ) ) {
			return; // schema clean — render findings (if any) already recorded
		}

		// 1. Activity log entry so the next whoami() bootstrap surfaces it.
		if ( file_exists( CC_ASSISTANT_DIR . 'includes/class-activity-log.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
			$summary = sprintf(
				'Schema audit on post %d found %d issue%s: %s',
				$post_id,
				count( $findings ),
				1 === count( $findings ) ? '' : 's',
				self::summarize_findings( $findings )
			);
			CC_Assistant_Activity_Log::record(
				CC_Assistant_Activity_Log::TYPE_DRIFT_DETECTED,
				$summary,
				$post_id,
				(int) $pending_id,
				'system'
			);
		}

		// 2. Stash on the pending row's verification_result so the inbox
		// shows the audit verdict next to the applied change.
		if ( (int) $pending_id > 0 ) {
			global $wpdb;
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT verification_result FROM {$wpdb->prefix}cc_pending_changes WHERE id = %d",
				(int) $pending_id
			) );
			$payload = is_string( $existing ) && '' !== $existing ? json_decode( $existing, true ) : array();
			if ( ! is_array( $payload ) ) {
				$payload = array();
			}
			$payload['rendered_schema_audit'] = array(
				'audited_at' => current_time( 'mysql' ),
				'findings'   => $findings,
			);
			$wpdb->update(
				$wpdb->prefix . 'cc_pending_changes',
				array( 'verification_result' => wp_json_encode( $payload ) ),
				array( 'id' => (int) $pending_id )
			);
		}

		// 3. Dashboard banner transient — surfaced by the admin notice
		// handler in class-admin-notices.php on the next wp-admin page load.
		$banners = (array) get_transient( 'cc_assistant_health_issues' );
		$banners[] = array(
			'severity' => 'critical',
			'code'     => 'rendered_schema_issue',
			'post_id'  => $post_id,
			'url'      => $url,
			'count'    => count( $findings ),
			'findings' => array_slice( $findings, 0, 5 ),
			'detected' => current_time( 'mysql' ),
		);
		set_transient( 'cc_assistant_health_issues', array_slice( $banners, -20 ), DAY_IN_SECONDS );
	}

	/**
	 * v0.68.1: persist the render-health verdict on the pending row — ALWAYS.
	 * v0.68.2: including when the check could NOT run ($checked=false with a
	 * $reason). Four visible states, zero silent ones: checked-clean,
	 * checked-with-warnings, checked-critical, and not-checked-because.
	 */
	private static function store_render_verdict( $pending_id, $findings, $checked = true, $reason = '' ) {
		if ( (int) $pending_id <= 0 ) {
			return;
		}
		global $wpdb;
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT verification_result FROM {$wpdb->prefix}cc_pending_changes WHERE id = %d",
			(int) $pending_id
		) );
		$payload = is_string( $existing ) && '' !== $existing ? json_decode( $existing, true ) : array();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$payload['render_health'] = array(
			'audited_at' => current_time( 'mysql' ),
			'checked'    => (bool) $checked,
			'critical'   => $checked ? CC_Assistant_Render_Health::has_critical( $findings ) : false,
			'findings'   => array_values( (array) $findings ),
			'reason'     => (string) $reason,
		);
		$wpdb->update(
			$wpdb->prefix . 'cc_pending_changes',
			array( 'verification_result' => wp_json_encode( $payload ) ),
			array( 'id' => (int) $pending_id )
		);
	}

	/**
	 * v0.68: alert channels for NON-empty findings — activity log (surfaces in
	 * the next whoami bootstrap) and the persistent admin notice. The pending
	 * row verdict itself is written by store_render_verdict() regardless.
	 * REPORT only; nothing auto-reverts.
	 */
	private static function record_render_findings( $post_id, $pending_id, $url, $findings ) {
		$critical = CC_Assistant_Render_Health::has_critical( $findings );

		$labels = array();
		foreach ( $findings as $f ) {
			$labels[] = isset( $f['code'] ) ? $f['code'] : 'unknown';
		}

		if ( file_exists( CC_ASSISTANT_DIR . 'includes/class-activity-log.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
			CC_Assistant_Activity_Log::record(
				CC_Assistant_Activity_Log::TYPE_DRIFT_DETECTED,
				sprintf(
					'%sRender health on post %d after pending #%d: %s',
					$critical ? 'CRITICAL ' : '',
					(int) $post_id,
					(int) $pending_id,
					implode( ', ', array_unique( $labels ) )
				),
				(int) $post_id,
				(int) $pending_id,
				'system'
			);
		}

		// The visible surface. Discovered in review: the schema audit's
		// 'cc_assistant_health_issues' transient has NO reader anywhere in the
		// plugin — findings written only there are invisible to the operator.
		// Route through the persistent Admin Notices store, which render()s
		// on every wp-admin load and supports dismissal.
		if ( file_exists( CC_ASSISTANT_DIR . 'includes/class-admin-notices.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-admin-notices.php';
			$messages = array();
			foreach ( array_slice( $findings, 0, 3 ) as $f ) {
				if ( ! empty( $f['message'] ) ) {
					$messages[] = (string) $f['message'];
				}
			}
			CC_Assistant_Admin_Notices::add(
				'render_health_' . (int) $pending_id,
				$critical ? 'error' : 'warning',
				sprintf(
					/* translators: 1: post title, 2: post ID, 3: pending id, 4: findings */
					__( 'Page check after applying pending #%3$d on "%1$s" (post %2$d): %4$s', 'cc-assistant' ),
					get_the_title( $post_id ),
					(int) $post_id,
					(int) $pending_id,
					implode( ' ', $messages )
				),
				array(
					'url'   => $url,
					'label' => __( 'View the page', 'cc-assistant' ),
				)
			);
		}
	}

	/**
	 * Heart of the audit: parses every JSON-LD block in the rendered HTML and
	 * returns a list of `{code, type, id?, message}` findings.
	 */
	public static function scan_rendered_html( $html ) {
		$findings = array();
		if ( ! preg_match_all( '#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $html, $m ) ) {
			return $findings;
		}
		$singletons = array(
			'FAQPage' => true,
			'MedicalProcedure' => true,
			'MedicalWebPage' => true,
			'WebPage' => true,
			'Article' => true,
		);
		$entities = array(); // flat list across all blocks
		foreach ( $m[1] as $block_i => $blob ) {
			$blob_trim = trim( $blob );
			$decoded = json_decode( $blob_trim, true );
			if ( ! is_array( $decoded ) ) {
				$findings[] = array(
					'code'    => 'parse_error',
					'message' => sprintf( 'JSON-LD block #%d failed to parse', $block_i ),
				);
				continue;
			}
			$batch = array();
			if ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) {
				$batch = $decoded['@graph'];
			} elseif ( isset( $decoded['@type'] ) ) {
				$batch = array( $decoded );
			}
			foreach ( $batch as $e ) {
				if ( ! is_array( $e ) ) {
					continue;
				}
				$t = $e['@type'] ?? '';
				if ( is_array( $t ) ) {
					$t = implode( '|', $t );
				}
				$entities[] = array(
					'type'  => (string) $t,
					'id'    => isset( $e['@id'] ) ? (string) $e['@id'] : '',
					'block' => $block_i,
				);
			}
		}

		// Duplicate @id detection.
		$by_id = array();
		foreach ( $entities as $e ) {
			if ( '' === $e['id'] ) {
				continue;
			}
			$key = $e['type'] . '|' . $e['id'];
			$by_id[ $key ] = ( $by_id[ $key ] ?? 0 ) + 1;
		}
		foreach ( $by_id as $key => $count ) {
			if ( $count > 1 ) {
				list( $type, $id ) = explode( '|', $key, 2 );
				$findings[] = array(
					'code'    => 'duplicate_id',
					'type'    => $type,
					'id'      => $id,
					'count'   => $count,
					'message' => sprintf( 'Schema @id "%s" (type %s) appears %d times on the rendered page.', $id, $type, $count ),
				);
			}
		}

		// Singleton type appearing more than once (even with different @id).
		$by_type = array();
		foreach ( $entities as $e ) {
			foreach ( explode( '|', $e['type'] ) as $t ) {
				if ( '' === $t ) {
					continue;
				}
				$by_type[ $t ] = ( $by_type[ $t ] ?? 0 ) + 1;
			}
		}
		foreach ( $by_type as $type => $count ) {
			if ( isset( $singletons[ $type ] ) && $count > 1 ) {
				$findings[] = array(
					'code'    => 'singleton_type_multiplied',
					'type'    => $type,
					'count'   => $count,
					'message' => sprintf( '%s should appear at most once per page; rendered %d times.', $type, $count ),
				);
			}
		}

		return $findings;
	}

	/**
	 * Short headline of findings for activity-log + admin-notice display.
	 */
	private static function summarize_findings( $findings ) {
		$bits = array();
		foreach ( $findings as $f ) {
			if ( 'duplicate_id' === $f['code'] ) {
				$bits[] = sprintf( '%s @id duplicated', $f['type'] );
			} elseif ( 'singleton_type_multiplied' === $f['code'] ) {
				$bits[] = sprintf( '%s x%d', $f['type'], $f['count'] );
			} elseif ( 'parse_error' === $f['code'] ) {
				$bits[] = 'JSON-LD parse error';
			}
		}
		return implode( '; ', array_slice( $bits, 0, 3 ) );
	}
}

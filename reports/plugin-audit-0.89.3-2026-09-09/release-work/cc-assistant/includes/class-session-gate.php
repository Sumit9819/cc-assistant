<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.44 — Session bootstrap gate. v0.81 — hard by default.
 *
 * "Every new chat must go through the necessary things." whoami marks the
 * session bootstrapped (transient, same model as the map-consulted gate in
 * class-elementor-map.php). Mutating handlers consult the gate through
 * pre_queue_gate(): a stale gate now REFUSES the write (409) instead of
 * attaching a reminder the model scrolls past.
 *
 * Why hard: the soft reminder shipped in v0.44 and was ignored on every
 * mutating call of at least one recorded session (2026-08-03). A reminder is
 * advice; the operator asked for a system that cannot skip the step. Set the
 * option cc_assistant_bootstrap_hard_gate to "0" to fall back to the reminder.
 *
 * Window: the stamp is site-wide, not per chat, so it cannot tell two chats
 * apart. It exists to guarantee SOME whoami happened recently on this site;
 * the per-chat guarantee lives in the Claude Code PreToolUse hook
 * (.claude/hooks/cc_gate.py), which keys on the session id. The window is a
 * working day so a long session is not cut off mid-task.
 */
class CC_Assistant_Session_Gate {

	const KEY = 'cc_assistant_session_bootstrapped';
	const TTL = 8 * HOUR_IN_SECONDS;

	/** Called by handle_whoami. Stamps "this session has bootstrapped". */
	public static function mark_bootstrapped() {
		set_transient( self::KEY, time(), self::TTL );
	}

	public static function bootstrapped_at() {
		$t = get_transient( self::KEY );
		return $t ? (int) $t : 0;
	}

	public static function is_fresh( $within = self::TTL ) {
		$at = self::bootstrapped_at();
		return $at > 0 && ( time() - $at ) <= (int) $within;
	}

	/** v0.81: hard unless the operator explicitly set the option to "0"/false. */
	public static function hard_gate_enabled() {
		$opt = get_option( 'cc_assistant_bootstrap_hard_gate', null );
		if ( null === $opt || '' === $opt ) {
			return true;
		}
		return ! in_array( $opt, array( '0', 0, false, 'false', 'off', 'no' ), true );
	}

	/**
	 * The ordered pre-flight every new chat must run. Shipped inside whoami so
	 * the model sees it on bootstrap, and echoed in the stale reminder.
	 */
	public static function preflight() {
		return array(
			array(
				'step' => 1,
				'do'   => 'whoami (you are here)',
				'why'  => 'Read current rules, memory_consistency, capabilities and pending work. Historical working_state is context: resume only when it matches the current user request. Preserve explicit operator constraints; outdated SEO recipes do not override current evidence.',
			),
			array(
				'step' => 2,
				'do'   => 'Read the per-site design skill',
				'why'  => 'Brand tokens, geo priority, allowed palette/greys, and widget patterns. Skipping it produces off-brand, off-geo output.',
			),
			array(
				'step' => 3,
				'do'   => 'verified_page_audit(post_id) before a published-page edit and after approval',
				'why'  => 'Use fixed rule IDs with timestamps, fingerprints and explicit unknown/review statuses. This checks server HTML only. For drafts read get_post; for visual/JavaScript claims use browser evidence. Use inspect_plugin_capability and widget_schema before planning plugin changes.',
			),
			array(
				'step' => 4,
				'do'   => 'list_pending_changes(post_id)',
				'why'  => 'Never duplicate queued work. Context compaction drops pending IDs, so re-query before queueing.',
			),
			array(
				'step' => 5,
				'do'   => 'Use content_workflow for a content job; verify actual saved results',
				'why'  => 'Maintain strategy from current sources, research the reader task, then prepare a concrete draft or change. Use content_decision for overlap or consolidation and verify_content_workflow for bound drafts. Optional heuristic scores cannot authorize rewrites or prove ranking effects.',
			),
		);
	}

	/**
	 * For mutating handlers: returns a reminder array when bootstrap is stale,
	 * or null when fresh. Append to the response `warnings` list.
	 */
	public static function reminder_if_stale() {
		if ( self::is_fresh() ) {
			return null;
		}
		return array(
			'code'      => 'session_not_bootstrapped',
			'message'   => 'No whoami in this session window. Before mutating content: run whoami (session_recap), read the design skill, render_probe the target page, and check the pending inbox.',
			'preflight' => self::preflight(),
		);
	}

	/** Hard gate (default ON since v0.81): WP_Error when bootstrap is stale, else null. */
	public static function hard_block_if_enabled() {
		if ( ! self::hard_gate_enabled() ) {
			return null;
		}
		if ( self::is_fresh() ) {
			return null;
		}
		return new WP_Error(
			'session_not_bootstrapped',
			'REFUSED: no whoami on this site in the last 8 hours. Call whoami first (it is the bootstrap pack: working_state, pending inbox, rules, operator_brain, relevant_rules), then render_probe the target page, then retry this write. Operator override: set option cc_assistant_bootstrap_hard_gate to "0".',
			array( 'status' => 409, 'preflight' => self::preflight() )
		);
	}
}

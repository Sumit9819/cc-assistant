<?php
/**
 * Working state (v0.42) — the structured "current job" record that makes a
 * brand-new chat resume EXACTLY where the last one stopped. Unlike the
 * site-memory notes (free text, tail-truncated in whoami) this is returned
 * IN FULL by whoami.session_recap.working_state: active task, target posts,
 * step checklist, agreed decisions/constraints, open loops, and the next
 * planned action. The assistant updates it via the update_working_state tool
 * after every meaningful step; site rules that prove permanent still get
 * promoted to site-memory Rules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Working_State {

	const OPT = 'cc_assistant_working_state';

	public static function get() {
		$s = get_option( self::OPT, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Merge a patch into the state. Scalar fields replace; list fields replace
	 * when passed as `<field>` and append-dedupe when passed as `add_<field>`.
	 * remove_open_loops removes exact-match entries (closing a loop).
	 */
	public static function update( $patch ) {
		$patch = is_array( $patch ) ? $patch : array();
		$s     = self::get();

		foreach ( array( 'active_task', 'current_plan', 'status' ) as $f ) {
			if ( isset( $patch[ $f ] ) ) {
				$s[ $f ] = sanitize_textarea_field( (string) $patch[ $f ] );
			}
		}
		if ( isset( $patch['target_post_ids'] ) && is_array( $patch['target_post_ids'] ) ) {
			$s['target_post_ids'] = array_values( array_unique( array_map( 'intval', $patch['target_post_ids'] ) ) );
		}
		foreach ( array( 'steps', 'decisions', 'constraints', 'open_loops' ) as $f ) {
			if ( isset( $patch[ $f ] ) && is_array( $patch[ $f ] ) ) {
				$s[ $f ] = array_values( array_map( function ( $x ) {
					return sanitize_textarea_field( (string) $x );
				}, $patch[ $f ] ) );
			}
			if ( isset( $patch[ 'add_' . $f ] ) && is_array( $patch[ 'add_' . $f ] ) ) {
				$cur = isset( $s[ $f ] ) && is_array( $s[ $f ] ) ? $s[ $f ] : array();
				foreach ( $patch[ 'add_' . $f ] as $x ) {
					$cur[] = sanitize_textarea_field( (string) $x );
				}
				$s[ $f ] = array_values( array_unique( $cur ) );
			}
		}
		if ( isset( $patch['remove_open_loops'] ) && is_array( $patch['remove_open_loops'] ) ) {
			$cur = isset( $s['open_loops'] ) && is_array( $s['open_loops'] ) ? $s['open_loops'] : array();
			$rm  = array_map( 'strval', $patch['remove_open_loops'] );
			$s['open_loops'] = array_values( array_filter( $cur, function ( $x ) use ( $rm ) {
				return ! in_array( (string) $x, $rm, true );
			} ) );
		}

		$s['updated_at'] = gmdate( 'c' );
		update_option( self::OPT, $s, false );
		return $s;
	}
}

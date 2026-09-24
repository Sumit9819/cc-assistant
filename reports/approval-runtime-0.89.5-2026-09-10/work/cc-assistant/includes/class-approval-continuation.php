<?php
/** Prove that only non-overlapping approved writes separate an observation from today. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-integrity.php';

class CC_Assistant_Approval_Continuation {
	/** Deliberately excludes structural edits, publication, slugs and arbitrary plugin settings. */
	public static function scope( $row ) {
		$p = json_decode( (string) ( $row->proposed_value ?? '' ), true );
		if ( ! is_array( $p ) ) { return null; }
		switch ( $row->change_type ?? '' ) {
			case 'meta_update':
				return in_array( $p['field'] ?? '', array( 'post_title', 'post_excerpt', 'post_author' ), true ) && is_scalar( $p['value'] ?? null ) ? array( 'field:' . $p['field'] ) : null;
			case 'post_content_update':
				return is_string( $p['content'] ?? null ) ? array( 'field:post_content' ) : null;
			case 'postmeta_update':
				$key = $p['key'] ?? '';
				// These are independent text/image SEO fields, not builder or workflow state.
				$keys = array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', 'rank_math_canonical_url', 'rank_math_facebook_title', 'rank_math_facebook_description', 'rank_math_twitter_title', 'rank_math_twitter_description', '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw', '_yoast_wpseo_canonical', '_yoast_wpseo_opengraph-title', '_yoast_wpseo_opengraph-description', '_yoast_wpseo_twitter-title', '_yoast_wpseo_twitter-description', '_seopress_titles_title', '_seopress_titles_desc', '_seopress_robots_canonical', '_thumbnail_id', '_wp_attachment_image_alt' );
				return in_array( $key, $keys, true ) && is_scalar( $p['value'] ?? null ) ? array( 'meta:' . $key ) : null;
			case 'elementor_widget_update':
				$updates = array( $p ); break;
			case 'elementor_section_content_replace':
				$updates = $p['widget_updates'] ?? null; break;
			default: return null;
		}
		if ( ! is_array( $updates ) || ! $updates ) { return null; }
		$scope = array();
		foreach ( $updates as $u ) {
			if ( ! is_array( $u ) || ! is_string( $u['widget_id'] ?? null ) || '' === $u['widget_id'] || ! is_array( $u['settings'] ?? null ) ) { return null; }
			$scope[] = 'widget:' . $u['widget_id'];
		}
		return count( array_unique( $scope ) ) === count( $scope ) ? $scope : null;
	}

	/** Pure replay. No writes, timestamps, cache resets, or fabricated observations. */
	public static function replay( $row, array $state ) {
		if ( null === self::scope( $row ) || '' === CC_Assistant_Integrity::state_hash( $state ) ) { return null; }
		$p = json_decode( $row->proposed_value, true );
		switch ( $row->change_type ) {
			case 'meta_update': $state['fields'][$p['field']] = (string) $p['value']; break;
			case 'post_content_update': $state['fields']['post_content'] = $p['content']; break;
			case 'postmeta_update':
				$values = $state['meta'][$p['key']] ?? array();
				if ( ! is_array( $values ) ) { return null; }
				// Match apply_post_meta's delete-only-when-already-empty behavior.
				if ( '' === trim( (string) $p['value'] ) && '' === trim( (string) ( $values[0] ?? '' ) ) ) { unset( $state['meta'][$p['key']] ); }
				else { $state['meta'][$p['key']] = array_fill( 0, max( 1, count( $values ) ), (string) $p['value'] ); }
				break;
			default:
				$raw = $state['meta']['_elementor_data'][0] ?? null;
				$tree = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( ! is_array( $tree ) ) { return null; }
				$deep = 'elementor_section_content_replace' === $row->change_type;
				$updates = $deep ? $p['widget_updates'] : array( $p );
				foreach ( $updates as $u ) {
					$count = 0;
					if ( ! self::patch( $tree, $u['widget_id'], $u['settings'], $deep, $count ) || 1 !== $count ) { return null; }
				}
				$json = wp_json_encode( $tree );
				if ( false === $json ) { return null; }
				$state['meta']['_elementor_data'] = array( $json );
				$state['meta']['_elementor_edit_mode'] = array( 'builder' );
		}
		return $state;
	}

	private static function patch( array &$nodes, $id, array $settings, $deep, &$count, $depth = 0 ) {
		if ( $depth > 64 ) { return false; }
		foreach ( $nodes as &$n ) {
			if ( ! is_array( $n ) ) { return false; }
			if ( ( $n['id'] ?? null ) === $id ) {
				++$count;
				$old = is_array( $n['settings'] ?? null ) ? $n['settings'] : array();
				$n['settings'] = $deep ? self::merge( $old, $settings ) : array_merge( $old, $settings );
			}
			if ( ! empty( $n['elements'] ) && ( ! is_array( $n['elements'] ) || ! self::patch( $n['elements'], $id, $settings, $deep, $count, $depth + 1 ) ) ) { return false; }
		}
		return true;
	}
	private static function merge( array $base, array $overlay ) {
		foreach ( $overlay as $key => $value ) {
			$base[$key] = is_array( $value ) && isset( $base[$key] ) && is_array( $base[$key] ) ? self::merge( $base[$key], $value ) : $value;
		}
		return $base;
	}

	/** An unbroken chain must start at this proposal's original observation and end exactly live. */
	public static function prove( $pending, $live_hash ) {
		$failure = array( 'safe' => false, 'approved_ids' => array(), 'reason' => 'unsupported_or_missing_evidence', 'ignored_runtime_meta_keys' => CC_Assistant_Integrity::RUNTIME_META );
		$scope = self::scope( $pending );
		$pid = (int) ( $pending->post_id ?? 0 );
		$e = CC_Assistant_Integrity::baseline( $pending )['evidence'] ?? array();
		$expected = $e['posts'][$pid]['post_hash'] ?? '';
		if ( ! $pid || ! $scope || ! $expected || ! $live_hash || count( $e['posts'] ?? array() ) !== 1 || ! CC_Assistant_Evidence_Gate::environment_matches( $e['environment_hash'] ?? '' ) ) { return $failure; }
		$live_state = CC_Assistant_Integrity::post_state( $pid );
		if ( CC_Assistant_Integrity::state_hash( $live_state ) !== $live_hash ) { $failure['reason'] = 'state_changed_during_check'; return $failure; }
		if ( CC_Assistant_Integrity::hash_matches_state( $expected, $live_state ) ) { return array( 'safe' => true, 'approved_ids' => array(), 'reason' => $expected === $live_hash ? 'current' : 'legacy_runtime_metadata_only', 'ignored_runtime_meta_keys' => CC_Assistant_Integrity::RUNTIME_META ); }
		require_once __DIR__ . '/class-snapshots.php';
		global $wpdb;
		// Bounded history. Missing/older history fails closed and requires a fresh proposal.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cc_pending_changes WHERE post_id = %d AND status = 'approved' ORDER BY reviewed_at DESC, id DESC LIMIT 200", $pid ) );
		$steps = array();
		foreach ( (array) $rows as $row ) {
			$b = CC_Assistant_Integrity::baseline( $row );
			if ( empty( $b['snapshot_id'] ) || empty( $b['applied_post_hash'] ) || null === self::scope( $row ) ) { continue; }
			if ( ! CC_Assistant_Evidence_Gate::environment_matches( $b['evidence']['environment_hash'] ?? '' ) ) { continue; }
			$s = CC_Assistant_Snapshots::get_snapshot( (int) $b['snapshot_id'] );
			if ( ! $s || (int) $s->post_id !== $pid || 'pre_apply' !== $s->snapshot_type ) { continue; }
			$state = CC_Assistant_Snapshots::state( $s );
			if ( 2 !== ( $state['cc_snapshot_version'] ?? null ) || CC_Assistant_Integrity::utc_timestamp( $state['created_at_gmt'] ?? '' ) < CC_Assistant_Integrity::queued_timestamp( $pending ) ) { continue; }
			$steps[(int) $s->id] = array( $row, $state, $b['applied_post_hash'] );
		}
		ksort( $steps, SORT_NUMERIC );
		$approved = array();
		$runtime_compatibility_ids = array();
		$last_after = null;
		foreach ( $steps as list( $row, $state, $recorded_after ) ) {
			if ( ! CC_Assistant_Integrity::hash_matches_state( $expected, $state ) ) { continue; }
			$failure['approved_change_id'] = (int) $row->id;
			if ( array_intersect( $scope, self::scope( $row ) ) ) { $failure['reason'] = 'overlapping_approved_change'; return $failure; }
			$after = self::replay( $row, $state );
			if ( ! $after || ! CC_Assistant_Integrity::hash_matches_state( $recorded_after, $after ) ) {
				$failure['reason'] = 'approved_write_has_unexplained_side_effects';
				if ( $after ) { $failure['comparison_to_current'] = self::differences( $after, $live_state ); }
				return $failure;
			}
			if ( CC_Assistant_Integrity::state_hash( $state ) !== $expected || CC_Assistant_Integrity::state_hash( $after ) !== $recorded_after ) { $runtime_compatibility_ids[] = (int) $row->id; }
			$expected = $recorded_after;
			$last_after = $after;
			$approved[] = (int) $row->id;
			if ( CC_Assistant_Integrity::state_hash( $after ) === $live_hash ) { return array( 'safe' => true, 'approved_ids' => $approved, 'reason' => 'compatible_approved_history', 'ignored_runtime_meta_keys' => CC_Assistant_Integrity::RUNTIME_META, 'legacy_runtime_hash_matches' => $runtime_compatibility_ids ); }
		}
		$failure['reason'] = $approved ? 'approved_history_does_not_reach_current_state' : 'no_matching_complete_snapshot';
		if ( $last_after ) { $failure['comparison_to_current'] = self::differences( $last_after, $live_state ); }
		return $failure;
	}

	/** Names only: no private field values or snapshot payloads in REST diagnostics. */
	private static function differences( array $predicted, array $current ) {
		$out = array( 'scope' => 'Predicted state after this approved change versus current state; differences may include later edits.', 'fields' => array(), 'meta_keys' => array(), 'taxonomies' => array() );
		foreach ( array( 'fields' => 'fields', 'meta' => 'meta_keys', 'terms' => 'taxonomies' ) as $group => $label ) {
			foreach ( array_unique( array_merge( array_keys( $predicted[$group] ?? array() ), array_keys( $current[$group] ?? array() ) ) ) as $key ) {
				$test = $predicted;
				if ( array_key_exists( $key, $current[$group] ?? array() ) ) { $test[$group][$key] = $current[$group][$key]; } else { unset( $test[$group][$key] ); }
				if ( CC_Assistant_Integrity::state_hash( $test ) !== CC_Assistant_Integrity::state_hash( $predicted ) ) { $out[$label][] = $key; }
			}
		}
		return $out;
	}
}

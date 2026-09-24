<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Shared persisted clocks and stable post state; never interprets local time as UTC. */
class CC_Assistant_Integrity {
	/** WordPress publish/cron job markers, never content or SEO configuration. */
	const RUNTIME_META = array( '_pingme', '_encloseme', '_trackbackme' );
	public static function utc_timestamp( $value ) {
		return $value ? (int) strtotime( (string) $value . ' UTC' ) : 0;
	}

	public static function legacy_timestamp( $value ) {
		if ( ! $value ) { return 0; }
		return self::utc_timestamp( get_gmt_from_date( (string) $value ) );
	}

	public static function baseline( $pending ) {
		$value = $pending->pre_check_baseline ?? null;
		$value = is_string( $value ) ? json_decode( $value, true ) : $value;
		return is_array( $value ) ? $value : array();
	}

	public static function queued_timestamp( $pending ) {
		$b = self::baseline( $pending );
		return isset( $b['queued_at_gmt'] ) ? self::utc_timestamp( $b['queued_at_gmt'] ) : self::legacy_timestamp( $pending->created_at );
	}

	/** Internal metadata overrides predict an exact approved write; never accepted from REST. */
	/**
	 * Meta that a mere READ can rewrite: editor locks and builder-generated caches.
	 * Rendering a page to gather evidence writes some of these, and the evidence
	 * gate treats any hash movement as a concurrent edit, so a key missing from
	 * this list makes the gate permanently unsatisfiable for that post rather
	 * than merely noisy. The list ships with the builders we know; extend it for
	 * a different stack with the filter instead of patching this file.
	 *
	 * Never add a content-bearing key here (_elementor_data, SEO meta, prices):
	 * excluding one would hide a real change from the reviewer.
	 */
	public static function volatile_meta_keys() {
		$keys = array(
			'_edit_lock', '_edit_last',
			'_elementor_css', '_elementor_element_cache', '_elementor_page_assets',
			'_elementor_controls_usage', '_elementor_inline_svg',
			'_cc_hero_preload', '_cc_assistant_content_workflow',
			// Essential Addons counts a view on EVERY front-end render, so the
			// loopback fetch that gathers evidence bumps it and the receipt is
			// stale the instant it is written. This deadlocked published pages
			// (drafts and templates never render) until 0.89.9 named it.
			'_eael_post_view_count',
		);
		// This class also runs from tests and CLI paths with no WordPress loaded,
		// so the filter is optional rather than assumed.
		return function_exists( 'apply_filters' ) ? (array) apply_filters( 'cc_assistant_volatile_meta_keys', $keys ) : $keys;
	}

	public static function is_volatile_meta( $key ) {
		return in_array( $key, self::volatile_meta_keys(), true ) || 0 === strpos( $key, '_cc_assistant_last_internal_' );
	}

	/**
	 * Per-key digests of a snapshot, volatile keys already dropped.
	 *
	 * A receipt stores only one hash for the whole post, so when that hash later
	 * moves there is nothing left to say WHICH key moved, and the gate can only
	 * report "state changed". Keeping the per-key digests alongside the hash
	 * costs little and turns that dead end into a named key.
	 */
	public static function state_meta_hashes( array $state ) {
		$meta = is_array( $state['meta'] ?? null ) ? $state['meta'] : array();
		$out = array();
		foreach ( $meta as $key => $value ) {
			if ( self::is_volatile_meta( $key ) || in_array( $key, self::RUNTIME_META, true ) ) { continue; }
			$out[ $key ] = hash( 'sha256', maybe_serialize( $value ) );
		}
		ksort( $out );
		return $out;
	}

	/** Which keys differ between two state_meta_hashes() maps, with a reason per key. */
	public static function meta_hash_diff( array $before, array $after ) {
		$changed = array();
		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $key ) {
			if ( ! isset( $before[ $key ] ) ) { $changed[ $key ] = 'added'; }
			elseif ( ! isset( $after[ $key ] ) ) { $changed[ $key ] = 'removed'; }
			elseif ( $before[ $key ] !== $after[ $key ] ) { $changed[ $key ] = 'modified'; }
		}
		ksort( $changed );
		return $changed;
	}

	/** Which meta keys actually differ between two post_state() snapshots. */
	public static function state_meta_diff( array $before, array $after ) {
		$a = is_array( $before['meta'] ?? null ) ? $before['meta'] : array();
		$b = is_array( $after['meta'] ?? null ) ? $after['meta'] : array();
		$changed = array();
		foreach ( array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) ) as $key ) {
			if ( self::is_volatile_meta( $key ) ) { continue; }
			if ( ( $a[$key] ?? null ) !== ( $b[$key] ?? null ) ) { $changed[] = $key; }
		}
		sort( $changed );
		return $changed;
	}

	public static function post_hash( $post_id, array $meta_overrides = array() ) {
		return self::state_hash( self::post_state( $post_id, $meta_overrides ) );
	}

	public static function post_state( $post_id, array $meta_overrides = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return array(); }
		$fields = array();
		foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status', 'post_author', 'post_parent', 'menu_order' ) as $key ) {
			$fields[ $key ] = (string) ( $post->$key ?? '' );
		}
		$meta = (array) get_post_meta( $post_id );
		foreach ( $meta_overrides as $key => $values ) { $meta[$key] = $values; }
		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$ids = wp_get_object_terms( (int) $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) ) { return array(); }
			$terms[$taxonomy] = $ids;
		}
		return array( 'fields' => $fields, 'meta' => $meta, 'terms' => $terms );
	}

	/** Same fingerprint for a live post or a complete version-2 recovery snapshot. */
	public static function state_hash( array $state, $legacy = false ) {
		if ( ! is_array( $state['fields'] ?? null ) || ! is_array( $state['meta'] ?? null ) || ! is_array( $state['terms'] ?? null ) ) { return ''; }
		$fields = array();
		foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status', 'post_author', 'post_parent', 'menu_order' ) as $key ) {
			$fields[$key] = (string) ( $state['fields'][$key] ?? '' );
		}
		$meta = $state['meta'];
		if ( ! $legacy ) { foreach ( self::RUNTIME_META as $key ) { unset( $meta[$key] ); } }
		// Ignore editor locks and generated caches, which can change on a read.
		foreach ( array_keys( $meta ) as $key ) {
			if ( self::is_volatile_meta( $key ) ) { unset( $meta[ $key ] ); }
		}
		ksort( $meta );
		$terms = array();
		foreach ( $state['terms'] as $taxonomy => $ids ) {
			if ( ! is_array( $ids ) ) { return ''; }
			$ids = array_map( 'intval', $ids );
			sort( $ids, SORT_NUMERIC );
			$terms[ $taxonomy ] = $ids;
		}
		ksort( $terms );
		return hash( 'sha256', wp_json_encode( array( $fields, $meta, $terms ) ) );
	}

	/**
	 * Match existing 0.89.3/4 receipts without replacing their recorded hashes.
	 * Only the three core job flags vary. All content, other metadata and terms
	 * remain byte-for-byte protected. At most 64 candidates; no hash guessing
	 * of content, SEO values, dates or arbitrary plugin metadata.
	 */
	public static function hash_matches_state( $hash, array $state ) {
		if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) || '' === self::state_hash( $state ) ) { return false; }
		if ( hash_equals( $hash, self::state_hash( $state ) ) || hash_equals( $hash, self::state_hash( $state, true ) ) ) { return true; }
		$variants = array( $state );
		foreach ( self::RUNTIME_META as $key ) {
			$values = array( null, array( '1' ) );
			$existing = $state['meta'][$key] ?? null;
			if ( is_array( $existing ) && count( $existing ) <= 32 && ! array_diff( $existing, array( '1' ) ) ) {
				$values[] = $existing;
				if ( '_trackbackme' === $key ) { $values[] = array_merge( $existing, array( '1' ) ); }
			}
			$next = array();
			foreach ( $variants as $candidate ) {
				foreach ( $values as $value ) {
					$copy = $candidate;
					if ( null === $value ) { unset( $copy['meta'][$key] ); } else { $copy['meta'][$key] = $value; }
					$next[] = $copy;
				}
			}
			$variants = $next;
		}
		foreach ( $variants as $candidate ) { if ( hash_equals( $hash, self::state_hash( $candidate, true ) ) ) { return true; } }
		return false;
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Shared persisted clocks and stable post state; never interprets local time as UTC. */
class CC_Assistant_Legacy_Integrity {
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
	public static function post_hash( $post_id, array $meta_overrides = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) { return ''; }
		$fields = array();
		foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status', 'post_author', 'post_parent', 'menu_order' ) as $key ) {
			$fields[ $key ] = (string) ( $post->$key ?? '' );
		}
		// Ignore editor locks and generated caches, which can change on a read.
		$meta = (array) get_post_meta( $post_id );
		foreach ( $meta_overrides as $key => $values ) { $meta[$key] = $values; }
		foreach ( array_keys( $meta ) as $key ) {
			if ( in_array( $key, array( '_edit_lock', '_edit_last', '_elementor_css', '_elementor_element_cache', '_elementor_page_assets', '_elementor_controls_usage', '_elementor_inline_svg', '_cc_hero_preload', '_cc_assistant_content_workflow' ), true )
				|| 0 === strpos( $key, '_cc_assistant_last_internal_' ) ) {
				unset( $meta[ $key ] );
			}
		}
		ksort( $meta );
		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$ids = wp_get_object_terms( (int) $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) ) { return ''; }
			$ids = array_map( 'intval', $ids );
			sort( $ids, SORT_NUMERIC );
			$terms[ $taxonomy ] = $ids;
		}
		ksort( $terms );
		return hash( 'sha256', wp_json_encode( array( $fields, $meta, $terms ) ) );
	}
}

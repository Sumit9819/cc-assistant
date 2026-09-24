<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.45.1 — Visibility for the schema cc-assistant injects itself.
 *
 * The plugin emits two JSON-LD blocks at wp_head from per-post postmeta:
 *   _cc_emergency_service_schema  -> <script data-cc-assistant="emergency-service">
 *   _cc_assistant_schema_jsonld   -> <script data-cc-assistant="page-jsonld">
 *
 * Until now there was NO way to see what the plugin was injecting or on which
 * posts — which is how a prior chat's bulk propose_emergency_service_schema
 * became an invisible site-wide org node that took a code-grep to find. This
 * report() makes that injection auditable: list every post carrying either
 * meta key (with parsed @type + byte size + the live enabled state), or pass a
 * post_id for the full raw blob of one post.
 *
 * Read-only. Changing what's stored still goes through the draft inbox
 * (propose_emergency_service_schema / propose_schema), never here.
 */
class CC_Assistant_Managed_Schema {

	const META_KEYS = array(
		'_cc_emergency_service_schema' => 'emergency-service',
		'_cc_assistant_schema_jsonld'  => 'page-jsonld',
	);

	/**
	 * @param int $post_id 0 = list every post with managed schema; >0 = full detail (incl. raw) for one post.
	 * @return array
	 */
	public static function report( $post_id = 0 ) {
		$post_id = (int) $post_id;
		$enabled = array(
			'emergency-service' => (bool) get_option( 'cc_assistant_emergency_schema_enabled', true ),
			'page-jsonld'       => (bool) get_option( 'cc_assistant_page_schema_enabled', true ),
		);

		if ( $post_id > 0 ) {
			$schemas = array();
			foreach ( self::META_KEYS as $key => $variant ) {
				$raw = get_post_meta( $post_id, $key, true );
				if ( empty( $raw ) || ! is_string( $raw ) ) {
					continue;
				}
				$decoded   = json_decode( $raw, true );
				$schemas[] = array(
					'meta_key' => $key,
					'variant'  => $variant,
					'enabled'  => $enabled[ $variant ],
					'valid'    => is_array( $decoded ),
					'types'    => self::types_of( $decoded ),
					'bytes'    => strlen( $raw ),
					'raw'      => $raw,
				);
			}
			return array(
				'post_id'   => $post_id,
				'title'     => html_entity_decode( wp_strip_all_tags( (string) get_the_title( $post_id ) ) ),
				'status'    => get_post_status( $post_id ),
				'permalink' => get_permalink( $post_id ),
				'enabled'   => $enabled,
				'schemas'   => $schemas,
			);
		}

		global $wpdb;
		$keys         = array_keys( self::META_KEYS );
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are %s, values bound below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)",
				$keys
			)
		);

		$by_post = array();
		foreach ( (array) $rows as $r ) {
			$pid     = (int) $r->post_id;
			$variant = isset( self::META_KEYS[ $r->meta_key ] ) ? self::META_KEYS[ $r->meta_key ] : $r->meta_key;
			$decoded = json_decode( (string) $r->meta_value, true );
			if ( ! isset( $by_post[ $pid ] ) ) {
				$by_post[ $pid ] = array(
					'post_id'  => $pid,
					'title'    => html_entity_decode( wp_strip_all_tags( (string) get_the_title( $pid ) ) ),
					'status'   => get_post_status( $pid ),
					'variants' => array(),
				);
			}
			$by_post[ $pid ]['variants'][] = array(
				'variant' => $variant,
				'enabled' => isset( $enabled[ $variant ] ) ? $enabled[ $variant ] : null,
				'valid'   => is_array( $decoded ),
				'types'   => self::types_of( $decoded ),
				'bytes'   => strlen( (string) $r->meta_value ),
			);
		}

		$posts = array_values( $by_post );
		return array(
			'enabled'    => $enabled,
			'post_count' => count( $posts ),
			'note'       => empty( $posts )
				? 'No posts carry plugin-managed schema postmeta.'
				: 'Pass a post_id to this tool to see the full raw JSON-LD for one post. Disable an emitter site-wide via option cc_assistant_emergency_schema_enabled / cc_assistant_page_schema_enabled (default true).',
			'posts'      => $posts,
		);
	}

	/** Collect unique @type values from a decoded node or @graph bundle. */
	private static function types_of( $decoded ) {
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$nodes = ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) ? $decoded['@graph'] : array( $decoded );
		$types = array();
		foreach ( $nodes as $n ) {
			if ( is_array( $n ) && isset( $n['@type'] ) ) {
				foreach ( (array) $n['@type'] as $t ) {
					$types[] = $t;
				}
			}
		}
		return array_values( array_unique( $types ) );
	}
}

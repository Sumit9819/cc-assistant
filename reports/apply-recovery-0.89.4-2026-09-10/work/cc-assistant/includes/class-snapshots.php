<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-integrity.php';

class CC_Assistant_Snapshots {
	public static function snapshot_post( $post_id, $type = 'pre_write', $note = '' ) {
		global $wpdb;
		$post = get_post( $post_id );
		if ( ! $post ) { return new WP_Error( 'snapshot_post_missing', 'Cannot snapshot a missing post.' ); }
		$fields = array();
		foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status', 'post_author', 'post_parent', 'menu_order', 'post_date', 'post_date_gmt', 'comment_status', 'ping_status', 'post_password' ) as $key ) {
			if ( isset( $post->$key ) ) { $fields[ $key ] = $post->$key; }
		}
		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$ids = wp_get_object_terms( (int) $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) ) { return $ids; }
			$terms[ $taxonomy ] = array_map( 'intval', $ids );
		}
		// A versioned envelope in the existing LONGTEXT column avoids reinterpreting
		// historical records or requiring a schema migration before a REST write.
		$state = array( 'cc_snapshot_version' => 2, 'created_at_gmt' => current_time( 'mysql', true ),
			'fields' => $fields, 'terms' => $terms, 'meta' => (array) get_post_meta( $post_id ) );
		$ok = $wpdb->insert( $wpdb->prefix . 'cc_snapshots', array(
			'post_id' => $post_id, 'snapshot_type' => $type,
			'post_content' => $post->post_content, 'post_title' => $post->post_title,
			'post_meta' => maybe_serialize( $state ),
			'elementor_data' => get_post_meta( $post_id, '_elementor_data', true ),
			'created_at' => current_time( 'mysql' ), 'created_by' => get_current_user_id() ?: null, 'note' => $note,
		) );
		if ( false === $ok || ! $wpdb->insert_id ) {
			return new WP_Error( 'snapshot_failed', 'The recovery snapshot could not be saved. No content write is permitted.' );
		}
		return (int) $wpdb->insert_id;
	}

	public static function list_snapshots( $post_id = null, $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_snapshots';
		if ( $post_id ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT id, post_id, snapshot_type, created_at, note FROM $table WHERE post_id = %d ORDER BY id DESC LIMIT %d", $post_id, $limit ) );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, post_id, snapshot_type, created_at, note FROM $table ORDER BY id DESC LIMIT %d", $limit ) );
	}

	public static function get_snapshot( $snapshot_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cc_snapshots WHERE id = %d", $snapshot_id ) );
	}

	public static function count_snapshots() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cc_snapshots" );
	}

	public static function state( $snap ) {
		$raw = $snap->post_meta ? maybe_unserialize( $snap->post_meta ) : array();
		if ( is_array( $raw ) && 2 === ( $raw['cc_snapshot_version'] ?? null ) && isset( $raw['meta'], $raw['fields'] ) ) { return $raw; }
		return array( 'cc_snapshot_version' => 1, 'meta' => is_array( $raw ) ? $raw : array(),
			'fields' => array( 'post_title' => (string) $snap->post_title, 'post_content' => (string) $snap->post_content ), 'terms' => array() );
	}

	public static function restore_snapshot( $snapshot_id, $force = false ) {
		$snap = self::get_snapshot( $snapshot_id );
		if ( ! $snap ) { return new WP_Error( 'not_found', 'Snapshot not found.' ); }
		$post_id = (int) $snap->post_id;
		$post = get_post( $post_id );
		if ( ! $post ) { return new WP_Error( 'post_not_found', 'Original post no longer exists.' ); }
		$state = self::state( $snap );
		$snap_ts = isset( $state['created_at_gmt'] ) ? CC_Assistant_Integrity::utc_timestamp( $state['created_at_gmt'] ) : CC_Assistant_Integrity::legacy_timestamp( $snap->created_at );
		$post_ts = CC_Assistant_Integrity::utc_timestamp( $post->post_modified_gmt );
		if ( ! $force && $post_ts > $snap_ts + 300 ) {
			return new WP_Error( 'snapshot_drift_detected', 'This post has later edits. Review the snapshot and use force=true to intentionally replace them.', array( 'status' => 409, 'drift_seconds' => $post_ts - $snap_ts ) );
		}
		foreach ( $state['terms'] as $taxonomy => $ids ) {
			if ( ! taxonomy_exists( $taxonomy ) ) { return new WP_Error( 'snapshot_taxonomy_missing', 'Restore requires taxonomy: ' . $taxonomy ); }
		}
		$pre_restore_id = self::snapshot_post( $post_id, 'pre_restore', sprintf( 'Pre-restore of snapshot #%d', $snapshot_id ) );
		if ( is_wp_error( $pre_restore_id ) ) { return $pre_restore_id; }
		if ( function_exists( 'wp_save_post_revision' ) ) { wp_save_post_revision( $post_id ); }
		$updated = wp_update_post( wp_slash( array_merge( $state['fields'], array( 'ID' => $post_id ) ) ), true );
		if ( is_wp_error( $updated ) || ! $updated ) { return is_wp_error( $updated ) ? $updated : new WP_Error( 'restore_failed', 'Post fields could not be restored.' ); }
		$meta = $state['meta'];
		// Remove introduced plugin/builder keys; retain unrelated plugins' new
		// metadata. Captured keys are restored, including JSON escaping.
		foreach ( array_keys( (array) get_post_meta( $post_id ) ) as $key ) {
			if ( ! array_key_exists( $key, $meta ) && ( 0 === strpos( $key, '_cc_' ) || 0 === strpos( $key, '_elementor_' ) ) ) {
				delete_post_meta( $post_id, $key );
				if ( metadata_exists( 'post', $post_id, $key ) ) { return new WP_Error( 'restore_meta_failed', 'Could not remove introduced metadata: ' . $key ); }
			}
		}
		foreach ( $meta as $key => $values ) {
			if ( ! is_array( $values ) || '_edit_lock' === $key ) { continue; }
			delete_post_meta( $post_id, $key );
			if ( metadata_exists( 'post', $post_id, $key ) ) { return new WP_Error( 'restore_meta_failed', 'Could not replace captured metadata: ' . $key ); }
			foreach ( $values as $value ) {
				if ( ! add_post_meta( $post_id, $key, wp_slash( maybe_unserialize( $value ) ) ) ) {
					return new WP_Error( 'restore_meta_failed', 'Could not restore metadata: ' . $key );
				}
			}
		}
		foreach ( $state['terms'] as $taxonomy => $ids ) {
			$r = wp_set_object_terms( $post_id, array_map( 'intval', $ids ), $taxonomy, false );
			if ( is_wp_error( $r ) ) { return $r; }
		}
		if ( 1 === $state['cc_snapshot_version'] && ! empty( $snap->elementor_data ) ) {
			update_post_meta( $post_id, '_elementor_data', wp_slash( (string) $snap->elementor_data ) );
		}
		clean_post_cache( $post_id );
		if ( class_exists( 'CC_Assistant_Apply' ) ) {
			CC_Assistant_Apply::regenerate_theme_builder_conditions();
			CC_Assistant_Apply::flush_elementor_css_cache();
		}
		return array( 'restored_from' => (int) $snapshot_id, 'pre_restore_id' => $pre_restore_id,
			'post_id' => $post_id, 'legacy_partial' => 1 === $state['cc_snapshot_version'],
			'warning' => 1 === $state['cc_snapshot_version'] ? 'Legacy snapshot has no saved slug, status, author or taxonomy state; those cannot be reconstructed.' : '' );
	}
}

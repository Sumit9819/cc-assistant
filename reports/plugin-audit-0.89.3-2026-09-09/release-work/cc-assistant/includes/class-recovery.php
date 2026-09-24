<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-integrity.php';

/** Durable recovery data for operations outside a single post. */
class CC_Assistant_Recovery {
	public static function save_baseline( $id, $baseline ) {
		global $wpdb;
		$json = wp_json_encode( $baseline );
		if ( false === $json || false === $wpdb->update( $wpdb->prefix . 'cc_pending_changes',
			array( 'pre_check_baseline' => $json ), array( 'id' => (int) $id ) ) ) {
			return new WP_Error( 'recovery_record_failed', 'Could not save the operation recovery record.' );
		}
		return true;
	}

	public static function capture( $pending, &$proposed, $snapshot_id ) {
		global $wpdb;
		$baseline = CC_Assistant_Integrity::baseline( $pending );
		$baseline['snapshot_id'] = $snapshot_id;
		$before = null;
		if ( in_array( $pending->change_type, array( 'delete_redirect', 'untrash_redirect' ), true ) ) {
			if ( ! class_exists( '\RankMath\Redirections\DB' ) ) { return new WP_Error( 'rank_math_unavailable', 'Rank Math is required.' ); }
			$before = self::redirect_row( (int) $proposed['id'] );
			if ( empty( $before['id'] ) ) { return new WP_Error( 'redirect_not_found', 'The redirect no longer exists.' ); }
		} elseif ( 'asset_reference_replace' === $pending->change_type ) {
			require_once __DIR__ . '/class-asset-references.php';
			$before = array();
			foreach ( $proposed['targets'] as $i => $target ) {
				$value = CC_Assistant_Asset_References::read_location( $target );
				if ( null === $value ) { continue; }
				list( $after, $count, $error ) = CC_Assistant_Asset_References::rewrite_value( $value, $proposed['old_url'], $proposed['new_url'] );
				if ( $error || ! $count ) { continue; }
				$before[] = array( 'location' => $target, 'before' => $value, 'after_hash' => hash( 'sha256', $after ) );
				$proposed['targets'][ $i ]['recovery_sha256'] = hash( 'sha256', $value );
			}
		}
		if ( null !== $before ) {
			$ok = $wpdb->insert( $wpdb->prefix . 'cc_snapshots', array(
				'post_id' => (int) $pending->post_id, 'snapshot_type' => 'operation_recovery',
				'post_title' => $pending->change_type, 'post_content' => '', 'elementor_data' => '',
				'post_meta' => maybe_serialize( array( 'cc_recovery_version' => 1, 'before' => $before ) ),
				'created_at' => current_time( 'mysql' ), 'created_by' => get_current_user_id(),
				'note' => 'Recovery for pending #' . (int) $pending->id,
			) );
			if ( false === $ok || ! $wpdb->insert_id ) { return new WP_Error( 'snapshot_failed', 'Operation recovery could not be saved. No write is permitted.' ); }
			$baseline['operation_snapshot_id'] = (int) $wpdb->insert_id;
		}
		return self::save_baseline( $pending->id, $baseline );
	}

	public static function complete( $pending, $proposed, $result, $snapshot_id ) {
		$fresh = CC_Assistant_Pending_Changes::get( $pending->id );
		$b = CC_Assistant_Integrity::baseline( $fresh );
		$b['snapshot_id'] = $snapshot_id;
		if ( $pending->post_id ) { $b['applied_post_hash'] = CC_Assistant_Integrity::post_hash( (int) $pending->post_id ); }
		if ( 'create_redirect' === $pending->change_type ) {
			$id = (int) ( $result['redirection_id'] ?? 0 );
			if ( ! $id ) { return new WP_Error( 'redirect_write_failed', 'No saved redirect ID was returned.' ); }
			$b['created_redirect_id'] = $id;
			$b['redirect_after'] = self::redirect_state( self::redirect_row( $id ) );
		}
		return self::save_baseline( $pending->id, $b );
	}

	private static function redirect_row( $id ) {
		$row = \RankMath\Redirections\DB::get_redirection_by_id( $id, 'all' );
		return is_array( $row ) ? $row : array();
	}

	private static function redirect_state( $row ) {
		$state = array();
		foreach ( array( 'sources', 'url_to', 'header_code', 'status' ) as $key ) { $state[ $key ] = $row[ $key ] ?? null; }
		return $state;
	}

	public static function rollback( $pending ) {
		require_once __DIR__ . '/class-snapshots.php';
		$b = CC_Assistant_Integrity::baseline( $pending );
		if ( in_array( $pending->change_type, array( 'category_update', 'elementor_full_import', 'elementor_section_content_replace', 'emergency_service_schema', 'trash_post' ), true ) ) {
			$sid = (int) ( $b['snapshot_id'] ?? 0 );
			if ( ! $sid ) { return new WP_Error( 'legacy_recovery_missing', 'This old change has no linked recovery snapshot. Select its snapshot in the Snapshots screen.' ); }
			if ( empty( $b['applied_post_hash'] ) || ! hash_equals( $b['applied_post_hash'], CC_Assistant_Integrity::post_hash( (int) $pending->post_id ) ) ) {
				return new WP_Error( 'rollback_conflict', 'Later edits exist. Review a snapshot restore rather than overwriting them with this rollback.' );
			}
			return CC_Assistant_Snapshots::restore_snapshot( $sid, true );
		}
		if ( 'asset_reference_replace' === $pending->change_type ) {
			require_once __DIR__ . '/class-asset-references.php';
			$before = self::operation_before( $b );
			if ( is_wp_error( $before ) ) { return $before; }
			return CC_Assistant_Asset_References::restore_locations( $before );
		}
		if ( ! class_exists( '\RankMath\Redirections\DB' ) ) { return new WP_Error( 'rank_math_unavailable', 'Rank Math is required.' ); }
		if ( 'create_redirect' === $pending->change_type ) {
			$id = (int) ( $b['created_redirect_id'] ?? 0 );
			if ( ! $id ) { return new WP_Error( 'legacy_recovery_missing', 'This old change did not record its created redirect ID. Review it in Rank Math.' ); }
			$row = self::redirect_row( $id );
			if ( ! $row ) { return true; }
			if ( self::redirect_state( $row ) !== ( $b['redirect_after'] ?? null ) ) {
				return new WP_Error( 'rollback_conflict', 'The redirect was edited after this operation.' );
			}
			$r = \RankMath\Redirections\DB::delete( array( $id ) );
		} else {
			$before = self::operation_before( $b );
			if ( is_wp_error( $before ) ) { return $before; }
			$id = (int) $before['id'];
			$row = self::redirect_row( $id );
			if ( 'delete_redirect' === $pending->change_type ) {
				if ( $row ) { return new WP_Error( 'rollback_conflict', 'A redirect already occupies the original ID.' ); }
				// Recreate all sources and settings through Rank Math; it assigns a
				// fresh ID after permanent deletion.
				$before['sources'] = maybe_unserialize( $before['sources'] );
				$patterns = array_column( $before['sources'], 'pattern' );
				// Refuse a new redirect for a source that was reassigned after deletion.
				for ( $page = 1; ; $page++ ) {
					$batch = \RankMath\Redirections\DB::get_redirections( array( 'limit' => 100, 'paged' => $page ) );
					foreach ( $batch['redirections'] as $existing ) {
						$sources = maybe_unserialize( $existing['sources'] );
						if ( is_array( $sources ) && array_intersect( $patterns, array_column( $sources, 'pattern' ) ) ) {
							return new WP_Error( 'rollback_conflict', 'A newer redirect uses an original source. Review it in Rank Math.' );
						}
					}
					if ( $page * 100 >= (int) $batch['count'] ) { break; }
				}
				unset( $before['id'] );
				$r = \RankMath\Redirections\DB::add( $before );
			} else {
				$expected = self::redirect_state( $before );
				$expected['status'] = 'active';
				if ( self::redirect_state( $row ) !== $expected ) { return new WP_Error( 'rollback_conflict', 'The restored redirect was edited after this operation.' ); }
				$r = \RankMath\Redirections\DB::change_status( array( $id ), $before['status'] );
			}
		}
		if ( ! $r ) { return new WP_Error( 'redirect_rollback_failed', 'Rank Math could not restore the redirect state.' ); }
		if ( class_exists( '\RankMath\Redirections\Cache' ) ) { \RankMath\Redirections\Cache::purge( array( $id ) ); }
		if ( class_exists( 'CC_Assistant_URL_Resolver' ) ) { CC_Assistant_URL_Resolver::flush(); }
		return true;
	}

	private static function operation_before( $baseline ) {
		$snap = CC_Assistant_Snapshots::get_snapshot( (int) ( $baseline['operation_snapshot_id'] ?? 0 ) );
		$state = $snap ? maybe_unserialize( $snap->post_meta ) : null;
		if ( ! is_array( $state ) || 1 !== ( $state['cc_recovery_version'] ?? null ) ) {
			return new WP_Error( 'legacy_recovery_missing', 'This change has no complete operation recovery record.' );
		}
		return $state['before'];
	}
}

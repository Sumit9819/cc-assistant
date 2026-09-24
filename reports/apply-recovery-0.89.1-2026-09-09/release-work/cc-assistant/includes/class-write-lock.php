<?php
/** Coordinate CC Assistant approvals and rollbacks sharing a WordPress database. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class CC_Assistant_Write_Lock {
	public static function name() {
		global $wpdb;
		return 'cc_write_' . substr( hash( 'sha256', ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . ':' . $wpdb->prefix ), 0, 48 );
	}
	public static function acquire() {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', self::name() ) );
		if ( '1' !== (string) $result ) {
			return new WP_Error( 'write_lock_unavailable', 'Another CC Assistant approval or rollback is running, or the database could not obtain the write lock. Retry after it finishes. This proposal has not been claimed.', array( 'status' => 409 ) );
		}
		return true;
	}
	public static function release() {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::name() ) );
	}
}

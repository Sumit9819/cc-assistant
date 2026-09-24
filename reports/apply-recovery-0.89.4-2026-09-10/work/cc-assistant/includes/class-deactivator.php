<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Deactivator {

	public static function deactivate() {
		// Preserve data on deactivation. Tables are dropped only on uninstall.
		$hooks = array();
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( $events ) as $hook ) {
				if ( 0 === strpos( $hook, 'cc_assistant_' ) ) { $hooks[ $hook ] = true; }
			}
		}
		foreach ( array_keys( $hooks ) as $hook ) { wp_unschedule_hook( $hook ); }
	}
}

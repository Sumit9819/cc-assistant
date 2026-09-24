<?php
/** Exercise opt-in cleanup without any database or WordPress installation. */
define( 'WP_UNINSTALL_PLUGIN', true );
class CleanupDB {
    public $prefix = 'wp_'; public $options = 'wp_options'; public $postmeta = 'wp_postmeta'; public $queries = array(); public $prefix_pattern;
    public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
    public function prepare( $sql, $value ) { $this->prefix_pattern = $value; return str_replace( '%s', "'" . addslashes( $value ) . "'", $sql ); }
    public function query( $sql ) { $this->queries[] = $sql; return 1; }
}
function get_option( $key ) { return $GLOBALS['opt_in'] ?? false; }
function delete_option( $key ) {}
function wp_clear_scheduled_hook( $hook, $args = array() ) { foreach ( $GLOBALS['events'][$hook] ?? array() as $i => $event ) { if ( $event === $args ) unset( $GLOBALS['events'][$hook][$i] ); } }
function wp_unschedule_hook( $hook ) { unset( $GLOBALS['events'][$hook] ); }
function wp_cache_flush() {}
function check( $pass, $label ) { if ( ! $pass ) throw new RuntimeException( $label ); echo "PASS: $label\n"; }
$wpdb = new CleanupDB(); $GLOBALS['events'] = array( 'cc_assistant_warm_rendered' => array( array( 971 ), array( 5525 ) ), 'cc_assistant_page_facts_capture' => array( array( 971 ) ), 'unrelated_cron' => array( array( 2 ) ) );
require dirname( __DIR__ ) . '/uninstall.php';
check( empty( $wpdb->queries ) && isset( $GLOBALS['events']['cc_assistant_warm_rendered'] ), 'Data-retention default performs no destructive cleanup' );
$GLOBALS['opt_in'] = true;
require dirname( __DIR__ ) . '/uninstall.php';
check( 'cc\\_assistant\\_%' === $wpdb->prefix_pattern, 'Option cleanup escapes underscores so unrelated lookalike names do not match' );
check( in_array( 'DROP TABLE IF EXISTS `wp_cc_page_facts`', $wpdb->queries, true ), 'Opt-in cleanup includes the page-facts table' );
check( ! isset( $GLOBALS['events']['cc_assistant_warm_rendered'] ) && ! isset( $GLOBALS['events']['cc_assistant_page_facts_capture'] ) && isset( $GLOBALS['events']['unrelated_cron'] ), 'Per-post scheduled tasks are removed while unrelated schedules survive' );

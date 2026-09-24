<?php
require __DIR__ . '/publication-recovery-test.php';
define( 'CC_ASSISTANT_HTTP_UA', 'fixture' );
function add_query_arg( $args, $url ) { return $url; }
function wp_remote_get( $url, $args ) {
    $GLOBALS['post']->post_content = 'Concurrent edit during slow baseline fetch';
    return new WP_Error( 'fixture_no_network' );
}
$args = publication_fixture();
$count = $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_cc_pending_changes' );
$r = CC_Assistant_Pending_Changes::queue( array( 'post_id' => 1, 'change_type' => 'post_content_update', 'proposed_value' => '{"content":"Reviewed proposal"}' ) );
check( err( $r, 'evidence_state_changed' ), 'Concurrent edit during baseline capture is rejected before queue insertion' );
check( $count === $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_cc_pending_changes' ), 'Failed final evidence check does not insert or supersede pending work' );

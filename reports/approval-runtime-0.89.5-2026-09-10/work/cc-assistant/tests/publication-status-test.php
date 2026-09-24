<?php
require __DIR__ . '/fixtures/safety-harness.php';
$method = new ReflectionMethod( 'CC_Assistant_Apply', 'apply_post_field' );
foreach ( array( 'publish', 'future', 'trash', 'invented-status', '' ) as $status ) {
    reset_review();
    $r = $method->invoke( null, 1, array( 'field' => 'post_status', 'value' => $status ) );
    check( err( $r, 'status_transition_requires_dedicated_proposal' ) && ! $GLOBALS['write_count'] && 'draft' === $GLOBALS['post']->post_status,
        'Generic field update refuses unsafe or unsupported status: ' . $status );
}
foreach ( array( 'draft', 'pending', 'private' ) as $status ) {
    reset_review();
    $r = $method->invoke( null, 1, array( 'field' => 'post_status', 'value' => $status ) );
    check( ! is_wp_error( $r ) && $status === $GLOBALS['post']->post_status, 'Reviewed non-public status remains supported: ' . $status );
}
reset_review(); $id = pending( 'meta_update' );
$GLOBALS['wpdb']->update( 'wp_cc_pending_changes', array( 'proposed_value' => '{"field":"post_status","value":"publish"}' ), array( 'id' => $id ) );
$r = CC_Assistant_Apply::apply_pending( $id, 17, true );
check( err( $r, 'status_transition_requires_dedicated_proposal' ) && ! $GLOBALS['write_count'], 'Previously queued generic publication cannot bypass the guard at approval' );
echo "Publication status assertions: $tests\n";

<?php
require __DIR__ . '/fixtures/safety-harness.php';
class SupersedeRaceDB extends ReviewDB {
    public function query( $sql ) {
        if ( ! empty( $GLOBALS['review_during_supersede'] ) && str_contains( $sql, 'SET superseded_by =' ) ) {
            $GLOBALS['review_during_supersede'] = false;
            parent::query( "UPDATE wp_cc_pending_changes SET status = 'applying' WHERE id = 1" );
        }
        return parent::query( $sql );
    }
}
reset_review();
$older = pending(); $newer = pending();
$args = array( 'post_id' => 1, 'change_type' => 'post_content_update', 'proposed_value' => '{"content":"New body"}' );
// Two requests have inserted; the older request resumes after the newer row exists.
CC_Assistant_Pending_Changes::mark_older_as_superseded( $older, $args );
check( empty( CC_Assistant_Pending_Changes::get( $newer )->superseded_by ), 'An older request never supersedes a newer proposal' );
CC_Assistant_Pending_Changes::mark_older_as_superseded( $newer, $args );
check( (int) CC_Assistant_Pending_Changes::get( $older )->superseded_by === $newer && empty( CC_Assistant_Pending_Changes::get( $newer )->superseded_by ), 'Concurrent proposals retain the newest reviewable intent without a cycle' );
reset_review(); $GLOBALS['wpdb'] = new SupersedeRaceDB();
$older = pending(); $newer = pending(); $GLOBALS['review_during_supersede'] = true;
CC_Assistant_Pending_Changes::mark_older_as_superseded( $newer, $args );
check( 'applying' === CC_Assistant_Pending_Changes::get( $older )->status && empty( CC_Assistant_Pending_Changes::get( $older )->superseded_by ), 'A proposal claimed during the candidate read cannot be superseded' );
echo "Supersede concurrency assertions: $tests\n";

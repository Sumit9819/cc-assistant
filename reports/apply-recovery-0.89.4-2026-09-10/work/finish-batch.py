from pathlib import Path
p=Path(__file__).resolve().parent/'cc-assistant/includes/class-approval-batch.php'
p.write_text('''<?php
/** Request-local selection integrity; persisted recovery records prove sibling changes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CC_Assistant_Approval_Batch {
    private $selected = array();
    public function __construct( array $ids ) {
        foreach ( $ids as $id ) {
            $row = CC_Assistant_Pending_Changes::get( $id );
            if ( $row ) { $this->selected[(int) $id] = self::signature( $row ); }
        }
    }
    private static function signature( $row ) {
        return hash( 'sha256', wp_json_encode( array( $row->post_id, $row->change_type, $row->proposed_value, $row->current_value, $row->pre_check_baseline, $row->superseded_by ?? null ) ) );
    }
    public function matches( $row ) {
        return empty( $row->superseded_by ) && isset( $this->selected[(int) $row->id] ) && $this->selected[(int) $row->id] === self::signature( $row );
    }
}
'''.replace('    ','\t'),encoding='utf-8')

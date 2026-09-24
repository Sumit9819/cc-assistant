<?php
/** Actual create -> queue -> workflow binding with real source hashes, isolated WP records. */
require __DIR__ . '/content-scope-test.php';
require CC_ASSISTANT_DIR . 'includes/class-apply.php';
require CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
function wp_parse_args( $a, $b ) { return array_merge( $b, $a ); }
function current_time( $type, $gmt = false ) { return '2026-09-09 12:00:00'; }
function sanitize_key( $s ) { return $s; }
function sanitize_textarea_field( $s ) { return $s; }
function wp_kses_post( $s ) { return $s; }
function apply_filters( $name, $value ) { return $value; }
function wp_slash( $v ) { return $v; }
function get_userdata( $id ) { return (object) array( 'ID' => $id, 'display_name' => 'Established Organization' ); }
function user_can( $u, $cap ) { return true; }
function is_multisite() { return false; }
function get_plugins() { return array(); }
function get_site_option( $key, $default = false ) { return $default; }
function get_transient( $k ) { return false; }
function delete_transient( $k ) {}
function get_object_taxonomies( $type ) { return array(); }
function get_preview_post_link( $id ) { return 'https://scope.test/?preview=' . $id; }
function get_edit_post_link( $id, $context ) { return 'https://scope.test/wp-admin/post.php?post=' . $id; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function wp_insert_post( $args, $wp_error ) { $GLOBALS['posts'][901] = (object) array_merge( $args, array( 'ID' => 901, 'post_name' => 'new-draft', 'post_modified_gmt' => '2026-09-09 12:00:00' ) ); return 901; }
class DraftBasisDB extends ScopeDB {
    public $insert_id = 0; public $pending;
    public function insert( $table, $row ) { $this->insert_id = 71; $this->pending = (object) array_merge( $row, array( 'id' => 71 ) ); return 1; }
    public function get_results( $sql ) { return array(); }
    public function get_row( $sql ) { return $this->pending; }
}
$GLOBALS['wpdb'] = new DraftBasisDB();
$workflow = CC_Assistant_Content_Workflow::prepare();
$r = CC_Assistant_Apply::create_draft_post( array( 'post_type' => 'post', 'title' => 'Useful new article', 'content' => 'A practical explanation.', 'author_id' => 4, 'workflow_id' => $workflow['record_id'], 'summary' => 'Draft fixture' ) );
if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_message() ); }
$row = $GLOBALS['wpdb']->pending;
$baseline = CC_Assistant_Integrity::baseline( $row );
check( 'New draft publication carries its actual source workflow', $baseline['publication_workflow_id'] === $workflow['record_id'] && ! empty( $baseline['publication_workflow_basis']['source_basis'] ) );
check( 'Creation preserves draft status and explicit organization author', 'draft' === $GLOBALS['posts'][901]->post_status && 4 === $GLOBALS['posts'][901]->post_author );
check( 'Initial workflow basis is valid before any source changes', true === CC_Assistant_Evidence_Gate::validate_apply( $row ) );
$GLOBALS['posts'][100]->post_content .= ' Changed service information after proposal.';
$proof = CC_Assistant_Evidence_Gate::validate_apply( $row );
check( 'Source changes block initial publication even when the draft itself is unchanged', is_wp_error( $proof ) && 'publication_sources_changed' === $proof->get_error_code() );

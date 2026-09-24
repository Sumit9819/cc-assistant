<?php
/** Run with wp eval-file against a disposable WordPress + Elementor database only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'cc_guard_test' !== DB_NAME ) { throw new RuntimeException( 'Disposable cc_guard_test database required.' ); }
require_once CC_ASSISTANT_DIR . 'includes/class-activator.php';
CC_Assistant_Activator::activate();
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%cc_assistant_evidence_%'" );
wp_cache_flush();
$GLOBALS['guard_test_count'] = 0;
function guard_check( $label, $condition, $detail = null ) {
	++$GLOBALS['guard_test_count'];
	if ( ! $condition ) { throw new RuntimeException( $label . ': ' . wp_json_encode( is_wp_error( $detail ) ? array( $detail->get_error_code(), $detail->get_error_message(), $detail->get_error_data() ) : $detail ) ); }
	echo "PASS $label\n";
}
function guard_request( $route, $params = array(), $method = 'GET' ) {
	$r = new WP_REST_Request( $method, '/cc-assistant/v1/' . $route );
	foreach ( $params as $key => $value ) { $r->set_param( $key, $value ); }
	return rest_do_request( $r );
}
// Create fixture posts before entering a client request: they are not new-draft exceptions.
wp_set_current_user( 1 );
$post_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Guard fixture', 'post_status' => 'draft', 'post_content' => 'Original fixture content' ), true );
$published = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Published fixture', 'post_status' => 'publish', 'post_content' => 'Public fixture content' ), true );
define( 'REST_REQUEST', true );
rest_get_server();
require_once CC_ASSISTANT_DIR . 'includes/class-elementor-validation.php';
require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
$args = array( 'post_id' => $post_id, 'change_type' => 'post_content_update', 'current_value' => wp_json_encode( array( 'content' => 'Original fixture content' ) ), 'proposed_value' => wp_json_encode( array( 'content' => 'Changed fixture content' ) ) );
$r = CC_Assistant_Pending_Changes::queue( $args );
guard_check( 'Shared queue rejects missing identity', is_wp_error( $r ) && 'evidence_identity_required' === $r->get_error_code(), $r );
$identity = guard_request( 'whoami' );
guard_check( 'Real whoami endpoint establishes server receipt', $identity->get_status() === 200 && true === CC_Assistant_Evidence_Gate::identity_check(), $identity->get_data() );
$r = CC_Assistant_Pending_Changes::queue( $args );
guard_check( 'Shared queue rejects missing page evidence', is_wp_error( $r ) && 'page_evidence_required' === $r->get_error_code(), $r );
$read = guard_request( 'posts/' . $post_id, array( 'slim' => false ) );
guard_check( 'Real draft read creates evidence', 200 === $read->get_status(), $read->get_data() );
$proof = CC_Assistant_Evidence_Gate::validate_queue( $args );
guard_check( 'Draft evidence accepted at shared boundary', is_array( $proof ) && isset( $proof['posts'][$post_id] ), $proof );
$uuid = 'fixture-app-a';
$GLOBALS['wp_rest_application_password_uuid'] = $uuid;
$r = CC_Assistant_Evidence_Gate::identity_check();
guard_check( 'Different Application Password cannot borrow identity proof', is_wp_error( $r ), $r );
$GLOBALS['wp_rest_application_password_uuid'] = null;
$args['pre_check_baseline'] = array( 'evidence' => array( 'forged' => true ) );
$pending_id = CC_Assistant_Pending_Changes::queue( $args );
guard_check( 'Observed draft proposal stored', is_int( $pending_id ) && $pending_id > 0, $pending_id );
$baseline = CC_Assistant_Integrity::baseline( CC_Assistant_Pending_Changes::get( $pending_id ) );
guard_check( 'Client supplied evidence replaced with server proof', isset( $baseline['evidence']['posts'][$post_id] ) && ! isset( $baseline['evidence']['forged'] ), $baseline );
$r = CC_Assistant_Apply::apply_pending( $pending_id, 1, true );
guard_check( 'Observed proposal applies in real WordPress', ! is_wp_error( $r ) && 'Changed fixture content' === get_post_field( 'post_content', $post_id ), $r );
$r = CC_Assistant_Apply::rollback_pending( $pending_id, 1 );
guard_check( 'Real rollback restores original content and status', ! is_wp_error( $r ) && 'Original fixture content' === get_post_field( 'post_content', $post_id ) && 'rolled_back' === CC_Assistant_Pending_Changes::get( $pending_id )->status, $r );
guard_request( 'posts/' . $post_id, array( 'slim' => false ) );
$pending_id = CC_Assistant_Pending_Changes::queue( $args );
guard_check( 'Fresh proposal after rollback stored', is_int( $pending_id ), $pending_id );
update_post_meta( $post_id, '_fixture_external_edit', 'same-second change' );
$r = CC_Assistant_Apply::apply_pending( $pending_id, 1, true );
guard_check( 'Changed evidence blocks approval without consuming proposal', is_wp_error( $r ) && 'evidence_state_changed' === $r->get_error_code() && 'pending' === CC_Assistant_Pending_Changes::get( $pending_id )->status, $r );
guard_request( 'posts/' . $post_id, array( 'slim' => false ) );
$pending_id = CC_Assistant_Pending_Changes::queue( $args );
$blog_public = get_option( 'blog_public' ); update_option( 'blog_public', '0' === (string) $blog_public ? '1' : '0' );
$r = CC_Assistant_Apply::apply_pending( $pending_id, 1, true );
guard_check( 'Changed SEO environment blocks approval', is_wp_error( $r ) && 'environment_changed' === $r->get_error_code(), $r );
update_option( 'blog_public', $blog_public );
guard_request( 'posts/' . $published, array( 'slim' => false ) );
$pub_args = array_merge( $args, array( 'post_id' => $published ) );
$r = CC_Assistant_Evidence_Gate::validate_queue( $pub_args );
guard_check( 'Editor storage read cannot prove published HTML', is_wp_error( $r ) && 'page_evidence_required' === $r->get_error_code(), $r );
// Supply deterministic server HTML only for the site's page capture.
$fixture_html = function ( $pre, $a, $url ) {
	if ( ! str_starts_with( $url, 'http://cc-guard.test' ) ) { return $pre; }
	if ( ! empty( $GLOBALS['guard_captcha'] ) ) { return array( 'response' => array( 'code' => 503 ), 'headers' => array( 'content-type' => 'text/html' ), 'body' => '<html><title>SiteGround CAPTCHA</title><body>Verify you are human</body></html>' ); }
	return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'text/html' ), 'body' => '<!doctype html><html><head><title>Published fixture</title><meta name="description" content="A disposable test page."><link rel="canonical" href="' . get_permalink( $GLOBALS['guard_published'] ) . '"></head><body><main><h1>Published fixture</h1><p>This is the public fixture content.</p></main></body></html>' );
};
$GLOBALS['guard_published'] = $published;
add_filter( 'pre_http_request', $fixture_html, 20, 3 );
$audit = guard_request( 'verified-audit/' . $published );
$r = CC_Assistant_Evidence_Gate::validate_queue( $pub_args );
guard_check( 'Fresh verified HTML audit proves published target', 200 === $audit->get_status() && is_array( $r ), $audit->get_data() );
$GLOBALS['guard_captcha'] = true;
guard_request( 'verified-audit/' . $published );
$r = CC_Assistant_Evidence_Gate::validate_queue( $pub_args );
guard_check( 'Later CAPTCHA invalidates previous good page evidence', is_wp_error( $r ), $r );
$GLOBALS['guard_captcha'] = false;

set_transient( Elementor\Api::TRANSIENT_KEY_PREFIX . ELEMENTOR_VERSION, array( 'pro_widgets' => array() ), 600 );
$node = array( 'id' => 'abc1234', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Fixture heading', 'header_size' => 'h2' ), 'elements' => array() );
$r = CC_Assistant_Elementor_Validation::validate_tree( array(), array( $node ) );
guard_check( 'Real Elementor heading controls accepted', true === $r, $r );
$bad = $node; $bad['settings']['header_size'] = 'h9';
$r = CC_Assistant_Elementor_Validation::validate_tree( array(), array( $bad ) );
guard_check( 'Real Elementor rejects invented enum', is_wp_error( $r ), $r );
$outer = array( 'id' => 'outer', 'elType' => 'container', 'settings' => array(), 'elements' => array( $bad ) );
$r = CC_Assistant_Elementor_Validation::validate_tree( array(), array( $outer ) );
guard_check( 'Real nested Elementor import rejects bad child', is_wp_error( $r ), $r );
$bad['settings'] = array( 'title' => 'Fixture', 'invented_control' => 'yes' );
$r = CC_Assistant_Elementor_Builder::save_tree( $post_id, array( $bad ) );
guard_check( 'Final persistence boundary rejects unsupported settings', is_wp_error( $r ) && '' === get_post_meta( $post_id, '_elementor_data', true ), $r );
$r = CC_Assistant_Elementor_Builder::save_tree( $post_id, array( $node ) );
guard_check( 'Real Elementor tree save succeeds with readback', true === $r && wp_json_encode( array( $node ) ) === get_post_meta( $post_id, '_elementor_data', true ), $r );
guard_request( 'posts/' . $post_id, array( 'slim' => false ) );
$widget_args = array( 'post_id' => $post_id, 'change_type' => 'elementor_widget_update', 'current_value' => wp_json_encode( array( 'widget_id' => 'abc1234', 'settings' => $node['settings'] ) ), 'proposed_value' => wp_json_encode( array( 'widget_id' => 'abc1234', 'settings' => array( 'title' => 'Approved heading', 'align' => 'center' ) ) ) );
$pending_id = CC_Assistant_Pending_Changes::queue( $widget_args );
guard_check( 'Valid Elementor update queues through shared gate', is_int( $pending_id ), $pending_id );
$r = CC_Assistant_Apply::apply_pending( $pending_id, 1, true );
guard_check( 'Real Elementor update applies with approval revalidation', ! is_wp_error( $r ) && 'Approved heading' === json_decode( get_post_meta( $post_id, '_elementor_data', true ), true )[0]['settings']['title'], $r );
$r = CC_Assistant_Apply::rollback_pending( $pending_id, 1 );
guard_check( 'Elementor rollback restores exact previous tree', ! is_wp_error( $r ) && wp_json_encode( array( $node ) ) === get_post_meta( $post_id, '_elementor_data', true ), $r );

$other_db = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$name = CC_Assistant_Write_Lock::name();
guard_check( 'Independent MySQL connection holds approval lock', '1' === (string) $other_db->get_var( $other_db->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) );
$r = CC_Assistant_Apply::apply_pending( $pending_id, 1, true );
guard_check( 'Concurrent approval is blocked before claiming', is_wp_error( $r ) && 'write_lock_unavailable' === $r->get_error_code(), $r );
$r = CC_Assistant_Apply::rollback_pending( $pending_id, 1 );
guard_check( 'Concurrent rollback shares the same lock', is_wp_error( $r ) && 'write_lock_unavailable' === $r->get_error_code(), $r );
$other_db->get_var( $other_db->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
$r = CC_Assistant_Apply::apply_pending( 999999, 1, true );
guard_check( 'Error path releases the database lock', is_wp_error( $r ) && '1' === (string) $other_db->get_var( $other_db->prepare( 'SELECT IS_FREE_LOCK(%s)', $name ) ), $r );
$other_db->close();

update_option( 'elementor_guard_fixture', array( 'mode' => 'before' ) );
require_once CC_ASSISTANT_DIR . 'includes/class-setting-writer.php';
$plan = CC_Assistant_Setting_Writer::build_plan( array( 'option_name' => 'elementor_guard_fixture', 'path' => 'mode', 'value' => 'after' ) );
$option_args = array( 'post_id' => 0, 'change_type' => 'plugin_setting_update', 'proposed_value' => wp_json_encode( $plan ) );
$r = CC_Assistant_Evidence_Gate::validate_queue( $option_args );
guard_check( 'Unobserved plugin setting blocked', is_wp_error( $r ) && 'setting_evidence_required' === $r->get_error_code(), $r );
$settings = guard_request( 'stack/settings', array( 'slug' => 'elementor' ) );
$r = CC_Assistant_Evidence_Gate::validate_queue( $option_args );
guard_check( 'Installed active plugin setting read establishes receipt', 200 === $settings->get_status() && is_array( $r ), $r );
update_option( 'elementor_guard_fixture', array( 'mode' => 'external change' ) );
$r = CC_Assistant_Evidence_Gate::validate_queue( $option_args );
guard_check( 'Changed option invalidates the observed plan', is_wp_error( $r ) && 'setting_evidence_required' === $r->get_error_code(), $r );

guard_request( 'posts/' . $post_id, array( 'slim' => false ) );
$r = guard_request( 'draft/elementor-widget', array( 'post_id' => $post_id, 'widget_id' => 'abc1234', 'settings' => array( 'imagined_setting' => 'x' ), 'dry_run' => true ), 'POST' );
guard_check( 'Real REST dry run rejects unsupported settings', 422 === $r->get_status() && 'unverified_elementor_settings' === ( $r->get_data()['code'] ?? '' ), $r->get_data() );
$reflect = new ReflectionMethod( 'CC_Assistant_Evidence_Gate', 'key' );
$key = $reflect->invoke( null, 'post', $post_id ); $receipt = get_transient( $key );
$receipt['observed_at'] = time() - 601; set_transient( $key, $receipt, 600 );
$r = CC_Assistant_Evidence_Gate::validate_queue( $args );
guard_check( 'Expired evidence is rejected even when transient still exists', is_wp_error( $r ) && 'page_evidence_required' === $r->get_error_code(), $r );
$before_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
$r = CC_Assistant_Apply::create_draft_post( array( 'title' => 'Invalid draft', 'elementor_data' => array( $bad ) ) );
guard_check( 'Invalid new draft rejected before any post insertion', is_wp_error( $r ) && $before_count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), $r );


update_post_meta( $post_id, '_elementor_data', '{broken json' );
$r = CC_Assistant_Elementor_Validation::validate_payload( array( 'post_id' => $post_id, 'change_type' => 'publish_draft', 'proposed_value' => '{}' ) );
guard_check( 'Malformed stored Elementor data blocks publication', is_wp_error( $r ), $r );

echo "Integration passed: " . $GLOBALS['guard_test_count'] . " checks; WordPress " . get_bloginfo( 'version' ) . '; Elementor ' . ELEMENTOR_VERSION . ".\n";

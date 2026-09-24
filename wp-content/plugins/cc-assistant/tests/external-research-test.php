<?php
// Production WordPress schema validator with an isolated actor-bound option store.
error_reporting( E_ALL ); define( 'ABSPATH', __DIR__ . '/' ); define( 'DAY_IN_SECONDS', 86400 );
class WP_Error { public function __construct( public $code, $message = '', $data = null ) {} public function get_error_code() { return $this->code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_current_user_id() { return $GLOBALS['actor'] ?? 9; }
function get_current_blog_id() { return 1; }
function home_url() { return 'https://fixture.example'; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $v, $autoload = false ) { $GLOBALS['options'][$key] = $v; return true; }
function wp_http_validate_url( $s ) { return str_starts_with( $s, 'https://competitor.example/' ); }
function check( $s, $v ) { if ( ! $v ) { throw new RuntimeException( $s ); } echo "PASS $s\n"; }
class ProviderDB { public function prepare( $sql, ...$v ) { return $sql; } public function get_var( $sql ) { return 1; } }
$GLOBALS['wpdb'] = new ProviderDB();
require dirname( __DIR__ ) . '/includes/class-external-research.php';
$wp_root = getenv( 'CC_TEST_WORDPRESS_ROOT' );
if ( ! $wp_root || ! is_file( $wp_root . '/wp-includes/rest-api.php' ) ) { throw new RuntimeException( 'Set CC_TEST_WORDPRESS_ROOT for actual WordPress schema validation.' ); }
require_once $wp_root . '/wp-includes/rest-api.php';
function __( $s ) { return $s; }
function wp_sprintf( $format, ...$args ) { return 'Schema enum validation failed.'; }
function wp_is_numeric_array( $v ) { return is_array( $v ) && array_is_list( $v ); }
function wp_array_slice_assoc( $v, $keys ) { return array_intersect_key( $v, array_flip( $keys ) ); }
function _doing_it_wrong( ...$a ) { throw new RuntimeException( 'Unexpected invalid schema' ); }
$GLOBALS['permitted'] = true;
$args = array( 'provider' => 'ubersuggest', 'tool_name' => 'keyword_suggestions', 'target' => 'what to bring to an appointment',
    'location' => 'Dallas, Texas', 'location_id' => 1026339, 'language' => 'English', 'captured_at_utc' => gmdate( 'Y-m-d\TH:i:s\Z' ),
    'observations' => array( array( 'subject' => 'what to bring', 'estimated_volume' => 0, 'provider_updated_at_utc' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 200 * DAY_IN_SECONDS ) ), array( 'subject' => 'missing metric' ) ) );
$a = CC_Assistant_External_Research::capture( $args );
check( 'actual scoped provider record is saved without pretending server verification', ! is_wp_error( $a ) && str_contains( $a['record']['provenance'], 'server_did_not_call' ) );
$repeat = CC_Assistant_External_Research::capture( $args );
check( 'same provider capture is idempotent', $repeat['reused'] && $repeat['record_id'] === $a['record_id'] );
$linked = CC_Assistant_External_Research::linked( array( $a['record_id'] ) );
$rows = $linked[0]['record']['provider_data']['observations'];
check( 'zero and missing volume remain distinct', 0 === $rows[0]['estimated_volume'] && ! array_key_exists( 'estimated_volume', $rows[1] ) );
check( 'old and missing provider dates remain explicit', 'older_than_90_days_review_applicability' === $rows[0]['freshness_review'] && 'update_date_unknown' === $rows[1]['freshness_review'] );
$bad = $args; $bad['observations'][0]['estimated_volume'] = -1;
check( 'negative demand rejected by actual WP schema', is_wp_error( CC_Assistant_External_Research::capture( $bad ) ) );
$bad = $args; $bad['observations'][0]['credential'] = 'must-not-store';
check( 'unknown payload fields rejected', is_wp_error( CC_Assistant_External_Research::capture( $bad ) ) );
$bad = $args; $bad['captured_at_utc'] = '2026-02-30T12:00:00Z';
check( 'invalid calendar date rejected', 'provider_capture_time' === CC_Assistant_External_Research::capture( $bad )->get_error_code() );
$bad = $args; $bad['observations'][0]['provider_updated_at_utc'] = gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS );
check( 'future provider update rejected', 'provider_update_time' === CC_Assistant_External_Research::capture( $bad )->get_error_code() );
$bad = $args; $bad['observations'][0]['url'] = 'http://127.0.0.1/secret';
check( 'private evidence URL rejected', is_wp_error( CC_Assistant_External_Research::capture( $bad ) ) );
$bad = $args; $bad['tool_name'] = 'generate_article';
check( 'unsupported tool provenance rejected', is_wp_error( CC_Assistant_External_Research::capture( $bad ) ) );
$research = CC_Assistant_Content_Decisions::save( 'research', 'ordinary', array( 'assessment' => 'prospective_topic_research' ) );
check( 'ordinary research cannot masquerade as a provider capture', is_wp_error( CC_Assistant_External_Research::linked( array( $research['record_id'] ) ) ) );
$GLOBALS['actor'] = 99;
check( 'provider records remain actor-isolated', is_wp_error( CC_Assistant_External_Research::linked( array( $a['record_id'] ) ) ) );

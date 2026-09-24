<?php
/** Real fetch/parser/audit lifecycle with deterministic HTTP and persistence fixtures. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
class WP_Error {
	private $code; private $message;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function home_url( $p = '' ) { return 'https://fixture.test' . $p; }
function wp_parse_url( $u, $component = -1 ) { return parse_url( $u, $component ); }
function wp_json_encode( $x ) { return json_encode( $x ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function get_permalink( $id ) { return 'https://fixture.test/page/'; }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_status' => 'publish', 'post_modified_gmt' => '2026-01-01 00:00:00' ); }
function get_post_meta() { return ''; }
function get_option( $name, $default = null ) { return $GLOBALS['options'][$name] ?? $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][$name] = $value; return true; }
function add_query_arg( $params, $url ) { return $url . '?' . http_build_query( $params ); }
function wp_remote_get( $url, $args ) { $GLOBALS['requests']++; $GLOBALS['request_args'] = $args; return $GLOBALS['response']; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_header( $r, $h ) { return $r['headers'][$h] ?? ''; }
class EvidenceDB {
	public $prefix = 'wp_'; public $row = null; public $fail = false;
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_var( $sql ) { return str_starts_with( $sql, 'SHOW TABLES' ) ? 'wp_cc_page_facts' : null; }
	public function get_row() { return $this->row; }
	public function replace( $table, $row, $formats ) { if ( $this->fail ) { return false; } $this->row = $row; return 1; }
	public function update( $table, $data, ...$args ) { $this->row = array_merge( $this->row, $data ); return 1; }
}
$GLOBALS['wpdb'] = new EvidenceDB();
$GLOBALS['requests'] = 0; $GLOBALS['options'] = array();
$html = '<!doctype html><html><head><title>Fixture</title><meta name="description" content="Actual description"><link rel="canonical" href="https://fixture.test/page/"><script type="application/ld+json">{"@type":"WebPage"}</script><!-- <script type="application/ld+json">{broken</script> --></head><body><h1>Heading</h1><a href="/other/">Other</a><img src="decor.png" alt=""><img src="meaning.png"></body></html>';
$GLOBALS['response'] = array( 'code' => 200, 'body' => $html, 'headers' => array( 'content-type' => 'text/html', 'x-proxy-cache' => 'MISS' ) );
require CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
require CC_ASSISTANT_DIR . 'includes/class-verified-page-audit.php';
function check( $name, $pass ) { if ( ! $pass ) { throw new RuntimeException( $name ); } echo "PASS $name\n"; }
function by_rule( $report, $id ) { return array_column( $report['findings'], null, 'rule_id' )[$id]; }
$first = CC_Assistant_Verified_Page_Audit::run( 1 );
check( 'fresh audit uses real parser and gives only partial coverage', $first['usable'] && 'partial' === $first['assessment'] && $first['counts']['unknown'] >= 6 );
check( 'HTTPS verification enabled and redirects cannot switch audited target', true === $GLOBALS['request_args']['sslverify'] && 0 === $GLOBALS['request_args']['redirection'] );
check( 'commented JSON-LD is excluded', 1 === by_rule( $first, 'schema.json_syntax' )['evidence']['block_count'] );
$alt = by_rule( $first, 'accessibility.image_alt' );
check( 'decorative empty alt is separated from missing attributes', 'review' === $alt['status'] && 1 === $alt['evidence']['absent_attribute'] && 1 === $alt['evidence']['empty_attribute'] );
$second = CC_Assistant_Verified_Page_Audit::run( 1 );
check( 'repeated identical input has identical findings with fresh fetches', 2 === $GLOBALS['requests'] && $first['findings_sha256'] === $second['findings_sha256'] && 'unchanged_findings' === $second['comparison']['status'] );
$GLOBALS['response']['body'] = str_replace( '<title>Fixture</title>', '<title>Changed title</title><title>Duplicate</title>', $html );
$changed = CC_Assistant_Verified_Page_Audit::run( 1 );
check( 'changed evidence explains changed assessment', 'changed_findings' === $changed['comparison']['status'] && $changed['comparison']['body_changed'] && 'review' === by_rule( $changed, 'seo.title' )['status'] );
$GLOBALS['response']['headers']['x-robots-tag'] = 'noindex';
$headers = CC_Assistant_Verified_Page_Audit::run( 1 );
check( 'HTTP noindex is part of assessment', 'review' === by_rule( $headers, 'seo.index_directives' )['status'] );
$GLOBALS['response']['headers']['x-proxy-cache'] = 'HIT';
$hit = CC_Assistant_Verified_Page_Audit::run( 1 );
check( 'cache hit cannot produce current all-clear', ! $hit['usable'] && 0 === $hit['counts']['pass'] && 'unverified' === $hit['assessment'] );
$pending = (object) array( 'change_type' => 'postmeta_update' );
$verified = CC_Assistant_Page_Facts::after_apply( 1, $pending, array( 'key' => 'rank_math_description', 'value' => 'Actual description' ) );
check( 'even matching after-apply expectation cannot verify a cache hit', 'inconclusive_cache' === $verified['verdict'] );
unset( $GLOBALS['response']['headers']['x-proxy-cache'] );
$unknown_cache = CC_Assistant_Page_Facts::after_apply( 1, $pending, array( 'key' => 'rank_math_description', 'value' => 'Actual description' ) );
check( 'unknown cache also cannot verify latest write', 'inconclusive_cache' === $unknown_cache['verdict'] );
$GLOBALS['response']['headers']['x-proxy-cache'] = 'MISS';
$verified = CC_Assistant_Page_Facts::after_apply( 1, $pending, array( 'key' => 'rank_math_description', 'value' => 'Actual description' ) );
check( 'verification binds exact successful capture', 'verified' === $verified['verdict'] && sha1( $GLOBALS['response']['body'] ) === $verified['body_sha1'] );
$recaptured = CC_Assistant_Page_Facts::capture( 1 );
check( 'later capture never inherits previous verified verdict', null === $recaptured['verification'] && null === $GLOBALS['wpdb']->row['verification'] );
$GLOBALS['response']['code'] = 503;
$failure = CC_Assistant_Verified_Page_Audit::run( 1 );
check( '503 never falls back to older passing audit', ! $failure['usable'] && 0 === $failure['counts']['pass'] && 'page_http_error' === $failure['source']['error'] );
$stale = CC_Assistant_Page_Facts::get( 1, true );
check( 'legacy fallback clearly stale and unverified', $stale['stale'] && ! $stale['refreshed'] && null === $stale['verification'] );
$GLOBALS['response']['code'] = 200;
$GLOBALS['response']['body'] = '<html><head><title>SiteGround CAPTCHA</title></head><body>Challenge</body></html>';
$challenge = CC_Assistant_Verified_Page_Audit::run( 1 );
check( '200 CAPTCHA is unverified', 'page_challenged' === $challenge['source']['error'] && ! $challenge['usable'] );
check( 'legacy render probe rejects CAPTCHA too', is_wp_error( CC_Assistant_Render_Probe::probe( 1 ) ) );
$GLOBALS['response']['body'] = '{"ok":true}'; $GLOBALS['response']['headers']['content-type'] = 'application/json';
check( 'non HTML response rejected', is_wp_error( CC_Assistant_Page_Facts::capture( 1 ) ) );
$GLOBALS['response']['body'] = $html; $GLOBALS['response']['headers']['content-type'] = 'text/html'; $GLOBALS['wpdb']->fail = true;
$storage = CC_Assistant_Verified_Page_Audit::run( 1 );
check( 'storage failure cannot claim successful capture', ! $storage['usable'] && 'facts_storage_failed' === $storage['source']['error'] );
$facts = $recaptured['facts']; $facts['links']['total'] = 500;
$absence = CC_Assistant_Page_Facts::check_expectations( $facts, array( array( 'check' => 'link_absent', 'href' => '/beyond-cap/' ) ) );
check( 'truncated inventory cannot verify absence', false === $absence['pass'] && null === $absence['checks'][0]['pass'] );
$text = CC_Assistant_Page_Facts::check_expectations( $facts, array( array( 'check' => 'text_present', 'text' => 'Paragraph outside extracted subset' ) ) );
check( 'text outside metadata/heading/anchor subset is inconclusive', null === $text['checks'][0]['pass'] );
echo "All evidence lifecycle checks passed.\n";

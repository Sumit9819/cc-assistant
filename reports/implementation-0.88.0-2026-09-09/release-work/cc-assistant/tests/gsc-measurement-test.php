<?php
/** Execute GSC insertion and aggregate SQL against SQLite; emulate MySQL table/lock metadata only. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' ); define( 'CC_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' ); define( 'DAY_IN_SECONDS', 86400 );
class WP_Error {
	public $code; public $message;
	public function __construct( $c, $m = '', $data = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
function home_url( $p = '' ) { return 'https://fixture.test' . $p; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][$key] = $value; return true; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function get_locale() { return 'en_US'; }
function apply_filters( $tag, $value ) { return $value; }
function add_query_arg( $pairs, $url ) { return $url . ( empty( $pairs ) ? '' : '?' . http_build_query( $pairs ) ); }
class CC_Assistant_URL_Resolver { public static function to_post_id( $url ) { return 0; } }
class SQLFixture {
	public $prefix = 'wp_'; public $last_error = ''; public $fail_appearance = false; public $engine = 'InnoDB'; public $db; public $statements = array();
	public function __construct() {
		$this->db = new SQLite3( ':memory:' );
		$this->db->enableExceptions( true );
		$schema = '(date TEXT, page TEXT, query TEXT, search_appearance TEXT, clicks INTEGER, impressions INTEGER, ctr REAL, position REAL, page_hash TEXT, query_hash TEXT, page_canonical_hash TEXT)';
		foreach ( array( 'wp_cc_gsc_queries','wp_cc_gsc_appearances' ) as $table ) { $this->db->exec( "CREATE TABLE $table $schema" ); }
	}
	public function esc_like( $v ) { return $v; }
	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		$i = 0;
		return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $args ) {
			$v = $args[$i++]; return '%s' === $m[0] ? "'" . SQLite3::escapeString( (string) $v ) . "'" : (string) ( '%d' === $m[0] ? (int) $v : (float) $v );
		}, $sql );
	}
	public function query( $sql ) {
		$this->statements[] = $sql;
		if ( str_starts_with( $sql, 'CREATE TABLE IF NOT EXISTS' ) ) { return true; }
		if ( $this->fail_appearance && str_starts_with( $sql, 'INSERT INTO wp_cc_gsc_appearances' ) ) { return false; }
		return $this->db->exec( 'START TRANSACTION' === $sql ? 'BEGIN' : $sql );
	}
	public function get_row( $sql, $format = null ) {
		if ( str_starts_with( $sql, 'SHOW TABLE STATUS' ) ) { return array( 'Engine' => $this->engine ); }
		return $this->db->querySingle( $sql, true );
	}
	public function get_var( $sql ) {
		if ( preg_match( "/SHOW TABLES LIKE '(.*?)'/", $sql, $m ) ) { return $m[1]; }
		return $this->db->querySingle( $sql );
	}
	public function get_col( $sql ) { return array(); } // No recent-edit fixture; outcomes have their own suite.
	public function get_results( $sql, $format = null ) {
		$result = $this->db->query( $sql ); $rows = array();
		while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { $rows[] = ARRAY_A === $format ? $row : (object) $row; }
		return $rows;
	}
}
function check( $name, $pass ) { if ( ! $pass ) { throw new RuntimeException( $name ); } echo "PASS $name\n"; }
$GLOBALS['wpdb'] = new SQLFixture(); $GLOBALS['options'] = array();
require CC_ASSISTANT_DIR . 'includes/class-gsc.php';
require CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
$store = new ReflectionMethod( 'CC_Assistant_GSC', 'store_rows_for_date' ); $store->setAccessible( true );
$date = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
$raw = array( array( 'keys' => array( $date, 'https://fixture.test/a/', 'imaging' ), 'clicks' => 1, 'impressions' => 1, 'ctr' => 1, 'position' => 2 ) );
$appearance = array(
	array( 'keys' => array( $date, 'https://fixture.test/a/', 'Rich result' ), 'clicks' => 1, 'impressions' => 1, 'position' => 2 ),
	array( 'keys' => array( $date, 'https://fixture.test/a/', 'Featured snippet' ), 'clicks' => 0, 'impressions' => 1, 'position' => 2 ),
);
check( 'native datasets store successfully', true === $store->invoke( null, $date, $raw, $appearance ) );
$row = $GLOBALS['wpdb']->db->querySingle( 'SELECT SUM(clicks) clicks, SUM(impressions) impressions, COUNT(*) rows FROM wp_cc_gsc_queries', true );
check( 'one input click/impression remains one, independent of appearance count', 1 === $row['clicks'] && 1 === $row['impressions'] && 1 === $row['rows'] );
check( 'appearance facts are stored in their own dimension table', 2 === $GLOBALS['wpdb']->db->querySingle( 'SELECT COUNT(*) FROM wp_cc_gsc_appearances' ) );
check( 'query facts have no inferred appearance allocation', '' === $GLOBALS['wpdb']->db->querySingle( 'SELECT search_appearance FROM wp_cc_gsc_queries' ) );
check( 'successful date records explicit migration provenance', array( $date ) === $GLOBALS['options']['cc_assistant_gsc_raw_dates'] );
$store->invoke( null, $date, $raw, $appearance );
check( 're-sync replaces a date without duplicating counts', 1 === $GLOBALS['wpdb']->db->querySingle( 'SELECT SUM(clicks) FROM wp_cc_gsc_queries' ) );
$GLOBALS['wpdb']->fail_appearance = true;
$bad = $raw; $bad[0]['clicks'] = 9;
check( 'failed second dataset returns an error', is_wp_error( $store->invoke( null, $date, $bad, $appearance ) ) );
check( 'failed replacement rolls back the earlier dataset too', 1 === $GLOBALS['wpdb']->db->querySingle( 'SELECT SUM(clicks) FROM wp_cc_gsc_queries' ) );
$GLOBALS['wpdb']->fail_appearance = false; $GLOBALS['wpdb']->engine = 'MyISAM';
check( 'nontransactional storage is refused before replacing existing rows', 'gsc_transactional_storage_required' === $store->invoke( null, $date, $bad, $appearance )->get_error_code() );
$GLOBALS['wpdb']->engine = 'InnoDB';
$appearance_results = CC_Assistant_GSC::ai_overview_pages( array( 'min_impressions' => 1 ) );
check( 'rich search appearances are not labeled confirmed AI', 2 === count( $appearance_results ) && false === $appearance_results[0]['ai_overview_confirmed'] );
$other_date = gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS );
$raw[0]['impressions'] = 99; $raw[0]['clicks'] = 0; $raw[0]['ctr'] = 0; $raw[0]['position'] = 2;
$raw[] = array( 'keys' => array( $date,'https://fixture.test/b/','imaging' ), 'impressions' => 50, 'clicks' => 0, 'position' => 4 );
$store->invoke( null, $date, $raw, array() );
$store->invoke( null, $other_date, array( array( 'keys' => array( $other_date,'https://fixture.test/a/','imaging' ), 'impressions' => 1, 'clicks' => 0, 'position' => 80 ) ), array() );
$overlap = CC_Assistant_SEO_Tools::cannibalization( array( 'min_impressions' => 1, 'max_position' => 30 ) );
check( 'weighted aggregation keeps the genuine 2.78 position candidate', 1 === $overlap['count'] && 2.78 === $overlap['conflicts'][0]['pages'][0]['avg_position'] );
check( 'overlap output does not establish harmful competition', false === $overlap['harm_established'] );
$alerts = CC_Assistant_GSC::aio_ctr_drop_alert( array( 'min_impressions' => 1 ) );
check( 'low CTR never becomes confirmed AI causation', ! empty( $alerts ) && 'not_established' === $alerts[0]['suspected_aio_cannibalization'] && null === $alerts[0]['estimated_clicks_lost'] );
echo "All GSC measurement checks passed.\n";

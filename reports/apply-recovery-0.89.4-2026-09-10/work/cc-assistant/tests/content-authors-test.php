<?php
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error { public function __construct( public $code, $message = '', $data = null ) {} public function get_error_code() { return $this->code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $k, $d = false ) { return $GLOBALS['profile'] ?? $d; }
function get_userdata( $id ) { return $GLOBALS['users'][$id] ?? false; }
function get_users( $args ) { return array_values( $GLOBALS['users'] ); }
function user_can( $u, $cap ) { return $u->allowed ?? true; }
function is_multisite() { return ! empty( $GLOBALS['multisite'] ); }
function is_user_member_of_blog( $id ) { return $id !== 4; }
function check( $s, $v ) { if ( ! $v ) { throw new RuntimeException( $s ); } echo "PASS $s\n"; }
require dirname( __DIR__ ) . '/includes/class-content-authors.php';
$GLOBALS['users'] = array( 2 => (object) array( 'ID' => 2, 'display_name' => 'Site team', 'user_login' => 'private-login', 'user_email' => 'private@example.test' ),
    3 => (object) array( 'ID' => 3, 'display_name' => 'Subscriber', 'allowed' => false ), 4 => (object) array( 'ID' => 4, 'display_name' => 'Other site author' ) );
check( 'no silent automation-account fallback', 'content_author_required' === CC_Assistant_Content_Authors::resolve()->get_error_code() );
check( 'explicit existing author accepted', 2 === CC_Assistant_Content_Authors::resolve( 2 )['id'] );
check( 'unknown account refused', 'content_author_unavailable' === CC_Assistant_Content_Authors::resolve( 999 )->get_error_code() );
check( 'non-author capability refused', 'content_author_unavailable' === CC_Assistant_Content_Authors::resolve( 3 )->get_error_code() );
$GLOBALS['profile'] = array( 'author_id' => 2, 'author_name_at_selection' => 'Site team' );
check( 'saved author selection reused', 2 === CC_Assistant_Content_Authors::resolve()['id'] );
$listing = json_encode( CC_Assistant_Content_Authors::listing() );
check( 'public listing omits private identity fields', ! str_contains( $listing, 'private-login' ) && ! str_contains( $listing, 'private@example' ) );
$GLOBALS['users'][2]->display_name = 'Changed attribution';
check( 'saved name drift requires explicit refresh', 'content_author_changed' === CC_Assistant_Content_Authors::resolve()->get_error_code() );
$GLOBALS['multisite'] = true;
check( 'network account from a different site refused', 'content_author_unavailable' === CC_Assistant_Content_Authors::resolve( 4 )->get_error_code() );
$GLOBALS['users'][2]->deleted = true;
check( 'deleted account refused', 'content_author_unavailable' === CC_Assistant_Content_Authors::resolve( 2 )->get_error_code() );

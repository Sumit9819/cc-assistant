<?php
require __DIR__ . '/fixtures/safety-harness.php';
define( 'CC_ASSISTANT_HTTP_UA', 'fixture' );
function wp_remote_get( $url, $args ) { $GLOBALS['http_args'] = $args; return $GLOBALS['response']; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_header( $r, $key ) { return 'text/html'; }
require CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
reset_review();
$GLOBALS['response'] = array( 'code' => 200, 'body' => '<html><head><title>SiteGround CAPTCHA</title></head><body>Challenge</body></html>' );
check( null === CC_Assistant_Pre_Publish::warm_rendered_html( 1 ), 'HTTP 200 CAPTCHA is not cached as article content' );
check( true === $GLOBALS['http_args']['sslverify'] && 0 === $GLOBALS['http_args']['redirection'], 'Rendered cache validates certificates and does not follow redirects' );
$GLOBALS['response'] = array( 'code' => 302, 'body' => '<html><body>Other destination</body></html>' );
check( null === CC_Assistant_Pre_Publish::warm_rendered_html( 1 ), 'Redirect is not evidence about the requested post' );
$GLOBALS['response'] = new WP_Error( 'tls_failure' );
check( null === CC_Assistant_Pre_Publish::warm_rendered_html( 1 ), 'TLS failure remains unavailable without insecure retry' );
$GLOBALS['response'] = array( 'code' => 200, 'body' => '<html><body>Actual requested page</body></html>' );
check( $GLOBALS['response']['body'] === CC_Assistant_Pre_Publish::warm_rendered_html( 1 ), 'Valid complete HTML remains cacheable' );
$GLOBALS['response']['body'] = '<html><body>' . str_repeat( 'x', 2097152 );
check( null === CC_Assistant_Pre_Publish::warm_rendered_html( 1 ), 'A truncated oversized response is not cached as complete content' );
echo "Rendered cache assertions: $tests\n";

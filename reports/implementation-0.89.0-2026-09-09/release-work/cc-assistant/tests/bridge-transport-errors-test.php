<?php
/**
 * v0.81.3: the bridge names the transport failure instead of shrugging.
 *
 * On 2026-09-07 SiteGround flagged the operator's IP reputation and began
 * answering 202 with a JavaScript browser challenge on every site on their
 * platform. The bridge reported only "Could not parse response as JSON", and an
 * hour went into the wrong diagnoses: first the user-agent block, then the
 * .mcp.json config, then the folder the session was running from. None of those
 * were it, and the error message had all the evidence needed to rule them out.
 *
 * This pins the three outcomes so the message cannot silently regress to a shrug.
 *
 * Run: php tests/bridge-transport-errors-test.php
 */
error_reporting( E_ALL );

$fails = 0;
function check( $label, $ok ) {
	global $fails;
	if ( ! $ok ) { $fails++; }
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
}

// The classifier lives in the bridge, which is a CLI script rather than a
// library: requiring it would run a server. Slice the one function out instead.
$bridge = dirname( __DIR__ ) . '/bin/mcp-server.php';
$src    = file_get_contents( $bridge );
$start  = strpos( $src, 'function cc_mcp_explain_non_json' );
check( 'classifier is present in bin/mcp-server.php', false !== $start );
if ( false === $start ) {
	echo "\n1 FAILURES\n";
	exit( 1 );
}
// Brace-match to the end of the function. Searching for a marker like
// "return null;" broke the moment the file was edited elsewhere, and a broken
// slice then fails as a fatal "undefined function" instead of a readable
// verdict, which is a worse test than no test at all.
$open  = strpos( $src, chr( 123 ), $start );
$depth = 0;
$end   = $open;
for ( $i = $open, $n = strlen( $src ); $i < $n; $i++ ) {
	if ( chr( 123 ) === $src[ $i ] ) { $depth++; }
	elseif ( chr( 125 ) === $src[ $i ] ) {
		$depth--;
		if ( 0 === $depth ) { $end = $i + 1; break; }
	}
}
check( 'classifier body extracted cleanly', $end > $open );
eval( substr( $src, $start, $end - $start ) );
check( 'classifier is callable after extraction', function_exists( 'cc_mcp_explain_non_json' ) );
if ( ! function_exists( 'cc_mcp_explain_non_json' ) ) { exit( 1 ); }

// --- the IP-reputation challenge -----------------------------------------
$challenge = '<html><head><link rel="icon" href="data:;"><meta http-equiv="refresh" '
	. 'content="0;/.well-known/sgcaptcha/?r=%2Fwp-json%2Fcc-assistant%2Fv1%2Fwhoami'
	. '&y=ipc:103.166.100.210:1788756568.131"></meta></head></html>';
$r = cc_mcp_explain_non_json( $challenge );

check( 'sgcaptcha body is classified, not left to json_parse_error', is_array( $r ) );
check( 'error code names the IP challenge', 'siteground_ip_challenge' === $r['error'] );
check( 'the offending IP is echoed back', false !== strpos( $r['message'], '103.166.100.210' ) );

// Observations must not turn into unsupported causal claims.
check( 'cause remains unknown', 'unknown' === $r['diagnosis']['root_cause'] );
check( 'scope on other sites remains unknown', 'unknown' === $r['diagnosis']['other_sites_affected'] );
check( 'does not assert EVERY site is affected', false === strpos( $r['message'], 'EVERY site' ) );
check( 'points to configured browser transport', false !== strpos( $r['message'], 'CC_MCP_TRANSPORT=browser' ) );
check( 'keeps normal provider challenge and approval process', false !== strpos( $r['message'], 'normal browser challenge' ) && false !== strpos( $r['message'], 'approval queue' ) );
check( 'mentions clearance context limits', false !== strpos( $r['message'], 'clearance cookies' ) );

// --- the older user-agent block, which must stay distinguishable ----------
$ua = '<html><body><h1>SiteGround</h1><p>Access denied by security rules</p></body></html>';
$r2 = cc_mcp_explain_non_json( $ua );
check( 'a SiteGround error page is classified separately', is_array( $r2 ) );
check( 'error code names the WAF/UA block', 'siteground_waf_block' === $r2['error'] );
check( 'the two SiteGround failures never collapse into one code', $r['error'] !== $r2['error'] );

// --- anything else must fall through -------------------------------------
// Over-claiming would be worse than the shrug: a maintenance page or a host
// cache page wrongly blamed on SiteGround sends the next session somewhere
// equally wrong, just faster.
foreach ( array(
	'ordinary branded page' => '<html><body>Hosted by SiteGround</body></html>',
	'plain maintenance' => '<html><body>Scheduled maintenance</body></html>',
	'html but empty'    => '<html></html>',
	'truncated json'    => '{"site":{"name":"ER of Ir',
	'empty string'      => '',
) as $label => $body ) {
	check( "falls through to json_parse_error: $label", null === cc_mcp_explain_non_json( $body ) );
}

// --- and the wiring, not just the function -------------------------------
// The first attempt at this put the call AFTER the return statement, where it
// could never run, and the unit test still passed.
$wired = preg_match_all(
	'/\$explained\s*=\s*cc_mcp_explain_non_json\([^;]*\);\s*if\s*\(\s*null\s*!==\s*\$explained\s*\)/',
	$src
);
check( 'both transports call the classifier (curl and streams)', 2 === $wired );

$before_return = preg_match_all(
	'/cc_mcp_explain_non_json[\s\S]{0,160}?return \$explained;[\s\S]{0,120}?\'error\'\s*=>\s*\'json_parse_error\'/',
	$src
);
check( 'the classifier runs BEFORE the json_parse_error return, not after it',
	2 === $before_return );

echo $fails ? "\n$fails FAILURES\n" : "\nALL PASS\n";
exit( $fails ? 1 : 0 );

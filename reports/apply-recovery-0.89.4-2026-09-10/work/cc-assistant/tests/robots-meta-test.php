<?php
/**
 * robots logical-key test.
 *
 * Pins the bug this feature exists to fix. On a live site, seven posts were
 * "noindexed" by writing a pre-serialized array STRING to rank_math_robots.
 * Every apply reported success; every page kept emitting "follow, index".
 * WordPress maybe_serialize() serializes a second time when handed a string
 * that already looks serialized, so Rank Math read back a string where it
 * expects an array, and ignored the override.
 *
 * The regression test that matters is therefore not "does it emit noindex" but
 * "is the stored value an ARRAY after a full serialize/unserialize round trip".
 *
 * Run: php tests/robots-meta-test.php
 */
error_reporting( E_ALL );

$fails = 0;
function check( $label, $cond, $detail = '' ) {
	global $fails;
	if ( $cond ) {
		echo "PASS  $label\n";
	} else {
		$fails++;
		echo "FAIL  $label" . ( '' !== $detail ? "  --  $detail" : '' ) . "\n";
	}
}

/* --------------------------------------------------------------------------
 * WordPress serialization behaviour, reproduced exactly.
 * ----------------------------------------------------------------------- */
function wp_is_serialized( $data ) {
	return is_string( $data ) && (bool) preg_match( '/^(a|O|s|b|i|d):\d*/', trim( $data ) );
}
function wp_maybe_serialize( $data ) {
	if ( is_array( $data ) || is_object( $data ) ) {
		return serialize( $data );
	}
	if ( wp_is_serialized( $data ) ) {
		return serialize( $data );
	}
	return $data;
}
function wp_maybe_unserialize( $data ) {
	if ( wp_is_serialized( $data ) ) {
		return @unserialize( $data );
	}
	return $data;
}
/** Full round trip: what a reader gets back after update_post_meta + get_post_meta. */
function meta_round_trip( $value ) {
	return wp_maybe_unserialize( wp_maybe_serialize( $value ) );
}

/* --------------------------------------------------------------------------
 * The transformer, mirrored from class-rest-api.php::robots_value_for_plugin.
 * Kept in lockstep with the real implementation (diag-mirrors-runtime rule).
 * ----------------------------------------------------------------------- */
function robots_value_for_plugin( $directives, $plugin ) {
	$parts = array_filter( array_map( 'trim', explode( ',', strtolower( $directives ) ) ) );
	$known = array( 'index', 'noindex', 'follow', 'nofollow' );
	foreach ( $parts as $p ) {
		if ( ! in_array( $p, $known, true ) ) {
			return array( 'error' => 'robots_directive_unknown' );
		}
	}
	if ( empty( $parts ) ) {
		return array( 'error' => 'robots_directive_required' );
	}
	$noindex  = in_array( 'noindex', $parts, true );
	$nofollow = in_array( 'nofollow', $parts, true );

	switch ( $plugin ) {
		case 'rank-math':
			if ( ! $noindex && ! $nofollow ) {
				return '';
			}
			return array( $noindex ? 'noindex' : 'index', $nofollow ? 'nofollow' : 'follow' );
		case 'yoast':
			if ( $nofollow ) {
				return array( 'error' => 'robots_nofollow_unsupported_yoast' );
			}
			return $noindex ? '1' : '2';
		case 'seopress':
			if ( $nofollow ) {
				return array( 'error' => 'robots_nofollow_unsupported_seopress' );
			}
			return $noindex ? 'yes' : '';
		default:
			return array( 'error' => 'robots_unsupported_plugin' );
	}
}
function is_err( $v ) {
	return is_array( $v ) && isset( $v['error'] );
}

/* ------------------------------------------------- the regression itself --- */
echo "--- the bug this fixes ---\n";
$old_way = 'a:2:{i:0;s:7:"noindex";i:1;s:6:"follow";}';   // what was sent before
$got     = meta_round_trip( $old_way );
check( 'OLD string write round-trips to a STRING (the bug)', is_string( $got ), gettype( $got ) );
check( 'OLD string write is NOT an array, so Rank Math ignores it', ! is_array( $got ) );

$new_way = robots_value_for_plugin( 'noindex', 'rank-math' );
$got2    = meta_round_trip( $new_way );
check( 'NEW transformer round-trips to an ARRAY', is_array( $got2 ), gettype( $got2 ) );
check( 'NEW value contains noindex', is_array( $got2 ) && in_array( 'noindex', $got2, true ), json_encode( $got2 ) );
check( 'NEW value defaults to follow, preserving link equity', is_array( $got2 ) && in_array( 'follow', $got2, true ), json_encode( $got2 ) );

/* ---------------------------------------------------------- rank math ----- */
echo "\n--- rank math ---\n";
$v = robots_value_for_plugin( 'noindex,nofollow', 'rank-math' );
check( 'noindex,nofollow both carried', $v === array( 'noindex', 'nofollow' ), json_encode( $v ) );
$v = robots_value_for_plugin( 'index', 'rank-math' );
check( 'plain index clears the override (empty = site default)', '' === $v, json_encode( $v ) );
$v = robots_value_for_plugin( 'nofollow', 'rank-math' );
check( 'nofollow alone still indexes', $v === array( 'index', 'nofollow' ), json_encode( $v ) );

/* --------------------------------------------------- other SEO plugins ---- */
echo "\n--- other plugins store robots differently ---\n";
check( 'yoast noindex is the string 1', '1' === robots_value_for_plugin( 'noindex', 'yoast' ) );
check( 'yoast index is the string 2', '2' === robots_value_for_plugin( 'index', 'yoast' ) );
check( 'yoast refuses nofollow (separate key)', is_err( robots_value_for_plugin( 'noindex,nofollow', 'yoast' ) ) );
check( 'seopress noindex is yes', 'yes' === robots_value_for_plugin( 'noindex', 'seopress' ) );
check( 'seopress index clears', '' === robots_value_for_plugin( 'index', 'seopress' ) );
check( 'aioseo refused (custom tables, not postmeta)', is_err( robots_value_for_plugin( 'noindex', 'aioseo' ) ) );
check( 'no SEO plugin refused', is_err( robots_value_for_plugin( 'noindex', '' ) ) );

/* ----------------------------------------------------------- bad input ---- */
echo "\n--- input validation ---\n";
check( 'unknown directive refused', is_err( robots_value_for_plugin( 'noarchive', 'rank-math' ) ) );
check( 'empty directive refused', is_err( robots_value_for_plugin( '', 'rank-math' ) ) );
check( 'whitespace and case tolerated', robots_value_for_plugin( ' NoIndex , Follow ', 'rank-math' ) === array( 'noindex', 'follow' ) );

/* ------------------------------------------------- idempotence on apply --- */
echo "\n--- apply idempotence (array compare) ---\n";
$stored   = array( 'noindex', 'follow' );
$proposed = array( 'noindex', 'follow' );
check( 'identical arrays compare equal, so re-apply is a success not a failure', $stored == $proposed );
check( 'differing arrays do not', ! ( $stored == array( 'noindex', 'nofollow' ) ) );

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURES\n";
exit( $fails === 0 ? 0 : 1 );

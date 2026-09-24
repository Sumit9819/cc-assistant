<?php
/**
 * brain-sync: keep every laptop's operator brain equal, using the sites as
 * the store. Fans out across every cc-assistant server in the project's
 * .mcp.json (the MCP tools operate on ONE site; this is the all-sites CLI).
 *
 *   php bin/brain-sync.php status                     compare local vs every site
 *   php bin/brain-sync.php push  [--only NAME]        upload local brain to every site (index diff, chunked)
 *   php bin/brain-sync.php pull  --from NAME [--what all|memory|skills|project|bridge] [--replace]
 *   php bin/brain-sync.php bootstrap --url URL --user USER --pass "APP PASS" [--project DIR]
 *                                                     fresh machine: pull brain + write .mcp.json
 *
 * Options: --project DIR (default: cwd; the folder holding .mcp.json)
 *          --php PATH   (bootstrap: PHP binary to write into .mcp.json; default: this one)
 *
 * Exit code 0 when every targeted site ends in_sync, 1 otherwise.
 *
 * @package CC_Assistant
 */

if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "brain-sync must run from the command line.\n" );
	exit( 1 );
}
ini_set( 'display_errors', 'stderr' );
require __DIR__ . '/operator-brain.php';

$argv_copy = $argv;
array_shift( $argv_copy );
$cmd  = isset( $argv_copy[0] ) && '-' !== $argv_copy[0][0] ? array_shift( $argv_copy ) : 'status';
$opts = array();
for ( $i = 0; $i < count( $argv_copy ); $i++ ) {
	$a = $argv_copy[ $i ];
	if ( 0 === strpos( $a, '--' ) ) {
		$k = substr( $a, 2 );
		$v = true;
		if ( false !== strpos( $k, '=' ) ) {
			list( $k, $v ) = explode( '=', $k, 2 );
		} elseif ( isset( $argv_copy[ $i + 1 ] ) && 0 !== strpos( $argv_copy[ $i + 1 ], '--' ) ) {
			$v = $argv_copy[ ++$i ];
		}
		$opts[ $k ] = $v;
	}
}

$project = isset( $opts['project'] ) ? rtrim( (string) $opts['project'], '/\\' ) : cc_brain_project_dir();
putenv( 'CC_PROJECT_DIR=' . $project );
$home = cc_brain_home();

function bs_out( $s ) {
	fwrite( STDOUT, $s . "\n" );
}

/** Servers from .mcp.json that run the cc-assistant bridge. name => [url,user,pass] */
function bs_servers( $project ) {
	$file = $project . '/.mcp.json';
	if ( ! is_file( $file ) ) {
		return array();
	}
	$cfg = json_decode( (string) file_get_contents( $file ), true );
	$out = array();
	if ( ! is_array( $cfg ) || empty( $cfg['mcpServers'] ) ) {
		return $out;
	}
	foreach ( $cfg['mcpServers'] as $name => $srv ) {
		if ( ! is_array( $srv ) || empty( $srv['env']['CC_WP_URL'] ) ) {
			continue;
		}
		$pass = isset( $srv['env']['CC_WP_APP_PASSWORD'] ) ? (string) $srv['env']['CC_WP_APP_PASSWORD'] : '';
		$out[ $name ] = array(
			'url'  => rtrim( (string) $srv['env']['CC_WP_URL'], '/' ),
			'user' => isset( $srv['env']['CC_WP_USER'] ) ? (string) $srv['env']['CC_WP_USER'] : 'admin',
			'pass' => $pass,
			'ok'   => '' !== $pass && '{FILL_ME}' !== $pass,
		);
	}
	return $out;
}

/** REST caller bound to one site, in the shape operator-brain.php expects. */
function bs_rest_for( array $site ) {
	return function ( $endpoint, $method = 'GET', $body = null, $params = array() ) use ( $site ) {
		$url = $site['url'] . '/wp-json/cc-assistant/v1' . $endpoint;
		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}
		return cc_brain_http( $url, $site['user'], $site['pass'], $method, $body );
	};
}

function bs_short( $fp ) {
	return '' === (string) $fp ? '-' : substr( (string) $fp, 0, 12 );
}

$exit = 0;

switch ( $cmd ) {
	case 'status':
	case 'push':
		$servers = bs_servers( $project );
		if ( empty( $servers ) ) {
			bs_out( "No cc-assistant servers found in $project/.mcp.json" );
			exit( 1 );
		}
		$local = cc_brain_collect_local( $project, $home );
		bs_out( sprintf( 'Local brain: %d files, %s, newest %s, fingerprint %s', $local['file_count'], number_format( $local['bytes'] ) . ' B', $local['newest_utc'] ?: '-', bs_short( $local['fingerprint'] ) ) );
		bs_out( '  memory ' . $local['sources']['memory_dir'] . ' (' . $local['present']['memory'] . ' files)' );
		bs_out( '  skills ' . $local['sources']['skills_dir'] . ' (' . $local['present']['skills'] . ' files)' );
		bs_out( '' );
		bs_out( sprintf( '%-42s %-10s %-22s %-13s %-8s %s', 'site', 'plugin', 'status', 'fingerprint', 'bridge', 'note' ) );
		foreach ( $servers as $name => $site ) {
			if ( isset( $opts['only'] ) && $opts['only'] !== $name ) {
				continue;
			}
			if ( ! $site['ok'] ) {
				bs_out( sprintf( '%-42s %-10s %-22s %-13s %-8s %s', $name, '-', 'no_password', '-', '-', 'fill CC_WP_APP_PASSWORD in .mcp.json' ) );
				$exit = 1;
				continue;
			}
			$rest = bs_rest_for( $site );
			$st   = $rest( '/operator-kit/brain/status', 'GET', null, array() );
			if ( ! is_array( $st ) || isset( $st['error'] ) ) {
				$why = is_array( $st ) && isset( $st['status'] ) ? 'HTTP ' . $st['status'] : ( is_array( $st ) && isset( $st['message'] ) ? $st['message'] : 'unreachable' );
				$hint = ( is_array( $st ) && isset( $st['status'] ) && 404 === (int) $st['status'] ) ? ' (plugin < 0.79: upload the zip)' : '';
				bs_out( sprintf( '%-42s %-10s %-22s %-13s %-8s %s', $name, '-', 'error', '-', '-', $why . $hint ) );
				$exit = 1;
				continue;
			}
			$hashes = array();
			$ver    = '';
			if ( isset( $st['bridge'] ) && is_array( $st['bridge'] ) ) {
				foreach ( $st['bridge'] as $n => $e ) {
					$hashes[ $n ] = is_array( $e ) ? $e['sha1'] : $e;
					if ( is_array( $e ) && isset( $e['version'] ) ) {
						$ver = $e['version'];
					}
				}
			}
			$bridge = cc_brain_bridge_check( $hashes );
			if ( 'push' === $cmd ) {
				$r = cc_brain_tool_push( $rest, $project, $home );
				if ( isset( $r['error'] ) ) {
					bs_out( sprintf( '%-42s %-10s %-22s %-13s %-8s %s', $name, $ver ?: '-', 'push_failed', '-', $bridge['status'], json_encode( $r ) ) );
					$exit = 1;
					continue;
				}
				$note = isset( $r['pushed'] ) ? 'pushed ' . $r['pushed'] . ', deleted ' . ( isset( $r['deleted'] ) ? $r['deleted'] : 0 ) : ( isset( $r['message'] ) ? $r['message'] : '' );
				bs_out( sprintf( '%-42s %-10s %-22s %-13s %-8s %s', $name, $ver ?: '-', $r['status'], bs_short( isset( $r['site_fingerprint'] ) ? $r['site_fingerprint'] : $r['fingerprint'] ), $bridge['status'], $note ) );
				if ( 'in_sync' !== $r['status'] ) {
					$exit = 1;
				}
			} else {
				$v = cc_brain_classify( $local, $st, isset( $st['index'] ) && is_array( $st['index'] ) ? $st['index'] : array() );
				$note = '';
				if ( isset( $v['diff'] ) ) {
					$note = sprintf( 'only_local %d, only_site %d, differ %d', $v['diff']['only_local'], $v['diff']['only_site'], $v['diff']['differ'] );
				}
				if ( 'differs' === $bridge['status'] ) {
					$note .= ( $note ? '; ' : '' ) . 'bridge differs: ' . implode( ',', array_merge( $bridge['differ'], $bridge['only_site'] ) );
				}
				bs_out( sprintf( '%-42s %-10s %-22s %-13s %-8s %s', $name, $ver ?: '-', $v['status'], bs_short( $v['site_fingerprint'] ), $bridge['status'], $note ) );
				if ( 'in_sync' !== $v['status'] ) {
					$exit = 1;
				}
			}
		}
		break;

	case 'pull':
		$servers = bs_servers( $project );
		$from    = isset( $opts['from'] ) ? (string) $opts['from'] : '';
		if ( '' === $from || ! isset( $servers[ $from ] ) ) {
			bs_out( 'pull needs --from NAME, one of: ' . implode( ', ', array_keys( $servers ) ) );
			exit( 1 );
		}
		$what = isset( $opts['what'] ) ? (string) $opts['what'] : 'all';
		$mode = isset( $opts['replace'] ) ? 'replace' : 'missing_only';
		$r    = cc_brain_tool_pull( bs_rest_for( $servers[ $from ] ), $what, $mode, $project, $home );
		bs_out( json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$exit = isset( $r['error'] ) ? 1 : 0;
		break;

	case 'bootstrap':
		foreach ( array( 'url', 'user', 'pass' ) as $k ) {
			if ( empty( $opts[ $k ] ) || true === $opts[ $k ] ) {
				bs_out( 'bootstrap needs --url URL --user USER --pass "APP PASSWORD" [--project DIR] [--php PATH]' );
				exit( 1 );
			}
		}
		$site = array( 'url' => rtrim( (string) $opts['url'], '/' ), 'user' => (string) $opts['user'], 'pass' => (string) $opts['pass'] );
		$rest = bs_rest_for( $site );
		if ( ! is_dir( $project ) && ! @mkdir( $project, 0777, true ) ) {
			bs_out( 'Cannot create project dir ' . $project );
			exit( 1 );
		}
		bs_out( 'Pulling brain from ' . $site['url'] . ' into project ' . $project );
		$r = cc_brain_tool_pull( $rest, 'all', isset( $opts['replace'] ) ? 'replace' : 'missing_only', $project, $home );
		if ( isset( $r['error'] ) ) {
			bs_out( json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			exit( 1 );
		}
		bs_out( sprintf( 'Brain: wrote %d files, skipped %d existing (site updated %s)', $r['written_count'], $r['skipped_count'], $r['site_updated_at'] ) );
		bs_out( '  memory -> ' . $r['targets']['memory'] );
		bs_out( '  skills -> ' . $r['targets']['skills'] );

		// .mcp.json from the stored template, with this machine's PHP and the one password we know.
		$tpl_file = $project . '/.mcp.template.json';
		$live     = $project . '/.mcp.json';
		if ( is_file( $tpl_file ) ) {
			$php = isset( $opts['php'] ) ? (string) $opts['php'] : PHP_BINARY;
			$existing = array();
			if ( is_file( $live ) ) {
				foreach ( bs_servers( $project ) as $s ) {
					if ( $s['ok'] ) {
						$existing[ $s['url'] ] = $s['pass'];
					}
				}
			}
			$existing[ $site['url'] ] = $site['pass'];
			$rendered = cc_brain_render_mcp_template( file_get_contents( $tpl_file ), $php, $existing );
			if ( '' !== $rendered ) {
				if ( is_file( $live ) ) {
					copy( $live, $live . '.bak-' . gmdate( 'Ymd-His' ) );
				}
				file_put_contents( $live, $rendered );
				$fill = substr_count( $rendered, '{FILL_ME}' );
				bs_out( 'Wrote ' . $live . ' (PHP: ' . $php . ')' . ( $fill ? '; ' . $fill . ' password(s) still {FILL_ME}' : '' ) );
			}
		} else {
			bs_out( 'No mcp template in the brain; write .mcp.json by hand from operator_kit.mcp_json_entry.' );
		}

		// Bridge: make sure bin/ matches what the site ships.
		$b = cc_brain_tool_pull( $rest, 'bridge', 'replace', $project, $home );
		if ( isset( $b['error'] ) ) {
			bs_out( 'Bridge pull failed: ' . json_encode( $b ) );
			$exit = 1;
		} else {
			bs_out( 'Bridge: wrote ' . count( $b['written'] ) . ', unchanged ' . count( $b['unchanged'] ) . ' -> ' . $b['bin_dir'] );
		}
		bs_out( '' );
		bs_out( 'Next: start Claude Code in ' . $project . ' and call whoami. operator_brain.status and operator_brain.bridge.status must both read in_sync.' );
		break;

	default:
		bs_out( "Unknown command '$cmd'. Use status | push | pull | bootstrap." );
		$exit = 1;
}
exit( $exit );

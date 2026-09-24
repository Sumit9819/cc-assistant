<?php
/**
 * v0.76 — wcag_sweep: run axe-core (WCAG 2.1 A/AA) plus keyboard checks in a
 * real browser against the site's URLs, from the operator machine.
 *
 * Lives in bin/ (the MCP process) on purpose: the WordPress host has no
 * browser. The runner is bin/wcag/axe-run.js; its dependencies are installed
 * once into ~/.cc-assistant/wcag (next to the warehouse) so the plugin zip
 * stays small and the host never sees node_modules.
 *
 * Two lessons baked in: pages are scrolled before measuring (lazy-loaded
 * backgrounds fake contrast failures otherwise), and Elementor's own
 * toggle/accordion ARIA markup is labelled vendor so nobody tries to patch
 * it in content.
 */

function cc_wcag_dir() {
	$d = getenv( 'CC_WCAG_DIR' );
	if ( ! $d ) {
		$d = dirname( cc_wh_dir() ) . DIRECTORY_SEPARATOR . 'wcag';
	}
	if ( ! is_dir( $d ) ) {
		@mkdir( $d, 0777, true );
	}
	return $d;
}

/** Sitemap <loc> values, pure. */
function cc_wcag_parse_sitemap_xml( $xml ) {
	$out = array();
	if ( preg_match_all( '/<loc>\s*([^<\s]+)\s*<\/loc>/i', (string) $xml, $m ) ) {
		foreach ( $m[1] as $u ) {
			$out[] = html_entity_decode( trim( $u ) );
		}
	}
	return $out;
}

/** Drop taxonomy/author archives and non-HTML entries, pure. */
function cc_wcag_filter_urls( array $urls ) {
	$keep = array();
	foreach ( $urls as $u ) {
		$u = trim( (string) $u );
		if ( '' === $u || ! preg_match( '#^https?://#i', $u ) ) {
			continue;
		}
		if ( preg_match( '#/(category|tag|author|product-category|product-tag)/#i', $u ) ) {
			continue;
		}
		if ( preg_match( '#\.(xml|kml|pdf|jpe?g|png|webp|gif|svg|mp4)(\?|$)#i', $u ) || preg_match( '#/feed/?$#', $u ) ) {
			continue;
		}
		$keep[ rtrim( $u, '/' ) . '/' ] = true;
	}
	return array_keys( $keep );
}

function cc_wcag_browser_get( $url, $timeout = 30 ) {
	$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init( $url );
		curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_USERAGENT => $ua, CURLOPT_SSL_VERIFYPEER => false ) );
		$body = curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );
		return array( 'code' => $code, 'body' => (string) $body );
	}
	$ctx  = stream_context_create( array( 'http' => array( 'timeout' => $timeout, 'header' => "User-Agent: $ua\r\n", 'follow_location' => 1 ), 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false ) ) );
	$body = @file_get_contents( $url, false, $ctx );
	$code = 0;
	if ( isset( $http_response_header[0] ) && preg_match( '/\s(\d{3})\s/', $http_response_header[0], $m ) ) {
		$code = (int) $m[1];
	}
	return array( 'code' => $code, 'body' => (string) $body );
}

/** Every indexable URL from the site's sitemap index; homepage-only fallback. */
function cc_wcag_site_urls( $max ) {
	$home = rtrim( (string) getenv( 'CC_WP_URL' ), '/' );
	$note = '';
	$urls = array();
	foreach ( array( '/sitemap_index.xml', '/sitemap.xml', '/wp-sitemap.xml' ) as $p ) {
		$r = cc_wcag_browser_get( $home . $p );
		if ( 200 !== $r['code'] || false === stripos( $r['body'], '<loc>' ) ) {
			continue;
		}
		$locs = cc_wcag_parse_sitemap_xml( $r['body'] );
		$subs = array();
		foreach ( $locs as $l ) {
			if ( preg_match( '/\.xml(\?|$)/i', $l ) ) {
				$subs[] = $l;
			} else {
				$urls[] = $l;
			}
		}
		foreach ( $subs as $s ) {
			if ( preg_match( '#(category|tag|author|product_cat|product_tag)-sitemap#i', $s ) ) {
				continue;
			}
			$sr = cc_wcag_browser_get( $s );
			if ( 200 === $sr['code'] ) {
				$urls = array_merge( $urls, cc_wcag_parse_sitemap_xml( $sr['body'] ) );
			}
		}
		$note = 'urls from ' . $p;
		break;
	}
	$urls = cc_wcag_filter_urls( $urls );
	if ( empty( $urls ) ) {
		$urls = array( $home . '/' );
		$note = 'no sitemap reachable (WAF or none published); homepage only';
	}
	// Homepage first, then stable order so two runs cover the same slice.
	usort( $urls, function ( $a, $b ) use ( $home ) {
		$ah = ( rtrim( $a, '/' ) === $home ) ? 0 : 1;
		$bh = ( rtrim( $b, '/' ) === $home ) ? 0 : 1;
		return $ah === $bh ? strcmp( $a, $b ) : $ah - $bh;
	} );
	$total = count( $urls );
	if ( $max > 0 && $total > $max ) {
		$urls = array_slice( $urls, 0, $max );
		$note .= sprintf( '; capped at %d of %d URLs (raise max_pages to cover the rest)', $max, $total );
	}
	return array( 'urls' => $urls, 'total_available' => $total, 'note' => $note );
}

/* ------------------------------------------------------------ toolchain --- */

function cc_wcag_node_bin() {
	$candidates = array( 'node' );
	if ( DIRECTORY_SEPARATOR === '\\' ) {
		foreach ( array( getenv( 'ProgramFiles' ), getenv( 'ProgramFiles(x86)' ), getenv( 'LOCALAPPDATA' ) . '\\Programs\\nodejs' ) as $base ) {
			if ( $base ) {
				$candidates[] = $base . '\\nodejs\\node.exe';
				$candidates[] = $base . '\\node.exe';
			}
		}
	}
	foreach ( $candidates as $c ) {
		$r = cc_wcag_run( array( $c, '--version' ), null, 15 );
		if ( 0 === $r['code'] && preg_match( '/^v\d+/', trim( $r['out'] ) ) ) {
			return array( 'bin' => $c, 'version' => trim( $r['out'] ) );
		}
	}
	return null;
}

/** Run a command with a hard timeout; returns code/out/err. */
function cc_wcag_run( array $cmd, $cwd = null, $timeout = 60, array $env_extra = array() ) {
	$parts = array();
	foreach ( $cmd as $c ) {
		$parts[] = ( DIRECTORY_SEPARATOR === '\\' ) ? ( preg_match( '/^[\w\-.\\\\:\/]+$/', $c ) ? $c : '"' . str_replace( '"', '\"', $c ) . '"' ) : escapeshellarg( $c );
	}
	$line = implode( ' ', $parts );
	$env  = null;
	if ( ! empty( $env_extra ) ) {
		$env = array_merge( getenv() ?: array(), $env_extra );
	}
	// Child stdout/stderr go to FILES, not pipes. On Windows PHP cannot make a pipe
	// non-blocking, so reading it blocks until the child exits and the timeout below
	// never fires; a hung browser then hangs the MCP tool forever (measured 2026-08-26).
	$tmp   = rtrim( sys_get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR . 'cc-wcag-' . uniqid();
	$out_f = $tmp . '.out';
	$err_f = $tmp . '.err';
	$spec  = array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $out_f, 'w' ), 2 => array( 'file', $err_f, 'w' ) );
	// .cmd/.bat wrappers (npm.cmd) need cmd.exe; real executables run direct so the pid we get is the child itself.
	$opts = ( DIRECTORY_SEPARATOR === '\\' && ! preg_match( '/\.(cmd|bat)$/i', (string) $cmd[0] ) ) ? array( 'bypass_shell' => true ) : array();
	$proc = @proc_open( $line, $spec, $pipes, $cwd, $env, $opts );
	if ( ! is_resource( $proc ) ) {
		return array( 'code' => 127, 'out' => '', 'err' => 'could not start: ' . $line );
	}
	if ( isset( $pipes[0] ) && is_resource( $pipes[0] ) ) {
		fclose( $pipes[0] );
	}
	$t0   = microtime( true );
	$code = 0;
	while ( true ) {
		$st = proc_get_status( $proc );
		if ( ! $st['running'] ) {
			$code = (int) $st['exitcode'];
			break;
		}
		if ( microtime( true ) - $t0 > $timeout ) {
			cc_wcag_kill_tree( (int) $st['pid'] );
			$code = 124;
			break;
		}
		usleep( 250000 );
	}
	proc_close( $proc );
	$out = (string) @file_get_contents( $out_f );
	$err = (string) @file_get_contents( $err_f );
	@unlink( $out_f );
	@unlink( $err_f );
	if ( 124 === $code ) {
		$err .= "\n[timeout after {$timeout}s; process tree killed]";
	}
	return array( 'code' => $code, 'out' => $out, 'err' => $err );
}

/** Kill a child and everything it spawned (node -> chromium -> renderers). */
function cc_wcag_kill_tree( $pid ) {
	$pid = (int) $pid;
	if ( $pid <= 0 ) {
		return;
	}
	if ( DIRECTORY_SEPARATOR === '\\' ) {
		@exec( 'taskkill /T /F /PID ' . $pid . ' >NUL 2>&1' );
	} else {
		@exec( 'pkill -KILL -P ' . $pid . ' >/dev/null 2>&1' );
		if ( function_exists( 'posix_kill' ) ) {
			@posix_kill( $pid, 9 );
		}
	}
}

/** Append one timestamped line to the per-site sweep log. */
function cc_wcag_log( $file, $msg ) {
	@file_put_contents( $file, gmdate( 'Y-m-d H:i:s' ) . ' ' . $msg . "\n", FILE_APPEND );
}

/** Make sure playwright + axe-core (and a Chromium) exist in the wcag dir. */
function cc_wcag_ensure_deps( $dir, $node ) {
	$runner = __DIR__ . '/wcag/axe-run.js';
	if ( ! file_exists( $runner ) ) {
		return array( 'ok' => false, 'error' => 'runner missing: ' . $runner );
	}
	$env = array( 'NODE_PATH' => $dir . DIRECTORY_SEPARATOR . 'node_modules' );
	$log = array();
	if ( ! is_dir( $dir . '/node_modules/axe-core' ) || ! is_dir( $dir . '/node_modules/playwright' ) ) {
		@copy( __DIR__ . '/wcag/package.json', $dir . '/package.json' );
		$npm = ( DIRECTORY_SEPARATOR === '\\' ) ? 'npm.cmd' : 'npm';
		$r   = cc_wcag_run( array( $npm, 'install', '--no-audit', '--no-fund', '--prefix', $dir ), $dir, 600 );
		$log[] = 'npm install exit ' . $r['code'];
		if ( 0 !== $r['code'] ) {
			return array( 'ok' => false, 'error' => 'npm install failed: ' . substr( $r['err'] ?: $r['out'], -600 ), 'log' => $log );
		}
	}
	$chk = cc_wcag_run( array( $node, $runner, '--check' ), $dir, 60, $env );
	if ( 0 !== $chk['code'] ) {
		$log[] = 'browser check: ' . trim( $chk['err'] ?: $chk['out'] );
		// One-time Chromium download (~150 MB).
		$cli = $dir . '/node_modules/playwright/cli.js';
		$ins = cc_wcag_run( array( $node, $cli, 'install', 'chromium' ), $dir, 900, $env );
		$log[] = 'playwright install chromium exit ' . $ins['code'];
		$chk   = cc_wcag_run( array( $node, $runner, '--check' ), $dir, 60, $env );
		if ( 0 !== $chk['code'] ) {
			return array( 'ok' => false, 'error' => 'no usable Chromium: ' . trim( $chk['err'] ?: $chk['out'] ), 'log' => $log );
		}
	}
	return array( 'ok' => true, 'env' => $env, 'runner' => $runner, 'log' => $log );
}

/* ------------------------------------------------------------- summary --- */

const CC_WCAG_VENDOR_RULES = array(
	'aria-allowed-attr'    => 'Elementor toggle/tab/accordion markup (role=button on a heading with aria-controls/aria-expanded) and popup modals (aria-modal on role=document). Vendor markup; not fixable in content.',
	'aria-prohibited-attr' => 'Elementor nested accordion / testimonial icon aria-label on a plain div. Vendor markup; not fixable in content.',
	'aria-allowed-role'    => 'Elementor Posts widget renders <article role=...>. Vendor markup, best-practice only.',
);

/** Pure: aggregate a runner JSON into the report the assistant reads. */
function cc_wcag_summarize( array $data ) {
	$pages   = isset( $data['pages'] ) && is_array( $data['pages'] ) ? $data['pages'] : array();
	$ok      = array_filter( $pages, function ( $p ) { return isset( $p['status'] ) && 200 === (int) $p['status'] && empty( $p['error'] ); } );
	$errors  = array_filter( $pages, function ( $p ) { return ! empty( $p['error'] ); } );
	$rules   = array();
	$clean   = 0;
	foreach ( $ok as $p ) {
		if ( empty( $p['violations'] ) ) {
			$clean++;
		}
		foreach ( (array) $p['violations'] as $v ) {
			$id = (string) $v['id'];
			if ( ! isset( $rules[ $id ] ) ) {
				$rules[ $id ] = array(
					'rule'     => $id,
					'impact'   => (string) $v['impact'],
					'wcag'     => implode( ',', (array) $v['wcag'] ) ?: 'best-practice',
					'help'     => (string) $v['help'],
					'pages'    => 0,
					'nodes'    => 0,
					'examples' => array(),
					'vendor'   => isset( CC_WCAG_VENDOR_RULES[ $id ] ) ? CC_WCAG_VENDOR_RULES[ $id ] : null,
				);
			}
			$rules[ $id ]['pages']++;
			$rules[ $id ]['nodes'] += (int) $v['count'];
			if ( count( $rules[ $id ]['examples'] ) < 3 && ! empty( $v['nodes'][0] ) ) {
				$rules[ $id ]['examples'][] = array(
					'url'     => (string) $p['url'],
					'html'    => (string) $v['nodes'][0]['html'],
					'summary' => (string) $v['nodes'][0]['summary'],
				);
			}
		}
	}
	$order = array( 'critical' => 0, 'serious' => 1, 'moderate' => 2, 'minor' => 3 );
	uasort( $rules, function ( $a, $b ) use ( $order ) {
		$ia = isset( $order[ $a['impact'] ] ) ? $order[ $a['impact'] ] : 9;
		$ib = isset( $order[ $b['impact'] ] ) ? $order[ $b['impact'] ] : 9;
		return $ia === $ib ? $b['nodes'] - $a['nodes'] : $ia - $ib;
	} );
	// Contrast is the noisiest rule; group its nodes by the element/colour
	// pair so a 800-node count collapses into the 3 template rules behind it.
	$contrast = array();
	foreach ( $ok as $p ) {
		foreach ( (array) $p['violations'] as $v ) {
			if ( 'color-contrast' !== $v['id'] ) {
				continue;
			}
			foreach ( (array) $v['nodes'] as $n ) {
				$cls = preg_match( '/class="([^"]*)"/', $n['html'], $cm ) ? preg_replace( '/\b(elementor-element-[a-z0-9]+|post-\d+|elementor-widget-\S+)\b/', '', $cm[1] ) : substr( $n['html'], 0, 30 );
				$cls = trim( preg_replace( '/\s+/', ' ', $cls ) );
				$fg  = preg_match( '/foreground color: (#[0-9a-fA-F]+)/', $n['summary'], $f ) ? strtolower( $f[1] ) : '?';
				$bg  = preg_match( '/background color: (#[0-9a-fA-F]+)/', $n['summary'], $b ) ? strtolower( $b[1] ) : '?';
				$rt  = preg_match( '/contrast of ([\d.]+)/', $n['summary'], $r ) ? $r[1] : '?';
				$key = $cls . '|' . $fg . '|' . $bg;
				if ( ! isset( $contrast[ $key ] ) ) {
					$contrast[ $key ] = array( 'element' => substr( $cls, 0, 70 ), 'fg' => $fg, 'bg' => $bg, 'ratio' => $rt, 'sampled_nodes' => 0, 'example_url' => (string) $p['url'] );
				}
				$contrast[ $key ]['sampled_nodes']++;
			}
		}
	}
	usort( $contrast, function ( $a, $b ) { return $b['sampled_nodes'] - $a['sampled_nodes']; } );

	$kb = array( 'pages_checked' => 0, 'skip_link_missing' => 0, 'skip_link_target_missing' => 0, 'focus_invisible_pages' => 0, 'focus_invisible_examples' => array(), 'forms_silent' => array(), 'popup_traps' => array() );
	foreach ( $ok as $p ) {
		if ( empty( $p['keyboard'] ) || ! is_array( $p['keyboard'] ) ) {
			continue;
		}
		$k = $p['keyboard'];
		$kb['pages_checked']++;
		if ( isset( $k['skip_link']['present'] ) && ! $k['skip_link']['present'] ) {
			$kb['skip_link_missing']++;
		} elseif ( isset( $k['skip_link']['target_exists'] ) && ! $k['skip_link']['target_exists'] ) {
			$kb['skip_link_target_missing']++;
		}
		if ( ! empty( $k['focus_invisible'] ) ) {
			$kb['focus_invisible_pages']++;
			if ( count( $kb['focus_invisible_examples'] ) < 5 ) {
				$kb['focus_invisible_examples'][] = array( 'url' => (string) $p['url'], 'elements' => array_slice( $k['focus_invisible'], 0, 4 ) );
			}
		}
		foreach ( (array) ( isset( $k['forms'] ) ? $k['forms'] : array() ) as $f ) {
			if ( isset( $f['result'] ) && 'none' === $f['result'] && count( $kb['forms_silent'] ) < 10 ) {
				$kb['forms_silent'][] = array( 'url' => (string) $p['url'], 'form' => $f['form'], 'fields' => $f['fields'] );
			}
		}
		if ( ! empty( $k['popup']['open'] ) && ( empty( $k['popup']['focus_inside'] ) || empty( $k['popup']['escape_closes'] ) ) && count( $kb['popup_traps'] ) < 10 ) {
			$kb['popup_traps'][] = array( 'url' => (string) $p['url'], 'popup' => $k['popup'] );
		}
	}

	return array(
		'pages_audited'    => count( $ok ),
		'pages_errored'    => count( $errors ),
		'pages_clean'      => $clean,
		'error_examples'   => array_slice( array_map( function ( $p ) { return array( 'url' => $p['url'], 'error' => $p['error'] ); }, array_values( $errors ) ), 0, 5 ),
		'rules'            => array_values( $rules ),
		'contrast_groups'  => array_slice( $contrast, 0, 15 ),
		'keyboard'         => $kb,
	);
}

/* ---------------------------------------------------------------- tool --- */

function cc_tool_wcag_sweep( $args ) {
	$action = isset( $args['action'] ) ? strtolower( (string) $args['action'] ) : 'run';
	$dir    = cc_wcag_dir();
	$site   = function_exists( 'cc_wh_site_key' ) ? cc_wh_site_key() : 'site';
	$store  = $dir . DIRECTORY_SEPARATOR . $site . '.json';

	if ( 'report' === $action ) {
		if ( ! file_exists( $store ) ) {
			return array( 'error' => 'no_report', 'message' => 'No sweep stored for this site yet. Run wcag_sweep first.' );
		}
		$data = json_decode( (string) file_get_contents( $store ), true );
		if ( ! is_array( $data ) ) {
			return array( 'error' => 'bad_report', 'message' => 'Stored sweep is unreadable; re-run.' );
		}
		return array( 'ran_at' => $data['ran_at'] ?? null, 'inventory' => $data['inventory'] ?? null, 'summary' => cc_wcag_summarize( $data ) );
	}

	$node = cc_wcag_node_bin();
	if ( ! $node ) {
		return array( 'error' => 'node_missing', 'message' => 'Node.js was not found on this machine. Install Node 18+ (nodejs.org) and restart the MCP; the sweep runs a real browser locally.' );
	}
	$deps = cc_wcag_ensure_deps( $dir, $node['bin'] );
	if ( empty( $deps['ok'] ) ) {
		return array( 'error' => 'toolchain', 'message' => $deps['error'], 'log' => $deps['log'] ?? array(), 'dir' => $dir );
	}

	$max   = isset( $args['max_pages'] ) ? max( 1, min( 1000, (int) $args['max_pages'] ) ) : 40;
	$conc  = isset( $args['concurrency'] ) ? max( 1, min( 8, (int) $args['concurrency'] ) ) : 4;
	$kbd   = ! ( isset( $args['keyboard'] ) && false === filter_var( $args['keyboard'], FILTER_VALIDATE_BOOLEAN ) );
	if ( ! empty( $args['urls'] ) && is_array( $args['urls'] ) ) {
		$urls = cc_wcag_filter_urls( $args['urls'] );
		$inv  = array( 'urls' => $urls, 'total_available' => count( $urls ), 'note' => 'explicit url list' );
	} else {
		$inv  = cc_wcag_site_urls( $max );
		$urls = $inv['urls'];
	}
	if ( empty( $urls ) ) {
		return array( 'error' => 'no_urls', 'message' => 'Nothing to audit.' );
	}
	$urls_file = $dir . DIRECTORY_SEPARATOR . $site . '.urls.txt';
	$raw_file  = $dir . DIRECTORY_SEPARATOR . $site . '.raw.json';
	$log_file  = $dir . DIRECTORY_SEPARATOR . $site . '.log';
	$prog_file = $raw_file . '.progress.log';
	file_put_contents( $urls_file, implode( "\n", $urls ) );
	@unlink( $raw_file );
	@unlink( $prog_file );

	$timeout = 90 + count( $urls ) * ( $kbd ? 14 : 8 );
	cc_wcag_log( $log_file, sprintf( 'run start: %d urls, concurrency %d, keyboard %s, timeout %ds, node %s', count( $urls ), $conc, $kbd ? 'on' : 'off', $timeout, $node['version'] ) );
	$t_run = microtime( true );
	$run   = cc_wcag_run( array( $node['bin'], $deps['runner'], '--urls', $urls_file, '--out', $raw_file, '--concurrency', (string) $conc, '--keyboard', $kbd ? '1' : '0' ), $dir, $timeout, $deps['env'] );
	cc_wcag_log( $log_file, sprintf( 'run end: exit %d after %ds, raw %s', $run['code'], (int) ( microtime( true ) - $t_run ), file_exists( $raw_file ) ? 'written' : 'MISSING' ) );
	if ( ! file_exists( $raw_file ) ) {
		$progress = file_exists( $prog_file ) ? (string) file_get_contents( $prog_file ) : '';
		$lines    = array_values( array_filter( explode( "\n", trim( $progress ) ) ) );
		return array(
			'error'    => 'run_failed',
			'exit'     => $run['code'],
			'message'  => substr( trim( $run['err'] ?: $run['out'] ), -800 ),
			'hint'     => 124 === $run['code'] ? 'Timed out and the process tree was killed; lower max_pages or raise concurrency. The progress tail shows the last page reached.' : null,
			'progress' => array( 'pages_logged' => count( $lines ), 'tail' => array_slice( $lines, -6 ), 'file' => $prog_file ),
			'log'      => $log_file,
		);
	}
	$data = json_decode( (string) file_get_contents( $raw_file ), true );
	if ( ! is_array( $data ) ) {
		return array( 'error' => 'bad_output', 'message' => 'Runner produced unreadable JSON.', 'stderr' => substr( $run['err'], -400 ) );
	}
	$data['inventory'] = array( 'requested' => count( $urls ), 'total_available' => $inv['total_available'], 'note' => $inv['note'] );
	file_put_contents( $store, wp_json_encode_compat( $data ) );

	$summary = cc_wcag_summarize( $data );
	return array(
		'ran_at'    => $data['ran_at'],
		'node'      => $node['version'],
		'inventory' => $data['inventory'],
		'summary'   => $summary,
		'stored_at' => $store,
		'reading'   => 'Fix order: critical/serious rules with wcag tags first (content-level: empty links, missing H1, unlabeled fields, iframe titles); contrast_groups names the few template colours behind hundreds of nodes (kit / Theme Builder edits, now queueable via draft_update_kit_setting and template widget edits); rules marked vendor are Elementor markup and are reported only. keyboard.focus_invisible and forms_silent are the failures no static audit sees.',
	);
}

/** wp_json_encode is WordPress-only; the MCP process is plain PHP. */
function wp_json_encode_compat( $v ) {
	return json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

<?php
/**
 * CC Assistant MCP Server
 *
 * Stdio JSON-RPC 2.0 server that exposes WordPress tools to Claude Code via MCP.
 * Reads requests from stdin, writes responses to stdout, logs errors to stderr.
 *
 * Configured via environment variables in .mcp.json:
 *   CC_WP_URL          - WordPress site URL (e.g. http://plugintesting.local)
 *   CC_WP_USER         - WordPress CC Assistant Operator username (recommended)
 *   CC_WP_APP_PASSWORD - WordPress application password
 */

if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "CC Assistant MCP server must be run from the command line.\n" );
	exit( 1 );
}

// STDOUT is the JSON-RPC stream — a single stray PHP warning printed there
// (CLI default is display_errors=STDOUT) becomes protocol garbage for the
// MCP client. Route all PHP diagnostics to STDERR instead.
ini_set( 'display_errors', 'stderr' );

$WP_URL      = getenv( 'CC_WP_URL' );
$WP_USER     = getenv( 'CC_WP_USER' );
$WP_PASSWORD = getenv( 'CC_WP_APP_PASSWORD' );

if ( ! $WP_URL || ! $WP_USER || ! $WP_PASSWORD ) {
	fwrite( STDERR, "CC Assistant MCP: missing config. Set CC_WP_URL, CC_WP_USER, CC_WP_APP_PASSWORD in .mcp.json env.\n" );
	exit( 1 );
}

// Bridge build version. Keep in sync with CC_ASSISTANT_VERSION — used for
// serverInfo AND the whoami version-drift warning (the local plugin copy is
// always the newest build, since this is where releases are made).
define( 'CC_MCP_VERSION', '0.88.0' );

// HTTP transport: prefer curl when available, fall back to PHP streams.
$HAS_CURL = function_exists( 'curl_init' );

// Desktop GSC warehouse (v0.54): local SQLite archive of full-fidelity
// Search Console data. Lives in its own file to keep this one navigable.
require __DIR__ . '/warehouse.php';
require __DIR__ . '/site-status.php';
require __DIR__ . '/keyword-targets.php';
require __DIR__ . '/wcag-sweep.php';
// Operator Brain (v0.79): the site stores memory + skills + bridge; whoami
// tells every new chat on every machine whether it is behind.
require __DIR__ . '/operator-brain.php';

$TOOLS = require __DIR__ . '/tool-catalog.php';
$cc_contract = require __DIR__ . '/agent-contract.php';
foreach ( $TOOLS as &$cc_tool ) {
    $cc_tool['description'] = $cc_contract['tool_descriptions'][$cc_tool['name']] ?? 'Capability description unavailable; inspect the current shared contract before planning.';
}
unset( $cc_tool, $cc_contract );

function wp_rest_call( $endpoint, $method = 'GET', $body = null, $params = array() ) {
	global $WP_URL, $WP_USER, $WP_PASSWORD, $HAS_CURL;

	$url = rtrim( $WP_URL, '/' ) . '/wp-json/cc-assistant/v1' . $endpoint;
	if ( ! empty( $params ) ) {
		$url .= '?' . http_build_query( $params );
	}

	$transport = getenv( 'CC_MCP_TRANSPORT' ) ?: 'http';
	if ( 'browser' === $transport ) {
		require_once __DIR__ . '/browser-transport.php';
		return cc_mcp_browser_call( $url, $method, $body );
	}
	$result = $HAS_CURL
		? wp_rest_call_curl( $url, $method, $body )
		: wp_rest_call_streams( $url, $method, $body );

	// v0.59 transport retry: SiteGround intermittently drops connections under
	// sustained sequential load (SSL_ERROR_SYSCALL mid-warehouse-sync). Retry
	// GETs only — a POST retry could double-queue if the response was lost
	// AFTER the server processed it. Two extra attempts, short backoff.
	if ( 'GET' === $method && is_array( $result ) && isset( $result['error'] )
		&& in_array( $result['error'], array( 'curl_error', 'http_error' ), true )
		&& ! isset( $result['status'] ) ) {
		for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
			usleep( $attempt * 400000 );
			$result = $HAS_CURL
				? wp_rest_call_curl( $url, $method, $body )
				: wp_rest_call_streams( $url, $method, $body );
			// Stop on success OR on a definitive HTTP-status error (403/404
			// won't change on retry — only connection-level failures might).
			if ( ! is_array( $result ) || ! isset( $result['error'] ) || isset( $result['status'] ) ) {
				break;
			}
		}
	}
	return $result;
}

/**
 * Outbound User-Agent for REST calls to the WordPress install.
 *
 * IMPORTANT: must NOT advertise a bot/automation token. SiteGround's WAF (and
 * similar managed-WordPress firewalls) hard-block requests whose UA carries an
 * automation signature — the previous 'Mozilla/5.0 (compatible; cc-assistant-mcp/1.0)'
 * UA started returning a 403 on every endpoint, including /wp-json/. A plain
 * browser UA reaches WordPress normally; auth is still fully enforced by the
 * application password. Override per-install with CC_MCP_USER_AGENT if a host
 * needs a different string.
 */
/** True when the target URL should get strict TLS verification (v0.60.1). */
function cc_mcp_should_verify_tls( $url ) {
	$env = getenv( 'CC_MCP_VERIFY_TLS' );
	if ( '1' === $env ) {
		return true;
	}
	if ( '0' === $env ) {
		return false;
	}
	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
	if ( '' === $host || 'localhost' === $host || '127.0.0.1' === $host || '::1' === $host ) {
		return false;
	}
	foreach ( array( '.local', '.test', '.internal' ) as $suffix ) {
		if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
			return false;
		}
	}
	return true;
}

function cc_mcp_user_agent() {
	$ua = getenv( 'CC_MCP_USER_AGENT' );
	if ( is_string( $ua ) && $ua !== '' ) {
		return $ua;
	}
	return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
}

function wp_rest_call_curl( $url, $method, $body ) {
	global $WP_USER, $WP_PASSWORD;

	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_USERPWD, $WP_USER . ':' . $WP_PASSWORD );
	curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Accept: application/json' ) );
	curl_setopt( $ch, CURLOPT_USERAGENT, cc_mcp_user_agent() );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
	// SSL (v0.60.1 security audit): verification is ON by default for
	// non-local hosts — this connection carries an admin Application Password
	// to production sites, so accepting any certificate was a live
	// credential-exposure path. The v0.35 "no CA bundle on Windows + Local"
	// blocker is solved with CURLSSLOPT_NATIVE_CA (Windows cert store,
	// verified working). Local dev hosts (.local/.test/localhost) keep
	// verification off (self-signed). Overrides: CC_MCP_VERIFY_TLS=0 forces
	// off (escape hatch), =1 forces on even for local hosts.
	if ( cc_mcp_should_verify_tls( $url ) ) {
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
		if ( defined( 'CURLSSLOPT_NATIVE_CA' ) && '' === (string) ini_get( 'curl.cainfo' ) ) {
			curl_setopt( $ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA );
		}
	} else {
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
	}
	if ( $body !== null ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $body ) );
	}

	$response = curl_exec( $ch );
	$code     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$err      = curl_error( $ch );

	if ( $err ) {
		return array( 'error' => 'curl_error', 'message' => $err );
	}
	if ( $code >= 400 ) {
		return array( 'error' => 'http_error', 'status' => $code, 'body' => $response );
	}
	// Mirror the streams transport: a 200 that is not JSON (host cache page,
	// WAF challenge, maintenance HTML) must surface as a transport error, not
	// decode to null — callers would misread null as "field missing" and give
	// wrong advice (e.g. "GSC not connected" when GSC is fine).
	$data = json_decode( $response, true );
	if ( $data === null && $response !== 'null' ) {
		// Name the cause when we can recognise it. A bare "not JSON" sent a
		// session chasing the user agent and then the config for an hour.
		$explained = cc_mcp_explain_non_json( $response );
		if ( null !== $explained ) {
			return $explained;
		}
		return array(
			'error'   => 'json_parse_error',
			'message' => 'Could not parse response as JSON',
			'body'    => substr( (string) $response, 0, 500 ),
		);
	}
	return $data;
}

/**
 * Explain a response body that is not JSON.
 *
 * "Could not parse response as JSON" is true and useless. On SiteGround there
 * are two causes that look identical in a log and have completely different
 * fixes, and a session that cannot tell them apart wastes a long time on the
 * wrong one (this happened on 2026-09-07: an IP-reputation challenge was
 * repeatedly misdiagnosed as the user-agent block, and then as a config problem).
 *
 *   202 + sgcaptcha  -> IP REPUTATION. Every site on their platform is affected
 *                       at once, including ones never touched. No UA, cookie or
 *                       config change helps; the clearance is bound to a real
 *                       browser. Fix the IP, or use the browser transport.
 *   403 + SiteGround -> USER AGENT. Send a plain browser UA (already the default
 *                       since v0.35.3; override with CC_MCP_USER_AGENT).
 */
function cc_mcp_explain_non_json( $body ) {
	$body = (string) $body;

	if ( false !== strpos( $body, 'sgcaptcha' ) || false !== strpos( $body, '/.well-known/captcha' ) ) {
		$ip = '';
		if ( preg_match( '/y=ip[rc]:([0-9.]+)/', $body, $m ) ) {
			$ip = $m[1];
		}
		return array(
			'error'   => 'siteground_ip_challenge',
			'message' => 'SiteGround returned a browser challenge instead of API JSON'
				. ( $ip ? ', for IP ' . $ip : '' )
				. '. The cause and scope are unknown from this response. Check the selected site entry in .mcp.json and restart its MCP server after changes. '
				. 'Use CC_MCP_TRANSPORT=browser with a dedicated cleared Chrome profile and the existing Application Password; see CONNECTION.md. '
				. 'Complete any required normal browser challenge or contact SiteGround support. Browser clearance cookies may be tied to their browser context; copying one to curl is not a reliable connection method. '
				. 'Keep the plugin approval queue and site protection in place.',
			'diagnosis' => array( 'observed' => 'browser_challenge', 'root_cause' => 'unknown', 'other_sites_affected' => 'unknown' ),
			'body'    => substr( $body, 0, 300 ),
		);
	}

	if ( false !== stripos( $body, 'siteground' ) && preg_match( '/access denied|forbidden|blocked|security rules/i', $body ) ) {
		return array(
			'error'   => 'siteground_waf_block',
			'message' => 'A SiteGround-branded access denial was returned instead of API JSON. The response does not establish whether the cause is authentication, a WAF rule, request headers or a network policy. Check the response and hosting logs or contact SiteGround support; do not assume a User-Agent change resolves it.',
			'body'    => substr( $body, 0, 300 ),
		);
	}

	return null;
}

function wp_rest_call_streams( $url, $method, $body ) {
	global $WP_USER, $WP_PASSWORD;

	$auth    = base64_encode( $WP_USER . ':' . $WP_PASSWORD );
	$headers = array(
		'Authorization: Basic ' . $auth,
		'Content-Type: application/json',
		'Accept: application/json',
		'User-Agent: ' . cc_mcp_user_agent(),
	);

	// SSL (v0.60.1): verify by default for non-local hosts, matching the cURL
	// transport. Note: PHP streams cannot use the Windows native cert store —
	// if this fallback path fails verification, set CC_MCP_VERIFY_TLS=0 or
	// (better) ensure the curl extension is loaded.
	$verify_tls = cc_mcp_should_verify_tls( $url );
	$opts = array(
		'http' => array(
			'method'        => $method,
			'header'        => implode( "\r\n", $headers ),
			'timeout'       => 30,
			'ignore_errors' => true,
		),
		'ssl'  => array(
			'verify_peer'      => $verify_tls,
			'verify_peer_name' => $verify_tls,
		),
	);
	if ( $body !== null ) {
		$opts['http']['content'] = json_encode( $body );
	}

	$context  = stream_context_create( $opts );
	$response = @file_get_contents( $url, false, $context );

	if ( $response === false ) {
		$err = error_get_last();
		return array(
			'error'   => 'http_error',
			'message' => isset( $err['message'] ) ? $err['message'] : 'Unknown HTTP error',
		);
	}

	$status = 0;
	if ( isset( $http_response_header[0] ) && preg_match( '#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m ) ) {
		$status = (int) $m[1];
	}

	if ( $status >= 400 ) {
		return array( 'error' => 'http_error', 'status' => $status, 'body' => $response );
	}

	$data = json_decode( $response, true );
	if ( $data === null && $response !== 'null' ) {
		// Name the cause when we can recognise it. A bare "not JSON" sent a
		// session chasing the user agent and then the config for an hour.
		$explained = cc_mcp_explain_non_json( $response );
		if ( null !== $explained ) {
			return $explained;
		}
		return array(
			'error'   => 'json_parse_error',
			'message' => 'Could not parse response as JSON',
			'body'    => substr( $response, 0, 500 ),
		);
	}
	return $data;
}

// cc_unwrap_envelope() lives in warehouse.php (loaded above) so warehouse
// tools can use it standalone — e.g. under the test harness.

function format_tool_result( $data, $heuristic = false ) {
	$data = cc_unwrap_envelope( $data );
	if ( $heuristic && is_array( $data ) && ! isset( $data['error'] ) ) {
		$data['evidence_policy'] = array( 'classification' => 'heuristic', 'google_score' => false, 'instruction' => 'Local scores and action suggestions are hypotheses. Check source age, coverage, verified_page_audit and installed plugin capabilities. Do not use this result alone to claim defects, guarantees or authorize destructive changes.' );
	}
	if ( isset( $data['error'] ) ) {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => 'Error: ' . json_encode( $data, JSON_UNESCAPED_SLASHES ),
				),
			),
			'isError' => true,
		);
	}
	return array(
		'content' => array(
			array(
				'type' => 'text',
				'text' => json_encode( $data, JSON_UNESCAPED_SLASHES ),
			),
		),
	);
}

function handle_tool_call( $tool_name, $arguments ) {
	switch ( $tool_name ) {
		case 'whoami':
			$resp = cc_unwrap_envelope( wp_rest_call( '/whoami' ) );
			// v0.58 version-drift check (v0.59: read from the UNWRAPPED body —
			// the original read $resp['plugin_version'] above the data envelope
			// and would have false-warned on every site).
			if ( is_array( $resp ) && ! isset( $resp['error'] ) ) {
				$site_ver = isset( $resp['plugin_version'] ) ? (string) $resp['plugin_version'] : '';
				if ( '' === $site_ver ) {
					$resp['version_drift'] = 'Site did not report a plugin version (pre-0.54 build?) — deploy the latest cc-assistant zip; newer tools will fail until then.';
				} elseif ( version_compare( $site_ver, CC_MCP_VERSION, '<' ) ) {
					$resp['version_drift'] = 'Site runs cc-assistant v' . $site_ver . ' but the local build is v' . CC_MCP_VERSION . ' — deploy the latest zip; tools added after v' . $site_ver . ' will 404 on this site until then.';
				}
				// v0.79: the other direction (stale laptop) + brain verdict +
				// the rules that apply to this site, pushed into the first call.
				cc_brain_decorate_whoami( $resp, CC_MCP_VERSION );
			}
			return format_tool_result( $resp );

		case 'seo_playbook':
			return format_tool_result( wp_rest_call( '/seo-playbook' ) );

		case 'health':
			return format_tool_result( wp_rest_call( '/health' ) );

		case 'list_posts':
			$params = array();
			foreach ( array( 'post_type', 'status', 'per_page', 'page', 'search' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/posts', 'GET', null, $params ) );

		case 'get_post':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( isset( $arguments['slim'] ) ) {
				$params['slim'] = $arguments['slim'] ? 'true' : 'false';
			}
			if ( ! empty( $arguments['widget_id'] ) ) {
				$params['widget_id'] = (string) $arguments['widget_id'];
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'], 'GET', null, $params ) );

		case 'get_elementor_widgets':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( isset( $arguments['format'] ) ) {
				$params['format'] = $arguments['format'];
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'] . '/elementor-widgets', 'GET', null, $params ) );

		case 'get_page_map':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( isset( $arguments['include_ascii'] ) ) {
				$params['include_ascii'] = $arguments['include_ascii'] ? 'true' : 'false';
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'] . '/page-map', 'GET', null, $params ) );

		case 'entity_lookup':
			if ( ! isset( $arguments['q'] ) || '' === trim( (string) $arguments['q'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: q' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/entity-lookup', 'GET', null, array( 'q' => (string) $arguments['q'] ) ) );

		case 'layout_spec':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'] . '/layout-spec', 'GET', null, array() ) );

		case 'layout_compare':
			if ( ! isset( $arguments['id'] ) || ! isset( $arguments['reference_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id and reference_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'] . '/layout-compare', 'GET', null, array( 'reference_id' => (int) $arguments['reference_id'] ) ) );

		case 'render_probe':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( ! empty( $arguments['extract'] ) ) {
				$params['extract'] = is_array( $arguments['extract'] )
					? implode( ',', $arguments['extract'] )
					: (string) $arguments['extract'];
			}
			if ( ! empty( $arguments['diff'] ) ) {
				$params['diff'] = 'true';
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'] . '/render-probe', 'GET', null, $params ) );

		case 'get_elementor_tree':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'] . '/elementor-tree', 'GET' ) );

		case 'schema_scan':
			$params = array();
			if ( ! empty( $arguments['post_ids'] ) ) {
				$params['post_ids'] = is_array( $arguments['post_ids'] )
					? implode( ',', $arguments['post_ids'] )
					: (string) $arguments['post_ids'];
			}
			if ( isset( $arguments['limit'] ) ) {
				$params['limit'] = (int) $arguments['limit'];
			}
			return format_tool_result( wp_rest_call( '/schema-scan', 'GET', null, $params ) );

		case 'redirect_audit':
			return format_tool_result( wp_rest_call( '/redirect-audit', 'GET' ) );

		case 'managed_schema':
			$params = array();
			if ( isset( $arguments['post_id'] ) ) {
				$params['post_id'] = (int) $arguments['post_id'];
			}
			return format_tool_result( wp_rest_call( '/managed-schema', 'GET', null, $params ) );

		case 'draft_rebuild_section':
			if ( ! isset( $arguments['post_id'] ) || ! isset( $arguments['remove_id'] ) || ! isset( $arguments['settings'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument(s): post_id, remove_id, and settings are all required.' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/draft/elementor-section-rebuild', 'POST', $arguments ) );

		case 'draft_add_section':
			if ( ! isset( $arguments['post_id'] ) || ! isset( $arguments['recipe'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument(s): post_id and recipe are required.' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/draft/add-section', 'POST', $arguments ) );

		case 'find_topic_clusters':
			$params = array();
			foreach ( array( 'post_type', 'threshold', 'limit', 'format' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/clusters', 'GET', null, $params ) );

		case 'analyze_topic_cluster':
			if ( ! isset( $arguments['post_ids'] ) || ! is_array( $arguments['post_ids'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_ids (array of integers)' ) ),
					'isError' => true,
				);
			}
			$params = array( 'post_ids' => implode( ',', array_map( 'intval', $arguments['post_ids'] ) ) );
			if ( isset( $arguments['excerpt_chars'] ) ) {
				$params['excerpt_chars'] = (int) $arguments['excerpt_chars'];
			}
			return format_tool_result( wp_rest_call( '/clusters/analyze', 'GET', null, $params ) );

		case 'list_pending_changes':
			return format_tool_result( wp_rest_call( '/pending' ) );

		case 'draft_update_post_meta':
			foreach ( array( 'post_id', 'field', 'value' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/post-meta', 'POST', $arguments ) );

		case 'draft_update_postmeta':
			foreach ( array( 'post_id', 'meta_key', 'value' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/postmeta', 'POST', $arguments ) );

		case 'draft_trash_post':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/draft/trash-post', 'POST', $arguments ) );

		case 'draft_update_elementor_widget':
			foreach ( array( 'post_id', 'widget_id', 'settings' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/elementor-widget', 'POST', $arguments ) );

		case 'draft_add_elementor_widget':
			foreach ( array( 'post_id', 'parent_id', 'widget_type', 'settings' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/elementor-widget-add', 'POST', $arguments ) );

		case 'draft_remove_elementor_widget':
			foreach ( array( 'post_id', 'widget_id' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/elementor-widget-remove', 'POST', $arguments ) );

		case 'draft_add_elementor_container':
			foreach ( array( 'post_id', 'parent_id', 'settings' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/elementor-container-add', 'POST', $arguments ) );

		case 'draft_add_accordion_item':
			foreach ( array( 'post_id', 'accordion_widget_id', 'title' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/elementor-accordion-item-add', 'POST', $arguments ) );

		case 'draft_remove_accordion_item':
			foreach ( array( 'post_id', 'accordion_widget_id', 'item_id' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/elementor-accordion-item-remove', 'POST', $arguments ) );

		case 'build_service_page':
			foreach ( array( 'mirror_post_id', 'title' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/build-service-page', 'POST', $arguments ) );

		case 'audit_page_design':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/audit-design', 'GET' ) );

		case 'list_sections':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/sections', 'GET' ) );

		case 'list_theme_templates':
			return format_tool_result( wp_rest_call( '/theme-templates', 'GET', null, array() ) );

		case 'refresh_theme_builder_conditions':
			return format_tool_result( wp_rest_call( '/theme-templates/refresh-conditions', 'POST', array() ) );

		case 'list_popups':
			$params = array();
			if ( isset( $arguments['covers_post_id'] ) && (int) $arguments['covers_post_id'] > 0 ) {
				$params['covers_post_id'] = (int) $arguments['covers_post_id'];
			}
			return format_tool_result( wp_rest_call( '/popups', 'GET', null, $params ) );

		case 'replace_section_content':
			foreach ( array( 'post_id', 'widget_updates' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			$payload = array(
				'section_id'     => isset( $arguments['section_id'] ) ? (string) $arguments['section_id'] : '',
				'widget_updates' => $arguments['widget_updates'],
				'reasoning'      => isset( $arguments['reasoning'] ) ? (string) $arguments['reasoning'] : '',
				'override_lint'  => isset( $arguments['override_lint'] ) ? (bool) $arguments['override_lint'] : false,
			);
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/sections/replace-content', 'POST', $payload ) );

		case 'list_image_placeholders':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/image-placeholders', 'GET' ) );

		case 'export_elementor_data':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/elementor-export', 'GET' ) );

		case 'import_elementor_data':
			foreach ( array( 'post_id', 'raw_data' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			$payload = $arguments;
			unset( $payload['post_id'] );
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/elementor-import', 'POST', $payload ) );

		case 'suggest_best_mirror':
			return format_tool_result( wp_rest_call( '/draft/suggest-best-mirror', 'POST', $arguments ) );

		case 'page_completeness_score':
			foreach ( array( 'post_id', 'mirror_post_id' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/page-completeness-score', 'GET', null, array(
				'post_id'        => (int) $arguments['post_id'],
				'mirror_post_id' => (int) $arguments['mirror_post_id'],
			) ) );

		case 'service_inventory':
			return format_tool_result( wp_rest_call( '/service-inventory', 'GET' ) );

		case 'reject_pending_change':
			// Accept either pending_ids (array) or pending_id (single int).
			$ids = array();
			if ( isset( $arguments['pending_ids'] ) && is_array( $arguments['pending_ids'] ) ) {
				foreach ( $arguments['pending_ids'] as $maybe_id ) {
					$n = (int) $maybe_id;
					if ( $n > 0 ) {
						$ids[] = $n;
					}
				}
			}
			if ( empty( $ids ) && isset( $arguments['pending_id'] ) ) {
				$n = (int) $arguments['pending_id'];
				if ( $n > 0 ) {
					$ids[] = $n;
				}
			}
			if ( empty( $ids ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: pending_id (int) or pending_ids (array of ints).' ) ),
					'isError' => true,
				);
			}
			$note    = isset( $arguments['note'] ) ? (string) $arguments['note'] : '';
			$results = array();
			$ok      = 0;
			$fail    = 0;
			foreach ( $ids as $rid ) {
				$body   = array();
				if ( '' !== $note ) {
					$body['note'] = $note;
				}
				$res = wp_rest_call( '/pending/' . $rid . '/reject', 'POST', $body );
				if ( is_array( $res ) && isset( $res['data'] ) && empty( $res['error'] ) ) {
					$ok++;
					$results[] = array(
						'pending_id'        => $rid,
						'status'            => 'rejected',
						'trashed_post_id'   => $res['data']['trashed_post_id']   ?? null,
						'snapshots_deleted' => $res['data']['snapshots_deleted'] ?? 0,
					);
				} else {
					$fail++;
					$results[] = array(
						'pending_id' => $rid,
						'status'     => 'failed',
						'error'      => $res['error'] ?? ( $res['message'] ?? 'unknown error' ),
					);
				}
			}
			return format_tool_result( array(
				'data' => array(
					'rejected_count' => $ok,
					'failed_count'   => $fail,
					'results'        => $results,
				),
			) );

		case 'get_page_style_context':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/elementor/style-context', 'GET', null, array( 'post_id' => (int) $arguments['post_id'] ) ) );

		case 'draft_update_post_content':
			foreach ( array( 'post_id', 'content' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/post-content', 'POST', $arguments ) );

		case 'draft_patch_post_content':
			foreach ( array( 'post_id', 'patches' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/post-content-patch', 'POST', $arguments ) );

		case 'list_divi_modules':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/divi/modules', 'GET', null, array( 'post_id' => (int) $arguments['post_id'] ) ) );

		case 'find_asset_references':
			if ( ! isset( $arguments['url'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: url' ) ),
					'isError' => true,
				);
			}
			$asset_query = array( 'url' => (string) $arguments['url'] );
			if ( isset( $arguments['limit'] ) ) {
				$asset_query['limit'] = (int) $arguments['limit'];
			}
			return format_tool_result( wp_rest_call( '/assets/references', 'GET', null, $asset_query ) );

		case 'replace_asset_reference':
			foreach ( array( 'old_url', 'new_url' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/assets/replace', 'POST', $arguments ) );

		case 'upload_media':
			foreach ( array( 'filename', 'content_base64' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) || '' === $arguments[ $req ] ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/assets/upload', 'POST', $arguments ) );

		case 'delete_media':
			$delete_ids = isset( $arguments['ids'] ) ? $arguments['ids'] : ( isset( $arguments['id'] ) ? $arguments['id'] : null );
			if ( null === $delete_ids || ( is_array( $delete_ids ) && empty( $delete_ids ) ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ids (one attachment ID, or a list).' ) ),
					'isError' => true,
				);
			}
			return format_tool_result(
				wp_rest_call(
					'/assets/delete',
					'POST',
					array(
						'ids'           => array_values( array_map( 'intval', (array) $delete_ids ) ),
						'confirm'       => ! empty( $arguments['confirm'] ),
						'allow_foreign' => ! empty( $arguments['allow_foreign'] ),
					)
				)
			);

		case 'media_audit':
			$audit_query = array();
			foreach ( array( 'min_kb', 'limit' ) as $opt ) {
				if ( isset( $arguments[ $opt ] ) ) {
					$audit_query[ $opt ] = (int) $arguments[ $opt ];
				}
			}
			return format_tool_result( wp_rest_call( '/assets/audit', 'GET', null, $audit_query ) );

		case 'get_divi_module':
			foreach ( array( 'post_id', 'index' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/divi/module', 'GET', null, array( 'post_id' => (int) $arguments['post_id'], 'index' => (int) $arguments['index'] ) ) );

		case 'draft_update_divi_modules':
			foreach ( array( 'post_id', 'edits' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/divi/update-modules', 'POST', $arguments ) );

		case 'draft_remove_divi_module':
			foreach ( array( 'post_id', 'index', 'type' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/divi/remove-module', 'POST', $arguments ) );

        case 'inspect_plugin_capability':
            return format_tool_result( wp_rest_call( '/stack/capability', 'POST', $arguments ) );
        case 'verify_content_workflow':
            return format_tool_result( wp_rest_call( '/content/workflow/verify', 'POST', $arguments ) );
		case 'draft_create_post':
			foreach ( array( 'title', 'content' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/create-post', 'POST', $arguments ) );

		case 'polylang_link_translations':
			if ( ! isset( $arguments['pairs'] ) || ! is_array( $arguments['pairs'] ) || empty( $arguments['pairs'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: pairs (non-empty array of {lang_slug: post_id} objects)' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/polylang/link-translations', 'POST', $arguments ) );

		case 'draft_update_categories':
			foreach ( array( 'post_id', 'category_ids' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/categories', 'POST', $arguments ) );

		case 'create_category':
			if ( ! isset( $arguments['name'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: name' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/category/create', 'POST', $arguments ) );

		case 'list_categories':
			return format_tool_result( wp_rest_call( '/category/list', 'GET' ) );

		case 'list_product_categories':
			$params = array();
			if ( ! empty( $arguments['taxonomy'] ) ) {
				$params['taxonomy'] = (string) $arguments['taxonomy'];
			}
			if ( ! empty( $arguments['full'] ) ) {
				$params['full'] = 'true';
			}
			return format_tool_result( wp_rest_call( '/terms/list', 'GET', null, $params ) );

		// v0.65 stack introspection — read-only.
		case 'list_installed_plugins':
			$params = array();
			if ( isset( $arguments['include_inactive'] ) ) {
				$params['include_inactive'] = $arguments['include_inactive'] ? 'true' : 'false';
			}
			return format_tool_result( wp_rest_call( '/stack/plugins', 'GET', null, $params ) );

		case 'stack_admin_menu':
			return format_tool_result( wp_rest_call( '/stack/admin-menu', 'GET' ) );

		case 'get_plugin_settings':
			if ( empty( $arguments['slug'] ) ) {
				return format_tool_result( array( 'error' => 'slug is required. Get it from list_installed_plugins.' ) );
			}
			return format_tool_result(
				wp_rest_call( '/stack/settings', 'GET', null, array( 'slug' => (string) $arguments['slug'] ) )
			);

		case 'get_rank_math_schema':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/schema/rank-math', 'GET', null, array( 'post_id' => (int) $arguments['post_id'] ) ) );

		case 'draft_update_rank_math_schema':
			if ( ! isset( $arguments['post_id'] ) || ! isset( $arguments['set'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required arguments: post_id and set (object of dot-path => value)' ) ),
					'isError' => true,
				);
			}
			// Send set{} as JSON. WP_REST_Request only rebuilds nested objects
			// from a JSON body when the schema declares them, and this one is
			// free-form, so the handler json_decodes a string form too.
			if ( is_array( $arguments['set'] ) || is_object( $arguments['set'] ) ) {
				$arguments['set'] = json_encode( $arguments['set'] );
			}
			return format_tool_result( wp_rest_call( '/draft/rank-math-schema', 'POST', $arguments ) );

		case 'draft_update_plugin_setting':
			foreach ( array( 'option_name', 'value' ) as $req_key ) {
				if ( ! array_key_exists( $req_key, $arguments ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req_key ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/plugin-setting', 'POST', $arguments ) );

		case 'get_kit_settings':
			return format_tool_result( wp_rest_call( '/kit-settings', 'GET' ) );

		case 'draft_update_kit_setting':
			foreach ( array( 'path', 'value' ) as $req_key ) {
				if ( ! array_key_exists( $req_key, $arguments ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req_key ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/kit-setting', 'POST', $arguments ) );

		case 'draft_bulk_assign_term':
			foreach ( array( 'taxonomy', 'term_id' ) as $req_key ) {
				if ( ! isset( $arguments[ $req_key ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req_key ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/bulk-terms', 'POST', $arguments ) );

		case 'draft_update_term':
			if ( ! isset( $arguments['term_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: term_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/draft/term', 'POST', $arguments ) );

		case 'delete_category':
			if ( ! isset( $arguments['term_ids'] ) || ! is_array( $arguments['term_ids'] ) || empty( $arguments['term_ids'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: term_ids (non-empty array of integers)' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/category/delete', 'POST', $arguments ) );

		case 'audit_post_links':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( isset( $arguments['check_external'] ) ) {
				$params['check_external'] = $arguments['check_external'] ? 'true' : 'false';
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'] . '/link-audit', 'GET', null, $params ) );

		case 'pre_publish_check':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['id'] . '/pre-publish-check' ) );

		case 'get_style_guide':
			return format_tool_result( wp_rest_call( '/style-guide' ) );

		case 'get_site_memory':
			return format_tool_result( wp_rest_call( '/site-memory' ) );

		case 'update_site_memory_notes':
			if ( ! isset( $arguments['notes'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: notes' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/site-memory/notes', 'POST', $arguments ) );

		case 'draft_update_seo_meta':
			foreach ( array( 'post_id', 'logical_key', 'value' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/seo-meta', 'POST', $arguments ) );

		case 'gsc_inspect_url':
			$params = array();
			foreach ( array( 'url', 'post_id', 'force_refresh' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = is_bool( $arguments[ $key ] ) ? ( $arguments[ $key ] ? 'true' : 'false' ) : $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/inspect-url', 'GET', null, $params ) );

		case 'find_duplicate_content':
			if ( ! isset( $arguments['post_ids'] ) || ! is_array( $arguments['post_ids'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_ids (array of integers)' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/similarity/find-duplicates', 'POST', $arguments ) );

		case 'draft_create_redirect':
			// Only source is required here; destination is required for 3xx
			// codes ONLY, which the REST endpoint enforces. 410/451 "gone"
			// statuses queue with source alone.
			if ( ! isset( $arguments['source'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: source' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/draft/redirect', 'POST', $arguments ) );

		case 'draft_delete_redirect':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/draft/redirect/delete', 'POST', $arguments ) );

		case 'draft_untrash_redirect':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/draft/redirect/untrash', 'POST', $arguments ) );

		case 'get_working_state':
			return format_tool_result( wp_rest_call( '/working-state', 'GET' ) );

		case 'update_working_state':
			return format_tool_result( wp_rest_call( '/working-state', 'POST', $arguments ) );

		case 'gbp_locations':
			return format_tool_result( wp_rest_call( '/gbp/locations' . ( ! empty( $arguments['refresh'] ) ? '?refresh=1' : '' ), 'GET' ) );

		case 'gbp_performance':
			if ( ! isset( $arguments['location'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: location (e.g. "locations/123" from gbp_locations)' ) ),
					'isError' => true,
				);
			}
			$gbp_q = '?location=' . rawurlencode( (string) $arguments['location'] );
			if ( isset( $arguments['days'] ) ) {
				$gbp_q .= '&days=' . (int) $arguments['days'];
			}
			return format_tool_result( wp_rest_call( '/gbp/performance' . $gbp_q, 'GET' ) );

		case 'page_quality_gate':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/quality-gate', 'GET' ) );

		case 'win_audit':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/win-audit', 'POST', $arguments ), true );

		case 'page_robustness_audit':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			$rob_params = array();
			if ( isset( $arguments['strict'] ) ) {
				$rob_params['strict'] = $arguments['strict'] ? '1' : '0';
			}
			return format_tool_result( wp_rest_call( '/posts/' . (int) $arguments['post_id'] . '/robustness-audit', 'GET', null, $rob_params ) );

		case 'build_page_from_spec':
			foreach ( array( 'title', 'style_mirror_post_id', 'sections' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/build-from-spec', 'POST', $arguments ) );

		case 'propose_emergency_service_schema':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/draft/emergency-service-schema', 'POST', $arguments ) );

		case 'review_health':
			$rh_params = array();
			if ( ! empty( $arguments['refresh'] ) ) {
				$rh_params['refresh'] = 1;
			}
			return format_tool_result( wp_rest_call( '/reviews', 'GET', null, $rh_params ), true );

		case 'gsc_status':
			return format_tool_result( wp_rest_call( '/gsc/status' ) );

		case 'gsc_opportunities':
			$params = array();
			foreach ( array( 'min_position', 'max_position', 'min_impressions', 'limit', 'days' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/opportunities', 'GET', null, $params ) );

		case 'gsc_low_ctr':
			$params = array();
			foreach ( array( 'min_impressions', 'max_ctr', 'limit', 'days' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/low-ctr', 'GET', null, $params ) );

		case 'gsc_missing_mentions':
			$params = array();
			foreach ( array( 'min_impressions', 'limit', 'days' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/missing-mentions', 'GET', null, $params ) );

		case 'gsc_page_queries':
			if ( empty( $arguments['post_id'] ) && empty( $arguments['page'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Provide post_id or page.' ) ),
					'isError' => true,
				);
			}
			$params = array();
			foreach ( array( 'post_id', 'page', 'limit', 'days' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/page-queries', 'GET', null, $params ) );

		case 'gsc_trends':
			$params = array();
			foreach ( array( 'window_days', 'lens', 'limit' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/trends', 'GET', null, $params ) );

		case 'list_recent_edits':
			$params = array();
			if ( isset( $arguments['limit'] ) ) {
				$params['limit'] = (int) $arguments['limit'];
			}
			return format_tool_result( wp_rest_call( '/edits', 'GET', null, $params ) );

		case 'get_edit_outcome':
			if ( ! isset( $arguments['edit_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: edit_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/edits/' . (int) $arguments['edit_id'] . '/outcome' ) );

		case 'gsc_anomalies':
			$params = array();
			foreach ( array( 'window_days', 'baseline_days', 'direction', 'limit' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/anomalies', 'GET', null, $params ) );

		case 'gsc_ai_overview':
			$params = array();
			foreach ( array( 'days', 'limit' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/ai-overview', 'GET', null, $params ) );

		case 'aio_ctr_drop_alert':
			$params = array();
			foreach ( array( 'min_impressions', 'max_position', 'max_ratio', 'limit', 'days' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/aio-ctr-drop', 'GET', null, $params ) );

		case 'gsc_intent_breakdown':
			$params = array();
			foreach ( array( 'days', 'post_id' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/gsc/intent-breakdown', 'GET', null, $params ) );

		case 'llm_crawls':
			$params = array();
			if ( isset( $arguments['days'] ) ) {
				$params['days'] = (int) $arguments['days'];
			}
			return format_tool_result( wp_rest_call( '/llm-crawls', 'GET', null, $params ) );

		case 'links_summary':
			return format_tool_result( wp_rest_call( '/links/summary' ) );

		case 'links_orphans':
			$params = array();
			if ( isset( $arguments['limit'] ) ) {
				$params['limit'] = (int) $arguments['limit'];
			}
			if ( isset( $arguments['include_pending'] ) ) {
				$params['include_pending'] = $arguments['include_pending'] ? 'true' : 'false';
			}
			return format_tool_result( wp_rest_call( '/links/orphans', 'GET', null, $params ) );

		case 'content_audits':
			$params = array();
			if ( isset( $arguments['limit'] ) ) {
				$params['limit'] = (int) $arguments['limit'];
			}
			return format_tool_result( wp_rest_call( '/audits', 'GET', null, $params ) );

		case 'connection_test':
			return format_tool_result( wp_rest_call( '/connection/test' ) );

		case 'links_audit_post':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/links/audit/' . (int) $arguments['post_id'] ) );

		case 'links_rebuild':
			return format_tool_result( wp_rest_call( '/links/rebuild', 'POST', new stdClass() ) );

		case 'topical_authority':
			$params = array();
			foreach ( array( 'post_type', 'threshold', 'limit', 'days' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/topical-authority', 'GET', null, $params ) );

		case 'list_topic_clusters':
			return format_tool_result( wp_rest_call( '/topic-clusters' ) );

		case 'get_topic_cluster':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/topic-clusters/' . (int) $arguments['id'] ) );

		case 'propose_topic_cluster':
			if ( ! isset( $arguments['name'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: name' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/topic-clusters/propose', 'POST', $arguments ) );

		case 'propose_cluster_assignment':
			foreach ( array( 'cluster_id', 'post_id' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			$cid     = (int) $arguments['cluster_id'];
			$payload = $arguments;
			unset( $payload['cluster_id'] );
			return format_tool_result( wp_rest_call( '/topic-clusters/' . $cid . '/members/propose', 'POST', $payload ) );

		case 'bulk_propose_cluster_assignments':
			return format_tool_result( wp_rest_call( '/topic-clusters/bulk-suggest', 'POST', $arguments ) );

		case 'gsc_cannibalization':
			$params = array();
			foreach ( array( 'days', 'min_impressions', 'max_position', 'limit' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/seo/cannibalization', 'GET', null, $params ) );

		case 'audit_post_images':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/image-audit/' . (int) $arguments['id'] ) );

		case 'refresh_queue':
			$params = array();
			foreach ( array( 'days', 'min_click_drop', 'min_age_days', 'limit' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/seo/refresh-queue', 'GET', null, $params ) );

		case 'click_depth_audit':
			$params = array();
			foreach ( array( 'max_depth_warn', 'buried_only' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = is_bool( $arguments[ $key ] ) ? ( $arguments[ $key ] ? 'true' : 'false' ) : $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/seo/click-depth', 'GET', null, $params ) );

		case 'post_dossier':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/dossier/' . (int) $arguments['id'] ) );

		case 'analyze_post_structure':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/structure/' . (int) $arguments['id'] ) );

		case 'competitor_brief':
			if ( ! isset( $arguments['keyword'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: keyword' ) ),
					'isError' => true,
				);
			}
			$params = array( 'keyword' => $arguments['keyword'] );
			if ( isset( $arguments['days'] ) ) {
				$params['days'] = $arguments['days'];
			}
			return format_tool_result( wp_rest_call( '/seo/competitor-brief', 'GET', null, $params ) );

		case 'weekly_priorities':
			$params = array();
			if ( isset( $arguments['force'] ) ) {
				$params['force'] = $arguments['force'] ? 'true' : 'false';
			}
			return format_tool_result( wp_rest_call( '/advisor/priorities', 'GET', null, $params ) );

		case 'discover_content_scope':
            return format_tool_result( wp_rest_call( '/content/scope/discover', 'GET', null, $arguments ) );
        case 'manage_content_scope':
            return format_tool_result( wp_rest_call( '/content/scope/manage', 'POST', $arguments ) );
        case 'content_workflow':
            return format_tool_result( wp_rest_call( '/content/workflow', 'POST', $arguments ) );
        case 'get_content_scope':
            return format_tool_result( wp_rest_call( '/content/scope' ) );
        case 'plan_blog_content':
            return format_tool_result( wp_rest_call( '/content/plan', 'POST', $arguments ) );
        case 'content_research':
            return format_tool_result( wp_rest_call( '/content/research', 'POST', $arguments ) );
        case 'content_decision':
            return format_tool_result( wp_rest_call( '/content/decision', 'POST', $arguments ) );
        case 'content_decision_history':
            return format_tool_result( wp_rest_call( '/content/history', 'GET', null, $arguments ) );

		case 'brief_for_keyword':
			if ( ! isset( $arguments['keyword'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: keyword' ) ),
					'isError' => true,
				);
			}
			$params = array( 'keyword' => $arguments['keyword'] );
			foreach ( array( 'intent_hint', 'days' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/advisor/brief', 'GET', null, $params ) );

		case 'prepare_rewrite_brief':
			if ( ! isset( $arguments['post_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: post_id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( isset( $arguments['keyword'] ) ) {
				$params['keyword'] = $arguments['keyword'];
			}
			return format_tool_result( wp_rest_call( '/seo/rewrite-brief/' . (int) $arguments['post_id'], 'GET', null, $params ) );

		case 'verify_change':
			if ( ! isset( $arguments['pending_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: pending_id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/pending/' . (int) $arguments['pending_id'] . '/verify' ) );

		case 'propose_rewrite_outline':
			foreach ( array( 'post_id', 'outline' ) as $req ) {
				if ( ! isset( $arguments[ $req ] ) ) {
					return array(
						'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: ' . $req ) ),
						'isError' => true,
					);
				}
			}
			return format_tool_result( wp_rest_call( '/draft/outline', 'POST', $arguments ) );

		case 'cluster_gsc':
			if ( ! isset( $arguments['cluster_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: cluster_id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( isset( $arguments['days'] ) ) {
				$params['days'] = (int) $arguments['days'];
			}
			return format_tool_result( wp_rest_call( '/topic-clusters/' . (int) $arguments['cluster_id'] . '/gsc', 'GET', null, $params ) );

		case 'accessibility_audit':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/accessibility/' . (int) $arguments['id'] ) );

		case 'canonical_audit':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/canonical-audit/' . (int) $arguments['id'] ) );

		case 'hreflang_audit':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/hreflang-audit/' . (int) $arguments['id'] ) );

		case 'eeat_coverage_audit':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/eeat-coverage/' . (int) $arguments['id'] ) );

		case 'schema_parity_check':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/schema-parity/' . (int) $arguments['id'] ) );

		case 'self_review_detection':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/self-review/' . (int) $arguments['id'] ) );

		case 'helpful_content_score':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( isset( $arguments['refresh'] ) ) {
				$params['refresh'] = $arguments['refresh'] ? '1' : '0';
			}
			return format_tool_result( wp_rest_call( '/seo/helpful-content/' . (int) $arguments['id'], 'GET', null, $params ), true );

		case 'site_quality_score':
			$params = array();
			foreach ( array( 'refresh_stale', 'freshness_days', 'bottom_n', 'rescore_cap' ) as $key ) {
				if ( isset( $arguments[ $key ] ) ) {
					$params[ $key ] = $arguments[ $key ];
				}
			}
			return format_tool_result( wp_rest_call( '/seo/site-quality', 'GET', null, $params ), true );

		case 'external_originality_check':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( isset( $arguments['keyword'] ) ) {
				$params['keyword'] = $arguments['keyword'];
			}
			return format_tool_result( wp_rest_call( '/seo/originality/' . (int) $arguments['id'], 'GET', null, $params ) );

		case 'keyword_research':
			if ( ! isset( $arguments['seed'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: seed' ) ),
					'isError' => true,
				);
			}
			$params = array( 'seed' => $arguments['seed'] );
			if ( isset( $arguments['days'] ) ) {
				$params['days'] = (int) $arguments['days'];
			}
			return format_tool_result( wp_rest_call( '/seo/keyword-research', 'GET', null, $params ) );

		case 'generate_schema':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/seo/schema/' . (int) $arguments['id'] ) );

		case 'propose_schema':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$body = array();
			if ( isset( $arguments['reasoning'] ) ) {
				$body['reasoning'] = $arguments['reasoning'];
			}
			// dry_run was previously dropped here, so the endpoint queued a
			// pending row even when the caller asked for a preview only.
			if ( isset( $arguments['dry_run'] ) ) {
				$body['dry_run'] = (bool) $arguments['dry_run'];
			}
			if ( isset( $arguments['include_breadcrumb'] ) ) {
				$body['include_breadcrumb'] = (bool) $arguments['include_breadcrumb'];
			}
			if ( isset( $arguments['success_metrics'] ) && is_array( $arguments['success_metrics'] ) ) {
				$body['success_metrics'] = $arguments['success_metrics'];
			}
			return format_tool_result( wp_rest_call( '/seo/schema/' . (int) $arguments['id'] . '/propose', 'POST', $body ) );

		case 'gsc_warehouse_sync':
			return format_tool_result( cc_wh_tool_sync( is_array( $arguments ) ? $arguments : array() ) );

		case 'gsc_warehouse_query':
			return format_tool_result( cc_wh_tool_query( is_array( $arguments ) ? $arguments : array() ) );

		case 'gsc_warehouse_status':
			return format_tool_result( cc_wh_tool_status() );

		case 'outcome_report':
			return format_tool_result( cc_wh_tool_outcome_report( is_array( $arguments ) ? $arguments : array() ) );

		case 'verified_page_audit':
			return format_tool_result( wp_rest_call( '/verified-audit/' . (int) ( $arguments['post_id'] ?? 0 ), 'GET' ) );
		case 'page_facts':
			return format_tool_result( wp_rest_call( '/facts/' . (int) ( $arguments['post_id'] ?? 0 ), 'GET', null, array( 'refresh' => ( ! array_key_exists( 'refresh', $arguments ) || ! empty( $arguments['refresh'] ) ) ? '1' : '0' ) ) );
		case 'keyword_targets':
			return format_tool_result( cc_wh_tool_keyword_targets( is_array( $arguments ) ? $arguments : array() ) );
		case 'wcag_sweep':
			return format_tool_result( cc_tool_wcag_sweep( is_array( $arguments ) ? $arguments : array() ) );
		case 'site_status':
			return format_tool_result( cc_wh_tool_site_status( is_array( $arguments ) ? $arguments : array() ) );
		case 'commodity_audit':
			return format_tool_result( cc_wh_tool_commodity_audit( is_array( $arguments ) ? $arguments : array() ) );

		case 'aeo_snapshot':
			return format_tool_result( cc_wh_tool_aeo_snapshot( is_array( $arguments ) ? $arguments : array() ) );

		case 'propose_revert':
			if ( ! isset( $arguments['edit_id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: edit_id' ) ),
					'isError' => true,
				);
			}
			$body = array( 'edit_id' => (int) $arguments['edit_id'] );
			if ( ! empty( $arguments['force'] ) ) {
				$body['force'] = true;
			}
			if ( ! empty( $arguments['reasoning'] ) ) {
				$body['reasoning'] = (string) $arguments['reasoning'];
			}
			return format_tool_result( wp_rest_call( '/revert/propose', 'POST', $body ) );

		case 'lead_events':
			$params = array();
			if ( isset( $arguments['days'] ) ) {
				$params['days'] = (int) $arguments['days'];
			}
			return format_tool_result( wp_rest_call( '/leads', 'GET', null, $params ) );

		case 'operator_kit':
			return format_tool_result( wp_rest_call( '/operator-kit' ) );

		case 'operator_kit_push_skills':
			$body = array();
			foreach ( array( 'global', 'site' ) as $key ) {
				if ( isset( $arguments[ $key ] ) && is_string( $arguments[ $key ] ) && '' !== $arguments[ $key ] ) {
					$body[ $key ] = $arguments[ $key ];
				}
			}
			if ( empty( $body ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Pass global and/or site skill text.' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( wp_rest_call( '/operator-kit/skills', 'POST', $body ) );

		case 'operator_brain_status':
			return format_tool_result( cc_brain_tool_status( 'cc_brain_rest' ) );

		case 'operator_brain_push':
			if ( array_key_exists( 'paths', $arguments ) && ! is_array( $arguments['paths'] ) ) {
				return format_tool_result( array( 'error' => 'scope_paths_required', 'message' => 'paths must be a nonempty array of exact local paths. Invalid selected scope never falls back to a full upload.' ) );
			}
			return format_tool_result( cc_brain_tool_push( 'cc_brain_rest', null, null, 900000, $arguments['paths'] ?? null ) );

		case 'operator_brain_pull':
			$what = isset( $arguments['what'] ) ? (string) $arguments['what'] : 'all';
			$mode = isset( $arguments['mode'] ) ? (string) $arguments['mode'] : 'missing_only';
			if ( ! in_array( $what, array( 'all', 'memory', 'skills', 'project', 'bridge' ), true ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'what must be one of all|memory|skills|project|bridge' ) ),
					'isError' => true,
				);
			}
			return format_tool_result( cc_brain_tool_pull( 'cc_brain_rest', $what, $mode ) );

		case 'widget_schema':
			$endpoint = empty( $arguments['widget_type'] )
				? '/widget-schema'
				: '/widget-schema/' . rawurlencode( (string) $arguments['widget_type'] );
			$params = array();
			if ( ! empty( $arguments['section'] ) ) {
				$params['section'] = (string) $arguments['section'];
			}
			// v0.80: stored vs effective values for one saved element.
			if ( ! empty( $arguments['post_id'] ) && ! empty( $arguments['widget_id'] ) ) {
				$params['post_id']   = (int) $arguments['post_id'];
				$params['widget_id'] = (string) $arguments['widget_id'];
			}
			return format_tool_result( wp_rest_call( $endpoint, 'GET', null, $params ) );

		case 'attention_spec':
			$params = array();
			if ( isset( $arguments['post_id'] ) ) {
				$params['post_id'] = (int) $arguments['post_id'];
			}
			if ( ! empty( $arguments['archetype'] ) ) {
				$params['archetype'] = (string) $arguments['archetype'];
			}
			return format_tool_result( wp_rest_call( '/attention/spec', 'GET', null, $params ) );

		case 'attention_audit':
			if ( ! isset( $arguments['id'] ) ) {
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'Missing required argument: id' ) ),
					'isError' => true,
				);
			}
			$params = array();
			if ( ! empty( $arguments['archetype'] ) ) {
				$params['archetype'] = (string) $arguments['archetype'];
			}
			return format_tool_result( wp_rest_call( '/attention/audit/' . (int) $arguments['id'], 'GET', null, $params ) );

		default:
			return array(
				'content' => array( array( 'type' => 'text', 'text' => 'Unknown tool "' . $tool_name . '" in local bridge v' . CC_MCP_VERSION . '. If whoami.version_drift says the site runs a NEWER plugin, this laptop\'s bridge is stale and the tool exists there: run operator_brain_pull(what="bridge") and restart the MCP server.' ) ),
				'isError' => true,
			);
	}
}

/** REST caller in the shape bin/operator-brain.php expects (unwrapped body). */
function cc_brain_rest( $endpoint, $method = 'GET', $body = null, $params = array() ) {
	return cc_unwrap_envelope( wp_rest_call( $endpoint, $method, $body, $params ) );
}

function send_response( $id, $result = null, $error = null ) {
	$response = array(
		'jsonrpc' => '2.0',
		'id'      => $id,
	);
	if ( $error !== null ) {
		$response['error'] = $error;
	} else {
		$response['result'] = $result;
	}
	echo json_encode( $response ) . "\n";
	if ( function_exists( 'fflush' ) ) {
		fflush( STDOUT );
	}
}

function handle_request( $req ) {
	global $TOOLS;

	$method = isset( $req['method'] ) ? $req['method'] : '';
	$id     = isset( $req['id'] ) ? $req['id'] : null;
	$params = isset( $req['params'] ) ? $req['params'] : array();

	switch ( $method ) {
		case 'initialize':
			send_response(
				$id,
				array(
					'protocolVersion' => '2024-11-05',
					'capabilities'    => array( 'tools' => new stdClass() ),
					'serverInfo'      => array(
						'name'    => 'cc-assistant',
						// Keep in sync with CC_ASSISTANT_VERSION in cc-assistant.php
						// (the MCP server is a standalone CLI process and can't read
						// the plugin constant). Was stuck at 0.1.0 since the scaffold.
						'version' => CC_MCP_VERSION,
					),
				)
			);
			break;

		case 'notifications/initialized':
			break;

		case 'tools/list':
			send_response( $id, array( 'tools' => $TOOLS ) );
			break;

		case 'tools/call':
			$tool_name = isset( $params['name'] ) ? $params['name'] : '';
			$arguments = isset( $params['arguments'] ) ? $params['arguments'] : array();
			$result    = handle_tool_call( $tool_name, $arguments );
			send_response( $id, $result );
			break;

		case 'ping':
			send_response( $id, new stdClass() );
			break;

		default:
			if ( $id !== null ) {
				send_response(
					$id,
					null,
					array(
						'code'    => -32601,
						'message' => 'Method not found: ' . $method,
					)
				);
			}
	}
}

while ( ( $line = fgets( STDIN ) ) !== false ) {
	$line = trim( $line );
	if ( empty( $line ) ) {
		continue;
	}

	$req = json_decode( $line, true );
	if ( $req === null ) {
		fwrite( STDERR, "CC Assistant MCP: invalid JSON: $line\n" );
		continue;
	}

	try {
		handle_request( $req );
	} catch ( Throwable $e ) {
		$id = isset( $req['id'] ) ? $req['id'] : null;
		if ( $id !== null ) {
			send_response(
				$id,
				null,
				array(
					'code'    => -32603,
					'message' => $e->getMessage(),
				)
			);
		}
	}
}

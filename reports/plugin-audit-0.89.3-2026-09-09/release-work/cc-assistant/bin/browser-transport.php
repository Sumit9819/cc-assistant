<?php
/** Optional persistent Node worker; no shell interpolation or credentials in argv. */
function cc_mcp_browser_call( $url, $method, $body ) {
	global $WP_URL, $WP_USER, $WP_PASSWORD;
	static $proc = null, $pipes = null;
	if ( ! function_exists( 'proc_open' ) ) { return array( 'error' => 'browser_unavailable', 'message' => 'PHP proc_open is required for browser transport.' ); }
	if ( ! is_resource( $proc ) ) {
		$node = getenv( 'CC_NODE_BINARY' ) ?: 'node';
		$proc = @proc_open( array( $node, __DIR__ . '/browser-transport.mjs' ),
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => STDERR ), $pipes, __DIR__ );
		if ( ! is_resource( $proc ) ) { return array( 'error' => 'browser_unavailable', 'message' => 'Could not start Node. Install Node or set CC_NODE_BINARY.' ); }
		register_shutdown_function( static function () use ( &$proc, &$pipes ) {
			if ( is_array( $pipes ) ) {
				// EOF lets the worker close its own browser/profile cleanly.
				if ( is_resource( $pipes[0] ) ) { fclose( $pipes[0] ); }
				if ( is_resource( $pipes[1] ) ) { fclose( $pipes[1] ); }
			}
			if ( is_resource( $proc ) ) { proc_close( $proc ); }
		} );
	}
	$json = json_encode( array( 'site' => rtrim( $WP_URL, '/' ), 'url' => $url, 'method' => $method, 'body' => $body, 'username' => $WP_USER, 'password' => $WP_PASSWORD ) ) . "\n";
	for ( $offset = 0, $length = strlen( $json ); $offset < $length; $offset += $written ) {
		$written = @fwrite( $pipes[0], substr( $json, $offset ) );
		if ( ! $written ) { return array( 'error' => 'browser_worker_closed', 'message' => 'Restart MCP. The browser worker closed; no automatic replay was attempted.' ); }
	}
	fflush( $pipes[0] );
	$line = fgets( $pipes[1] );
	$data = is_string( $line ) ? json_decode( $line, true ) : null;
	return is_array( $data ) ? $data : array( 'error' => 'browser_worker_closed', 'message' => 'The browser worker stopped. Inspect the inbox before retrying a write, then restart MCP.' );
}

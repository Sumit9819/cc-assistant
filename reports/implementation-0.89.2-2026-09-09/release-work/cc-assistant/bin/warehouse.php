<?php
/**
 * CC Assistant desktop GSC warehouse (v0.54).
 *
 * Full-fidelity (date, page, query) Search Console archive in SQLite on the
 * operator's machine. The WP site is only a pass-through proxy to the
 * searchAnalytics API (/gsc-export route) — nothing warehouse-related is
 * stored in the site database, so the 250MB cap and impression-floor pruning
 * no longer apply. One .sqlite file per site under ~/.cc-assistant/warehouse/.
 *
 * Loaded by bin/mcp-server.php. Requires the sqlite3 PHP extension
 * (add "-d", "extension=sqlite3" to the server args in .mcp.json).
 * Functions are prefixed cc_wh_ and return plain arrays; an 'error' key
 * marks failure (format_tool_result() turns that into an MCP error).
 */

/** How many days behind "today" GSC data is considered final. */
const CC_WH_FINALIZE_LAG_DAYS = 2;

/** GSC only retains ~16 months of search analytics. */
const CC_WH_MAX_BACKFILL_DAYS = 485;

/** Hard cap on pages fetched per date (24 x 25k = 600k rows/date). */
const CC_WH_MAX_PAGES_PER_DATE = 24;

/**
 * v0.59: strip the legacy {site, data} REST envelope before a payload is
 * consumed. The site block is identical boilerplate on EVERY legacy tool
 * response (wasted tokens), and the envelope's presence-or-absence caused
 * three shipped bugs — normalizing in one place means bridge code and the
 * model always see ONE shape. Lives here (not mcp-server.php) so warehouse
 * tools work standalone under the test harness.
 */
function cc_unwrap_envelope( $data ) {
	if ( is_array( $data ) && isset( $data['site'], $data['data'] ) && is_array( $data['data'] ) && 2 === count( $data ) ) {
		return $data['data'];
	}
	return $data;
}

function cc_wh_available() {
	if ( ! class_exists( 'SQLite3' ) ) {
		return array(
			'error'   => 'sqlite_missing',
			'message' => 'The sqlite3 PHP extension is not loaded. Add "-d", "extension=sqlite3" to this server\'s args in .mcp.json (next to extension=curl) and restart the MCP server.',
		);
	}
	return true;
}

function cc_wh_site_key() {
	$url  = (string) getenv( 'CC_WP_URL' );
	$host = parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) || '' === $host ) {
		$host = 'unknown-site';
	}
	$key = strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', $host ) );
	return trim( $key, '-' );
}

function cc_wh_dir() {
	$dir = getenv( 'CC_WAREHOUSE_DIR' );
	if ( ! is_string( $dir ) || '' === $dir ) {
		$home = getenv( 'USERPROFILE' );
		if ( ! is_string( $home ) || '' === $home ) {
			$home = getenv( 'HOME' );
		}
		if ( ! is_string( $home ) || '' === $home ) {
			$home = sys_get_temp_dir();
		}
		$dir = rtrim( $home, '/\\' ) . DIRECTORY_SEPARATOR . '.cc-assistant' . DIRECTORY_SEPARATOR . 'warehouse';
	}
	if ( ! is_dir( $dir ) ) {
		@mkdir( $dir, 0700, true );
	}
	return $dir;
}

function cc_wh_db_path() {
	return cc_wh_dir() . DIRECTORY_SEPARATOR . cc_wh_site_key() . '.sqlite';
}

/**
 * Open the warehouse DB. Pass $readonly=true for query access — the file
 * must already exist (no silent creation of an empty DB on a typo'd path).
 * Returns SQLite3 instance or an error array.
 */
function cc_wh_open( $readonly = false ) {
	$ok = cc_wh_available();
	if ( true !== $ok ) {
		return $ok;
	}
	$path = cc_wh_db_path();
	if ( $readonly && ! file_exists( $path ) ) {
		return array(
			'error'   => 'warehouse_empty',
			'message' => 'No warehouse database for this site yet (' . $path . '). Run gsc_warehouse_sync first.',
		);
	}
	try {
		$db = $readonly
			? new SQLite3( $path, SQLITE3_OPEN_READONLY )
			: new SQLite3( $path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE );
	} catch ( Exception $e ) {
		return array( 'error' => 'sqlite_open_failed', 'message' => $e->getMessage(), 'path' => $path );
	}
	// CRITICAL: without this, SQLite3 failures are PHP warnings + false
	// returns — the warnings would land on STDOUT (display_errors=STDOUT on
	// this CLI) corrupting the JSON-RPC stream, and method calls on a false
	// prepare() would raise an uncatchable-by-Exception \Error that kills the
	// whole MCP server. With it, every failure is a catchable SQLite3Exception.
	$db->enableExceptions( true );
	$db->busyTimeout( 5000 );
	if ( ! $readonly ) {
		try {
			$db->exec( 'PRAGMA synchronous = NORMAL' );
			cc_wh_init_schema( $db );
		} catch ( Throwable $e ) {
			return array(
				'error'   => 'sqlite_corrupt',
				'message' => 'Warehouse database is unreadable or corrupt (' . $path . '): ' . $e->getMessage() . ' — move or delete the file and re-run gsc_warehouse_sync (GSC re-backfills ~16 months).',
			);
		}
	}
	return $db;
}

function cc_wh_init_schema( $db ) {
	$db->exec(
		'CREATE TABLE IF NOT EXISTS gsc_daily (
			date        TEXT NOT NULL,
			page        TEXT NOT NULL,
			query       TEXT NOT NULL,
			clicks      INTEGER NOT NULL DEFAULT 0,
			impressions INTEGER NOT NULL DEFAULT 0,
			position    REAL NOT NULL DEFAULT 0,
			PRIMARY KEY (date, page, query)
		) WITHOUT ROWID'
	);
	$db->exec( 'CREATE INDEX IF NOT EXISTS idx_wh_page_date ON gsc_daily (page, date)' );
	$db->exec( 'CREATE INDEX IF NOT EXISTS idx_wh_query_date ON gsc_daily (query, date)' );
	$db->exec(
		'CREATE TABLE IF NOT EXISTS sync_log (
			date       TEXT PRIMARY KEY,
			rows       INTEGER NOT NULL DEFAULT 0,
			fetched_at INTEGER NOT NULL DEFAULT 0,
			complete   INTEGER NOT NULL DEFAULT 1
		)'
	);
	$db->exec( 'CREATE TABLE IF NOT EXISTS meta ( key TEXT PRIMARY KEY, value TEXT )' );
}

function cc_wh_meta_get( $db, $key ) {
	// Meta is advisory (property-change tracking) — never let a failure here
	// take down a sync or status call on a damaged DB.
	try {
		$stmt = $db->prepare( 'SELECT value FROM meta WHERE key = :k' );
		$stmt->bindValue( ':k', $key, SQLITE3_TEXT );
		$res = $stmt->execute();
		$row = $res ? $res->fetchArray( SQLITE3_NUM ) : false;
		return $row ? (string) $row[0] : null;
	} catch ( Throwable $e ) {
		return null;
	}
}

function cc_wh_meta_set( $db, $key, $value ) {
	try {
		$stmt = $db->prepare( 'INSERT INTO meta (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value = excluded.value' );
		$stmt->bindValue( ':k', $key, SQLITE3_TEXT );
		$stmt->bindValue( ':v', (string) $value, SQLITE3_TEXT );
		$stmt->execute();
	} catch ( Throwable $e ) {
		// Advisory only — swallow.
	}
}

/** UTC date string N days before the given UTC date string. */
function cc_wh_date_sub( $date, $days ) {
	$dt = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
	return $dt->sub( new DateInterval( 'P' . (int) $days . 'D' ) )->format( 'Y-m-d' );
}

/** UTC date string N days after the given UTC date string. */
function cc_wh_date_add( $date, $days ) {
	$dt = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
	return $dt->add( new DateInterval( 'P' . (int) $days . 'D' ) )->format( 'Y-m-d' );
}

/** Days between two Y-m-d strings (b - a). */
function cc_wh_date_diff_days( $a, $b ) {
	$da = DateTimeImmutable::createFromFormat( '!Y-m-d', $a, new DateTimeZone( 'UTC' ) );
	$db = DateTimeImmutable::createFromFormat( '!Y-m-d', $b, new DateTimeZone( 'UTC' ) );
	$d  = $da->diff( $db );
	return (int) $d->format( '%r%a' );
}

/**
 * Fetch all pages for one date through the site proxy, streaming each page
 * straight into a single per-date transaction (delete + insert = idempotent
 * re-pull). Streaming keeps peak memory at ONE page (~25k rows) regardless
 * of how many pages the date has — the previous accumulate-then-insert
 * approach could exceed the CLI's 128M memory_limit around 250k rows.
 * Returns array{rows: int, truncated?: true} or an error array.
 */
function cc_wh_sync_one_date( $db, $date ) {
	$rows_stored = 0;
	$start       = 0;
	$pages       = 0;
	$next        = null;
	$property    = null;
	$complete    = 1;

	try {
		$db->exec( 'BEGIN IMMEDIATE' );
	} catch ( Throwable $e ) {
		return array( 'error' => 'sqlite_busy', 'message' => $date . ': could not lock the warehouse (another sync running?): ' . $e->getMessage() );
	}

	try {
		$del = $db->prepare( 'DELETE FROM gsc_daily WHERE date = :d' );
		$del->bindValue( ':d', $date, SQLITE3_TEXT );
		$del->execute();

		$ins = $db->prepare(
			'INSERT OR REPLACE INTO gsc_daily (date, page, query, clicks, impressions, position)
			 VALUES (:date, :page, :query, :clicks, :impressions, :position)'
		);

		do {
			$resp = wp_rest_call( '/gsc-export', 'GET', null, array( 'date' => $date, 'start_row' => $start ) );
			if ( isset( $resp['error'] ) ) {
				cc_wh_rollback_quietly( $db );
				return cc_wh_friendly_transport_error( $resp, '/gsc-export' );
			}
			if ( ! isset( $resp['rows'] ) || ! is_array( $resp['rows'] ) ) {
				cc_wh_rollback_quietly( $db );
				return array( 'error' => 'bad_export_response', 'message' => 'Unexpected /gsc-export response shape for ' . $date, 'body' => $resp );
			}

			foreach ( $resp['rows'] as $r ) {
				if ( ! is_array( $r ) || count( $r ) < 5 ) {
					continue;
				}
				$ins->bindValue( ':date', $date, SQLITE3_TEXT );
				$ins->bindValue( ':page', (string) $r[0], SQLITE3_TEXT );
				$ins->bindValue( ':query', (string) $r[1], SQLITE3_TEXT );
				$ins->bindValue( ':clicks', (int) $r[2], SQLITE3_INTEGER );
				$ins->bindValue( ':impressions', (int) $r[3], SQLITE3_INTEGER );
				$ins->bindValue( ':position', (float) $r[4], SQLITE3_FLOAT );
				$ins->execute();
				$ins->reset();
				$rows_stored++;
			}

			$property = isset( $resp['property'] ) ? (string) $resp['property'] : $property;
			$next     = isset( $resp['next_start_row'] ) ? $resp['next_start_row'] : null;
			$start    = is_int( $next ) ? $next : 0;
			$pages++;
		} while ( null !== $next && $pages < CC_WH_MAX_PAGES_PER_DATE );

		// Page-cap hit with data still remaining: keep what we have but record
		// the date as INCOMPLETE so it is never silently passed off as full —
		// tool_sync surfaces it and status counts it.
		$complete = ( null === $next ) ? 1 : 0;

		$log = $db->prepare( 'INSERT OR REPLACE INTO sync_log (date, rows, fetched_at, complete) VALUES (:d, :r, :t, :c)' );
		$log->bindValue( ':d', $date, SQLITE3_TEXT );
		$log->bindValue( ':r', $rows_stored, SQLITE3_INTEGER );
		$log->bindValue( ':t', time(), SQLITE3_INTEGER );
		$log->bindValue( ':c', $complete, SQLITE3_INTEGER );
		$log->execute();

		$db->exec( 'COMMIT' );
	} catch ( Throwable $e ) {
		// Throwable, not Exception: besides SQLite3Exception (enableExceptions),
		// a \Error here would otherwise leave the transaction open and kill the
		// whole MCP server process.
		cc_wh_rollback_quietly( $db );
		return array( 'error' => 'sqlite_write_failed', 'message' => $date . ': ' . $e->getMessage() );
	}

	if ( null !== $property ) {
		cc_wh_meta_set( $db, 'gsc_property', $property );
	}
	$out = array( 'rows' => $rows_stored );
	if ( ! $complete ) {
		$out['truncated'] = true;
	}
	return $out;
}

/** Roll back without letting a rollback failure mask the original error. */
function cc_wh_rollback_quietly( $db ) {
	try {
		$db->exec( 'ROLLBACK' );
	} catch ( Throwable $e ) {
		// Already out of the transaction (or the DB is gone) — nothing to do.
	}
}

/** Map transport-level errors from the site proxy to actionable messages. */
function cc_wh_friendly_transport_error( $resp, $endpoint = '' ) {
	$body = isset( $resp['body'] ) ? (string) $resp['body'] : '';
	if ( isset( $resp['status'] ) && 404 === (int) $resp['status'] && false !== strpos( $body, 'rest_no_route' ) ) {
		$route = '' !== $endpoint ? $endpoint : 'the required route';
		$ver   = defined( 'CC_MCP_VERSION' ) ? 'v' . CC_MCP_VERSION : 'the latest version';
		return array(
			'error'   => 'site_plugin_outdated',
			'message' => 'This site\'s cc-assistant does not have ' . $route . ' yet — deploy the ' . $ver . ' plugin zip to the site, then retry.',
		);
	}
	if ( false !== strpos( $body, 'gsc_not_connected' ) || false !== strpos( $body, 'gsc_no_property' ) ) {
		return array(
			'error'   => 'gsc_not_connected',
			'message' => 'Search Console is not connected (or no property selected) on this site — connect it in cc-assistant settings first.',
		);
	}
	return $resp;
}

/**
 * gsc_warehouse_sync tool. Arguments:
 *   max_dates    - date budget for this call (default 30, cap 120)
 *   refresh_days - trailing dates always re-pulled for late-arriving data (default 7, cap 30)
 *   backfill     - keep walking backward into history for missing dates (default true)
 */
function cc_wh_tool_sync( $arguments ) {
	$ok = cc_wh_available();
	if ( true !== $ok ) {
		return $ok;
	}

	// Preflight: confirms the site has the route + GSC connected before any writes.
	$status = wp_rest_call( '/gsc-export/status' );
	if ( isset( $status['error'] ) ) {
		return cc_wh_friendly_transport_error( $status, '/gsc-export/status' );
	}
	if ( empty( $status['connected'] ) ) {
		return array(
			'error'   => 'gsc_not_connected',
			'message' => 'Search Console is not connected on this site — connect it in cc-assistant settings first.',
		);
	}

	$max_dates    = isset( $arguments['max_dates'] ) ? max( 1, min( 120, (int) $arguments['max_dates'] ) ) : 30;
	$refresh_days = isset( $arguments['refresh_days'] ) ? max( 1, min( 30, (int) $arguments['refresh_days'] ) ) : 7;
	$backfill     = isset( $arguments['backfill'] ) ? (bool) $arguments['backfill'] : true;

	$db = cc_wh_open( false );
	if ( is_array( $db ) ) {
		return $db;
	}

	$end = isset( $status['freshest_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $status['freshest_date'] )
		? (string) $status['freshest_date']
		: gmdate( 'Y-m-d', time() - CC_WH_FINALIZE_LAG_DAYS * 86400 );
	$floor = cc_wh_date_sub( $end, CC_WH_MAX_BACKFILL_DAYS );

	// Dates we already have.
	$have = array();
	$res  = $db->query( 'SELECT date FROM sync_log' );
	while ( $res && ( $row = $res->fetchArray( SQLITE3_NUM ) ) ) {
		$have[ (string) $row[0] ] = true;
	}

	// Build the work queue, newest first: trailing refresh window (always
	// re-pulled — GSC restates recent days), then every missing date walking
	// back to the retention floor (gap-fill + backfill in one pass).
	$candidates = array();
	for ( $i = 0; $i < $refresh_days; $i++ ) {
		$candidates[] = cc_wh_date_sub( $end, $i );
	}
	if ( $backfill ) {
		$cursor = cc_wh_date_sub( $end, $refresh_days );
		while ( $cursor >= $floor ) {
			if ( ! isset( $have[ $cursor ] ) ) {
				$candidates[] = $cursor;
			}
			$cursor = cc_wh_date_sub( $cursor, 1 );
		}
	}
	$candidates = array_values( array_unique( $candidates ) );
	$queue      = array_slice( $candidates, 0, $max_dates );
	$remaining  = count( $candidates ) - count( $queue );

	$started       = time();
	$synced        = array();
	$truncated     = array();
	$rows_total    = 0;
	$error         = null;
	$prev_property = cc_wh_meta_get( $db, 'gsc_property' );

	foreach ( $queue as $date ) {
		$one = cc_wh_sync_one_date( $db, $date );
		if ( isset( $one['error'] ) ) {
			$error = $one;
			break;
		}
		$synced[]    = $date;
		$rows_total += (int) $one['rows'];
		if ( ! empty( $one['truncated'] ) ) {
			$truncated[] = $date;
		}
	}

	// Coverage snapshot after this run (best-effort — never mask a sync result).
	try {
		$cov = $db->querySingle( 'SELECT COUNT(*) AS dates, MIN(date) AS min_d, MAX(date) AS max_d FROM sync_log', true );
	} catch ( Throwable $e ) {
		$cov = array();
	}
	$new_property = cc_wh_meta_get( $db, 'gsc_property' );
	$db->close();

	$result = array(
		'site'            => cc_wh_site_key(),
		'db_path'         => cc_wh_db_path(),
		'synced_dates'    => count( $synced ),
		'rows_fetched'    => $rows_total,
		'seconds'         => time() - $started,
		'coverage'        => array(
			'dates'    => isset( $cov['dates'] ) ? (int) $cov['dates'] : 0,
			'earliest' => isset( $cov['min_d'] ) ? $cov['min_d'] : null,
			'latest'   => isset( $cov['max_d'] ) ? $cov['max_d'] : null,
		),
		'backfill_remaining_dates' => $remaining,
		'db_size_mb'      => file_exists( cc_wh_db_path() ) ? round( filesize( cc_wh_db_path() ) / 1048576, 1 ) : 0,
	);
	if ( $remaining > 0 && null === $error ) {
		$result['next'] = 'Backfill incomplete — call gsc_warehouse_sync again to continue (about ' . $remaining . ' dates left).';
	}
	if ( ! empty( $truncated ) ) {
		$result['truncated_dates'] = $truncated;
		$result['warning_truncated'] = 'These dates exceeded the ' . ( CC_WH_MAX_PAGES_PER_DATE * 25000 ) . '-row per-date cap and were stored PARTIALLY (marked complete=0 in sync_log).';
	}
	if ( null !== $prev_property && null !== $new_property && $prev_property !== $new_property ) {
		$result['warning'] = 'GSC property changed from ' . $prev_property . ' to ' . $new_property . ' — history before the switch is from the old property.';
	}
	if ( null !== $error ) {
		$result['error']        = $error['error'];
		$result['message']      = isset( $error['message'] ) ? $error['message'] : '';
		$result['synced_before_error'] = $synced;
	}
	return $result;
}

/**
 * gsc_warehouse_query tool: read-only SELECT/WITH against the local archive.
 * Arguments: sql (required), params (positional array), max_rows (default 200, cap 5000).
 */
function cc_wh_tool_query( $arguments ) {
	$sql = isset( $arguments['sql'] ) ? trim( (string) $arguments['sql'] ) : '';
	$sql = rtrim( $sql, "; \t\n\r" );
	if ( '' === $sql ) {
		return array( 'error' => 'missing_sql', 'message' => 'sql is required.' );
	}
	// Multi-statement guard: only reject a ';' OUTSIDE quoted strings, so a
	// legitimate literal like WHERE query LIKE '%;%' still works. ('' and ""
	// are SQL escape doubling; both quote styles stripped before the check.)
	$stripped = preg_replace( '/\'(?:[^\']|\'\')*\'|"(?:[^"]|"")*"/', '', $sql );
	if ( false !== strpos( (string) $stripped, ';' ) ) {
		return array( 'error' => 'multi_statement', 'message' => 'One statement per call — remove the inner ";".' );
	}
	if ( ! preg_match( '/^(select|with)\b/i', $sql ) ) {
		return array( 'error' => 'read_only', 'message' => 'Only SELECT / WITH queries are allowed (the connection is opened read-only).' );
	}

	$max_rows = isset( $arguments['max_rows'] ) ? max( 1, min( 5000, (int) $arguments['max_rows'] ) ) : 200;
	$params   = ( isset( $arguments['params'] ) && is_array( $arguments['params'] ) ) ? array_values( $arguments['params'] ) : array();

	$db = cc_wh_open( true );
	if ( is_array( $db ) ) {
		return $db;
	}

	try {
		$stmt = @$db->prepare( $sql );
		if ( false === $stmt ) {
			$msg = $db->lastErrorMsg();
			$db->close();
			return array( 'error' => 'sql_error', 'message' => $msg );
		}
		foreach ( $params as $i => $value ) {
			$type = is_int( $value ) ? SQLITE3_INTEGER : ( is_float( $value ) ? SQLITE3_FLOAT : SQLITE3_TEXT );
			$stmt->bindValue( $i + 1, $value, $type );
		}
		$res = @$stmt->execute();
		if ( false === $res ) {
			$msg = $db->lastErrorMsg();
			$db->close();
			return array( 'error' => 'sql_error', 'message' => $msg );
		}

		$columns = array();
		$rows    = array();
		$n       = 0;
		while ( $row = $res->fetchArray( SQLITE3_ASSOC ) ) {
			if ( empty( $columns ) ) {
				$columns = array_keys( $row );
			}
			$rows[] = array_values( $row );
			$n++;
			if ( $n >= $max_rows + 1 ) {
				break;
			}
		}
	} catch ( Throwable $e ) {
		$db->close();
		return array( 'error' => 'sql_error', 'message' => $e->getMessage() );
	}
	$db->close();

	$truncated = count( $rows ) > $max_rows;
	if ( $truncated ) {
		$rows = array_slice( $rows, 0, $max_rows );
	}
	return array(
		'columns'   => $columns,
		'rows'      => $rows,
		'row_count' => count( $rows ),
		'truncated' => $truncated,
	);
}

/* -------------------------------------------------------------------------
 * Outcome engine v2 (v0.57): difference-in-differences per applied edit.
 * ---------------------------------------------------------------------- */

/** Escape LIKE wildcards in a literal fragment (ESCAPE '\'). */
function cc_wh_like_escape( $s ) {
	return str_replace( array( '\\', '%', '_' ), array( '\\\\', '\\%', '\\_' ), (string) $s );
}

/**
 * Exact URL permutations for a path on THIS site (scheme x www x trailing
 * slash = 8 values). v0.60.1: replaces suffix-LIKE matching, which pooled
 * /es/<same-slug>/ Polylang translations into the EN page's totals.
 */
function cc_wh_page_url_variants( $path ) {
	$host = strtolower( (string) parse_url( (string) getenv( 'CC_WP_URL' ), PHP_URL_HOST ) );
	$host = preg_replace( '/^www\./', '', $host );
	$path = '/' . ltrim( (string) $path, '/' );
	$base = rtrim( $path, '/' );
	$out  = array();
	foreach ( array( 'https://', 'http://' ) as $scheme ) {
		foreach ( array( $host, 'www.' . $host ) as $h ) {
			$out[] = $scheme . $h . $base;
			$out[] = $scheme . $h . $base . '/';
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * SUM clicks/impressions for a date window.
 *
 * $path         — restrict to ONE page (exact URL variants).
 * $exclude_path — sum every page EXCEPT that one. Used for the site-wide
 *                 control: a control that still contains the page being
 *                 measured is not a control. It barely matters for a small
 *                 page, but a homepage is routinely 20-40% of a site's clicks,
 *                 so including it drags the "site trend" toward the very move
 *                 being measured and understates the effect. Added 2026-08-21.
 */
function cc_wh_window_totals( $db, $start, $end, $path = null, $exclude_path = null ) {
	if ( null === $path && null !== $exclude_path ) {
		$variants = cc_wh_page_url_variants( $exclude_path );
		$names    = array();
		foreach ( $variants as $i => $v ) {
			$names[] = ':x' . $i;
		}
		$stmt = $db->prepare(
			"SELECT COALESCE(SUM(clicks),0) c, COALESCE(SUM(impressions),0) i FROM gsc_daily
			 WHERE date >= :s AND date <= :e AND page NOT IN ( " . implode( ',', $names ) . ' )'
		);
		foreach ( $variants as $i => $v ) {
			$stmt->bindValue( ':x' . $i, $v, SQLITE3_TEXT );
		}
	} elseif ( null === $path ) {
		$stmt = $db->prepare( 'SELECT COALESCE(SUM(clicks),0) c, COALESCE(SUM(impressions),0) i FROM gsc_daily WHERE date >= :s AND date <= :e' );
	} else {
		$variants = cc_wh_page_url_variants( $path );
		$names    = array();
		foreach ( $variants as $i => $v ) {
			$names[] = ':p' . $i;
		}
		$stmt = $db->prepare(
			"SELECT COALESCE(SUM(clicks),0) c, COALESCE(SUM(impressions),0) i FROM gsc_daily
			 WHERE date >= :s AND date <= :e AND page IN ( " . implode( ',', $names ) . ' )'
		);
		foreach ( $variants as $i => $v ) {
			$stmt->bindValue( ':p' . $i, $v, SQLITE3_TEXT );
		}
	}
	$stmt->bindValue( ':s', $start, SQLITE3_TEXT );
	$stmt->bindValue( ':e', $end, SQLITE3_TEXT );
	$res = $stmt->execute();
	$row = $res ? $res->fetchArray( SQLITE3_ASSOC ) : false;
	return array(
		'clicks'      => $row ? (int) $row['c'] : 0,
		'impressions' => $row ? (int) $row['i'] : 0,
	);
}

/**
 * outcome_report tool: for every applied edit old enough to judge, compare
 * the target page's clicks in symmetric pre/post windows around the apply
 * date, minus the SITE-WIDE change over the same windows (difference-in-
 * differences) — so an algorithm update or seasonal swing is not credited
 * to the edit. This is the structural fix for the get_edit_outcome
 * asymmetric-window problem: windows are always equal length, and the site
 * total is the control.
 *
 * Arguments: window (days each side, default 28, 7-56), limit (edits to
 * pull, default 20), min_pre_clicks (low-data threshold, default 20).
 */
function cc_wh_tool_outcome_report( $arguments ) {
	$window         = isset( $arguments['window'] ) ? max( 7, min( 56, (int) $arguments['window'] ) ) : 28;
	$limit          = isset( $arguments['limit'] ) ? max( 1, min( 100, (int) $arguments['limit'] ) ) : 20;
	$min_pre_clicks = isset( $arguments['min_pre_clicks'] ) ? max( 1, (int) $arguments['min_pre_clicks'] ) : 20;

	$db = cc_wh_open( true );
	if ( is_array( $db ) ) {
		return $db;
	}

	try {
		$cov = $db->querySingle( 'SELECT MIN(date) min_d, MAX(date) max_d FROM sync_log', true );
	} catch ( Throwable $e ) {
		$db->close();
		return array( 'error' => 'warehouse_unreadable', 'message' => $e->getMessage() );
	}
	$wh_min = isset( $cov['min_d'] ) ? (string) $cov['min_d'] : '';
	$wh_max = isset( $cov['max_d'] ) ? (string) $cov['max_d'] : '';
	if ( '' === $wh_min || '' === $wh_max ) {
		$db->close();
		return array( 'error' => 'warehouse_empty', 'message' => 'No synced GSC data yet — run gsc_warehouse_sync first.' );
	}

	$edits_resp = wp_rest_call( '/edits', 'GET', null, array( 'limit' => $limit ) );
	if ( isset( $edits_resp['error'] ) ) {
		$db->close();
		return cc_wh_friendly_transport_error( $edits_resp, '/edits' );
	}
	// /edits uses the plugin's {site, data:{...}} response envelope (unlike
	// the newer gsc-export/leads routes) — unwrap it, tolerating both shapes.
	$edits_body = ( isset( $edits_resp['data'] ) && is_array( $edits_resp['data'] ) ) ? $edits_resp['data'] : $edits_resp;
	$edit_rows  = ( isset( $edits_body['edits'] ) && is_array( $edits_body['edits'] ) ) ? $edits_body['edits'] : array();

	// Best-effort lead counts (v0.58): fold form-submission counts into each
	// judged edit. A 404 (pre-0.58 site) or empty table just means no leads
	// data — never an error for the outcome report itself.
	$leads_map  = array();
	$leads_resp = wp_rest_call( '/leads', 'GET', null, array( 'days' => 400 ) );
	if ( isset( $leads_resp['data'] ) && is_array( $leads_resp['data'] ) ) {
		$leads_resp = $leads_resp['data'];
	}
	if ( ! isset( $leads_resp['error'] ) && isset( $leads_resp['rows'] ) && is_array( $leads_resp['rows'] ) ) {
		foreach ( $leads_resp['rows'] as $lr ) {
			if ( is_array( $lr ) && count( $lr ) >= 4 ) {
				$lpid = (int) $lr[1];
				$ld   = (string) $lr[0];
				if ( ! isset( $leads_map[ $lpid ][ $ld ] ) ) {
					$leads_map[ $lpid ][ $ld ] = 0;
				}
				$leads_map[ $lpid ][ $ld ] += (int) $lr[3];
			}
		}
	}

	// Count edits per post so overlapping-edit attribution is flagged.
	$per_post = array();
	foreach ( $edit_rows as $row ) {
		$pid = isset( $row['edit']['post_id'] ) ? (int) $row['edit']['post_id'] : 0;
		if ( $pid > 0 ) {
			$per_post[ $pid ] = isset( $per_post[ $pid ] ) ? $per_post[ $pid ] + 1 : 1;
		}
	}

	$out     = array();
	$summary = array( 'win' => 0, 'flat' => 0, 'regressed' => 0, 'low_data' => 0, 'too_early' => 0, 'no_url' => 0 );

	try {
		foreach ( $edit_rows as $row ) {
			$edit = isset( $row['edit'] ) && is_array( $row['edit'] ) ? $row['edit'] : array();
			$item = array(
				'edit_id'    => isset( $edit['id'] ) ? (int) $edit['id'] : 0,
				'post_id'    => isset( $edit['post_id'] ) ? (int) $edit['post_id'] : 0,
				'change'     => isset( $edit['change_summary'] ) ? (string) $edit['change_summary'] : ( isset( $edit['change_type'] ) ? (string) $edit['change_type'] : '' ),
				'applied_at' => isset( $edit['applied_at'] ) ? (string) $edit['applied_at'] : '',
			);

			$page_url = isset( $edit['page_url'] ) ? (string) $edit['page_url'] : '';
			$path     = is_string( $page_url ) && '' !== $page_url ? (string) parse_url( $page_url, PHP_URL_PATH ) : '';
			// The homepage USED to be skipped here because page matching was a
			// suffix LIKE, where '/' matched every URL on the site. v0.60.1
			// replaced that with exact variant matching (page IN (...8 forms)),
			// so '/' now resolves to the eight homepage spellings and nothing
			// else — but this guard was never revisited. The result was that the
			// biggest non-brand page on most of these sites could never be
			// scored at all, and homepage regressions had to be diagnosed by
			// hand. Lifted 2026-08-21; only genuinely unattributable rows
			// (no path, or no apply date) are skipped now.
			if ( '' === $path || ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $item['applied_at'] ) ) {
				$item['verdict'] = 'no_url';
				$summary['no_url']++;
				$out[] = $item;
				continue;
			}
			$applied = substr( $item['applied_at'], 0, 10 );

			// Symmetric observed window: capped by warehouse freshness.
			$days_observed = min( $window, cc_wh_date_diff_days( $applied, $wh_max ) - 1 );
			if ( $days_observed < 7 ) {
				$item['verdict'] = 'too_early';
				$item['note']    = 'Only ' . max( 0, $days_observed ) . ' finalized post-apply days in the warehouse; needs 7+.';
				$summary['too_early']++;
				$out[] = $item;
				continue;
			}
			$pre_start  = cc_wh_date_sub( $applied, $days_observed );
			$pre_end    = cc_wh_date_sub( $applied, 1 );
			$post_start = cc_wh_date_add( $applied, 1 );
			$post_end   = cc_wh_date_add( $applied, $days_observed );

			$page_pre  = cc_wh_window_totals( $db, $pre_start, $pre_end, $path );
			$page_post = cc_wh_window_totals( $db, $post_start, $post_end, $path );
			// Control = the REST of the site, with the measured page removed.
			$site_pre  = cc_wh_window_totals( $db, $pre_start, $pre_end, null, $path );
			$site_post = cc_wh_window_totals( $db, $post_start, $post_end, null, $path );

			$page_delta = 100.0 * ( $page_post['clicks'] - $page_pre['clicks'] ) / max( 1, $page_pre['clicks'] );
			$site_delta = 100.0 * ( $site_post['clicks'] - $site_pre['clicks'] ) / max( 1, $site_pre['clicks'] );
			$did        = round( $page_delta - $site_delta, 1 );

			$item['days_each_side']   = $days_observed;
			$item['page_clicks']      = array( 'pre' => $page_pre['clicks'], 'post' => $page_post['clicks'] );
			$item['page_impressions'] = array( 'pre' => $page_pre['impressions'], 'post' => $page_post['impressions'] );
			$item['page_delta_pct']   = round( $page_delta, 1 );
			$item['site_delta_pct']   = round( $site_delta, 1 );
			$item['did_delta_pct']    = $did;

			if ( $page_pre['clicks'] < $min_pre_clicks && $page_post['clicks'] < $min_pre_clicks ) {
				$item['verdict'] = 'low_data';
			} elseif ( $did >= 10 ) {
				$item['verdict'] = 'win';
			} elseif ( $did <= -10 ) {
				$item['verdict'] = 'regressed';
			} else {
				$item['verdict'] = 'flat';
			}
			if ( isset( $leads_map[ $item['post_id'] ] ) ) {
				$leads_pre  = 0;
				$leads_post = 0;
				foreach ( $leads_map[ $item['post_id'] ] as $ld => $ln ) {
					if ( $ld >= $pre_start && $ld <= $pre_end ) {
						$leads_pre += $ln;
					} elseif ( $ld >= $post_start && $ld <= $post_end ) {
						$leads_post += $ln;
					}
				}
				if ( $leads_pre + $leads_post > 0 ) {
					$item['leads'] = array( 'pre' => $leads_pre, 'post' => $leads_post );
				}
			}
			if ( $pre_start < $wh_min ) {
				$item['note'] = 'Baseline partially precedes warehouse coverage (' . $wh_min . ') — treat with caution.';
			}
			// The DiD verdict for this edit is only as sound as its baseline.
			// A pre window inside the inflated-impressions period compared
			// against a post window after the fix manufactures a decline.
			$item['data_integrity'] = cc_wh_impressions_integrity( $pre_start, $post_end );
			if ( $item['post_id'] > 0 && $per_post[ $item['post_id'] ] > 1 ) {
				$item['overlapping_edits'] = $per_post[ $item['post_id'] ];
			}
			$summary[ $item['verdict'] ]++;
			$out[] = $item;
		}
	} catch ( Throwable $e ) {
		$db->close();
		return array( 'error' => 'outcome_failed', 'message' => $e->getMessage() );
	}
	$db->close();

	// Regressions first — they are the actionable rows.
	$order = array( 'regressed' => 0, 'win' => 1, 'flat' => 2, 'low_data' => 3, 'too_early' => 4, 'no_url' => 5 );
	usort(
		$out,
		function ( $a, $b ) use ( $order ) {
			return $order[ $a['verdict'] ] <=> $order[ $b['verdict'] ];
		}
	);

	$result = array(
		'method'             => 'difference-in-differences: page clicks pre-vs-post minus site-wide change over SYMMETRIC windows; verdict thresholds +/-10 points',
		'window_days'        => $window,
		'warehouse_coverage' => array( 'earliest' => $wh_min, 'latest' => $wh_max ),
		// Per-edit integrity is on each row; this is the report-level read.
		'data_integrity'     => cc_wh_impressions_integrity( $wh_min, $wh_max ),
		'summary'            => $summary,
		'edits'              => $out,
	);
	// v0.60.1: leads that could not be attributed to a post (referer stripped
	// or Custom Permalinks defeating url_to_postid) land under post_id 0 —
	// surface them instead of silently reporting "no leads" for converting pages.
	if ( isset( $leads_map[0] ) ) {
		$result['leads_unattributed'] = array_sum( $leads_map[0] );
		$result['leads_note']         = 'Some form submissions could not be attributed to a page (post_id 0) — per-edit leads may undercount.';
	}
	return $result;
}

/* -------------------------------------------------------------------------
 * Commodity audit + AEO snapshot (v0.60).
 * ---------------------------------------------------------------------- */

/** Rough expected organic CTR by average position (informational baseline). */
function cc_expected_ctr( $pos ) {
	if ( $pos <= 1.5 ) { return 0.28; }
	if ( $pos <= 2.5 ) { return 0.15; }
	if ( $pos <= 3.5 ) { return 0.09; }
	if ( $pos <= 5 )   { return 0.05; }
	if ( $pos <= 8 )   { return 0.025; }
	if ( $pos <= 12 )  { return 0.012; }
	if ( $pos <= 20 )  { return 0.006; }
	return 0.003;
}

/**
 * Pure classifier: warehouse metrics + content signals -> one of four
 * strategies. Absorption = actual CTR under 25% of the positional
 * expectation with real impressions — the market saying "AI answers this".
 *   INVEST    conversion pages (never STOP a money page)
 *   BRIDGE    research content still earning clicks — keep, weave service links
 *   CITE_PLAY absorbed but high-volume — format to be the CITED source
 *   STOP      absorbed, low volume — no further investment
 */
function cc_commodity_classify( $row ) {
    $imp = max( 0, (int) ( $row['impressions'] ?? 0 ) );
    $pos = (float) ( $row['position'] ?? 0 );
    $ctr = $imp > 0 ? max( 0, (int) ( $row['clicks'] ?? 0 ) ) / $imp : 0;
    $low_ctr = $imp >= 200 && $pos > 0 && $pos <= 20 && $ctr < 0.25 * cc_expected_ctr( $pos );
    return array(
        'verdict' => $imp <= 0 || $pos <= 0 ? 'REVIEW_DATA' : ( $low_ctr ? 'REVIEW_CTR' : 'OBSERVE' ),
        'absorbed' => null, 'ai_attribution' => 'not_established', 'low_ctr_candidate' => $low_ctr,
        'business_role_hint' => $row['intent_family'] ?? 'unknown',
        'ctr' => round( 100 * $ctr, 2 ), 'expected_ctr' => $pos > 0 ? round( 100 * cc_expected_ctr( $pos ), 2 ) : null,
        'decision_policy' => 'content-evidence-1',
        'next_step' => 'Check demand, indexing, query mix, reader purpose and measured outcomes. CTR and volume alone do not justify stopping, rewriting or claiming AI absorption.',
    );
}



/**
 * Merge redirected URLs into the row for the post they redirect to.
 *
 * Search Console keeps reporting a retired URL for months after it starts
 * 301ing. Those impressions belong to the destination: the searcher who saw
 * that result and clicked landed on the destination post. Keeping them on a
 * separate row understates the destination and leaves a phantom "unresolved"
 * row that reads like a defect.
 *
 * Position is re-averaged impression-weighted, because a straight mean of two
 * rows with wildly different volumes is meaningless.
 *
 * @param array $pages  Warehouse rows {page, impressions, clicks, position}.
 * @param array $by_url Signal rows from /commodity/signals, keyed by URL.
 * @return array Rows with redirected sources folded into their destinations.
 */
function cc_commodity_fold_redirects( $pages, $by_url ) {
	// Canonical URL per resolved post id, preferring a row that resolved
	// directly — that is the URL actually serving content today.
	$canonical = array();
	foreach ( $pages as $p ) {
		$sig = isset( $by_url[ $p['page'] ] ) ? $by_url[ $p['page'] ] : array();
		if ( empty( $sig['found'] ) ) {
			continue;
		}
		$pid = (int) $sig['post_id'];
		$via = isset( $sig['via'] ) ? (string) $sig['via'] : 'direct';
		if ( $pid <= 0 || 'redirect' === $via ) {
			continue;
		}
		if ( ! isset( $canonical[ $pid ] ) ) {
			$canonical[ $pid ] = $p['page'];
		}
	}

	$index = array();
	foreach ( $pages as $i => $p ) {
		$index[ $p['page'] ] = $i;
	}

	$folded = array();
	foreach ( $pages as $i => $p ) {
		$sig = isset( $by_url[ $p['page'] ] ) ? $by_url[ $p['page'] ] : array();
		$via = isset( $sig['via'] ) ? (string) $sig['via'] : '';
		$pid = isset( $sig['post_id'] ) ? (int) $sig['post_id'] : 0;

		if ( 'redirect' !== $via || $pid <= 0 || ! isset( $canonical[ $pid ] ) ) {
			continue;
		}
		$dest_url = $canonical[ $pid ];
		if ( ! isset( $index[ $dest_url ] ) || $index[ $dest_url ] === $i ) {
			continue;
		}
		$d = $index[ $dest_url ];

		$src_imp = (int) $p['impressions'];
		$dst_imp = (int) $pages[ $d ]['impressions'];
		$tot_imp = $src_imp + $dst_imp;
		if ( $tot_imp > 0 ) {
			$pages[ $d ]['position'] = round(
				( ( (float) $pages[ $d ]['position'] * $dst_imp ) + ( (float) $p['position'] * $src_imp ) ) / $tot_imp,
				1
			);
		}
		$pages[ $d ]['impressions'] = $tot_imp;
		$pages[ $d ]['clicks']      = (int) $pages[ $d ]['clicks'] + (int) $p['clicks'];

		if ( ! isset( $pages[ $d ]['folded_from'] ) ) {
			$pages[ $d ]['folded_from'] = array();
		}
		$pages[ $d ]['folded_from'][] = array(
			'url'         => $p['page'],
			'impressions' => $src_imp,
			'clicks'      => (int) $p['clicks'],
			'code'        => isset( $sig['redirect_code'] ) ? (int) $sig['redirect_code'] : 301,
		);
		$folded[ $i ] = true;
	}

	if ( empty( $folded ) ) {
		return $pages;
	}
	$kept = array();
	foreach ( $pages as $i => $p ) {
		if ( ! isset( $folded[ $i ] ) ) {
			$kept[] = $p;
		}
	}
	// Re-sort: folding changes the volume ranking.
	usort(
		$kept,
		static function ( $a, $b ) {
			return (int) $b['impressions'] <=> (int) $a['impressions'];
		}
	);
	return $kept;
}

/**
 * commodity_audit tool: classify the site's top pages (by 28d impressions)
 * into INVEST / CITE_PLAY / BRIDGE / STOP using warehouse metrics + the
 * site's content signals (/commodity/signals). Args: days (default 28),
 * limit pages (default 25, max 50).
 */
function cc_wh_tool_commodity_audit( $arguments ) {
	$days  = isset( $arguments['days'] ) ? max( 7, min( 90, (int) $arguments['days'] ) ) : 28;
	$limit = isset( $arguments['limit'] ) ? max( 5, min( 50, (int) $arguments['limit'] ) ) : 25;

	$db = cc_wh_open( true );
	if ( is_array( $db ) ) {
		return $db;
	}
	try {
		$since = gmdate( 'Y-m-d', time() - $days * 86400 );
		$stmt  = $db->prepare(
			'SELECT page, SUM(impressions) imp, SUM(clicks) ck, SUM(position*impressions)/SUM(impressions) pos
			 FROM gsc_daily WHERE date >= :s GROUP BY page ORDER BY imp DESC LIMIT ' . (int) $limit
		);
		$stmt->bindValue( ':s', $since, SQLITE3_TEXT );
		$res   = $stmt->execute();
		$pages = array();
		while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
			$pages[] = array(
				'page'        => (string) $r['page'],
				'impressions' => (int) $r['imp'],
				'clicks'      => (int) $r['ck'],
				'position'    => round( (float) $r['pos'], 1 ),
			);
		}
	} catch ( Throwable $e ) {
		$db->close();
		return array( 'error' => 'warehouse_unreadable', 'message' => $e->getMessage() );
	}
	$db->close();

	if ( empty( $pages ) ) {
		return array( 'error' => 'no_data', 'message' => 'No warehouse rows in the window — run gsc_warehouse_sync first.' );
	}

	$signals_resp = wp_rest_call( '/commodity/signals', 'POST', array( 'urls' => array_column( $pages, 'page' ) ) );
	if ( isset( $signals_resp['error'] ) ) {
		return cc_wh_friendly_transport_error( $signals_resp, '/commodity/signals' );
	}
	$by_url = array();
	foreach ( ( isset( $signals_resp['rows'] ) && is_array( $signals_resp['rows'] ) ? $signals_resp['rows'] : array() ) as $s ) {
		$by_url[ (string) $s['url'] ] = $s;
	}

	// v0.71: fold redirected URLs into their destination BEFORE classifying.
	// A consolidated URL's impressions belong to the post that replaced it;
	// leaving them on a separate row understated the destination's real volume
	// and produced a "REVIEW / classify manually" row for work already done.
	$pages = cc_commodity_fold_redirects( $pages, $by_url );

	$out    = array();
	$counts = array( 'OBSERVE' => 0, 'REVIEW_CTR' => 0, 'REVIEW_DATA' => 0 );
	foreach ( $pages as $p ) {
		$sig        = isset( $by_url[ $p['page'] ] ) ? $by_url[ $p['page'] ] : array();
		$unresolved = empty( $sig ) || empty( $sig['found'] );
		$p['intent_family']     = isset( $sig['intent_family'] ) ? (string) $sig['intent_family'] : 'unknown';
		$p['info_gain_signals'] = isset( $sig['info_gain_signals'] ) ? $sig['info_gain_signals'] : array();
		$p['post_id']           = isset( $sig['post_id'] ) ? (int) $sig['post_id'] : 0;

		if ( $unresolved ) {
			// v0.60.1: never classify blind — a mis-set STOP on a money page is
			// the one mistake this tool must not make.
			$c            = cc_commodity_classify( array_merge( $p, array( 'intent_family' => 'research' ) ) );
			$c['verdict'] = 'REVIEW';
			$p            = array_merge( $p, $c );
			// v0.71: report the measured reason from the resolver instead of
			// guessing "Custom Permalinks / host mismatch?" — that guess was
			// wrong on a live site and sent a real investigation sideways.
			$p['note'] = isset( $sig['reason'] ) && '' !== (string) $sig['reason']
				? (string) $sig['reason']
				: 'URL did not resolve to a post, and no redirect rule covers it — classify manually.';
		} else {
			$c = cc_commodity_classify( $p );
			$p = array_merge( $p, $c );
			if ( 'CITE_PLAY' === $c['verdict'] && empty( $p['info_gain_signals'] ) ) {
				$p['fix'] = 'Absorbed + zero info-gain elements: add answer-first cited chunk + a first-party element, goal = be the CITED source.';
			}
		}
		if ( ! isset( $counts[ $p['verdict'] ] ) ) {
			$counts[ $p['verdict'] ] = 0;
		}
		$counts[ $p['verdict'] ]++;
		$out[] = $p;
	}

	return array(
		'window_days' => $days,
		'strategy_key' => 'OBSERVE = measured activity | REVIEW_CTR = low CTR hypothesis to investigate | REVIEW_DATA = insufficient data. No automatic investment, retirement or AI-causation verdict.',
		'summary'     => $counts,
		'pages'       => $out,
	);
}

/**
 * aeo_snapshot tool: one dated AI-visibility snapshot for THIS site, stored
 * in the local warehouse for trend comparison: AI-crawler hits (llm_crawls),
 * AI Overview presence (gsc/ai-overview), and the absorbed-query count from
 * the warehouse. Compares against the previous snapshot when one exists.
 * (Ubersuggest brand-visibility lives on a separate MCP server — join it at
 * session level when needed.)
 */
function cc_wh_tool_aeo_snapshot( $arguments ) {
	$db = cc_wh_open( false );
	if ( is_array( $db ) ) {
		return $db;
	}
	try {
		$db->exec( 'CREATE TABLE IF NOT EXISTS aeo_snapshots ( snapshot_date TEXT PRIMARY KEY, data TEXT NOT NULL )' );

		// Low-CTR candidates under a local heuristic; the cause is not observed.
		$since = gmdate( 'Y-m-d', time() - 28 * 86400 );
		$stmt  = $db->prepare(
			'SELECT COUNT(*) n, COALESCE(SUM(imp),0) total_imp FROM (
				SELECT query, SUM(impressions) imp, SUM(clicks) ck, SUM(position*impressions)/SUM(impressions) pos
				FROM gsc_daily WHERE date >= :s GROUP BY query HAVING imp >= 200 AND pos <= 20
			) WHERE ck * 4.0 < imp * ( CASE
				WHEN pos <= 1.5 THEN 0.28 WHEN pos <= 2.5 THEN 0.15 WHEN pos <= 3.5 THEN 0.09
				WHEN pos <= 5 THEN 0.05 WHEN pos <= 8 THEN 0.025 WHEN pos <= 12 THEN 0.012
				WHEN pos <= 20 THEN 0.006 ELSE 0.003 END )'
		);
		if ( ! $stmt ) { throw new RuntimeException( 'Candidate query could not be prepared.' ); }
		$stmt->bindValue( ':s', $since, SQLITE3_TEXT );
		$res      = $stmt->execute();
		$candidate_row  = $res ? $res->fetchArray( SQLITE3_ASSOC ) : false;
		if ( ! $candidate_row ) { throw new RuntimeException( 'Candidate query returned no measurement.' ); }
		$candidates = array(
			'queries'     => $candidate_row ? (int) $candidate_row['n'] : 0,
			'impressions' => $candidate_row ? (int) $candidate_row['total_imp'] : 0,
		);

		$crawls = cc_unwrap_envelope( wp_rest_call( '/llm-crawls', 'GET', null, array( 'days' => 7 ) ) );
		$aio    = cc_unwrap_envelope( wp_rest_call( '/gsc/ai-overview', 'GET', null, array() ) );

		$snapshot = array(
            'measurement_version' => 'observations-2',
            'ai_attribution' => 'unknown',
            'limits' => array( 'Low CTR has multiple possible causes. This heuristic cannot identify AI absorption.', 'Crawler requests do not establish citations or referral visits.', 'Search Console query omissions and date coverage constrain this snapshot.' ),
			'low_ctr_candidates_28d' => $candidates,
			'llm_crawls_7d' => isset( $crawls['error'] ) ? array( 'unavailable' => $crawls['error'] ) : $crawls,
			'search_appearance_observations' => isset( $aio['error'] ) ? array( 'unavailable' => $aio['error'] ) : $aio,
		);

		$today = gmdate( 'Y-m-d' );
		$prev  = null;
		$res   = $db->query( "SELECT snapshot_date, data FROM aeo_snapshots WHERE snapshot_date < '" . $today . "' ORDER BY snapshot_date DESC LIMIT 1" );
		if ( $res && ( $row = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
			$prev = array( 'date' => $row['snapshot_date'], 'data' => json_decode( (string) $row['data'], true ) );
		}

		$ins = $db->prepare( 'INSERT OR REPLACE INTO aeo_snapshots (snapshot_date, data) VALUES (:d, :j)' );
		if ( ! $ins ) { throw new RuntimeException( 'Snapshot insert could not be prepared.' ); }
		$ins->bindValue( ':d', $today, SQLITE3_TEXT );
		$ins->bindValue( ':j', json_encode( $snapshot ), SQLITE3_TEXT );
		if ( ! $ins->execute() ) { throw new RuntimeException( 'Snapshot could not be saved.' ); }
	} catch ( Throwable $e ) {
		$db->close();
		return array( 'error' => 'aeo_snapshot_failed', 'message' => $e->getMessage() );
	}
	$db->close();

	$result = array( 'date' => $today, 'snapshot' => $snapshot );
	if ( $prev && ( $prev['data']['measurement_version'] ?? '' ) === 'observations-2' && isset( $prev['data']['low_ctr_candidates_28d'] ) ) {
		$result['vs_previous'] = array(
			'previous_date'      => $prev['date'],
			'low_ctr_queries_delta'     => $candidates['queries'] - (int) $prev['data']['low_ctr_candidates_28d']['queries'],
			'low_ctr_impressions_delta' => $candidates['impressions'] - (int) $prev['data']['low_ctr_candidates_28d']['impressions'],
		);
	}
	if ( $prev && ( $prev['data']['measurement_version'] ?? '' ) !== 'observations-2' ) { $result['previous_comparison'] = 'not_comparable_legacy_measurement'; }
	return $result;
}

/** gsc_warehouse_status tool: coverage + size snapshot, no writes. */
/**
 * v0.76.3. Google over-reported Search Console IMPRESSIONS from 2025-05-13
 * until 2026-04-27 — a logging error, since fixed, with history NOT restated.
 * Clicks were unaffected.
 *
 * This matters far more here than on the WP side. wp_cc_gsc_queries prunes at
 * 60 days and resolve_windows() clamps to 30, so the site-side tools can never
 * reach the window. This warehouse keeps everything: every site's history
 * starts inside the bug period, so roughly the first half of it carries
 * inflated impressions while its clicks are correct. A difference-in-
 * differences read whose PRE window sits inside the window and whose POST
 * window sits after it will report an impression collapse that never happened.
 *
 * Source: support.google.com/webmasters/answer/6211453 ("April 3" entry).
 * Returns null when the range being read starts after the fix, so this
 * disappears on its own as the warehouse ages rather than becoming noise.
 *
 * Kept self-contained: warehouse.php must not depend on the plugin (v0.60
 * dependency rule).
 */
define( 'CC_WH_IMPRESSION_BUG_START', '2025-05-13' );
define( 'CC_WH_IMPRESSION_BUG_END', '2026-04-27' );

function cc_wh_impressions_integrity( $window_start, $window_end = null ) {
	$start = (string) $window_start;
	if ( '' === $start || strcmp( $start, CC_WH_IMPRESSION_BUG_END ) > 0 ) {
		return null;
	}
	$note = 'Impressions between ' . CC_WH_IMPRESSION_BUG_START . ' and ' . CC_WH_IMPRESSION_BUG_END
		. ' are inflated by a Google logging error that was fixed without restating history. Clicks are correct — judge this period on CLICKS.';
	if ( null !== $window_end && strcmp( (string) $window_end, CC_WH_IMPRESSION_BUG_END ) > 0 ) {
		$note .= ' This range STRADDLES the fix date, so any impression or CTR decline it shows is partly the correction being removed, not lost visibility.';
	}
	return array(
		'code'       => 'gsc_impressions_inflated',
		'bug_window' => CC_WH_IMPRESSION_BUG_START . ' to ' . CC_WH_IMPRESSION_BUG_END,
		'reads_from' => $start,
		'clicks_ok'  => true,
		'note'       => $note,
		'source'     => 'https://support.google.com/webmasters/answer/6211453',
	);
}

function cc_wh_tool_status() {
	$db = cc_wh_open( true );
	if ( is_array( $db ) ) {
		return $db;
	}
	try {
		$cov  = $db->querySingle( 'SELECT COUNT(*) AS dates, MIN(date) AS min_d, MAX(date) AS max_d, MAX(fetched_at) AS last_fetch, SUM(CASE WHEN complete = 0 THEN 1 ELSE 0 END) AS incomplete FROM sync_log', true );
		$rows = $db->querySingle( 'SELECT COUNT(*) FROM gsc_daily' );
	} catch ( Throwable $e ) {
		// Read-only opens never run init_schema, so a 0-byte or foreign file
		// reaches here ("no such table") instead of dying with a fatal.
		$db->close();
		if ( false !== stripos( $e->getMessage(), 'no such table' ) ) {
			return array(
				'error'   => 'warehouse_empty',
				'message' => 'Warehouse file exists but has no data yet (' . cc_wh_db_path() . '). Run gsc_warehouse_sync first.',
			);
		}
		return array(
			'error'   => 'warehouse_unreadable',
			'message' => 'Could not read the warehouse (' . cc_wh_db_path() . '): ' . $e->getMessage() . ' — if the file is corrupt, delete it and re-run gsc_warehouse_sync.',
		);
	}
	$prop = cc_wh_meta_get( $db, 'gsc_property' );
	$db->close();

	$latest = isset( $cov['max_d'] ) ? (string) $cov['max_d'] : '';
	$stale  = '';
	if ( '' !== $latest ) {
		$freshest = gmdate( 'Y-m-d', time() - CC_WH_FINALIZE_LAG_DAYS * 86400 );
		if ( $latest < $freshest ) {
			$stale = 'Latest warehouse date is ' . $latest . ' but GSC has data through ' . $freshest . ' — run gsc_warehouse_sync.';
		}
	}

	return array(
		'site'         => cc_wh_site_key(),
		'db_path'      => cc_wh_db_path(),
		'db_size_mb'   => file_exists( cc_wh_db_path() ) ? round( filesize( cc_wh_db_path() ) / 1048576, 1 ) : 0,
		'property'     => $prop,
		'total_rows'   => (int) $rows,
		'dates_synced' => isset( $cov['dates'] ) ? (int) $cov['dates'] : 0,
		'earliest'     => isset( $cov['min_d'] ) ? $cov['min_d'] : null,
		'latest'       => isset( $cov['max_d'] ) ? $cov['max_d'] : null,
		'last_sync_at' => ! empty( $cov['last_fetch'] ) ? gmdate( 'Y-m-d H:i:s', (int) $cov['last_fetch'] ) . ' UTC' : null,
		'incomplete_dates' => isset( $cov['incomplete'] ) ? (int) $cov['incomplete'] : 0,
		'stale_hint'   => '' !== $stale ? $stale : null,
		// Null unless the stored history reaches into the inflated-impressions
		// window; clears itself as the warehouse ages past it.
		'data_integrity' => cc_wh_impressions_integrity(
			isset( $cov['min_d'] ) ? $cov['min_d'] : '',
			isset( $cov['max_d'] ) ? $cov['max_d'] : null
		),
	);
}

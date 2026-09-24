<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Operator kit (v0.57) + Operator Brain (v0.79).
 *
 * v0.57 stored two design skills so a fresh machine could rebuild the design
 * system from the site. v0.79 widens that to the WHOLE operator brain: every
 * skill, every memory file plus its index, the project CLAUDE.md, and a
 * secrets-stripped .mcp.json template. The bridge (bin/) also serves ITSELF
 * from here so a stale laptop can update its local MCP build without a repo.
 *
 * Why the site and not a repo: the operator asked for it. Ten sites already
 * hold app-password-authenticated REST; any one of them is reachable from any
 * laptop, and whoami is the first call every session makes, so whoami is
 * where "your local brain is behind" gets announced.
 *
 * Storage: one option, autoload off. Files are gzip+base64 as a JSON map so
 * a 1.5 MB brain is a ~400 KB row; a separate uncompressed index (path ->
 * sha1, bytes) serves status calls without inflating anything. The
 * fingerprint is sha256 over the sorted "path\n<sha1>\n" lines — the bridge
 * computes the same formula locally, so equal fingerprints mean identical
 * file sets, not "probably the same".
 *
 * All writes here are operator config (like site-memory notes), not content:
 * direct saves, no pending queue.
 */
class CC_Assistant_REST_Operator_Kit {

	const REST_NAMESPACE = 'cc-assistant/v1';
	const OPT_SKILLS     = 'cc_assistant_operator_skills';
	const OPT_BRAIN      = 'cc_assistant_operator_brain';

	const MAX_FILE_BYTES  = 400000;   // one memory/skill file
	const MAX_TOTAL_BYTES = 12000000; // whole brain, uncompressed
	const MAX_FILES       = 3000;

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/operator-kit',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/operator-kit/skills',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_push_skills' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		// v0.79 Operator Brain.
		register_rest_route(
			self::REST_NAMESPACE,
			'/operator-kit/brain/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_brain_status' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/operator-kit/brain',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'handle_brain_get' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'handle_brain_post' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/operator-kit/bridge',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_bridge_get' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	public static function check_permission() {
		if ( ! CC_Assistant_Access::can_use() ) {
			return new WP_Error( 'rest_forbidden', 'You do not have permission to use CC Assistant.', array( 'status' => 403 ) );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Pure helpers (no WP calls) — shared formula with bin/operator-brain.php */
	/* ------------------------------------------------------------------ */

	/**
	 * Fingerprint of a file set. Sorted by path so two machines listing the
	 * same files in different orders agree. Content enters via sha1 so the
	 * index alone (no bodies) is enough to recompute it.
	 *
	 * @param array $index path => array( 'sha1' => ..., 'bytes' => ... )
	 */
	public static function fingerprint_from_index( array $index ) {
		if ( empty( $index ) ) {
			return '';
		}
		ksort( $index, SORT_STRING );
		$lines = '';
		foreach ( $index as $path => $meta ) {
			$sha = is_array( $meta ) && isset( $meta['sha1'] ) ? (string) $meta['sha1'] : (string) $meta;
			$lines .= $path . "\n" . $sha . "\n";
		}
		return hash( 'sha256', $lines );
	}

	/** Build an index from a path => content map. */
	public static function index_from_files( array $files ) {
		$index = array();
		foreach ( $files as $path => $content ) {
			$index[ (string) $path ] = array(
				'sha1'  => sha1( (string) $content ),
				'bytes' => strlen( (string) $content ),
			);
		}
		ksort( $index, SORT_STRING );
		return $index;
	}

	/**
	 * A brain path is relative, forward-slash, and rooted in one of the
	 * four known prefixes. Anything else is refused: this store is written
	 * back to disk on other machines, so a stray "../" or absolute path here
	 * would become a file write there.
	 */
	public static function valid_brain_path( $path ) {
		if ( ! is_string( $path ) || '' === $path || strlen( $path ) > 300 ) {
			return false;
		}
		if ( false !== strpos( $path, '\\' ) || false !== strpos( $path, "\0" ) ) {
			return false;
		}
		if ( '/' === $path[0] || preg_match( '#(^|/)\.\.(/|$)#', $path ) || preg_match( '#^[A-Za-z]:#', $path ) ) {
			return false;
		}
		return (bool) preg_match( '#^(skills|memory|project)/[^/]#', $path );
	}

	/* ------------------------------------------------------------------ */
	/* Storage                                                              */
	/* ------------------------------------------------------------------ */

	/** Stored record with files inflated. Empty array when nothing stored. */
	public static function load_brain( $with_files = true ) {
		$rec = get_option( self::OPT_BRAIN, array() );
		if ( ! is_array( $rec ) || empty( $rec['index'] ) ) {
			return array();
		}
		if ( $with_files ) {
			$files = array();
			if ( isset( $rec['files_gz'] ) && is_string( $rec['files_gz'] ) && '' !== $rec['files_gz'] ) {
				$raw = base64_decode( $rec['files_gz'], true );
				$json = ( false !== $raw && function_exists( 'gzuncompress' ) ) ? @gzuncompress( $raw ) : false;
				if ( false === $json && false !== $raw ) {
					$json = $raw; // stored uncompressed (no zlib on that host)
				}
				$decoded = is_string( $json ) ? json_decode( $json, true ) : null;
				if ( is_array( $decoded ) ) {
					$files = $decoded;
				}
			}
			$rec['files'] = $files;
			$rec['integrity_error'] = empty( $files ) || self::fingerprint_from_index( self::index_from_files( $files ) ) !== ( $rec['fingerprint'] ?? '' );
		}
		unset( $rec['files_gz'] );
		return $rec;
	}

	/** Persist a full path => content map. Returns the summary or WP_Error. */
	public static function save_brain( array $files, $pushed_from = '' ) {
		$total = 0;
		foreach ( $files as $path => $content ) {
			if ( ! self::valid_brain_path( $path ) ) {
				return new WP_Error( 'bad_path', 'Refused brain path: ' . (string) $path, array( 'status' => 422 ) );
			}
			if ( ! is_string( $content ) ) {
				return new WP_Error( 'bad_content', 'Brain file must be a string: ' . $path, array( 'status' => 422 ) );
			}
			if ( strlen( $content ) > self::MAX_FILE_BYTES ) {
				return new WP_Error( 'file_too_large', $path . ' exceeds ' . self::MAX_FILE_BYTES . ' bytes.', array( 'status' => 422 ) );
			}
			$total += strlen( $content );
		}
		if ( count( $files ) > self::MAX_FILES ) {
			return new WP_Error( 'too_many_files', 'Brain exceeds ' . self::MAX_FILES . ' files.', array( 'status' => 422 ) );
		}
		if ( $total > self::MAX_TOTAL_BYTES ) {
			return new WP_Error( 'brain_too_large', 'Brain exceeds ' . self::MAX_TOTAL_BYTES . ' bytes uncompressed.', array( 'status' => 422 ) );
		}
		ksort( $files, SORT_STRING );
		$index = self::index_from_files( $files );
		$json  = json_encode( $files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new WP_Error( 'encode_failed', 'Brain could not be JSON-encoded (non-UTF8 file?).', array( 'status' => 422 ) );
		}
		$blob = function_exists( 'gzcompress' ) ? gzcompress( $json, 6 ) : $json;
		$rec  = array(
			'schema'      => 1,
			'index'       => $index,
			'files_gz'    => base64_encode( $blob ),
			'fingerprint' => self::fingerprint_from_index( $index ),
			'file_count'  => count( $files ),
			'bytes'       => $total,
			'stored_bytes'=> strlen( $blob ),
			'updated_at'  => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'pushed_from' => is_string( $pushed_from ) ? substr( $pushed_from, 0, 120 ) : '',
		);
		$saved = update_option( self::OPT_BRAIN, $rec, false );
		if ( ! $saved && get_option( self::OPT_BRAIN, array() ) !== $rec ) {
			return new WP_Error( 'brain_storage_failed', 'The shared brain was not saved. Existing data remains authoritative; do not claim synchronization.', array( 'status' => 500 ) );
		}
		return self::summary_from_record( $rec );
	}

	private static function summary_from_record( array $rec ) {
		if ( empty( $rec['index'] ) ) {
			return array(
				'present'     => false,
				'fingerprint' => '',
				'file_count'  => 0,
				'bytes'       => 0,
				'updated_at'  => '',
				'pushed_from' => '',
			);
		}
		$groups = array( 'skills' => 0, 'memory' => 0, 'project' => 0 );
		foreach ( array_keys( $rec['index'] ) as $p ) {
			$g = substr( $p, 0, strpos( $p, '/' ) );
			if ( isset( $groups[ $g ] ) ) {
				$groups[ $g ]++;
			}
		}
		return array(
			'present'     => true,
			'fingerprint' => isset( $rec['fingerprint'] ) ? (string) $rec['fingerprint'] : '',
			'file_count'  => isset( $rec['file_count'] ) ? (int) $rec['file_count'] : count( $rec['index'] ),
			'bytes'       => isset( $rec['bytes'] ) ? (int) $rec['bytes'] : 0,
			'updated_at'  => isset( $rec['updated_at'] ) ? (string) $rec['updated_at'] : '',
			'pushed_from' => isset( $rec['pushed_from'] ) ? (string) $rec['pushed_from'] : '',
			'groups'      => $groups,
		);
	}

	/** Small block for whoami: is there a brain here, how fresh, what hash. */
	public static function brain_summary() {
		$rec = get_option( self::OPT_BRAIN, array() );
		return self::summary_from_record( is_array( $rec ) ? $rec : array() );
	}

	/** The bin/ files this site ships, with hashes — whoami compares them to the laptop's copy. */
	public static function bridge_files( $with_content = false ) {
		$dir = CC_ASSISTANT_DIR . 'bin/';
		$out = array();
		if ( ! is_dir( $dir ) ) {
			return $out;
		}
		$files = glob( $dir . '*.php' );
		foreach ( array( 'browser-transport.mjs', 'browser/package.json', 'automation-runner.py' ) as $extra ) {
			if ( is_file( $dir . $extra ) ) { $files[] = $dir . $extra; }
		}
		foreach ( $files as $abs ) {
			$name    = str_replace( '\\', '/', substr( $abs, strlen( $dir ) ) );
			$content = (string) file_get_contents( $abs );
			$entry   = array(
				'sha1'  => sha1( $content ),
				'bytes' => strlen( $content ),
			);
			if ( 'mcp-server.php' === $name && preg_match( "/define\\(\\s*'CC_MCP_VERSION',\\s*'([^']+)'/", $content, $m ) ) {
				$entry['version'] = $m[1];
			}
			if ( $with_content ) {
				$entry['content'] = $content;
			}
			$out[ $name ] = $entry;
		}
		ksort( $out, SORT_STRING );
		return $out;
	}

	public static function bridge_hashes() {
		$files = self::bridge_files( false );
		$out   = array();
		foreach ( $files as $name => $e ) {
			$out[ $name ] = $e['sha1'];
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                             */
	/* ------------------------------------------------------------------ */

	/** GET /operator-kit — everything a fresh machine needs. */
	public static function handle_get( $request ) {
		$skills = get_option( self::OPT_SKILLS, array() );
		$skills = is_array( $skills ) ? $skills : array();

		$template_path = CC_ASSISTANT_DIR . 'bootstrap/CLAUDE-md-template.md';
		$claude_md     = file_exists( $template_path ) ? (string) file_get_contents( $template_path ) : '';

		$host = parse_url( home_url(), PHP_URL_HOST );
		$slug = strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', is_string( $host ) ? $host : 'site' ) );
		$server_name = 'cc-assistant-' . trim( $slug, '-' );

		// v0.79: the brain is authoritative for skills when present.
		$brain        = self::load_brain( true );
		$brain_skills = array();
		if ( ! empty( $brain['files'] ) ) {
			foreach ( $brain['files'] as $path => $content ) {
				if ( 0 === strpos( $path, 'skills/' ) ) {
					$brain_skills[ $path ] = strlen( $content );
				}
			}
			$g = 'skills/design-system-global/SKILL.md';
			if ( isset( $brain['files'][ $g ] ) ) {
				$skills['global'] = $brain['files'][ $g ];
			}
			foreach ( $brain['files'] as $path => $content ) {
				if ( preg_match( '#^skills/([^/]+)-design/SKILL\.md$#', $path, $m ) && false !== strpos( $slug, $m[1] ) ) {
					$skills['site'] = $content;
				}
			}
		}

		$rest_base = rtrim( home_url(), '/' ) . '/wp-json/' . self::REST_NAMESPACE;

		return rest_ensure_response(
			array(
				'site_url'          => home_url(),
				'server_name'       => $server_name,
				'plugin_version'    => defined( 'CC_ASSISTANT_VERSION' ) ? CC_ASSISTANT_VERSION : '',
				'operator_brain'    => self::brain_summary(),
				'brain_skill_files' => $brain_skills,
				'skills'            => array(
					'global'     => isset( $skills['global'] ) ? (string) $skills['global'] : '',
					'site'       => isset( $skills['site'] ) ? (string) $skills['site'] : '',
					'updated_at' => isset( $skills['updated_at'] ) ? (string) $skills['updated_at'] : '',
				),
				'claude_md_template' => str_replace( '{SERVER_NAME}', $server_name, $claude_md ),
				'mcp_json_entry'    => array(
					'comment' => 'Add under mcpServers in the project .mcp.json; fill CC_WP_APP_PASSWORD with an Application Password from Users > Profile. On Windows + Local, command is the full php.exe path with -d extension flags (extension_dir, curl, openssl, sqlite3). brain-sync bootstrap writes this for you.',
					'entry'   => array(
						'command' => 'php',
						'args'    => array( './wp-content/plugins/cc-assistant/bin/mcp-server.php' ),
						'env'     => array(
							'CC_WP_URL'          => home_url(),
							'CC_WP_USER'         => 'admin',
							'CC_WP_APP_PASSWORD' => '{FILL_ME}',
						),
					),
				),
				'new_machine_steps' => array(
					'1. Install PHP 8.1+ with curl, openssl and sqlite3 (Local by Flywheel bundles one). Create an empty project folder and open a terminal there.',
					'2. Fetch the bridge from this site (no repo needed). PowerShell: $h=@{Authorization=\'Basic \'+[Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes(\'USER:APP PASSWORD\'));\'User-Agent\'=\'Mozilla/5.0\'}; $r=Invoke-RestMethod -Uri \'' . $rest_base . '/operator-kit/bridge\' -Headers $h; New-Item -ItemType Directory -Force wp-content/plugins/cc-assistant/bin | Out-Null; $r.files.PSObject.Properties | ForEach-Object { [IO.File]::WriteAllText((Join-Path (Get-Location) ("wp-content/plugins/cc-assistant/bin/"+$_.Name)), $_.Value.content) }',
					'3. php wp-content/plugins/cc-assistant/bin/brain-sync.php bootstrap --url ' . home_url() . ' --user USER --pass "APP PASSWORD"  — pulls the whole operator brain (memory, skills, CLAUDE.md) into the right local folders and writes .mcp.json from the stored template with this machine\'s PHP path. Other sites\' passwords stay {FILL_ME} until you add them.',
					'4. Start Claude Code in that folder and call whoami. operator_brain.status must read in_sync and bridge.status in_sync. If not, the response says exactly which pull to run.',
					'5. gsc_warehouse_sync rebuilds the local GSC archive (re-backfills ~16 months).',
					'Keeping machines equal: after editing memory or skills on any laptop run  php bin/brain-sync.php push  (all sites in .mcp.json). Every whoami on every site then tells any other laptop it is behind.',
				),
			)
		);
	}

	/** POST /operator-kit/skills {global?, site?} — legacy direct save, no queue. */
	public static function handle_push_skills( $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$skills = get_option( self::OPT_SKILLS, array() );
		$skills = is_array( $skills ) ? $skills : array();
		$saved  = array();

		foreach ( array( 'global', 'site' ) as $key ) {
			if ( isset( $params[ $key ] ) && is_string( $params[ $key ] ) && '' !== trim( $params[ $key ] ) ) {
				if ( strlen( $params[ $key ] ) > 200000 ) {
					return new WP_Error( 'skill_too_large', 'Skill "' . $key . '" exceeds 200KB.', array( 'status' => 422 ) );
				}
				$skills[ $key ] = (string) $params[ $key ];
				$saved[]        = $key;
			}
		}
		if ( empty( $saved ) ) {
			return new WP_Error( 'nothing_to_save', 'Pass "global" and/or "site" as non-empty strings.', array( 'status' => 400 ) );
		}
		$skills['updated_at'] = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		update_option( self::OPT_SKILLS, $skills, false );

		return rest_ensure_response(
			array(
				'saved'      => $saved,
				'sizes'      => array(
					'global' => isset( $skills['global'] ) ? strlen( $skills['global'] ) : 0,
					'site'   => isset( $skills['site'] ) ? strlen( $skills['site'] ) : 0,
				),
				'updated_at' => $skills['updated_at'],
				'note'       => 'Legacy store. v0.79 keeps the whole brain (all skills + memory) via /operator-kit/brain; push with bin/brain-sync.php.',
			)
		);
	}

	/** GET /operator-kit/brain/status — summary + index (no bodies). */
	public static function handle_brain_status( $request ) {
		$rec = self::load_brain( false );
		$out = self::summary_from_record( $rec );
		$out['index']  = ! empty( $rec['index'] ) ? $rec['index'] : array();
		$out['bridge'] = self::bridge_files( false );
		$out['write_concurrency'] = 'lock_and_expected_fingerprint_required';
		$out['limits'] = array(
			'max_file_bytes'  => self::MAX_FILE_BYTES,
			'max_total_bytes' => self::MAX_TOTAL_BYTES,
			'max_files'       => self::MAX_FILES,
		);
		return rest_ensure_response( $out );
	}

	/** GET /operator-kit/brain[?prefix=memory/][&paths=a,b] — files with bodies. */
	public static function handle_brain_get( $request ) {
		$rec = self::load_brain( true );
		$out = self::summary_from_record( $rec );
		$files = ! empty( $rec['files'] ) ? $rec['files'] : array();

		$prefix = (string) $request->get_param( 'prefix' );
		$paths  = (string) $request->get_param( 'paths' );
		if ( '' !== $prefix ) {
			$files = array_filter( $files, function ( $k ) use ( $prefix ) {
				return 0 === strpos( $k, $prefix );
			}, ARRAY_FILTER_USE_KEY );
		}
		if ( '' !== $paths ) {
			$want  = array_flip( array_filter( array_map( 'trim', explode( ',', $paths ) ) ) );
			$files = array_intersect_key( $files, $want );
		}
		$out['files']          = $files;
		$out['returned_files'] = count( $files );
		return rest_ensure_response( $out );
	}

	/**
	 * POST /operator-kit/brain
	 *   { mode: "replace", files: {path: content}, pushed_from }
	 *   { mode: "merge",   files: {path: content}, delete: [path], pushed_from }
	 * merge lets a laptop send only what changed (index diff) and remove what
	 * it no longer has; the fingerprint is recomputed from the stored set, so
	 * a merge that converges on the local file set converges on its hash.
	 */
	public static function handle_brain_post( $request ) {
		$p = $request->get_json_params();
		$p = is_array( $p ) ? $p : array();
		if ( ! array_key_exists( 'expected_fingerprint', $p ) || ! is_string( $p['expected_fingerprint'] ) || ! preg_match( '/^(?:[a-f0-9]{64})?$/', $p['expected_fingerprint'] ) ) {
			return new WP_Error( 'brain_basis_required', 'Read operator-brain status and supply its expected_fingerprint. Update older bridges before pushing.', array( 'status' => 409 ) );
		}
		global $wpdb;
		$lock = 'cc_brain_' . substr( hash( 'sha256', home_url() ), 0, 48 );
		if ( ! $wpdb || '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return new WP_Error( 'brain_store_busy', 'Another shared-brain update is in progress, or the lock is unavailable. Re-read before retrying.', array( 'status' => 409 ) );
		}
		try {
		if ( function_exists( 'wp_cache_delete' ) ) { wp_cache_delete( self::OPT_BRAIN, 'options' ); }
		$rec = self::load_brain( true );
		if ( ! empty( $rec['integrity_error'] ) ) { return new WP_Error( 'brain_storage_corrupt', 'Stored brain content cannot be verified. Restore a reviewed backup; an update must not erase unreadable data.', array( 'status' => 409 ) ); }
		if ( ( $rec['fingerprint'] ?? '' ) !== $p['expected_fingerprint'] ) {
			return new WP_Error( 'brain_fingerprint_changed', 'The shared brain changed after it was read. Re-read and reconcile the selected files before retrying.', array( 'status' => 409 ) );
		}

		$mode  = isset( $p['mode'] ) && 'merge' === $p['mode'] ? 'merge' : 'replace';
		$files = isset( $p['files'] ) && is_array( $p['files'] ) ? $p['files'] : array();
		$del   = isset( $p['delete'] ) && is_array( $p['delete'] ) ? $p['delete'] : array();
		$from  = isset( $p['pushed_from'] ) ? (string) $p['pushed_from'] : '';

		if ( 'replace' === $mode && empty( $files ) ) {
			return new WP_Error( 'empty_brain', 'Refusing to replace the brain with an empty set. Use mode=merge with delete[] to remove files.', array( 'status' => 400 ) );
		}
		if ( 'merge' === $mode ) {
			$existing = ! empty( $rec['files'] ) ? $rec['files'] : array();
			foreach ( $del as $d ) {
				unset( $existing[ (string) $d ] );
			}
			foreach ( $files as $path => $content ) {
				$existing[ (string) $path ] = $content;
			}
			$files = $existing;
			if ( empty( $files ) ) {
				return new WP_Error( 'empty_brain', 'Merge would leave the brain empty; refused.', array( 'status' => 400 ) );
			}
		}
		$result = self::save_brain( $files, $from );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['mode']          = $mode;
		$result['written_files'] = count( isset( $p['files'] ) && is_array( $p['files'] ) ? $p['files'] : array() );
		$result['deleted_files'] = count( $del );
		return rest_ensure_response( $result );
		} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}

	/** GET /operator-kit/bridge — the bin/ files this site ships, with bodies. */
	public static function handle_bridge_get( $request ) {
		$files = self::bridge_files( true );
		return rest_ensure_response(
			array(
				'plugin_version' => defined( 'CC_ASSISTANT_VERSION' ) ? CC_ASSISTANT_VERSION : '',
				'file_count'     => count( $files ),
				'files'          => $files,
				'install_to'     => 'wp-content/plugins/cc-assistant/bin/ inside the project folder; restart the MCP server afterwards.',
			)
		);
	}
}

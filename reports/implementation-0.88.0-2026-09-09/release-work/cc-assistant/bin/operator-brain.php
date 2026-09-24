<?php
/**
 * Operator Brain (v0.79) — bridge side.
 *
 * The problem this solves: a second laptop opened a new chat and "a lot of
 * functions were missing", and the chat knew nothing of the rules learned on
 * the first laptop. Both symptoms have the same cause: the operator knowledge
 * (234 memory files, 7 skills, CLAUDE.md, the .mcp.json shape) and the bridge
 * itself (this bin/ folder) lived on ONE disk with nothing that detected drift.
 *
 * The site is now the store (see includes/class-rest-operator-kit.php). This
 * file is the laptop half: it collects the local brain, fingerprints it with
 * the SAME formula the server uses, diffs the two, writes site files back to
 * the right local folders, and — because whoami is the first call every
 * session makes — decorates whoami with a verdict a new chat cannot miss:
 * in_sync, local_missing, local_newer, site_newer, plus whether this very
 * bridge build matches the one the site ships.
 *
 * Loadable standalone (tests, brain-sync.php): no bridge globals at load time.
 *
 * @package CC_Assistant
 */

/* ---------------------------------------------------------------------- */
/* Paths                                                                    */
/* ---------------------------------------------------------------------- */

function cc_brain_home() {
	$env = getenv( 'CC_BRAIN_HOME' );
	if ( is_string( $env ) && '' !== $env ) {
		return rtrim( $env, '/\\' );
	}
	foreach ( array( 'USERPROFILE', 'HOME' ) as $k ) {
		$v = getenv( $k );
		if ( is_string( $v ) && '' !== $v ) {
			return rtrim( $v, '/\\' );
		}
	}
	return '';
}

function cc_brain_project_dir() {
	$env = getenv( 'CC_PROJECT_DIR' );
	if ( is_string( $env ) && '' !== $env ) {
		return rtrim( $env, '/\\' );
	}
	$cwd = getcwd();
	return is_string( $cwd ) ? rtrim( $cwd, '/\\' ) : '.';
}

/**
 * Claude Code keys its per-project memory folder by the project path with
 * every non-alphanumeric character turned into "-". Observed on disk:
 *   c:\Users\sumit\Local Sites\plugintesting\app\public
 *     -> c--Users-sumit-Local-Sites-plugintesting-app-public
 *   d:\Best video generator -> d--Best-video-generator
 * The drive letter is lower-case; the rest keeps its case.
 */
function cc_brain_project_key( $dir ) {
	$dir = (string) $dir;
	if ( preg_match( '/^[A-Za-z]:/', $dir ) ) {
		$dir = strtolower( $dir[0] ) . substr( $dir, 1 );
	}
	return preg_replace( '/[^A-Za-z0-9]/', '-', $dir );
}

/**
 * Memory folder for a project. If the exact key is absent but a folder
 * differing only in case exists (Windows), use that one rather than making
 * a twin Claude Code would never read.
 */
/**
 * v0.81.1: Claude Code's autoMemoryDirectory setting (project
 * .claude/settings.local.json, then user ~/.claude/settings.json; the
 * checked-in project settings.json is ignored by Claude Code for this key)
 * moves the memory folder INTO the workspace. Honour it so collect/pull and
 * the harness gate read the same folder Claude Code writes.
 */
function cc_brain_memory_override( $home, $project_dir ) {
	foreach ( array( $project_dir . '/.claude/settings.local.json', $home . '/.claude/settings.json' ) as $f ) {
		if ( ! is_file( $f ) ) {
			continue;
		}
		$cfg = json_decode( (string) file_get_contents( $f ), true );
		if ( is_array( $cfg ) && ! empty( $cfg['autoMemoryDirectory'] ) && is_string( $cfg['autoMemoryDirectory'] ) ) {
			$p = str_replace( '\\', '/', $cfg['autoMemoryDirectory'] );
			if ( 0 === strpos( $p, '~/' ) ) {
				$p = $home . substr( $p, 1 );
			}
			return rtrim( $p, '/' );
		}
	}
	return '';
}

function cc_brain_memory_dir( $home, $project_dir ) {
	$override = cc_brain_memory_override( $home, $project_dir );
	if ( '' !== $override ) {
		return $override;
	}
	$projects = $home . '/.claude/projects';
	$key      = cc_brain_project_key( $project_dir );
	$exact    = $projects . '/' . $key . '/memory';
	if ( is_dir( $exact ) ) {
		return $exact;
	}
	if ( is_dir( $projects ) ) {
		foreach ( scandir( $projects ) as $d ) {
			if ( '.' !== $d && '..' !== $d && 0 === strcasecmp( $d, $key ) && is_dir( $projects . '/' . $d ) ) {
				return $projects . '/' . $d . '/memory';
			}
		}
	}
	return $exact;
}

function cc_brain_skills_dir( $home ) {
	return $home . '/.claude/skills';
}

/* ---------------------------------------------------------------------- */
/* Fingerprint (identical to CC_Assistant_REST_Operator_Kit)               */
/* ---------------------------------------------------------------------- */

function cc_brain_fingerprint( array $index ) {
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

function cc_brain_index_from_files( array $files ) {
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

/** Same rule as the server: relative, forward-slash, one of three roots. */
function cc_brain_valid_path( $path ) {
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

/* ---------------------------------------------------------------------- */
/* Collect the local brain                                                 */
/* ---------------------------------------------------------------------- */

/** Text-ish files only; a skill folder may hold reference images we must not ship. */
function cc_brain_is_text_file( $name ) {
	$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
	$ok  = array( 'md', 'txt', 'json', 'yml', 'yaml', 'csv', 'py', 'js', 'mjs', 'ts', 'sh', 'ps1', 'php', 'html', 'css', 'xml', 'toml', 'ini', 'cfg' );
	return in_array( $ext, $ok, true );
}

function cc_brain_scan_dir( $dir, $prefix, $recursive, &$files, &$newest, $max_bytes = 400000 ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $entry ) {
		if ( '.' === $entry || '..' === $entry || '.git' === $entry || 'node_modules' === $entry || '__pycache__' === $entry ) {
			continue;
		}
		$abs = $dir . '/' . $entry;
		if ( is_dir( $abs ) ) {
			if ( $recursive ) {
				cc_brain_scan_dir( $abs, $prefix . $entry . '/', true, $files, $newest, $max_bytes );
			}
			continue;
		}
		if ( ! cc_brain_is_text_file( $entry ) ) {
			continue;
		}
		$size = filesize( $abs );
		if ( false === $size || $size > $max_bytes ) {
			continue;
		}
		$rel = $prefix . $entry;
		if ( ! cc_brain_valid_path( $rel ) ) {
			continue;
		}
		$files[ $rel ] = (string) file_get_contents( $abs );
		$mt = filemtime( $abs );
		if ( $mt && $mt > $newest ) {
			$newest = $mt;
		}
	}
}

/**
 * Turn the live .mcp.json into a template safe to store on a site: every
 * credential-shaped env value becomes {FILL_ME}, the PHP binary becomes
 * {PHP} and its extension dir {PHP_EXT_DIR}. Other servers (http type) keep
 * their shape with the same credential scrub.
 */
function cc_brain_mcp_template( $json_text ) {
	$cfg = json_decode( (string) $json_text, true );
	if ( ! is_array( $cfg ) || ! isset( $cfg['mcpServers'] ) || ! is_array( $cfg['mcpServers'] ) ) {
		return '';
	}
	foreach ( $cfg['mcpServers'] as $name => &$srv ) {
		if ( ! is_array( $srv ) ) {
			continue;
		}
		if ( isset( $srv['command'] ) && is_string( $srv['command'] ) && preg_match( '/php(\.exe)?$/i', $srv['command'] ) ) {
			$srv['command'] = '{PHP}';
		}
		if ( isset( $srv['args'] ) && is_array( $srv['args'] ) ) {
			foreach ( $srv['args'] as &$a ) {
				if ( is_string( $a ) && 0 === strpos( $a, 'extension_dir=' ) ) {
					$a = 'extension_dir={PHP_EXT_DIR}';
				}
			}
			unset( $a );
		}
		if ( isset( $srv['env'] ) && is_array( $srv['env'] ) ) {
			foreach ( $srv['env'] as $k => &$v ) {
				if ( preg_match( '/pass|token|secret|key|auth/i', (string) $k ) ) {
					$v = '{FILL_ME}';
				}
			}
			unset( $v );
		}
		foreach ( array( 'headers' ) as $hk ) {
			if ( isset( $srv[ $hk ] ) && is_array( $srv[ $hk ] ) ) {
				foreach ( $srv[ $hk ] as $k => &$v ) {
					if ( preg_match( /** @lang RegExp */ '/authorization|token|key|secret/i', (string) $k ) ) {
						$v = '{FILL_ME}';
					}
				}
				unset( $v );
			}
		}
	}
	unset( $srv );
	return json_encode( $cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}

/**
 * Render a stored template into a working .mcp.json for THIS machine.
 * $passwords: CC_WP_URL (normalised, no trailing slash) => app password.
 * Servers whose password is unknown keep {FILL_ME} so the file is honest.
 */
function cc_brain_render_mcp_template( $template_text, $php_binary, array $passwords = array() ) {
	$cfg = json_decode( (string) $template_text, true );
	if ( ! is_array( $cfg ) || empty( $cfg['mcpServers'] ) ) {
		return '';
	}
	$php     = str_replace( '\\', '/', (string) $php_binary );
	$ext_dir = dirname( $php ) . '/ext';
	$norm    = array();
	foreach ( $passwords as $u => $p ) {
		$norm[ rtrim( strtolower( (string) $u ), '/' ) ] = (string) $p;
	}
	foreach ( $cfg['mcpServers'] as &$srv ) {
		if ( ! is_array( $srv ) ) {
			continue;
		}
		if ( isset( $srv['command'] ) && '{PHP}' === $srv['command'] ) {
			$srv['command'] = $php;
		}
		if ( isset( $srv['args'] ) && is_array( $srv['args'] ) ) {
			$keep = array();
			$skip_next = false;
			foreach ( $srv['args'] as $i => $a ) {
				if ( $skip_next ) {
					$skip_next = false;
					continue;
				}
				if ( 'extension_dir={PHP_EXT_DIR}' === $a ) {
					if ( is_dir( $ext_dir ) ) {
						$keep[] = 'extension_dir=' . $ext_dir;
					} else {
						// No bundled ext dir on this machine: drop "-d extension_dir=..." (the -d before it).
						array_pop( $keep );
					}
					continue;
				}
				$keep[] = $a;
			}
			$srv['args'] = array_values( $keep );
		}
		if ( isset( $srv['env']['CC_WP_URL'] ) ) {
			$u = rtrim( strtolower( (string) $srv['env']['CC_WP_URL'] ), '/' );
			if ( isset( $norm[ $u ] ) ) {
				$srv['env']['CC_WP_APP_PASSWORD'] = $norm[ $u ];
			}
		}
	}
	unset( $srv );
	return json_encode( $cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}

/**
 * Everything on this machine that belongs in the brain.
 * Returns files (path => content), index, fingerprint, newest mtime, and the
 * source paths so a status report can say WHICH folder was empty.
 */
function cc_brain_collect_local( $project_dir = null, $home = null ) {
	$project_dir = null === $project_dir ? cc_brain_project_dir() : $project_dir;
	$home        = null === $home ? cc_brain_home() : $home;

	$files  = array();
	$newest = 0;
	$sources = array(
		'memory_dir' => cc_brain_memory_dir( $home, $project_dir ),
		'skills_dir' => cc_brain_skills_dir( $home ),
		'claude_md'  => $project_dir . '/CLAUDE.md',
		'mcp_json'   => $project_dir . '/.mcp.json',
	);
	$present = array();

	cc_brain_scan_dir( $sources['memory_dir'], 'memory/', false, $files, $newest );
	$present['memory'] = count( array_filter( array_keys( $files ), function ( $k ) { return 0 === strpos( $k, 'memory/' ); } ) );

	$before = count( $files );
	cc_brain_scan_dir( $sources['skills_dir'], 'skills/', true, $files, $newest );
	$present['skills'] = count( $files ) - $before;

	if ( is_file( $sources['claude_md'] ) ) {
		$files['project/CLAUDE.md'] = (string) file_get_contents( $sources['claude_md'] );
		$mt = filemtime( $sources['claude_md'] );
		if ( $mt && $mt > $newest ) {
			$newest = $mt;
		}
	}
	if ( is_file( $sources['mcp_json'] ) ) {
		$tpl = cc_brain_mcp_template( file_get_contents( $sources['mcp_json'] ) );
		if ( '' !== $tpl ) {
			$files['project/mcp-template.json'] = $tpl;
		}
	}
	// v0.81: the harness enforcement travels with the brain — project hooks
	// and the shared project settings (never settings.local.json: personal).
	$claude_dir = $project_dir . '/.claude';
	if ( is_file( $claude_dir . '/settings.json' ) ) {
		$files['project/.claude/settings.json'] = (string) file_get_contents( $claude_dir . '/settings.json' );
		$mt = filemtime( $claude_dir . '/settings.json' );
		if ( $mt && $mt > $newest ) {
			$newest = $mt;
		}
	}
	cc_brain_scan_dir( $claude_dir . '/hooks', 'project/.claude/hooks/', true, $files, $newest );
	// v0.81.1: project-scoped skills (the workspace layout keeps them in-folder).
	cc_brain_scan_dir( $claude_dir . '/skills', 'project/.claude/skills/', true, $files, $newest );
	$present['project'] = count( array_filter( array_keys( $files ), function ( $k ) { return 0 === strpos( $k, 'project/' ); } ) );

	ksort( $files, SORT_STRING );
	$index = cc_brain_index_from_files( $files );
	return array(
		'files'        => $files,
		'index'        => $index,
		'fingerprint'  => cc_brain_fingerprint( $index ),
		'file_count'   => count( $files ),
		'bytes'        => array_sum( array_map( 'strlen', $files ) ),
		'newest_mtime' => $newest,
		'newest_utc'   => $newest ? gmdate( 'Y-m-d H:i:s', $newest ) . ' UTC' : '',
		'sources'      => $sources,
		'present'      => $present,
		'project_dir'  => $project_dir,
		'project_key'  => cc_brain_project_key( $project_dir ),
	);
}

/* ---------------------------------------------------------------------- */
/* Compare                                                                  */
/* ---------------------------------------------------------------------- */

function cc_brain_diff( array $local_index, array $site_index ) {
	$only_local = array_values( array_diff( array_keys( $local_index ), array_keys( $site_index ) ) );
	$only_site  = array_values( array_diff( array_keys( $site_index ), array_keys( $local_index ) ) );
	$differ     = array();
	$same       = 0;
	foreach ( $local_index as $p => $m ) {
		if ( isset( $site_index[ $p ] ) ) {
			$ls = is_array( $m ) ? $m['sha1'] : $m;
			$ss = is_array( $site_index[ $p ] ) ? $site_index[ $p ]['sha1'] : $site_index[ $p ];
			if ( $ls === $ss ) {
				$same++;
			} else {
				$differ[] = $p;
			}
		}
	}
	sort( $only_local, SORT_STRING );
	sort( $only_site, SORT_STRING );
	sort( $differ, SORT_STRING );
	return array(
		'same'       => $same,
		'only_local' => $only_local,
		'only_site'  => $only_site,
		'differ'     => $differ,
	);
}

/**
 * The verdict a new chat reads. $site is the server's brain summary
 * (present, fingerprint, updated_at ...), $site_index its index (may be
 * empty when only the summary is known).
 */
function cc_brain_classify( array $local, array $site, array $site_index = array() ) {
	$local_has = $local['file_count'] > 0;
	$site_has  = ! empty( $site['present'] );
	$out = array(
		'local_fingerprint' => $local['fingerprint'],
		'site_fingerprint'  => $site_has ? (string) $site['fingerprint'] : '',
		'local_files'       => $local['file_count'],
		'site_files'        => $site_has ? (int) $site['file_count'] : 0,
		'local_newest'      => $local['newest_utc'],
		'site_updated_at'   => $site_has ? (string) $site['updated_at'] : '',
		'site_pushed_from'  => $site_has ? (string) $site['pushed_from'] : '',
		'local_present'     => $local['present'],
	);

	if ( ! $local_has && ! $site_has ) {
		$out['status'] = 'both_empty';
		$out['action'] = 'No brain anywhere yet. On the laptop that has ~/.claude memory and skills run: php bin/brain-sync.php push';
		return $out;
	}
	if ( ! $local_has ) {
		$out['status'] = 'local_missing';
		$out['action'] = 'This machine has NO operator memory or skills. Run operator_brain_pull (or php bin/brain-sync.php pull) BEFORE any work; the rules learned on other machines live on this site.';
		return $out;
	}
	if ( ! $site_has ) {
		$out['status'] = 'site_empty';
		$out['action'] = 'This site holds no brain yet. Review the relevant local files and use operator_brain_push(paths=[exact site-relevant paths]) to seed a curated snapshot for other laptops.';
		return $out;
	}
	if ( $local['fingerprint'] === $site['fingerprint'] ) {
		$out['status'] = 'in_sync';
		$out['action'] = 'none';
		return $out;
	}
	if ( ! empty( $site_index ) ) {
		$d = cc_brain_diff( $local['index'], $site_index );
		$out['diff'] = array(
			'same'       => $d['same'],
			'only_local' => count( $d['only_local'] ),
			'only_site'  => count( $d['only_site'] ),
			'differ'     => count( $d['differ'] ),
			'sample'     => array(
				'only_local' => array_slice( $d['only_local'], 0, 15 ),
				'only_site'  => array_slice( $d['only_site'], 0, 15 ),
				'differ'     => array_slice( $d['differ'], 0, 15 ),
			),
		);
	}
	$site_ts  = strtotime( str_replace( ' UTC', '', (string) $site['updated_at'] ) . ' UTC' );
	$local_ts = (int) $local['newest_mtime'];
	if ( $site_ts && $local_ts > $site_ts ) {
		$out['status'] = 'local_newer';
		$out['action'] = 'Local memory/skills changed after the last push (' . $local['newest_utc'] . ' > ' . $site['updated_at'] . '). Review file scope. Prefer operator_brain_push(paths=[exact site-relevant paths]); a curated site snapshot need not equal every local client file.';
	} else {
		$out['status'] = 'site_newer';
		$out['action'] = 'The site brain was pushed at ' . $site['updated_at'] . ' from "' . $site['pushed_from'] . '" and differs from this machine. A timestamp does not prove its instructions are more correct. Inspect the selected file differences; operator_brain_pull(mode=missing_only) can add missing files. Preserve a deliberately curated site scope.';
	}
	return $out;
}

/** Compare the bin/ folder this bridge runs from against the hashes the site ships. */
function cc_brain_bridge_check( array $site_hashes, $bin_dir = null ) {
	$bin_dir = null === $bin_dir ? __DIR__ : $bin_dir;
	$local   = array();
	foreach ( array( 'browser-transport.mjs', 'browser/package.json' ) as $name ) {
		$path = rtrim( $bin_dir, '/\\' ) . '/' . $name;
		if ( is_file( $path ) ) { $local[ $name ] = sha1( (string) file_get_contents( $path ) ); }
	}
	foreach ( glob( rtrim( $bin_dir, '/\\' ) . '/*.php' ) as $abs ) {
		$local[ basename( $abs ) ] = sha1( (string) file_get_contents( $abs ) );
	}
	if ( empty( $site_hashes ) ) {
		return array( 'status' => 'unknown', 'note' => 'Site did not report bridge hashes (plugin older than 0.79).', 'local_files' => count( $local ) );
	}
	$differ    = array();
	$only_site = array();
	foreach ( $site_hashes as $name => $sha ) {
		if ( ! isset( $local[ $name ] ) ) {
			$only_site[] = $name;
		} elseif ( $local[ $name ] !== $sha ) {
			$differ[] = $name;
		}
	}
	$only_local = array_values( array_diff( array_keys( $local ), array_keys( $site_hashes ) ) );
	sort( $differ ); sort( $only_site ); sort( $only_local );
	$in_sync = empty( $differ ) && empty( $only_site );
	return array(
		'status'     => $in_sync ? 'in_sync' : 'differs',
		'differ'     => $differ,
		'only_site'  => $only_site,
		'only_local' => $only_local,
	);
}

/* ---------------------------------------------------------------------- */
/* Relevant rules: push the rules to the chat instead of hoping it recalls */
/* ---------------------------------------------------------------------- */

function cc_brain_site_tokens( $host, $site_name = '' ) {
	$host = strtolower( (string) $host );
	$host = preg_replace( '/^www\./', '', $host );
	$tokens = array();
	$labels = explode( '.', $host );
	if ( ! empty( $labels[0] ) ) {
		$first = $labels[0];
		$tokens[] = $first;
		$tokens[] = str_replace( '-', '_', $first );
		$tokens[] = str_replace( '-', ' ', $first );
		$tokens[] = str_replace( '-', '', $first );
	}
	// get_bloginfo('name') arrives entity-encoded ("Sid&#039;s Ponds"); decode
	// before matching or the token never appears in any memory file.
	$name = strtolower( trim( html_entity_decode( (string) $site_name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	if ( strlen( $name ) >= 4 ) {
		$tokens[] = $name;
	}
	return array_values( array_unique( array_filter( $tokens, function ( $t ) { return strlen( $t ) >= 4; } ) ) );
}

/** Parse the frontmatter name/description and the body of one memory file. */
function cc_brain_parse_memory( $text ) {
	$text = (string) $text;
	$name = '';
	$desc = '';
	$body = $text;
	if ( preg_match( '/\A---\R(.*?)\R---\R?(.*)\z/s', $text, $m ) ) {
		$fm   = $m[1];
		$body = $m[2];
		if ( preg_match( '/^name:\s*(.+)$/m', $fm, $n ) ) {
			$name = trim( $n[1], " \t\"'" );
		}
		if ( preg_match( '/^description:\s*(.+)$/m', $fm, $d ) ) {
			$desc = trim( $d[1], " \t\"'" );
		}
	}
	return array( 'name' => $name, 'description' => $desc, 'body' => $body );
}

/**
 * Rules that apply to THIS site and task. Two selectors, both mechanical:
 * hard rules (description flagged HARD/STRICT) and site matches (the site's
 * host token appears in the description or body). Names + descriptions
 * only; bodies stay on disk. Cap keeps whoami small.
 */
function cc_brain_relevant_rules( $memory_dir, $host, $site_name = '', $cap = 30 ) {
	$out = array( 'memory_dir' => $memory_dir, 'memory_present' => is_dir( $memory_dir ), 'hard_rules' => array(), 'site_rules' => array(), 'total_memories' => 0 );
	if ( ! is_dir( $memory_dir ) ) {
		$out['note'] = 'No memory folder on this machine for this project. operator_brain_pull restores it from the site.';
		return $out;
	}
	$tokens = cc_brain_site_tokens( $host, $site_name );
	$hard   = array();
	$site   = array();
	foreach ( scandir( $memory_dir ) as $f ) {
		if ( 'MEMORY.md' === $f || substr( $f, -3 ) !== '.md' ) {
			continue;
		}
		$out['total_memories']++;
		$p = cc_brain_parse_memory( file_get_contents( $memory_dir . '/' . $f ) );
		$label = '' !== $p['name'] ? $p['name'] : substr( $f, 0, -3 );
		$row   = array( 'name' => $label, 'description' => $p['description'] );
		if ( preg_match( '/\b(HARD|STRICT)\b/', $p['description'] ) ) {
			$hard[] = $row;
			continue;
		}
		$hay = strtolower( $p['description'] . "\n" . $p['body'] );
		foreach ( $tokens as $t ) {
			if ( false !== strpos( $hay, $t ) ) {
				$site[] = $row;
				break;
			}
		}
	}
	usort( $hard, function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
	usort( $site, function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
	$out['tokens']     = $tokens;
	$out['hard_rules'] = array_slice( $hard, 0, $cap );
	$out['site_rules'] = array_slice( $site, 0, $cap );
	$out['hard_total'] = count( $hard );
	$out['site_total'] = count( $site );
	return $out;
}

/* ---------------------------------------------------------------------- */
/* Write site files back to disk                                            */
/* ---------------------------------------------------------------------- */

/** Where a brain path lands on this machine. Null when refused. */
function cc_brain_local_target( $rel, $project_dir, $home ) {
	if ( ! cc_brain_valid_path( $rel ) ) {
		return null;
	}
	if ( 0 === strpos( $rel, 'skills/' ) ) {
		return cc_brain_skills_dir( $home ) . '/' . substr( $rel, 7 );
	}
	if ( 0 === strpos( $rel, 'memory/' ) ) {
		return cc_brain_memory_dir( $home, $project_dir ) . '/' . substr( $rel, 7 );
	}
	if ( 'project/CLAUDE.md' === $rel ) {
		return $project_dir . '/CLAUDE.md';
	}
	if ( 'project/mcp-template.json' === $rel ) {
		// Never the live .mcp.json: that holds passwords this file does not.
		return $project_dir . '/.mcp.template.json';
	}
	// v0.81: project hooks + shared settings. settings.local.json stays personal.
	if ( 'project/.claude/settings.json' === $rel || 0 === strpos( $rel, 'project/.claude/hooks/' ) || 0 === strpos( $rel, 'project/.claude/skills/' ) ) {
		return $project_dir . '/' . substr( $rel, 8 );
	}
	return null;
}

/**
 * $mode: missing_only (default; never touch a file that exists locally) or
 * replace (overwrite when content differs). Never deletes.
 */
function cc_brain_write_files( array $files, $project_dir, $home, $mode = 'missing_only' ) {
	$written = array();
	$skipped = array();
	$refused = array();
	$errors  = array();
	foreach ( $files as $rel => $content ) {
		$target = cc_brain_local_target( $rel, $project_dir, $home );
		if ( null === $target || ! is_string( $content ) ) {
			$refused[] = $rel;
			continue;
		}
		if ( is_file( $target ) ) {
			if ( 'replace' !== $mode ) {
				$skipped[] = $rel;
				continue;
			}
			if ( (string) file_get_contents( $target ) === $content ) {
				$skipped[] = $rel;
				continue;
			}
		}
		$dir = dirname( $target );
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0777, true ) ) {
			$errors[] = $rel . ' (mkdir failed: ' . $dir . ')';
			continue;
		}
		if ( false === @file_put_contents( $target, $content ) ) {
			$errors[] = $rel . ' (write failed: ' . $target . ')';
			continue;
		}
		$written[] = $rel;
	}
	return array( 'written' => $written, 'skipped' => $skipped, 'refused' => $refused, 'errors' => $errors, 'mode' => $mode );
}

/* ---------------------------------------------------------------------- */
/* HTTP (standalone; the MCP bridge passes its own caller instead)          */
/* ---------------------------------------------------------------------- */

function cc_brain_http( $url, $user, $pass, $method = 'GET', $body = null ) {
	$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
	$json = null === $body ? null : json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_USERPWD, $user . ':' . $pass );
		curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_USERAGENT, $ua );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		if ( preg_match( '/(\.local|\.test|localhost)$/', $host ) ) {
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		} else {
			// Same rule as the bridge (v0.60.1): verify, and when no CA bundle is
			// configured use the Windows certificate store. The standalone PHP
			// in the workspace ships no php.ini, so without this every HTTPS
			// push failed with "unable to get local issuer certificate".
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
			if ( defined( 'CURLSSLOPT_NATIVE_CA' ) && '' === (string) ini_get( 'curl.cainfo' ) ) {
				curl_setopt( $ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA );
			}
		}
		$headers = array( 'Accept: application/json' );
		if ( null !== $json ) {
			$headers[] = 'Content-Type: application/json';
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $json );
		}
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		$raw  = curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$err  = curl_error( $ch );
		curl_close( $ch );
		if ( false === $raw ) {
			return array( 'error' => 'curl_error', 'message' => $err );
		}
	} else {
		$opts = array(
			'http' => array(
				'method'        => $method,
				'header'        => "Authorization: Basic " . base64_encode( $user . ':' . $pass ) . "\r\nUser-Agent: $ua\r\nAccept: application/json\r\n" . ( null !== $json ? "Content-Type: application/json\r\n" : '' ),
				'ignore_errors' => true,
				'timeout'       => 120,
			),
		);
		if ( null !== $json ) {
			$opts['http']['content'] = $json;
		}
		$raw  = @file_get_contents( $url, false, stream_context_create( $opts ) );
		$code = 0;
		if ( isset( $http_response_header[0] ) && preg_match( '/\s(\d{3})\s/', $http_response_header[0], $m ) ) {
			$code = (int) $m[1];
		}
		if ( false === $raw ) {
			return array( 'error' => 'http_error', 'message' => 'request failed' );
		}
	}
	$data = json_decode( (string) $raw, true );
	if ( $code >= 400 ) {
		return array( 'error' => 'http_error', 'status' => $code, 'body' => is_array( $data ) ? $data : substr( (string) $raw, 0, 500 ) );
	}
	if ( ! is_array( $data ) ) {
		return array( 'error' => 'bad_json', 'status' => $code, 'body' => substr( (string) $raw, 0, 500 ) );
	}
	return $data;
}

/* ---------------------------------------------------------------------- */
/* Tool bodies. $rest($endpoint, $method, $body, $params) -> decoded array  */
/* ---------------------------------------------------------------------- */

function cc_brain_tool_status( callable $rest, $project_dir = null, $home = null ) {
	$site = $rest( '/operator-kit/brain/status', 'GET', null, array() );
	if ( ! is_array( $site ) || isset( $site['error'] ) ) {
		return array( 'error' => 'site_unreachable', 'detail' => $site, 'hint' => 'If the error is a 404, this site runs a plugin older than 0.79: upload the new zip first.' );
	}
	$local  = cc_brain_collect_local( $project_dir, $home );
	$out    = cc_brain_classify( $local, $site, isset( $site['index'] ) && is_array( $site['index'] ) ? $site['index'] : array() );
	$hashes = array();
	if ( isset( $site['bridge'] ) && is_array( $site['bridge'] ) ) {
		foreach ( $site['bridge'] as $n => $e ) {
			$hashes[ $n ] = is_array( $e ) ? $e['sha1'] : $e;
		}
	}
	$out['bridge']  = cc_brain_bridge_check( $hashes );
	$out['sources'] = $local['sources'];
	return $out;
}

/**
 * Push the local brain to the connected site. Sends only what the index diff
 * says is new or changed, deletes what the laptop no longer has, in chunks
 * under ~900 KB so no single request trips a host limit. Verifies by
 * fingerprint afterwards; a push that does not converge is reported, not
 * assumed.
 */
function cc_brain_tool_push( callable $rest, $project_dir = null, $home = null, $chunk_bytes = 900000, $paths = null ) {
	$local = cc_brain_collect_local( $project_dir, $home );
	$scoped = null !== $paths;
	if ( $scoped ) {
		if ( ! is_array( $paths ) || empty( $paths ) || count( $paths ) > 50 ) { return array( 'error' => 'scope_paths_required', 'message' => 'Select one to fifty exact collected paths. An empty selection never becomes a full sync.' ); }
		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) || ! isset( $local['files'][$path] ) ) { return array( 'error' => 'scope_path_unavailable', 'message' => 'A selected path is not in the collected local brain; no files were sent.' ); }
		}
		$local['files'] = array_intersect_key( $local['files'], array_flip( $paths ) );
		$local['index'] = cc_brain_index_from_files( $local['files'] );
		$local['fingerprint'] = cc_brain_fingerprint( $local['index'] );
		$local['file_count'] = count( $local['files'] );
	}
	if ( 0 === $local['file_count'] ) {
		return array( 'error' => 'local_empty', 'message' => 'Nothing to push: no memory, skills or CLAUDE.md found on this machine.', 'sources' => $local['sources'] );
	}
	$site = $rest( '/operator-kit/brain/status', 'GET', null, array() );
	if ( ! is_array( $site ) || isset( $site['error'] ) ) {
		return array( 'error' => 'site_unreachable', 'detail' => $site, 'hint' => 'A 404 means the site runs a plugin older than 0.79.' );
	}
	$site_index = isset( $site['index'] ) && is_array( $site['index'] ) ? $site['index'] : array();
	if ( ! $scoped && ! empty( $site['present'] ) && $site['fingerprint'] === $local['fingerprint'] ) {
		return array( 'status' => 'in_sync', 'pushed' => 0, 'fingerprint' => $local['fingerprint'], 'message' => 'Site already holds this exact brain.' );
	}
	$diff    = cc_brain_diff( $local['index'], $site_index );
	$to_send = array_merge( $diff['only_local'], $diff['differ'] );
	$delete  = $scoped ? array() : $diff['only_site'];
	$from    = php_uname( 'n' );

	$chunks = array();
	$cur    = array();
	$size   = 0;
	foreach ( $to_send as $p ) {
		$len = strlen( $local['files'][ $p ] );
		if ( ! empty( $cur ) && $size + $len > $chunk_bytes ) {
			$chunks[] = $cur;
			$cur  = array();
			$size = 0;
		}
		$cur[ $p ] = $local['files'][ $p ];
		$size += $len;
	}
	if ( ! empty( $cur ) || ! empty( $delete ) ) {
		$chunks[] = $cur;
	}
	$responses = array();
	foreach ( $chunks as $i => $chunk ) {
		$body = array( 'mode' => 'merge', 'files' => $chunk, 'pushed_from' => $from );
		if ( 0 === $i && ! empty( $delete ) ) {
			$body['delete'] = $delete;
		}
		$r = $rest( '/operator-kit/brain', 'POST', $body, array() );
		if ( ! is_array( $r ) || isset( $r['error'] ) ) {
			return array( 'error' => 'push_failed', 'chunk' => $i + 1, 'of' => count( $chunks ), 'detail' => $r );
		}
		$responses[] = array( 'files' => count( $chunk ), 'fingerprint' => isset( $r['fingerprint'] ) ? $r['fingerprint'] : '' );
	}
	$after = $rest( '/operator-kit/brain/status', 'GET', null, array() );
	$fp    = is_array( $after ) && isset( $after['fingerprint'] ) ? $after['fingerprint'] : '';
	if ( $scoped ) {
		$after_index = is_array( $after ) && isset( $after['index'] ) && is_array( $after['index'] ) ? $after['index'] : array();
		$selected_index = array_intersect_key( $after_index, $local['index'] );
		$matched = cc_brain_fingerprint( $selected_index ) === $local['fingerprint'];
		return array( 'status' => $matched ? 'selected_files_verified' : 'selected_files_unverified', 'pushed' => count( $to_send ),
			'deleted' => 0, 'selected_paths' => array_keys( $local['files'] ), 'site_fingerprint' => $fp,
			'limits' => 'Only the selected file hashes were checked; this is not a full-brain synchronization claim. Other site files were preserved.' );
	}
	return array(
		'status'            => $fp === $local['fingerprint'] ? 'in_sync' : 'diverged_after_push',
		'pushed'            => count( $to_send ),
		'deleted'           => count( $delete ),
		'chunks'            => count( $chunks ),
		'local_fingerprint' => $local['fingerprint'],
		'site_fingerprint'  => $fp,
		'site_updated_at'   => is_array( $after ) && isset( $after['updated_at'] ) ? $after['updated_at'] : '',
		'sample_pushed'     => array_slice( $to_send, 0, 20 ),
	);
}

/**
 * $what: all | memory | skills | project | bridge.  $mode: missing_only | replace.
 * bridge writes bin/*.php under the project's plugin folder and needs an MCP restart.
 */
function cc_brain_install_php( $target, $content ) {
	$ext = pathinfo( $target, PATHINFO_EXTENSION );
	if ( 'json' === $ext ) {
		json_decode( $content, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) { return 'downloaded JSON is invalid'; }
	} elseif ( ! function_exists( 'proc_open' ) ) { return 'syntax validation is unavailable'; }
	$tmp = @tempnam( dirname( $target ), 'cc-update-' );
	if ( false === $tmp ) { return 'could not create temporary file'; }
	try {
		if ( strlen( $content ) !== @file_put_contents( $tmp, $content, LOCK_EX ) ) { return 'incomplete temporary write'; }
		if ( 'json' !== $ext ) {
			if ( 'mjs' === $ext ) {
				if ( ! @rename( $tmp, $tmp . '.mjs' ) ) { return 'could not stage JavaScript'; }
				$tmp .= '.mjs';
			}
			$command = 'mjs' === $ext ? array( getenv( 'CC_NODE_BINARY' ) ?: 'node', '--check', $tmp ) : array( PHP_BINARY, '-n', '-l', $tmp );
			$proc = @proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
			if ( ! is_resource( $proc ) ) { return 'could not start syntax validation'; }
			fclose( $pipes[0] );
			stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
			stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
			if ( 0 !== proc_close( $proc ) ) { return 'downloaded file has a syntax error'; }
		}
		if ( is_file( $target ) && ! @copy( $target, $target . '.previous' ) ) { return 'could not save previous version'; }
		return @rename( $tmp, $target ) ? true : 'atomic replacement failed';
	} finally { if ( is_file( $tmp ) ) { @unlink( $tmp ); } }
}

function cc_brain_tool_pull( callable $rest, $what = 'all', $mode = 'missing_only', $project_dir = null, $home = null ) {
	$project_dir = null === $project_dir ? cc_brain_project_dir() : $project_dir;
	$home        = null === $home ? cc_brain_home() : $home;
	$mode        = 'replace' === $mode ? 'replace' : 'missing_only';

	if ( 'bridge' === $what ) {
		$r = $rest( '/operator-kit/bridge', 'GET', null, array() );
		if ( ! is_array( $r ) || isset( $r['error'] ) || empty( $r['files'] ) ) {
			return array( 'error' => 'bridge_unavailable', 'detail' => $r );
		}
		$bin = $project_dir . '/wp-content/plugins/cc-assistant/bin';
		if ( ! is_dir( $bin ) && ! @mkdir( $bin, 0777, true ) ) {
			return array( 'error' => 'mkdir_failed', 'dir' => $bin );
		}
		$written = array(); $same = array(); $errors = array();
		foreach ( $r['files'] as $name => $e ) {
			if ( ( ! preg_match( '/^[A-Za-z0-9_.-]+\.php$/', (string) $name ) && ! in_array( $name, array( 'browser-transport.mjs', 'browser/package.json' ), true ) ) || ! is_array( $e ) || ! isset( $e['content'] ) ) {
				continue;
			}
			$target = $bin . '/' . $name;
			if ( is_link( $target ) || is_link( $bin ) || is_link( dirname( $target ) ) ) { $errors[] = $name . ' (symlink refused)'; continue; }
			if ( ! is_string( $e['content'] ) || ! isset( $e['sha1'] ) || ! is_string( $e['sha1'] )
				|| ! hash_equals( $e['sha1'], sha1( $e['content'] ) ) ) {
				$errors[] = $name . ' (download hash mismatch)'; continue;
			}
			if ( is_file( $target ) && 'missing_only' === $mode ) { $same[] = $name; continue; }
			if ( is_file( $target ) && sha1( (string) file_get_contents( $target ) ) === $e['sha1'] ) {
				$same[] = $name;
				continue;
			}
			if ( ! is_dir( dirname( $target ) ) && ! @mkdir( dirname( $target ), 0777, true ) ) { $errors[] = $name . ' (mkdir failed)'; continue; }
			$write = cc_brain_install_php( $target, $e['content'] );
			if ( true !== $write ) { $errors[] = $name . ' (' . $write . ')'; continue; }
			$written[] = $name;
		}
		return array(
			'what'           => 'bridge',
			'site_version'   => isset( $r['plugin_version'] ) ? $r['plugin_version'] : '',
			'written'        => $written,
			'unchanged'      => $same,
			'errors'         => $errors,
			'bin_dir'        => $bin,
			'next'           => empty( $written ) ? 'No files replaced. Use mode=replace to update existing bridge files.' : 'RESTART the MCP server (Claude Code: /mcp or restart the session) so the new bridge loads. The running process still holds the old files.',
		);
	}

	$params = array();
	if ( in_array( $what, array( 'memory', 'skills', 'project' ), true ) ) {
		$params['prefix'] = $what . '/';
	}
	$r = $rest( '/operator-kit/brain', 'GET', null, $params );
	if ( ! is_array( $r ) || isset( $r['error'] ) ) {
		return array( 'error' => 'site_unreachable', 'detail' => $r );
	}
	if ( empty( $r['files'] ) ) {
		return array( 'error' => 'site_empty', 'message' => 'The site holds no brain files for "' . $what . '".' );
	}
	$w = cc_brain_write_files( $r['files'], $project_dir, $home, $mode );
	$w['what']            = $what;
	$w['site_updated_at'] = isset( $r['updated_at'] ) ? $r['updated_at'] : '';
	$w['written_count']   = count( $w['written'] );
	$w['skipped_count']   = count( $w['skipped'] );
	$w['targets']         = array(
		'memory' => cc_brain_memory_dir( $home, $project_dir ),
		'skills' => cc_brain_skills_dir( $home ),
		'project' => $project_dir,
	);
	if ( count( $w['written'] ) > 25 ) {
		$w['written'] = array_merge( array_slice( $w['written'], 0, 25 ), array( '... +' . ( $w['written_count'] - 25 ) . ' more' ) );
	}
	if ( count( $w['skipped'] ) > 10 ) {
		$w['skipped'] = array_merge( array_slice( $w['skipped'], 0, 10 ), array( '... +' . ( $w['skipped_count'] - 10 ) . ' more' ) );
	}
	$w['next'] = 'Memory files are read by Claude Code at session start: begin a NEW chat for the pulled rules to load. Skills load on demand.';
	return $w;
}

/**
 * whoami decoration. $resp is the unwrapped /whoami body (mutated in place).
 * Uses operator_brain + bridge_hashes the server embedded (no extra call).
 */
function cc_brain_decorate_whoami( array &$resp, $mcp_version, $project_dir = null, $home = null ) {
	$site_ver = isset( $resp['plugin_version'] ) ? (string) $resp['plugin_version'] : '';
	if ( '' !== $site_ver && version_compare( $site_ver, $mcp_version, '>' ) ) {
		$resp['version_drift'] = 'LOCAL BRIDGE IS STALE: this laptop runs bridge v' . $mcp_version . ' but the site runs cc-assistant v' . $site_ver . '. Tools added after v' . $mcp_version . ' are MISSING from this chat. Fix: operator_brain_pull(what="bridge") then restart the MCP server.';
	}

	$site_summary = isset( $resp['operator_brain'] ) && is_array( $resp['operator_brain'] ) ? $resp['operator_brain'] : array( 'present' => false );
	$local        = cc_brain_collect_local( $project_dir, $home );
	$verdict      = cc_brain_classify( $local, $site_summary );
	$hashes       = isset( $resp['bridge_hashes'] ) && is_array( $resp['bridge_hashes'] ) ? $resp['bridge_hashes'] : array();
	$verdict['bridge'] = cc_brain_bridge_check( $hashes );
	$verdict['bridge']['local_version'] = $mcp_version;
	$verdict['bridge']['site_version']  = $site_ver;
	if ( 'differs' === $verdict['bridge']['status'] ) {
		$verdict['bridge']['action'] = version_compare( $site_ver, $mcp_version, '>' )
			? 'Site ships a newer bridge: operator_brain_pull(what="bridge"), then restart the MCP server.'
			: 'This laptop\'s bin/ differs from what the site ships (local edits not yet deployed, or the site zip is behind). If the local build is the newer one, deploy the zip; otherwise pull the bridge.';
	}
	unset( $resp['bridge_hashes'] );
	$resp['operator_brain'] = $verdict;

	$host = '';
	if ( isset( $resp['site_url'] ) ) {
		$host = (string) parse_url( (string) $resp['site_url'], PHP_URL_HOST );
	} elseif ( isset( $resp['url'] ) ) {
		$host = (string) parse_url( (string) $resp['url'], PHP_URL_HOST );
	}
	$name = isset( $resp['site_name'] ) ? (string) $resp['site_name'] : ( isset( $resp['name'] ) ? (string) $resp['name'] : '' );
	$resp['relevant_rules'] = cc_brain_relevant_rules( $local['sources']['memory_dir'], $host, $name );
	$resp['relevant_rules']['read_me'] = 'hard_rules are the operator\'s non-negotiables (descriptions flagged HARD/STRICT); site_rules mention this site. Full text lives in the memory folder named above. If memory_present is false, pull the brain before working.';
}

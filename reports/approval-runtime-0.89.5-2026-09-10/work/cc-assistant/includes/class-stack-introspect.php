<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.65 — Stack introspection.
 *
 * The problem this solves: the assistant repeatedly gave the operator WRONG
 * wp-admin navigation ("Rank Math has a Taxonomies tab" — it does not; "fix the
 * feed price in Attribute Mapping" — wrong screen entirely). class-site-memory
 * only recognises a curated list of plugins it was explicitly taught about, so
 * anything outside that list is invisible and the assistant guesses.
 *
 * This class answers three questions with evidence instead of guesswork:
 *   1. What is installed?          -> plugins()
 *   2. Where does its UI live?     -> admin_menu_snapshot()
 *   3. What is it set to?          -> plugin_settings( $slug )
 *
 * WHY THE MENU IS A CACHED SNAPSHOT AND NOT LIVE (verified, not assumed):
 * WordPress builds $menu/$submenu in wp-admin/includes/menu.php, which fires
 * 'admin_menu' at line 161. Third-party plugins only REGISTER their admin_menu
 * callbacks when is_admin() is true — Elementor gates `new Admin()` behind
 * `if ( is_admin() )` in includes/plugin.php:727, and the same pattern holds
 * across every plugin checked. A REST request has is_admin() === false, so
 * firing do_action('admin_menu') there would build a tree MISSING most
 * third-party plugins while looking complete. A silently-partial answer is
 * worse than no answer, because it reads as authoritative.
 *
 * So we capture the real tree during real admin page loads (where every plugin
 * has registered) and serve that snapshot, stamped with when it was taken.
 *
 * READ-ONLY. This class never writes to any plugin's settings.
 */
class CC_Assistant_Stack_Introspect {

	const MENU_OPTION  = 'cc_assistant_admin_menu_snapshot';
	const MAX_VALUE    = 1200;   // chars per option value before truncation
	const MAX_OPTIONS  = 60;     // options returned per plugin

	/**
	 * Hook the menu capture. Called from the main plugin bootstrap.
	 * Runs at PHP_INT_MAX so every other plugin has registered first.
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'capture_admin_menu' ), PHP_INT_MAX );
		}
	}

	/* ---------------------------------------------------------------------
	 * 1. MENU CAPTURE
	 * ------------------------------------------------------------------ */

	/**
	 * Serialise the live $menu/$submenu into an option.
	 * Only writes when the tree actually changed, so this is not a write on
	 * every single admin page load.
	 */
	public static function capture_admin_menu() {
		global $menu, $submenu;
		if ( ! is_array( $menu ) ) {
			return;
		}

		$tree = array();
		foreach ( $menu as $item ) {
			if ( empty( $item[0] ) || empty( $item[2] ) ) {
				continue;
			}
			$label = self::clean_label( $item[0] );
			if ( '' === $label ) {
				continue; // separators
			}
			$slug     = $item[2];
			$children = array();
			if ( isset( $submenu[ $slug ] ) && is_array( $submenu[ $slug ] ) ) {
				foreach ( $submenu[ $slug ] as $sub ) {
					if ( empty( $sub[0] ) || empty( $sub[2] ) ) {
						continue;
					}
					$children[] = array(
						'label' => self::clean_label( $sub[0] ),
						'slug'  => $sub[2],
						'url'   => self::menu_url( $sub[2], $slug ),
					);
				}
			}
			$tree[] = array(
				'label'      => $label,
				'slug'       => $slug,
				'capability' => isset( $item[1] ) ? $item[1] : '',
				'url'        => self::menu_url( $slug, '' ),
				'submenu'    => $children,
			);
		}

		$payload = array(
			'captured_at'  => current_time( 'mysql' ),
			'captured_by'  => get_current_user_id(),
			'wp_version'   => get_bloginfo( 'version' ),
			'top_level'    => count( $tree ),
			'menu'         => $tree,
		);

		$existing = get_option( self::MENU_OPTION );
		if ( is_array( $existing ) && isset( $existing['menu'] ) && $existing['menu'] === $tree ) {
			return; // unchanged, skip the write
		}
		update_option( self::MENU_OPTION, $payload, false );
	}

	private static function clean_label( $raw ) {
		// Menu labels routinely carry update-count bubbles and screen-reader spans.
		$s = preg_replace( '#<span[^>]*>.*?</span>#is', '', (string) $raw );
		$s = wp_strip_all_tags( $s );
		return trim( html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Resolve a menu slug to the URL an operator would actually click.
	 *
	 * Uses core's own menu_page_url() rather than reimplementing the rule,
	 * because a wrong URL here would reintroduce exactly the guess-the-path
	 * problem this class exists to eliminate. menu_page_url() reads the
	 * $_parent_pages global, which is populated during the admin request we
	 * capture in, so it resolves a plugin page to the correct parent file
	 * (options-general.php?page=x vs admin.php?page=x). It returns '' for
	 * anything that is not a registered plugin page, which is the signal that
	 * the slug is a core admin file such as edit.php.
	 */
	private static function menu_url( $slug, $parent ) {
		$slug = (string) $slug;
		if ( preg_match( '#^https?://#i', $slug ) ) {
			return $slug; // external link in the menu
		}
		if ( function_exists( 'menu_page_url' ) ) {
			$url = menu_page_url( $slug, false );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		// Core admin file (edit.php, options-general.php, upload.php...),
		// optionally already carrying its own query string.
		return admin_url( ltrim( $slug, '/' ) );
	}

	public static function admin_menu_snapshot() {
		$snap = get_option( self::MENU_OPTION );
		if ( ! is_array( $snap ) || empty( $snap['menu'] ) ) {
			return array(
				'available' => false,
				'why'       => 'No snapshot yet. The admin menu can only be captured during a real wp-admin page load, because third-party plugins register their menus behind is_admin(). Ask the operator to open any wp-admin screen once, then call this again.',
				'menu'      => array(),
			);
		}
		$snap['available'] = true;
		$age = strtotime( current_time( 'mysql' ) ) - strtotime( $snap['captured_at'] );
		$snap['age_hours'] = round( max( 0, $age ) / HOUR_IN_SECONDS, 1 );
		if ( $snap['age_hours'] > 168 ) {
			$snap['staleness_warning'] = 'Snapshot is over a week old. Plugin menus may have changed since. Have the operator load any wp-admin page to refresh it.';
		}
		return $snap;
	}

	/* ---------------------------------------------------------------------
	 * 2. PLUGIN ENUMERATION
	 * ------------------------------------------------------------------ */

	public static function plugins( $include_inactive = true ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$all     = get_plugins();
		$updates = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : array();
		$out     = array();

		foreach ( $all as $file => $data ) {
			$active = is_plugin_active( $file );
			if ( ! $active && ! $include_inactive ) {
				continue;
			}
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			$row = array(
				'slug'        => $slug,
				'file'        => $file,
				'name'        => isset( $data['Name'] ) ? $data['Name'] : $slug,
				'version'     => isset( $data['Version'] ) ? $data['Version'] : '',
				'active'      => $active,
				'text_domain' => isset( $data['TextDomain'] ) ? $data['TextDomain'] : '',
			);
			if ( isset( $updates[ $file ]->update->new_version ) ) {
				$row['update_available'] = $updates[ $file ]->update->new_version;
			}
			if ( is_multisite() && is_plugin_active_for_network( $file ) ) {
				$row['network_active'] = true;
			}
			$out[] = $row;
		}

		usort(
			$out,
			function ( $a, $b ) {
				if ( $a['active'] !== $b['active'] ) {
					return $a['active'] ? -1 : 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		$result = array(
			'total'    => count( $out ),
			'active'   => count( array_filter( $out, function ( $p ) { return $p['active']; } ) ),
			'plugins'  => $out,
		);

		// Must-use and drop-ins are invisible to get_plugins() and routinely
		// carry site-breaking behaviour, so surface them separately.
		if ( function_exists( 'get_mu_plugins' ) ) {
			$mu = get_mu_plugins();
			if ( $mu ) {
				$result['must_use'] = array_map(
					function ( $d, $f ) {
						$name = isset( $d['Name'] ) ? $d['Name'] : $f;
						return array( 'file' => $f, 'name' => $name );
					},
					$mu,
					array_keys( $mu )
				);
			}
		}
		if ( function_exists( 'get_dropins' ) ) {
			$drop = get_dropins();
			if ( $drop ) {
				$result['dropins'] = array_keys( $drop );
			}
		}

		return $result;
	}

	/* ---------------------------------------------------------------------
	 * 3. SETTINGS READING (redacted)
	 * ------------------------------------------------------------------ */

	/**
	 * Option-name prefixes worth trying for a plugin. WordPress has no formal
	 * plugin-to-option mapping, so this is a heuristic and is reported as such.
	 */
	private static function prefixes_for( $slug, $text_domain ) {
		$cands = array();
		foreach ( array( $slug, $text_domain ) as $base ) {
			$base = trim( (string) $base );
			if ( '' === $base ) {
				continue;
			}
			$us  = str_replace( '-', '_', $base );
			$hy  = str_replace( '_', '-', $base );
			$sq  = str_replace( array( '-', '_' ), '', $base );
			foreach ( array( $base, $us, $hy, $sq ) as $v ) {
				$cands[ $v ] = true;
				$cands[ $v . '_' ] = true;
			}
			// common vendor-prefix compressions: woo-multi-currency -> wmc_
			$parts = preg_split( '/[-_]/', $base );
			if ( count( $parts ) >= 2 ) {
				$initials = '';
				foreach ( $parts as $p ) {
					$initials .= substr( $p, 0, 1 );
				}
				if ( strlen( $initials ) >= 2 ) {
					$cands[ $initials . '_' ] = true;
				}
			}
		}
		return array_keys( $cands );
	}

	/**
	 * Discover the option names a plugin ACTUALLY uses by reading its own source
	 * for get_option()/update_option() calls with a literal first argument.
	 *
	 * This exists because the name-prefix heuristic alone is wrong for most real
	 * plugins. Measured against this site's stack, prefixes derived from the
	 * folder slug MISS: seo-by-rank-math (stores rank_math_*),
	 * webappick-product-feed-for-woocommerce (stores woo_feed_*) and
	 * sg-cachepress (stores siteground_optimizer_*). Returning "0 options found"
	 * for those is worse than useless, because it reads as "not configured".
	 *
	 * Source scanning is self-maintaining: it works for any plugin without a
	 * hand-kept alias table that would rot. It only reads PHP files inside the
	 * plugin's own directory and is capped so a huge plugin cannot stall the
	 * request.
	 *
	 * @return array<string> option names, deduplicated.
	 */
	private static function discover_option_names( $plugin_slug, $dir_override = null ) {
		$empty = array( 'names' => array(), 'wrapper_prefixes' => array() );
		$dir   = $dir_override ? trailingslashit( $dir_override ) : trailingslashit( WP_PLUGIN_DIR ) . $plugin_slug;
		if ( ( ! $plugin_slug && ! $dir_override ) || ! is_dir( $dir ) ) {
			return $empty; // keep the return shape consistent for callers
		}

		// Directories that burn the file budget without holding any of the
		// plugin's own option names. Verified miss: Google for WooCommerce
		// returned source_scan_found=1 because its Composer vendor/ tree
		// consumed the whole cap before the scan reached src/.
		$skip_dirs = array( 'vendor', 'node_modules', 'tests', 'test', 'languages', 'assets',
			'build', 'dist', 'i18n', 'lang', 'css', 'fonts', 'images', 'img', '.git' );

		$names       = array();
		$prefix_hits = array();
		$files_read  = 0;
		$bytes_read  = 0;
		$max_files   = 600;
		$max_bytes   = 4194304; // 4 MB
		$max_names   = 250;

		try {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $it as $file ) {
				if ( $files_read >= $max_files || $bytes_read >= $max_bytes || count( $names ) >= $max_names ) {
					break;
				}
				if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
					continue;
				}
				// Drop anything under a skipped directory before it costs budget.
				$rel = str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $dir ) ) );
				$hit = false;
				foreach ( explode( '/', trim( $rel, '/' ) ) as $seg ) {
					if ( in_array( strtolower( $seg ), $skip_dirs, true ) ) {
						$hit = true;
						break;
					}
				}
				if ( $hit ) {
					continue;
				}
				$size = $file->getSize();
				if ( $size > 1048576 ) {
					continue; // skip single files over 1 MB (vendored libs, minified bundles)
				}
				$src = @file_get_contents( $file->getPathname() );
				if ( false === $src ) {
					continue;
				}
				$files_read++;
				$bytes_read += $size;
				if ( preg_match_all(
					'/\b(?:get|update|add|delete)_(?:site_)?option\s*\(\s*([\'"])([A-Za-z0-9_\-]{3,64})\1/',
					$src,
					$m
				) ) {
					foreach ( $m[2] as $n ) {
						$names[ $n ] = true;
						if ( count( $names ) >= $max_names ) {
							break;
						}
					}
				}
				// Many plugins never pass a literal. They wrap options in a
				// class that concatenates a constant prefix onto a key, e.g.
				// get_option( self::PREFIX . $name ) with PREFIX = 'gla_'.
				// Verified miss: Google for WooCommerce returned 1 name and 0
				// options because of exactly this. Capture the literal prefix so
				// the caller at least learns which namespace to look in.
				if ( preg_match_all(
					'/\b(?:get|update|add|delete)_(?:site_)?option\s*\(\s*([\'"])([A-Za-z0-9_\-]{2,32}_)\1\s*\./',
					$src,
					$mp
				) ) {
					foreach ( $mp[2] as $p ) {
						$prefix_hits[ $p ] = isset( $prefix_hits[ $p ] ) ? $prefix_hits[ $p ] + 1 : 1;
					}
				}
				if ( preg_match_all(
					'/(?:const|public|private|protected|static)[^;=\n]*=\s*([\'"])([A-Za-z0-9]{2,20}_)\1\s*;/',
					$src,
					$mc
				) ) {
					foreach ( $mc[2] as $p ) {
						$prefix_hits[ $p ] = isset( $prefix_hits[ $p ] ) ? $prefix_hits[ $p ] + 1 : 1;
					}
				}
			}
		} catch ( \Throwable $e ) {
			// partial is fine, never fatal
		}

		// A wrapper prefix only counts if the plugin leans on it repeatedly.
		arsort( $prefix_hits );
		$wrapper = array();
		foreach ( $prefix_hits as $p => $c ) {
			if ( $c >= 2 ) {
				$wrapper[] = $p;
			}
		}

		return array(
			'names'            => array_values( array_diff( array_keys( $names ), self::core_option_names() ) ),
			'wrapper_prefixes' => array_slice( $wrapper, 0, 5 ),
		);
	}

	/**
	 * WordPress core option names, excluded from source-scan results.
	 *
	 * Without this, scanning any plugin returns core settings it merely READS.
	 * Measured: scanning elementor returned date_format, time_format,
	 * show_on_front and page_on_front, none of which are Elementor settings.
	 */
	// Public since v0.69: the settings WRITER needs the same denylist. Kept
	// as one source of truth rather than a second copy that would drift.
	public static function core_option_names() {
		return array(
			'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email', 'users_can_register',
			'start_of_week', 'date_format', 'time_format', 'timezone_string', 'gmt_offset',
			'permalink_structure', 'category_base', 'tag_base', 'rewrite_rules',
			'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page', 'per_page',
			'default_role', 'default_category', 'default_comment_status', 'comment_moderation',
			'thumbnail_size_w', 'thumbnail_size_h', 'medium_size_w', 'medium_size_h',
			'large_size_w', 'large_size_h', 'uploads_use_yearmonth_folders', 'upload_path',
			'template', 'stylesheet', 'current_theme', 'active_plugins', 'blog_charset',
			'blog_public', 'html_type', 'WPLANG', 'site_icon', 'db_version', 'initial_db_version',
			'cron', 'recently_edited', 'sidebars_widgets', 'widget_text', 'user_roles',
			'wp_user_roles', 'fresh_site', 'auto_update_core_dev', 'woocommerce_permalinks',
		);
	}

	/** Does a discovered option name look like it belongs to THIS plugin? */
	private static function owns_option( $name, $prefixes ) {
		foreach ( $prefixes as $p ) {
			if ( strlen( $p ) >= 3 && 0 === strpos( $name, $p ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Infer a plugin's real option prefix from the names its own source uses.
	 *
	 * Needed because slug and text domain both fail for plugins that namespace
	 * their options differently. Verified case: sg-cachepress declares Text
	 * Domain "sg-cachepress" but stores 103 options under siteground_optimizer_.
	 * Neither the slug nor the text domain finds those, yet the prefix is
	 * obvious from the names themselves once you count them.
	 *
	 * A prefix qualifies when it covers at least 3 names and a quarter of the
	 * discovered set, which is high enough that an incidentally-referenced
	 * foreign plugin never wins.
	 */
	private static function dominant_prefixes( $names ) {
		$total = count( $names );
		if ( $total < 3 ) {
			return array();
		}
		$counts = array();
		foreach ( $names as $n ) {
			$parts = explode( '_', $n );
			$max   = min( 3, count( $parts ) - 1 );
			for ( $take = 1; $take <= $max; $take++ ) {
				$p = implode( '_', array_slice( $parts, 0, $take ) ) . '_';
				if ( strlen( $p ) < 4 ) {
					continue;
				}
				$counts[ $p ] = isset( $counts[ $p ] ) ? $counts[ $p ] + 1 : 1;
			}
		}
		$out = array();
		foreach ( $counts as $p => $c ) {
			if ( $c >= 3 && ( $c / $total ) >= 0.25 ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * Patterns that mark an option as secret-bearing. Deliberately broad:
	 * this output travels over MCP to a model and may be logged, so the cost of
	 * a false positive (one redacted value) is far below the cost of a false
	 * negative (a leaked live payment key).
	 */
	// Public since v0.69 — see note on core_option_names().
	public static function is_secret_key( $key ) {
		$key = (string) $key;
		// NOTE on the auth patterns: a bare /auth/ also matches "author", which
		// falsely redacts post_author, default_author and similar. Word
		// boundaries keep gtm_auth and oauth_token while letting author through.
		return (bool) preg_match(
			'/(secret|token|password|passwd|_pwd\b|api[_-]?key|apikey|private[_-]?key|credential|client[_-]?secret'
			. '|\boauth\b|oauth[_-]|\bauth\b|_auth\b|\bauth[_-]|authorization'
			. '|\bsalt\b|_salt\b|nonce|licen[sc]e|_key$|^key_|access[_-]?key|signing|webhook)/i',
			$key
		);
	}

	private static function looks_like_secret_value( $val ) {
		if ( ! is_string( $val ) || strlen( $val ) < 16 ) {
			return false;
		}
		if ( preg_match( '/^(sk|pk|rk)_(live|test)_[A-Za-z0-9]{8,}/', $val ) ) {
			return true; // Stripe-style
		}
		if ( preg_match( '/^ey[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\./', $val ) ) {
			return true; // JWT
		}
		if ( false !== strpos( $val, '-----BEGIN' ) ) {
			return true; // PEM
		}
		// Structured, obviously-not-secret shapes must be exempted BEFORE the
		// entropy test below, which otherwise flags them. Caught in real output:
		// auto_update_plugins stores rows like
		// "woocommerce-gateway-stripe/woocommerce-gateway-stripe.php" — 57 chars,
		// no whitespace, allowed charset, plenty of distinct characters, so the
		// entropy rule redacted a plugin path as a credential.
		if ( preg_match( '/\.(php|js|css|jpe?g|png|webp|gif|svg|xml|json|txt|pdf|zip)$/i', $val ) ) {
			return false; // file path or filename
		}
		if ( preg_match( '#^(https?|ftp)://#i', $val ) || 0 === strpos( $val, '/' ) ) {
			return false; // URL or absolute path
		}
		if ( preg_match( '/^[\d.,\s:+-]+$/', $val ) ) {
			return false; // numbers, dates, timestamps
		}
		// long single-token high-entropy string with no spaces
		if ( strlen( $val ) >= 32 && ! preg_match( '/\s/', $val ) && preg_match( '/^[A-Za-z0-9_\-\.=\/+]+$/', $val ) ) {
			$uniq = count( array_unique( str_split( $val ) ) );
			if ( $uniq >= 16 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Recursively redact a value. Returns a marker string in place of anything
	 * that looks like a credential, so the assistant knows the setting EXISTS
	 * without ever receiving it.
	 */
	private static function redact( $value, $key = '' ) {
		if ( self::is_secret_key( $key ) ) {
			return '[redacted: key matched a credential pattern]';
		}
		if ( is_scalar( $value ) || null === $value ) {
			if ( self::looks_like_secret_value( $value ) ) {
				return '[redacted: value looks like a credential]';
			}
			if ( is_string( $value ) && strlen( $value ) > self::MAX_VALUE ) {
				return substr( $value, 0, self::MAX_VALUE ) . '... [truncated, ' . strlen( $value ) . ' chars total]';
			}
			return $value;
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > 80 ) {
				return '[array of ' . count( $value ) . ' items, omitted for size]';
			}
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::redact( $v, (string) $k );
			}
			return $out;
		}
		if ( is_object( $value ) ) {
			return '[object ' . get_class( $value ) . ']';
		}
		return '[unreadable]';
	}

	/** Prove the returned redacted value still represents the current option. */
	public static function observed_value_matches( $option, $value ) {
		return self::redact( get_option( $option, null ), $option ) === $value;
	}

	public static function plugin_settings( $slug ) {
		global $wpdb;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$slug = sanitize_key( str_replace( ' ', '-', (string) $slug ) );
		if ( '' === $slug ) {
			return new WP_Error( 'bad_slug', 'A plugin slug is required.', array( 'status' => 400 ) );
		}

		// Resolve the plugin so we can use its text domain as a second prefix source.
		$all   = get_plugins();
		$match = null;
		foreach ( $all as $file => $data ) {
			$d = dirname( $file );
			if ( '.' === $d ) {
				$d = basename( $file, '.php' );
			}
			if ( $d === $slug ) {
				$match = array( 'file' => $file, 'data' => $data );
				break;
			}
		}
		$text_domain = ( $match && isset( $match['data']['TextDomain'] ) ) ? $match['data']['TextDomain'] : '';

		// v0.69: the active THEME's settings matter as much as any plugin's —
		// Divi keeps its Theme Options under et_divi, which the plugin-slug
		// path can never reach (field gap: "is Dynamic CSS on?" was
		// unanswerable from data). When the slug names the active theme or its
		// parent, resolve against the theme instead: same prefix heuristics,
		// same source scan pointed at the theme directory, plus theme_mods.
		$theme_mode = null;
		if ( ! $match ) {
			$active_theme = wp_get_theme();
			foreach ( array( $active_theme, $active_theme->parent() ) as $t ) {
				if ( ! $t || ! $t->exists() ) {
					continue;
				}
				$candidates = array_unique(
					array(
						strtolower( $t->get_stylesheet() ),
						strtolower( $t->get_template() ),
						sanitize_key( (string) $t->get( 'Name' ) ),
					)
				);
				if ( in_array( $slug, $candidates, true ) ) {
					$theme_mode = $t;
					break;
				}
			}
			if ( $theme_mode ) {
				$text_domain = (string) $theme_mode->get( 'TextDomain' );
			}
		}

		$prefixes = self::prefixes_for( $slug, $text_domain );
		$rows     = array();
		$seen     = array();

		foreach ( $prefixes as $p ) {
			if ( strlen( $p ) < 3 ) {
				continue; // too broad, would match half the table
			}
			$like = $wpdb->esc_like( $p ) . '%';
			$got  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d",
					$like,
					self::MAX_OPTIONS
				),
				ARRAY_A
			);
			foreach ( (array) $got as $r ) {
				if ( isset( $seen[ $r['option_name'] ] ) ) {
					continue;
				}
				$seen[ $r['option_name'] ] = true;
				$val = maybe_unserialize( $r['option_value'] );
				$rows[] = array(
					'option'   => $r['option_name'],
					'autoload' => $r['autoload'],
					'value'    => self::redact( $val, $r['option_name'] ),
				);
			}
			if ( count( $rows ) >= self::MAX_OPTIONS ) {
				break;
			}
		}

		// Theme mode: customizer settings live in theme_mods_{stylesheet},
		// which no name-prefix heuristic can reach. Fetch them explicitly.
		if ( $theme_mode && count( $rows ) < self::MAX_OPTIONS ) {
			$mod_names = array_unique(
				array(
					'theme_mods_' . $theme_mode->get_stylesheet(),
					'theme_mods_' . $theme_mode->get_template(),
				)
			);
			foreach ( $mod_names as $mn ) {
				if ( isset( $seen[ $mn ] ) ) {
					continue;
				}
				$mrow = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
						$mn
					),
					ARRAY_A
				);
				if ( $mrow ) {
					$seen[ $mn ] = true;
					$rows[]      = array(
						'option'   => $mrow['option_name'],
						'autoload' => $mrow['autoload'],
						'value'    => self::redact( maybe_unserialize( $mrow['option_value'] ), $mrow['option_name'] ),
						'via'      => 'theme-mods',
					);
				}
			}
		}

		// Second pass: option names read straight out of the plugin's own source.
		// This is what catches rank_math_*, woo_feed_*, siteground_optimizer_*
		// and every other plugin whose option names do not match its folder slug.
		$discovered = array();
		$own_prefixes = $prefixes;
		if ( count( $rows ) < self::MAX_OPTIONS ) {
			$scan         = self::discover_option_names( $slug, $theme_mode ? $theme_mode->get_stylesheet_directory() : null );
			$discovered   = isset( $scan['names'] ) ? $scan['names'] : array();
			$wrapper      = isset( $scan['wrapper_prefixes'] ) ? $scan['wrapper_prefixes'] : array();
			$own_prefixes = array_values( array_unique( array_merge( $prefixes, self::dominant_prefixes( $discovered ) ) ) );

			// Wrapper prefixes are a namespace, not a name: query them as LIKE.
			foreach ( $wrapper as $wp_pref ) {
				if ( count( $rows ) >= self::MAX_OPTIONS ) {
					break;
				}
				$own_prefixes[] = $wp_pref;
				$wlike = $wpdb->esc_like( $wp_pref ) . '%';
				$wgot  = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d",
						$wlike,
						self::MAX_OPTIONS - count( $rows )
					),
					ARRAY_A
				);
				foreach ( (array) $wgot as $r ) {
					if ( isset( $seen[ $r['option_name'] ] ) ) {
						continue;
					}
					$seen[ $r['option_name'] ] = true;
					$rows[] = array(
						'option'   => $r['option_name'],
						'autoload' => $r['autoload'],
						'value'    => self::redact( maybe_unserialize( $r['option_value'] ), $r['option_name'] ),
						'via'      => 'wrapper-prefix ' . $wp_pref,
					);
				}
			}
			$wanted     = array();
			foreach ( $discovered as $n ) {
				if ( ! isset( $seen[ $n ] ) ) {
					$wanted[] = $n;
				}
			}
			if ( $wanted ) {
				$room  = self::MAX_OPTIONS - count( $rows );
				$wanted = array_slice( $wanted, 0, max( 0, $room ) );
				if ( $wanted ) {
					$ph  = implode( ',', array_fill( 0, count( $wanted ), '%s' ) );
					$got = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ({$ph})",
							$wanted
						),
						ARRAY_A
					);
					foreach ( (array) $got as $r ) {
						if ( isset( $seen[ $r['option_name'] ] ) ) {
							continue;
						}
						$seen[ $r['option_name'] ] = true;
						$rows[] = array(
							'option'   => $r['option_name'],
							'autoload' => $r['autoload'],
							'value'    => self::redact( maybe_unserialize( $r['option_value'] ), $r['option_name'] ),
							// A plugin's source references options it does not own
							// (compatibility shims read other plugins' settings).
							// Flag which is which so the caller never misattributes.
							'via'      => self::owns_option( $r['option_name'], $own_prefixes )
								? 'source-scan'
								: 'source-scan (referenced, may belong to another plugin)',
						);
					}
				}
			}
		}

		usort( $rows, function ( $a, $b ) { return strcmp( $a['option'], $b['option'] ); } );

		$result = array(
			'slug'              => $slug,
			'type'              => $theme_mode ? 'theme' : 'plugin',
			'name'              => $theme_mode ? (string) $theme_mode->get( 'Name' ) : ( $match ? $match['data']['Name'] : null ),
			'installed'         => $theme_mode ? true : (bool) $match,
			'active'            => $theme_mode ? true : ( $match ? is_plugin_active( $match['file'] ) : false ),
			'version'           => $theme_mode ? (string) $theme_mode->get( 'Version' ) : ( $match ? $match['data']['Version'] : null ),
			'captured_at_utc'   => gmdate( 'Y-m-d H:i:s' ),
			'capability_evidence' => array( 'source' => 'installed_files_and_stored_options', 'complete' => false, 'option_ownership' => 'heuristic', 'available_features' => 'unknown', 'license_entitlements' => 'unknown', 'effective_runtime_values' => 'unknown', 'instruction' => 'Only the listed storage and registry facts were observed. Never invent controls, accepted values, UI locations or Pro features. Inspect the installed runtime schema or exact-version vendor documentation before a feature plan; absence here does not establish absence of a feature.' ),
			'text_domain'       => $text_domain,
			'prefixes_tried'    => $prefixes,
			'source_scan_found' => count( $discovered ),
			'wrapper_prefixes'  => isset( $wrapper ) ? $wrapper : array(),
			'options_found'     => count( $rows ),
			'options'           => $rows,
			'redaction_note'    => 'Values matching credential patterns are replaced with a marker. The assistant is told a setting exists but never receives its value.',
			'heuristic_warning' => 'WordPress has no formal plugin-to-option mapping. Options are found two ways: by name prefix derived from the slug and text domain, and by scanning the plugin\'s own PHP for get_option()/update_option() literals (rows marked via=source-scan). A setting stored under a name built at runtime, e.g. concatenated from a variable, is invisible to both. Absence here is NOT proof the plugin is unconfigured.',
		);

		// Settings API registry is authoritative where a plugin uses it.
		if ( function_exists( 'get_registered_settings' ) ) {
			$reg  = get_registered_settings();
			$hits = array();
			$result['registered_setting_schemas'] = array();
			foreach ( (array) $reg as $name => $args ) {
				foreach ( $prefixes as $p ) {
					if ( strlen( $p ) >= 3 && 0 === strpos( $name, $p ) ) {
						$hits[] = $name;
						$result['registered_setting_schemas'][$name] = array( 'type' => $args['type'] ?? null, 'source' => 'wordpress_settings_registry_this_request', 'ownership' => 'prefix_match_unverified', 'has_rest_schema' => ! empty( $args['show_in_rest']['schema'] ) );
						break;
					}
				}
			}
			if ( $hits ) {
				$result['registered_via_settings_api'] = array_values( array_unique( $hits ) );
			}
		}

		if ( count( $rows ) >= self::MAX_OPTIONS ) {
			$result['truncated'] = 'Hit the ' . self::MAX_OPTIONS . '-option cap. There may be more.';
		}

		return $result;
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Site_Identity {

	public static function whoami() {
		return array(
			'site_name'          => get_bloginfo( 'name' ),
			'site_url'           => home_url(),
			'admin_url'          => admin_url(),
			'site_id'            => get_option( 'cc_assistant_site_id', '' ),
			'wp_version'         => get_bloginfo( 'version' ),
			'plugin_version'     => CC_ASSISTANT_VERSION,
			'is_multisite'       => is_multisite(),
			'language'           => get_locale(),
			'timezone'           => wp_timezone_string(),
			'allowed_post_types' => get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) ),
			'elementor_active'   => self::is_elementor_active(),
			'fingerprint'        => self::fingerprint(),
			'environment'        => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
			'connection_time'    => current_time( 'mysql' ),
		);
	}

	public static function fingerprint() {
		$site_id = get_option( 'cc_assistant_site_id', '' );
		$url     = home_url();
		return substr( hash( 'sha256', $site_id . '|' . $url ), 0, 16 );
	}

	public static function is_elementor_active() {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
	}

	public static function record_heartbeat( $source = 'rest' ) {
		update_option(
			'cc_assistant_last_heartbeat',
			array(
				'time'      => current_time( 'mysql' ),
				'timestamp' => time(),
				'source'    => $source,
			),
			false
		);
	}

	public static function get_last_heartbeat() {
		return get_option( 'cc_assistant_last_heartbeat', null );
	}

	/**
	 * Detect whether this WP install is on the same machine the user will run
	 * Claude Code from. Used to decide whether the "save .mcp.json at <ABSPATH>"
	 * guidance is correct (local site) or misleading (live site, where ABSPATH
	 * is the remote server's web root).
	 */
	public static function is_local_install() {
		$file = wp_normalize_path( __FILE__ );
		if ( false !== stripos( $file, '/AppData/Local/Programs/Local/' ) || false !== stripos( $file, '/Local Sites/' ) ) {
			return true;
		}
		// Mac Local-by-Flywheel default path.
		if ( false !== stripos( $file, '/Library/Application Support/Local/' ) ) {
			return true;
		}
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}
		if ( preg_match( '/\.(local|test|localhost)$/i', $host ) ) {
			return true;
		}
		return false;
	}

	/**
	 * On Local-by-Flywheel + Windows, derive the bundled CLI php.exe path and
	 * its extension dir. Returns null if we can't be confident about the paths
	 * (wrong OS, missing env, files not where we expect). Callers should fall
	 * back to "php" on PATH.
	 */
	public static function detect_local_php() {
		// Windows Local-by-Flywheel: php.exe at .../lightning-services/php-X.Y.Z+0/bin/win64/php.exe
		if ( 0 === stripos( PHP_OS, 'WIN' ) ) {
			$userprofile = getenv( 'USERPROFILE' );
			if ( ! $userprofile ) {
				return null;
			}
			$base_dir = wp_normalize_path( $userprofile ) . '/AppData/Local/Programs/Local/resources/extraResources/lightning-services';
			if ( ! is_dir( $base_dir ) ) {
				return null;
			}
			// Prefer the folder matching this site's PHP version (Local pairs them).
			$preferred = $base_dir . '/php-' . PHP_VERSION . '+0';
			$candidate = file_exists( $preferred . '/bin/win64/php.exe' ) ? $preferred : null;
			if ( ! $candidate ) {
				// Fall back to any php-*+* folder with a usable php.exe.
				$matches = glob( $base_dir . '/php-*' );
				if ( $matches ) {
					foreach ( $matches as $dir ) {
						if ( file_exists( $dir . '/bin/win64/php.exe' ) ) {
							$candidate = $dir;
							break;
						}
					}
				}
			}
			if ( ! $candidate ) {
				return null;
			}
			return array(
				'php'     => $candidate . '/bin/win64/php.exe',
				'ext_dir' => $candidate . '/bin/win64/ext',
				'os'      => 'windows',
			);
		}
		// Mac Local-by-Flywheel: similar layout under ~/Library/Application Support/Local/lightning-services/
		$home = getenv( 'HOME' );
		if ( $home ) {
			$base_dir = $home . '/Library/Application Support/Local/lightning-services';
			if ( is_dir( $base_dir ) ) {
				$preferred = $base_dir . '/php-' . PHP_VERSION . '+0';
				$candidate = file_exists( $preferred . '/bin/darwin/bin/php' ) ? $preferred : null;
				if ( ! $candidate ) {
					$matches = glob( $base_dir . '/php-*' );
					if ( $matches ) {
						foreach ( $matches as $dir ) {
							if ( file_exists( $dir . '/bin/darwin/bin/php' ) ) {
								$candidate = $dir;
								break;
							}
						}
					}
				}
				if ( $candidate ) {
					return array(
						'php'     => $candidate . '/bin/darwin/bin/php',
						'ext_dir' => $candidate . '/bin/darwin/lib/php/extensions',
						'os'      => 'mac',
					);
				}
			}
		}
		return null;
	}

	/**
	 * Build the .mcp.json snippet for this site, with command and args
	 * pre-filled from local-PHP detection where possible. The caller is
	 * expected to replace CC_WP_APP_PASSWORD before pasting.
	 */
	public static function mcp_config_snippet( $username = null ) {
		$slug = sanitize_title_with_dashes( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'site' );
		if ( empty( $slug ) ) {
			$slug = 'site';
		}
		$server_name = 'cc-assistant-' . $slug;
		$detected    = self::detect_local_php();
		if ( $detected ) {
			$command = $detected['php'];
			$args    = array(
				'-d', 'extension_dir=' . $detected['ext_dir'],
				'-d', 'extension=curl',
				'-d', 'extension=openssl',
				'./wp-content/plugins/cc-assistant/bin/mcp-server.php',
			);
		} else {
			$command = 'php';
			$args    = array( './wp-content/plugins/cc-assistant/bin/mcp-server.php' );
		}
		if ( null === $username ) {
			$user     = wp_get_current_user();
			$username = $user && ! empty( $user->user_login ) ? $user->user_login : 'YOUR_USERNAME';
		}
		return array(
			'config'   => array(
				'mcpServers' => array(
					$server_name => array(
						'command' => $command,
						'args'    => $args,
						'env'     => array(
							'CC_WP_URL'          => home_url(),
							'CC_WP_USER'         => $username,
							'CC_WP_APP_PASSWORD' => 'PASTE_YOUR_APP_PASSWORD_HERE',
						),
					),
				),
			),
			'detected' => $detected,
		);
	}
}

<?php
/**
 * Shared URL -> post resolution.
 *
 * WordPress core's url_to_postid() answers one narrow question: "is this URL
 * the canonical permalink of a live post right now?" Every analytics surface
 * in this plugin was asking a different question — "which post do these
 * Search Console rows belong to?" — and getting url_to_postid()'s answer.
 *
 * Those differ whenever a URL has been consolidated. A retired post that 301s
 * to its replacement resolved to 0, so its impressions were dropped from the
 * destination's totals and the tools reported the URL as unresolved with a
 * speculative note about Custom Permalinks. Measured on irvingwellnessclinic
 * 2026-08: 4 URLs / 1,388 impressions orphaned, and topical_authority told us
 * to consolidate a cluster whose consolidation had already happened — it could
 * not see its own prior work.
 *
 * The fallback chain here already existed, scattered: class-internal-links.php
 * had the get_page_by_path() half, class-redirect-audit.php had the redirect
 * table half. Neither knew about the other. This is both, in one place.
 *
 * @package CC_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_URL_Resolver {

	/** Redirect hops to follow before declaring a loop. */
	const MAX_HOPS = 5;

	/** Per-request memo: normalized path => resolution array. */
	private static $memo = array();

	/** Per-request cache of the redirect map (null = not yet built). */
	private static $redirect_map = null;

	/** Per-request cache of the custom_permalink map (null = not yet built). */
	private static $cp_map = null;

	/**
	 * Resolve a URL to the post its metrics belong to.
	 *
	 * @param string $url Absolute URL or site-relative path.
	 * @return array {
	 *     @type int    $post_id   Resolved post ID, 0 if none.
	 *     @type string $via       How it resolved: direct|path|redirect|gone|loop|unresolved.
	 *     @type int    $hops      Redirects followed (0 for direct/path).
	 *     @type int    $code      HTTP code of the matched redirect, 0 if not via redirect.
	 *     @type string $final_url Destination URL when via=redirect.
	 * }
	 */
	public static function resolve( $url ) {
		$url = (string) $url;
		$key = self::norm_path( self::path_of( $url ) );
		if ( isset( self::$memo[ $key ] ) ) {
			return self::$memo[ $key ];
		}

		$result = self::resolve_uncached( $url );
		// Bound the memo so a large warehouse sweep cannot grow it without limit.
		if ( count( self::$memo ) < 2000 ) {
			self::$memo[ $key ] = $result;
		}
		return $result;
	}

	/**
	 * Drop-in replacement for url_to_postid() that also follows redirects.
	 * Returns 0 when nothing resolves, so existing truthiness checks hold.
	 *
	 * @param string $url URL to resolve.
	 * @return int Post ID or 0.
	 */
	public static function to_post_id( $url ) {
		$r = self::resolve( $url );
		return (int) $r['post_id'];
	}

	/**
	 * Human-readable note for an unresolved or indirectly resolved URL.
	 * Replaces the old speculative "Custom Permalinks / host mismatch?" text,
	 * which guessed at a cause and sent a real investigation down the wrong path.
	 *
	 * @param array $r Result from resolve().
	 * @return string Empty string when via=direct (nothing worth saying).
	 */
	public static function explain( $r ) {
		$via = isset( $r['via'] ) ? $r['via'] : 'unresolved';
		switch ( $via ) {
			case 'direct':
				return '';
			case 'path':
				return 'Resolved by slug path, not by permalink (rewrite prefix in play).';
			case 'custom_permalink':
				return 'Resolved via the Custom Permalinks postmeta override — WordPress core routing cannot see this URL.';
			case 'redirect':
				return sprintf(
					'Redirected (%d) to %s — metrics belong to that post.',
					(int) $r['code'],
					(string) $r['final_url']
				);
			case 'gone':
				return 'Retired deliberately: 410 Gone in the redirect table. Not an error, and not a candidate for a 301.';
			case 'loop':
				return 'Redirect loop detected — the chain does not terminate. Fix the redirect table.';
		}
		return 'No post, no redirect rule, and no matching slug. Likely deleted without a redirect, or a URL from another host.';
	}

	/**
	 * Clear caches. Called after redirect changes apply, and by tests.
	 */
	public static function flush() {
		self::$memo         = array();
		self::$redirect_map = null;
		self::$cp_map       = null;
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'cc_assistant_redirect_map' );
			delete_transient( 'cc_assistant_custom_permalink_map' );
		}
	}

	/* --------------------------------------------------------------------
	 * Internals
	 * ----------------------------------------------------------------- */

	private static function resolve_uncached( $url ) {
		$base = array(
			'post_id'   => 0,
			'via'       => 'unresolved',
			'hops'      => 0,
			'code'      => 0,
			'final_url' => '',
		);

		if ( '' === trim( (string) $url ) ) {
			return $base;
		}

		// Step 1 + 2: direct permalink, then slug path.
		$direct = self::resolve_local( $url );
		if ( $direct['post_id'] > 0 ) {
			return array_merge( $base, $direct );
		}

		// Step 3: follow the redirect table.
		$map = self::redirect_map();
		if ( empty( $map['exact'] ) && empty( $map['start'] ) ) {
			return $base;
		}

		$path = self::norm_path( self::path_of( $url ) );
		$seen = array();
		$hops = 0;

		while ( $hops < self::MAX_HOPS ) {
			if ( isset( $seen[ $path ] ) ) {
				return array_merge( $base, array( 'via' => 'loop', 'hops' => $hops ) );
			}
			$seen[ $path ] = true;

			$rule = self::match_rule( $map, $path );
			if ( null === $rule ) {
				break;
			}
			$hops++;

			if ( 410 === (int) $rule['code'] || '' === trim( (string) $rule['target'] ) ) {
				return array_merge( $base, array( 'via' => 'gone', 'hops' => $hops, 'code' => (int) $rule['code'] ) );
			}

			$target = (string) $rule['target'];
			$local  = self::resolve_local( $target );
			if ( $local['post_id'] > 0 ) {
				return array(
					'post_id'   => $local['post_id'],
					'via'       => 'redirect',
					'hops'      => $hops,
					'code'      => (int) $rule['code'],
					'final_url' => $target,
				);
			}

			// Destination is itself redirected — keep walking.
			$path = self::norm_path( self::path_of( $target ) );
		}

		if ( $hops >= self::MAX_HOPS ) {
			return array_merge( $base, array( 'via' => 'loop', 'hops' => $hops ) );
		}
		return $base;
	}

	/**
	 * Permalink lookup, then the slug-path fallbacks. This is the chain that
	 * previously lived only in class-internal-links.php.
	 *
	 * @param string $url URL or path.
	 * @return array {post_id, via}
	 */
	private static function resolve_local( $url ) {
		$abs = self::absolutize( $url );
		$id  = (int) url_to_postid( $abs );
		if ( $id > 0 ) {
			return array( 'post_id' => $id, 'via' => 'direct' );
		}

		// A URL on someone else's host must never fall through to the slug
		// lookup below. Search Console domain properties report sibling hosts,
		// and matching purely on path would attribute another site's metrics
		// to a local post that merely shares a slug — resolved-but-wrong,
		// which is worse than unresolved because nothing flags it.
		if ( ! self::is_local_host( $url ) ) {
			return array( 'post_id' => 0, 'via' => 'unresolved' );
		}

		$path = trim( (string) self::path_of( $url ), '/' );
		if ( '' === $path ) {
			return array( 'post_id' => 0, 'via' => 'unresolved' );
		}

		// Strip a language prefix (Polylang/WPML) before slug matching.
		if ( preg_match( '#^([a-z]{2})/(.+)$#i', $path, $pm ) ) {
			$langs = array( 'en', 'es', 'fr', 'de', 'it', 'pt', 'ja', 'zh', 'ko', 'ar', 'ru', 'nl' );
			if ( in_array( strtolower( $pm[1] ), $langs, true ) ) {
				$path = $pm[2];
			}
		}

		$types = array( 'page', 'post' );
		$page  = get_page_by_path( $path, OBJECT, $types );
		if ( ! ( $page instanceof WP_Post ) ) {
			// Virtual rewrite prefixes (e.g. /services/X/ where X lives at root).
			$segments = explode( '/', $path );
			$last     = end( $segments );
			if ( $last && $last !== $path ) {
				$page = get_page_by_path( $last, OBJECT, $types );
			}
		}
		if ( $page instanceof WP_Post ) {
			return array( 'post_id' => (int) $page->ID, 'via' => 'path' );
		}

		// Custom Permalinks stores an arbitrary URL in postmeta and routes it
		// itself, so neither url_to_postid() nor get_page_by_path() can see it.
		// This is why swapped service pages reported as orphaned/buried across
		// four sites: the link graph could not map their live URLs back to a
		// post, so every inbound link to them counted as zero.
		$cp = self::custom_permalink_map();
		$key = self::norm_path( $path );
		if ( isset( $cp[ $key ] ) ) {
			return array( 'post_id' => (int) $cp[ $key ], 'via' => 'custom_permalink' );
		}

		return array( 'post_id' => 0, 'via' => 'unresolved' );
	}

	/**
	 * normalized custom_permalink path => post ID.
	 * One query per request, transient-cached like the redirect map.
	 *
	 * @return array
	 */
	private static function custom_permalink_map() {
		if ( null !== self::$cp_map ) {
			return self::$cp_map;
		}
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( 'cc_assistant_custom_permalink_map' );
			if ( is_array( $cached ) ) {
				self::$cp_map = $cached;
				return self::$cp_map;
			}
		}

		$map = array();
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' LIMIT %d",
					'custom_permalink',
					5000
				),
				ARRAY_A
			);
			foreach ( (array) $rows as $r ) {
				$k = self::norm_path( self::path_of( (string) $r['meta_value'] ) );
				if ( '' !== $k && ! isset( $map[ $k ] ) ) {
					$map[ $k ] = (int) $r['post_id'];
				}
			}
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'cc_assistant_custom_permalink_map', $map, 15 * MINUTE_IN_SECONDS );
		}
		self::$cp_map = $map;
		return $map;
	}

	/**
	 * Match a normalized path against the redirect map.
	 * Exact match wins; then the longest "start" prefix, so a specific rule
	 * beats a broad one regardless of table order.
	 *
	 * @param array  $map  Redirect map.
	 * @param string $path Normalized path.
	 * @return array|null {target, code} or null.
	 */
	private static function match_rule( $map, $path ) {
		if ( isset( $map['exact'][ $path ] ) ) {
			return $map['exact'][ $path ];
		}
		$best     = null;
		$best_len = -1;
		foreach ( $map['start'] as $prefix => $rule ) {
			if ( '' === $prefix ) {
				continue;
			}
			if ( 0 === strpos( $path, $prefix ) && strlen( $prefix ) > $best_len ) {
				$best     = $rule;
				$best_len = strlen( $prefix );
			}
		}
		return $best;
	}

	/**
	 * Build the normalized redirect map.
	 *
	 * Deliberately reads through one provider rather than hardcoding a table
	 * name at the call site: the storage-agnostic axis here is "something maps
	 * old paths to new ones", and a second provider (Redirection, Yoast
	 * Premium) should slot in without touching the resolver.
	 *
	 * @return array {exact: path=>rule, start: prefix=>rule}
	 */
	private static function redirect_map() {
		if ( null !== self::$redirect_map ) {
			return self::$redirect_map;
		}
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( 'cc_assistant_redirect_map' );
			if ( is_array( $cached ) && isset( $cached['exact'], $cached['start'] ) ) {
				self::$redirect_map = $cached;
				return self::$redirect_map;
			}
		}

		$map = array( 'exact' => array(), 'start' => array() );
		foreach ( self::redirect_rows() as $row ) {
			$code   = (int) $row['code'];
			$target = (string) $row['target'];
			foreach ( $row['sources'] as $src ) {
				$pattern = self::norm_path( self::path_of( $src['pattern'] ) );
				if ( '' === $pattern ) {
					continue;
				}
				$rule = array( 'target' => $target, 'code' => $code );
				$cmp  = $src['comparison'];
				if ( 'start' === $cmp ) {
					$map['start'][ $pattern ] = $rule;
				} elseif ( 'exact' === $cmp || '' === $cmp ) {
					$map['exact'][ $pattern ] = $rule;
				}
				// contains/end/regex are intentionally not indexed: matching them
				// correctly means running the SEO plugin's own matcher, and a
				// wrong guess here would silently misattribute traffic. They stay
				// unresolved, which is honest, rather than resolved-but-wrong.
			}
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'cc_assistant_redirect_map', $map, 15 * MINUTE_IN_SECONDS );
		}
		self::$redirect_map = $map;
		return $map;
	}

	/**
	 * Redirect-storage provider. Rank Math today; the return shape is what
	 * any other provider must produce.
	 *
	 * @return array List of {sources: [{pattern, comparison}], target, code}.
	 */
	private static function redirect_rows() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}
		$table = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT sources, url_to, header_code FROM `{$table}` WHERE status = %s LIMIT %d", 'active', 2000 ),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$sources = array();
			$decoded = maybe_unserialize( $r['sources'] );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $s ) {
					if ( is_array( $s ) && isset( $s['pattern'] ) ) {
						$sources[] = array(
							'pattern'    => (string) $s['pattern'],
							'comparison' => isset( $s['comparison'] ) ? strtolower( (string) $s['comparison'] ) : 'exact',
						);
					} elseif ( is_string( $s ) ) {
						$sources[] = array( 'pattern' => $s, 'comparison' => 'exact' );
					}
				}
			}
			if ( empty( $sources ) ) {
				continue;
			}
			$out[] = array(
				'sources' => $sources,
				'target'  => (string) $r['url_to'],
				'code'    => (int) $r['header_code'],
			);
		}
		return $out;
	}

	/** Path component of a URL or path, without query/fragment. */
	private static function path_of( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( is_array( $parts ) && isset( $parts['path'] ) ) {
			return $parts['path'];
		}
		// Bare path with no scheme/host.
		$clean = preg_replace( '/[?#].*$/', '', $url );
		return is_string( $clean ) ? $clean : '';
	}

	/** Lowercased, slash-trimmed path — the map's key form. */
	private static function norm_path( $p ) {
		return trim( strtolower( (string) $p ), '/' );
	}

	/**
	 * Is this URL on this site? A URL with no host (a bare path, or a relative
	 * redirect destination) counts as local. www and non-www are the same host.
	 *
	 * @param string $url URL or path.
	 * @return bool
	 */
	private static function is_local_host( $url ) {
		$parts = wp_parse_url( (string) $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return true;
		}
		$home = wp_parse_url( home_url( '/' ) );
		$here = isset( $home['host'] ) ? strtolower( $home['host'] ) : '';
		$there = strtolower( $parts['host'] );
		if ( '' === $here ) {
			return true;
		}
		$strip = static function ( $h ) {
			return 0 === strpos( $h, 'www.' ) ? substr( $h, 4 ) : $h;
		};
		return $strip( $here ) === $strip( $there );
	}

	/** Make a site-relative path absolute so url_to_postid() can see it. */
	private static function absolutize( $url ) {
		$url   = (string) $url;
		$parts = wp_parse_url( $url );
		if ( is_array( $parts ) && ! empty( $parts['host'] ) ) {
			return $url;
		}
		return home_url( '/' === substr( $url, 0, 1 ) ? $url : '/' . $url );
	}
}

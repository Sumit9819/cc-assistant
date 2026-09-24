<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.44 — Redirect-hygiene audit (read-only).
 *
 * The stale `301 /blog/hpv-vs-herpes/ -> homepage` that was funneling off-topic
 * relevance onto the front page was invisible to every audit — found only by
 * accident on a 409. This reads Rank Math's active redirects and flags:
 *   - funnel_to_homepage: a content URL 301'd to the homepage (passes its
 *     often off-topic signals to the money page — should be 410, or 301 to a
 *     relevant page).
 *   - redirect_chain: a destination that is itself the source of another
 *     redirect (A -> B -> C; point A straight at C).
 */
class CC_Assistant_Redirect_Audit {

	public static function audit( $limit = 500 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $found !== $table ) {
			return array(
				'available' => false,
				'message'   => 'Rank Math Redirections table not found — the module may be inactive.',
				'findings'  => array(),
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, sources, url_to, header_code, status FROM `{$table}` WHERE status = %s LIMIT %d", 'active', (int) $limit ),
			ARRAY_A
		);

		$home_path    = self::norm_path( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		$source_index = array(); // normalized source path -> redirect id (for chain detection)
		$parsed       = array();
		foreach ( (array) $rows as $r ) {
			$srcs      = self::extract_sources( $r['sources'] );
			$dest_path = self::norm_path( (string) wp_parse_url( $r['url_to'], PHP_URL_PATH ) );
			$parsed[]  = array(
				'id'        => (int) $r['id'],
				'sources'   => $srcs,
				'dest'      => (string) $r['url_to'],
				'dest_path' => $dest_path,
				'code'      => (int) $r['header_code'],
			);
			foreach ( $srcs as $s ) {
				$source_index[ self::norm_path( $s ) ] = (int) $r['id'];
			}
		}

		$findings = array();
		foreach ( $parsed as $p ) {
			$dest_is_home = ( '' === $p['dest_path'] || $p['dest_path'] === $home_path );

			foreach ( $p['sources'] as $src ) {
				$src_path = self::norm_path( $src );
				if ( $dest_is_home && 301 === $p['code'] && self::looks_like_content( $src_path ) ) {
					$findings[] = array(
						'type'        => 'funnel_to_homepage',
						'severity'    => 'warn',
						'redirect_id' => $p['id'],
						'source'      => $src,
						'destination' => $p['dest'],
						'message'     => 'Content URL 301-redirected to the homepage funnels its (often off-topic) relevance onto the front page. Use 410 Gone for retired content, or 301 to a topically-relevant page.',
					);
				}
			}

			if ( '' !== $p['dest_path'] && isset( $source_index[ $p['dest_path'] ] ) && $source_index[ $p['dest_path'] ] !== $p['id'] ) {
				$findings[] = array(
					'type'        => 'redirect_chain',
					'severity'    => 'warn',
					'redirect_id' => $p['id'],
					'source'      => implode( ', ', $p['sources'] ),
					'destination' => $p['dest'],
					'message'     => 'Destination is itself redirected (redirect #' . $source_index[ $p['dest_path'] ] . ') — a chain. Point the source directly at the final URL.',
				);
			}
		}

		return array(
			'available'        => true,
			'active_redirects' => count( $parsed ),
			'finding_count'    => count( $findings ),
			'findings'         => array_slice( $findings, 0, 50 ),
			'verdict'          => empty( $findings ) ? 'clean' : 'warn',
		);
	}

	private static function extract_sources( $raw ) {
		$out = array();
		$d   = maybe_unserialize( $raw );
		if ( is_array( $d ) ) {
			foreach ( $d as $s ) {
				if ( is_array( $s ) && isset( $s['pattern'] ) ) {
					$out[] = (string) $s['pattern'];
				} elseif ( is_string( $s ) ) {
					$out[] = $s;
				}
			}
		}
		return $out;
	}

	private static function norm_path( $p ) {
		return trim( strtolower( (string) $p ), '/' );
	}

	/** A source that looks like a real content URL (blog post / multi-word slug). */
	private static function looks_like_content( $src_path ) {
		if ( '' === $src_path ) {
			return false;
		}
		return ( 0 === strpos( $src_path, 'blog/' ) )
			|| ( substr_count( $src_path, '-' ) >= 1 && strlen( $src_path ) > 8 );
	}
}

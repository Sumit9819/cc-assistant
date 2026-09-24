<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.67 — Asset references: find where a file is used, and swap it safely.
 *
 * The problem this solves, from a real incident: a 2.6 MB PNG was loading on
 * EVERY page of a client site. It was not in any post the assistant could
 * read — it was the overlay image of a popup campaign stored in a third-party
 * plugin's custom post type. The assistant could see the file in the rendered
 * HTML, could prove the cost, and could do nothing about it, because
 * draft_update_* only reaches posts and pages.
 *
 * The wrong fix is a Brave adapter. The image was never "a Brave thing" — it
 * was a URL sitting in storage that needed to become a different URL. Build
 * that, and every plugin is reachable: popups, sliders, theme options,
 * Customizer, product galleries, page builders that have not shipped yet.
 *
 * THREE THINGS MAKE THIS NON-TRIVIAL, and all three have bitten real sites:
 *
 * 1. SERIALIZED DATA. WordPress stores arrays in postmeta/options as PHP
 *    serialize(), where every string carries its byte length: s:53:"https://…".
 *    A plain str_replace that changes the string's length leaves the prefix
 *    lying, and the option silently unserializes to false from then on —
 *    which usually reads as "the plugin lost its settings". We never
 *    str_replace serialized blobs; we unserialize, walk, and re-serialize.
 *
 * 2. JSON-ESCAPED SLASHES. Page builders (Elementor, Brave, most React-based
 *    admin UIs) store URLs inside JSON as https:\/\/example.com\/path. A
 *    search for the plain URL finds nothing and reports "not used anywhere"
 *    while the image is demonstrably on the page. Every needle is therefore
 *    expanded into variants before searching.
 *
 * 3. OBJECTS. unserialize() on attacker-controlled data instantiates classes
 *    and fires __wakeup. We allow exactly one class — stdClass, which many
 *    plugins legitimately use for settings trees and which round-trips
 *    losslessly — and REFUSE any value containing another class rather than
 *    guess. A refusal is reported to the caller as a skipped location; it is
 *    never silently dropped.
 *
 * Read paths here deliberately span ALL post types, because the whole point
 * is reaching content the write allowlist does not cover. Writing stays
 * exactly as gated as every other change: a plan is queued as a normal
 * pending change, a human approves it, and the original values ride along as
 * the revert payload.
 */
class CC_Assistant_Asset_References {

	/** Hard cap on locations returned by a single search. */
	const MAX_RESULTS = 300;

	/** Characters of surrounding text kept with each hit, for the reviewer. */
	const CONTEXT_CHARS = 90;

	/**
	 * Classes unserialize() may instantiate while walking a value. stdClass
	 * only: it holds no behaviour, has no __wakeup, and re-serializes to a
	 * byte-identical shape. Anything else means we hand the value back
	 * untouched and tell the caller why.
	 */
	const ALLOWED_CLASSES = array( 'stdClass' );

	/* ---------------------------------------------------------------------
	 * Needle variants
	 * ------------------------------------------------------------------ */

	/**
	 * Every on-disk spelling of the same URL.
	 *
	 * A single asset can appear as any of these in the same database, and
	 * missing one means missing the reference entirely:
	 *   raw               https://site.com/wp-content/uploads/a.png
	 *   JSON-escaped      https:\/\/site.com\/wp-content\/uploads\/a.png
	 *   protocol-relative //site.com/wp-content/uploads/a.png
	 *   root-relative     /wp-content/uploads/a.png
	 *   HTML-entity amp   only relevant when the URL carries a query string
	 *
	 * Ordered longest-first so replacement never leaves a partially rewritten
	 * URL behind (rewriting the root-relative form first would corrupt the
	 * absolute one that contains it).
	 */
	public static function needle_variants( $url ) {
		$url      = trim( (string) $url );
		$variants = array();

		if ( '' === $url ) {
			return $variants;
		}

		$variants[] = $url;

		// JSON-escaped slashes, as written by wp_json_encode without
		// JSON_UNESCAPED_SLASHES — the default, and therefore the common case.
		$json_escaped = str_replace( '/', '\\/', $url );
		if ( $json_escaped !== $url ) {
			$variants[] = $json_escaped;
		}

		$parts = wp_parse_url( $url );
		if ( ! empty( $parts['host'] ) && ! empty( $parts['path'] ) ) {
			$path_and_query = $parts['path'] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );

			$protocol_relative = '//' . $parts['host'] . $path_and_query;
			$variants[]        = $protocol_relative;
			$variants[]        = str_replace( '/', '\\/', $protocol_relative );

			$variants[] = $path_and_query;
			$variants[] = str_replace( '/', '\\/', $path_and_query );

			// http:// twin of an https:// URL and vice versa — mixed-protocol
			// rows survive for years in old postmeta.
			if ( isset( $parts['scheme'] ) ) {
				$other = ( 'https' === $parts['scheme'] ) ? 'http' : 'https';
				$twin  = $other . '://' . $parts['host'] . $path_and_query;
				$variants[] = $twin;
				$variants[] = str_replace( '/', '\\/', $twin );
			}
		}

		if ( false !== strpos( $url, '&' ) ) {
			$variants[] = str_replace( '&', '&amp;', $url );
		}

		$variants = array_values( array_unique( array_filter( $variants ) ) );

		usort(
			$variants,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		return $variants;
	}

	/* ---------------------------------------------------------------------
	 * Search
	 * ------------------------------------------------------------------ */

	/**
	 * Find every stored location referencing $url.
	 *
	 * Scans post_content across ALL post types, postmeta, options and
	 * termmeta. Returns a flat list of locations, each carrying enough
	 * identity to be re-found at apply time plus a human-readable label so
	 * the reviewer can tell "the homepage slider" from "a popup campaign"
	 * without opening anything.
	 */
	public static function find( $url, $limit = self::MAX_RESULTS, &$truncated = null ) {
		global $wpdb;

		$truncated = false;

		$variants = self::needle_variants( $url );
		if ( empty( $variants ) ) {
			return array();
		}

		$limit     = max( 1, min( (int) $limit, self::MAX_RESULTS ) );
		$locations = array();

		// --- posts (all post types, including CPTs the write allowlist blocks)
		$where = array();
		foreach ( $variants as $v ) {
			$where[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( $v ) . '%' );
		}
		$sql = "SELECT ID, post_type, post_status, post_title, post_content
			FROM {$wpdb->posts}
			WHERE post_status != 'auto-draft' AND ( " . implode( ' OR ', $where ) . ' )
			LIMIT ' . ( $limit + 1 );
		$rows = (array) $wpdb->get_results( $sql );
		// Each query asks for limit+1 so a full result set proves more exist.
		// Detected per table, because rows discarded below (no real hit after
		// decoding) would otherwise mask a query that really did hit its cap.
		$truncated = $truncated || count( $rows ) > $limit;
		foreach ( $rows as $row ) {
			$hits = self::count_hits( $row->post_content, $variants );
			if ( $hits < 1 ) {
				continue;
			}
			$locations[] = array(
				'kind'       => 'post_content',
				'id'         => (int) $row->ID,
				'post_type'  => $row->post_type,
				'status'     => $row->post_status,
				'label'      => sprintf( '%s #%d "%s"', $row->post_type, $row->ID, self::short( $row->post_title, 60 ) ),
				'hits'       => $hits,
				'serialized' => false,
				'context'    => self::context( $row->post_content, $variants ),
			);
		}

		// --- postmeta
		$where = array();
		foreach ( $variants as $v ) {
			$where[] = $wpdb->prepare( 'pm.meta_value LIKE %s', '%' . $wpdb->esc_like( $v ) . '%' );
		}
		$sql = "SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_title
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE " . implode( ' OR ', $where ) . '
			LIMIT ' . ( $limit + 1 );
		$rows = (array) $wpdb->get_results( $sql );
		// Each query asks for limit+1 so a full result set proves more exist.
		// Detected per table, because rows discarded below (no real hit after
		// decoding) would otherwise mask a query that really did hit its cap.
		$truncated = $truncated || count( $rows ) > $limit;
		foreach ( $rows as $row ) {
			$hits = self::count_hits( $row->meta_value, $variants );
			if ( $hits < 1 ) {
				continue;
			}
			$locations[] = array(
				'kind'       => 'postmeta',
				'id'         => (int) $row->meta_id,
				'post_id'    => (int) $row->post_id,
				'meta_key'   => $row->meta_key,
				'post_type'  => $row->post_type ? $row->post_type : '(orphan)',
				'label'      => sprintf(
					'postmeta %s on %s #%d "%s"',
					$row->meta_key,
					$row->post_type ? $row->post_type : '?',
					(int) $row->post_id,
					self::short( (string) $row->post_title, 40 )
				),
				'hits'       => $hits,
				'serialized' => is_serialized( $row->meta_value ),
				'context'    => self::context( $row->meta_value, $variants ),
			);
		}

		// --- options
		$where = array();
		foreach ( $variants as $v ) {
			$where[] = $wpdb->prepare( 'option_value LIKE %s', '%' . $wpdb->esc_like( $v ) . '%' );
		}
		// Transients are regenerated caches: rewriting them is pointless churn
		// and they may be gone by apply time. Excluded in SQL rather than in
		// the loop, because a site with a big cached blob referencing the URL
		// would otherwise spend the whole LIMIT on rows that get discarded and
		// silently push real options out of the result set.
		$sql = "SELECT option_id, option_name, option_value
			FROM {$wpdb->options}
			WHERE ( " . implode( ' OR ', $where ) . " )
			AND option_name NOT LIKE '\\_transient\\_%'
			AND option_name NOT LIKE '\\_transient\\_timeout\\_%'
			AND option_name NOT LIKE '\\_site\\_transient\\_%'
			AND option_name NOT LIKE '\\_site\\_transient\\_timeout\\_%'
			LIMIT " . ( $limit + 1 );
		$rows = (array) $wpdb->get_results( $sql );
		// Each query asks for limit+1 so a full result set proves more exist.
		// Detected per table, because rows discarded below (no real hit after
		// decoding) would otherwise mask a query that really did hit its cap.
		$truncated = $truncated || count( $rows ) > $limit;
		foreach ( $rows as $row ) {
			$hits = self::count_hits( $row->option_value, $variants );
			if ( $hits < 1 ) {
				continue;
			}
			$locations[] = array(
				'kind'        => 'option',
				'id'          => (int) $row->option_id,
				'option_name' => $row->option_name,
				'label'       => sprintf( 'option %s', $row->option_name ),
				'hits'        => $hits,
				'serialized'  => is_serialized( $row->option_value ),
				'context'     => self::context( $row->option_value, $variants ),
			);
		}

		// --- termmeta
		$where = array();
		foreach ( $variants as $v ) {
			$where[] = $wpdb->prepare( 'meta_value LIKE %s', '%' . $wpdb->esc_like( $v ) . '%' );
		}
		$sql = "SELECT meta_id, term_id, meta_key, meta_value
			FROM {$wpdb->termmeta}
			WHERE " . implode( ' OR ', $where ) . '
			LIMIT ' . ( $limit + 1 );
		$rows = (array) $wpdb->get_results( $sql );
		// Each query asks for limit+1 so a full result set proves more exist.
		// Detected per table, because rows discarded below (no real hit after
		// decoding) would otherwise mask a query that really did hit its cap.
		$truncated = $truncated || count( $rows ) > $limit;
		foreach ( $rows as $row ) {
			$hits = self::count_hits( $row->meta_value, $variants );
			if ( $hits < 1 ) {
				continue;
			}
			$locations[] = array(
				'kind'       => 'termmeta',
				'id'         => (int) $row->meta_id,
				'term_id'    => (int) $row->term_id,
				'meta_key'   => $row->meta_key,
				'label'      => sprintf( 'termmeta %s on term %d', $row->meta_key, (int) $row->term_id ),
				'hits'       => $hits,
				'serialized' => is_serialized( $row->meta_value ),
				'context'    => self::context( $row->meta_value, $variants ),
			);
		}

		// A silent slice here would be the worst possible failure: the swap
		// reports success while the old URL is still live in the rows that
		// fell off the end. Say so instead, so the caller can raise the limit
		// and re-run rather than believe the job is done.
		$truncated = $truncated || count( $locations ) > $limit;

		return array_slice( $locations, 0, $limit );
	}

	/**
	 * Occurrences of the URL, counted the way it will actually be replaced.
	 *
	 * NOT a sum of substr_count per variant: one absolute URL literally
	 * contains its own protocol-relative and root-relative variants, so
	 * summing reports 3 hits for 1 reference. Count in a single pass instead,
	 * consuming each match so the text inside it is never counted again.
	 */
	private static function count_hits( $haystack, $variants ) {
		return self::count_single_pass( (string) $haystack, $variants );
	}

	/**
	 * Non-overlapping matches of any needle, longest-first at each position —
	 * the same rule strtr() applies when doing the replacement, so the count
	 * and the rewrite can never disagree.
	 */
	private static function count_single_pass( $haystack, $needles ) {
		$count = 0;
		$len   = strlen( $haystack );
		$i     = 0;

		while ( $i < $len ) {
			$step = 0;
			foreach ( $needles as $needle ) {
				$nl = strlen( $needle );
				if ( $nl > 0 && $i + $nl <= $len && 0 === substr_compare( $haystack, $needle, $i, $nl ) ) {
					$step = $nl;
					break;
				}
			}
			if ( $step > 0 ) {
				$count++;
				$i += $step;
			} else {
				$i++;
			}
		}

		return $count;
	}

	private static function short( $text, $len ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( strlen( $text ) <= $len ) {
			return $text;
		}
		return substr( $text, 0, $len ) . '…';
	}

	/**
	 * A slice of the raw value around the first hit, so a reviewer can see
	 * what the URL is doing there (a src, a background, a JSON key) without
	 * loading the whole blob.
	 */
	private static function context( $haystack, $variants ) {
		$haystack = (string) $haystack;
		foreach ( $variants as $v ) {
			$pos = strpos( $haystack, $v );
			if ( false === $pos ) {
				continue;
			}
			$start   = max( 0, $pos - self::CONTEXT_CHARS );
			$length  = strlen( $v ) + ( self::CONTEXT_CHARS * 2 );
			$snippet = substr( $haystack, $start, $length );
			return trim( preg_replace( '/\s+/', ' ', $snippet ) );
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Safe value rewriting
	 * ------------------------------------------------------------------ */

	/**
	 * Rewrite every needle variant inside one stored value.
	 *
	 * Returns array( $new_value, $replacements, $error ). $error non-empty
	 * means nothing was changed and the caller must skip this location —
	 * never write a value this method refused.
	 */
	public static function rewrite_value( $value, $old_url, $new_url ) {
		$count = 0;

		if ( ! is_string( $value ) ) {
			return array( $value, 0, 'value is not a string' );
		}

		if ( is_serialized( $value ) ) {
			$blocker = self::serialized_blocker( $value );
			if ( '' !== $blocker ) {
				return array( $value, 0, $blocker );
			}

			$data = @unserialize( $value, array( 'allowed_classes' => self::ALLOWED_CLASSES ) ); // phpcs:ignore
			if ( false === $data && 'b:0;' !== $value ) {
				return array( $value, 0, 'value looked serialized but did not unserialize' );
			}

			$walked = self::walk( $data, $old_url, $new_url, $count );
			if ( $count < 1 ) {
				return array( $value, 0, '' );
			}

			$reserialized = serialize( $walked ); // phpcs:ignore

			// Paranoia that has earned its place: prove the value we are about
			// to store still unserializes before it goes anywhere near the DB.
			$verify = @unserialize( $reserialized, array( 'allowed_classes' => self::ALLOWED_CLASSES ) ); // phpcs:ignore
			if ( false === $verify && 'b:0;' !== $reserialized ) {
				return array( $value, 0, 're-serialized value failed verification, refused' );
			}

			return array( $reserialized, $count, '' );
		}

		// Plain strings — post_content, unserialized meta, JSON blobs. JSON
		// carries no length prefixes, so a straight replace is safe provided
		// the escaped-slash variant is in the needle list, which it is.
		return array( self::replace_variants( $value, $old_url, $new_url, $count ), $count, '' );
	}

	/**
	 * Replace every spelling of the URL in ONE left-to-right pass.
	 *
	 * The obvious implementation — str_replace per variant in a loop — re-scans
	 * text it has already rewritten. That corrupts the case where the new URL
	 * CONTAINS the old one, which is the single most common swap on a site
	 * being converted to WebP: replacing hero.jpg with hero.jpg.webp gave
	 * hero.jpg.webp.webp.webp, because after the absolute variant was written
	 * the root-relative variant matched again inside the result.
	 *
	 * strtr() takes the longest matching key at each position and never
	 * revisits what it just wrote, so a replacement containing the needle is
	 * inert. count_single_pass() applies the identical rule.
	 */
	private static function replace_variants( $subject, $old_url, $new_url, &$count ) {
		$map = array();
		foreach ( self::needle_variants( $old_url ) as $variant ) {
			if ( '' === $variant ) {
				continue;
			}
			$map[ $variant ] = self::matching_replacement( $variant, $old_url, $new_url );
		}
		if ( empty( $map ) ) {
			return $subject;
		}

		$count += self::count_single_pass( $subject, array_keys( $map ) );

		return strtr( $subject, $map );
	}

	/**
	 * Recursively rewrite strings inside an unserialized structure.
	 */
	private static function walk( $data, $old_url, $new_url, &$count ) {
		if ( is_string( $data ) ) {
			return self::replace_variants( $data, $old_url, $new_url, $count );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = self::walk( $v, $old_url, $new_url, $count );
			}
			return $data;
		}
		if ( $data instanceof stdClass ) {
			foreach ( get_object_vars( $data ) as $k => $v ) {
				$data->$k = self::walk( $v, $old_url, $new_url, $count );
			}
			return $data;
		}
		return $data;
	}

	/**
	 * The replacement spelled the same way as the variant it replaces, so a
	 * JSON-escaped hit is replaced with a JSON-escaped URL and a
	 * root-relative hit stays root-relative. Rewriting a root-relative
	 * reference into an absolute one would work, but it silently changes how
	 * the site behaves behind a proxy or on a staging clone.
	 */
	private static function matching_replacement( $variant, $old_url, $new_url ) {
		$old_shape = self::shape_of( $variant );
		foreach ( self::needle_variants( $new_url ) as $candidate ) {
			if ( self::shape_of( $candidate ) === $old_shape ) {
				return $candidate;
			}
		}
		return $new_url;
	}

	/**
	 * Classify a variant so the same shape can be found in the new URL's
	 * variant list. Order matters: test escaped forms before plain ones.
	 */
	private static function shape_of( $variant ) {
		$escaped = ( false !== strpos( $variant, '\\/' ) );
		if ( 0 === strpos( $variant, 'http' ) || 0 === strpos( $variant, 'https' ) ) {
			return $escaped ? 'absolute_escaped' : 'absolute';
		}
		if ( 0 === strpos( $variant, '//' ) || 0 === strpos( $variant, '\\/\\/' ) ) {
			return $escaped ? 'protocol_relative_escaped' : 'protocol_relative';
		}
		return $escaped ? 'root_relative_escaped' : 'root_relative';
	}

	/**
	 * Empty string when a serialized value is safe to walk, otherwise the
	 * reason to skip it. The check is on the RAW string, before any
	 * unserialize call, because the point is to avoid instantiating things.
	 */
	private static function serialized_blocker( $value ) {
		// O:<len>:"ClassName" — any class other than stdClass.
		if ( preg_match_all( '/O:\d+:"([^"]+)"/', $value, $m ) ) {
			foreach ( $m[1] as $class ) {
				if ( ! in_array( $class, self::ALLOWED_CLASSES, true ) ) {
					return sprintf( 'contains a serialized %s object; refused rather than risk corrupting it', $class );
				}
			}
		}
		// Custom-serialized closures / references we will not reconstruct.
		if ( false !== strpos( $value, 'C:' ) && preg_match( '/(^|;)C:\d+:"/', $value ) ) {
			return 'contains a custom-serialized (C:) value; refused';
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Plan
	 * ------------------------------------------------------------------ */

	/**
	 * Build a replacement manifest: every location, its rewritten value, and
	 * any refusals. This is what gets queued; apply_plan() consumes it.
	 *
	 * The manifest stores the ORIGINAL value for each location, which is what
	 * makes revert exact rather than best-effort.
	 */
	public static function plan( $old_url, $new_url, $limit = self::MAX_RESULTS ) {
		$truncated = false;
		$locations = self::find( $old_url, $limit, $truncated );

		$targets = array();
		$skipped = array();
		$total   = 0;

		foreach ( $locations as $loc ) {
			$current = self::read_location( $loc );
			if ( null === $current ) {
				$skipped[] = array_merge( $loc, array( 'reason' => 'value could not be read' ) );
				continue;
			}

			list( $new_value, $count, $error ) = self::rewrite_value( $current, $old_url, $new_url );

			if ( '' !== $error ) {
				$skipped[] = array_merge( $loc, array( 'reason' => $error ) );
				continue;
			}
			if ( $count < 1 ) {
				$skipped[] = array_merge( $loc, array( 'reason' => 'no replaceable occurrence after decoding' ) );
				continue;
			}

			$targets[] = array(
				'kind'         => $loc['kind'],
				'id'           => $loc['id'],
				'post_id'      => isset( $loc['post_id'] ) ? $loc['post_id'] : null,
				'term_id'      => isset( $loc['term_id'] ) ? $loc['term_id'] : null,
				'meta_key'     => isset( $loc['meta_key'] ) ? $loc['meta_key'] : null,
				'option_name'  => isset( $loc['option_name'] ) ? $loc['option_name'] : null,
				'label'        => $loc['label'],
				'replacements' => $count,
				'serialized'   => $loc['serialized'],
				// Kept through compact_plan() so the inbox diff can show WHERE
				// in the row the URL sits without storing the whole value.
				'context'      => isset( $loc['context'] ) ? $loc['context'] : '',
				'before'       => $current,
				'after'        => $new_value,
			);
			$total += $count;
		}

		return array(
			'old_url'            => $old_url,
			'new_url'            => $new_url,
			'targets'            => $targets,
			'skipped'            => $skipped,
			'location_count'     => count( $targets ),
			'replacement_count'  => $total,
			'truncated'          => $truncated,
		);
	}

	public static function read_location( $loc ) {
		global $wpdb;
		switch ( $loc['kind'] ) {
			case 'post_content':
				$post = get_post( (int) $loc['id'] );
				return $post ? (string) $post->post_content : null;
			case 'postmeta':
				$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", (int) $loc['id'] ) );
				return null === $v ? null : (string) $v;
			case 'option':
				$v = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_id = %d", (int) $loc['id'] ) );
				return null === $v ? null : (string) $v;
			case 'termmeta':
				$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_id = %d", (int) $loc['id'] ) );
				return null === $v ? null : (string) $v;
		}
		return null;
	}

	/* ---------------------------------------------------------------------
	 * Apply
	 * ------------------------------------------------------------------ */

	/**
	 * Strip a plan down to what is safe to store in the pending-changes row.
	 *
	 * A full plan carries the complete before/after of every location — for a
	 * page-builder post_content that is 50 KB per side, and a site-wide swap
	 * can touch dozens of rows. Persisting that would put megabytes into a
	 * single DB column and make the inbox unusable.
	 *
	 * Instead we store identity plus a SHA-1 of the current value. At apply
	 * time each row is re-read, the hash re-checked, and the rewrite computed
	 * fresh from old_url/new_url. Same drift protection, a few hundred bytes.
	 */
	/** Restore only rows we actually changed, refusing later edits. */
	public static function restore_locations( $records ) {
		global $wpdb;
		$todo = array();
		foreach ( $records as $r ) {
			$current = self::read_location( $r['location'] );
			if ( $current === $r['before'] ) { continue; }
			if ( null === $current || ! hash_equals( $r['after_hash'], hash( 'sha256', $current ) ) ) {
				return new WP_Error( 'rollback_conflict', 'An asset reference row has later edits. Roll back later operations first.' );
			}
			$r['current'] = $current;
			$todo[] = $r;
		}
		$kinds = array(
			'post_content' => array( $wpdb->posts, 'post_content', 'ID' ),
			'postmeta' => array( $wpdb->postmeta, 'meta_value', 'meta_id' ),
			'option' => array( $wpdb->options, 'option_value', 'option_id' ),
			'termmeta' => array( $wpdb->termmeta, 'meta_value', 'meta_id' ),
		);
		foreach ( $todo as $r ) {
			$loc = $r['location'];
			if ( ! isset( $kinds[ $loc['kind'] ] ) ) { return new WP_Error( 'bad_recovery_location', 'Unknown asset recovery location.' ); }
			list( $table, $field, $key ) = $kinds[ $loc['kind'] ];
			$ok = $wpdb->update( $table, array( $field => $r['before'] ), array( $key => (int) $loc['id'], $field => $r['current'] ) );
			if ( 1 !== $ok ) { return new WP_Error( 'asset_restore_failed', 'A row changed concurrently or could not be restored; inspect the recovery record.' ); }
			if ( 'post_content' === $loc['kind'] ) { clean_post_cache( (int) $loc['id'] ); }
			if ( ! empty( $loc['post_id'] ) ) { clean_post_cache( (int) $loc['post_id'] ); }
			if ( ! empty( $loc['term_id'] ) ) { wp_cache_delete( (int) $loc['term_id'], 'term_meta' ); }
			if ( ! empty( $loc['option_name'] ) ) { wp_cache_delete( $loc['option_name'], 'options' ); wp_cache_delete( 'alloptions', 'options' ); }
		}
		return true;
	}

	public static function compact_plan( $plan ) {
		$compact = $plan;
		$compact['targets'] = array();

		foreach ( ( isset( $plan['targets'] ) ? $plan['targets'] : array() ) as $t ) {
			unset( $t['after'] );
			$before = isset( $t['before'] ) ? (string) $t['before'] : '';
			unset( $t['before'] );
			$t['before_sha1'] = sha1( $before );
			$t['before_size'] = strlen( $before );
			$compact['targets'][] = $t;
		}

		return $compact;
	}

	/**
	 * Execute an approved manifest.
	 *
	 * Each target is re-read and the replacement recomputed against the bytes
	 * actually on disk right now.
	 *
	 * DRIFT HANDLING — deliberately not a blocking hash comparison.
	 *
	 * A URL swap is surgical and merge-safe in a way a full-body content
	 * replace is not: we re-read the row, change only the URL inside it, and
	 * write it back, so a concurrent edit by someone else is preserved rather
	 * than clobbered. Refusing on a changed hash therefore protects nothing.
	 *
	 * It also actively breaks the normal case. Six banner swaps queued against
	 * the same page and approved together: the first apply rewrote page #72,
	 * which changed its hash, and the remaining five all refused with "value
	 * changed after this was queued". Sibling changes collided with each other.
	 * (The plugin already learned this once — see the last_internal_apply stamp
	 * that stops post_modified doing the same thing to bulk approvals.)
	 *
	 * The real drift signal for this operation is the old URL no longer being
	 * present. That means somebody already changed it, and there is genuinely
	 * nothing to do. A changed hash is recorded and reported, never blocking.
	 *
	 * Writes go through the DB layer directly rather than update_post_meta /
	 * update_option because the value is already a fully-formed serialized or
	 * plain string; passing it back through the meta API would serialize it a
	 * second time.
	 */
	public static function apply_plan( $plan ) {
		global $wpdb;

		$written = array();
		$drifted = array();
		$failed  = array();

		$targets = isset( $plan['targets'] ) && is_array( $plan['targets'] ) ? $plan['targets'] : array();
		$old_url = isset( $plan['old_url'] ) ? (string) $plan['old_url'] : '';
		$new_url = isset( $plan['new_url'] ) ? (string) $plan['new_url'] : '';

		if ( '' === $old_url || '' === $new_url ) {
			return new WP_Error( 'plan_incomplete', 'Stored plan is missing old_url or new_url.' );
		}

		foreach ( $targets as $t ) {
			$kind  = isset( $t['kind'] ) ? $t['kind'] : '';
			$id    = isset( $t['id'] ) ? (int) $t['id'] : 0;
			$label = isset( $t['label'] ) ? $t['label'] : ( $kind . ' #' . $id );

			$current = self::read_location( array( 'kind' => $kind, 'id' => $id ) );
			if ( null === $current ) {
				$failed[] = array( 'label' => $label, 'reason' => 'row no longer exists' );
				continue;
			}
			if ( ! empty( $t['recovery_sha256'] ) && ! hash_equals( $t['recovery_sha256'], hash( 'sha256', $current ) ) ) {
				$failed[] = array( 'label' => $label, 'reason' => 'row changed after its recovery snapshot; requeue against current data' );
				continue;
			}
			// Recorded for the report, never used to refuse. See the note above.
			$changed_since_queue = isset( $t['before_sha1'] ) && sha1( $current ) !== $t['before_sha1'];

			list( $after, $count, $error ) = self::rewrite_value( $current, $old_url, $new_url );
			if ( '' !== $error ) {
				$failed[] = array( 'label' => $label, 'reason' => $error );
				continue;
			}
			if ( $count < 1 ) {
				$drifted[] = array(
					'label'  => $label,
					'reason' => 'the old URL is no longer in this row — something already changed it, so there was nothing to replace',
				);
				continue;
			}
			$t['replacements'] = $count;

			$ok = false;
			switch ( $kind ) {
				case 'post_content':
					$ok = 1 === $wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $id, 'post_content' => $current ) );
					if ( $ok ) {
						clean_post_cache( $id );
					}
					break;
				case 'postmeta':
					$ok = 1 === $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $after ), array( 'meta_id' => $id, 'meta_value' => $current ) );
					if ( $ok && ! empty( $t['post_id'] ) ) {
						wp_cache_delete( (int) $t['post_id'], 'post_meta' );
					}
					break;
				case 'option':
					$ok = 1 === $wpdb->update( $wpdb->options, array( 'option_value' => $after ), array( 'option_id' => $id, 'option_value' => $current ) );
					if ( $ok && ! empty( $t['option_name'] ) ) {
						wp_cache_delete( $t['option_name'], 'options' );
						wp_cache_delete( 'alloptions', 'options' );
					}
					break;
				case 'termmeta':
					$ok = 1 === $wpdb->update( $wpdb->termmeta, array( 'meta_value' => $after ), array( 'meta_id' => $id, 'meta_value' => $current ) );
					if ( $ok && ! empty( $t['term_id'] ) ) {
						wp_cache_delete( (int) $t['term_id'], 'term_meta' );
					}
					break;
				default:
					$failed[] = array( 'label' => $label, 'reason' => 'unknown location kind: ' . $kind );
					continue 2;
			}

			if ( $ok ) {
				$entry = array( 'label' => $label, 'replacements' => isset( $t['replacements'] ) ? (int) $t['replacements'] : 0 );
				if ( $changed_since_queue ) {
					// Transparency, not a warning: the row had changed since
					// queueing and the swap was merged into the newer content.
					$entry['note'] = 'row had changed since this was queued; the URL was swapped inside the newer content and that content was preserved';
				}
				$written[] = $entry;
			} else {
				$failed[] = array( 'label' => $label, 'reason' => 'database update failed' );
			}
		}

		return array(
			'written' => $written,
			'drifted' => $drifted,
			'failed'  => $failed,
		);
	}
}

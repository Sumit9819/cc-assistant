<?php
/**
 * Query tagging — brand detection, intent classification, URL canonicalization.
 *
 * Pure heuristics, no external services. Used by GSC trend lenses to filter
 * brand queries (which inflate every metric) and group queries by intent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Query_Tagger {

	const INTENT_INFORMATIONAL = 'informational';
	const INTENT_COMMERCIAL    = 'commercial';
	const INTENT_TRANSACTIONAL = 'transactional';
	const INTENT_NAVIGATIONAL  = 'navigational';
	const INTENT_LOCAL         = 'local';
	const INTENT_BRANDED       = 'branded';
	const INTENT_OTHER         = 'other';

	public static function brand_terms() {
		$terms = (array) get_option( 'cc_assistant_brand_terms', array() );
		$terms = array_filter( array_map( 'trim', $terms ) );
		if ( empty( $terms ) ) {
			$terms = self::derive_brand_terms( get_bloginfo( 'name' ), home_url() );
		}
		return array_values( array_unique( array_map( 'mb_strtolower', $terms ) ) );
	}

	private static function derive_brand_terms( $site_name, $site_url ) {
		$out = array();
		$site_name = trim( (string) $site_name );
		if ( '' !== $site_name ) {
			$out[] = $site_name;
			$out[] = preg_replace( '/\s+/', '', $site_name ); // joined
		}
		$host = wp_parse_url( $site_url, PHP_URL_HOST );
		if ( $host ) {
			$host = preg_replace( '/^www\./i', '', $host );
			$out[] = $host;
			$first = explode( '.', $host );
			if ( ! empty( $first[0] ) && strlen( $first[0] ) > 2 ) {
				$out[] = $first[0];
			}
		}
		return array_filter( $out );
	}

	public static function is_brand_query( $query, $brand_terms = null ) {
		if ( null === $brand_terms ) {
			$brand_terms = self::brand_terms();
		}
		if ( empty( $brand_terms ) ) {
			return false;
		}
		$q = mb_strtolower( (string) $query );
		foreach ( $brand_terms as $term ) {
			if ( '' === $term ) {
				continue;
			}
			if ( mb_strpos( $q, mb_strtolower( $term ) ) !== false ) {
				return true;
			}
		}
		return false;
	}

	public static function classify_intent( $query ) {
		$q = mb_strtolower( trim( (string) $query ) );
		if ( '' === $q ) {
			return self::INTENT_OTHER;
		}

		if ( self::is_brand_query( $q ) ) {
			return self::INTENT_BRANDED;
		}

		// Local
		if ( preg_match( '/\b(near me|nearby|in [a-z]{2,}\b|open now|hours|directions)\b/u', $q ) ) {
			return self::INTENT_LOCAL;
		}

		// Transactional
		if ( preg_match( '/\b(buy|order|coupon|discount|deal|free shipping|cheap|price|cost|book|hire|sign up|signup|subscribe|download|install|demo|trial|quote|appointment)\b/u', $q ) ) {
			return self::INTENT_TRANSACTIONAL;
		}

		// Commercial investigation
		if ( preg_match( '/\b(best|top|review|reviews|vs|versus|comparison|compare|alternative|alternatives|cheapest|fastest|safest|recommended)\b/u', $q ) ) {
			return self::INTENT_COMMERCIAL;
		}

		// Informational
		if ( preg_match( '/^(how|what|why|when|where|who|which|can|does|do|is|are|will)\b/u', $q )
			|| preg_match( '/\b(meaning|definition|examples?|guide|tutorial|tips|ideas|history)\b/u', $q ) ) {
			return self::INTENT_INFORMATIONAL;
		}

		// Navigational (proper noun-y, short, no question words)
		if ( str_word_count( $q ) <= 3 && preg_match( '/\b(login|signin|sign in|account|dashboard|portal|app)\b/u', $q ) ) {
			return self::INTENT_NAVIGATIONAL;
		}

		return self::INTENT_OTHER;
	}

	/**
	 * Normalize a URL for stable per-page comparisons:
	 *   - drop scheme (http vs https variance)
	 *   - drop leading www.
	 *   - strip query string + fragment
	 *   - lowercase host
	 *   - collapse trailing slash
	 *   - decode percent-encoded path
	 */
	public static function canonicalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return $url;
		}
		$host = isset( $parts['host'] ) ? mb_strtolower( $parts['host'] ) : '';
		$host = preg_replace( '/^www\./', '', $host );
		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		$path = '/' . ltrim( rawurldecode( $path ), '/' );
		if ( '/' !== $path && '/' === substr( $path, -1 ) ) {
			$path = rtrim( $path, '/' );
		}
		return $host . $path;
	}

	public static function canonical_hash( $url ) {
		$canon = self::canonicalize_url( $url );
		return '' === $canon ? '' : sha1( $canon );
	}
}

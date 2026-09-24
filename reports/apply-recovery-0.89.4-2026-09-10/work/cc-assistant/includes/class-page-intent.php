<?php
/**
 * CC Assistant — Page & Query Intent classifier (global, site-agnostic).
 *
 * Implements the search-intent doctrine that governs every build/optimize:
 *   - One page serves ONE intent. Service/landing pages are transactional/local;
 *     blog/article pages are informational. Mixing intents satisfies neither.
 *   - Healthcare splits into a "business mode": EMERGENCY (freestanding ER —
 *     walk-in/24-7/call-now, transactional+local) vs SCHEDULED (wellness clinic,
 *     med-spa — book-a-consultation/appointment, commercial+transactional).
 *     The 3 ERs and the 1 wellness clinic are treated OPPOSITELY above the fold.
 *
 * Pure heuristics + cached corpus scan. No external calls. Never fatal.
 *
 * @package CC_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Page_Intent {

	/** Operator override option. Values: emergency|scheduled|generic|'' (auto). */
	const MODE_OPTION = 'cc_assistant_business_mode';

	/** Intent families. ACTION = conversion pages; RESEARCH = article/blog pages. */
	const ACTION_INTENTS   = array( 'transactional', 'local' );
	const RESEARCH_INTENTS = array( 'informational', 'commercial' );

	/**
	 * Healthcare business mode: emergency | scheduled | generic.
	 * Operator override wins; otherwise auto-detected from the title corpus.
	 *
	 * @return string
	 */
	public static function business_mode() {
		$override = (string) get_option( self::MODE_OPTION, '' );
		if ( in_array( $override, array( 'emergency', 'scheduled', 'generic' ), true ) ) {
			return $override;
		}
		return self::detect_business_mode();
	}

	/**
	 * Whether the mode came from an operator override (vs auto-detect).
	 *
	 * @return string 'manual'|'auto'
	 */
	public static function mode_source() {
		$override = (string) get_option( self::MODE_OPTION, '' );
		return in_array( $override, array( 'emergency', 'scheduled', 'generic' ), true ) ? 'manual' : 'auto';
	}

	/**
	 * Auto-detect emergency vs scheduled from site name + recent title corpus.
	 * Cached 12h in a transient (corpus scan is the only cost).
	 *
	 * @return string
	 */
	public static function detect_business_mode() {
		$cached = get_transient( 'cc_business_mode' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$blob = ' ' . get_bloginfo( 'name' ) . ' ' . get_bloginfo( 'description' ) . ' ';
		$q    = new WP_Query(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $q->posts as $pid ) {
			$blob .= ' ' . get_the_title( $pid );
		}
		$blob = strtolower( $blob );

		$emergency = preg_match_all( '/\b(emergency room|emergency care|freestanding er|er of |24\/?7|24[- ]?hour|walk[- ]?in er|er near|emergency department|stroke care|chest pain|trauma)\b/', $blob );
		$scheduled = preg_match_all( '/\b(book (?:an|your)|appointment|schedule (?:a|your)|consultation|wellness|med ?spa|aesthetic|membership|new patient|hormone|iv therapy|weight loss|botox|facial|massage)\b/', $blob );

		$mode = 'generic';
		if ( $emergency >= 2 && $emergency >= $scheduled ) {
			$mode = 'emergency';
		} elseif ( $scheduled >= 2 && $scheduled > $emergency ) {
			$mode = 'scheduled';
		}

		set_transient( 'cc_business_mode', $mode, 12 * HOUR_IN_SECONDS );
		return $mode;
	}

	/** Clear the cached mode (call after content changes that would shift it). */
	public static function flush_mode_cache() {
		delete_transient( 'cc_business_mode' );
	}

	/**
	 * Classify a search query's intent.
	 *
	 * @param string $query
	 * @return array { intent, family, signals[] }
	 */
	public static function classify_query( $query ) {
		$q = strtolower( trim( (string) $query ) );
		if ( '' === $q ) {
			return array( 'intent' => 'unknown', 'family' => 'unknown', 'signals' => array() );
		}

		$signals = array();

		$geo_terms = get_option( 'cc_assistant_geo_terms', array() );
		$geo_re    = '';
		if ( is_array( $geo_terms ) && ! empty( $geo_terms ) ) {
			$escaped = array_map(
				function ( $t ) {
					return preg_quote( strtolower( (string) $t ), '/' );
				},
				$geo_terms
			);
			$escaped = array_filter( $escaped );
			if ( $escaped ) {
				$geo_re = '/\b(?:' . implode( '|', $escaped ) . ')\b/';
			}
		}

		$is_local    = preg_match( '/\bnear me\b|\bopen now\b|\bnearby\b|\bnear by\b/', $q ) || ( $geo_re && preg_match( $geo_re, $q ) );
		// STRONG informational: a question, even one that names a place ("when to
		// go to the emergency room"), is research intent — the word "emergency"
		// alone must NOT make it a local/action query.
		$strong_info = preg_match( '/^(?:what|how|why|when|where|who|can|should|is|are|does|do|will)\b/', $q )
			|| preg_match( '/\bwhen to\b|\bwhen should\b|\bshould i\b|\bsymptoms?\b|\bcauses?\b|\bsigns? of\b|\bwhat is\b|\bhow to\b/', $q );
		$is_urgency  = preg_match( '/\bemergency\b|\b24\/?7\b|\bwalk[- ]?in\b|\bsame[- ]?day\b|\btonight\b|\bright now\b/', $q );
		$is_trans    = preg_match( '/\bbook\b|\bbuy\b|\border\b|\bappointment\b|\bschedule\b|\bhire\b|\bget a quote\b|\bquote\b|\bprice\b|\bpricing\b|\bcost\b|\bcheap\b|\bnear me\b/', $q );
		$is_comm     = preg_match( '/\bbest\b|\btop\b|\bvs\.?\b|\bversus\b|\breview(?:s)?\b|\bcompar|\balternative|\bcheapest\b/', $q );
		$is_info     = preg_match( '/\bmeaning\b|\bdefinition\b|\bguide\b|\btreatment\b|\bremed|\boverview\b|\bexplained\b/', $q );

		// Precedence (doctrine-correct): proximity > informational question >
		// transactional verb > bare urgency > commercial compare > broad info.
		if ( $is_local ) {
			$signals[] = 'local/proximity';
			return array( 'intent' => 'local', 'family' => 'action', 'signals' => $signals );
		}
		if ( $strong_info ) {
			$signals[] = 'informational question';
			return array( 'intent' => 'informational', 'family' => 'research', 'signals' => $signals );
		}
		if ( $is_trans ) {
			$signals[] = 'transactional verb';
			return array( 'intent' => 'transactional', 'family' => 'action', 'signals' => $signals );
		}
		if ( $is_urgency ) {
			$signals[] = 'urgency';
			return array( 'intent' => 'local', 'family' => 'action', 'signals' => $signals );
		}
		if ( $is_comm ) {
			$signals[] = 'comparison/commercial';
			return array( 'intent' => 'commercial', 'family' => 'research', 'signals' => $signals );
		}
		if ( $is_info ) {
			$signals[] = 'informational phrasing';
			return array( 'intent' => 'informational', 'family' => 'research', 'signals' => $signals );
		}

		// Bare noun query (e.g. "abdominal pain") — ambiguous, lean informational.
		$signals[] = 'bare-noun default';
		return array( 'intent' => 'informational', 'family' => 'research', 'signals' => $signals );
	}

	/**
	 * Classify a page: its type and the intent it SHOULD serve.
	 *
	 * @param int   $post_id
	 * @param array $opts { format?: string from win-audit, plain?: string body text }
	 * @return array { page_type, natural_intent, family, mode, signals[] }
	 */
	public static function classify_page( $post_id, $opts = array() ) {
		$post = get_post( $post_id );
		$mode = self::business_mode();
		if ( ! $post ) {
			return array( 'page_type' => 'unknown', 'natural_intent' => 'unknown', 'family' => 'unknown', 'mode' => $mode, 'signals' => array( 'no post' ) );
		}

		$signals = array();
		$is_front = (int) get_option( 'page_on_front' ) === (int) $post_id;
		$slug     = (string) $post->post_name;
		$permalink = (string) get_permalink( $post_id );
		$title    = strtolower( (string) $post->post_title );

		// Homepage: conversion-first. Intent family follows business mode.
		if ( $is_front ) {
			$signals[] = 'front page';
			$intent = ( 'scheduled' === $mode ) ? 'commercial' : 'local';
			return array( 'page_type' => 'home', 'natural_intent' => $intent, 'family' => 'action', 'mode' => $mode, 'signals' => $signals );
		}

		// Blog posts are informational by default.
		if ( 'post' === $post->post_type ) {
			$signals[] = 'post type=post';
			return array( 'page_type' => 'blog', 'natural_intent' => 'informational', 'family' => 'research', 'mode' => $mode, 'signals' => $signals );
		}

		// Pages: service/landing vs informational page.
		$is_service = false;
		if ( false !== strpos( $permalink, '/services/' ) || false !== strpos( $slug, 'service' ) ) {
			$is_service = true;
			$signals[]  = '/services/ path';
		}
		if ( preg_match( '/\b(treatment|care|services?|repair|installation|removal|therapy|emergency|clinic|consultation)\b/', $title ) ) {
			$is_service = true;
			$signals[]  = 'service noun in title';
		}
		// win-audit format signal (service page emits CTA verbs).
		if ( isset( $opts['format'] ) && 'service' === $opts['format'] ) {
			$is_service = true;
			$signals[]  = 'win-audit format=service';
		}

		if ( $is_service ) {
			$intent = ( 'scheduled' === $mode ) ? 'transactional' : 'local';
			return array( 'page_type' => 'service', 'natural_intent' => $intent, 'family' => 'action', 'mode' => $mode, 'signals' => $signals );
		}

		$signals[] = 'generic page default';
		return array( 'page_type' => 'page', 'natural_intent' => 'commercial', 'family' => 'research', 'mode' => $mode, 'signals' => $signals );
	}

	/**
	 * Does a query's intent belong on this page, or does it belong on a spoke?
	 *
	 * @param string $query_family  'action'|'research'|'unknown'
	 * @param string $page_family   'action'|'research'|'unknown'
	 * @return array { match: bool, advice: string }
	 */
	public static function families_align( $query_family, $page_family ) {
		if ( 'unknown' === $query_family || 'unknown' === $page_family ) {
			return array( 'match' => true, 'advice' => '' ); // can't judge — don't penalize.
		}
		if ( $query_family === $page_family ) {
			return array( 'match' => true, 'advice' => '' );
		}
		if ( 'research' === $query_family && 'action' === $page_family ) {
			return array(
				'match'  => false,
				'advice' => 'This is a research-intent query but the page is a conversion (service/landing) page. A service page should NOT chase this query — build a blog spoke that answers it and internally link the spoke to this service page. Target the service page at transactional/local queries (e.g. "[service] near me", "walk in [service] [city]").',
			);
		}
		return array(
			'match'  => false,
			'advice' => 'This is an action-intent query but the page is informational. The conversion belongs on a dedicated service/landing page; point this article at it via an inline link rather than trying to convert here.',
		);
	}

	/** Family for an intent string. */
	public static function family_of( $intent ) {
		if ( in_array( $intent, self::ACTION_INTENTS, true ) ) {
			return 'action';
		}
		if ( in_array( $intent, self::RESEARCH_INTENTS, true ) ) {
			return 'research';
		}
		return 'unknown';
	}
}

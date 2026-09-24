<?php
/**
 * Elementor popup support (v0.50).
 *
 * Three jobs, shared by every write path that can carry a popup-open link:
 *
 *   1. popup_action_url() — emit the EXACT Elementor action-link format a
 *      button needs to open an Elementor Pro popup:
 *      #elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3D{base64 of
 *      {"id":"294","toggle":false}} (key order matters — id first).
 *
 *   2. list_popups() — inventory every popup template (elementor_library
 *      posts whose _elementor_template_type = popup) with its raw display
 *      conditions and a derived coverage summary, so the AI never has to
 *      scrape action URLs out of live HTML again.
 *
 *   3. The condition guard — covers_post() + coverage_warnings(). Elementor
 *      only loads a popup document on pages matching its display conditions;
 *      a popup-open button on a non-covered page silently no-ops (shipped
 *      broken in production once). Every queue path that carries a popup
 *      action URL runs this and attaches a WARN-ONLY notice. Never blocks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Popups {

	/**
	 * URL-encoded Elementor action-link prefix for popup:open.
	 * Elementor emits (and its frontend handler expects) the %3A/%3D/%26
	 * encoded form inside href attributes — do not "clean it up".
	 */
	const ACTION_PREFIX = '#elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3D';

	/**
	 * Build the exact popup-open action URL for a popup id.
	 * base64( {"id":"294","toggle":false} ) — string id, id key FIRST —
	 * matches Elementor's own output byte-for-byte
	 * (eyJpZCI6IjI5NCIsInRvZ2dsZSI6ZmFsc2V9 for id 294).
	 *
	 * @param int $popup_id Popup template post id.
	 * @return string
	 */
	public static function popup_action_url( $popup_id ) {
		$settings = array(
			'id'     => (string) (int) $popup_id,
			'toggle' => false,
		);
		return self::ACTION_PREFIX . base64_encode( wp_json_encode( $settings ) );
	}

	/**
	 * Replace link.popup_id in a widget settings payload with the real
	 * Elementor popup-open action link, server-side, BEFORE lint/queue.
	 * {"link": {"popup_id": 294}} -> {"link": {"url": "#elementor-action…",
	 * "is_external": "", "nofollow": ""}}.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	public static function resolve_popup_links( array $settings ) {
		if ( isset( $settings['link'] ) && is_array( $settings['link'] ) && ! empty( $settings['link']['popup_id'] ) ) {
			$popup_id = (int) $settings['link']['popup_id'];
			if ( $popup_id > 0 ) {
				$settings['link'] = array(
					'url'         => self::popup_action_url( $popup_id ),
					'is_external' => '',
					'nofollow'    => '',
				);
			}
		}
		return $settings;
	}

	/**
	 * List every Elementor popup template on this site.
	 *
	 * @param int $covers_post_id Optional. When > 0, each popup also reports
	 *                            covers_this_post (true/false/null) for that post.
	 * @return array
	 */
	public static function list_popups( $covers_post_id = 0 ) {
		$covers_post_id = (int) $covers_post_id;

		$q = new WP_Query(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'meta_key'       => '_elementor_template_type',
				'meta_value'     => 'popup',
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$popups = array();
		foreach ( $q->posts as $p ) {
			$conditions = get_post_meta( $p->ID, '_elementor_conditions', true );
			$conditions = is_array( $conditions ) ? array_values( array_map( 'strval', $conditions ) ) : array();
			$summary    = self::summarize_conditions( $conditions );

			$row = array(
				'id'             => (int) $p->ID,
				'title'          => (string) $p->post_title,
				'post_status'    => (string) $p->post_status,
				'modified'       => (string) $p->post_modified,
				'edit_url'       => admin_url( 'post.php?post=' . (int) $p->ID . '&action=elementor' ),
				'conditions'     => $conditions,
				'site_wide'      => $summary['site_wide'],
				'included_pages' => $summary['included_pages'],
				'popup_open_url' => self::popup_action_url( (int) $p->ID ),
			);
			if ( $covers_post_id > 0 ) {
				$row['covers_this_post'] = self::covers_post( $conditions, $covers_post_id );
			}
			$popups[] = $row;
		}

		$out = array(
			'popup_count' => count( $popups ),
			'popups'      => $popups,
		);
		if ( $covers_post_id > 0 ) {
			$out['covers_post_id'] = $covers_post_id;
		}
		return $out;
	}

	/**
	 * Derived condition summary: site_wide = an include/general with no
	 * narrowing excludes at all; included_pages = the post ids named in
	 * include/singular/{type}/{id} conditions.
	 *
	 * @param array $conditions Raw _elementor_conditions strings.
	 * @return array { site_wide: bool, included_pages: int[] }
	 */
	public static function summarize_conditions( $conditions ) {
		$has_general    = false;
		$has_exclude    = false;
		$included_pages = array();
		foreach ( (array) $conditions as $cond ) {
			if ( ! is_string( $cond ) || '' === trim( $cond ) ) {
				continue;
			}
			$parts = explode( '/', trim( $cond ) );
			$mode  = array_shift( $parts );
			if ( 'exclude' === $mode ) {
				$has_exclude = true;
				continue;
			}
			if ( 'include' !== $mode ) {
				continue;
			}
			if ( isset( $parts[0] ) && 'general' === $parts[0] ) {
				$has_general = true;
			}
			if ( isset( $parts[0] ) && 'singular' === $parts[0] && isset( $parts[2] ) && is_numeric( $parts[2] ) ) {
				$included_pages[] = (int) $parts[2];
			}
		}
		return array(
			'site_wide'      => ( $has_general && ! $has_exclude ),
			'included_pages' => array_values( array_unique( $included_pages ) ),
		);
	}

	/**
	 * Do these display conditions cover $post_id?
	 *
	 * Deliberately conservative: any condition string the parser does not
	 * understand yields NULL ("could not verify") rather than a guess.
	 * A matching exclude always wins (returns false).
	 *
	 * @param array $conditions Raw _elementor_conditions strings.
	 * @param int   $post_id    Target post id (0 = a page that does not exist yet).
	 * @return bool|null true = covered, false = not covered, null = unverifiable.
	 */
	public static function covers_post( $conditions, $post_id ) {
		$post_id         = (int) $post_id;
		$include_match   = false;
		$unknown_include = false;
		$unknown_exclude = false;

		foreach ( (array) $conditions as $cond ) {
			if ( ! is_string( $cond ) || '' === trim( $cond ) ) {
				continue;
			}
			$parts = explode( '/', trim( $cond ) );
			$mode  = array_shift( $parts );
			if ( 'include' !== $mode && 'exclude' !== $mode ) {
				// Not a shape we recognize at all — cannot verify.
				$unknown_include = true;
				continue;
			}
			$match = self::condition_matches( $parts, $post_id );
			if ( 'exclude' === $mode ) {
				if ( true === $match ) {
					return false; // A matching exclude always wins.
				}
				if ( null === $match ) {
					$unknown_exclude = true;
				}
			} else {
				if ( true === $match ) {
					$include_match = true;
				}
				if ( null === $match ) {
					$unknown_include = true;
				}
			}
		}

		if ( $include_match ) {
			// Covered by an include — unless an exclude we couldn't parse
			// might carve this page back out.
			return $unknown_exclude ? null : true;
		}
		// No include matched. If one was unparseable it MIGHT have matched.
		return $unknown_include ? null : false;
	}

	/**
	 * Evaluate one condition body (mode already stripped) against a post.
	 *
	 * Understood shapes:
	 *   general                       -> everywhere
	 *   singular                      -> every singular page/post
	 *   singular/front_page           -> the static front page
	 *   singular/{post_type}          -> every singular of that post type
	 *   singular/{post_type}/{id}     -> exactly that post
	 *   singular/child_of/{id}        -> descendants of that post
	 *   archive[/...]                 -> never a singular target (no match)
	 *
	 * Anything else returns null (not understood — never guess).
	 *
	 * @param array $segments Condition segments after include|exclude.
	 * @param int   $post_id  Target post id (0 = not yet created).
	 * @return bool|null
	 */
	private static function condition_matches( $segments, $post_id ) {
		$name = isset( $segments[0] ) ? (string) $segments[0] : '';

		if ( 'general' === $name ) {
			return true;
		}
		if ( 'archive' === $name ) {
			// A page/post front end is singular, never an archive.
			return false;
		}
		if ( 'singular' !== $name ) {
			return null;
		}

		// Bare include/singular: every singular page — a target page/post
		// (existing or about to be created) always qualifies.
		if ( ! isset( $segments[1] ) || '' === $segments[1] ) {
			return true;
		}

		$sub = (string) $segments[1];

		if ( 'front_page' === $sub ) {
			return ( $post_id > 0 && (int) get_option( 'page_on_front' ) === $post_id );
		}

		if ( 'child_of' === $sub ) {
			if ( ! isset( $segments[2] ) || ! is_numeric( $segments[2] ) ) {
				return null;
			}
			if ( $post_id <= 0 ) {
				return false; // A not-yet-created page has no ancestors.
			}
			return in_array( (int) $segments[2], array_map( 'intval', get_post_ancestors( $post_id ) ), true );
		}

		// singular/{post_type}/{id} — exact-post condition.
		if ( isset( $segments[2] ) && is_numeric( $segments[2] ) ) {
			return ( (int) $segments[2] === $post_id && $post_id > 0 );
		}

		// singular/{post_type} — every singular of that type.
		if ( $post_id > 0 ) {
			$ptype = get_post_type( $post_id );
			if ( ! $ptype ) {
				return null;
			}
			if ( $sub === $ptype ) {
				return true;
			}
			// A different REGISTERED post type is a definite no-match; an
			// unregistered token might be an Elementor sub-condition we
			// don't know about — don't guess.
			return post_type_exists( $sub ) ? false : null;
		}
		return null; // Type of a not-yet-created post is unknown here.
	}

	/**
	 * Find every Elementor popup:open action link in a string (raw
	 * _elementor_data JSON, a settings payload, etc.) and return the decoded
	 * popup ids. Handles both the %3A-encoded href form and the decoded
	 * form, and JSON "\/" escaping inside the base64 blob.
	 *
	 * @param string $str Haystack.
	 * @return int[] Unique popup ids.
	 */
	public static function find_popup_ids_in( $str ) {
		$ids = array();
		if ( ! is_string( $str ) || '' === $str ) {
			return $ids;
		}
		if ( ! preg_match_all(
			'~#elementor-action(?:%3A|:)action(?:%3D|=)popup(?:%3A|:)open(?:%26|&)settings(?:%3D|=)([A-Za-z0-9+/\\\\%=]+)~i',
			$str,
			$m
		) ) {
			return $ids;
		}
		foreach ( $m[1] as $blob ) {
			// JSON escapes "/" as "\/"; base64 never contains a backslash.
			$b64  = urldecode( str_replace( '\\', '', $blob ) );
			$json = base64_decode( $b64, true );
			if ( false === $json ) {
				continue;
			}
			$data = json_decode( $json, true );
			if ( is_array( $data ) && isset( $data['id'] ) && (int) $data['id'] > 0 ) {
				$ids[] = (int) $data['id'];
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * The condition guard. For each popup id about to be wired into a page,
	 * check its display conditions cover the target post and return
	 * warn-only notices in the standard {code, message} shape. Never blocks.
	 *
	 * @param int[]  $popup_ids      Popup template ids found in the payload.
	 * @param int    $target_post_id Post the link will live on (0 = a new page
	 *                               that does not exist yet).
	 * @param string $target_label   Optional human label for the target
	 *                               ("the new page"). Defaults to "post {id}".
	 * @return array[] Warning rows: { code, message, popup_id }.
	 */
	public static function coverage_warnings( $popup_ids, $target_post_id, $target_label = '' ) {
		$warnings       = array();
		$target_post_id = (int) $target_post_id;
		if ( '' === $target_label ) {
			$target_label = $target_post_id > 0 ? sprintf( 'post %d', $target_post_id ) : 'the new page';
		}

		foreach ( array_values( array_unique( array_map( 'intval', (array) $popup_ids ) ) ) as $popup_id ) {
			if ( $popup_id <= 0 ) {
				continue;
			}
			$popup = get_post( $popup_id );
			if ( ! $popup || 'elementor_library' !== $popup->post_type
				|| 'popup' !== get_post_meta( $popup_id, '_elementor_template_type', true ) ) {
				$warnings[] = array(
					'code'     => 'popup_not_found',
					'popup_id' => $popup_id,
					'message'  => sprintf(
						'A link in this change opens Elementor popup %d, but no popup template with that id exists on this site — the button will silently do nothing. Check list_popups for valid ids.',
						$popup_id
					),
				);
				continue;
			}

			$title      = '' !== $popup->post_title ? $popup->post_title : ( '#' . $popup_id );
			$conditions = get_post_meta( $popup_id, '_elementor_conditions', true );
			$conditions = is_array( $conditions ) ? array_values( array_map( 'strval', $conditions ) ) : array();
			$cond_label = empty( $conditions ) ? '(none set)' : implode( ', ', $conditions );
			$fix        = sprintf(
				'Operator fix: Elementor > Templates > Popups > edit "%s" > Publish/Update > Publish Settings > Conditions > add this page (Singular) or Entire Site, then save.',
				$title
			);

			if ( 'publish' !== $popup->post_status ) {
				$warnings[] = array(
					'code'     => 'popup_not_published',
					'popup_id' => $popup_id,
					'message'  => sprintf(
						'Popup %d ("%s") has status "%s" — Elementor only loads PUBLISHED popups, so the button will not fire on %s until the popup is published.',
						$popup_id,
						$title,
						$popup->post_status,
						$target_label
					),
				);
			}

			$covers = self::covers_post( $conditions, $target_post_id );
			if ( false === $covers ) {
				$warnings[] = array(
					'code'     => 'popup_not_displayed_on_target',
					'popup_id' => $popup_id,
					'message'  => sprintf(
						'Popup %d ("%s") will NOT open on %s: its display conditions %s do not cover that page, so Elementor never loads the popup document there and the button silently no-ops. %s',
						$popup_id,
						$title,
						$target_label,
						$cond_label,
						$fix
					),
				);
			} elseif ( null === $covers ) {
				$warnings[] = array(
					'code'     => 'popup_conditions_unverified',
					'popup_id' => $popup_id,
					'message'  => sprintf(
						'Could not verify that popup %d ("%s") displays on %s — its display conditions %s contain rules this check does not understand. Verify manually in Elementor > Templates > Popups > "%s" > Publish Settings > Conditions.',
						$popup_id,
						$title,
						$target_label,
						$cond_label,
						$title
					),
				);
			}
		}
		return $warnings;
	}

	/**
	 * Convenience: scan an encoded payload string for popup action links and
	 * return coverage warnings for the target post in one call.
	 *
	 * @param string $payload        Any string (settings JSON, raw tree JSON).
	 * @param int    $target_post_id Post the payload targets.
	 * @param string $target_label   Optional label override.
	 * @return array[]
	 */
	public static function coverage_warnings_for_payload( $payload, $target_post_id, $target_label = '' ) {
		$ids = self::find_popup_ids_in( (string) $payload );
		if ( empty( $ids ) ) {
			return array();
		}
		return self::coverage_warnings( $ids, $target_post_id, $target_label );
	}
}

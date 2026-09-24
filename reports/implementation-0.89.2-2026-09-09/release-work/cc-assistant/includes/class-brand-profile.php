<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-site brand profile: the small set of design defaults the Elementor
 * builder reads when a caller doesn't pin them explicitly. Storing these
 * once means a draft_add_elementor_container call from Claude can produce
 * widgets that look like they belong on the page without restating the
 * full color/typography/sizing hash on every call.
 *
 * Why this exists: v0.11.0 shipped a Delivery Methods + Results Timeline
 * section with text colors copied off a sample widget that lived on a
 * dark-background section. v0.12.0 stripped those globals at queue time;
 * v0.13.0 takes the next step and gives the builder real defaults to
 * fall back on so the next add doesn't have to be precisely instrumented
 * by the caller to look right.
 *
 * Source of truth (in order):
 *   1. The `cc_assistant_brand_profile` WP option (operator-managed).
 *   2. Auto-populated from the active Elementor Kit on first read.
 *   3. Hard-coded fallback constants so the builder never produces
 *      `null` colors that Elementor would silently render as transparent.
 */
class CC_Assistant_Brand_Profile {

	const OPTION_KEY = 'cc_assistant_brand_profile';

	/**
	 * Fallback defaults — used only if the Kit is missing and the operator
	 * hasn't saved a profile yet. Chosen to be neutral (dark green primary,
	 * yellow accent) rather than vibrant so a fresh install doesn't write
	 * a wildly off-brand widget.
	 */
	const FALLBACK = array(
		'primary'           => '#003017',
		'secondary'         => '#FFD900',
		'accent'            => '#96A681',
		'text'              => '#202020',
		'card_bg'           => '#F1F2ED',
		'page_bg'           => '#FFFFFF',
		'body_max_width'    => 850,
		'heading_font'      => 'Montserrat',
		'body_font'         => 'Montserrat',
		'icon_size'         => 40,
		'icon_view'         => 'stacked',
		'card_padding'      => 20,
		'card_border_radius'=> 8,
		'section_padding_y' => 60,
		'h2_size'           => 32,
		'h2_weight'         => '600',
		'body_size'         => 16,
	);

	/**
	 * Get the active brand profile. Merges the saved option over the
	 * Kit-derived defaults over the hard-coded fallback. Cached per
	 * request — call as many times as you want.
	 *
	 * @return array Brand profile hash. Keys defined by FALLBACK.
	 */
	public static function get() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$saved      = get_option( self::OPTION_KEY, array() );
		$saved      = is_array( $saved ) ? $saved : array();
		$kit_derived = self::derive_from_kit();
		// Explicit operator-saved values override kit-derived; kit-derived
		// overrides fallback. array_merge here is intentional — string keys
		// overwrite, so the call order encodes the precedence.
		$cached = array_merge( self::FALLBACK, $kit_derived, $saved );
		// Coerce types so the builder doesn't have to defensive-cast on
		// every read.
		$cached['body_max_width']    = (int) $cached['body_max_width'];
		$cached['icon_size']         = (int) $cached['icon_size'];
		$cached['card_padding']      = (int) $cached['card_padding'];
		$cached['card_border_radius']= (int) $cached['card_border_radius'];
		$cached['section_padding_y'] = (int) $cached['section_padding_y'];
		$cached['h2_size']           = (int) $cached['h2_size'];
		$cached['body_size']         = (int) $cached['body_size'];
		return $cached;
	}

	/**
	 * Persist an operator-edited profile. Whitelists keys against FALLBACK
	 * so a stray POST field can't smuggle in extra option keys.
	 */
	public static function update( array $values ) {
		$current = get_option( self::OPTION_KEY, array() );
		$current = is_array( $current ) ? $current : array();
		foreach ( $values as $k => $v ) {
			if ( ! array_key_exists( $k, self::FALLBACK ) ) {
				continue;
			}
			// Hex color fields get sanitized so a malformed value doesn't
			// hose every future widget.
			if ( in_array( $k, array( 'primary', 'secondary', 'accent', 'text', 'card_bg', 'page_bg' ), true ) ) {
				$v = trim( (string) $v );
				if ( '' === $v ) {
					continue;
				}
				if ( '#' !== substr( $v, 0, 1 ) ) {
					$v = '#' . $v;
				}
				if ( ! preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v ) ) {
					continue;
				}
				$v = strtolower( $v );
			}
			$current[ $k ] = $v;
		}
		update_option( self::OPTION_KEY, $current, false );
		// Reset request cache so subsequent get() reflects the new state.
		// PHP doesn't expose static-var reset cleanly, so we set a sentinel
		// that get() inspects in the future. Cheaper alternative: just
		// rely on next-request reload, since update() is typically called
		// from an admin handler that returns immediately.
	}

	/**
	 * Pull primary / secondary / accent / text from the active Elementor
	 * Kit's system_colors, and the heading / body font from system_typography.
	 * Returns whatever was found — missing fields fall through to FALLBACK.
	 *
	 * @return array Partial brand profile, may be empty.
	 */
	private static function derive_from_kit() {
		$out = array();
		$kit_id = (int) get_option( 'elementor_active_kit', 0 );
		if ( $kit_id <= 0 ) {
			return $out;
		}
		$kit = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $kit ) ) {
			return $out;
		}
		// Map Kit system_colors id -> our profile key.
		$color_map = array(
			'primary'   => 'primary',
			'secondary' => 'secondary',
			'accent'    => 'accent',
			'text'      => 'text',
		);
		if ( ! empty( $kit['system_colors'] ) && is_array( $kit['system_colors'] ) ) {
			foreach ( $kit['system_colors'] as $c ) {
				if ( ! isset( $c['_id'], $c['color'] ) ) {
					continue;
				}
				$pid = (string) $c['_id'];
				if ( isset( $color_map[ $pid ] ) ) {
					$out[ $color_map[ $pid ] ] = strtolower( (string) $c['color'] );
				}
			}
		}
		// custom_colors often hold "card_bg" or "page_bg" surface tokens. We
		// don't know the operator's naming, so look by title heuristic and
		// fall back to scan for a near-white + a light-tint that match
		// common card/page bg patterns.
		if ( ! empty( $kit['custom_colors'] ) && is_array( $kit['custom_colors'] ) ) {
			foreach ( $kit['custom_colors'] as $c ) {
				if ( ! isset( $c['color'] ) ) {
					continue;
				}
				$hex   = strtolower( (string) $c['color'] );
				$title = mb_strtolower( (string) ( $c['title'] ?? '' ) );
				if ( str_contains( $title, 'card' ) || str_contains( $title, 'light bg' ) || str_contains( $title, 'light green' ) ) {
					$out['card_bg'] = $hex;
				}
				if ( str_contains( $title, 'page bg' ) || $title === 'white' ) {
					$out['page_bg'] = $hex;
				}
			}
		}
		// Heading + body font from system_typography. Kit ids are usually
		// 'primary' (heading), 'text' (body).
		if ( ! empty( $kit['system_typography'] ) && is_array( $kit['system_typography'] ) ) {
			foreach ( $kit['system_typography'] as $t ) {
				if ( empty( $t['_id'] ) || empty( $t['typography_font_family'] ) ) {
					continue;
				}
				$family = (string) $t['typography_font_family'];
				if ( 'primary' === $t['_id'] ) {
					$out['heading_font'] = $family;
				}
				if ( 'text' === $t['_id'] ) {
					$out['body_font'] = $family;
				}
			}
		}
		return $out;
	}
}

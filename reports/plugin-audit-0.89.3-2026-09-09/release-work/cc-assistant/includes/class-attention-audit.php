<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Predicted-attention audit (v0.55) — the "theoretical heatmap".
 *
 * Walks the page's top-level bands (Elementor root elements / Divi root
 * sections) and computes a predicted share of visitor attention per band
 * from position, visual weight (headings, CTAs, imagery, background
 * treatment), and text density — then flags composition failures: flat
 * hierarchy, competing CTAs, CTA below the fold, missing proof next to a
 * CTA, monotone rhythm, buried H1, text walls, and (for emergency
 * archetypes) no tap-to-call above the fold.
 *
 * The scorer is a heuristic PREDICTION to be calibrated against real
 * heatmaps (Microsoft Clarity) — it makes hierarchy checkable, not perfect.
 * Core methods are pure (arrays in, arrays out) so they are testable
 * without WordPress.
 */
class CC_Assistant_Attention_Audit {

	/** Per-band position decay: band 0 gets full attention, each later band 75%. */
	const POSITION_DECAY = 0.75;

	/** Default pixel sizes when a heading has no explicit typography setting. */
	public static function default_heading_px( $tag ) {
		$map = array( 'h1' => 40, 'h2' => 32, 'h3' => 24, 'h4' => 20, 'h5' => 18, 'h6' => 16 );
		return isset( $map[ $tag ] ) ? $map[ $tag ] : 16;
	}

	/* ---------------------------------------------------------------------
	 * WP entry point
	 * ------------------------------------------------------------------- */

	public static function audit( $post_id, $archetype_override = '' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-attention-spec.php';
		$arch = CC_Assistant_Attention_Spec::for_post( $post_id, $archetype_override );

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $raw ) && '' !== $raw && '[]' !== $raw ) {
			$tree = json_decode( $raw, true );
			if ( ! is_array( $tree ) ) {
				return new WP_Error( 'bad_elementor_data', 'Could not parse _elementor_data JSON.', array( 'status' => 422 ) );
			}
			$bands    = self::extract_bands_elementor( $tree );
			$platform = 'elementor';
			$fidelity = 'full';
		} elseif ( false !== strpos( (string) $post->post_content, '[et_pb_section' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-rest-divi.php';
			$bands    = self::extract_bands_divi( (string) $post->post_content );
			$platform = 'divi';
			$fidelity = 'basic (Divi shortcodes expose less styling detail than Elementor JSON — weights are coarser)';
		} else {
			return new WP_Error( 'unsupported_builder', 'No Elementor data and no Divi sections on this post — the attention audit needs a builder layout.', array( 'status' => 422 ) );
		}

		if ( empty( $bands ) ) {
			return new WP_Error( 'empty_layout', 'The builder layout has no top-level bands.', array( 'status' => 422 ) );
		}

		$report = self::score_bands( $bands, $arch['archetype'] );

		return array(
			'post_id'   => (int) $post_id,
			'title'     => get_the_title( $post_id ),
			'platform'  => $platform,
			'fidelity'  => $fidelity,
			'archetype' => $arch['archetype'],
			'archetype_source' => $arch['source'],
			'five_second_job'  => $arch['spec']['five_second_job'],
			'bands'     => $report['bands'],
			'flags'     => $report['flags'],
			'attention_score' => $report['attention_score'],
			'top_fixes' => $report['top_fixes'],
		);
	}

	/* ---------------------------------------------------------------------
	 * Extractors — normalize both builders to the same band shape:
	 * { label, headings: [{tag, px, words}], h1: bool, cta_count, tel_link,
	 *   proof, images, text_words, bg_treatment, widget_count }
	 * ------------------------------------------------------------------- */

	public static function extract_bands_elementor( $tree ) {
		$bands = array();
		foreach ( $tree as $root ) {
			if ( ! is_array( $root ) ) {
				continue;
			}
			$band = self::empty_band();

			$settings = isset( $root['settings'] ) && is_array( $root['settings'] ) ? $root['settings'] : array();
			if ( ! empty( $settings['background_background'] ) || ! empty( $settings['background_color'] ) || ! empty( $settings['background_image']['url'] ) ) {
				$band['bg_treatment'] = 'tinted';
			}

			self::walk_elementor( $root, $band );

			$band['label'] = '' !== $band['label'] ? $band['label'] : self::fallback_label( $band );
			$bands[]       = $band;
		}
		return $bands;
	}

	private static function walk_elementor( $node, &$band ) {
		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				self::walk_elementor( $child, $band );
			}
		}

		if ( ! isset( $node['widgetType'] ) ) {
			return;
		}
		$band['widget_count']++;
		$type     = (string) $node['widgetType'];
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();

		// Generic tel: detection across ALL widget link settings (linked
		// headings, icon-list items, CTA widgets — not just buttons). Elementor
		// stores every link control as {"url":"..."}; one JSON scan covers them.
		if ( ! $band['tel_link'] ) {
			$json = json_encode( $settings );
			if ( is_string( $json ) && false !== stripos( $json, '"url":"tel:' ) ) {
				$band['tel_link'] = true;
			}
		}

		switch ( true ) {
			case 'heading' === $type || 'theme-post-title' === $type:
				// Elementor Pro's Post Title widget DEFAULTS to h1 and only
				// persists non-default settings — a missing header_size on
				// theme-post-title means h1, not h2.
				$default_tag = ( 'theme-post-title' === $type ) ? 'h1' : 'h2';
				$has_size    = isset( $settings['header_size'] );
				$tag         = $has_size ? strtolower( (string) $settings['header_size'] ) : $default_tag;
				if ( ! preg_match( '/^h[1-6]$/', $tag ) ) {
					// Explicit div/span/p: still a visual heading, never an H1.
					$tag = $has_size ? 'h2' : $default_tag;
				}
				$px = self::default_heading_px( $tag );
				if ( isset( $settings['typography_font_size']['size'] ) && is_numeric( $settings['typography_font_size']['size'] ) ) {
					$size = (float) $settings['typography_font_size']['size'];
					$unit = isset( $settings['typography_font_size']['unit'] ) ? (string) $settings['typography_font_size']['unit'] : 'px';
					if ( 'em' === $unit || 'rem' === $unit ) {
						$size *= 16;
					} elseif ( 'vw' === $unit ) {
						$size *= 10;
					}
					if ( $size >= 8 && $size <= 200 ) {
						$px = $size;
					}
				}
				$text = isset( $settings['title'] ) ? wp_strip_all_tags( (string) $settings['title'] ) : '';
				$band['headings'][] = array( 'tag' => $tag, 'px' => $px, 'words' => self::word_count( $text ) );
				if ( 'h1' === $tag ) {
					$band['h1'] = true;
				}
				if ( '' === $band['label'] && '' !== $text ) {
					$band['label'] = mb_substr( $text, 0, 60 );
				}
				break;

			case in_array( $type, array( 'button', 'call-to-action', 'form' ), true ):
				// Forms ARE the primary conversion action on consideration
				// pages (the archetype spec says so) — count them as CTAs.
				$band['cta_count']++;
				break;

			case 'image' === $type || 'image-box' === $type || 'image-carousel' === $type:
				$band['images']++;
				break;

			case in_array( $type, array( 'testimonial', 'testimonial-carousel', 'star-rating', 'reviews' ), true ):
				$band['proof'] = true;
				break;

			case 'text-editor' === $type:
				$text = isset( $settings['editor'] ) ? wp_strip_all_tags( (string) $settings['editor'] ) : '';
				$band['text_words'] += self::word_count( $text );
				if ( false !== stripos( (string) ( isset( $settings['editor'] ) ? $settings['editor'] : '' ), 'tel:' ) ) {
					$band['tel_link'] = true;
				}
				break;

			case in_array( $type, array( 'accordion', 'toggle', 'icon-list', 'icon-box', 'price-list', 'nested-accordion' ), true ):
				// Structured relief widgets: they break text walls; icon-box
				// headings were counted above only for heading widgets, so
				// give these a small text estimate instead of zero.
				$band['relief'] = true;
				break;
		}
	}

	public static function extract_bands_divi( $content ) {
		$modules = CC_Assistant_REST_Divi::parse_modules( $content );
		$bands   = array();

		foreach ( $modules as $m ) {
			if ( 'et_pb_section' !== $m['type'] || 0 !== (int) $m['depth'] ) {
				continue;
			}
			$band  = self::empty_band();
			$inner = substr( $content, $m['inner_start'], max( 0, $m['inner_end'] - $m['inner_start'] ) );

			if ( false !== strpos( (string) $m['attrs_str'], 'background_color' ) || false !== strpos( (string) $m['attrs_str'], 'background_image' ) ) {
				$band['bg_treatment'] = 'tinted';
			}

			$band['cta_count'] = substr_count( $inner, '[et_pb_button' ) + substr_count( $inner, '[et_pb_contact_form' );
			$band['images']    = substr_count( $inner, '[et_pb_image' ) + substr_count( $inner, '[et_pb_gallery' ) + substr_count( $inner, '[et_pb_fullwidth_image' );
			if ( false !== stripos( $inner, 'tel:' ) ) {
				$band['tel_link'] = true;
			}
			// Divi's standard hero (fullwidth_header) renders its H1 and
			// buttons from ATTRIBUTES, not inner markup — read them there.
			if ( preg_match_all( '/\[et_pb_fullwidth_header\b([^\]]*)\]/i', $inner, $fh, PREG_SET_ORDER ) ) {
				foreach ( $fh as $f ) {
					if ( preg_match( '/\btitle="([^"]+)"/i', $f[1], $t ) ) {
						$band['headings'][] = array( 'tag' => 'h1', 'px' => self::default_heading_px( 'h1' ), 'words' => self::word_count( $t[1] ) );
						$band['h1']         = true;
						if ( '' === $band['label'] ) {
							$band['label'] = mb_substr( $t[1], 0, 60 );
						}
					}
					foreach ( array( 'button_one_url', 'button_two_url' ) as $bk ) {
						if ( preg_match( '/\b' . $bk . '="([^"]+)"/i', $f[1], $bu ) ) {
							$band['cta_count']++;
							if ( 0 === stripos( trim( $bu[1] ), 'tel:' ) ) {
								$band['tel_link'] = true;
							}
						}
					}
				}
			}
			// Proof: testimonial modules, or a HEADING about reviews — a bare
			// substring match wrongly fired on words like "Preview".
			if ( false !== strpos( $inner, '[et_pb_testimonial' ) || preg_match( '/<h[1-6][^>]*>[^<]*\breviews?\b/i', $inner ) ) {
				$band['proof'] = true;
			}
			if ( false !== strpos( $inner, '[et_pb_accordion' ) || false !== strpos( $inner, '[et_pb_toggle' ) || false !== strpos( $inner, '[et_pb_blurb' ) ) {
				$band['relief'] = true;
			}

			if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $inner, $hm, PREG_SET_ORDER ) ) {
				foreach ( $hm as $h ) {
					$tag  = 'h' . $h[1];
					$text = wp_strip_all_tags( $h[2] );
					$band['headings'][] = array( 'tag' => $tag, 'px' => self::default_heading_px( $tag ), 'words' => self::word_count( $text ) );
					if ( 'h1' === $tag ) {
						$band['h1'] = true;
					}
					if ( '' === $band['label'] && '' !== $text ) {
						$band['label'] = mb_substr( $text, 0, 60 );
					}
				}
			}

			$band['text_words']   = self::word_count( wp_strip_all_tags( preg_replace( '/\[[^\]]*\]/', ' ', $inner ) ) );
			$band['widget_count'] = substr_count( $inner, '[et_pb_' );
			$band['label']        = '' !== $band['label'] ? $band['label'] : self::fallback_label( $band );
			$bands[]              = $band;
		}
		return $bands;
	}

	private static function empty_band() {
		return array(
			'label'        => '',
			'headings'     => array(),
			'h1'           => false,
			'cta_count'    => 0,
			'tel_link'     => false,
			'proof'        => false,
			'images'       => 0,
			'text_words'   => 0,
			'bg_treatment' => 'plain',
			'relief'       => false,
			'widget_count' => 0,
		);
	}

	/**
	 * Multibyte-aware word count. str_word_count() splits on accented
	 * characters, over-counting Spanish text by ~45% and firing the dense-
	 * prose and text-wall thresholds far too early on Polylang ES pages.
	 */
	private static function word_count( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return 0;
		}
		$n = preg_match_all( '/[\p{L}\p{N}\'\x{2019}]+/u', $text );
		return false === $n ? str_word_count( $text ) : (int) $n;
	}

	private static function fallback_label( $band ) {
		if ( $band['cta_count'] > 0 ) {
			return '(CTA band)';
		}
		if ( $band['images'] > 0 && 0 === $band['text_words'] ) {
			return '(image band)';
		}
		return '(unlabeled band)';
	}

	/* ---------------------------------------------------------------------
	 * Pure scorer
	 * ------------------------------------------------------------------- */

	public static function score_bands( $bands, $archetype = 'service_local' ) {
		$n = count( $bands );

		// 1. Raw visual weight per band (position-independent).
		$raw = array();
		foreach ( $bands as $i => $b ) {
			$w = 1.0;

			$max_px = 0;
			foreach ( $b['headings'] as $h ) {
				$max_px = max( $max_px, (float) $h['px'] );
			}
			$w += min( 1.5, $max_px / 32 );              // a 48px heading buys ~1.5
			$w += min( 1.0, $b['cta_count'] * 0.5 );      // CTAs draw the eye
			$w += min( 0.8, $b['images'] * 0.4 );         // imagery draws the eye
			$w += ( 'tinted' === $b['bg_treatment'] ) ? 0.4 : 0; // treatment contrast = landmark
			$w += $b['proof'] ? 0.2 : 0;

			// Dense unrelieved prose repels the scanner.
			if ( $b['text_words'] > 250 && ! $b['relief'] && 0 === $b['images'] ) {
				$w *= 0.6;
			}

			$raw[ $i ] = $w;
		}

		// 2. Position decay, then normalize to share-of-attention.
		$weighted = array();
		$total    = 0;
		foreach ( $raw as $i => $w ) {
			$weighted[ $i ] = $w * pow( self::POSITION_DECAY, $i );
			$total         += $weighted[ $i ];
		}

		$out_bands = array();
		foreach ( $bands as $i => $b ) {
			$out_bands[] = array(
				'index'             => $i,
				'label'             => $b['label'],
				'est_attention_pct' => $total > 0 ? round( 100 * $weighted[ $i ] / $total, 1 ) : 0,
				'visual_weight'     => round( $raw[ $i ], 2 ),
				'cta_count'         => $b['cta_count'],
				'tel_link'          => $b['tel_link'],
				'proof'             => $b['proof'],
				'images'            => $b['images'],
				'text_words'        => $b['text_words'],
				'bg_treatment'      => $b['bg_treatment'],
				'headings'          => $b['headings'],
			);
		}

		// 3. Flags.
		$flags = array();

		// Buried H1.
		$h1_band = -1;
		foreach ( $bands as $i => $b ) {
			if ( $b['h1'] ) {
				$h1_band = $i;
				break;
			}
		}
		if ( $h1_band > 0 ) {
			$flags[] = self::flag( 'buried_h1', 'major', 'H1 first appears in band ' . $h1_band . '.', 'Move the H1 into the first band — it is the anchor of the first viewport.' );
		} elseif ( -1 === $h1_band ) {
			$flags[] = self::flag( 'no_h1', 'major', 'No H1 in any band.', 'Add one H1 in the hero band.' );
		}

		// First CTA position.
		$first_cta = -1;
		foreach ( $bands as $i => $b ) {
			if ( $b['cta_count'] > 0 ) {
				$first_cta = $i;
				break;
			}
		}
		if ( -1 === $first_cta ) {
			$flags[] = self::flag( 'no_cta', 'major', 'No CTA button in any band.', 'Add one primary CTA in the hero and repeat it at the end.' );
		} elseif ( $first_cta >= 2 ) {
			$flags[] = self::flag( 'cta_below_fold', 'major', 'First CTA appears in band ' . $first_cta . ' — likely below the first two viewports.', 'Put ONE primary CTA in the hero band.' );
		}

		// Competing CTAs in the hero.
		if ( $n > 0 && $bands[0]['cta_count'] >= 3 ) {
			$flags[] = self::flag( 'competing_ctas', 'major', $bands[0]['cta_count'] . ' CTA buttons in the hero band.', 'One primary + at most one quiet secondary; a third equal action splits attention three ways.' );
		}

		// Emergency archetype: tap-to-call above the fold.
		if ( 'emergency_transactional' === $archetype ) {
			$tel_early = ( $n > 0 && $bands[0]['tel_link'] ) || ( $n > 1 && $bands[1]['tel_link'] );
			if ( ! $tel_early ) {
				$flags[] = self::flag( 'no_tel_above_fold', 'major', 'No tel: link in the first two bands on an emergency-archetype page.', 'A panicked mobile visitor must reach a tap-to-call without scrolling.' );
			}
		}

		// Proof adjacent to conversion CTAs (skip the hero, which rarely has room).
		foreach ( $bands as $i => $b ) {
			if ( 0 === $i || 0 === $b['cta_count'] ) {
				continue;
			}
			$near_proof = $b['proof']
				|| ( isset( $bands[ $i - 1 ] ) && $bands[ $i - 1 ]['proof'] )
				|| ( isset( $bands[ $i + 1 ] ) && $bands[ $i + 1 ]['proof'] );
			if ( ! $near_proof ) {
				$flags[] = self::flag( 'cta_without_proof', 'minor', 'CTA band ' . $i . ' ("' . $bands[ $i ]['label'] . '") has no proof element within one band.', 'Place a review/rating/credential immediately before the ask.' );
				break; // One flag is enough to make the point.
			}
		}

		// Flat hierarchy: 4+ consecutive bands with near-equal visual weight.
		$flat_run = 1;
		$flat_at  = -1;
		for ( $i = 1; $i < $n; $i++ ) {
			$a = $raw[ $i - 1 ];
			$b = $raw[ $i ];
			if ( $a > 0 && abs( $a - $b ) / $a <= 0.15 ) {
				$flat_run++;
				if ( $flat_run >= 4 && -1 === $flat_at ) {
					$flat_at = $i - 3;
				}
			} else {
				$flat_run = 1;
			}
		}
		if ( $flat_at >= 0 ) {
			$flags[] = self::flag( 'flat_hierarchy', 'major', '4+ consecutive bands from band ' . $flat_at . ' carry near-identical visual weight.', 'Make one of them a deliberate moment (bigger heading, tinted band, image) — a flat page reads as a list, not a story.' );
		}

		// Monotone rhythm: 5+ consecutive bands with the same background treatment.
		$mono_run = 1;
		$mono_at  = -1;
		for ( $i = 1; $i < $n; $i++ ) {
			if ( $bands[ $i ]['bg_treatment'] === $bands[ $i - 1 ]['bg_treatment'] ) {
				$mono_run++;
				if ( $mono_run >= 5 && -1 === $mono_at ) {
					$mono_at = $i - 4;
				}
			} else {
				$mono_run = 1;
			}
		}
		if ( $mono_at >= 0 ) {
			$flags[] = self::flag( 'monotone_rhythm', 'minor', '5+ consecutive bands share the same background treatment from band ' . $mono_at . '.', 'Alternate boxed/full-bleed or white/tinted so the eye gets landmarks while scrolling.' );
		}

		// Text walls at band level.
		foreach ( $bands as $i => $b ) {
			if ( $b['text_words'] > 350 && 0 === $b['images'] && ! $b['relief'] ) {
				$flags[] = self::flag( 'text_wall_band', 'minor', 'Band ' . $i . ' ("' . $b['label'] . '") has ' . $b['text_words'] . ' words with no image or structured relief.', 'Break it with an icon-list, accordion, image, or split it across two bands.' );
				break;
			}
		}

		// 4. Score: start at 100, deduct per flag.
		$score = 100;
		foreach ( $flags as $f ) {
			$score -= ( 'major' === $f['severity'] ) ? 15 : 6;
		}
		$score = max( 0, $score );

		$fixes = array();
		foreach ( $flags as $f ) {
			if ( 'major' === $f['severity'] && count( $fixes ) < 3 ) {
				$fixes[] = $f['fix'];
			}
		}
		foreach ( $flags as $f ) {
			if ( 'minor' === $f['severity'] && count( $fixes ) < 3 ) {
				$fixes[] = $f['fix'];
			}
		}

		return array(
			'bands'           => $out_bands,
			'flags'           => $flags,
			'attention_score' => $score,
			'top_fixes'       => $fixes,
		);
	}

	private static function flag( $code, $severity, $detail, $fix ) {
		return array( 'code' => $code, 'severity' => $severity, 'detail' => $detail, 'fix' => $fix );
	}
}

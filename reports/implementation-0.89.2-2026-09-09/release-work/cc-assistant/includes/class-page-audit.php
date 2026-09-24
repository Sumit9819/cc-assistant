<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.30 — Design-quality audit for a post's Elementor tree.
 *
 * Eight checks, returning a single design_score (0-100) plus per-check
 * pass/fail + violation details. Designed so the AI can call
 * audit_page_design(post_id) AFTER any build or import and catch its own
 * design mistakes before the human ever sees them.
 *
 * Checks:
 *   1. wcag_contrast        — every text-on-bg pair meets 4.5:1 (AA normal text)
 *   2. heading_order        — no skipped heading levels (H1→H3 etc.)
 *   3. heading_bold         — every heading has font-weight ≥ 600
 *   4. hover_coverage       — every interactive widget has a hover state defined
 *   5. alt_text_coverage    — every image has non-empty alt text
 *   6. brand_compliance     — every explicit hex is in the Kit's palette
 *   7. section_uniqueness   — no two ADJACENT root sections share the same
 *                              shape (widget-type sequence + background color)
 *   8. alignment_consistency — within a section, heading + body alignment match
 */
class CC_Assistant_Page_Audit {

	const NORMAL_TEXT_CONTRAST   = 4.5;
	const HEADING_BOLD_MIN       = 600;

	/**
	 * Run all checks on a post and return a structured report.
	 */
	public static function audit( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', 'post_id required' );
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return new WP_Error( 'no_elementor_data', 'Post has no Elementor data to audit.' );
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return new WP_Error( 'invalid_json', 'Could not parse _elementor_data.' );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-io.php';
		$kit_globals = CC_Assistant_Elementor_IO::read_kit_globals();
		$brand_palette = self::resolve_palette( $kit_globals );

		// v0.31: build a color resolver that can resolve hex / rgb() / inline-style /
		// __globals__ references through the Kit's actual palette, then use it
		// across contrast + brand-compliance + section-uniqueness.
		$resolver = self::build_color_resolver( $kit_globals );

		$site_url = home_url();
		$site_host = (string) wp_parse_url( $site_url, PHP_URL_HOST );

		// v0.31: industry vocabulary so the audit can flag cross-industry leakage
		// in body text (e.g. "infusion therapy" on an ER page after cloning from
		// a wellness clinic).
		require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
		$industry = CC_Assistant_Industry_Profile::industry_slug();
		$banned_phrases = CC_Assistant_Industry_Profile::banned_phrases( $industry );
		$preferred = CC_Assistant_Industry_Profile::preferred_vocabulary( $industry );

		$checks = array(
			'wcag_contrast'           => self::check_contrast( $tree, $brand_palette, $resolver ),
			'heading_order'           => self::check_heading_order( $tree ),
			'heading_bold'            => self::check_heading_bold( $tree ),
			'heading_size_hierarchy'  => self::check_heading_size_hierarchy( $tree ), // v0.31
			'hover_coverage'          => self::check_hover_coverage( $tree ),
			'alt_text_coverage'       => self::check_alt_coverage( $tree ),
			'brand_compliance'        => self::check_brand_compliance( $tree, $brand_palette ),
			'section_uniqueness'      => self::check_section_uniqueness( $tree, $resolver ),
			'alignment_consistency'   => self::check_alignment_consistency( $tree ),
			'industry_vocabulary'     => self::check_industry_vocabulary( $tree, $industry, $banned_phrases, $preferred ), // v0.31
			'cross_domain_images'     => self::check_cross_domain_images( $tree, $site_host ), // v0.31
			'schema_cross_domain'     => self::check_schema_cross_domain( $tree, $site_host ), // v0.31
			'map_address_geo'         => self::check_map_address( $tree ), // v0.31
			'empty_containers'        => self::check_empty_containers( $tree ), // v0.31
			'icon_variety'            => self::check_icon_variety( $tree ), // v0.43.3
			'button_contrast'         => self::check_button_contrast( $tree, $resolver ), // v0.43.3
		);

		// v0.44: severity tiers. "advisory" checks are pedantic heuristics — e.g.
		// the heading-size MEDIAN comparison, which fires on h3==h4 across
		// unrelated widgets even when the visual H1>H2>H3 hierarchy is correct.
		// They are labelled and, on their own, never drop the verdict below pass,
		// so a clean page stops reading as "warn" because of a non-issue.
		$advisory  = array( 'heading_size_hierarchy' );
		$hard_fail = false;
		foreach ( $checks as $name => $c ) {
			$sev = ! empty( $c['pass'] ) ? 'pass' : ( ( in_array( $name, $advisory, true ) || ! empty( $c['advisory'] ) ) ? 'advisory' : 'fail' );
			$checks[ $name ]['severity'] = $sev;
			if ( 'fail' === $sev ) {
				$hard_fail = true;
			}
		}

		$design_score = self::compute_score( $checks );
		$verdict      = $design_score >= 80 ? 'pass' : ( $design_score >= 60 ? 'warn' : 'fail' );
		if ( 'pass' !== $verdict && ! $hard_fail ) {
			$verdict = 'pass'; // only advisory heuristics are failing — not a real fail
		}

		return array(
			'post_id'      => $post_id,
			'design_score' => $design_score,
			'verdict'      => $verdict,
			'industry'     => $industry,
			'checks'       => $checks,
			'kit_palette'  => $brand_palette,
		);
	}

	// ---- v0.31 color resolver ------------------------------------------

	/**
	 * Build a closure that resolves any color reference (hex, rgb, rgba,
	 * inline style attribute, __globals__ ref) to a normalized lowercase
	 * 6-char hex. Returns '' if the input can't be resolved.
	 */
	private static function build_color_resolver( $kit_globals ) {
		$colors = isset( $kit_globals['colors'] ) ? $kit_globals['colors'] : array();
		// Token id (e.g. "primary", "164e783") -> resolved hex
		$tokens = array();
		foreach ( $colors as $id => $info ) {
			if ( isset( $info['color'] ) ) {
				$tokens[ strtolower( $id ) ] = strtolower( (string) $info['color'] );
			}
		}
		return function ( $value, $globals_ref = null ) use ( $tokens ) {
			// If a globals ref is supplied, resolve it via Kit tokens
			if ( $globals_ref && is_string( $globals_ref )
				&& preg_match( '#^globals/colors\?id=(.+)$#', $globals_ref, $m ) ) {
				$id = strtolower( $m[1] );
				if ( isset( $tokens[ $id ] ) ) {
					return $tokens[ $id ];
				}
			}
			if ( ! is_string( $value ) ) {
				return '';
			}
			$v = trim( $value );
			if ( '' === $v ) {
				return '';
			}
			// hex
			if ( '#' === substr( $v, 0, 1 ) ) {
				$h = strtolower( ltrim( $v, '#' ) );
				if ( 3 === strlen( $h ) ) {
					$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
				}
				if ( 6 === strlen( $h ) || 8 === strlen( $h ) ) {
					return '#' . substr( $h, 0, 6 );
				}
			}
			// rgb(r,g,b) or rgba(r,g,b,a). A fully transparent fill (alpha 0) is
			// NOT a color — return '' so it cascades to the section behind it,
			// instead of being mis-read as opaque white/black (the transparent
			// outline-button false-positive).
			if ( preg_match( '#rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([0-9.]+)\s*)?\)#i', $v, $m ) ) {
				if ( isset( $m[4] ) && 0.0 === (float) $m[4] ) {
					return '';
				}
				return sprintf( '#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3] );
			}
			return '';
		};
	}

	/**
	 * Resolve a widget setting's effective color by checking __globals__ ref
	 * first, then the inline value. Returns lowercase hex or ''.
	 */
	private static function resolve_widget_color( $node, $field, $resolver ) {
		$s = isset( $node['settings'] ) ? $node['settings'] : array();
		$g = isset( $s['__globals__'] ) && is_array( $s['__globals__'] ) ? $s['__globals__'] : array();
		if ( isset( $g[ $field ] ) ) {
			$hex = $resolver( '', $g[ $field ] );
			if ( '' !== $hex ) {
				return $hex;
			}
		}
		if ( isset( $s[ $field ] ) ) {
			return $resolver( $s[ $field ] );
		}
		return '';
	}

	// ---- check 1: contrast ---------------------------------------------

	private static function check_contrast( $tree, $brand_palette, $resolver ) {
		$violations = array();
		$pairs_checked = 0;
		// v0.31: cascade bg through __globals__ resolution + rgb()
		self::walk_for_contrast( $tree, '#ffffff', $resolver, $violations, $pairs_checked );
		$violations = array_slice( $violations, 0, 25 );
		return array(
			'pass'           => empty( $violations ),
			'pairs_checked'  => $pairs_checked,
			'violations'     => $violations,
			'message'        => empty( $violations )
				? sprintf( 'Contrast OK across %d resolved text/bg pairs (hex + rgb + __globals__).', $pairs_checked )
				: sprintf( '%d low-contrast text/bg pairs (need 4.5:1, resolved through globals).', count( $violations ) ),
		);
	}

	private static function walk_for_contrast( $tree, $bg_inherit, $resolver, &$violations, &$pairs_checked ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$bg = $bg_inherit;
			$cur_bg = self::resolve_widget_color( $node, 'background_color', $resolver );
			if ( $cur_bg ) {
				$bg = $cur_bg;
			}
			if ( isset( $node['widgetType'] ) ) {
				$wt = $node['widgetType'];
				$text_colors = array();
				if ( 'heading' === $wt ) {
					$hex = self::resolve_widget_color( $node, 'title_color', $resolver );
					if ( $hex ) {
						$text_colors[] = array( 'field' => 'title_color', 'hex' => $hex );
					}
				}
				if ( 'icon-box' === $wt ) {
					$hex = self::resolve_widget_color( $node, 'title_color', $resolver );
					if ( $hex ) {
						$text_colors[] = array( 'field' => 'title_color', 'hex' => $hex );
					}
					$hex2 = self::resolve_widget_color( $node, 'description_color', $resolver );
					if ( $hex2 ) {
						$text_colors[] = array( 'field' => 'description_color', 'hex' => $hex2 );
					}
				}
				if ( 'button' === $wt ) {
					$btn_bg = self::resolve_widget_color( $node, 'background_color', $resolver );
					if ( ! $btn_bg ) {
						$btn_bg = $bg;
					}
					$btn_text = self::resolve_widget_color( $node, 'button_text_color', $resolver );
					if ( $btn_text ) {
						$text_colors[] = array( 'field' => 'button_text_color', 'hex' => $btn_text, 'override_bg' => $btn_bg );
					}
				}
				// v0.31: also scan inline style="color:#xxx" in text-editor editor field
				if ( in_array( $wt, array( 'text-editor', 'html' ), true ) ) {
					$ed = isset( $node['settings']['editor'] ) ? $node['settings']['editor'] : ( isset( $node['settings']['html'] ) ? $node['settings']['html'] : '' );
					if ( is_string( $ed ) && preg_match_all( '#style="[^"]*color:\s*(#[0-9a-fA-F]{3,6}|rgba?\([^)]+\))[^"]*"#i', $ed, $matches ) ) {
						foreach ( $matches[1] as $color_val ) {
							$hex = $resolver( $color_val );
							if ( $hex ) {
								$text_colors[] = array( 'field' => 'inline_html_style', 'hex' => $hex );
							}
						}
					}
				}
				foreach ( $text_colors as $tc ) {
					if ( empty( $tc['hex'] ) ) {
						continue;
					}
					$check_bg = isset( $tc['override_bg'] ) ? $tc['override_bg'] : $bg;
					if ( empty( $check_bg ) ) {
						continue;
					}
					$ratio = self::contrast_ratio( $tc['hex'], $check_bg );
					$pairs_checked++;
					if ( $ratio < self::NORMAL_TEXT_CONTRAST ) {
						$violations[] = array(
							'widget_id'  => isset( $node['id'] ) ? $node['id'] : '',
							'widget_type' => $wt,
							'field'      => $tc['field'],
							'text_color' => $tc['hex'],
							'background' => $check_bg,
							'ratio'      => round( $ratio, 2 ),
						);
					}
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_contrast( $node['elements'], $bg, $resolver, $violations, $pairs_checked );
			}
		}
	}

	// ---- v0.31 check: heading size hierarchy ---------------------------

	private static function check_heading_size_hierarchy( $tree ) {
		$by_level = array();
		self::collect_heading_sizes( $tree, $by_level );
		$violations = array();
		// Default Elementor sizes if no explicit
		$default_px = array( 'h1' => 48, 'h2' => 32, 'h3' => 24, 'h4' => 20, 'h5' => 16, 'h6' => 14 );
		// Compute median size per level
		$medians = array();
		foreach ( $by_level as $lvl => $sizes ) {
			if ( ! $sizes ) {
				continue;
			}
			sort( $sizes );
			$medians[ $lvl ] = $sizes[ intval( count( $sizes ) / 2 ) ];
		}
		// Fill in defaults for missing levels
		foreach ( $default_px as $lvl => $def ) {
			if ( ! isset( $medians[ $lvl ] ) ) {
				$medians[ $lvl ] = $def;
			}
		}
		// H1 should be > H2 > H3
		if ( isset( $medians['h1'], $medians['h2'] ) && $medians['h1'] <= $medians['h2'] ) {
			$violations[] = array( 'a' => 'h1', 'b' => 'h2', 'a_size' => $medians['h1'], 'b_size' => $medians['h2'] );
		}
		if ( isset( $medians['h2'], $medians['h3'] ) && $medians['h2'] <= $medians['h3'] ) {
			$violations[] = array( 'a' => 'h2', 'b' => 'h3', 'a_size' => $medians['h2'], 'b_size' => $medians['h3'] );
		}
		if ( isset( $medians['h3'], $medians['h4'] ) && $medians['h3'] <= $medians['h4'] ) {
			$violations[] = array( 'a' => 'h3', 'b' => 'h4', 'a_size' => $medians['h3'], 'b_size' => $medians['h4'] );
		}
		return array(
			'pass'         => empty( $violations ),
			'medians_px'   => $medians,
			'violations'   => $violations,
			'message'      => empty( $violations )
				? sprintf( 'Heading size hierarchy intact (H1=%dpx > H2=%dpx > H3=%dpx).', $medians['h1'], $medians['h2'], $medians['h3'] )
				: sprintf( '%d heading-size hierarchy inversions (lower level larger than higher).', count( $violations ) ),
		);
	}

	private static function collect_heading_sizes( $tree, &$by_level ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType'] ) {
				$lvl = isset( $node['settings']['header_size'] ) ? strtolower( (string) $node['settings']['header_size'] ) : 'h2';
				$size_arr = isset( $node['settings']['typography_font_size'] ) ? $node['settings']['typography_font_size'] : array();
				if ( is_array( $size_arr ) && isset( $size_arr['size'] ) && $size_arr['size'] ) {
					$px = (float) $size_arr['size'];
					$unit = isset( $size_arr['unit'] ) ? $size_arr['unit'] : 'px';
					if ( 'rem' === $unit || 'em' === $unit ) {
						$px = $px * 16;
					}
					if ( ! isset( $by_level[ $lvl ] ) ) {
						$by_level[ $lvl ] = array();
					}
					$by_level[ $lvl ][] = $px;
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::collect_heading_sizes( $node['elements'], $by_level );
			}
		}
	}

	// ---- v0.31 check: industry vocabulary ------------------------------

	private static function check_industry_vocabulary( $tree, $industry, $banned_phrases, $preferred ) {
		$violations = array();
		if ( empty( $banned_phrases ) ) {
			return array(
				'pass'     => true,
				'industry' => $industry,
				'violations' => array(),
				'message'  => sprintf( 'No industry vocabulary bank defined for %s.', $industry ),
			);
		}
		self::walk_for_vocabulary( $tree, $banned_phrases, $preferred, $violations );
		$violations = array_slice( $violations, 0, 30 );
		// If the auto-detected overlay doesn't fit this site's own vocabulary
		// (playbook-fit drift/thin), the banned list belongs to the wrong
		// vertical — keep the findings but make the check advisory, never a fail.
		$advisory = false;
		$fit_note = '';
		if ( ! empty( $violations ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-playbook-fit.php';
			$fit = CC_Assistant_Playbook_Fit::audit();
			if ( is_array( $fit ) && isset( $fit['status'] ) && in_array( $fit['status'], array( 'drift_detected', 'thin' ), true ) ) {
				$advisory = true;
				$fit_note = sprintf( ' NOTE: industry overlay fit is "%s" (coverage %d%%) — these terms are likely this site\'s OWN services, not cross-industry leakage. Advisory only.', $fit['status'], isset( $fit['coverage'] ) ? (int) $fit['coverage'] : 0 );
			}
		}
		return array(
			'pass'       => empty( $violations ),
			'advisory'   => $advisory,
			'industry'   => $industry,
			'phrases_banned' => count( $banned_phrases ),
			'violations' => $violations,
			'message'    => empty( $violations )
				? sprintf( 'Body content matches %s vocabulary (no banned cross-industry phrases).', $industry )
				: sprintf( '%d body-text phrases incompatible with %s industry.', count( $violations ), $industry ) . $fit_note,
		);
	}

	private static function walk_for_vocabulary( $tree, $banned, $preferred, &$violations ) {
		$text_fields = array( 'title', 'editor', 'text', 'title_text', 'description_text', 'html', 'content' );
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$s = isset( $node['settings'] ) ? $node['settings'] : array();
			if ( is_array( $s ) ) {
				foreach ( $text_fields as $f ) {
					if ( isset( $s[ $f ] ) && is_string( $s[ $f ] ) ) {
						$lower = strtolower( wp_strip_all_tags( $s[ $f ] ) );
						foreach ( $banned as $phrase ) {
							if ( false !== strpos( $lower, strtolower( $phrase ) ) ) {
								$preferred_swap = isset( $preferred[ strtolower( $phrase ) ] ) ? $preferred[ strtolower( $phrase ) ] : '';
								$violations[] = array(
									'widget_id' => isset( $node['id'] ) ? $node['id'] : '',
									'widget_type' => isset( $node['widgetType'] ) ? $node['widgetType'] : '',
									'field' => $f,
									'banned_phrase' => $phrase,
									'preferred_replacement' => $preferred_swap,
									'snippet' => mb_substr( $s[ $f ], 0, 140 ),
								);
							}
						}
					}
				}
				// nested-accordion item_title
				if ( isset( $s['items'] ) && is_array( $s['items'] ) ) {
					foreach ( $s['items'] as $it ) {
						if ( isset( $it['item_title'] ) && is_string( $it['item_title'] ) ) {
							$lower = strtolower( $it['item_title'] );
							foreach ( $banned as $phrase ) {
								if ( false !== strpos( $lower, strtolower( $phrase ) ) ) {
									$preferred_swap = isset( $preferred[ strtolower( $phrase ) ] ) ? $preferred[ strtolower( $phrase ) ] : '';
									$violations[] = array(
										'widget_id' => isset( $node['id'] ) ? $node['id'] : '',
										'widget_type' => 'accordion-item',
										'field' => 'item_title',
										'banned_phrase' => $phrase,
										'preferred_replacement' => $preferred_swap,
										'snippet' => mb_substr( $it['item_title'], 0, 140 ),
									);
								}
							}
						}
					}
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_vocabulary( $node['elements'], $banned, $preferred, $violations );
			}
		}
	}

	// ---- v0.31 check: cross-domain image src ---------------------------

	private static function check_cross_domain_images( $tree, $site_host ) {
		$violations = array();
		self::walk_for_image_origin( $tree, $site_host, $violations );
		$violations = array_slice( $violations, 0, 20 );
		return array(
			'pass'       => empty( $violations ),
			'violations' => $violations,
			'message'    => empty( $violations )
				? 'All image sources are on the current site or empty placeholders.'
				: sprintf( '%d image source(s) point to a different domain (likely sister-site CDN).', count( $violations ) ),
		);
	}

	private static function walk_for_image_origin( $tree, $site_host, &$violations ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$s = isset( $node['settings'] ) ? $node['settings'] : array();
			$urls_to_check = array();
			if ( isset( $s['image']['url'] ) && is_string( $s['image']['url'] ) ) {
				$urls_to_check[] = array( 'field' => 'image.url', 'url' => $s['image']['url'] );
			}
			if ( isset( $s['background_image']['url'] ) && is_string( $s['background_image']['url'] ) ) {
				$urls_to_check[] = array( 'field' => 'background_image.url', 'url' => $s['background_image']['url'] );
			}
			if ( isset( $s['selected_icon']['library'] ) && 'svg' === $s['selected_icon']['library']
				&& isset( $s['selected_icon']['value']['url'] ) && is_string( $s['selected_icon']['value']['url'] ) ) {
				$urls_to_check[] = array( 'field' => 'selected_icon.value.url', 'url' => $s['selected_icon']['value']['url'] );
			}
			foreach ( $urls_to_check as $u ) {
				$url = $u['url'];
				if ( '' === $url || 0 === strpos( $url, 'data:' ) ) {
					continue;
				}
				$host = (string) wp_parse_url( $url, PHP_URL_HOST );
				if ( $host && self::is_placeholder_host( $host ) ) {
					continue; // Intentional swap-pending placeholder (design-system §24), not a sister-site CDN leak.
				}
				if ( $host && $host !== $site_host && ! self::host_matches_site( $host, $site_host ) ) {
					$violations[] = array(
						'widget_id'  => isset( $node['id'] ) ? $node['id'] : '',
						'widget_type' => isset( $node['widgetType'] ) ? $node['widgetType'] : '',
						'field'      => $u['field'],
						'foreign_host' => $host,
						'url'        => mb_substr( $url, 0, 140 ),
					);
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_image_origin( $node['elements'], $site_host, $violations );
			}
		}
	}

	private static function host_matches_site( $host, $site_host ) {
		$h = preg_replace( '#^www\.#', '', strtolower( $host ) );
		$s = preg_replace( '#^www\.#', '', strtolower( $site_host ) );
		return $h === $s;
	}

	/**
	 * Known placeholder-image providers. Per the design-system §24 protocol the
	 * assistant ships via.placeholder.com images on purpose (operator swaps real
	 * photos later), so they must NOT be reported as cross-domain leaks.
	 */
	private static function is_placeholder_host( $host ) {
		$host = strtolower( $host );
		$placeholder_hosts = array(
			'via.placeholder.com', 'placeholder.com', 'placehold.co', 'placehold.it',
			'dummyimage.com', 'picsum.photos', 'placekitten.com', 'fakeimg.pl',
		);
		foreach ( $placeholder_hosts as $ph ) {
			if ( $host === $ph || ( function_exists( 'str_ends_with' ) && str_ends_with( $host, '.' . $ph ) ) ) {
				return true;
			}
		}
		return false;
	}

	// ---- v0.31 check: schema cross-domain @id --------------------------

	private static function check_schema_cross_domain( $tree, $site_host ) {
		$violations = array();
		self::walk_for_schema( $tree, $site_host, $violations );
		$violations = array_slice( $violations, 0, 20 );
		return array(
			'pass'       => empty( $violations ),
			'violations' => $violations,
			'message'    => empty( $violations )
				? 'JSON-LD schema (if any) references match the current site domain.'
				: sprintf( '%d JSON-LD schema reference(s) point to a foreign domain.', count( $violations ) ),
		);
	}

	private static function walk_for_schema( $tree, $site_host, &$violations ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] ) {
				$h = isset( $node['settings']['html'] ) ? $node['settings']['html'] : '';
				if ( is_string( $h ) && false !== strpos( $h, 'application/ld+json' ) ) {
					if ( preg_match_all( '#"@id"\s*:\s*"([^"]+)"#', $h, $m ) ) {
						foreach ( $m[1] as $url ) {
							$host = (string) wp_parse_url( $url, PHP_URL_HOST );
							if ( $host && ! self::host_matches_site( $host, $site_host ) ) {
								$violations[] = array(
									'widget_id' => isset( $node['id'] ) ? $node['id'] : '',
									'foreign_host' => $host,
									'at_id_url' => mb_substr( $url, 0, 160 ),
								);
							}
						}
					}
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_schema( $node['elements'], $site_host, $violations );
			}
		}
	}

	// ---- v0.31 check: map widget address -------------------------------

	private static function check_map_address( $tree ) {
		$violations = array();
		self::walk_for_map( $tree, $violations );
		return array(
			'pass'       => empty( $violations ),
			'violations' => $violations,
			'message'    => empty( $violations )
				? 'Map widget addresses present.'
				: sprintf( '%d map widget(s) have empty or stale address.', count( $violations ) ),
		);
	}

	private static function walk_for_map( $tree, &$violations ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'google_maps' === $node['widgetType'] ) {
				$addr = isset( $node['settings']['address'] ) ? trim( (string) $node['settings']['address'] ) : '';
				if ( '' === $addr ) {
					$violations[] = array(
						'widget_id' => isset( $node['id'] ) ? $node['id'] : '',
						'issue' => 'empty_address',
					);
				}
				// Flag if address references obviously wrong city/state (heuristic — flagged-for-review, not hard-fail).
				// This check is intentionally soft because true validation requires knowing the site's catchment.
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_map( $node['elements'], $violations );
			}
		}
	}

	// ---- v0.31 check: empty containers ---------------------------------

	private static function check_empty_containers( $tree ) {
		$violations = array();
		foreach ( (array) $tree as $i => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$wc = self::count_descendants( $section );
			if ( 0 === $wc ) {
				$violations[] = array(
					'section_index' => $i,
					'id' => isset( $section['id'] ) ? $section['id'] : '',
					'issue' => 'empty_root_section',
				);
			}
		}
		return array(
			'pass'       => empty( $violations ),
			'violations' => $violations,
			'message'    => empty( $violations ) ? 'No empty root sections.' : sprintf( '%d empty root section(s).', count( $violations ) ),
		);
	}

	private static function count_descendants( $node ) {
		$c = 0;
		if ( is_array( $node ) && isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				if ( is_array( $child ) ) {
					if ( isset( $child['widgetType'] ) ) {
						$c++;
					}
					$c += self::count_descendants( $child );
				}
			}
		}
		return $c;
	}

	// ---- check (v0.43.3): icon variety ---------------------------------

	/**
	 * Catch the "wall of identical icons" defect: icon-box widgets that all
	 * render the same glyph, or that have no icon set at all (Elementor then
	 * renders its default star on every one). This is the gap that let a page
	 * with 22 identical stars score "pass" on design.
	 */
	private static function check_icon_variety( $tree ) {
		$icons = array();
		self::walk_for_icons( $tree, $icons );
		$boxes = count( $icons );
		if ( 0 === $boxes ) {
			return array( 'pass' => true, 'icon_boxes' => 0, 'violations' => array(), 'message' => 'No icon-boxes to check.' );
		}

		$violations = array();
		$blank = 0;
		$freq  = array();
		foreach ( $icons as $val ) {
			if ( '' === $val ) {
				$blank++;
				continue;
			}
			$freq[ $val ] = isset( $freq[ $val ] ) ? $freq[ $val ] + 1 : 1;
		}

		// (a) Any blank icon = Elementor's default star renders.
		if ( $blank > 0 ) {
			$violations[] = array(
				'issue' => 'default_icon',
				'count' => $blank,
				'reason' => sprintf( '%d of %d icon-boxes have no icon set — Elementor renders the default star on each.', $blank, $boxes ),
			);
		}

		// (b) One icon dominates the page (templated look). Only meaningful at 4+.
		if ( $boxes >= 4 && ! empty( $freq ) ) {
			arsort( $freq );
			$top_icon = key( $freq );
			$top_n    = reset( $freq );
			$threshold = (int) max( 3, ceil( $boxes * 0.6 ) );
			if ( $top_n >= $threshold ) {
				$violations[] = array(
					'issue'  => 'icons_not_varied',
					'icon'   => $top_icon,
					'count'  => $top_n,
					'reason' => sprintf( '%d of %d icon-boxes share one icon (%s). Give each card a distinct, meaningful icon.', $top_n, $boxes, $top_icon ),
				);
			}
		}

		$distinct = count( $freq );
		return array(
			'pass'       => empty( $violations ),
			'icon_boxes' => $boxes,
			'distinct'   => $distinct,
			'blank'      => $blank,
			'violations' => $violations,
			'message'    => empty( $violations )
				? sprintf( '%d icon-boxes use %d distinct icons.', $boxes, $distinct )
				: sprintf( 'Icon variety problem across %d icon-boxes (%d distinct, %d blank).', $boxes, $distinct, $blank ),
		);
	}

	private static function walk_for_icons( $tree, &$icons ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'icon-box' === $node['widgetType'] ) {
				$s = isset( $node['settings'] ) ? $node['settings'] : array();
				$val = '';
				if ( isset( $s['selected_icon']['value'] ) && is_string( $s['selected_icon']['value'] ) ) {
					$val = trim( $s['selected_icon']['value'] );
				} elseif ( isset( $s['icon'] ) && is_string( $s['icon'] ) ) {
					$val = trim( $s['icon'] );
				}
				$icons[] = $val;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_icons( $node['elements'], $icons );
			}
		}
	}

	// ---- check (v0.43.3): button vs section contrast -------------------

	/**
	 * Catch buttons that disappear into their band: a solid button whose
	 * background resolves to (nearly) the same color as the section behind it
	 * (the red-button-on-red-band defect the contrast check missed because it
	 * only compares TEXT to bg, never button-fill to section-fill).
	 */
	private static function check_button_contrast( $tree, $resolver ) {
		$violations = array();
		self::walk_for_button_contrast( $tree, '#ffffff', $resolver, $violations );
		return array(
			'pass'       => empty( $violations ),
			'violations' => array_slice( $violations, 0, 15 ),
			'message'    => empty( $violations )
				? 'All buttons contrast with their section background.'
				: sprintf( '%d button(s) blend into their section background (same fill).', count( $violations ) ),
		);
	}

	private static function walk_for_button_contrast( $tree, $bg_inherit, $resolver, &$violations ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$is_button = isset( $node['widgetType'] ) && 'button' === $node['widgetType'];
			// The section behind this node is the inherited bg, updated ONLY by
			// containers — never by a button's own fill (comparing a button to its
			// own bg always "matched" and produced false positives).
			$section_bg = $bg_inherit;
			$cur_bg     = self::resolve_widget_color( $node, 'background_color', $resolver );
			if ( '' !== $cur_bg && ! $is_button ) {
				$section_bg = $cur_bg;
			}
			if ( $is_button ) {
				$btn_bg = self::resolve_widget_color( $node, 'background_color', $resolver );
				// A visible border makes the button readable even when its fill is
				// close to the band (outlined chips, white-on-grey pills). Exempt
				// buttons that have a solid border whose color contrasts the section.
				$bs          = isset( $node['settings'] ) ? $node['settings'] : array();
				$has_border  = ! empty( $bs['border_border'] ) && 'none' !== $bs['border_border'];
				$border_hex  = self::resolve_widget_color( $node, 'border_color', $resolver );
				$bordered_ok = $has_border && '' !== $border_hex && ! self::colors_close( $border_hex, $section_bg );
				// Transparent/outline buttons (no resolvable fill) are exempt — they
				// rely on a border, not a fill, so same-as-band is intentional.
				if ( '' !== $btn_bg && ! $bordered_ok && self::colors_close( $btn_bg, $section_bg ) ) {
					$violations[] = array(
						'widget_id'  => isset( $node['id'] ) ? $node['id'] : '',
						'text'       => isset( $node['settings']['text'] ) ? mb_substr( (string) $node['settings']['text'], 0, 40 ) : '',
						'button_bg'  => $btn_bg,
						'section_bg' => $section_bg,
						'reason'     => 'Button fill matches the section background — invisible. Use a contrasting fill or an outline variant.',
					);
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_button_contrast( $node['elements'], $section_bg, $resolver, $violations );
			}
		}
	}

	/** True when two #rrggbb hexes are within a small per-channel delta. */
	private static function colors_close( $a, $b ) {
		$a = ltrim( (string) $a, '#' );
		$b = ltrim( (string) $b, '#' );
		if ( 6 !== strlen( $a ) || 6 !== strlen( $b ) ) {
			return strtolower( $a ) === strtolower( $b );
		}
		for ( $i = 0; $i < 3; $i++ ) {
			$da = hexdec( substr( $a, $i * 2, 2 ) );
			$db = hexdec( substr( $b, $i * 2, 2 ) );
			if ( abs( $da - $db ) > 24 ) {
				return false;
			}
		}
		return true;
	}

	// ---- check 2: heading order ----------------------------------------

	private static function check_heading_order( $tree ) {
		$sequence = array();
		self::collect_heading_levels( $tree, $sequence );
		$violations = array();
		$prev = null;
		$seen_h1 = false;
		foreach ( $sequence as $level_with_title ) {
			$level = (int) $level_with_title['n'];
			// Eyebrow / kicker as a real heading: any h2-h6 appearing BEFORE the
			// page H1 inverts the outline (operator-flagged: an h6 "HEAD INJURY
			// EMERGENCY CARE" above the h1). Style the kicker as a <p>, not a heading.
			if ( ! $seen_h1 && $level > 1 ) {
				$violations[] = array(
					'prev'   => '(page start)',
					'this'   => 'h' . $level,
					'title'  => $level_with_title['title'],
					'reason' => sprintf( 'h%d appears before the page H1 — render eyebrows/kickers as styled <p>, not a heading.', $level ),
				);
			}
			if ( 1 === $level ) {
				$seen_h1 = true;
			}
			if ( null !== $prev && $level > $prev + 1 ) {
				$violations[] = array(
					'prev'   => 'h' . $prev,
					'this'   => 'h' . $level,
					'title'  => $level_with_title['title'],
					'reason' => sprintf( 'Skipped h%d.', $prev + 1 ),
				);
			}
			$prev = $level;
		}
		return array(
			'pass'       => empty( $violations ),
			'headings'   => count( $sequence ),
			'violations' => $violations,
			'message'    => empty( $violations ) ? sprintf( 'No skipped heading levels across %d headings.', count( $sequence ) ) : sprintf( '%d heading-level skips.', count( $violations ) ),
		);
	}

	private static function collect_heading_levels( $tree, &$out ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType'] ) {
				$h = isset( $node['settings']['header_size'] ) ? strtolower( (string) $node['settings']['header_size'] ) : 'h2';
				if ( preg_match( '/^h([1-6])$/', $h, $m ) ) {
					$out[] = array( 'n' => (int) $m[1], 'title' => isset( $node['settings']['title'] ) ? mb_substr( (string) $node['settings']['title'], 0, 80 ) : '' );
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::collect_heading_levels( $node['elements'], $out );
			}
		}
	}

	// ---- check 3: heading bold ----------------------------------------

	private static function check_heading_bold( $tree ) {
		$violations = array();
		$headings   = 0;
		self::walk_for_heading_bold( $tree, $violations, $headings );
		return array(
			'pass'       => empty( $violations ),
			'headings'   => $headings,
			'violations' => array_slice( $violations, 0, 20 ),
			'message'    => empty( $violations ) ? sprintf( 'All %d headings are bold (600+).', $headings ) : sprintf( '%d of %d headings render under bold weight.', count( $violations ), $headings ),
		);
	}

	private static function walk_for_heading_bold( $tree, &$violations, &$headings ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType'] ) {
				$headings++;
				$weight = isset( $node['settings']['typography_font_weight'] ) ? (string) $node['settings']['typography_font_weight'] : '';
				$ok = false;
				if ( '' === $weight ) {
					// Inherits from Kit. Without resolving the Kit's heading
					// weight, conservatively flag as "not pinned bold."
					$ok = false;
				} elseif ( is_numeric( $weight ) ) {
					$ok = ( (int) $weight >= self::HEADING_BOLD_MIN );
				} else {
					$ok = in_array( strtolower( $weight ), array( 'bold', 'bolder', '600', '700', '800', '900' ), true );
				}
				if ( ! $ok ) {
					$violations[] = array(
						'widget_id' => isset( $node['id'] ) ? $node['id'] : '',
						'title'     => isset( $node['settings']['title'] ) ? mb_substr( (string) $node['settings']['title'], 0, 80 ) : '',
						'weight'    => '' === $weight ? '(kit-inherit)' : $weight,
					);
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_heading_bold( $node['elements'], $violations, $headings );
			}
		}
	}

	// ---- check 4: hover coverage --------------------------------------

	private static function check_hover_coverage( $tree ) {
		$violations = array();
		$interactives = 0;
		self::walk_for_hover( $tree, $violations, $interactives );
		return array(
			'pass'         => empty( $violations ),
			'interactives' => $interactives,
			'violations'   => array_slice( $violations, 0, 20 ),
			'message'      => empty( $violations ) ? sprintf( 'All %d interactive widgets have a hover state.', $interactives ) : sprintf( '%d of %d interactive widgets lack a hover state.', count( $violations ), $interactives ),
		);
	}

	private static function walk_for_hover( $tree, &$violations, &$interactives ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) ) {
				$wt = $node['widgetType'];
				if ( 'button' === $wt ) {
					$interactives++;
					$bs = isset( $node['settings'] ) ? $node['settings'] : array();
					$bg = isset( $bs['__globals__'] ) && is_array( $bs['__globals__'] ) ? $bs['__globals__'] : array();
					// Recognize the actual Elementor hover keys (button_background_hover_color
					// is the real one; background_hover_color does not exist) plus hover text /
					// border and kit-token hover refs.
					$has_hover = ! empty( $bs['background_hover_color'] )
						|| ! empty( $bs['button_background_hover_color'] )
						|| ! empty( $bs['hover_color'] )
						|| ! empty( $bs['button_hover_border_color'] )
						|| ! empty( $bs['hover_button_text_color'] )
						|| ! empty( $bs['hover_animation'] )
						|| ! empty( $bg['button_background_hover_color'] )
						|| ! empty( $bg['hover_color'] );
					if ( ! $has_hover ) {
						$violations[] = array( 'widget_id' => isset( $node['id'] ) ? $node['id'] : '', 'widget_type' => 'button', 'text' => isset( $node['settings']['text'] ) ? $node['settings']['text'] : '' );
					}
				} elseif ( 'icon-box' === $wt && ! empty( $node['settings']['link']['url'] ) ) {
					$interactives++;
					$has_hover = ! empty( $node['settings']['hover_title_color'] )
						|| ! empty( $node['settings']['hover_primary_color'] );
					if ( ! $has_hover ) {
						$violations[] = array( 'widget_id' => isset( $node['id'] ) ? $node['id'] : '', 'widget_type' => 'icon-box-linked', 'title' => isset( $node['settings']['title_text'] ) ? $node['settings']['title_text'] : '' );
					}
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_hover( $node['elements'], $violations, $interactives );
			}
		}
	}

	// ---- check 5: alt coverage -----------------------------------------

	private static function check_alt_coverage( $tree ) {
		$violations = array();
		$images = 0;
		self::walk_for_alt( $tree, $violations, $images );
		return array(
			'pass'       => empty( $violations ),
			'images'     => $images,
			'violations' => array_slice( $violations, 0, 20 ),
			'message'    => empty( $violations ) ? sprintf( 'All %d images have alt text.', $images ) : sprintf( '%d of %d images missing alt text.', count( $violations ), $images ),
		);
	}

	private static function walk_for_alt( $tree, &$violations, &$images ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'image' === $node['widgetType'] ) {
				$images++;
				$alt = isset( $node['settings']['image']['alt'] ) ? trim( (string) $node['settings']['image']['alt'] ) : '';
				$has_src = ! empty( $node['settings']['image']['url'] );
				if ( $has_src && '' === $alt ) {
					$violations[] = array(
						'widget_id' => isset( $node['id'] ) ? $node['id'] : '',
						'src'       => isset( $node['settings']['image']['url'] ) ? $node['settings']['image']['url'] : '',
					);
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_alt( $node['elements'], $violations, $images );
			}
		}
	}

	// ---- check 6: brand compliance -------------------------------------

	private static function check_brand_compliance( $tree, $brand_palette ) {
		$palette_lower = array_map( 'strtolower', $brand_palette );
		// Allow standard neutrals always — PLUS the documented design-system
		// semantic greys (§22): body text #555555, footnote #777777, borders
		// #dddddd, on-dark subtitle #e0e0e0. These are allowed roles, not
		// "off-palette" drift, and flagging them produced chronic false fails.
		$palette_lower = array_unique( array_merge( $palette_lower, array( '#ffffff', '#000000', '#202020', '#555555', '#777777', '#dddddd', '#e0e0e0', '#4b5563', '#6b7280', '#9ca3af', '#e5e7eb', '#f4f4f4', '#f9fafb' ) ) );
		$violations = array();
		$colors_checked = 0;
		self::walk_for_brand( $tree, $palette_lower, $violations, $colors_checked );
		return array(
			'pass'           => empty( $violations ),
			'colors_checked' => $colors_checked,
			'violations'     => array_slice( $violations, 0, 20 ),
			'message'        => empty( $violations ) ? sprintf( 'All %d explicit colors match Kit palette.', $colors_checked ) : sprintf( '%d off-palette colors detected.', count( $violations ) ),
		);
	}

	private static function walk_for_brand( $tree, $palette_lower, &$violations, &$colors_checked ) {
		$color_fields = array( 'background_color', 'title_color', 'description_color', 'button_text_color', 'background_hover_color', 'hover_button_text_color', 'border_color', 'primary_color', 'icon_color', 'text_color' );
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
				foreach ( $color_fields as $f ) {
					if ( isset( $node['settings'][ $f ] ) && is_string( $node['settings'][ $f ] ) ) {
						$hex = trim( strtolower( $node['settings'][ $f ] ) );
						if ( '' === $hex || strpos( $hex, '#' ) !== 0 ) {
							continue;
						}
						$colors_checked++;
						if ( ! in_array( $hex, $palette_lower, true ) ) {
							$violations[] = array(
								'widget_id'  => isset( $node['id'] ) ? $node['id'] : '',
								'widget_type' => isset( $node['widgetType'] ) ? $node['widgetType'] : ( isset( $node['elType'] ) ? $node['elType'] : '' ),
								'field'      => $f,
								'hex'        => $hex,
							);
						}
					}
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_brand( $node['elements'], $palette_lower, $violations, $colors_checked );
			}
		}
	}

	// ---- check 7: section uniqueness -----------------------------------

	private static function check_section_uniqueness( $tree, $resolver = null ) {
		$signatures = array();
		foreach ( (array) $tree as $i => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$sig = self::section_signature( $section, $resolver );
			$signatures[] = array(
				'index' => $i,
				'sig' => $sig['signature'],
				'resolved_bg' => $sig['resolved_bg'],
				'first_heading' => self::first_heading_in( $section ),
			);
		}
		$violations = array();
		// v0.31: also flag when ADJACENT sections share resolved bg (even if explicit values differ)
		for ( $i = 1; $i < count( $signatures ); $i++ ) {
			$prev = $signatures[ $i - 1 ];
			$cur = $signatures[ $i ];
			if ( $cur['sig'] === $prev['sig'] ) {
				$violations[] = array(
					'reason' => 'identical_shape',
					'section_a_index' => $prev['index'],
					'section_a_heading' => $prev['first_heading'],
					'section_b_index' => $cur['index'],
					'section_b_heading' => $cur['first_heading'],
					'shared_signature' => $cur['sig'],
				);
			} elseif ( $cur['resolved_bg'] && $prev['resolved_bg'] && $cur['resolved_bg'] === $prev['resolved_bg'] ) {
				$violations[] = array(
					'reason' => 'same_resolved_background',
					'section_a_index' => $prev['index'],
					'section_a_heading' => $prev['first_heading'],
					'section_b_index' => $cur['index'],
					'section_b_heading' => $cur['first_heading'],
					'shared_bg' => $cur['resolved_bg'],
				);
			}
		}
		return array(
			'pass'        => empty( $violations ),
			'sections'    => count( $signatures ),
			'violations'  => $violations,
			'message'     => empty( $violations ) ? sprintf( '%d sections — no adjacent pair shares the same shape or resolved bg.', count( $signatures ) ) : sprintf( '%d adjacent section pairs share identical shape or background (monotony).', count( $violations ) ),
		);
	}

	private static function section_signature( $section, $resolver = null ) {
		// v0.31: resolve bg through inline hex / rgb / __globals__ ref OR fall
		// back to inherited (theme default = white) so all-empty sections are
		// detected as sharing background.
		$resolved_bg = '';
		if ( $resolver ) {
			$resolved_bg = self::resolve_widget_color( $section, 'background_color', $resolver );
			if ( '' === $resolved_bg ) {
				$resolved_bg = '#ffffff'; // inherited theme default
			}
		} else {
			$resolved_bg = isset( $section['settings']['background_color'] ) ? strtolower( (string) $section['settings']['background_color'] ) : '#ffffff';
		}
		$direction = isset( $section['settings']['flex_direction'] ) ? (string) $section['settings']['flex_direction'] : 'column';
		$types = array();
		self::collect_top_widget_types( isset( $section['elements'] ) ? $section['elements'] : array(), $types, 2 );
		return array(
			'signature'   => $direction . '|' . $resolved_bg . '|' . implode( ',', $types ),
			'resolved_bg' => $resolved_bg,
		);
	}

	private static function collect_top_widget_types( $tree, &$out, $max_depth ) {
		if ( $max_depth <= 0 ) {
			return;
		}
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) ) {
				$out[] = (string) $node['widgetType'];
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::collect_top_widget_types( $node['elements'], $out, $max_depth - 1 );
			}
		}
	}

	private static function first_heading_in( $section ) {
		foreach ( (array) ( isset( $section['elements'] ) ? $section['elements'] : array() ) as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType'] && ! empty( $node['settings']['title'] ) ) {
				return mb_substr( (string) $node['settings']['title'], 0, 100 );
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$nested = self::first_heading_in( $node );
				if ( $nested ) {
					return $nested;
				}
			}
		}
		return '';
	}

	// ---- check 8: alignment consistency --------------------------------

	private static function check_alignment_consistency( $tree ) {
		$violations = array();
		foreach ( (array) $tree as $i => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			// A centered heading/intro over a left-aligned table, list, or card
			// grid is the intended pattern, not a defect. Skip alignment-uniformity
			// for sections that carry such block content.
			if ( self::section_has_block_content( $section ) ) {
				continue;
			}
			$align_set = array();
			self::collect_alignments( isset( $section['elements'] ) ? $section['elements'] : array(), $align_set );
			$unique = array_unique( $align_set );
			if ( count( $unique ) > 1 ) {
				$violations[] = array(
					'section_index'  => $i,
					'section_heading' => self::first_heading_in( $section ),
					'alignments'     => $unique,
				);
			}
		}
		return array(
			'pass'       => empty( $violations ),
			'violations' => array_slice( $violations, 0, 20 ),
			'message'    => empty( $violations ) ? 'Every section uses one consistent text alignment.' : sprintf( '%d sections have mixed text alignments (heading vs body).', count( $violations ) ),
		);
	}

	/** True if a section contains block content (table/list/cards/accordion) that
	 * legitimately left-aligns under a centered heading. */
	private static function section_has_block_content( $node ) {
		$found = false;
		$walk  = function ( $n ) use ( &$walk, &$found ) {
			if ( $found || ! is_array( $n ) ) {
				return;
			}
			$wt = isset( $n['widgetType'] ) ? $n['widgetType'] : '';
			if ( in_array( $wt, array( 'accordion', 'toggle', 'nested-accordion', 'icon-list', 'price-list', 'table', 'tabs', 'icon-box' ), true ) ) {
				$found = true;
				return;
			}
			// A CSS-grid container (card grid, steps row) is block content: a
			// centered section heading over its left-aligned cells is intended.
			$cs = isset( $n['settings'] ) ? $n['settings'] : array();
			if ( ( isset( $cs['container_type'] ) && 'grid' === $cs['container_type'] ) || isset( $cs['grid_columns_grid'] ) ) {
				$found = true;
				return;
			}
			if ( 'text-editor' === $wt ) {
				$html = isset( $n['settings']['editor'] ) ? (string) $n['settings']['editor'] : '';
				if ( '' !== $html && preg_match( '/<(table|ul|ol)\b/i', $html ) ) {
					$found = true;
					return;
				}
			}
			if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
				foreach ( $n['elements'] as $c ) {
					$walk( $c );
				}
			}
		};
		$walk( $node );
		return $found;
	}

	private static function collect_alignments( $tree, &$out ) {
		foreach ( (array) $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['settings']['align'] ) && in_array( $node['settings']['align'], array( 'left', 'center', 'right', 'justify' ), true ) ) {
				$out[] = (string) $node['settings']['align'];
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::collect_alignments( $node['elements'], $out );
			}
		}
	}

	// ---- helpers --------------------------------------------------------

	private static function resolve_palette( $kit_globals ) {
		$out = array();
		if ( ! empty( $kit_globals['colors'] ) && is_array( $kit_globals['colors'] ) ) {
			foreach ( $kit_globals['colors'] as $c ) {
				if ( ! empty( $c['color'] ) ) {
					$out[] = strtolower( $c['color'] );
				}
			}
		}
		return array_unique( $out );
	}

	private static function resolve_color( $value, $brand_palette ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( '#' === substr( $value, 0, 1 ) ) {
			return strtolower( $value );
		}
		return '';
	}

	private static function relative_luminance( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return 0.5;
		}
		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;
		$linearize = function ( $c ) {
			return ( $c <= 0.03928 ) ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * $linearize( $r ) + 0.7152 * $linearize( $g ) + 0.0722 * $linearize( $b );
	}

	private static function contrast_ratio( $hex1, $hex2 ) {
		$l1 = self::relative_luminance( $hex1 );
		$l2 = self::relative_luminance( $hex2 );
		$light = max( $l1, $l2 );
		$dark  = min( $l1, $l2 );
		return ( $light + 0.05 ) / ( $dark + 0.05 );
	}

	private static function compute_score( $checks ) {
		// v0.31: weighted scoring. WCAG, brand_compliance, industry_vocabulary,
		// schema_cross_domain are the high-stakes checks (10 points each).
		// Lower-stakes structural checks worth 5 points. Total = 100.
		$weights = array(
			'wcag_contrast'           => 12,
			'heading_order'           => 6,
			'heading_bold'            => 5,
			'heading_size_hierarchy'  => 4, // v0.44: demoted — median-based, advisory (visual H1>H2>H3 is the real signal)
			'hover_coverage'          => 5,
			'alt_text_coverage'       => 6,
			'brand_compliance'        => 10,
			'section_uniqueness'      => 8,
			'alignment_consistency'   => 5,
			'industry_vocabulary'     => 12, // big — cross-industry leakage is a major fail
			'cross_domain_images'     => 8,
			'schema_cross_domain'     => 10,
			'map_address_geo'         => 3,
			'empty_containers'        => 2,
			'icon_variety'            => 8, // v0.43.3 — wall of identical/default icons reads as broken/templated
			'button_contrast'         => 10, // v0.43.3 — invisible button is an accessibility + conversion failure
		);
		$total_weight = 0;
		$earned = 0;
		foreach ( $checks as $name => $c ) {
			$w = isset( $weights[ $name ] ) ? $weights[ $name ] : 5;
			$total_weight += $w;
			if ( ! empty( $c['pass'] ) || ! empty( $c['advisory'] ) ) {
				// advisory=true (e.g. industry_vocabulary under overlay drift) must
				// not bleed score, or design_score contradicts the forced-pass verdict.
				$earned += $w;
			} else {
				$vc = isset( $c['violations'] ) ? count( $c['violations'] ) : 0;
				if ( $vc > 0 && $vc <= 2 ) {
					$earned += $w * 0.5;
				} elseif ( $vc <= 5 ) {
					$earned += $w * 0.25;
				}
			}
		}
		if ( $total_weight <= 0 ) {
			return 0;
		}
		return (int) round( ( $earned / $total_weight ) * 100 );
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.45 — Semantic section recipes.
 *
 * Describe a section by INTENT + CONTENT and get the full brand-correct
 * Elementor tree: eyebrow + 38px H2 + red accent bar, brand tokens, grid rows
 * auto, FA5 icons, responsive type, capped widths. The agent writes ~10 lines
 * of data; expand() writes the ~300 lines of structure. This is
 * build_service_page generalised from whole-pages down to single sections.
 *
 * expand($recipe, $data) returns ['settings'=>..., 'children'=>...] ready for
 * CC_Assistant_Elementor_Builder::add_container (or, with a remove_id, for the
 * atomic elementor_section_rebuild path).
 *
 * Brand tokens (ER design system): primary red #DA1212, secondary navy
 * #11468F, text #041562, body grey #555555, card grey #F4F4F4, hero navy
 * #11468F. Keep these in sync with the per-site design skill.
 */
class CC_Assistant_Section_Recipes {

	const RED  = '#DA1212';
	const NAVY = '#11468F';
	const TEXT = '#041562';
	const BODY = '#555555';
	const CARD = '#F4F4F4';

	public static function recipes() {
		return array( 'hero', 'card_grid', 'checklist_2col', 'cta_banner', 'section_header' );
	}

	/**
	 * @return array{settings:array,children:array}|WP_Error
	 */
	public static function expand( $recipe, $data ) {
		$recipe = (string) $recipe;
		$data   = is_array( $data ) ? $data : array();
		switch ( $recipe ) {
			case 'hero':
				return self::recipe_hero( $data );
			case 'card_grid':
				return self::recipe_card_grid( $data );
			case 'checklist_2col':
				return self::recipe_checklist_2col( $data );
			case 'cta_banner':
				return self::recipe_cta_banner( $data );
			case 'section_header':
				return self::recipe_section_header( $data );
			default:
				return new WP_Error( 'unknown_recipe', sprintf( 'Unknown recipe "%s". Available: %s', $recipe, implode( ', ', self::recipes() ) ) );
		}
	}

	// ---- recipes ---------------------------------------------------------

	/** Navy text hero: eyebrow? + H1 + sub? + CTAs + trust chips?. data:
	 * { eyebrow?, h1, sub?, ctas:[{text,href}], chips?:[string] } */
	private static function recipe_hero( $d ) {
		if ( empty( $d['h1'] ) ) {
			return new WP_Error( 'missing_field', 'hero needs h1.' );
		}
		$children = array();
		if ( ! empty( $d['eyebrow'] ) ) {
			$children[] = self::eyebrow( $d['eyebrow'], '#FFFFFF' );
		}
		$children[] = self::heading( $d['h1'], 'h1', '#FFFFFF', 52, 40, 32 );
		if ( ! empty( $d['sub'] ) ) {
			$children[] = self::intro( $d['sub'], '#E0E0E0', 18 );
		}
		$children[] = self::button_row( isset( $d['ctas'] ) ? $d['ctas'] : array() );
		if ( ! empty( $d['chips'] ) && is_array( $d['chips'] ) ) {
			$children[] = self::icon_list( $d['chips'], '#FFFFFF', '#FFFFFF', 'fas fa-check' );
		}
		$settings              = self::section_settings( self::NAVY );
		$settings['min_height'] = array( 'unit' => 'vh', 'size' => 88, 'sizes' => array() );
		$settings['flex_justify_content'] = 'center';
		return array( 'settings' => $settings, 'children' => $children );
	}

	/** Eyebrow + H2 + accent + intro? + card grid + cta?. data:
	 * { eyebrow?, heading, intro?, columns?, bg?, cta?:{text,href},
	 *   cards:[{title, description, icon?|image?, link?}] } */
	private static function recipe_card_grid( $d ) {
		if ( empty( $d['heading'] ) || empty( $d['cards'] ) || ! is_array( $d['cards'] ) ) {
			return new WP_Error( 'missing_field', 'card_grid needs heading and a non-empty cards array.' );
		}
		$cols     = isset( $d['columns'] ) ? max( 1, min( 4, (int) $d['columns'] ) ) : 3;
		$children = self::header_block( $d );
		if ( ! empty( $d['intro'] ) ) {
			$children[] = self::intro( $d['intro'] );
		}

		$cards = array();
		foreach ( $d['cards'] as $c ) {
			if ( ! is_array( $c ) || empty( $c['title'] ) ) {
				continue;
			}
			$link = ! empty( $c['link'] ) ? array( 'url' => (string) $c['link'], 'is_external' => '', 'nofollow' => '' ) : null;
			if ( ! empty( $c['image'] ) ) {
				$cards[] = self::image_box( $c['title'], isset( $c['description'] ) ? $c['description'] : '', (string) $c['image'], $link );
			} else {
				$icon    = ! empty( $c['icon'] ) ? self::fa( $c['icon'] ) : 'fas fa-circle-check';
				$cards[] = self::icon_box( $c['title'], isset( $c['description'] ) ? $c['description'] : '', $icon, $link );
			}
		}
		$children[] = self::grid_container( $cards, $cols );
		if ( ! empty( $d['cta']['text'] ) && ! empty( $d['cta']['href'] ) ) {
			$children[] = self::button( $d['cta']['text'], $d['cta']['href'] );
		}
		$bg = isset( $d['bg'] ) ? (string) $d['bg'] : '#FFFFFF';
		return array( 'settings' => self::section_settings( $bg ), 'children' => $children );
	}

	/** Eyebrow + H2 + accent + intro? + two icon-list columns. data:
	 * { eyebrow?, heading, intro?, bg?, icon?, items:[string] } */
	private static function recipe_checklist_2col( $d ) {
		if ( empty( $d['heading'] ) || empty( $d['items'] ) || ! is_array( $d['items'] ) ) {
			return new WP_Error( 'missing_field', 'checklist_2col needs heading and a non-empty items array.' );
		}
		$children = self::header_block( $d );
		if ( ! empty( $d['intro'] ) ) {
			$children[] = self::intro( $d['intro'] );
		}
		$icon  = ! empty( $d['icon'] ) ? self::fa( $d['icon'] ) : 'fas fa-check';
		$items = array_values( array_filter( array_map( 'strval', $d['items'] ) ) );
		$half  = (int) ceil( count( $items ) / 2 );
		$left  = array_slice( $items, 0, $half );
		$right = array_slice( $items, $half );
		$grid_children = array( self::icon_list( $left, self::RED, self::BODY, $icon ) );
		if ( ! empty( $right ) ) {
			$grid_children[] = self::icon_list( $right, self::RED, self::BODY, $icon );
		}
		$children[] = self::grid_container( $grid_children, empty( $right ) ? 1 : 2 );
		$bg = isset( $d['bg'] ) ? (string) $d['bg'] : '#FFFFFF';
		return array( 'settings' => self::section_settings( $bg ), 'children' => $children );
	}

	/** Navy CTA banner: H2 + sub? + CTAs. data: { heading, sub?, ctas:[{text,href}] } */
	private static function recipe_cta_banner( $d ) {
		if ( empty( $d['heading'] ) ) {
			return new WP_Error( 'missing_field', 'cta_banner needs heading.' );
		}
		$children   = array();
		$children[] = self::heading( $d['heading'], 'h2', '#FFFFFF', 36, 28, 24 );
		if ( ! empty( $d['sub'] ) ) {
			$children[] = self::intro( $d['sub'], '#E0E0E0' );
		}
		$children[] = self::button_row( isset( $d['ctas'] ) ? $d['ctas'] : array() );
		return array( 'settings' => self::section_settings( self::NAVY ), 'children' => $children );
	}

	/** Bare eyebrow + H2 + accent bar. data: { eyebrow?, heading, bg? } */
	private static function recipe_section_header( $d ) {
		if ( empty( $d['heading'] ) ) {
			return new WP_Error( 'missing_field', 'section_header needs heading.' );
		}
		$bg = isset( $d['bg'] ) ? (string) $d['bg'] : '#FFFFFF';
		return array( 'settings' => self::section_settings( $bg ), 'children' => self::header_block( $d ) );
	}

	// ---- composable blocks ----------------------------------------------

	/** eyebrow? + H2 + red accent bar. */
	private static function header_block( $d ) {
		$out = array();
		if ( ! empty( $d['eyebrow'] ) ) {
			$out[] = self::eyebrow( $d['eyebrow'] );
		}
		$out[] = self::heading( $d['heading'], 'h2', self::TEXT, 38, 30, 26 );
		$out[] = self::accent_bar();
		return $out;
	}

	private static function section_settings( $bg ) {
		return array(
			'content_width'    => 'boxed',
			'boxed_width'      => array( 'unit' => 'px', 'size' => 1200, 'sizes' => array() ),
			'flex_direction'   => 'column',
			'flex_align_items' => 'center',
			'flex_gap'         => array( 'unit' => 'px', 'size' => 24, 'sizes' => array(), 'column' => '24', 'row' => '24', 'isLinked' => true ),
			'padding'          => array( 'unit' => 'px', 'top' => '80', 'right' => '24', 'bottom' => '80', 'left' => '24', 'isLinked' => false ),
			'padding_mobile'   => array( 'unit' => 'px', 'top' => '48', 'right' => '16', 'bottom' => '48', 'left' => '16', 'isLinked' => false ),
			'background_background' => 'classic',
			'background_color'      => $bg,
		);
	}

	private static function eyebrow( $text, $color = self::NAVY ) {
		return array( 'type' => 'widget', 'widgetType' => 'heading', 'settings' => array(
			'title'                      => (string) $text,
			'header_size'                => 'p',
			'align'                      => 'center',
			'title_color'                => $color,
			'typography_typography'      => 'custom',
			'typography_font_size'       => array( 'unit' => 'px', 'size' => 14, 'sizes' => array() ),
			'typography_font_weight'     => '600',
			'typography_text_transform'  => 'uppercase',
			'typography_letter_spacing'  => array( 'unit' => 'px', 'size' => 2, 'sizes' => array() ),
			'_element_width'             => 'initial',
			'_element_custom_width'      => array( 'unit' => 'px', 'size' => 800, 'sizes' => array() ),
		) );
	}

	private static function heading( $text, $tag, $color, $d, $t, $m ) {
		return array( 'type' => 'widget', 'widgetType' => 'heading', 'settings' => array(
			'title'                       => (string) $text,
			'header_size'                 => $tag,
			'align'                       => 'center',
			'title_color'                 => $color,
			'typography_typography'       => 'custom',
			'typography_font_size'        => array( 'unit' => 'px', 'size' => $d, 'sizes' => array() ),
			'typography_font_size_tablet' => array( 'unit' => 'px', 'size' => $t, 'sizes' => array() ),
			'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => $m, 'sizes' => array() ),
			'typography_font_weight'      => '700',
			'typography_line_height'      => array( 'unit' => 'em', 'size' => 1.15, 'sizes' => array() ),
			'_element_width'              => 'initial',
			'_element_custom_width'       => array( 'unit' => 'px', 'size' => 800, 'sizes' => array() ),
		) );
	}

	private static function accent_bar() {
		return array( 'type' => 'widget', 'widgetType' => 'divider', 'settings' => array(
			'color'  => self::RED,
			'weight' => array( 'unit' => 'px', 'size' => 4, 'sizes' => array() ),
			'width'  => array( 'unit' => 'px', 'size' => 48, 'sizes' => array() ),
			'align'  => 'center',
			'gap'    => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ),
		) );
	}

	private static function intro( $text, $color = self::BODY, $size = 16 ) {
		return array( 'type' => 'widget', 'widgetType' => 'text-editor', 'settings' => array(
			'editor'                 => '<p>' . wp_kses_post( (string) $text ) . '</p>',
			'align'                  => 'center',
			'text_color'             => $color,
			'typography_typography'  => 'custom',
			'typography_font_size'   => array( 'unit' => 'px', 'size' => $size, 'sizes' => array() ),
			'_element_width'         => 'initial',
			'_element_custom_width'  => array( 'unit' => 'px', 'size' => 800, 'sizes' => array() ),
		) );
	}

	private static function button( $text, $href, $is_external = '' ) {
		return array( 'type' => 'widget', 'widgetType' => 'button', 'settings' => array(
			'text'                         => (string) $text,
			'link'                         => array( 'url' => (string) $href, 'is_external' => $is_external, 'nofollow' => '' ),
			'background_color'             => self::RED,
			'button_text_color'            => '#FFFFFF',
			'button_background_hover_color' => self::NAVY,
			'background_hover_color'        => self::NAVY,
			'hover_color'                   => '#FFFFFF',
			'border_radius'                 => array( 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => true ),
			'text_padding'                  => array( 'unit' => 'px', 'top' => '16', 'right' => '24', 'bottom' => '16', 'left' => '24', 'isLinked' => false ),
			'typography_typography'         => 'custom',
			'typography_font_weight'        => '700',
		) );
	}

	/** A centered row of buttons (tel: links flagged external). */
	private static function button_row( $ctas ) {
		$buttons = array();
		foreach ( (array) $ctas as $cta ) {
			if ( ! is_array( $cta ) || empty( $cta['text'] ) || empty( $cta['href'] ) ) {
				continue;
			}
			$ext       = ( 0 === strpos( (string) $cta['href'], 'tel:' ) || 0 === strpos( (string) $cta['href'], 'http' ) ) ? 'true' : '';
			$buttons[] = self::button( $cta['text'], $cta['href'], $ext );
		}
		return array( 'type' => 'container', 'settings' => array(
			'flex_direction'   => 'row',
			'flex_wrap'        => 'wrap',
			'flex_justify_content' => 'center',
			'flex_gap'         => array( 'unit' => 'px', 'size' => 16, 'sizes' => array(), 'column' => '16', 'row' => '16', 'isLinked' => true ),
			'width'            => array( 'unit' => 'px', 'size' => 800, 'sizes' => array() ),
			'_element_width'   => 'initial',
		), 'children' => $buttons );
	}

	private static function grid_container( $children, $cols ) {
		// grid_rows omitted on purpose — the builder's maybe_default_grid_rows
		// sets it to auto, so no empty reserved row.
		$settings = array(
			'container_type'           => 'grid',
			'grid_columns_grid'        => array( 'unit' => 'fr', 'size' => (int) $cols, 'sizes' => array() ),
			'grid_columns_grid_mobile' => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
			'grid_gap'                 => array( 'unit' => 'px', 'size' => 24, 'sizes' => array(), 'column' => '24', 'row' => '24', 'isLinked' => true ),
			'width'                    => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
		);
		if ( (int) $cols >= 3 ) {
			$settings['grid_columns_grid_tablet'] = array( 'unit' => 'fr', 'size' => 2, 'sizes' => array() );
		}
		return array( 'type' => 'container', 'settings' => $settings, 'children' => $children );
	}

	private static function icon_box( $title, $desc, $icon, $link ) {
		$s = array(
			'selected_icon'              => array( 'value' => $icon, 'library' => 'fa-solid' ),
			'title_text'                 => (string) $title,
			'description_text'           => (string) $desc,
			'title_size'                 => 'h3',
			'position'                   => 'top',
			'text_align'                 => 'center',
			'primary_color'              => self::RED,
			'title_color'                => self::TEXT,
			'description_color'          => self::BODY,
			'title_typography_typography' => 'custom',
			'title_typography_font_size' => array( 'unit' => 'px', 'size' => 20, 'sizes' => array() ),
			'title_typography_font_weight' => '700',
		);
		if ( $link ) {
			$s['link'] = $link;
		}
		return array( 'type' => 'widget', 'widgetType' => 'icon-box', 'settings' => $s );
	}

	private static function image_box( $title, $desc, $image_url, $link ) {
		$s = array(
			'image'                      => array( 'url' => $image_url, 'id' => '', 'alt' => (string) $title ),
			'title_text'                 => (string) $title,
			'description_text'           => (string) $desc,
			'title_size'                 => 'h3',
			'text_align'                 => 'center',
			'title_color'                => self::TEXT,
			'description_color'          => self::BODY,
			'image_border_radius'        => array( 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => true ),
			'title_typography_typography' => 'custom',
			'title_typography_font_size' => array( 'unit' => 'px', 'size' => 20, 'sizes' => array() ),
			'title_typography_font_weight' => '700',
		);
		if ( $link ) {
			$s['link'] = $link;
		}
		return array( 'type' => 'widget', 'widgetType' => 'image-box', 'settings' => $s );
	}

	private static function icon_list( $items, $icon_color, $text_color, $icon ) {
		$list = array();
		$i    = 0;
		foreach ( (array) $items as $it ) {
			$list[] = array(
				'text'          => (string) $it,
				'selected_icon' => array( 'value' => $icon, 'library' => 'fa-solid' ),
				'_id'           => 'rl' . ( ++$i ),
			);
		}
		return array( 'type' => 'widget', 'widgetType' => 'icon-list', 'settings' => array(
			'icon_color'    => $icon_color,
			'text_color'    => $text_color,
			'space_between' => array( 'unit' => 'px', 'size' => 10, 'sizes' => array() ),
			'icon_list'     => $list,
			'_element_width' => 'initial',
			'_element_custom_width' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
		) );
	}

	/** Normalise an icon name to an FA5 solid value ("fa-x" or "x" -> "fas fa-x"). */
	private static function fa( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 'fas fa-check';
		}
		if ( 0 === strpos( $name, 'fas ' ) || 0 === strpos( $name, 'far ' ) || 0 === strpos( $name, 'fab ' ) ) {
			return $name;
		}
		if ( 0 !== strpos( $name, 'fa-' ) ) {
			$name = 'fa-' . $name;
		}
		return 'fas ' . $name;
	}
}

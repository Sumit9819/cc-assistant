<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses Elementor's _elementor_data JSON tree into a flat, readable widget map.
 *
 * Output is designed to be consumed by Claude or other tooling: every widget
 * gets a consistent shape with extracted text, links, and structural metadata.
 */
class CC_Assistant_Elementor_Parser {

	/**
	 * Parse a post's Elementor data into a flat widget map.
	 *
	 * @param int $post_id
	 * @return array|null Parsed result or null if the post has no Elementor data.
	 */
	public static function parse( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return null;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$state = array(
			'widgets'       => array(),
			'headings'      => array(),
			'all_links'     => array(),
			'text_chunks'   => array(),
			'section_count' => 0,
			'widget_count'  => 0,
			'max_depth'     => 0,
		);

		self::walk( $data, $state, 0, '' );

		$all_text   = trim( implode( "\n\n", $state['text_chunks'] ) );
		$word_count = $all_text ? str_word_count( wp_strip_all_tags( $all_text ) ) : 0;

		return array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'post_status' => $post->post_status,
			'post_type'  => $post->post_type,
			'permalink'  => get_permalink( $post_id ),
			'edit_url'   => get_edit_post_link( $post_id, 'raw' ),
			'has_elementor' => true,
			'structure'  => array(
				'section_count' => $state['section_count'],
				'widget_count'  => $state['widget_count'],
				'max_depth'     => $state['max_depth'],
			),
			'headings'   => $state['headings'],
			'widgets'    => $state['widgets'],
			'all_links'  => $state['all_links'],
			'all_text'   => $all_text,
			'word_count' => $word_count,
		);
	}

	/**
	 * Recursively walk the element tree.
	 */
	private static function walk( $elements, &$state, $depth, $path ) {
		if ( $depth > $state['max_depth'] ) {
			$state['max_depth'] = $depth;
		}

		foreach ( $elements as $i => $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}

			$el_type    = isset( $el['elType'] ) ? $el['elType'] : '';
			$widget_id  = isset( $el['id'] ) ? $el['id'] : '';
			$node_path  = ( '' === $path ? '' : $path . '/' ) . $el_type . '[' . $i . ']';

			if ( 'section' === $el_type || 'container' === $el_type ) {
				$state['section_count']++;
				// v0.74.1: a container can carry its own link (Elementor renders it
				// as <a class="e-con" href=...> wrapping every child). This walker
				// only ever read widget-level links, so a card grid whose six
				// containers each linked to a service page was reported as
				// "links to nothing" - and that false gap was acted on. Read it.
				if ( ! empty( $el['settings']['link']['url'] ) ) {
					$label = self::first_text_in( isset( $el['elements'] ) ? $el['elements'] : array() );
					$state['all_links'][] = array_merge(
						self::link_obj( $el['settings']['link'], $label ),
						array(
							'widget_id'   => $widget_id,
							'widget_type' => $el_type . '(linked)',
							'wraps_children' => true,
						)
					);
				}
				if ( ! empty( $el['elements'] ) ) {
					self::walk( $el['elements'], $state, $depth + 1, $node_path );
				}
				continue;
			}

			if ( 'column' === $el_type ) {
				if ( ! empty( $el['elements'] ) ) {
					self::walk( $el['elements'], $state, $depth + 1, $node_path );
				}
				continue;
			}

			if ( 'widget' === $el_type ) {
				$state['widget_count']++;
				$widget_data = self::extract_widget( $el, $node_path, $depth, $widget_id );
				$state['widgets'][] = $widget_data;

				if ( ! empty( $widget_data['headings'] ) ) {
					foreach ( $widget_data['headings'] as $h ) {
						$state['headings'][] = $h;
					}
				}
				if ( ! empty( $widget_data['links'] ) ) {
					foreach ( $widget_data['links'] as $link ) {
						$state['all_links'][] = array_merge(
							$link,
							array(
								'widget_id'   => $widget_id,
								'widget_type' => $widget_data['type'],
							)
						);
					}
				}
				if ( ! empty( $widget_data['text'] ) ) {
					$state['text_chunks'][] = $widget_data['text'];
				}

				// Some widgets have nested elements (inner sections inside columns).
				if ( ! empty( $el['elements'] ) ) {
					self::walk( $el['elements'], $state, $depth + 1, $node_path );
				}
			}
		}
	}

	/**
	 * Extract content from a single widget based on its widgetType.
	 */
	private static function extract_widget( $el, $path, $depth, $widget_id ) {
		$type     = isset( $el['widgetType'] ) ? $el['widgetType'] : 'unknown';
		$settings = isset( $el['settings'] ) ? $el['settings'] : array();

		$result = array(
			'id'       => $widget_id,
			'type'     => $type,
			'path'     => $path,
			'depth'    => $depth,
			'text'     => '',
			'headings' => array(),
			'links'    => array(),
			'meta'     => array(),
		);

		switch ( $type ) {
			case 'heading':
				$title = isset( $settings['title'] ) ? wp_strip_all_tags( $settings['title'] ) : '';
				$level = isset( $settings['header_size'] ) ? $settings['header_size'] : 'h2';
				$result['text'] = $title;
				$result['meta']['heading_level'] = $level;
				if ( $title ) {
					$result['headings'][] = array(
						'level'     => $level,
						'text'      => $title,
						'widget_id' => $widget_id,
					);
				}
				if ( ! empty( $settings['link']['url'] ) ) {
					$result['links'][] = self::link_obj( $settings['link'], $title );
				}
				break;

			case 'text-editor':
			case 'theme-post-content':
				$html = isset( $settings['editor'] ) ? $settings['editor'] : '';
				$result['text'] = wp_strip_all_tags( $html );
				$result['headings'] = self::extract_headings_from_html( $html, $widget_id );
				$result['links']    = self::extract_links_from_html( $html );
				break;

			case 'button':
				$text = isset( $settings['text'] ) ? wp_strip_all_tags( $settings['text'] ) : '';
				$result['text'] = $text;
				if ( ! empty( $settings['link']['url'] ) ) {
					$result['links'][] = self::link_obj( $settings['link'], $text );
				}
				break;

			case 'image':
				$alt     = isset( $settings['image']['alt'] ) ? $settings['image']['alt'] : '';
				$caption = isset( $settings['caption'] ) ? wp_strip_all_tags( $settings['caption'] ) : '';
				$src     = isset( $settings['image']['url'] ) ? $settings['image']['url'] : '';
				$result['text'] = trim( $alt . ' ' . $caption );
				$result['meta']['image_url'] = $src;
				$result['meta']['alt']       = $alt;
				$result['meta']['caption']   = $caption;
				if ( ! empty( $settings['link']['url'] ) ) {
					$result['links'][] = self::link_obj( $settings['link'], $alt ?: $caption );
				}
				break;

			case 'image-box':
				$title       = isset( $settings['title_text'] ) ? wp_strip_all_tags( $settings['title_text'] ) : '';
				$description = isset( $settings['description_text'] ) ? wp_strip_all_tags( $settings['description_text'] ) : '';
				$alt         = isset( $settings['image']['alt'] ) ? $settings['image']['alt'] : '';
				$tag         = isset( $settings['title_size'] ) ? strtolower( (string) $settings['title_size'] ) : 'h3';
				$result['text'] = trim( $title . "\n" . $description );
				$result['meta']['alt'] = $alt;
				// image-box renders the title as <h3 class="elementor-image-box-title"> by
				// default (configurable via title_size). Register it as a heading so the
				// pre-publish heading_depth check sees the actual page structure instead
				// of reporting 0 H3s on pages built primarily from these widgets.
				if ( $title && preg_match( '/^h[1-6]$/', $tag ) ) {
					$result['headings'][] = array(
						'level'     => $tag,
						'text'      => $title,
						'widget_id' => $widget_id,
					);
				}
				if ( ! empty( $settings['link']['url'] ) ) {
					$result['links'][] = self::link_obj( $settings['link'], $title );
				}
				break;

			case 'icon-box':
				$title       = isset( $settings['title_text'] ) ? wp_strip_all_tags( $settings['title_text'] ) : '';
				$description = isset( $settings['description_text'] ) ? wp_strip_all_tags( $settings['description_text'] ) : '';
				$tag         = isset( $settings['title_size'] ) ? strtolower( (string) $settings['title_size'] ) : 'h3';
				$result['text'] = trim( $title . "\n" . $description );
				// icon-box renders the title as <h3 class="elementor-icon-box-title"> by
				// default. See image-box note above.
				if ( $title && preg_match( '/^h[1-6]$/', $tag ) ) {
					$result['headings'][] = array(
						'level'     => $tag,
						'text'      => $title,
						'widget_id' => $widget_id,
					);
				}
				if ( ! empty( $settings['link']['url'] ) ) {
					$result['links'][] = self::link_obj( $settings['link'], $title );
				}
				break;

			case 'icon-list':
				$items = isset( $settings['icon_list'] ) && is_array( $settings['icon_list'] ) ? $settings['icon_list'] : array();
				$texts = array();
				foreach ( $items as $item ) {
					$text = isset( $item['text'] ) ? wp_strip_all_tags( $item['text'] ) : '';
					$texts[] = $text;
					if ( ! empty( $item['link']['url'] ) ) {
						$result['links'][] = self::link_obj( $item['link'], $text );
					}
				}
				$result['text'] = implode( "\n", array_filter( $texts ) );
				break;

			case 'accordion':
			case 'toggle':
				// Accordion item titles render as <div class="elementor-tab-title">
				// in v3 toggles, or as a real heading in nested-accordion. The
				// classic accordion widget defaults to <div role="tab"> not a
				// real heading element — but the title_html_tag setting (added
				// in Elementor 3.0) lets the user choose h1-h6. Honour it when
				// set; otherwise emit as h3 (closest semantic match) so that
				// the pre-publish heading_depth check counts these structurally.
				$tabs = isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ? $settings['tabs'] : array();
				$tag  = isset( $settings['title_html_tag'] ) ? strtolower( (string) $settings['title_html_tag'] ) : 'h3';
				if ( ! preg_match( '/^h[1-6]$/', $tag ) ) {
					$tag = 'h3';
				}
				$texts = array();
				foreach ( $tabs as $tab ) {
					$title   = isset( $tab['tab_title'] ) ? wp_strip_all_tags( $tab['tab_title'] ) : '';
					$content = isset( $tab['tab_content'] ) ? wp_strip_all_tags( $tab['tab_content'] ) : '';
					if ( $title ) {
						$texts[] = $title;
						$result['headings'][] = array(
							'level'     => $tag,
							'text'      => $title,
							'widget_id' => $widget_id,
						);
					}
					if ( $content ) {
						$texts[] = $content;
					}
					if ( ! empty( $tab['tab_content'] ) ) {
						$tab_links = self::extract_links_from_html( $tab['tab_content'] );
						foreach ( $tab_links as $l ) {
							$result['links'][] = $l;
						}
					}
				}
				$result['text'] = implode( "\n\n", array_filter( $texts ) );
				break;

			case 'tabs':
				$tabs = isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ? $settings['tabs'] : array();
				$texts = array();
				foreach ( $tabs as $tab ) {
					$title   = isset( $tab['tab_title'] ) ? wp_strip_all_tags( $tab['tab_title'] ) : '';
					$content = isset( $tab['tab_content'] ) ? wp_strip_all_tags( $tab['tab_content'] ) : '';
					$texts[] = trim( $title . "\n" . $content );
					if ( ! empty( $tab['tab_content'] ) ) {
						$tab_links = self::extract_links_from_html( $tab['tab_content'] );
						foreach ( $tab_links as $l ) {
							$result['links'][] = $l;
						}
					}
				}
				$result['text'] = implode( "\n\n", array_filter( $texts ) );
				break;

			case 'testimonial':
				$content = isset( $settings['testimonial_content'] ) ? wp_strip_all_tags( $settings['testimonial_content'] ) : '';
				$name    = isset( $settings['testimonial_name'] ) ? wp_strip_all_tags( $settings['testimonial_name'] ) : '';
				$job     = isset( $settings['testimonial_job'] ) ? wp_strip_all_tags( $settings['testimonial_job'] ) : '';
				$result['text'] = trim( $content . "\n" . $name . ( $job ? ' - ' . $job : '' ) );
				$result['meta']['attribution'] = trim( $name . ( $job ? ' - ' . $job : '' ) );
				break;

			case 'call-to-action':
				$title       = isset( $settings['title'] ) ? wp_strip_all_tags( $settings['title'] ) : '';
				$description = isset( $settings['description'] ) ? wp_strip_all_tags( $settings['description'] ) : '';
				$button_text = isset( $settings['button'] ) ? wp_strip_all_tags( $settings['button'] ) : '';
				$result['text'] = trim( $title . "\n" . $description . "\n" . $button_text );
				if ( ! empty( $settings['link']['url'] ) ) {
					$result['links'][] = self::link_obj( $settings['link'], $button_text );
				}
				break;

			case 'html':
			case 'shortcode':
				$html = isset( $settings['html'] ) ? $settings['html'] : ( isset( $settings['shortcode'] ) ? $settings['shortcode'] : '' );
				$result['text'] = wp_strip_all_tags( $html );
				$result['headings'] = self::extract_headings_from_html( $html, $widget_id );
				$result['links']    = self::extract_links_from_html( $html );
				break;

			case 'video':
				$url = isset( $settings['youtube_url'] ) ? $settings['youtube_url'] : ( isset( $settings['vimeo_url'] ) ? $settings['vimeo_url'] : '' );
				$result['meta']['video_url'] = $url;
				break;

			case 'spacer':
			case 'divider':
				// No content to extract.
				break;

			case 'nested-accordion':
				// Elementor v3 nested-accordion uses an `items` array; each item
				// has `title` (and the item's content is the widget's nested
				// elements, walked separately by the recursion). Front-end
				// renders each title as <h3 class="e-n-accordion-item-title-text">
				// by default, configurable via title_tag.
				$items = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : array();
				$tag   = isset( $settings['title_tag'] ) ? strtolower( (string) $settings['title_tag'] ) : 'h3';
				if ( ! preg_match( '/^h[1-6]$/', $tag ) ) {
					$tag = 'h3';
				}
				$texts = array();
				foreach ( $items as $item ) {
					$title = isset( $item['title'] ) ? wp_strip_all_tags( $item['title'] ) : '';
					if ( $title ) {
						$texts[] = $title;
						$result['headings'][] = array(
							'level'     => $tag,
							'text'      => $title,
							'widget_id' => $widget_id,
						);
					}
				}
				$result['text'] = implode( "\n", array_filter( $texts ) );
				break;

			case 'posts':
			case 'post-feed':
			case 'theme-posts':
			case 'archive-posts':
				// Posts/loop widgets render dynamic post cards. Each card title
				// is an <h3 class="elementor-post__title"> by default. The widget
				// settings expose title_size for the level. We can't enumerate
				// the actual post titles at parse time (they're queried at
				// render), so emit a single placeholder heading at the
				// configured level so heading_depth at least counts the
				// presence of a posts grid (better than 0).
				$tag = isset( $settings['title_size'] ) ? strtolower( (string) $settings['title_size'] ) : 'h3';
				if ( ! preg_match( '/^h[1-6]$/', $tag ) ) {
					$tag = 'h3';
				}
				$result['headings'][] = array(
					'level'     => $tag,
					'text'      => '[posts grid]',
					'widget_id' => $widget_id,
				);
				$result['meta']['title_tag'] = $tag;
				break;

			default:
				// Generic fallback: probe common keys.
				foreach ( array( 'title', 'text', 'content', 'editor', 'description' ) as $key ) {
					if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
						$result['text'] .= ( $result['text'] ? "\n" : '' ) . wp_strip_all_tags( $settings[ $key ] );
					}
				}
				if ( ! empty( $settings['link']['url'] ) ) {
					$result['links'][] = self::link_obj( $settings['link'], $result['text'] );
				}
				break;
		}

		return $result;
	}

	/**
	 * Build a normalized link object.
	 */
	/**
	 * Best-effort accessible name for a linked container: the first heading or
	 * text-bearing setting among its descendants. Mirrors what a reader sees as
	 * the card's label.
	 */
	private static function first_text_in( $elements ) {
		foreach ( (array) $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$s = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
			foreach ( array( 'title', 'title_text', 'text', 'editor' ) as $k ) {
				if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
					$t = trim( wp_strip_all_tags( $s[ $k ] ) );
					if ( '' !== $t && 'Popular' !== $t ) {
						return $t;
					}
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$t = self::first_text_in( $el['elements'] );
				if ( '' !== $t ) {
					return $t;
				}
			}
		}
		return '';
	}

	private static function link_obj( $link_settings, $anchor = '' ) {
		$url = isset( $link_settings['url'] ) ? $link_settings['url'] : '';
		return array(
			'url'         => $url,
			'anchor'      => trim( wp_strip_all_tags( $anchor ) ),
			'is_external' => ! empty( $link_settings['is_external'] ),
			'nofollow'    => ! empty( $link_settings['nofollow'] ),
			'is_internal' => self::is_internal_url( $url ),
		);
	}

	/**
	 * Extract <h1> through <h6> tags from an HTML blob.
	 */
	private static function extract_headings_from_html( $html, $widget_id ) {
		$results = array();
		if ( empty( $html ) ) {
			return $results;
		}
		if ( preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$results[] = array(
					'level'     => 'h' . $m[1],
					'text'      => trim( wp_strip_all_tags( $m[2] ) ),
					'widget_id' => $widget_id,
				);
			}
		}
		return $results;
	}

	/**
	 * Extract <a href> links from an HTML blob.
	 */
	private static function extract_links_from_html( $html ) {
		$results = array();
		if ( empty( $html ) ) {
			return $results;
		}
		if ( preg_match_all( '#<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$url       = $m[1];
				$anchor    = trim( wp_strip_all_tags( $m[2] ) );
				$rel_match = array();
				preg_match( '#rel=["\']([^"\']+)["\']#i', $m[0], $rel_match );
				$rel       = isset( $rel_match[1] ) ? strtolower( $rel_match[1] ) : '';
				$results[] = array(
					'url'         => $url,
					'anchor'      => $anchor,
					'is_external' => self::is_external_url( $url ),
					'nofollow'    => false !== strpos( $rel, 'nofollow' ),
					'is_internal' => self::is_internal_url( $url ),
				);
			}
		}
		return $results;
	}

	private static function is_internal_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		if ( 0 === strpos( $url, '#' ) || 0 === strpos( $url, '/' ) || 0 === strpos( $url, '?' ) ) {
			return true;
		}
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$link_host = wp_parse_url( $url, PHP_URL_HOST );
		return $site_host && $link_host && $site_host === $link_host;
	}

	private static function is_external_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		if ( 0 === strpos( $url, '#' ) || 0 === strpos( $url, '/' ) ) {
			return false;
		}
		return ! self::is_internal_url( $url );
	}
}

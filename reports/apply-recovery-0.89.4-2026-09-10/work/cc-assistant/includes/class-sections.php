<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section-level editing tools (v0.31.0).
 *
 * Up to v0.30 the only ways to mutate an Elementor page were:
 *   1. draft_update_elementor_widget — one widget at a time
 *   2. draft_add_elementor_container / draft_add_elementor_widget — append new
 *   3. elementor_full_import — replace the whole tree
 *
 * For pages cloned from another site, the typical workflow is "this whole
 * section is wrong — swap its content but keep its layout/widgets." Doing
 * that one widget at a time means 8–15 separate pending changes that the
 * reviewer must approve individually, and any failure mid-batch leaves the
 * page half-translated.
 *
 * This class fixes that gap:
 *
 *   list_sections($post_id)
 *     Returns one row per root-level container with: section id, position,
 *     heading text (closest H1/H2 inside), widget inventory, background
 *     color (resolved through Kit globals), and a content fingerprint so
 *     the AI can spot which sections still have unmodified source-site
 *     content.
 *
 *   replace_section_content($post_id, $section_id, $widget_updates)
 *     Queues ONE pending that swaps text/color/image fields across many
 *     widgets inside a section in a single atomic apply. Reviewer sees a
 *     single line "section #abc1234: 8 widgets updated" instead of 8 rows.
 *
 * Everything routes through CC_Assistant_Pending_Changes — draft-only.
 */
class CC_Assistant_Sections {

	const CHANGE_TYPE = 'elementor_section_content_replace';

	/**
	 * Walk the root-level containers and emit one summary per section.
	 *
	 * Returns:
	 *   sections: array of {
	 *     id, index, heading, heading_level, widget_count,
	 *     widget_types (type=>count), background_color_resolved,
	 *     content_fingerprint, has_foreign_schema, has_cross_domain_images,
	 *     has_banned_phrases
	 *   }
	 *   industry: the resolved industry slug
	 *   home_host: this site's host (for cross-domain detection callers)
	 */
	public static function list_sections( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', 'post_id is required.' );
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return new WP_Error( 'no_elementor_data', 'Post has no Elementor data.' );
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return new WP_Error( 'invalid_json', 'Could not parse _elementor_data.' );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
		$industry      = method_exists( 'CC_Assistant_Industry_Profile', 'industry_slug' ) ? CC_Assistant_Industry_Profile::industry_slug() : 'general';
		$banned        = method_exists( 'CC_Assistant_Industry_Profile', 'banned_phrases' ) ? CC_Assistant_Industry_Profile::banned_phrases( $industry ) : array();
		$home_host     = strtolower( preg_replace( '#^www\.#i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		$kit_globals   = method_exists( 'CC_Assistant_Elementor_IO', 'read_kit_globals' )
			? CC_Assistant_Elementor_IO::read_kit_globals()
			: array( 'colors' => array() );

		$sections = array();
		foreach ( $tree as $idx => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$widget_types     = array();
			$widget_count     = 0;
			$banned_hits      = array();
			$foreign_schema   = false;
			$cross_domain_img = array();
			$texts            = array();
			self::scan_section_recursive( $node, $widget_types, $widget_count, $banned, $banned_hits, $home_host, $foreign_schema, $cross_domain_img, $texts );

			arsort( $widget_types );

			$bg_resolved = self::resolve_section_bg( $node, $kit_globals );
			$heading     = self::first_heading_within( $node );

			$sections[] = array(
				'id'                        => isset( $node['id'] ) ? (string) $node['id'] : '',
				'index'                     => $idx,
				'heading'                   => isset( $heading['title'] ) ? $heading['title'] : '',
				'heading_level'             => isset( $heading['level'] ) ? $heading['level'] : '',
				'widget_count'              => $widget_count,
				'widget_types'              => $widget_types,
				'background_color_resolved' => $bg_resolved,
				'content_fingerprint'       => substr( md5( implode( "\n", $texts ) ), 0, 12 ),
				'banned_phrase_hits'        => $banned_hits,
				'has_banned_phrases'        => ! empty( $banned_hits ),
				'has_foreign_schema'        => $foreign_schema,
				'cross_domain_image_count'  => count( $cross_domain_img ),
			);
		}

		return array(
			'post_id'   => $post_id,
			'industry'  => $industry,
			'home_host' => $home_host,
			'section_count' => count( $sections ),
			'sections'  => $sections,
		);
	}

	/**
	 * Queue a single pending change that mutates many widgets inside one
	 * root-level section atomically. Useful for "swap source-site content
	 * out of a cloned page" workflows where 8-12 sibling widgets all need
	 * updates and the reviewer should see them as one atomic change.
	 *
	 * $widget_updates: array of {
	 *   widget_id: string (target widget's existing id)
	 *   settings:  array  (partial settings to merge — NOT replace)
	 * }
	 *
	 * Apply behavior is a deep-merge per widget — strings/scalars replace,
	 * arrays merge by key. To wipe an array field, pass an explicit empty
	 * array.
	 *
	 * If section_id is empty, treats the whole document as one section
	 * (allows whole-page batch text-and-color rewrites).
	 *
	 * Returns { pending_id, summary } or WP_Error.
	 */
	public static function queue_replace_section_content( $post_id, $section_id, $widget_updates, $reasoning = '', $override_lint = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', 'post_id is required.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post %d not found.', $post_id ) );
		}
		if ( ! is_array( $widget_updates ) || empty( $widget_updates ) ) {
			return new WP_Error( 'no_widget_updates', 'widget_updates must be a non-empty array.' );
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return new WP_Error( 'no_elementor_data', 'Post has no Elementor data.' );
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return new WP_Error( 'invalid_json', 'Could not parse _elementor_data.' );
		}

		// Validate each update has a widget_id + settings, and that the
		// widget exists inside the named section (or anywhere if section_id
		// is empty).
		$section_id = (string) $section_id;
		$scope      = null;
		if ( '' !== $section_id ) {
			$scope = self::find_section_by_id( $tree, $section_id );
			if ( ! $scope ) {
				return new WP_Error( 'section_not_found', sprintf( 'Section %s not found at root level.', $section_id ) );
			}
		}

		$normalized = array();
		$missing_ids = array();
		foreach ( $widget_updates as $upd ) {
			if ( ! is_array( $upd ) || empty( $upd['widget_id'] ) ) {
				return new WP_Error( 'invalid_update', 'Each update must include a widget_id.' );
			}
			$wid = (string) $upd['widget_id'];
			$settings = isset( $upd['settings'] ) && is_array( $upd['settings'] ) ? $upd['settings'] : array();
			if ( empty( $settings ) ) {
				continue;
			}
			$node = self::find_node_in_scope( $scope ? $scope : $tree, $wid );
			if ( ! $node ) {
				$missing_ids[] = $wid;
				continue;
			}
			$normalized[] = array(
				'widget_id'  => $wid,
				'widget_type' => isset( $node['widgetType'] ) ? (string) $node['widgetType'] : ( isset( $node['elType'] ) ? (string) $node['elType'] : '' ),
				'settings'   => $settings,
			);
		}

		if ( ! empty( $missing_ids ) ) {
			return new WP_Error(
				'widget_ids_not_in_section',
				sprintf( '%d widget id(s) not found in section %s: %s', count( $missing_ids ), $section_id, implode( ', ', array_slice( $missing_ids, 0, 5 ) ) )
			);
		}
		if ( empty( $normalized ) ) {
			return new WP_Error( 'empty_updates', 'No non-empty settings updates to apply.' );
		}

		// Content-quality gate. Before v0.36 this path ran ZERO lint, so it
		// could launder banned content (em dashes, AI-tells, style-guide
		// violations, placeholder / lorem-ipsum text, walls of text, a wrong
		// street address, hospital comparisons) onto a page while every other
		// queue path (container_add / widget_add / post_content) refused it.
		// Mirror the container_add gate; bypassable with override_lint=true.
		if ( ! $override_lint ) {
			$lint_error = self::lint_widget_updates( $normalized );
			if ( is_wp_error( $lint_error ) ) {
				return $lint_error;
			}
		}

		$payload = array(
			'section_id'     => $section_id,
			'widget_updates' => $normalized,
		);

		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';

		$change_summary = sprintf(
			'Section content replace: section %s, %d widget(s)',
			'' !== $section_id ? $section_id : '(whole page)',
			count( $normalized )
		);

		$current_payload = array(
			'section_id'      => $section_id,
			'widget_count'    => count( $normalized ),
			'widget_id_list'  => array_map( function ( $u ) { return $u['widget_id']; }, $normalized ),
		);

		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => $post_id,
				'change_type'    => self::CHANGE_TYPE,
				'change_summary' => $change_summary,
				'current_value'  => wp_json_encode( $current_payload ),
				'proposed_value' => wp_json_encode( $payload ),
				'reasoning'      => (string) $reasoning,
			)
		);

		if ( is_wp_error( $pending_id ) ) {
			return $pending_id;
		}

		return array(
			'pending_id' => (int) $pending_id,
			'summary'    => $change_summary,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
		);
	}

	/**
	 * Per-widget content lint for queued section-replace updates. Mirrors the
	 * container_add gate (CC_Assistant_REST_API::lint_per_widget_payloads):
	 * extracts the text-bearing fields from each partial settings update and
	 * runs CC_Assistant_Pre_Publish::lint_html_block_per_widget, then refuses
	 * on the same hard-violation set. Returns null when clean (or when the
	 * linter is unavailable), or a WP_Error (422) listing the failed checks.
	 */
	private static function lint_widget_updates( array $normalized ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		if ( ! method_exists( 'CC_Assistant_Pre_Publish', 'lint_html_block_per_widget' ) ) {
			return null;
		}

		$payloads = array();
		foreach ( $normalized as $u ) {
			$settings = isset( $u['settings'] ) && is_array( $u['settings'] ) ? $u['settings'] : array();
			$html = '';
			// Same text-bearing field list the container_add walker uses
			// (class-rest-api.php collect_per_widget_lint_payloads) so both
			// paths see identical content.
			foreach ( array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' ) as $field ) {
				if ( isset( $settings[ $field ] ) && is_string( $settings[ $field ] ) ) {
					$html .= "\n" . $settings[ $field ];
				}
			}
			$payloads[] = array(
				'html'     => $html,
				'settings' => $settings,
				'label'    => ( ! empty( $u['widget_type'] ) ? (string) $u['widget_type'] : 'widget' )
					. ' ' . ( isset( $u['widget_id'] ) ? (string) $u['widget_id'] : '' ),
			);
		}
		if ( empty( $payloads ) ) {
			return null;
		}

		$block_lint   = CC_Assistant_Pre_Publish::lint_html_block_per_widget( $payloads );
		if ( method_exists( 'CC_Assistant_Pre_Publish', 'detect_duplicate_blocks' ) ) {
			$dup = CC_Assistant_Pre_Publish::detect_duplicate_blocks( wp_list_pluck( $payloads, 'html' ) );
			if ( $dup > 0 ) {
				$block_lint['duplicate_card_text'] = array(
					'pass'    => false,
					'message' => sprintf( '%d block(s) of identical text repeated across 3+ widgets — unfilled template. Give each card unique copy.', $dup ),
				);
			}
		}
		$hard         = array();
		$probe_layers = array();
		if ( ! empty( $block_lint['checks'] ) && is_array( $block_lint['checks'] ) ) {
			$probe_layers[] = $block_lint['checks'];
		}
		$probe_layers[] = $block_lint;
		foreach ( array( 'em_dashes', 'ai_tells', 'style_guide', 'placeholders', 'wall_of_text', 'address_consistency', 'hospital_comparison' ) as $name ) {
			foreach ( $probe_layers as $layer ) {
				if ( isset( $layer[ $name ]['pass'] ) && empty( $layer[ $name ]['pass'] ) ) {
					$hard[] = $name;
					break;
				}
			}
		}

		if ( ! empty( $hard ) ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf(
					'Section-replace content fails: %s. Fix and resubmit, or pass override_lint=true to queue anyway.',
					implode( ', ', $hard )
				),
				array(
					'status' => 422,
					'lint'   => array_merge( (array) $block_lint, array( 'hard_violations' => $hard ) ),
				)
			);
		}
		return null;
	}

	/**
	 * Called from class-apply.php switch. Loads the tree, finds each
	 * widget by id, deep-merges the proposed settings, saves through
	 * CC_Assistant_Elementor_Builder::save_tree (which validates and
	 * graduates _elementor_edit_mode if needed).
	 */
	public static function apply_section_content_replace( $pending_id, $post_id, $proposed, $bulk_mode = false ) {
		if ( ! is_array( $proposed ) || empty( $proposed['widget_updates'] ) ) {
			return new WP_Error( 'invalid_payload', 'proposed_value missing widget_updates.' );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';

		if ( method_exists( 'CC_Assistant_Snapshots', 'snapshot_post' ) ) {
			$snapshot = CC_Assistant_Snapshots::snapshot_post( (int) $post_id, 'pre_section_content_replace', (int) $pending_id );
			if ( is_wp_error( $snapshot ) ) { return $snapshot; }
		}

		$tree = CC_Assistant_Elementor_Builder::load_tree( (int) $post_id );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$section_id = isset( $proposed['section_id'] ) ? (string) $proposed['section_id'] : '';
		$scope      = null;
		if ( '' !== $section_id ) {
			$scope_ref =& self::find_section_ref_by_id( $tree, $section_id );
			if ( ! is_array( $scope_ref ) ) {
				return new WP_Error( 'section_not_found_on_apply', 'Section disappeared between queue and apply.' );
			}
			$scope =& $scope_ref;
		}

		$applied = 0;
		$missing = array();
		foreach ( $proposed['widget_updates'] as $upd ) {
			if ( empty( $upd['widget_id'] ) || ! isset( $upd['settings'] ) || ! is_array( $upd['settings'] ) ) {
				continue;
			}
			// v0.31.1 fix: two bugs in v0.31, both blocking on WP 7.0 (PHP 8.1+):
			//
			// (1) Ternary-as-by-reference-arg fatal. The previous call site
			//     `find_node( $scope ? $scope['elements'] : $tree, ... )` passes
			//     a ternary expression to a `&$tree` parameter. PHP 8.0 warned
			//     and silently used a temporary; PHP 8.1+ throws a hard fatal
			//     "Only variables should be passed by reference". WP 7.0
			//     "Armstrong" bumped PHP min to 8.1, so every section-content-
			//     replace apply on heavy payloads (hero bg image overlay) died
			//     here. Fixed by binding the search target to a real variable
			//     reference before the call.
			//
			// (2) Wrapper-vs-node confusion. CC_Assistant_Elementor_Builder::find_node
			//     returns a WRAPPER of shape { node => &actual, parent_elements
			//     => &siblings, index => int } — NOT the node itself. v0.31
			//     treated the wrapper as the node, so settings got attached to
			//     the wrapper (a local var) instead of the tree node. Apply
			//     silently no-op'd for widget settings updates. Now we reach
			//     through ['node'] to mutate the real tree node.
			if ( null !== $scope && isset( $scope['elements'] ) && is_array( $scope['elements'] ) ) {
				$search_in =& $scope['elements'];
			} else {
				$search_in =& $tree;
			}
			$wrapper_ref =& CC_Assistant_Elementor_Builder::find_node( $search_in, (string) $upd['widget_id'] );
			unset( $search_in );
			if ( ! is_array( $wrapper_ref ) || ! isset( $wrapper_ref['node'] ) ) {
				// fall back to whole-tree search if scope lookup failed
				unset( $wrapper_ref );
				$wrapper_ref =& CC_Assistant_Elementor_Builder::find_node( $tree, (string) $upd['widget_id'] );
			}
			if ( ! is_array( $wrapper_ref ) || ! isset( $wrapper_ref['node'] ) || ! is_array( $wrapper_ref['node'] ) ) {
				$missing[] = $upd['widget_id'];
				continue;
			}
			$node_ref =& $wrapper_ref['node'];
			if ( ! isset( $node_ref['settings'] ) || ! is_array( $node_ref['settings'] ) ) {
				$node_ref['settings'] = array();
			}
			// Deep merge: arrays merge by key, scalars overwrite. Mutates the
			// real tree node via reference.
			$node_ref['settings'] = self::deep_merge( $node_ref['settings'], $upd['settings'] );
			$applied++;
			unset( $node_ref );
			unset( $wrapper_ref );
		}

		$save = CC_Assistant_Elementor_Builder::save_tree( (int) $post_id, $tree );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		// Flush Elementor CSS unless bulk.
		if ( ! $bulk_mode ) {
			if ( class_exists( 'CC_Assistant_Apply' ) && method_exists( 'CC_Assistant_Apply', 'flush_elementor_css_cache' ) ) {
				CC_Assistant_Apply::flush_elementor_css_cache();
			} elseif ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		}

		return array(
			'applied' => $applied,
			'missing' => $missing,
		);
	}

	// ---- helpers --------------------------------------------------------

	private static function scan_section_recursive( $node, &$widget_types, &$widget_count, $banned, &$banned_hits, $home_host, &$foreign_schema, &$cross_domain_img, &$texts ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		if ( isset( $node['widgetType'] ) ) {
			$t = (string) $node['widgetType'];
			$widget_types[ $t ] = isset( $widget_types[ $t ] ) ? $widget_types[ $t ] + 1 : 1;
			$widget_count++;
		}
		$s = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$text_fields = array( 'title', 'editor', 'text', 'title_text', 'description_text', 'html', 'content', 'item_title' );
		foreach ( $text_fields as $f ) {
			if ( isset( $s[ $f ] ) && is_string( $s[ $f ] ) && '' !== $s[ $f ] ) {
				$plain = wp_strip_all_tags( $s[ $f ] );
				$texts[] = $plain;
				foreach ( $banned as $bp ) {
					if ( '' !== $bp && false !== stripos( $plain, $bp ) ) {
						$banned_hits[] = array(
							'widget_id' => isset( $node['id'] ) ? (string) $node['id'] : '',
							'field'     => $f,
							'phrase'    => $bp,
						);
						break;
					}
				}
			}
		}
		// nested-accordion items
		if ( isset( $s['items'] ) && is_array( $s['items'] ) ) {
			foreach ( $s['items'] as $it ) {
				if ( ! is_array( $it ) ) {
					continue;
				}
				$it_title = isset( $it['item_title'] ) ? wp_strip_all_tags( (string) $it['item_title'] ) : '';
				if ( '' !== $it_title ) {
					$texts[] = $it_title;
					foreach ( $banned as $bp ) {
						if ( '' !== $bp && false !== stripos( $it_title, $bp ) ) {
							$banned_hits[] = array(
								'widget_id' => isset( $node['id'] ) ? (string) $node['id'] : '',
								'field'     => 'items.item_title',
								'phrase'    => $bp,
							);
							break;
						}
					}
				}
			}
		}
		// HTML/text-editor JSON-LD foreign-schema sniff
		if ( isset( $node['widgetType'] ) && in_array( (string) $node['widgetType'], array( 'html', 'text-editor' ), true ) ) {
			$body = '';
			if ( isset( $s['html'] ) && is_string( $s['html'] ) ) {
				$body = $s['html'];
			} elseif ( isset( $s['editor'] ) && is_string( $s['editor'] ) ) {
				$body = $s['editor'];
			}
			if ( '' !== $body && false !== stripos( $body, 'ld+json' ) && '' !== $home_host ) {
				if ( preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $body, $m ) ) {
					foreach ( $m[1] as $blob ) {
						$data = json_decode( trim( $blob ), true );
						if ( ! is_array( $data ) ) {
							continue;
						}
						$hosts = array();
						self::collect_json_hosts( $data, $hosts );
						foreach ( $hosts as $h ) {
							$h = strtolower( preg_replace( '#^www\.#i', '', (string) $h ) );
							if ( '' !== $h && $h !== $home_host ) {
								$foreign_schema = true;
								break 2;
							}
						}
					}
				}
			}
		}
		// image cross-domain
		if ( isset( $s['image']['url'] ) && is_string( $s['image']['url'] ) ) {
			$h = wp_parse_url( $s['image']['url'], PHP_URL_HOST );
			if ( $h ) {
				$h = strtolower( preg_replace( '#^www\.#i', '', (string) $h ) );
				if ( '' !== $home_host && '' !== $h && $h !== $home_host ) {
					$cross_domain_img[] = $h;
				}
			}
		}
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				self::scan_section_recursive( $child, $widget_types, $widget_count, $banned, $banned_hits, $home_host, $foreign_schema, $cross_domain_img, $texts );
			}
		}
	}

	private static function collect_json_hosts( $node, &$out ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $k => $v ) {
			if ( in_array( $k, array( '@id', 'url', 'contentUrl', 'image', 'sameAs', 'mainEntityOfPage' ), true ) ) {
				if ( is_string( $v ) ) {
					$h = wp_parse_url( $v, PHP_URL_HOST );
					if ( $h ) {
						$out[] = $h;
					}
				} elseif ( is_array( $v ) ) {
					foreach ( $v as $item ) {
						if ( is_string( $item ) ) {
							$h = wp_parse_url( $item, PHP_URL_HOST );
							if ( $h ) {
								$out[] = $h;
							}
						} else {
							self::collect_json_hosts( $item, $out );
						}
					}
				}
			} elseif ( is_array( $v ) ) {
				self::collect_json_hosts( $v, $out );
			}
		}
	}

	private static function first_heading_within( $node, $depth = 0 ) {
		if ( $depth > 6 ) {
			return array();
		}
		if ( isset( $node['widgetType'] ) && 'heading' === $node['widgetType'] ) {
			$s = isset( $node['settings'] ) ? $node['settings'] : array();
			return array(
				'title' => isset( $s['title'] ) ? mb_substr( wp_strip_all_tags( (string) $s['title'] ), 0, 200 ) : '',
				'level' => isset( $s['header_size'] ) ? (string) $s['header_size'] : 'h2',
			);
		}
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				$found = self::first_heading_within( $child, $depth + 1 );
				if ( ! empty( $found ) ) {
					return $found;
				}
			}
		}
		return array();
	}

	private static function resolve_section_bg( $node, $kit_globals ) {
		$s = isset( $node['settings'] ) ? $node['settings'] : array();
		$bg = isset( $s['background_color'] ) ? (string) $s['background_color'] : '';
		if ( '' !== $bg ) {
			return strtolower( $bg );
		}
		// Resolve through __globals__ if present
		if ( isset( $s['__globals__']['background_color'] ) && is_string( $s['__globals__']['background_color'] ) ) {
			if ( preg_match( '#id=([^&]+)#', $s['__globals__']['background_color'], $m ) ) {
				$tok = $m[1];
				if ( isset( $kit_globals['colors'][ $tok ]['color'] ) ) {
					return strtolower( (string) $kit_globals['colors'][ $tok ]['color'] );
				}
				// system tokens: look up by name
				foreach ( (array) ( isset( $kit_globals['colors'] ) ? $kit_globals['colors'] : array() ) as $cid => $c ) {
					if ( isset( $c['title'] ) && 0 === strcasecmp( $tok, (string) $c['title'] ) && isset( $c['color'] ) ) {
						return strtolower( (string) $c['color'] );
					}
				}
			}
		}
		return '(inherit/white)';
	}

	private static function find_section_by_id( $tree, $section_id ) {
		foreach ( (array) $tree as $node ) {
			if ( is_array( $node ) && isset( $node['id'] ) && (string) $node['id'] === $section_id ) {
				return $node;
			}
		}
		return null;
	}

	private static function &find_section_ref_by_id( &$tree, $section_id ) {
		foreach ( $tree as &$node ) {
			if ( is_array( $node ) && isset( $node['id'] ) && (string) $node['id'] === $section_id ) {
				return $node;
			}
		}
		unset( $node );
		$null = null;
		return $null;
	}

	private static function find_node_in_scope( $scope, $widget_id ) {
		// $scope can be a single node (section) or an array (whole tree).
		if ( isset( $scope['id'] ) && (string) $scope['id'] === $widget_id ) {
			return $scope;
		}
		$kids = array();
		if ( isset( $scope['elements'] ) && is_array( $scope['elements'] ) ) {
			$kids = $scope['elements'];
		} elseif ( is_array( $scope ) && ! isset( $scope['elements'] ) ) {
			// Top-level call: $scope is the whole $tree array (list of nodes)
			$kids = $scope;
		}
		foreach ( $kids as $child ) {
			$found = self::find_node_in_scope( $child, $widget_id );
			if ( $found ) {
				return $found;
			}
		}
		return null;
	}

	private static function deep_merge( $base, $overlay ) {
		if ( ! is_array( $base ) ) {
			$base = array();
		}
		foreach ( (array) $overlay as $k => $v ) {
			if ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) ) {
				$base[ $k ] = self::deep_merge( $base[ $k ], $v );
			} else {
				$base[ $k ] = $v;
			}
		}
		return $base;
	}
}

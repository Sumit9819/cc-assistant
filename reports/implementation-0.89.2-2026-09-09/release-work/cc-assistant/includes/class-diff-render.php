<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pending-changes diff rendering. Pulled out of admin/views/pending.php so the
 * inbox can lazy-load diff HTML on group expand instead of rendering every
 * row's diff at page load (Elementor JSON parse + word-aware diff is heavy
 * once you hit 50+ items).
 */
class CC_Assistant_Diff_Render {

	/**
	 * Word-aware prefix/suffix diff. Returns the longest common prefix and suffix
	 * (rounded to whitespace boundaries) plus the divergent middles for each side.
	 */
	public static function compute( $before, $after ) {
		$b_len = strlen( $before );
		$a_len = strlen( $after );

		$min_len    = min( $b_len, $a_len );
		$prefix_len = 0;
		while ( $prefix_len < $min_len && $before[ $prefix_len ] === $after[ $prefix_len ] ) {
			$prefix_len++;
		}

		$suffix_len = 0;
		$max_suffix = min( $b_len - $prefix_len, $a_len - $prefix_len );
		while ( $suffix_len < $max_suffix && $before[ $b_len - 1 - $suffix_len ] === $after[ $a_len - 1 - $suffix_len ] ) {
			$suffix_len++;
		}

		// Round prefix back to a whitespace boundary so we do not split mid-word.
		if ( $prefix_len > 0 && $prefix_len < $b_len && ! ctype_space( $before[ $prefix_len - 1 ] ) && ! ctype_space( $before[ $prefix_len ] ) ) {
			$walk = $prefix_len;
			while ( $walk > 0 && ! ctype_space( $before[ $walk - 1 ] ) ) {
				$walk--;
			}
			$prefix_len = $walk;
		}

		// Round suffix forward to a whitespace boundary.
		if ( $suffix_len > 0 ) {
			$b_diff_end = $b_len - $suffix_len;
			if ( $b_diff_end > 0 && $b_diff_end < $b_len && ! ctype_space( $before[ $b_diff_end - 1 ] ) && ! ctype_space( $before[ $b_diff_end ] ) ) {
				$walk = $suffix_len;
				$pos  = $b_len - $walk;
				while ( $pos < $b_len && ! ctype_space( $before[ $pos ] ) ) {
					$pos++;
					$walk--;
					if ( $walk <= 0 ) {
						break;
					}
				}
				$suffix_len = max( 0, $walk );
			}
		}

		return array(
			'prefix'     => substr( $before, 0, $prefix_len ),
			'before_mid' => substr( $before, $prefix_len, $b_len - $prefix_len - $suffix_len ),
			'after_mid'  => substr( $after, $prefix_len, $a_len - $prefix_len - $suffix_len ),
			'suffix'     => $suffix_len > 0 ? substr( $before, -$suffix_len ) : '',
		);
	}

	/**
	 * Trim a long unchanged prefix/suffix down to a few chars of context with ellipsis.
	 */
	public static function trim_context( $prefix, $suffix, $context = 100 ) {
		$prefix_trim = $prefix;
		$suffix_trim = $suffix;
		if ( strlen( $prefix ) > $context ) {
			$tail = substr( $prefix, -$context );
			$ws   = strpos( $tail, ' ' );
			if ( false !== $ws && $ws < $context - 10 ) {
				$tail = substr( $tail, $ws + 1 );
			}
			$prefix_trim = '…' . $tail;
		}
		if ( strlen( $suffix ) > $context ) {
			$head = substr( $suffix, 0, $context );
			$ws   = strrpos( $head, ' ' );
			if ( false !== $ws && $ws > 10 ) {
				$head = substr( $head, 0, $ws );
			}
			$suffix_trim = $head . '…';
		}
		return array( $prefix_trim, $suffix_trim );
	}

	public static function smart_text_diff( $before, $after ) {
		$before_text = trim( wp_strip_all_tags( (string) $before ) );
		$after_text  = trim( wp_strip_all_tags( (string) $after ) );
		$before_text = preg_replace( '/\s+/', ' ', $before_text );
		$after_text  = preg_replace( '/\s+/', ' ', $after_text );

		if ( '' === $before_text && '' === $after_text ) {
			return '<em class="cc-diff-empty">(no text)</em>';
		}
		if ( $before_text === $after_text ) {
			return '<em class="cc-diff-empty">(no visible text change)</em>';
		}

		// Pure addition.
		if ( '' === $before_text ) {
			return sprintf(
				'<div class="cc-text-diff"><div class="cc-text-before"><div class="cc-diff-label-mini">%s</div><div class="cc-text-content"><em class="cc-diff-empty">(empty)</em></div></div><div class="cc-text-after"><div class="cc-diff-label-mini">%s</div><div class="cc-text-content"><span class="cc-diff-add">%s</span></div></div></div>',
				esc_html__( 'Before', 'cc-assistant' ),
				esc_html__( 'After', 'cc-assistant' ),
				esc_html( $after_text )
			);
		}
		// Pure deletion.
		if ( '' === $after_text ) {
			return sprintf(
				'<div class="cc-text-diff"><div class="cc-text-before"><div class="cc-diff-label-mini">%s</div><div class="cc-text-content"><span class="cc-diff-del">%s</span></div></div><div class="cc-text-after"><div class="cc-diff-label-mini">%s</div><div class="cc-text-content"><em class="cc-diff-empty">(empty)</em></div></div></div>',
				esc_html__( 'Before', 'cc-assistant' ),
				esc_html( $before_text ),
				esc_html__( 'After', 'cc-assistant' )
			);
		}

		$d = self::compute( $before_text, $after_text );
		list( $prefix_disp, $suffix_disp ) = self::trim_context( $d['prefix'], $d['suffix'], 100 );

		$before_html = esc_html( $prefix_disp )
			. ( '' !== $d['before_mid'] ? '<span class="cc-diff-del">' . esc_html( $d['before_mid'] ) . '</span>' : '' )
			. esc_html( $suffix_disp );

		$after_html = esc_html( $prefix_disp )
			. ( '' !== $d['after_mid'] ? '<span class="cc-diff-add">' . esc_html( $d['after_mid'] ) . '</span>' : '' )
			. esc_html( $suffix_disp );

		return sprintf(
			'<div class="cc-text-diff"><div class="cc-text-before"><div class="cc-diff-label-mini">%s</div><div class="cc-text-content">%s</div></div><div class="cc-text-after"><div class="cc-diff-label-mini">%s</div><div class="cc-text-content">%s</div></div></div>',
			esc_html__( 'Before', 'cc-assistant' ),
			$before_html,
			esc_html__( 'After', 'cc-assistant' ),
			$after_html
		);
	}

	/** "Display Name (#ID)" for a stored post_author user ID. */
	private static function author_label( $user_id ) {
		$user = $user_id ? get_user_by( 'id', (int) $user_id ) : null;
		return $user
			? sprintf( '%s (#%d)', $user->display_name, $user->ID )
			: sprintf( '(user #%s not found)', $user_id );
	}

	/**
	 * Render the full human-readable diff for a pending change row.
	 * $item must be a row from cc_pending_changes (object).
	 */
	public static function render_human_diff( $item ) {
		$current  = json_decode( $item->current_value, true );
		$proposed = json_decode( $item->proposed_value, true );

		$out = '';
		switch ( $item->change_type ) {
			case 'meta_update':
				$field = isset( $current['field'] ) ? $current['field'] : '';
				$cur_v = $current['value'] ?? '';
				$new_v = $proposed['value'] ?? '';
				if ( 'post_author' === $field ) {
					// Values are numeric user IDs; the reviewer should see WHO,
					// not a bare ID.
					$cur_v = self::author_label( $cur_v );
					$new_v = self::author_label( $new_v );
				}
				$out .= sprintf(
					'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <code>%s</code></div>%s</div>',
					esc_html__( 'Field:', 'cc-assistant' ),
					esc_html( $field ),
					self::smart_text_diff( $cur_v, $new_v )
				);
				break;

			case 'postmeta_update':
				$key = isset( $current['key'] ) ? $current['key'] : '';
				$out .= sprintf(
					'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <code>%s</code></div>%s</div>',
					esc_html__( 'Meta key:', 'cc-assistant' ),
					esc_html( $key ),
					self::smart_text_diff( $current['value'] ?? '', $proposed['value'] ?? '' )
				);
				break;

			case 'rank_math_schema_update':
				$out .= sprintf(
					'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <strong>%s</strong> <code>%s</code></div></div>',
					esc_html__( 'Rank Math schema:', 'cc-assistant' ),
					esc_html( $proposed['type'] ?? $current['type'] ?? '' ),
					esc_html( $proposed['meta_key'] ?? $current['meta_key'] ?? '' )
				);
				foreach ( (array) ( $proposed['paths'] ?? array() ) as $path => $new ) {
					$old  = $current['paths'][ $path ] ?? '';
					$out .= sprintf(
						'<div class="cc-diff-section"><div class="cc-diff-field-label"><code>%s</code></div>%s</div>',
						esc_html( (string) $path ),
						self::smart_text_diff(
							'' === (string) $old ? '(not set)' : (string) $old,
							(string) $new
						)
					);
				}
				break;

			case 'term_update':
				$term_name = $proposed['term_name'] ?? $current['term_name'] ?? '';
				$taxonomy  = $proposed['taxonomy'] ?? $current['taxonomy'] ?? '';
				$term_id   = (int) ( $proposed['term_id'] ?? $current['term_id'] ?? 0 );
				$out      .= sprintf(
					'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <strong>%s</strong> <code>%s #%d</code></div></div>',
					esc_html__( 'Category:', 'cc-assistant' ),
					esc_html( $term_name ),
					esc_html( $taxonomy ),
					$term_id
				);
				$term_fields = array(
					'description'     => __( 'Archive description', 'cc-assistant' ),
					'seo_title'       => __( 'SEO title (term meta)', 'cc-assistant' ),
					'seo_description' => __( 'SEO description (term meta)', 'cc-assistant' ),
				);
				foreach ( $term_fields as $tf_key => $tf_label ) {
					if ( ! array_key_exists( $tf_key, (array) $proposed ) ) {
						continue;
					}
					$out .= sprintf(
						'<div class="cc-diff-section"><div class="cc-diff-field-label">%s</div>%s</div>',
						esc_html( $tf_label ),
						self::smart_text_diff( $current[ $tf_key ] ?? '', $proposed[ $tf_key ] ?? '' )
					);
				}
				break;

			case 'elementor_widget_update':
				$current_settings  = isset( $current['settings'] ) && is_array( $current['settings'] ) ? $current['settings'] : array();
				$proposed_settings = isset( $proposed['settings'] ) && is_array( $proposed['settings'] ) ? $proposed['settings'] : array();
				$widget_id         = $current['widget_id'] ?? $proposed['widget_id'] ?? '';

				$any_diff = false;
				foreach ( $proposed_settings as $key => $new_val ) {
					$old_val = $current_settings[ $key ] ?? null;
					if ( $old_val === $new_val ) {
						continue;
					}
					$any_diff = true;

					if ( is_string( $old_val ) || is_string( $new_val ) ) {
						$out .= sprintf(
							'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <code>%s</code> %s <code>%s</code></div>%s</div>',
							esc_html__( 'Widget', 'cc-assistant' ),
							esc_html( $widget_id ),
							esc_html__( 'field', 'cc-assistant' ),
							esc_html( $key ),
							self::smart_text_diff( (string) $old_val, (string) $new_val )
						);
					} else {
						$out .= sprintf(
							'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <code>%s</code> %s <code>%s</code> (%s)</div><pre class="cc-mini-pre">%s</pre><div class="cc-diff-arrow">&darr;</div><pre class="cc-mini-pre">%s</pre></div>',
							esc_html__( 'Widget', 'cc-assistant' ),
							esc_html( $widget_id ),
							esc_html__( 'field', 'cc-assistant' ),
							esc_html( $key ),
							esc_html__( 'structured', 'cc-assistant' ),
							esc_html( wp_json_encode( $old_val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ),
							esc_html( wp_json_encode( $new_val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) )
						);
					}
				}
				if ( ! $any_diff ) {
					$out .= '<em class="cc-diff-empty">(no field changes detected in widget settings)</em>';
				}
				break;

			case 'cluster_create':
				$out .= self::render_cluster_create( $proposed );
				break;

			case 'cluster_assign':
				$out .= self::render_cluster_assign( $proposed );
				break;

			case 'rewrite_outline':
				$out .= self::render_rewrite_outline( $proposed );
				break;

			case 'create_redirect':
			case 'delete_redirect':
			case 'untrash_redirect':
				$out .= self::render_redirect_diff( $item->change_type, $proposed );
				break;

			case 'asset_reference_replace':
				$out .= self::render_asset_reference_diff( $proposed );
				break;

			case 'bulk_term_assign':
				$out .= self::render_bulk_term_diff( $proposed );
				break;

			case 'plugin_setting_update':
				$out .= self::render_plugin_setting_diff( $proposed );
				break;

			case 'kit_setting_update':
				$out .= self::render_plugin_setting_diff( self::kit_plan_as_setting( $proposed ) );
				break;

			default:
				// Generic readable fallback: render the proposed payload as a
				// key/value list so EVERY change type shows something useful in
				// the approval inbox. Before v0.37 ~8 change types (incl. the
				// redirect tools, widget add/remove, section replace, full body
				// rewrite) hit a dead-end "(no preview available)".
				$out .= self::render_generic_fields( $proposed );
		}

		return $out;
	}

	/**
	 * Diff card for a site-wide media URL swap.
	 *
	 * Unlike every other change type this one is not post-scoped, so the
	 * reviewer's question is "what does this touch?" rather than "what does
	 * this say?". Lead with the two URLs, then list every row that will be
	 * rewritten, then — separately and plainly — the rows that were found but
	 * refused, because a silent skip on a serialized settings blob is exactly
	 * the thing that would otherwise be discovered weeks later.
	 *
	 * Public because admin/views/pending.php carries its own copy of the diff
	 * switch and calls this directly rather than duplicating the markup.
	 */
	/**
	 * v0.69 — preview for plugin_setting_update.
	 *
	 * The reviewer is approving a change inside ANOTHER plugin's configuration,
	 * so the two things that matter are exactly which key is being touched and
	 * what it holds today. Shows the option name, the dot path, and a literal
	 * before/after — with (empty) spelled out, because a blank-to-value change
	 * is the most common shape here and an invisible "" reads as a no-op.
	 */
	/**
	 * v0.76 — a kit plan is a plugin-setting plan with the option name
	 * implied (the active kit's _elementor_page_settings); reuse the card.
	 */
	public static function kit_plan_as_setting( $proposed ) {
		$p = is_array( $proposed ) ? $proposed : array();
		$p['option_name'] = 'Elementor Site Settings (kit #' . ( isset( $p['kit_id'] ) ? (int) $p['kit_id'] : 0 ) . ')';
		return $p;
	}

	public static function render_plugin_setting_diff( $proposed ) {
		$p = is_array( $proposed ) ? $proposed : array();
		if ( empty( $p['option_name'] ) ) {
			return '<em class="cc-diff-empty">(setting plan is empty)</em>';
		}
		$disp = function ( $v ) {
			if ( is_bool( $v ) ) { return $v ? 'true' : 'false'; }
			$v = (string) $v;
			return '' === $v ? '(empty)' : $v;
		};
		$out  = '<div class="cc-diff-block">';
		$out .= '<p><code>' . esc_html( $p['option_name'] ) . '</code>';
		if ( ! empty( $p['path'] ) ) {
			$out .= ' &rarr; <code>' . esc_html( $p['path'] ) . '</code>';
		}
		$out .= '</p>';
		$out .= '<table class="cc-diff-table"><tr><th>Now</th><th>After</th></tr><tr>'
			. '<td><del>' . esc_html( $disp( isset( $p['prior_value'] ) ? $p['prior_value'] : '' ) ) . '</del></td>'
			. '<td><ins>' . esc_html( $disp( isset( $p['value'] ) ? $p['value'] : '' ) ) . '</ins></td>'
			. '</tr></table>';
		if ( ! empty( $p['created_path'] ) ) {
			$out .= '<p style="color:#8a5a06;">This key does not exist yet and will be created. If the plugin does not read it, the setting will appear changed while doing nothing.</p>';
		}
		$out .= '<p style="color:#6b7280;font-size:12px;">Rolling back restores the entire option to its current value, not just this key.</p>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * v0.69 — preview for bulk_term_assign.
	 *
	 * Also not post-scoped. The reviewer's real question is "is this the right
	 * SET of products?", so lead with the match rule and the three counts that
	 * make coverage checkable at a glance (matched / already had it / will
	 * change), then a sample of actual product titles. A reviewer who sees
	 * "matched 105, changing 74" can tell instantly whether the rule caught
	 * the intended catalogue slice.
	 *
	 * Public for the same reason as the asset diff above.
	 */
	public static function render_bulk_term_diff( $proposed ) {
		$p = is_array( $proposed ) ? $proposed : array();
		if ( empty( $p['term_name'] ) ) {
			return '<em class="cc-diff-empty">(bulk term plan is empty)</em>';
		}
		$total   = isset( $p['total_matched'] ) ? (int) $p['total_matched'] : 0;
		$had     = isset( $p['already_had'] ) ? (int) $p['already_had'] : 0;
		$change  = isset( $p['to_change'] ) ? (int) $p['to_change'] : 0;
		$mode    = ( isset( $p['mode'] ) && 'replace' === $p['mode'] ) ? 'replace existing terms' : 'add (keeps existing terms)';
		$rule    = isset( $p['match']['label'] ) ? $p['match']['label'] : '';

		$out  = '<div class="cc-diff-block">';
		$out .= '<p><strong>' . esc_html( $p['term_name'] ) . '</strong> <span style="color:#6b7280;">(' . esc_html( $p['taxonomy'] ) . ')</span> &rarr; '
			. esc_html( isset( $p['post_type'] ) ? $p['post_type'] : 'post' ) . 's matching <code>' . esc_html( $rule ) . '</code></p>';

		$out .= '<p style="margin:8px 0;">'
			. '<span style="display:inline-block;margin-right:16px;"><strong>' . (int) $total . '</strong> matched</span>'
			. '<span style="display:inline-block;margin-right:16px;color:#6b7280;"><strong>' . (int) $had . '</strong> already tagged</span>'
			. '<span style="display:inline-block;color:#1e8a4c;"><strong>' . (int) $change . '</strong> will change</span>'
			. '</p>';
		$out .= '<p style="color:#6b7280;font-size:12px;">Mode: ' . esc_html( $mode ) . '. Rolling back restores each item&rsquo;s previous terms exactly.</p>';

		if ( ! empty( $p['truncated'] ) ) {
			$out .= '<p style="color:#8a5a06;">' . esc_html( $p['truncated'] ) . '</p>';
		}

		$targets = isset( $p['targets'] ) && is_array( $p['targets'] ) ? $p['targets'] : array();
		if ( $targets ) {
			$out .= '<ul style="margin:8px 0 0 18px;">';
			foreach ( array_slice( $targets, 0, 12 ) as $t ) {
				$out .= '<li>' . esc_html( isset( $t['title'] ) ? $t['title'] : ( '#' . $t['id'] ) ) . '</li>';
			}
			$out .= '</ul>';
			if ( count( $targets ) > 12 ) {
				$out .= '<p style="color:#6b7280;">&hellip; and ' . ( count( $targets ) - 12 ) . ' more</p>';
			}
		}
		$out .= '</div>';
		return $out;
	}

	public static function render_asset_reference_diff( $proposed ) {
		$proposed = is_array( $proposed ) ? $proposed : array();
		$old      = isset( $proposed['old_url'] ) ? (string) $proposed['old_url'] : '';
		$new      = isset( $proposed['new_url'] ) ? (string) $proposed['new_url'] : '';
		$targets  = isset( $proposed['targets'] ) && is_array( $proposed['targets'] ) ? $proposed['targets'] : array();
		$skipped  = isset( $proposed['skipped'] ) && is_array( $proposed['skipped'] ) ? $proposed['skipped'] : array();

		$out = sprintf(
			'<div class="cc-diff-section"><div class="cc-diff-field-label">%s</div>%s</div>',
			esc_html__( 'Media URL replacement', 'cc-assistant' ),
			self::smart_text_diff( $old, $new )
		);

		if ( ! empty( $proposed['truncated'] ) ) {
			$out .= sprintf(
				'<p class="cc-diff-note"><strong>%s</strong></p>',
				esc_html__( 'This does not cover every location. More rows reference the old URL than one change can carry — after applying this, the swap needs to be run again until nothing references it.', 'cc-assistant' )
			);
		}

		if ( $targets ) {
			$rows = '';
			foreach ( $targets as $t ) {
				$count = isset( $t['replacements'] ) ? (int) $t['replacements'] : 0;
				$rows .= sprintf(
					'<li><code>%s</code> — %s%s%s</li>',
					esc_html( isset( $t['kind'] ) ? $t['kind'] : '?' ),
					esc_html( isset( $t['label'] ) ? $t['label'] : '' ),
					sprintf(
						' <em>(%s)</em>',
						esc_html( sprintf( _n( '%d occurrence', '%d occurrences', $count, 'cc-assistant' ), $count ) )
					),
					empty( $t['serialized'] ) ? '' : ' <strong>' . esc_html__( '[serialized]', 'cc-assistant' ) . '</strong>'
				);
			}
			$out .= sprintf(
				'<div class="cc-diff-section"><div class="cc-diff-field-label">%s</div><ul class="cc-diff-list">%s</ul></div>',
				esc_html( sprintf( _n( 'Will be rewritten in %d location', 'Will be rewritten in %d locations', count( $targets ), 'cc-assistant' ), count( $targets ) ) ),
				$rows
			);
		}

		if ( $skipped ) {
			$rows = '';
			foreach ( $skipped as $s ) {
				$rows .= sprintf(
					'<li><code>%s</code> — %s: %s</li>',
					esc_html( isset( $s['kind'] ) ? $s['kind'] : '?' ),
					esc_html( isset( $s['label'] ) ? $s['label'] : '' ),
					esc_html( isset( $s['reason'] ) ? $s['reason'] : '' )
				);
			}
			$out .= sprintf(
				'<div class="cc-diff-section"><div class="cc-diff-field-label">%s</div><ul class="cc-diff-list">%s</ul></div>',
				esc_html( sprintf( _n( 'Found but NOT changed (%d location)', 'Found but NOT changed (%d locations)', count( $skipped ), 'cc-assistant' ), count( $skipped ) ) ),
				$rows
			);
		}

		$out .= sprintf(
			'<p class="cc-diff-note">%s</p>',
			esc_html__( 'Each row is re-read when you approve and skipped if its content changed in the meantime. The old file is not deleted; to undo, run the swap in reverse.', 'cc-assistant' )
		);

		return $out;
	}

	/**
	 * Diff card for redirect lifecycle change types (create / delete / untrash).
	 * Shows source -> destination + HTTP code; flags delete as destructive
	 * (Rank Math soft-deletes, but the plugin cannot auto-undo a hard delete).
	 */
	private static function render_redirect_diff( $change_type, $proposed ) {
		$proposed = is_array( $proposed ) ? $proposed : array();
		$source   = isset( $proposed['source'] ) ? (string) $proposed['source'] : '';
		$dest     = isset( $proposed['destination'] ) ? (string) $proposed['destination'] : '';
		$code     = isset( $proposed['http_code'] ) ? (int) $proposed['http_code'] : 0;
		$rid      = isset( $proposed['id'] ) ? (int) $proposed['id'] : 0;
		$prev     = isset( $proposed['prev_status'] ) ? (string) $proposed['prev_status'] : '';

		$labels = array(
			'create_redirect'  => __( 'Create redirect', 'cc-assistant' ),
			'delete_redirect'  => __( 'Delete redirect', 'cc-assistant' ),
			'untrash_redirect' => __( 'Restore (untrash) redirect', 'cc-assistant' ),
		);
		$title = isset( $labels[ $change_type ] ) ? $labels[ $change_type ] : $change_type;

		$out  = '<div class="cc-diff-section">';
		$out .= '<div class="cc-diff-field-label">' . esc_html( $title );
		if ( $rid > 0 ) {
			$out .= ' <code>id ' . (int) $rid . '</code>';
		}
		$out .= '</div>';

		if ( '' !== $source || '' !== $dest ) {
			$out .= '<div class="cc-redirect-map"><code>' . esc_html( '' !== $source ? $source : '(unknown source)' ) . '</code> &rarr; <code>' . esc_html( '' !== $dest ? $dest : '(no destination)' ) . '</code>';
			if ( $code > 0 ) {
				$out .= ' <span class="cc-pill">HTTP ' . (int) $code . '</span>';
			}
			$out .= '</div>';
		}
		if ( '' !== $prev ) {
			$out .= '<div class="cc-diff-meta">' . esc_html__( 'Current status:', 'cc-assistant' ) . ' <code>' . esc_html( $prev ) . '</code></div>';
		}
		if ( 'delete_redirect' === $change_type ) {
			$out .= '<div class="cc-diff-warning" style="margin-top:8px;color:#8a1f11;font-weight:600;">&#9888; ' . esc_html__( 'Destructive: permanently deletes this Rank Math redirect. The plugin cannot auto-undo a hard delete — you would have to re-create it manually.', 'cc-assistant' ) . '</div>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Generic readable fallback for change types without a bespoke renderer: a
	 * compact key/value list of the proposed payload so the reviewer always
	 * sees what is being approved instead of a dead-end "(no preview)".
	 */
	private static function render_generic_fields( $proposed ) {
		if ( ! is_array( $proposed ) || empty( $proposed ) ) {
			return '<em class="cc-diff-empty">' . esc_html__( 'No structured preview for this change type — expand the technical details below to review the raw change.', 'cc-assistant' ) . '</em>';
		}
		$out = '<div class="cc-diff-section"><table class="cc-generic-diff" style="width:100%;border-collapse:collapse;">';
		foreach ( $proposed as $k => $v ) {
			if ( is_array( $v ) ) {
				$v = wp_json_encode( $v, JSON_UNESCAPED_SLASHES );
			}
			$v = (string) $v;
			if ( mb_strlen( $v ) > 300 ) {
				$v = mb_substr( $v, 0, 300 ) . '…';
			}
			$out .= '<tr><td style="vertical-align:top;padding:2px 8px 2px 0;font-weight:600;white-space:nowrap;"><code>' . esc_html( (string) $k ) . '</code></td><td style="padding:2px 0;word-break:break-word;">' . esc_html( $v ) . '</td></tr>';
		}
		$out .= '</table></div>';
		return $out;
	}

	private static function render_cluster_create( $proposed ) {
		$name      = isset( $proposed['name'] ) ? (string) $proposed['name'] : '';
		$desc      = isset( $proposed['description'] ) ? (string) $proposed['description'] : '';
		$pillar_id = (int) ( $proposed['pillar_post_id'] ?? 0 );

		$out = '<div class="cc-diff-section cc-cluster-proposal">';
		$out .= sprintf(
			'<div class="cc-cluster-proposal-name"><span class="dashicons dashicons-category"></span> %s <strong>%s</strong></div>',
			esc_html__( 'New cluster:', 'cc-assistant' ),
			esc_html( $name )
		);
		if ( '' !== $desc ) {
			$out .= sprintf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
		if ( $pillar_id > 0 ) {
			$pillar_title = get_the_title( $pillar_id ) ?: ( '#' . $pillar_id );
			$pillar_link  = get_edit_post_link( $pillar_id );
			$pillar_html  = $pillar_link
				? sprintf( '<a href="%s">%s</a>', esc_url( $pillar_link ), esc_html( $pillar_title ) )
				: esc_html( $pillar_title );
			$out .= sprintf(
				'<div class="cc-cluster-proposal-pillar"><span class="dashicons dashicons-flag"></span> %s %s</div>',
				esc_html__( 'Pillar:', 'cc-assistant' ),
				$pillar_html
			);
		} else {
			$out .= sprintf( '<div class="cc-cluster-proposal-pillar cc-pillar-missing"><span class="dashicons dashicons-warning"></span> %s</div>', esc_html__( 'No pillar selected', 'cc-assistant' ) );
		}
		$supporting_ids = array_map( 'intval', (array) ( $proposed['supporting_post_ids'] ?? array() ) );
		if ( $supporting_ids ) {
			_prime_post_caches( $supporting_ids, false, false );
			$out .= '<div class="cc-cluster-proposal-list">';
			$out .= sprintf( '<div class="cc-diff-field-label">%s</div>', esc_html( sprintf( /* translators: %d: count */ _n( '%d supporting page', '%d supporting pages', count( $supporting_ids ), 'cc-assistant' ), count( $supporting_ids ) ) ) );
			$out .= '<ul class="cc-cluster-proposal-pages">';
			foreach ( $supporting_ids as $sp_id ) {
				$t = get_the_title( $sp_id );
				$l = get_edit_post_link( $sp_id );
				$out .= '<li>' . ( $l ? sprintf( '<a href="%s">%s</a>', esc_url( $l ), esc_html( $t ?: '#' . $sp_id ) ) : esc_html( $t ?: '#' . $sp_id ) ) . '</li>';
			}
			$out .= '</ul></div>';
		}
		$out .= '</div>';
		return $out;
	}

	private static function render_cluster_assign( $proposed ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		$cluster_id = (int) ( $proposed['cluster_id'] ?? 0 );
		$post_id    = (int) ( $proposed['post_id'] ?? 0 );
		$role       = (string) ( $proposed['role'] ?? 'supporting' );
		$cluster    = $cluster_id > 0 ? CC_Assistant_Topic_Clusters::get_cluster( $cluster_id ) : null;
		$post_title = $post_id > 0 ? get_the_title( $post_id ) : '';
		$post_link  = $post_id > 0 ? get_edit_post_link( $post_id ) : '';

		$out  = '<div class="cc-diff-section cc-cluster-proposal">';
		$out .= sprintf(
			'<div class="cc-cluster-proposal-name"><span class="dashicons dashicons-plus-alt2"></span> %s <strong>%s</strong></div>',
			esc_html__( 'Assign to cluster:', 'cc-assistant' ),
			esc_html( $cluster ? $cluster->name : ( '#' . $cluster_id ) )
		);
		$out .= sprintf(
			'<div class="cc-cluster-proposal-pillar">%s %s &middot; %s <em>%s</em></div>',
			esc_html__( 'Page:', 'cc-assistant' ),
			$post_link
				? sprintf( '<a href="%s">%s</a>', esc_url( $post_link ), esc_html( $post_title ?: '#' . $post_id ) )
				: esc_html( $post_title ?: '#' . $post_id ),
			esc_html__( 'Role:', 'cc-assistant' ),
			esc_html( $role )
		);
		$out .= '</div>';
		return $out;
	}

	/**
	 * Render a rewrite_outline proposal as a clean editorial plan: each H2
	 * with its action chip (keep / cut / merge / add / rewrite) plus the
	 * model's notes. Reviewer signs off on this BEFORE the model writes
	 * any HTML, which is the whole point of the outline-first workflow.
	 */
	private static function render_rewrite_outline( $proposed ) {
		$rows      = isset( $proposed['outline'] ) && is_array( $proposed['outline'] ) ? $proposed['outline'] : array();
		$rationale = isset( $proposed['rationale'] ) ? (string) $proposed['rationale'] : '';

		$out  = '<div class="cc-diff-section cc-outline-proposal">';
		$out .= '<div class="cc-outline-header"><span class="dashicons dashicons-list-view"></span> ' . esc_html__( 'Editorial outline (approve before body rewrite)', 'cc-assistant' ) . '</div>';
		if ( '' !== $rationale ) {
			$out .= sprintf( '<div class="cc-outline-rationale">%s</div>', esc_html( $rationale ) );
		}
		if ( empty( $rows ) ) {
			$out .= '<em class="cc-diff-empty">(empty outline)</em>';
			$out .= '</div>';
			return $out;
		}
		$out .= '<ol class="cc-outline-list">';
		foreach ( $rows as $row ) {
			$action = isset( $row['action'] ) ? (string) $row['action'] : 'keep';
			$h2     = isset( $row['h2'] ) ? (string) $row['h2'] : '';
			$notes  = isset( $row['notes'] ) ? (string) $row['notes'] : '';
			$out   .= sprintf(
				'<li class="cc-outline-row cc-outline-action-%s"><span class="cc-outline-chip">%s</span><span class="cc-outline-h2">%s</span>%s</li>',
				esc_attr( $action ),
				esc_html( $action ),
				esc_html( $h2 ),
				'' !== $notes ? '<div class="cc-outline-notes">' . esc_html( $notes ) . '</div>' : ''
			);
		}
		$out .= '</ol>';
		$out .= '<div class="cc-outline-next-step description">' . esc_html__( 'Approving this outline unblocks draft_update_post_content for this post for the next 7 days. Reject if the structure is wrong.', 'cc-assistant' ) . '</div>';
		$out .= '</div>';
		return $out;
	}
}

// Backwards-compat shims so any code still calling the old function names
// keeps working. They proxy into the class.
if ( ! function_exists( 'cc_diff_compute' ) ) {
	function cc_diff_compute( $before, $after ) {
		return CC_Assistant_Diff_Render::compute( $before, $after );
	}
}
if ( ! function_exists( 'cc_diff_trim_context' ) ) {
	function cc_diff_trim_context( $prefix, $suffix, $context = 100 ) {
		return CC_Assistant_Diff_Render::trim_context( $prefix, $suffix, $context );
	}
}
if ( ! function_exists( 'cc_smart_text_diff' ) ) {
	function cc_smart_text_diff( $before, $after ) {
		return CC_Assistant_Diff_Render::smart_text_diff( $before, $after );
	}
}
if ( ! function_exists( 'cc_render_human_diff' ) ) {
	function cc_render_human_diff( $item ) {
		return CC_Assistant_Diff_Render::render_human_diff( $item );
	}
}

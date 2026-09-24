<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Diff helpers live in their own class now. This require_once defines the
// function shims (cc_diff_compute, cc_render_human_diff, etc.) which means
// the inline if-not-function-exists definitions below are skipped and the
// shared class becomes the single source of truth.
require_once CC_ASSISTANT_DIR . 'includes/class-diff-render.php';

$action_message = '';
$action_error   = '';

if ( isset( $_POST['cc_pending_action'], $_POST['cc_pending_nonce'] )
	&& wp_verify_nonce( $_POST['cc_pending_nonce'], 'cc_pending_review' ) ) {

	$action = sanitize_key( $_POST['cc_pending_action'] );
	$user   = get_current_user_id();
	$note   = isset( $_POST['cc_pending_note'] ) ? sanitize_text_field( $_POST['cc_pending_note'] ) : '';

	// Bulk actions: cc_pending_ids[] from checkboxes. Apply runs each change in
	// bulk_mode (skips per-apply cache invalidation), then runs the cleanup
	// helpers once at end. Cuts ~10 DB hits per applied change (transient
	// deletes + a SELECT-and-loop over trends keys) — the freeze cause when
	// 8+ changes are bulk-approved.
	if ( in_array( $action, array( 'bulk_approve', 'bulk_reject' ), true ) && ! empty( $_POST['cc_pending_ids'] ) ) {
		$ids = array_values( array_unique( array_map( 'intval', (array) $_POST['cc_pending_ids'] ) ) );
		// Sort ASC so containers/widgets get appended to the Elementor tree in
		// queue order (oldest first). The inbox renders newest-first, so without
		// this sort the form would POST [589, 588, ..., 581] and bulk_approve
		// would apply Schema before Hero — leaving the page rendered backwards.
		// IDs are auto-increment so ASC by id is equivalent to ASC by created_at.
		// Only matters for bulk_approve; bulk_reject is order-insensitive but
		// sorting is cheap so we do both.
		sort( $ids, SORT_NUMERIC );
		$ok  = 0;
		$fail = 0;
		$errs = array();
		$had_text_change = false;
		$had_edit_record = false;
		$had_elementor   = false;
		$batch_snapshots = array();

		// Pre-batch snapshot. Per-change snapshots run inside apply_pending
		// already, so reverting one change is fine — but reverting "the whole
		// batch I just approved" today requires 7 separate restore clicks.
		// For any post that has >1 change in this batch, capture a single
		// pre_batch snapshot before the loop so rollback is one click per
		// post. Bulk_approve only.
		if ( 'bulk_approve' === $action ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
			$ids_by_post = array();
			foreach ( $ids as $bid ) {
				$p = CC_Assistant_Pending_Changes::get( $bid );
				if ( $p && (int) $p->post_id > 0 ) {
					$ids_by_post[ (int) $p->post_id ][] = (int) $bid;
				}
			}
			foreach ( $ids_by_post as $pid => $pending_ids ) {
				if ( count( $pending_ids ) < 2 ) {
					continue;
				}
				$snap_id = CC_Assistant_Snapshots::snapshot_post(
					$pid,
					'pre_batch',
					sprintf( 'Pre-batch snapshot before bulk-approve of %d changes (#%s).', count( $pending_ids ), implode( ',', $pending_ids ) )
				);
				if ( ! is_wp_error( $snap_id ) && $snap_id ) {
					$batch_snapshots[ $pid ] = (int) $snap_id;
				}
			}
		}
		// Apply in dependency-safe order so a single "Approve all" can't fail on
		// ordering (the source of most apply errors): retire/trash first (frees a
		// URL the new page will claim), then content + meta edits, then the
		// custom-permalink claim, then publish_draft LAST — so the publish gate
		// sees the finished page and a freshly published page never collides with
		// the old page's permalink. Ties keep queue order (id ascending).
		if ( 'bulk_approve' === $action && count( $ids ) > 1 ) {
			$cc_apply_rank = function ( $bid ) {
				$p = CC_Assistant_Pending_Changes::get( $bid );
				if ( ! $p ) {
					return 2;
				}
				if ( 'trash_post' === $p->change_type ) {
					return 0;
				}
				if ( 'publish_draft' === $p->change_type ) {
					return 4;
				}
				if ( 'postmeta_update' === $p->change_type && false !== strpos( (string) $p->proposed_value, 'custom_permalink' ) ) {
					return 3;
				}
				return 2;
			};
			usort(
				$ids,
				function ( $a, $b ) use ( $cc_apply_rank ) {
					$ra = $cc_apply_rank( $a );
					$rb = $cc_apply_rank( $b );
					if ( $ra === $rb ) {
						return ( (int) $a ) - ( (int) $b );
					}
					return $ra - $rb;
				}
			);
		}
		$batch_results = 'bulk_approve' === $action ? CC_Assistant_Apply::apply_pending_batch( $ids, $user ) : array();
		foreach ( $ids as $bid ) {
			if ( 'bulk_approve' === $action ) {
				$r = $batch_results[$bid];
				if ( is_wp_error( $r ) ) {
					$fail++;
					$errs[] = sprintf( '#%d: %s', $bid, $r->get_error_message() );
				} else {
					$ok++;
					$ct = isset( $r['change_type'] ) ? $r['change_type'] : '';
					if ( in_array( $ct, array( 'post_content_update', 'elementor_widget_update', 'meta_update' ), true ) ) {
						$had_text_change = true;
					}
					if ( ! empty( $r['edit_id'] ) ) {
						$had_edit_record = true;
					}
					if ( 'elementor_widget_update' === $ct ) {
						$had_elementor = true;
					}
				}
			} else {
				if ( CC_Assistant_Pending_Changes::reject( $bid, $user, '' ) ) { $ok++; }
				else { $fail++; $errs[] = sprintf( '#%d: the change is no longer pending.', $bid ); }
			}
		}
		// Single cleanup pass at end of the bulk apply. Skipped for bulk_reject
		// (rejects don't write to posts, so no caches need busting beyond
		// the inbox count which the next page render will recompute).
		if ( 'bulk_approve' === $action && $ok > 0 ) {
			CC_Assistant_Apply::run_post_apply_cleanup( $had_text_change, $had_edit_record );
			if ( $had_elementor ) {
				CC_Assistant_Apply::flush_elementor_css_cache();
			}
		}
		$verb = 'bulk_approve' === $action ? __( 'applied', 'cc-assistant' ) : __( 'rejected', 'cc-assistant' );
		if ( $fail > 0 ) {
			$action_error = sprintf(
				/* translators: 1: ok count, 2: verb, 3: fail count, 4: errors */
				__( '%1$d %2$s, %3$d failed. Details: %4$s', 'cc-assistant' ),
				$ok, $verb, $fail, implode( '; ', $errs )
			);
			if ( 'bulk_approve' === $action && $ok > 0 ) {
				$action_error .= ' ' . __( 'The successfully applied changes remain saved. Have Claude inspect verify_change evidence diagnostics for the failed IDs. Rebuild a proposal only after identifying a real conflict or missing recovery evidence.', 'cc-assistant' );
			}
		} else {
			$action_message = sprintf(
				/* translators: 1: ok count, 2: verb */
				__( '%1$d changes %2$s.', 'cc-assistant' ),
				$ok, $verb
			);
			if ( ! empty( $batch_snapshots ) ) {
				$action_message .= ' ' . sprintf(
					/* translators: %d: snapshot count */
					_n( '%d pre-batch snapshot saved for one-click rollback.', '%d pre-batch snapshots saved for one-click rollback.', count( $batch_snapshots ), 'cc-assistant' ),
					count( $batch_snapshots )
				);
			}
		}
	} elseif ( ! empty( $_POST['cc_pending_id'] ) ) {
		// Single-item actions.
		$id = (int) $_POST['cc_pending_id'];
		if ( 'approve' === $action ) {
			$result = CC_Assistant_Apply::apply_pending( $id, $user );
			if ( is_wp_error( $result ) ) {
				$action_error = sprintf( 'Apply failed: %s', $result->get_error_message() );
			} else {
				$pending      = CC_Assistant_Pending_Changes::get( $id );
				$post_link    = $pending && $pending->post_id ? sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $pending->post_id ) ), esc_html( get_the_title( $pending->post_id ) ) ) : '';
				$action_message = sprintf(
					'Applied to %s. Snapshot #%s saved for rollback.',
					$post_link ?: 'site',
					$result['snapshot_id'] ?? 'n/a'
				);
			}
		} elseif ( 'reject' === $action ) {
			if ( CC_Assistant_Pending_Changes::reject( $id, $user, $note ) ) { $action_message = 'Change rejected.'; }
			else { $action_error = 'The change is no longer pending. Refresh the inbox.'; }
		} elseif ( 'rollback' === $action ) {
			$result = CC_Assistant_Apply::rollback_pending( $id, $user );
			if ( is_wp_error( $result ) ) {
				$action_error = sprintf( 'Rollback failed: %s', $result->get_error_message() );
			} else {
				$action_message = sprintf( 'Rolled back. Pre-rollback snapshot #%s saved.', $result['snapshot_id'] ?? 'n/a' );
			}
		}
	}
}

$initial_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'pending';
$valid_tabs  = array( 'pending', 'approved', 'rejected', 'rolled_back', 'applying', 'apply_failed', 'rolling_back', 'rollback_failed' );
if ( ! in_array( $initial_tab, $valid_tabs, true ) ) {
	$initial_tab = 'pending';
}

$counts    = CC_Assistant_Pending_Changes::counts_by_status();
$bundle    = CC_Assistant_Pending_Changes::fetch_all_for_inbox( 100 );
$by_status = $bundle['by_status'];
$reviewers = $bundle['reviewers'];

if ( ! function_exists( 'cc_diff_compute' ) ) {
	/**
	 * Word-aware prefix/suffix diff. Returns the longest common prefix and suffix
	 * (rounded to whitespace boundaries) plus the divergent middles for each side.
	 */
	function cc_diff_compute( $before, $after ) {
		$b_len = strlen( $before );
		$a_len = strlen( $after );

		// Find longest common prefix at byte level.
		$min_len    = min( $b_len, $a_len );
		$prefix_len = 0;
		while ( $prefix_len < $min_len && $before[ $prefix_len ] === $after[ $prefix_len ] ) {
			$prefix_len++;
		}

		// Find longest common suffix (constrained to not overlap prefix).
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

		$prefix      = substr( $before, 0, $prefix_len );
		$before_mid  = substr( $before, $prefix_len, $b_len - $prefix_len - $suffix_len );
		$after_mid   = substr( $after, $prefix_len, $a_len - $prefix_len - $suffix_len );
		$suffix      = $suffix_len > 0 ? substr( $before, -$suffix_len ) : '';

		return array(
			'prefix'     => $prefix,
			'before_mid' => $before_mid,
			'after_mid'  => $after_mid,
			'suffix'     => $suffix,
		);
	}
}

if ( ! function_exists( 'cc_diff_trim_context' ) ) {
	/**
	 * Trim a long unchanged prefix/suffix down to a few chars of context with ellipsis.
	 */
	function cc_diff_trim_context( $prefix, $suffix, $context = 100 ) {
		$prefix_trim = $prefix;
		$suffix_trim = $suffix;
		if ( strlen( $prefix ) > $context ) {
			// Try to start at a whitespace inside the kept tail.
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
}

if ( ! function_exists( 'cc_smart_text_diff' ) ) {
	function cc_smart_text_diff( $before, $after ) {
		$before_text = trim( wp_strip_all_tags( (string) $before ) );
		$after_text  = trim( wp_strip_all_tags( (string) $after ) );
		// Collapse runs of whitespace so the diff is not noisy.
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

		$d = cc_diff_compute( $before_text, $after_text );
		list( $prefix_disp, $suffix_disp ) = cc_diff_trim_context( $d['prefix'], $d['suffix'], 100 );

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
}

if ( ! function_exists( 'cc_render_structure_diff_card' ) ) {
	/**
	 * Side-by-side structure diff for post_content_update changes. Reads the
	 * current and proposed HTML bodies off the pending row and counts the
	 * coarse structural signals (word count, h2 / h3 totals, list count,
	 * table count, link count) for each, then renders a compact comparison
	 * the reviewer can read at a glance before scrolling into the full diff.
	 *
	 * Cheap regex-based; parses ~20 KB of HTML in well under 10 ms. No DB
	 * queries beyond the json_decode of values already on the row.
	 */
	function cc_render_structure_diff_card( $item ) {
		if ( 'post_content_update' !== $item->change_type ) {
			return;
		}
		$current  = json_decode( $item->current_value, true );
		$proposed = json_decode( $item->proposed_value, true );
		if ( ! is_array( $current ) || ! is_array( $proposed ) ) {
			return;
		}
		$current_html  = isset( $current['content'] ) ? (string) $current['content'] : '';
		$proposed_html = isset( $proposed['content'] ) ? (string) $proposed['content'] : '';
		if ( '' === $current_html && '' === $proposed_html ) {
			return;
		}
		$before = cc_quick_structure_signals( $current_html );
		$after  = cc_quick_structure_signals( $proposed_html );

		// Build deltas — positive numbers in green, negative in amber-ish.
		$delta_field = function ( $key ) use ( $before, $after ) {
			$d = (int) $after[ $key ] - (int) $before[ $key ];
			if ( 0 === $d ) {
				return '<span class="cc-sd-delta cc-sd-flat">±0</span>';
			}
			return sprintf(
				'<span class="cc-sd-delta %s">%+d</span>',
				$d > 0 ? 'cc-sd-up' : 'cc-sd-down',
				$d
			);
		};

		echo '<details class="cc-structure-diff" open>';
		echo '<summary class="cc-structure-summary"><span class="cc-structure-badge">' . esc_html__( 'Structure diff', 'cc-assistant' ) . '</span></summary>';
		echo '<table class="cc-structure-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Signal', 'cc-assistant' ) . '</th>';
		echo '<th>' . esc_html__( 'Before', 'cc-assistant' ) . '</th>';
		echo '<th>' . esc_html__( 'After', 'cc-assistant' ) . '</th>';
		echo '<th>' . esc_html__( 'Delta', 'cc-assistant' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		$rows = array(
			'word_count'  => __( 'Word count', 'cc-assistant' ),
			'h2_total'    => __( 'H2 headings', 'cc-assistant' ),
			'h3_total'    => __( 'H3 headings', 'cc-assistant' ),
			'list_count'  => __( 'Lists', 'cc-assistant' ),
			'table_count' => __( 'Tables', 'cc-assistant' ),
			'link_count'  => __( 'Inline links', 'cc-assistant' ),
		);
		foreach ( $rows as $key => $label ) {
			echo '<tr>';
			echo '<td class="cc-sd-label">' . esc_html( $label ) . '</td>';
			echo '<td class="cc-sd-num">' . esc_html( (string) $before[ $key ] ) . '</td>';
			echo '<td class="cc-sd-num">' . esc_html( (string) $after[ $key ] ) . '</td>';
			echo '<td class="cc-sd-num">' . $delta_field( $key ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		// Headings in/out — the model often merges/cuts/adds H2s; show the diff
		// list explicitly so the reviewer doesn't have to scroll.
		$h2_added   = array_values( array_diff( $after['h2_titles'], $before['h2_titles'] ) );
		$h2_removed = array_values( array_diff( $before['h2_titles'], $after['h2_titles'] ) );
		if ( ! empty( $h2_added ) || ! empty( $h2_removed ) ) {
			echo '<div class="cc-sd-headings">';
			if ( ! empty( $h2_added ) ) {
				echo '<div class="cc-sd-headings-block cc-sd-added"><strong>+ Added H2s</strong><ul>';
				foreach ( $h2_added as $h ) {
					echo '<li>' . esc_html( $h ) . '</li>';
				}
				echo '</ul></div>';
			}
			if ( ! empty( $h2_removed ) ) {
				echo '<div class="cc-sd-headings-block cc-sd-removed"><strong>− Removed H2s</strong><ul>';
				foreach ( $h2_removed as $h ) {
					echo '<li>' . esc_html( $h ) . '</li>';
				}
				echo '</ul></div>';
			}
			echo '</div>';
		}
		echo '</details>';
	}
}

if ( ! function_exists( 'cc_quick_structure_signals' ) ) {
	/**
	 * Coarse structural signals from raw HTML. Mirrors the subset of
	 * analyze_post_structure that's relevant for an inbox at-a-glance view.
	 * Pure regex, no DB.
	 */
	function cc_quick_structure_signals( $html ) {
		$html  = (string) $html;
		$plain = trim( wp_strip_all_tags( $html ) );
		$wc    = $plain ? str_word_count( $plain ) : 0;
		$h2    = preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $h2m );
		$h3    = preg_match_all( '/<h3\b[^>]*>/i', $html );
		$lists = preg_match_all( '/<(ul|ol)\b/i', $html );
		$tables = preg_match_all( '/<table\b/i', $html );
		$links = preg_match_all( '/<a\b[^>]*href=/i', $html );
		$titles = array();
		if ( $h2 && ! empty( $h2m[1] ) ) {
			foreach ( $h2m[1] as $t ) {
				$titles[] = trim( wp_strip_all_tags( $t ) );
			}
		}
		return array(
			'word_count'  => (int) $wc,
			'h2_total'    => (int) $h2,
			'h3_total'    => (int) $h3,
			'list_count'  => (int) $lists,
			'table_count' => (int) $tables,
			'link_count'  => (int) $links,
			'h2_titles'   => $titles,
		);
	}
}

if ( ! function_exists( 'cc_render_lint_card' ) ) {
	/**
	 * Render the lint_report attached to a pending change. Surfaces every
	 * failed check with the offending detail (paragraph snippets, banned
	 * phrases, redundancy bigrams) so the reviewer can see exactly what
	 * the queue endpoint flagged before approving.
	 */
	function cc_render_lint_card( $lint ) {
		$checks      = isset( $lint['checks'] ) && is_array( $lint['checks'] ) ? $lint['checks'] : array();
		$pass_count  = isset( $lint['pass_count'] ) ? (int) $lint['pass_count'] : 0;
		$fail_count  = isset( $lint['fail_count'] ) ? (int) $lint['fail_count'] : 0;
		$total       = isset( $lint['total'] ) ? (int) $lint['total'] : count( $checks );
		$hard        = isset( $lint['hard_violations'] ) && is_array( $lint['hard_violations'] ) ? $lint['hard_violations'] : array();

		// v0.68: attribution split. On sites whose stored bodies always fail
		// the structural checks, "4 failed" carried zero signal; the number
		// the reviewer needs is how many failures THIS change introduces.
		$pre_existing = isset( $lint['pre_existing'] ) && is_array( $lint['pre_existing'] ) ? $lint['pre_existing'] : array();
		$introduced   = isset( $lint['introduced'] ) && is_array( $lint['introduced'] ) ? $lint['introduced'] : array();
		$has_split    = ! empty( $pre_existing ) || ! empty( $introduced );

		$badge_class = 0 === $fail_count ? 'cc-lint-pass' : ( ! empty( $hard ) ? 'cc-lint-hard' : 'cc-lint-warn' );
		if ( 0 === $fail_count ) {
			$badge_label = sprintf( __( 'Lint passed (%d/%d)', 'cc-assistant' ), $pass_count, $total );
		} elseif ( ! empty( $hard ) ) {
			$badge_label = sprintf( __( 'Lint hard-violation (%d failed)', 'cc-assistant' ), $fail_count );
		} elseif ( $has_split && 0 === count( $introduced ) ) {
			$badge_label = sprintf(
				__( 'Lint: introduces 0 new issues (%d pre-existing on this post)', 'cc-assistant' ),
				count( $pre_existing )
			);
			$badge_class = 'cc-lint-pass';
		} elseif ( $has_split ) {
			$badge_label = sprintf(
				__( 'Lint: %1$d introduced, %2$d pre-existing', 'cc-assistant' ),
				count( $introduced ),
				count( $pre_existing )
			);
		} else {
			$badge_label = sprintf( __( 'Lint warnings (%d failed)', 'cc-assistant' ), $fail_count );
		}

		// v0.61: $badge_class already carries the cc-lint- prefix — the old
		// concatenation produced cc-lint-cc-lint-pass (codified into CSS
		// instead of fixed here; that CSS hack is now removed).
		echo '<details class="cc-lint-card ' . esc_attr( $badge_class ) . '"' . ( $fail_count > 0 ? ' open' : '' ) . '>';
		echo '<summary class="cc-lint-summary"><span class="cc-lint-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $badge_label ) . '</span></summary>';

		if ( $fail_count > 0 ) {
			echo '<ul class="cc-lint-list">';
			foreach ( $checks as $name => $c ) {
				if ( ! empty( $c['pass'] ) ) {
					continue;
				}
				$label   = method_exists( 'CC_Assistant_Pre_Publish', 'label_for' )
					? CC_Assistant_Pre_Publish::label_for( $name )
					: ucfirst( str_replace( '_', ' ', $name ) );
				$message = isset( $c['message'] ) ? $c['message'] : '';
				echo '<li class="cc-lint-item"><span class="cc-lint-item-label">' . esc_html( $label ) . '</span> ';
				if ( in_array( $name, $pre_existing, true ) ) {
					echo '<em style="color:#6b7280;font-size:11px;font-style:normal;border:1px solid #d1d5db;border-radius:8px;padding:0 6px;margin-right:4px;">' . esc_html__( 'pre-existing', 'cc-assistant' ) . '</em> ';
				}
				echo '<span class="cc-lint-item-message">' . esc_html( $message ) . '</span>';

				// Surface the most useful per-check evidence so the reviewer
				// does not have to read raw JSON.
				if ( 'paragraph_length' === $name && ! empty( $c['violations'] ) ) {
					echo '<ol class="cc-lint-evidence">';
					foreach ( (array) $c['violations'] as $v ) {
						echo '<li>' . sprintf(
							'<strong>%d sentences:</strong> %s',
							(int) ( $v['sentences'] ?? 0 ),
							esc_html( (string) ( $v['snippet'] ?? '' ) )
						) . '</li>';
					}
					echo '</ol>';
				} elseif ( 'sentence_length' === $name && ! empty( $c['samples'] ) ) {
					echo '<ol class="cc-lint-evidence">';
					foreach ( (array) $c['samples'] as $s ) {
						echo '<li>' . sprintf(
							'<strong>%d words:</strong> %s',
							(int) ( $s['words'] ?? 0 ),
							esc_html( (string) ( $s['snippet'] ?? '' ) )
						) . '</li>';
					}
					echo '</ol>';
				} elseif ( 'redundancy' === $name && ! empty( $c['shared_bigrams'] ) ) {
					echo '<div class="cc-lint-evidence"><strong>Shared bigrams:</strong> '
						. esc_html( implode( ' · ', array_slice( (array) $c['shared_bigrams'], 0, 12 ) ) )
						. '</div>';
				} elseif ( 'jargon' === $name && ! empty( $c['matches'] ) ) {
					$parts = array();
					foreach ( (array) $c['matches'] as $m ) {
						$parts[] = sprintf( '%s (%d)', (string) ( $m['term'] ?? '' ), (int) ( $m['count'] ?? 0 ) );
					}
					echo '<div class="cc-lint-evidence"><strong>Terms:</strong> ' . esc_html( implode( ', ', $parts ) ) . '</div>';
				} elseif ( 'style_guide' === $name && ! empty( $c['found'] ) ) {
					echo '<div class="cc-lint-evidence"><strong>Banned phrases hit:</strong> '
						. esc_html( implode( ', ', array_slice( (array) $c['found'], 0, 8 ) ) )
						. '</div>';
				} elseif ( 'ai_tells' === $name && ! empty( $c['found'] ) ) {
					echo '<div class="cc-lint-evidence"><strong>AI-tell phrases:</strong> '
						. esc_html( implode( ', ', (array) $c['found'] ) )
						. '</div>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</details>';
	}
}

if ( ! function_exists( 'cc_render_success_metrics_card' ) ) {
	/**
	 * Render the success_metrics attached to a pending change. Stored at
	 * queue time so edit_outcomes can score the change against intent later.
	 */
	function cc_render_success_metrics_card( $metrics ) {
		// Verdict prediction: if the metrics carry baseline_position (set by
		// the v0.22 auto-default flow) we can render an inline "now → target"
		// chip in the summary so the reviewer sees the prediction without
		// expanding the card. Source=auto vs. operator-supplied is also
		// surfaced because auto-defaulted metrics deserve more scrutiny.
		$source       = isset( $metrics['source'] ) ? (string) $metrics['source'] : 'operator';
		$baseline_pos = isset( $metrics['baseline_position'] ) ? (float) $metrics['baseline_position'] : null;
		$target_pos   = isset( $metrics['target_position'] ) ? (float) $metrics['target_position'] : null;
		$predict_chip = '';
		if ( null !== $baseline_pos && null !== $target_pos ) {
			$predict_chip = sprintf(
				' <span class="cc-metrics-predict" title="%s">%s%s</span>',
				esc_attr__( 'Baseline weighted-avg position vs target position.', 'cc-assistant' ),
				esc_html( sprintf( '%.1f', $baseline_pos ) . ' → ' . sprintf( '%.1f', $target_pos ) ),
				'auto' === $source ? ' <small style="opacity:0.7;">(auto)</small>' : ''
			);
		} elseif ( null !== $target_pos ) {
			$predict_chip = sprintf(
				' <span class="cc-metrics-predict" title="%s">→ %s</span>',
				esc_attr__( 'Target weighted-avg position.', 'cc-assistant' ),
				esc_html( sprintf( '%.1f', $target_pos ) )
			);
		}
		echo '<details class="cc-metrics-card">';
		echo '<summary class="cc-metrics-summary"><span class="cc-metrics-badge">' . esc_html__( 'Success metrics set', 'cc-assistant' ) . '</span>' . $predict_chip . '</summary>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $predict_chip is built from sprintf with esc_html/esc_attr internally.
		echo '<dl class="cc-metrics-list">';
		if ( ! empty( $metrics['target_query'] ) ) {
			echo '<dt>' . esc_html__( 'Target query', 'cc-assistant' ) . '</dt><dd>' . esc_html( (string) $metrics['target_query'] ) . '</dd>';
		}
		if ( null !== $baseline_pos ) {
			echo '<dt>' . esc_html__( 'Baseline position', 'cc-assistant' ) . '</dt><dd>' . esc_html( sprintf( '%.1f', $baseline_pos ) ) . '</dd>';
		}
		if ( isset( $metrics['target_position'] ) ) {
			echo '<dt>' . esc_html__( 'Target position', 'cc-assistant' ) . '</dt><dd>' . esc_html( (string) $metrics['target_position'] ) . '</dd>';
		}
		if ( isset( $metrics['target_ctr'] ) ) {
			echo '<dt>' . esc_html__( 'Target CTR', 'cc-assistant' ) . '</dt><dd>' . esc_html( sprintf( '%.2f%%', (float) $metrics['target_ctr'] * 100 ) ) . '</dd>';
		}
		if ( isset( $metrics['eval_window_days'] ) ) {
			echo '<dt>' . esc_html__( 'Eval window', 'cc-assistant' ) . '</dt><dd>' . esc_html( (int) $metrics['eval_window_days'] . ' days' ) . '</dd>';
		}
		if ( ! empty( $metrics['hypothesis'] ) ) {
			echo '<dt>' . esc_html__( 'Hypothesis', 'cc-assistant' ) . '</dt><dd>' . esc_html( (string) $metrics['hypothesis'] ) . '</dd>';
		}
		echo '</dl>';
		echo '</details>';
	}
}

if ( ! function_exists( 'cc_render_human_diff' ) ) {
	function cc_render_human_diff( $item ) {
		$current  = json_decode( $item->current_value, true );
		$proposed = json_decode( $item->proposed_value, true );

		$out = '';
		switch ( $item->change_type ) {
			case 'meta_update':
				$field = isset( $current['field'] ) ? $current['field'] : '';
				$out  .= sprintf(
					'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <code>%s</code></div>%s</div>',
					esc_html__( 'Field:', 'cc-assistant' ),
					esc_html( $field ),
					cc_smart_text_diff( $current['value'] ?? '', $proposed['value'] ?? '' )
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
						cc_smart_text_diff(
							'' === (string) $old ? '(not set)' : (string) $old,
							(string) $new
						)
					);
				}
				break;

			case 'term_update':
				$term_name = $proposed['term_name'] ?? $current['term_name'] ?? '';
				$taxonomy  = $proposed['taxonomy'] ?? $current['taxonomy'] ?? '';
				$out      .= sprintf(
					'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <strong>%s</strong> <code>%s #%d</code></div></div>',
					esc_html__( 'Category:', 'cc-assistant' ),
					esc_html( $term_name ),
					esc_html( $taxonomy ),
					(int) ( $proposed['term_id'] ?? $current['term_id'] ?? 0 )
				);
				$term_fields = array(
					'description'     => __( 'Archive description', 'cc-assistant' ),
					'seo_title'       => __( 'SEO title', 'cc-assistant' ),
					'seo_description' => __( 'SEO description', 'cc-assistant' ),
				);
				foreach ( $term_fields as $tf_key => $tf_label ) {
					if ( ! is_array( $proposed ) || ! array_key_exists( $tf_key, $proposed ) ) {
						continue;
					}
					$out .= sprintf(
						'<div class="cc-diff-section"><div class="cc-diff-field-label">%s</div>%s</div>',
						esc_html( $tf_label ),
						cc_smart_text_diff( $current[ $tf_key ] ?? '', $proposed[ $tf_key ] ?? '' )
					);
				}
				break;

			case 'postmeta_update':
				$key  = isset( $current['key'] ) ? $current['key'] : '';
				$out .= sprintf(
					'<div class="cc-diff-section"><div class="cc-diff-field-label">%s <code>%s</code></div>%s</div>',
					esc_html__( 'Meta key:', 'cc-assistant' ),
					esc_html( $key ),
					cc_smart_text_diff( $current['value'] ?? '', $proposed['value'] ?? '' )
				);
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
							cc_smart_text_diff( (string) $old_val, (string) $new_val )
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

			case 'elementor_widget_add':
				$parent_id   = isset( $proposed['parent_id'] ) ? (string) $proposed['parent_id'] : '';
				$widget_type = isset( $proposed['widget_type'] ) ? (string) $proposed['widget_type'] : '';
				$position    = isset( $proposed['position'] ) ? (string) (int) $proposed['position'] : '(append)';
				$settings    = isset( $proposed['settings'] ) && is_array( $proposed['settings'] ) ? $proposed['settings'] : array();

				$out .= '<div class="cc-diff-section cc-elementor-add">';
				$out .= sprintf(
					'<div class="cc-diff-field-label"><span class="cc-op-badge cc-op-add">%s</span> %s <code>%s</code> %s <code>%s</code> %s <code>%s</code></div>',
					esc_html__( 'ADD', 'cc-assistant' ),
					esc_html__( 'widget type', 'cc-assistant' ),
					esc_html( $widget_type ),
					esc_html__( 'into container', 'cc-assistant' ),
					esc_html( $parent_id ),
					esc_html__( 'at position', 'cc-assistant' ),
					esc_html( $position )
				);

				// Inline preview of the most relevant settings field.
				$preview_field = '';
				foreach ( array( 'title', 'title_text', 'editor', 'text', 'description_text', 'html' ) as $k ) {
					if ( ! empty( $settings[ $k ] ) && is_string( $settings[ $k ] ) ) {
						$preview_field = $k;
						break;
					}
				}
				if ( '' !== $preview_field ) {
					$out .= sprintf(
						'<div class="cc-diff-field-label">%s <code>%s</code>:</div><pre class="cc-mini-pre">%s</pre>',
						esc_html__( 'Preview', 'cc-assistant' ),
						esc_html( $preview_field ),
						esc_html( mb_substr( wp_strip_all_tags( (string) $settings[ $preview_field ] ), 0, 400 ) )
					);
				}

				$out .= sprintf(
					'<details class="cc-diff-collapsible"><summary>%s</summary><pre class="cc-mini-pre">%s</pre></details>',
					esc_html__( 'View full settings JSON', 'cc-assistant' ),
					esc_html( wp_json_encode( $settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) )
				);
				$out .= '</div>';
				break;

			case 'elementor_widget_remove':
				$widget_id = isset( $proposed['widget_id'] ) ? (string) $proposed['widget_id'] : '';
				$type      = isset( $current['type'] ) ? (string) $current['type'] : '';
				$preview   = isset( $current['preview'] ) ? (string) $current['preview'] : '';
				$elType    = isset( $current['elType'] ) ? (string) $current['elType'] : '';

				$out .= '<div class="cc-diff-section cc-elementor-remove">';
				$out .= sprintf(
					'<div class="cc-diff-field-label"><span class="cc-op-badge cc-op-remove">%s</span> %s <code>%s</code> (%s <code>%s</code>)</div>',
					esc_html__( 'REMOVE', 'cc-assistant' ),
					esc_html__( 'widget', 'cc-assistant' ),
					esc_html( $widget_id ),
					esc_html( $elType ),
					esc_html( $type )
				);
				if ( '' !== $preview ) {
					$out .= sprintf(
						'<div class="cc-diff-field-label">%s</div><pre class="cc-mini-pre">%s</pre>',
						esc_html__( 'Content that will be deleted:', 'cc-assistant' ),
						esc_html( $preview )
					);
				} else {
					$out .= sprintf( '<em class="cc-diff-empty">%s</em>', esc_html__( '(widget has no text preview — likely a divider or spacer)', 'cc-assistant' ) );
				}
				$out .= '</div>';
				break;

			case 'elementor_container_add':
				$parent_id = isset( $proposed['parent_id'] ) ? (string) $proposed['parent_id'] : '';
				$el_type   = isset( $proposed['el_type'] ) ? (string) $proposed['el_type'] : 'container';
				$children  = isset( $proposed['children'] ) && is_array( $proposed['children'] ) ? $proposed['children'] : array();
				$position  = isset( $proposed['position'] ) ? (string) (int) $proposed['position'] : '(append)';

				$out .= '<div class="cc-diff-section cc-elementor-container-add">';
				$out .= sprintf(
					'<div class="cc-diff-field-label"><span class="cc-op-badge cc-op-container">%s</span> %s <code>%s</code> %s <code>%s</code> %s <code>%s</code></div>',
					esc_html__( 'ADD SECTION', 'cc-assistant' ),
					esc_html__( 'as a', 'cc-assistant' ),
					esc_html( $el_type ),
					esc_html__( 'inside', 'cc-assistant' ),
					esc_html( '' === $parent_id ? '(page root)' : $parent_id ),
					esc_html__( 'at position', 'cc-assistant' ),
					esc_html( $position )
				);
				if ( ! empty( $children ) ) {
					$out .= '<div class="cc-diff-field-label">' . esc_html( sprintf( /* translators: %d: count */ _n( '%d child element to insert:', '%d child elements to insert:', count( $children ), 'cc-assistant' ), count( $children ) ) ) . '</div>';
					$out .= '<ul class="cc-elementor-children-preview">';
					foreach ( $children as $i => $ch ) {
						$ch_type     = isset( $ch['type'] ) ? (string) $ch['type'] : '?';
						$ch_widget   = isset( $ch['widgetType'] ) ? (string) $ch['widgetType'] : '';
						$ch_settings = isset( $ch['settings'] ) && is_array( $ch['settings'] ) ? $ch['settings'] : array();
						$ch_preview  = '';
						foreach ( array( 'title', 'title_text', 'editor', 'text', 'description_text', 'html' ) as $k ) {
							if ( ! empty( $ch_settings[ $k ] ) && is_string( $ch_settings[ $k ] ) ) {
								$ch_preview = mb_substr( wp_strip_all_tags( (string) $ch_settings[ $k ] ), 0, 120 );
								break;
							}
						}
						$out .= sprintf(
							'<li><code>%s</code>%s %s</li>',
							esc_html( $ch_widget ?: $ch_type ),
							isset( $ch['children'] ) && is_array( $ch['children'] ) && ! empty( $ch['children'] ) ? sprintf( ' <em>(+%d children)</em>', count( $ch['children'] ) ) : '',
							'' !== $ch_preview ? '&mdash; ' . esc_html( $ch_preview ) : ''
						);
					}
					$out .= '</ul>';
				}
				$out .= sprintf(
					'<details class="cc-diff-collapsible"><summary>%s</summary><pre class="cc-mini-pre">%s</pre></details>',
					esc_html__( 'View full proposed JSON', 'cc-assistant' ),
					esc_html( wp_json_encode( $proposed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) )
				);
				$out .= '</div>';
				break;

			case 'elementor_accordion_item_add':
				$accordion_id = isset( $proposed['accordion_widget_id'] ) ? (string) $proposed['accordion_widget_id'] : '';
				$title        = isset( $proposed['title'] ) ? (string) $proposed['title'] : '';
				$content_html = isset( $proposed['content_html'] ) ? (string) $proposed['content_html'] : '';
				$item_count   = isset( $current['item_count_before'] ) ? (int) $current['item_count_before'] : 0;

				$out .= '<div class="cc-diff-section cc-elementor-accordion-add">';
				$out .= sprintf(
					'<div class="cc-diff-field-label"><span class="cc-op-badge cc-op-add">%s</span> %s <code>%s</code> (%s)</div>',
					esc_html__( 'ADD FAQ ITEM', 'cc-assistant' ),
					esc_html__( 'to accordion', 'cc-assistant' ),
					esc_html( $accordion_id ),
					esc_html( sprintf( /* translators: %d: count */ _n( '%d item currently', '%d items currently', $item_count, 'cc-assistant' ), $item_count ) )
				);
				$out .= sprintf(
					'<div class="cc-diff-field-label">%s</div><div class="cc-mini-pre">%s</div>',
					esc_html__( 'Question:', 'cc-assistant' ),
					esc_html( $title )
				);
				if ( '' !== $content_html ) {
					$out .= sprintf(
						'<div class="cc-diff-field-label">%s</div><div class="cc-mini-pre">%s</div>',
						esc_html__( 'Answer:', 'cc-assistant' ),
						wp_kses_post( $content_html )
					);
				}
				$out .= '</div>';
				break;

			case 'cluster_create':
				$name        = isset( $proposed['name'] ) ? (string) $proposed['name'] : '';
				$desc        = isset( $proposed['description'] ) ? (string) $proposed['description'] : '';
				$pillar_id   = (int) ( $proposed['pillar_post_id'] ?? 0 );
				$pillar_html = '';
				if ( $pillar_id > 0 ) {
					$pillar_title = get_the_title( $pillar_id ) ?: ( '#' . $pillar_id );
					$pillar_link  = get_edit_post_link( $pillar_id );
					$pillar_html  = $pillar_link
						? sprintf( '<a href="%s">%s</a>', esc_url( $pillar_link ), esc_html( $pillar_title ) )
						: esc_html( $pillar_title );
				}
				$supporting_ids = array_map( 'intval', (array) ( $proposed['supporting_post_ids'] ?? array() ) );
				if ( $supporting_ids ) {
					_prime_post_caches( $supporting_ids, false, false );
				}

				$out .= '<div class="cc-diff-section cc-cluster-proposal">';
				$out .= sprintf(
					'<div class="cc-cluster-proposal-name"><span class="dashicons dashicons-category"></span> %s <strong>%s</strong></div>',
					esc_html__( 'New cluster:', 'cc-assistant' ),
					esc_html( $name )
				);
				if ( '' !== $desc ) {
					$out .= sprintf( '<p class="description">%s</p>', esc_html( $desc ) );
				}
				if ( $pillar_html ) {
					$out .= sprintf(
						'<div class="cc-cluster-proposal-pillar"><span class="dashicons dashicons-flag"></span> %s %s</div>',
						esc_html__( 'Pillar:', 'cc-assistant' ),
						$pillar_html
					);
				} else {
					$out .= sprintf( '<div class="cc-cluster-proposal-pillar cc-pillar-missing"><span class="dashicons dashicons-warning"></span> %s</div>', esc_html__( 'No pillar selected', 'cc-assistant' ) );
				}
				if ( $supporting_ids ) {
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
				break;

			case 'cluster_assign':
				require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
				$cluster_id = (int) ( $proposed['cluster_id'] ?? 0 );
				$post_id    = (int) ( $proposed['post_id'] ?? 0 );
				$role       = (string) ( $proposed['role'] ?? 'supporting' );
				$cluster    = $cluster_id > 0 ? CC_Assistant_Topic_Clusters::get_cluster( $cluster_id ) : null;
				$post_title = $post_id > 0 ? get_the_title( $post_id ) : '';
				$post_link  = $post_id > 0 ? get_edit_post_link( $post_id ) : '';

				$out .= '<div class="cc-diff-section cc-cluster-proposal">';
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
				break;

			case 'bulk_term_assign':
				require_once CC_ASSISTANT_DIR . 'includes/class-diff-render.php';
				$out .= CC_Assistant_Diff_Render::render_bulk_term_diff( $proposed );
				break;

			case 'plugin_setting_update':
				require_once CC_ASSISTANT_DIR . 'includes/class-diff-render.php';
				$out .= CC_Assistant_Diff_Render::render_plugin_setting_diff( $proposed );
				break;

			case 'kit_setting_update':
				require_once CC_ASSISTANT_DIR . 'includes/class-diff-render.php';
				$out .= CC_Assistant_Diff_Render::render_plugin_setting_diff( CC_Assistant_Diff_Render::kit_plan_as_setting( $proposed ) );
				break;

			case 'asset_reference_replace':
				// Delegated rather than copied: this view already duplicates
				// every other case from CC_Assistant_Diff_Render, and the two
				// copies drift. A site-wide URL swap is the one change type
				// where a stale preview would hide which rows get touched.
				require_once CC_ASSISTANT_DIR . 'includes/class-diff-render.php';
				$out .= CC_Assistant_Diff_Render::render_asset_reference_diff( $proposed );
				break;

			default:
				$out .= '<em class="cc-diff-empty">(no preview available for this change type)</em>';
		}

		return $out;
	}
}

$tab_labels = array(
	'pending'     => __( 'Pending', 'cc-assistant' ),
	'approved'    => __( 'Applied', 'cc-assistant' ),
	'rejected'    => __( 'Rejected', 'cc-assistant' ),
	'rolled_back' => __( 'Rolled back', 'cc-assistant' ),
	'applying' => __( 'Applying', 'cc-assistant' ),
	'apply_failed' => __( 'Apply failed', 'cc-assistant' ),
	'rolling_back' => __( 'Rolling back', 'cc-assistant' ),
	'rollback_failed' => __( 'Rollback failed', 'cc-assistant' ),
);

if ( ! function_exists( 'cc_change_type_label' ) ) {
	/**
	 * Human-readable label for a change_type. Used in group headers and badges.
	 */
	function cc_change_type_label( $type ) {
		$map = array(
			'meta_update'                  => __( 'Page settings', 'cc-assistant' ),
			'postmeta_update'              => __( 'Custom field', 'cc-assistant' ),
			'elementor_widget_update'      => __( 'Content edit', 'cc-assistant' ),
			'elementor_widget_add'         => __( 'Add widget', 'cc-assistant' ),
			'elementor_widget_remove'      => __( 'Remove widget', 'cc-assistant' ),
			'elementor_container_add'      => __( 'Add section', 'cc-assistant' ),
			'elementor_accordion_item_add' => __( 'Add FAQ item', 'cc-assistant' ),
			'post_content_update'          => __( 'Body rewrite', 'cc-assistant' ),
			'publish_draft'                => __( 'Publish draft', 'cc-assistant' ),
			'cluster_create'               => __( 'New topic cluster', 'cc-assistant' ),
			'cluster_assign'               => __( 'Cluster assignment', 'cc-assistant' ),
			'rewrite_outline'              => __( 'Editorial outline', 'cc-assistant' ),
			'term_update'                  => __( 'Category page', 'cc-assistant' ),
			'rank_math_schema_update'      => __( 'Rank Math schema', 'cc-assistant' ),
			'asset_reference_replace'      => __( 'Media URL swap', 'cc-assistant' ),
			'bulk_term_assign'             => __( 'Bulk brand / term tagging', 'cc-assistant' ),
			'plugin_setting_update'        => __( 'Plugin setting', 'cc-assistant' ),
			'kit_setting_update'           => __( 'Elementor Site Settings', 'cc-assistant' ),
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : ucfirst( str_replace( '_', ' ', $type ) );
	}
}

if ( ! function_exists( 'cc_classify_item_issue' ) ) {
	/**
	 * Single-item version: returns the issue bucket name for one pending change.
	 * Same heuristics the group summarizer uses, kept in one place so per-item
	 * tagging and per-group counts cannot drift.
	 */
	function cc_classify_item_issue( $item ) {
		$summary = strtolower( (string) $item->change_summary );
		$reason  = strtolower( (string) $item->reasoning );
		if ( false !== strpos( $summary, 'em dash' ) || false !== strpos( $reason, 'em dash' ) || false !== strpos( $summary, 'em-dash' ) ) {
			return __( 'Em dash cleanup', 'cc-assistant' );
		}
		if ( false !== strpos( $summary, 'meta description' ) || false !== strpos( $summary, 'metadesc' ) || false !== strpos( $summary, 'seo description' ) ) {
			return __( 'Meta description', 'cc-assistant' );
		}
		if ( false !== strpos( $summary, 'meta title' ) || false !== strpos( $summary, 'seo title' ) ) {
			return __( 'SEO title', 'cc-assistant' );
		}
		if ( false !== strpos( $summary, 'alt text' ) || false !== strpos( $summary, 'alt attribute' ) ) {
			return __( 'Image alt text', 'cc-assistant' );
		}
		if ( false !== strpos( $summary, 'authority' ) || false !== strpos( $summary, 'cite' ) || false !== strpos( $summary, 'source link' ) ) {
			return __( 'Citations', 'cc-assistant' );
		}
		if ( false !== strpos( $summary, 'heading' ) || false !== strpos( $summary, 'h1' ) || false !== strpos( $summary, 'h2' ) ) {
			return __( 'Heading', 'cc-assistant' );
		}
		if ( false !== strpos( $summary, 'internal link' ) || false !== strpos( $summary, 'external link' ) ) {
			return __( 'Links', 'cc-assistant' );
		}
		return cc_change_type_label( $item->change_type );
	}
}

if ( ! function_exists( 'cc_summarize_group_issues' ) ) {
	/**
	 * Detect issue themes from a group of items by inspecting the change_summary text.
	 * Returns a list of human-readable issue tags like ["Em dash cleanup", "Meta description"].
	 */
	function cc_summarize_group_issues( $items ) {
		$themes = array();
		$counts = array();
		foreach ( $items as $item ) {
			$bucket = cc_classify_item_issue( $item );
			if ( ! isset( $counts[ $bucket ] ) ) {
				$counts[ $bucket ] = 0;
				$themes[]          = $bucket;
			}
			$counts[ $bucket ]++;
		}
		$out = array();
		foreach ( $themes as $name ) {
			$out[] = array(
				'name'  => $name,
				'count' => $counts[ $name ],
			);
		}
		return $out;
	}
}

if ( ! function_exists( 'cc_count_issues_for_pane' ) ) {
	/**
	 * Returns issue buckets across an entire tab pane (not per-group), with counts,
	 * sorted by count desc. Used to render the top-of-pane filter pills.
	 */
	function cc_count_issues_for_pane( $items ) {
		$counts = array();
		foreach ( $items as $item ) {
			$bucket = cc_classify_item_issue( $item );
			$counts[ $bucket ] = isset( $counts[ $bucket ] ) ? $counts[ $bucket ] + 1 : 1;
		}
		arsort( $counts );
		$out = array();
		foreach ( $counts as $name => $count ) {
			$out[] = array( 'name' => $name, 'count' => $count );
		}
		return $out;
	}
}

if ( ! function_exists( 'cc_issue_slug' ) ) {
	/**
	 * Stable kebab-case slug for an issue label so it survives in data attrs and CSS.
	 */
	function cc_issue_slug( $label ) {
		return sanitize_title_with_dashes( $label );
	}
}

if ( ! function_exists( 'cc_group_items_by_post' ) ) {
	/**
	 * Bucket items by post_id, with the most recently active group first
	 * and the newest item within each group also first.
	 */
	function cc_group_items_by_post( $items ) {
		$groups = array();
		foreach ( $items as $item ) {
			$key = $item->post_id ? 'p_' . (int) $item->post_id : 'site';
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'post_id'   => $item->post_id ? (int) $item->post_id : null,
					'items'     => array(),
					'newest_at' => $item->created_at,
				);
			}
			$groups[ $key ]['items'][] = $item;
			if ( $item->created_at > $groups[ $key ]['newest_at'] ) {
				$groups[ $key ]['newest_at'] = $item->created_at;
			}
		}
		uasort(
			$groups,
			function ( $a, $b ) {
				return strcmp( $b['newest_at'], $a['newest_at'] );
			}
		);
		return $groups;
	}
}
?>
<div class="wrap cc-assistant" data-pending-count="<?php echo (int) $counts['pending']; ?>">
	<h1><?php esc_html_e( 'Changes', 'cc-assistant' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-reindex' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Reindex tracker', 'cc-assistant' ); ?></a>
	</h1>
	<p class="cc-tagline"><?php esc_html_e( 'Every change Claude proposes lives here. Approve to apply with a snapshot, reject to discard. Applied changes can be rolled back at any time.', 'cc-assistant' ); ?></p>

	<div id="cc-refresh-banner" class="cc-refresh-banner" hidden>
		<span class="dashicons dashicons-update"></span>
		<span><strong id="cc-refresh-message"></strong> <?php esc_html_e( 'Reload to see them.', 'cc-assistant' ); ?></span>
		<button type="button" class="button button-primary" id="cc-refresh-btn"><?php esc_html_e( 'Reload now', 'cc-assistant' ); ?></button>
	</div>

	<?php if ( $action_message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post( $action_message ); ?></p></div>
	<?php endif; ?>
	<?php if ( $action_error ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $action_error ); ?></p></div>
	<?php endif; ?>

	<?php
	// Two retroactive integrity checks across the existing pending queue.
	// Both ride a shared 1-hour transient so the inbox load stays cheap;
	// busted whenever a new PC is queued (see queue() in class-pending-changes.php).
	$banner_cache = get_transient( 'cc_inbox_integrity_banners' );
	if ( false === $banner_cache ) {
		$banner_cache = array(
			'conflicts'      => CC_Assistant_Pending_Changes::find_pending_widget_conflicts(),
			'lint_failures'  => CC_Assistant_Pending_Changes::scan_pending_for_lint_violations( 200 ),
			'computed_at'    => time(),
		);
		set_transient( 'cc_inbox_integrity_banners', $banner_cache, HOUR_IN_SECONDS );
	}
	if ( ! empty( $banner_cache['conflicts'] ) ) :
		$conflict_count = count( $banner_cache['conflicts'] );
		?>
		<div class="notice notice-warning"><p>
			<strong><?php
			printf(
				/* translators: %d: count */
				esc_html( _n( '%d widget has multiple pending edits queued.', '%d widgets have multiple pending edits queued.', $conflict_count, 'cc-assistant' ) ),
				$conflict_count
			);
			?></strong>
			<?php esc_html_e( 'Approve in the order they were queued — the second apply replaces what the first added.', 'cc-assistant' ); ?>
			<details style="display:inline-block; margin-left:6px;">
				<summary style="cursor:pointer;"><?php esc_html_e( 'Show conflicts', 'cc-assistant' ); ?></summary>
				<ul style="margin-top:8px;">
					<?php foreach ( $banner_cache['conflicts'] as $g ) :
						$ids = array();
						foreach ( $g['pending'] as $row ) {
							$ids[] = '#' . $row['id'];
						}
						?>
						<li>
							<?php
							printf(
								/* translators: 1: post link 2: widget id 3: pending ids */
								esc_html__( '%1$s — widget %2$s — pending: %3$s', 'cc-assistant' ),
								'<a href="' . esc_url( get_edit_post_link( (int) $g['post_id'], 'raw' ) ) . '">' . esc_html( get_the_title( (int) $g['post_id'] ) ) . '</a>',
								'<code>' . esc_html( $g['widget_id'] ) . '</code>',
								esc_html( implode( ', ', $ids ) )
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		</p></div>
	<?php endif; ?>
	<?php if ( ! empty( $banner_cache['lint_failures'] ) ) :
		$lint_count = count( $banner_cache['lint_failures'] );
		?>
		<div class="notice notice-error"><p>
			<strong><?php
			printf(
				/* translators: %d: count */
				esc_html( _n( '%d pending change has a style-guide violation.', '%d pending changes have style-guide violations.', $lint_count, 'cc-assistant' ) ),
				$lint_count
			);
			?></strong>
			<?php esc_html_e( 'These were queued before the queue-time validator was added. Review and supersede before approving.', 'cc-assistant' ); ?>
			<details style="display:inline-block; margin-left:6px;">
				<summary style="cursor:pointer;"><?php esc_html_e( 'Show violations', 'cc-assistant' ); ?></summary>
				<ul style="margin-top:8px;">
					<?php foreach ( array_slice( $banner_cache['lint_failures'], 0, 25 ) as $v ) : ?>
						<li>
							#<?php echo (int) $v['id']; ?> —
							<?php echo esc_html( $v['change_summary'] ); ?>
							<small>(<?php echo esc_html( implode( ', ', $v['hard_violations'] ) ); ?>)</small>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		</p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper cc-tab-nav">
		<?php foreach ( $tab_labels as $key => $label ) :
			$count  = $counts[ $key ] ?? 0;
			$active = $initial_tab === $key ? 'nav-tab-active' : '';
			?>
			<a href="#" class="nav-tab cc-tab-link <?php echo esc_attr( $active ); ?>" data-tab="<?php echo esc_attr( $key ); ?>">
				<?php echo esc_html( $label ); ?>
				<?php if ( $count > 0 ) : ?>
					<span class="cc-tab-count"><?php echo esc_html( $count ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php foreach ( $valid_tabs as $tab_key ) :
		$items     = $by_status[ $tab_key ];
		$is_active = $initial_tab === $tab_key;
		?>
		<div class="cc-tab-pane" data-pane="<?php echo esc_attr( $tab_key ); ?>" <?php echo $is_active ? '' : 'hidden'; ?>>

			<?php if ( empty( $items ) ) : ?>
				<div class="cc-empty-state">
					<span class="dashicons dashicons-yes"></span>
					<h2>
						<?php
						if ( 'pending' === $tab_key ) {
							esc_html_e( 'All caught up', 'cc-assistant' );
						} else {
							printf( esc_html__( 'No %s changes', 'cc-assistant' ), esc_html( strtolower( $tab_labels[ $tab_key ] ) ) );
						}
						?>
					</h2>
					<p>
						<?php
						if ( 'pending' === $tab_key ) {
							esc_html_e( 'No changes waiting for review. When Claude proposes edits, they will show up here in real time.', 'cc-assistant' );
						} else {
							esc_html_e( 'Nothing in this tab yet.', 'cc-assistant' );
						}
						?>
					</p>
				</div>
			<?php else : ?>
				<?php
				$issue_buckets = cc_count_issues_for_pane( $items );
				if ( 'pending' === $tab_key && count( $issue_buckets ) > 1 ) :
				?>
				<div class="cc-issue-filter-bar" data-issue-pane="<?php echo esc_attr( $tab_key ); ?>">
					<span class="cc-issue-filter-label"><?php esc_html_e( 'Show only:', 'cc-assistant' ); ?></span>
					<?php foreach ( $issue_buckets as $bucket ) :
						$slug = cc_issue_slug( $bucket['name'] );
						?>
						<button type="button"
							class="cc-issue-pill"
							data-issue-slug="<?php echo esc_attr( $slug ); ?>"
							data-pane="<?php echo esc_attr( $tab_key ); ?>">
							<?php echo esc_html( $bucket['name'] ); ?>
							<span class="cc-issue-pill-count"><?php echo (int) $bucket['count']; ?></span>
						</button>
					<?php endforeach; ?>
					<button type="button" class="cc-issue-clear" data-clear-pane="<?php echo esc_attr( $tab_key ); ?>" hidden>
						<span class="dashicons dashicons-no-alt"></span>
						<?php esc_html_e( 'Clear', 'cc-assistant' ); ?>
					</button>
				</div>
				<?php endif; ?>
				<?php if ( 'pending' === $tab_key ) : ?>
				<div class="cc-bulk-toolbar" data-pane-bulk="<?php echo esc_attr( $tab_key ); ?>">
					<label class="cc-select-all-wrap">
						<input type="checkbox" class="cc-select-all" data-target-pane="<?php echo esc_attr( $tab_key ); ?>">
						<?php esc_html_e( 'Select all', 'cc-assistant' ); ?>
					</label>
					<div class="cc-filter-wrap">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<input
							type="search"
							class="cc-filter-input"
							data-filter-pane="<?php echo esc_attr( $tab_key ); ?>"
							placeholder="<?php esc_attr_e( 'Filter by summary, reason, or post', 'cc-assistant' ); ?>"
							autocomplete="off"
						>
						<kbd class="cc-kbd-hint" title="<?php esc_attr_e( 'Press / to focus', 'cc-assistant' ); ?>">/</kbd>
					</div>
					<span class="cc-bulk-count" data-bulk-count="<?php echo esc_attr( $tab_key ); ?>">0 selected</span>
					<button type="button" class="button button-primary cc-bulk-btn" data-bulk-action="bulk_approve" disabled>
						<?php esc_html_e( 'Approve selected', 'cc-assistant' ); ?>
					</button>
					<button type="button" class="button cc-bulk-btn" data-bulk-action="bulk_reject" disabled>
						<?php esc_html_e( 'Reject selected', 'cc-assistant' ); ?>
					</button>
					<span class="cc-bulk-divider" hidden></span>
					<button type="button" class="button button-primary cc-bulk-filtered-btn" data-bulk-action="bulk_approve" data-filtered-pane="<?php echo esc_attr( $tab_key ); ?>" hidden>
						<span class="cc-filtered-label"><?php esc_html_e( 'Approve all visible', 'cc-assistant' ); ?></span>
					</button>
					<button type="button" class="button cc-bulk-filtered-btn" data-bulk-action="bulk_reject" data-filtered-pane="<?php echo esc_attr( $tab_key ); ?>" hidden>
						<span class="cc-filtered-label"><?php esc_html_e( 'Reject all visible', 'cc-assistant' ); ?></span>
					</button>
					<button type="button" class="button-link cc-keyboard-help-btn" title="<?php esc_attr_e( 'Keyboard shortcuts', 'cc-assistant' ); ?>" aria-label="<?php esc_attr_e( 'Keyboard shortcuts', 'cc-assistant' ); ?>">
						<span class="dashicons dashicons-editor-help"></span>
					</button>
				</div>
				<?php endif; ?>
				<?php
				$post_groups = cc_group_items_by_post( $items );
				foreach ( $post_groups as $group_key => $group ) :
					$group_post_id    = $group['post_id'];
					// get_the_title returns empty for trashed / permanently
					// deleted posts. Fall through to a "[Post #N — deleted]"
					// label so the inbox row doesn't render with a blank
					// title and so the operator can still see what was queued.
					if ( $group_post_id ) {
						$group_post_title = get_the_title( $group_post_id );
						if ( '' === $group_post_title ) {
							$group_post_title = sprintf( __( '[Post #%d — deleted]', 'cc-assistant' ), (int) $group_post_id );
						}
					} else {
						$group_post_title = __( 'Site-level', 'cc-assistant' );
					}
					$group_edit_link  = $group_post_id ? get_edit_post_link( $group_post_id ) : '';
					$group_permalink  = $group_post_id ? get_permalink( $group_post_id ) : '';
					$group_count      = count( $group['items'] );
					$group_dom_id     = 'cc-group-' . esc_attr( $group_key );
					?>
					<?php
					$group_issues       = cc_summarize_group_issues( $group['items'] );
					$group_issue_text   = '';
					$group_issue_slugs  = array();
					foreach ( $group_issues as $gi ) {
						$group_issue_text   .= ' ' . $gi['name'];
						$group_issue_slugs[] = cc_issue_slug( $gi['name'] );
					}

					// Per-post rollup stats: scan the items in this group for
					// lint failures, hard violations, and success_metrics
					// presence so the group header can show the at-a-glance
					// "3 pendings, 2 with lint failures, 1 with metrics set"
					// summary without forcing the operator to expand the group.
					$rollup_lint_fail = 0;
					$rollup_hard_violations = 0;
					$rollup_with_metrics = 0;
					$rollup_largest_delta = 0;
					foreach ( $group['items'] as $g_item ) {
						$lint_blob = ! empty( $g_item->lint_report )
							? json_decode( $g_item->lint_report, true )
							: null;
						if ( is_array( $lint_blob ) ) {
							if ( ! empty( $lint_blob['fail_count'] ) ) {
								$rollup_lint_fail++;
							}
							if ( ! empty( $lint_blob['hard_violations'] ) ) {
								$rollup_hard_violations++;
							}
						}
						if ( ! empty( $g_item->success_metrics ) ) {
							$rollup_with_metrics++;
						}
						if ( 'post_content_update' === $g_item->change_type ) {
							$cur = json_decode( $g_item->current_value, true );
							$prop = json_decode( $g_item->proposed_value, true );
							$cur_len = is_array( $cur ) && isset( $cur['content'] ) ? strlen( $cur['content'] ) : 0;
							$prop_len = is_array( $prop ) && isset( $prop['content'] ) ? strlen( $prop['content'] ) : 0;
							$delta = abs( $prop_len - $cur_len );
							if ( $delta > abs( $rollup_largest_delta ) ) {
								$rollup_largest_delta = $prop_len - $cur_len;
							}
						}
					}
					$group_filter_text   = strtolower(
						(string) $group_post_title
						. ' ' . $group_issue_text
					);
					$group_issue_attr = implode( ' ', array_unique( $group_issue_slugs ) );
					?>
					<div class="cc-post-group" data-group="<?php echo esc_attr( $group_key ); ?>" data-filter-text="<?php echo esc_attr( $group_filter_text ); ?>" data-issue-slugs="<?php echo esc_attr( $group_issue_attr ); ?>">
						<div class="cc-group-header">
							<div class="cc-group-title">
								<?php if ( 'pending' === $tab_key ) : ?>
									<input type="checkbox" class="cc-group-select-all" data-group="<?php echo esc_attr( $group_key ); ?>" data-pane="<?php echo esc_attr( $tab_key ); ?>" title="<?php esc_attr_e( 'Select all in this post', 'cc-assistant' ); ?>">
								<?php endif; ?>
								<button type="button" class="cc-group-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $group_dom_id ); ?>" title="<?php esc_attr_e( 'Expand / collapse', 'cc-assistant' ); ?>">
									<span class="dashicons dashicons-arrow-right"></span>
								</button>
								<div class="cc-group-title-text">
									<div class="cc-group-title-row">
										<?php if ( $group_post_id && $group_edit_link ) : ?>
											<a href="<?php echo esc_url( $group_edit_link ); ?>" class="cc-group-post-title"><?php echo esc_html( $group_post_title ); ?></a>
										<?php else : ?>
											<span class="cc-group-post-title"><em><?php echo esc_html( $group_post_title ); ?></em></span>
										<?php endif; ?>
										<?php if ( $group_permalink ) : ?>
											<a href="<?php echo esc_url( $group_permalink ); ?>" target="_blank" class="cc-view-live" title="<?php esc_attr_e( 'View live', 'cc-assistant' ); ?>"><span class="dashicons dashicons-external"></span></a>
										<?php endif; ?>
										<span class="cc-group-count-badge"><?php
											printf(
												esc_html( _n( '%d change', '%d changes', $group_count, 'cc-assistant' ) ),
												(int) $group_count
											);
										?></span>
									</div>
									<?php if ( ! empty( $group_issues ) ) : ?>
										<div class="cc-group-issues">
											<?php foreach ( $group_issues as $issue ) :
												$badge_slug = cc_issue_slug( $issue['name'] );
												?>
												<button type="button" class="cc-issue-badge cc-issue-badge-clickable" data-issue-slug="<?php echo esc_attr( $badge_slug ); ?>" data-pane="<?php echo esc_attr( $tab_key ); ?>" title="<?php esc_attr_e( 'Filter to this issue', 'cc-assistant' ); ?>">
													<?php echo esc_html( $issue['name'] ); ?>
													<?php if ( $issue['count'] > 1 ) : ?>
														<span class="cc-issue-count">&times;<?php echo (int) $issue['count']; ?></span>
													<?php endif; ?>
												</button>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
									<?php if ( $rollup_lint_fail > 0 || $rollup_hard_violations > 0 || $rollup_with_metrics > 0 || 0 !== $rollup_largest_delta ) : ?>
										<div class="cc-group-rollup">
											<?php if ( $rollup_hard_violations > 0 ) : ?>
												<span class="cc-rollup-pill cc-rollup-hard" title="<?php esc_attr_e( 'Pendings refusing to apply due to em dash / banned phrase / AI-tell violations.', 'cc-assistant' ); ?>">
													<span class="dashicons dashicons-warning"></span>
													<?php
													printf(
														esc_html( _n( '%d hard violation', '%d hard violations', $rollup_hard_violations, 'cc-assistant' ) ),
														(int) $rollup_hard_violations
													);
													?>
												</span>
											<?php endif; ?>
											<?php if ( $rollup_lint_fail > 0 ) : ?>
												<span class="cc-rollup-pill cc-rollup-warn" title="<?php esc_attr_e( 'Pendings with lint warnings (paragraph length, sentence length, redundancy, etc.).', 'cc-assistant' ); ?>">
													<span class="dashicons dashicons-flag"></span>
													<?php
													printf(
														esc_html( _n( '%d lint flag', '%d lint flags', $rollup_lint_fail, 'cc-assistant' ) ),
														(int) $rollup_lint_fail
													);
													?>
												</span>
											<?php endif; ?>
											<?php if ( $rollup_with_metrics > 0 ) : ?>
												<span class="cc-rollup-pill cc-rollup-metrics" title="<?php esc_attr_e( 'Pendings with success_metrics set so edit_outcomes can score them.', 'cc-assistant' ); ?>">
													<span class="dashicons dashicons-chart-line"></span>
													<?php
													printf(
														esc_html( _n( '%d with metrics', '%d with metrics', $rollup_with_metrics, 'cc-assistant' ) ),
														(int) $rollup_with_metrics
													);
													?>
												</span>
											<?php endif; ?>
											<?php if ( 0 !== $rollup_largest_delta ) : ?>
												<span class="cc-rollup-pill cc-rollup-delta" title="<?php esc_attr_e( 'Largest character delta among proposed body rewrites in this post.', 'cc-assistant' ); ?>">
													<span class="dashicons dashicons-editor-textcolor"></span>
													<?php
													printf(
														esc_html__( '%s chars', 'cc-assistant' ),
														( $rollup_largest_delta > 0 ? '+' : '' ) . number_format_i18n( $rollup_largest_delta )
													);
													?>
												</span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							</div>
							<?php if ( 'pending' === $tab_key ) : ?>
								<div class="cc-group-actions">
									<button type="button" class="button button-small cc-group-bulk" data-bulk-action="bulk_approve" data-group="<?php echo esc_attr( $group_key ); ?>"><?php esc_html_e( 'Approve all', 'cc-assistant' ); ?></button>
									<button type="button" class="button button-small cc-group-bulk" data-bulk-action="bulk_reject" data-group="<?php echo esc_attr( $group_key ); ?>"><?php esc_html_e( 'Reject all', 'cc-assistant' ); ?></button>
								</div>
							<?php endif; ?>
						</div>

						<div class="cc-group-body" id="<?php echo esc_attr( $group_dom_id ); ?>" hidden>
				<?php foreach ( $group['items'] as $item ) :
					$reviewer   = ! empty( $item->reviewed_by ) && isset( $reviewers[ (int) $item->reviewed_by ] ) ? $reviewers[ (int) $item->reviewed_by ] : '';
					$time_label = 'pending' === $item->status ? __( 'created', 'cc-assistant' ) : __( 'reviewed', 'cc-assistant' );
					$time_src   = 'pending' === $item->status || empty( $item->reviewed_at ) ? $item->created_at : $item->reviewed_at;
					$edit_link  = $item->post_id ? get_edit_post_link( $item->post_id ) : '';
					$item_issue       = cc_classify_item_issue( $item );
					$item_issue_slug  = cc_issue_slug( $item_issue );
					$item_filter_text = strtolower(
						(string) $item->change_summary
						. ' ' . (string) $item->reasoning
						. ' ' . (string) $item->change_type
						. ' ' . (string) $group_post_title
						. ' ' . $item_issue
					);
					// v0.61: hard-violation awareness for risk-styled Approve +
					// bulk-confirm counts. String probe keeps it O(1) per row.
					$item_has_hard = ! empty( $item->lint_report ) && false !== strpos( (string) $item->lint_report, '"hard_violations":["' );
					?>
					<div class="cc-pending-item" data-pending-id="<?php echo esc_attr( $item->id ); ?>" data-hard="<?php echo $item_has_hard ? '1' : '0'; ?>" data-filter-text="<?php echo esc_attr( $item_filter_text ); ?>" data-issue-slug="<?php echo esc_attr( $item_issue_slug ); ?>">
						<div class="cc-pending-head">
							<div class="cc-pending-head-left">
								<?php if ( 'pending' === $item->status ) : ?>
									<label class="cc-row-check-wrap">
										<input type="checkbox" name="cc_pending_ids[]" value="<?php echo esc_attr( $item->id ); ?>" class="cc-row-check" data-pane="<?php echo esc_attr( $tab_key ); ?>" data-group="<?php echo esc_attr( $group_key ); ?>">
									</label>
								<?php endif; ?>
								<span class="cc-tag" title="<?php echo esc_attr( $item->change_type ); ?>"><?php echo esc_html( cc_change_type_label( $item->change_type ) ); ?></span>
								<?php
								// Post-apply duplication verification pill. Only present
								// for body-content rewrites that have actually been applied
								// AND the 30s-delayed cron has run. Stable changes show
								// the pill too so the operator knows verification ran.
								if ( ! empty( $item->verification_result ) ) {
									if ( ! class_exists( 'CC_Assistant_Post_Apply_Verifier' ) ) {
										require_once CC_ASSISTANT_DIR . 'includes/class-post-apply-verifier.php';
									}
									echo ' ' . CC_Assistant_Post_Apply_Verifier::render_pill( $item->verification_result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								?>
							</div>
							<div class="cc-pending-time">
								<?php
								echo esc_html( $time_label . ' ' . human_time_diff( strtotime( $time_src ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'cc-assistant' ) );
								if ( $reviewer ) {
									echo ' &middot; ' . esc_html( $reviewer );
								}
								?>
							</div>
						</div>

						<h3 class="cc-pending-summary">
							<span class="cc-pending-id-badge" aria-label="<?php esc_attr_e( 'Pending change ID', 'cc-assistant' ); ?>">
								<?php echo esc_html( '#' . (int) $item->id ); ?>
							</span>
							<?php echo esc_html( $item->change_summary ); ?>
						</h3>

						<?php if ( ! empty( $item->reasoning ) ) : ?>
							<div class="cc-pending-reasoning">
								<strong><?php esc_html_e( 'Why:', 'cc-assistant' ); ?></strong>
								<?php echo esc_html( $item->reasoning ); ?>
							</div>
						<?php endif; ?>

						<?php
						// Lint report (post_content rewrites are linted at queue time).
						$lint = ! empty( $item->lint_report ) ? json_decode( $item->lint_report, true ) : null;
						if ( is_array( $lint ) && ! empty( $lint['checks'] ) ) :
							cc_render_lint_card( $lint );
						endif;

						// Success metrics (model-stated intent for outcome scoring).
						$metrics = ! empty( $item->success_metrics ) ? json_decode( $item->success_metrics, true ) : null;
						if ( is_array( $metrics ) && ! empty( $metrics ) ) :
							cc_render_success_metrics_card( $metrics );
						endif;
						?>

						<?php if ( 'publish_draft' === $item->change_type ) : ?>
							<div class="cc-pending-publish-note">
								<p>
									<?php
									if ( 'approved' === $item->status ) {
										esc_html_e( 'Draft was published.', 'cc-assistant' );
									} elseif ( 'rolled_back' === $item->status ) {
										esc_html_e( 'Published post was rolled back to draft.', 'cc-assistant' );
									} else {
										esc_html_e( 'A new draft post has been created. Approving will publish it.', 'cc-assistant' );
									}
									?>
									<?php if ( $item->post_id ) : ?>
										<a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Open in editor &rarr;', 'cc-assistant' ); ?></a>
									<?php endif; ?>
								</p>
							</div>
						<?php else : ?>
							<?php cc_render_structure_diff_card( $item ); ?>
							<?php if ( 'post_content_update' === $item->change_type ) : ?>
								<div class="cc-preview-actions">
									<button type="button" class="button button-secondary cc-preview-btn" data-cc-preview-pending-id="<?php echo (int) $item->id; ?>">
										<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
										<?php esc_html_e( 'Preview rendered HTML', 'cc-assistant' ); ?>
									</button>
								</div>
							<?php endif; ?>
							<div class="cc-human-diff" data-cc-diff-pending-id="<?php echo (int) $item->id; ?>" data-cc-diff-loaded="0">
								<div class="cc-diff-skeleton">
									<span class="cc-diff-skeleton-bar"></span>
									<span class="cc-diff-skeleton-bar"></span>
									<span class="cc-diff-skeleton-bar cc-diff-skeleton-short"></span>
								</div>
							</div>
							<details class="cc-tech-details">
								<summary><?php esc_html_e( 'Show technical details (raw JSON)', 'cc-assistant' ); ?></summary>
								<div class="cc-diff-grid">
									<div class="cc-diff-col cc-diff-current">
										<div class="cc-diff-label"><?php esc_html_e( 'current_value', 'cc-assistant' ); ?></div>
										<pre><?php echo esc_html( wp_json_encode( json_decode( $item->current_value, true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
									</div>
									<div class="cc-diff-col cc-diff-proposed">
										<div class="cc-diff-label"><?php esc_html_e( 'proposed_value', 'cc-assistant' ); ?></div>
										<pre><?php echo esc_html( wp_json_encode( json_decode( $item->proposed_value, true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
									</div>
								</div>
							</details>
						<?php endif; ?>

						<?php if ( in_array( $item->status, array( 'applying', 'apply_failed', 'rolling_back', 'rollback_failed' ), true ) ) : ?>
							<p><strong><?php echo esc_html( $tab_labels[ $item->status ] ); ?></strong> <?php echo esc_html( $item->review_note ?? '' ); ?></p>
							<p><?php esc_html_e( 'Inspect the current content and saved recovery snapshots before submitting a replacement change.', 'cc-assistant' ); ?></p>
						<?php endif; ?>
						<form method="post" class="cc-pending-actions">
							<?php wp_nonce_field( 'cc_pending_review', 'cc_pending_nonce' ); ?>
							<input type="hidden" name="cc_pending_id" value="<?php echo esc_attr( $item->id ); ?>">

							<?php if ( 'pending' === $item->status ) : ?>
								<button type="submit" name="cc_pending_action" value="approve" class="button button-primary<?php echo $item_has_hard ? ' cc-approve-risk' : ''; ?>"<?php echo $item_has_hard ? ' title="' . esc_attr__( 'This change has HARD lint violations — approving overrides them.', 'cc-assistant' ) . '"' : ''; ?>><?php echo 'publish_draft' === $item->change_type ? esc_html__( 'Approve and publish', 'cc-assistant' ) : esc_html__( 'Approve and apply', 'cc-assistant' ); ?></button>
								<input type="hidden" name="cc_pending_note" value="">
								<button type="button" class="button cc-reject-btn"><?php esc_html_e( 'Reject', 'cc-assistant' ); ?></button>
							<?php elseif ( 'approved' === $item->status ) : ?>
								<button type="submit" name="cc_pending_action" value="rollback" class="button" onclick="return confirm('<?php esc_attr_e( 'Roll back this change? A snapshot will be saved first.', 'cc-assistant' ); ?>');">
									<span class="dashicons dashicons-undo"></span>
									<?php esc_html_e( 'Roll back', 'cc-assistant' ); ?>
								</button>
								<?php if ( $edit_link ) : ?>
									<a href="<?php echo esc_url( $edit_link ); ?>" class="button button-secondary"><?php esc_html_e( 'Open post', 'cc-assistant' ); ?></a>
								<?php endif; ?>
							<?php elseif ( in_array( $item->status, array( 'rejected', 'rolled_back' ), true ) && $edit_link ) : ?>
								<a href="<?php echo esc_url( $edit_link ); ?>" class="button button-secondary"><?php esc_html_e( 'Open post', 'cc-assistant' ); ?></a>
							<?php endif; ?>
						</form>
					</div>
					<?php
				endforeach; // items in group
				?>
						</div><!-- .cc-group-body -->
					</div><!-- .cc-post-group -->
				<?php
				endforeach; // post_groups
			endif;
			?>

		</div>
	<?php endforeach; ?>

	<form id="cc-bulk-form" method="post" style="display:none">
		<?php wp_nonce_field( 'cc_pending_review', 'cc_pending_nonce' ); ?>
		<input type="hidden" name="cc_pending_action" id="cc-bulk-form-action" value="">
		<div id="cc-bulk-form-ids"></div>
	</form>

	<!-- Confirmation modal: replaces native confirm() so the dialog matches the rest of the UI. -->
	<div class="cc-modal-backdrop" id="cc-confirm-modal" hidden role="dialog" aria-modal="true" aria-labelledby="cc-confirm-title">
		<div class="cc-modal">
			<h2 id="cc-confirm-title" class="cc-modal-title"><?php esc_html_e( 'Confirm', 'cc-assistant' ); ?></h2>
			<p class="cc-modal-body" id="cc-confirm-body"></p>
			<div id="cc-confirm-reason-wrap" hidden>
				<label for="cc-confirm-reason" class="cc-modal-reason-label"><?php esc_html_e( 'Why? (optional — this note teaches the AI what to avoid next time)', 'cc-assistant' ); ?></label>
				<textarea id="cc-confirm-reason" rows="2" style="width:100%;"></textarea>
			</div>
			<div class="cc-modal-actions">
				<button type="button" class="button" id="cc-confirm-cancel"><?php esc_html_e( 'Cancel', 'cc-assistant' ); ?></button>
				<button type="button" class="button button-primary" id="cc-confirm-ok"><?php esc_html_e( 'Confirm', 'cc-assistant' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Keyboard shortcuts help -->
	<div class="cc-modal-backdrop" id="cc-keyboard-modal" hidden role="dialog" aria-modal="true" aria-labelledby="cc-keyboard-title">
		<div class="cc-modal cc-modal-narrow">
			<h2 id="cc-keyboard-title" class="cc-modal-title"><?php esc_html_e( 'Keyboard shortcuts', 'cc-assistant' ); ?></h2>
			<dl class="cc-keyboard-list">
				<dt><kbd>/</kbd></dt><dd><?php esc_html_e( 'Focus the filter box', 'cc-assistant' ); ?></dd>
				<dt><kbd>j</kbd> / <kbd>k</kbd></dt><dd><?php esc_html_e( 'Move focus to next / previous change', 'cc-assistant' ); ?></dd>
				<dt><kbd>x</kbd></dt><dd><?php esc_html_e( 'Toggle the focused change\'s checkbox', 'cc-assistant' ); ?></dd>
				<dt><kbd>a</kbd></dt><dd><?php esc_html_e( 'Approve selected', 'cc-assistant' ); ?></dd>
				<dt><kbd>r</kbd></dt><dd><?php esc_html_e( 'Reject selected', 'cc-assistant' ); ?></dd>
				<dt><kbd>e</kbd></dt><dd><?php esc_html_e( 'Expand or collapse the focused group', 'cc-assistant' ); ?></dd>
				<dt><kbd>?</kbd></dt><dd><?php esc_html_e( 'Show this help', 'cc-assistant' ); ?></dd>
				<dt><kbd>Esc</kbd></dt><dd><?php esc_html_e( 'Close dialogs / clear filter', 'cc-assistant' ); ?></dd>
			</dl>
			<div class="cc-modal-actions">
				<button type="button" class="button button-primary" id="cc-keyboard-close"><?php esc_html_e( 'Got it', 'cc-assistant' ); ?></button>
			</div>
		</div>
	</div>
</div>

<script>
(function () {
	// ---- Tab switching ----
	var tabs  = document.querySelectorAll('.cc-tab-link');
	var panes = document.querySelectorAll('.cc-tab-pane');
	function activate(tabKey) {
		tabs.forEach(function (t) {
			t.classList.toggle('nav-tab-active', t.dataset.tab === tabKey);
		});
		panes.forEach(function (p) {
			if (p.dataset.pane === tabKey) {
				p.removeAttribute('hidden');
			} else {
				p.setAttribute('hidden', '');
			}
		});
		try {
			var url = new URL(window.location.href);
			url.searchParams.set('tab', tabKey);
			history.replaceState(null, '', url.toString());
		} catch (e) {}
	}
	tabs.forEach(function (t) {
		t.addEventListener('click', function (e) {
			e.preventDefault();
			activate(this.dataset.tab);
		});
	});

	// ---- Custom confirmation modal ----
	var confirmModal  = document.getElementById('cc-confirm-modal');
	var confirmBody   = document.getElementById('cc-confirm-body');
	var confirmOk     = document.getElementById('cc-confirm-ok');
	var confirmCancel = document.getElementById('cc-confirm-cancel');
	var confirmResolver = null;

	function ccConfirm(message, withReason) {
		return new Promise(function (resolve) {
			if (!confirmModal) { resolve(window.confirm(message)); return; }
			confirmBody.textContent = message;
			var wrap = document.getElementById('cc-confirm-reason-wrap');
			if (wrap) {
				if (withReason) { wrap.removeAttribute('hidden'); } else { wrap.setAttribute('hidden', ''); }
				var ta = document.getElementById('cc-confirm-reason');
				if (ta) ta.value = '';
			}
			confirmModal.removeAttribute('hidden');
			confirmResolver = resolve;
			setTimeout(function () { confirmOk && confirmOk.focus(); }, 50);
		});
	}
	function closeConfirm(answer) {
		if (!confirmModal) return;
		confirmModal.setAttribute('hidden', '');
		if (confirmResolver) { confirmResolver(answer); confirmResolver = null; }
	}

	// v0.63 Review Deck: approve/reject WITHOUT a page reload. Decisions go
	// to the JSON /pending/{id}/decide endpoint; on success the card fades
	// out in place (a 37-item batch no longer means 37 reloads); on failure
	// the error is shown and nothing is removed; on network trouble we fall
	// back to the classic full-page POST.
	var ccDecideNonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
	var ccDecideBase  = '<?php echo esc_js( rest_url( 'cc-assistant/v1/pending/' ) ); ?>';

	function ccClassicSubmit(form, action, note) {
		var noteInput = form.querySelector('input[name="cc_pending_note"]');
		if (noteInput) noteInput.value = note || '';
		var a = document.createElement('input');
		a.type = 'hidden'; a.name = 'cc_pending_action'; a.value = action;
		form.appendChild(a);
		form.submit();
	}

	function ccDecide(form, action, note) {
		var item = form.closest('.cc-pending-item');
		var id   = (form.querySelector('input[name="cc_pending_id"]') || {}).value;
		if (!id) { ccClassicSubmit(form, action, note); return; }
		form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
		fetch(ccDecideBase + id + '/decide', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': ccDecideNonce, 'Content-Type': 'application/json' },
			body: JSON.stringify({ action: action, note: note || '' })
		}).then(function (r) { return r.json(); }).then(function (res) {
			if (res && res.ok) {
				if (item) {
					item.style.transition = 'opacity .25s';
					item.style.opacity = '0';
					setTimeout(function () { item.remove(); }, 260);
				}
				var badge = document.querySelector('.cc-tab-link[data-tab="pending"] .cc-tab-count')
					|| document.querySelector('.cc-tab-link[data-tab="pending"] .count');
				if (badge) {
					var n = parseInt(badge.textContent.replace(/\D/g, ''), 10);
					if (!isNaN(n) && n > 0) badge.textContent = badge.textContent.replace(/\d+/, String(n - 1));
				}
			} else {
				form.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
				window.alert(res && res.error
					? '<?php echo esc_js( __( 'Failed:', 'cc-assistant' ) ); ?> ' + res.error
					: '<?php echo esc_js( __( 'Action failed. Reloading to show details.', 'cc-assistant' ) ); ?>');
				if (!(res && res.error)) window.location.reload();
			}
		}).catch(function () { ccClassicSubmit(form, action, note); });
	}

	// Approve: intercept the submit so success removes the card in place.
	document.querySelectorAll('.cc-pending-actions').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			var btn = e.submitter;
			if (!btn || btn.name !== 'cc_pending_action' || btn.value !== 'approve') return;
			e.preventDefault();
			ccDecide(form, 'approve', '');
		});
	});

	// Reject: confirm + optional reason (the note teaches the AI operator).
	document.querySelectorAll('.cc-reject-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var form = btn.closest('form');
			ccConfirm('<?php echo esc_js( __( 'Reject this change? It will not be applied.', 'cc-assistant' ) ); ?>', true).then(function (ok) {
				if (!ok || !form) return;
				var ta = document.getElementById('cc-confirm-reason');
				ccDecide(form, 'reject', ta ? ta.value : '');
			});
		});
	});
	if (confirmOk)     confirmOk.addEventListener('click', function () { closeConfirm(true); });
	if (confirmCancel) confirmCancel.addEventListener('click', function () { closeConfirm(false); });
	if (confirmModal) {
		confirmModal.addEventListener('click', function (e) {
			if (e.target === confirmModal) closeConfirm(false);
		});
	}

	// ---- Keyboard shortcuts modal ----
	var kbModal = document.getElementById('cc-keyboard-modal');
	var kbClose = document.getElementById('cc-keyboard-close');
	var kbBtn   = document.querySelector('.cc-keyboard-help-btn');
	function openKb()  { if (kbModal) { kbModal.removeAttribute('hidden'); kbClose && kbClose.focus(); } }
	function closeKb() { if (kbModal) kbModal.setAttribute('hidden', ''); }
	if (kbBtn)   kbBtn.addEventListener('click', openKb);
	if (kbClose) kbClose.addEventListener('click', closeKb);
	if (kbModal) {
		kbModal.addEventListener('click', function (e) {
			if (e.target === kbModal) closeKb();
		});
	}

	// ---- Auto-refresh poll (smart: tells you which post got new changes) ----
	var wrap         = document.querySelector('.wrap.cc-assistant');
	var initialCount = wrap ? parseInt(wrap.dataset.pendingCount || '0', 10) : 0;
	var nonce        = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
	var restUrl      = '<?php echo esc_js( rest_url( 'cc-assistant/v1/pending' ) ); ?>';
	var banner       = document.getElementById('cc-refresh-banner');
	var bannerMsg    = document.getElementById('cc-refresh-message');
	var refreshBtn   = document.getElementById('cc-refresh-btn');

	// Snapshot the IDs we already rendered so we can compute what's actually new.
	var knownIds = {};
	document.querySelectorAll('.cc-pending-item[data-pending-id]').forEach(function (el) {
		knownIds[el.dataset.pendingId] = true;
	});

	function poll() {
		fetch(restUrl, {
			headers: { 'X-WP-Nonce': nonce, 'Accept': 'application/json' },
			credentials: 'same-origin'
		})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			if (!data || !data.data) return;
			var pending  = Array.isArray(data.data.pending) ? data.data.pending : [];
			var newCount = typeof data.data.count === 'number' ? data.data.count : null;
			if (newCount === null || newCount === initialCount) return;

			// Detect rows that exist server-side but not in our DOM snapshot.
			var newOnes = pending.filter(function (p) { return !knownIds[String(p.id)]; });
			var msg;
			if (newOnes.length > 0) {
				// Group by post for a nicer message.
				var byPost = {};
				newOnes.forEach(function (p) {
					var k = p.post_id || 'site';
					byPost[k] = (byPost[k] || 0) + 1;
				});
				var keys = Object.keys(byPost);
				if (keys.length === 1) {
					var k = keys[0];
					var n = byPost[k];
					msg = n + ' new ' + (n === 1 ? 'change' : 'changes') + (k === 'site' ? ' at site level' : ' on post #' + k) + '.';
				} else {
					msg = newOnes.length + ' new changes across ' + keys.length + ' posts.';
				}
			} else {
				var diff = newCount - initialCount;
				msg = diff > 0
					? (diff === 1 ? '1 new pending change.' : diff + ' new pending changes.')
					: 'Pending list changed.';
			}
			bannerMsg.textContent = msg;
			banner.removeAttribute('hidden');
		})
		.catch(function () {});
	}

	if (refreshBtn) {
		refreshBtn.addEventListener('click', function () {
			window.location.reload();
		});
	}

	if (wrap && banner && bannerMsg && refreshBtn) {
		setInterval(poll, 30000); // v0.62: 5s forever was unkind to phones; 30s is plenty for an approval inbox.
	}

	// ---- Filter (search term + issue pills, AND across, OR within issues) ----
	// State per pane: { term: string, issues: Set<string> }
	var filterState = {};
	function paneState(pane) {
		if (!filterState[pane]) filterState[pane] = { term: '', issues: {} };
		return filterState[pane];
	}
	function activeIssueList(pane) {
		var s = paneState(pane);
		return Object.keys(s.issues).filter(function (k) { return s.issues[k]; });
	}
	function applyFilter(pane) {
		var s          = paneState(pane);
		var term       = (s.term || '').trim().toLowerCase();
		var issuesOn   = activeIssueList(pane);
		var hasIssues  = issuesOn.length > 0;
		var items      = document.querySelectorAll('.cc-tab-pane[data-pane="' + pane + '"] .cc-pending-item');
		var groups     = document.querySelectorAll('.cc-tab-pane[data-pane="' + pane + '"] .cc-post-group');
		var anyMatch   = false;
		items.forEach(function (it) {
			var hay        = it.dataset.filterText || '';
			var slug       = it.dataset.issueSlug || '';
			var termMatch  = !term || hay.indexOf(term) !== -1;
			var issueMatch = !hasIssues || issuesOn.indexOf(slug) !== -1;
			var match      = termMatch && issueMatch;
			it.classList.toggle('cc-filter-hidden', !match);
			if (match) anyMatch = true;
		});
		// Hide groups whose visible items drop to 0; auto-expand when actively filtering.
		var actuallyFiltering = !!term || hasIssues;
		groups.forEach(function (g) {
			var visibleItems = g.querySelectorAll('.cc-pending-item:not(.cc-filter-hidden)');
			var keep         = visibleItems.length > 0;
			g.classList.toggle('cc-filter-hidden', !keep);
			if (actuallyFiltering && keep) {
				var body = g.querySelector('.cc-group-body');
				if (body && body.hasAttribute('hidden')) {
					body.removeAttribute('hidden');
					var t = g.querySelector('.cc-group-toggle');
					if (t) {
						t.setAttribute('aria-expanded', 'true');
						var icon = t.querySelector('.dashicons');
						if (icon) { icon.classList.remove('dashicons-arrow-right'); icon.classList.add('dashicons-arrow-down'); }
					}
				}
			}
		});
		// Sync pill active state and clear button.
		document.querySelectorAll('.cc-issue-pill[data-pane="' + pane + '"]').forEach(function (p) {
			var on = !!s.issues[p.dataset.issueSlug];
			p.classList.toggle('cc-issue-pill-active', on);
			p.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
		var clearBtn = document.querySelector('.cc-issue-clear[data-clear-pane="' + pane + '"]');
		if (clearBtn) {
			if (actuallyFiltering) clearBtn.removeAttribute('hidden');
			else clearBtn.setAttribute('hidden', '');
		}

		// Filter-aware combo buttons: "Approve all N visible" / "Reject all N visible".
		var visibleCount = 0;
		items.forEach(function (it) {
			if (!it.classList.contains('cc-filter-hidden')) visibleCount++;
		});
		var comboBtns = document.querySelectorAll('.cc-bulk-filtered-btn[data-filtered-pane="' + pane + '"]');
		var divider   = document.querySelector('.cc-tab-pane[data-pane="' + pane + '"] .cc-bulk-divider');
		// Show "Approve/Reject all visible" when actively filtering OR when the
		// pane has enough items that a per-row triage is tedious (the redirects
		// case: 28 PCs on one tab). The submitBulk handler always shows a
		// confirm dialog with the exact count, so a one-click approve-all stays
		// safe. Threshold of 5 covers the common batch-redirect pattern without
		// nagging the operator on small queues.
		var BULK_DISCOVERABILITY_FLOOR = 5;
		var showCombo = visibleCount > 0 && ( actuallyFiltering || visibleCount >= BULK_DISCOVERABILITY_FLOOR );
		// Build a context label. If exactly one issue pill is active and no search, use the issue name.
		var label = '';
		if (showCombo) {
			if (issuesOn.length === 1 && !term) {
				var pill = document.querySelector('.cc-issue-pill[data-pane="' + pane + '"][data-issue-slug="' + issuesOn[0] + '"]');
				var name = pill ? (pill.firstChild ? pill.firstChild.textContent : pill.textContent).trim() : 'visible';
				label = ' all ' + visibleCount + ' ' + name;
			} else {
				label = ' all ' + visibleCount + ' visible';
			}
		}
		comboBtns.forEach(function (b) {
			if (showCombo) {
				b.removeAttribute('hidden');
				var verb = b.dataset.bulkAction === 'bulk_approve' ? 'Approve' : 'Reject';
				var span = b.querySelector('.cc-filtered-label');
				if (span) span.textContent = verb + label;
			} else {
				b.setAttribute('hidden', '');
			}
		});
		if (divider) {
			if (showCombo) divider.removeAttribute('hidden');
			else divider.setAttribute('hidden', '');
		}
		// Empty state.
		var pane_el = document.querySelector('.cc-tab-pane[data-pane="' + pane + '"]');
		if (pane_el) {
			var noRes = pane_el.querySelector('.cc-no-filter-results');
			if (actuallyFiltering && !anyMatch) {
				if (!noRes) {
					noRes = document.createElement('div');
					noRes.className = 'cc-no-filter-results';
					noRes.innerHTML = '<p><?php echo esc_js( __( 'No changes match your filter. Press Esc to clear.', 'cc-assistant' ) ); ?></p>';
					pane_el.appendChild(noRes);
				}
				noRes.removeAttribute('hidden');
			} else if (noRes) {
				noRes.setAttribute('hidden', '');
			}
		}
	}
	function setTerm(pane, term) {
		paneState(pane).term = term || '';
		applyFilter(pane);
	}
	function toggleIssue(pane, slug) {
		var s = paneState(pane);
		if (s.issues[slug]) delete s.issues[slug];
		else s.issues[slug] = true;
		applyFilter(pane);
	}
	function clearFilters(pane) {
		var s = paneState(pane);
		s.term   = '';
		s.issues = {};
		var inp = document.querySelector('.cc-filter-input[data-filter-pane="' + pane + '"]');
		if (inp) inp.value = '';
		applyFilter(pane);
	}

	document.querySelectorAll('.cc-filter-input').forEach(function (inp) {
		inp.addEventListener('input', function () { setTerm(this.dataset.filterPane, this.value); });
	});
	document.querySelectorAll('.cc-issue-pill').forEach(function (pill) {
		pill.addEventListener('click', function () { toggleIssue(this.dataset.pane, this.dataset.issueSlug); });
	});
	// Group-header badges share the same pill semantics.
	document.querySelectorAll('.cc-issue-badge-clickable').forEach(function (b) {
		b.addEventListener('click', function (e) {
			e.stopPropagation(); // do not collapse/expand group when clicking the badge
			toggleIssue(this.dataset.pane, this.dataset.issueSlug);
		});
	});
	document.querySelectorAll('.cc-issue-clear').forEach(function (b) {
		b.addEventListener('click', function () { clearFilters(this.dataset.clearPane); });
	});

	// Initial filter pass per pane so the "Approve/Reject all visible" combo
	// buttons surface immediately on page load when the queue has enough items
	// to warrant batch action (BULK_DISCOVERABILITY_FLOOR inside applyFilter).
	// Without this priming pass the buttons stay hidden until the operator
	// types in the filter or clicks an issue pill — which was the root of the
	// "I had to reject 28 redirects one at a time" pain point.
	document.querySelectorAll('.cc-tab-pane[data-pane]').forEach(function (p) {
		applyFilter(p.dataset.pane);
	});

	// ---- Bulk approve / reject ----
	function updateBulkState() {
		document.querySelectorAll('.cc-bulk-toolbar').forEach(function (toolbar) {
			var pane     = toolbar.dataset.paneBulk;
			var checked  = document.querySelectorAll('.cc-row-check[data-pane="' + pane + '"]:checked');
			var allBoxes = document.querySelectorAll('.cc-row-check[data-pane="' + pane + '"]');
			var count    = checked.length;
			var counter  = toolbar.querySelector('[data-bulk-count="' + pane + '"]');
			if (counter) {
				counter.textContent = count + ' selected';
				counter.classList.toggle('cc-bulk-active', count > 0);
			}
			toolbar.querySelectorAll('.cc-bulk-btn').forEach(function (b) { b.disabled = count === 0; });
			var selectAll = toolbar.querySelector('.cc-select-all');
			if (selectAll && allBoxes.length > 0) {
				selectAll.checked       = count === allBoxes.length;
				selectAll.indeterminate = count > 0 && count < allBoxes.length;
			}
		});
	}

	function updateGroupCheckboxes() {
		document.querySelectorAll('.cc-group-select-all').forEach(function (gsa) {
			var grp     = gsa.dataset.group;
			var inGroup = document.querySelectorAll('.cc-row-check[data-group="' + grp + '"]');
			var checked = document.querySelectorAll('.cc-row-check[data-group="' + grp + '"]:checked');
			gsa.checked       = inGroup.length > 0 && checked.length === inGroup.length;
			gsa.indeterminate = checked.length > 0 && checked.length < inGroup.length;
		});
	}

	document.querySelectorAll('.cc-row-check').forEach(function (cb) {
		cb.addEventListener('change', function () {
			updateBulkState();
			updateGroupCheckboxes();
		});
	});

	function visibleRowChecks(selector) {
		return Array.prototype.filter.call(
			document.querySelectorAll(selector),
			function (cb) {
				var item = cb.closest('.cc-pending-item');
				if (!item) return true;
				if (item.classList.contains('cc-filter-hidden')) return false;
				var grp = item.closest('.cc-post-group');
				if (grp && grp.classList.contains('cc-filter-hidden')) return false;
				return true;
			}
		);
	}

	document.querySelectorAll('.cc-select-all').forEach(function (sa) {
		sa.addEventListener('change', function () {
			var pane = this.dataset.targetPane;
			visibleRowChecks('.cc-row-check[data-pane="' + pane + '"]').forEach(function (c) {
				c.checked = sa.checked;
			});
			updateBulkState();
			updateGroupCheckboxes();
		});
	});

	document.querySelectorAll('.cc-group-select-all').forEach(function (gsa) {
		gsa.addEventListener('change', function () {
			var grp = this.dataset.group;
			visibleRowChecks('.cc-row-check[data-group="' + grp + '"]').forEach(function (c) {
				c.checked = gsa.checked;
			});
			updateBulkState();
			updateGroupCheckboxes();
		});
	});

	function submitBulk(action, ids) {
		if (!ids.length) return;
		var verb = action === 'bulk_approve' ? 'apply' : 'reject';
		var msg  = 'This will ' + verb + ' ' + ids.length + ' change' + (ids.length === 1 ? '' : 's') + ' at once. A snapshot is saved per affected post before any apply.';
		// v0.61: never let hard-violation items slip through a bulk approve unannounced.
		var hardCount = ids.filter(function (id) {
			var row = document.querySelector('.cc-pending-item[data-pending-id="' + id + '"]');
			return row && row.dataset.hard === '1';
		}).length;
		if (hardCount > 0 && action === 'bulk_approve') {
			msg += ' WARNING: ' + hardCount + ' of these ' + (hardCount === 1 ? 'has' : 'have') + ' HARD lint violations (banned phrases / em dashes / AI tells) — approving overrides them.';
		}
		ccConfirm(msg).then(function (ok) {
			if (!ok) return;
			var form        = document.getElementById('cc-bulk-form');
			var actionInput = document.getElementById('cc-bulk-form-action');
			var idsBox      = document.getElementById('cc-bulk-form-ids');
			actionInput.value = action;
			idsBox.innerHTML = '';
			ids.forEach(function (val) {
				var inp = document.createElement('input');
				inp.type  = 'hidden';
				inp.name  = 'cc_pending_ids[]';
				inp.value = val;
				idsBox.appendChild(inp);
			});
			form.submit();
		});
	}

	document.querySelectorAll('.cc-bulk-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var action  = this.dataset.bulkAction;
			var pane    = this.closest('.cc-bulk-toolbar').dataset.paneBulk;
			var ids     = Array.prototype.map.call(
				document.querySelectorAll('.cc-row-check[data-pane="' + pane + '"]:checked'),
				function (c) { return c.value; }
			);
			submitBulk(action, ids);
		});
	});

	// Filter-aware combo buttons act on the visible (non-filter-hidden) item set directly,
	// no checkbox selection required.
	document.querySelectorAll('.cc-bulk-filtered-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var action = this.dataset.bulkAction;
			var pane   = this.dataset.filteredPane;
			var ids    = Array.prototype.map.call(
				document.querySelectorAll('.cc-tab-pane[data-pane="' + pane + '"] .cc-pending-item:not(.cc-filter-hidden) .cc-row-check'),
				function (c) { return c.value; }
			);
			submitBulk(action, ids);
		});
	});

	document.querySelectorAll('.cc-group-bulk').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var action = this.dataset.bulkAction;
			var grp    = this.dataset.group;
			var ids    = Array.prototype.map.call(
				document.querySelectorAll('.cc-row-check[data-group="' + grp + '"]'),
				function (c) { return c.value; }
			);
			submitBulk(action, ids);
		});
	});

	// ---- Lazy diff loading ----
	// At page load every row's diff is just a skeleton placeholder. The first
	// time a group expands (or the first time any item in the group becomes
	// visible via filter), we fetch the rendered diff HTML for each item and
	// inject it. Big perf win once the inbox has 50+ Elementor-heavy items.
	var ccDiffNonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
	var ccDiffBase  = '<?php echo esc_js( rest_url( 'cc-assistant/v1/pending/' ) ); ?>';

	function loadDiffForItem(diffEl) {
		if (!diffEl || diffEl.dataset.ccDiffLoaded === '1') return;
		var id = diffEl.dataset.ccDiffPendingId;
		if (!id) return;
		diffEl.dataset.ccDiffLoaded = '1'; // mark in-flight so we do not double-fetch
		fetch(ccDiffBase + encodeURIComponent(id) + '/diff', {
			headers: { 'X-WP-Nonce': ccDiffNonce, 'Accept': 'application/json' },
			credentials: 'same-origin'
		})
		.then(function (r) { return r.ok ? r.json() : null; })
		.then(function (payload) {
			if (payload && payload.data && typeof payload.data.html === 'string') {
				diffEl.innerHTML = payload.data.html;
			} else {
				diffEl.dataset.ccDiffLoaded = '0';
				diffEl.innerHTML = '<em class="cc-diff-empty"><?php echo esc_js( __( 'Could not load diff. Reload the page to retry.', 'cc-assistant' ) ); ?></em>';
			}
		})
		.catch(function () {
			diffEl.dataset.ccDiffLoaded = '0';
			diffEl.innerHTML = '<em class="cc-diff-empty"><?php echo esc_js( __( 'Network error loading diff.', 'cc-assistant' ) ); ?></em>';
		});
	}

	function loadDiffsInGroup(group) {
		group.querySelectorAll('.cc-human-diff[data-cc-diff-pending-id]').forEach(loadDiffForItem);
	}

	// Rendered-preview modal: when the operator clicks "Preview rendered HTML"
	// on a post_content_update card, fetch /pending/{id}/preview and inject
	// the wpautop'd / shortcode-expanded body into a centered overlay so they
	// can see what the post will actually look like after approval. Modal is
	// theme-agnostic (no external chrome injected) — just the body content.
	function ccOpenPreviewModal(pendingId, btn) {
		btn.disabled = true;
		btn.classList.add('cc-preview-loading');
		fetch(ccDiffBase + encodeURIComponent(pendingId) + '/preview', {
			headers: { 'X-WP-Nonce': ccDiffNonce, 'Accept': 'application/json' },
			credentials: 'same-origin'
		})
		.then(function (r) { return r.ok ? r.json() : null; })
		.then(function (payload) {
			btn.disabled = false;
			btn.classList.remove('cc-preview-loading');
			if (!payload || !payload.data || !payload.data.previewable) {
				alert('<?php echo esc_js( __( 'Preview not available for this change type.', 'cc-assistant' ) ); ?>');
				return;
			}
			ccShowPreviewOverlay(payload.data);
		})
		.catch(function () {
			btn.disabled = false;
			btn.classList.remove('cc-preview-loading');
			alert('<?php echo esc_js( __( 'Network error loading preview.', 'cc-assistant' ) ); ?>');
		});
	}

	function ccShowPreviewOverlay(data) {
		var overlay = document.createElement('div');
		overlay.className = 'cc-preview-overlay';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', '<?php echo esc_js( __( 'Rendered preview', 'cc-assistant' ) ); ?>');
		overlay.innerHTML =
			'<div class="cc-preview-modal">' +
				'<div class="cc-preview-header">' +
					'<div class="cc-preview-title"></div>' +
					'<button type="button" class="cc-preview-close" aria-label="<?php echo esc_attr( __( 'Close preview', 'cc-assistant' ) ); ?>">&times;</button>' +
				'</div>' +
				'<div class="cc-preview-body"></div>' +
				'<div class="cc-preview-footer">' +
					'<span class="cc-preview-note"><?php echo esc_js( __( 'This is the proposed post body run through wpautop + shortcodes. The site theme is not applied.', 'cc-assistant' ) ); ?></span>' +
				'</div>' +
			'</div>';
		document.body.appendChild(overlay);
		overlay.querySelector('.cc-preview-title').textContent = data.post_title || ('Post ' + (data.post_id || ''));
		overlay.querySelector('.cc-preview-body').innerHTML = data.rendered;

		var close = function () {
			overlay.remove();
			document.removeEventListener('keydown', escClose);
		};
		var escClose = function (e) { if (e.key === 'Escape') close(); };
		overlay.querySelector('.cc-preview-close').addEventListener('click', close);
		overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
		document.addEventListener('keydown', escClose);
	}

	document.querySelectorAll('.cc-preview-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = this.dataset.ccPreviewPendingId;
			if (id) ccOpenPreviewModal(id, this);
		});
	});

	document.querySelectorAll('.cc-group-toggle').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var group = this.closest('.cc-post-group');
			var body  = group.querySelector('.cc-group-body');
			var open  = !body.hasAttribute('hidden');
			if (open) {
				body.setAttribute('hidden', '');
				this.setAttribute('aria-expanded', 'false');
				this.querySelector('.dashicons').classList.remove('dashicons-arrow-down');
				this.querySelector('.dashicons').classList.add('dashicons-arrow-right');
			} else {
				body.removeAttribute('hidden');
				this.setAttribute('aria-expanded', 'true');
				this.querySelector('.dashicons').classList.remove('dashicons-arrow-right');
				this.querySelector('.dashicons').classList.add('dashicons-arrow-down');
				loadDiffsInGroup(group);
			}
		});
	});

	// If a group is already open at page load (e.g. after a filter auto-expand
	// or after a successful bulk action keeps state), load its diffs now.
	document.querySelectorAll('.cc-post-group').forEach(function (g) {
		var body = g.querySelector('.cc-group-body');
		if (body && !body.hasAttribute('hidden')) {
			loadDiffsInGroup(g);
		}
	});

	updateBulkState();
	updateGroupCheckboxes();

	// ---- Keyboard shortcuts ----
	function activeTabPane() {
		var active = document.querySelector('.cc-tab-pane:not([hidden])');
		return active || document.querySelector('.cc-tab-pane');
	}
	function visibleItems() {
		var pane = activeTabPane();
		if (!pane) return [];
		return Array.prototype.filter.call(
			pane.querySelectorAll('.cc-pending-item'),
			function (el) {
				if (el.classList.contains('cc-filter-hidden')) return false;
				var body = el.closest('.cc-group-body');
				if (body && body.hasAttribute('hidden')) return false;
				var grp  = el.closest('.cc-post-group');
				if (grp && grp.classList.contains('cc-filter-hidden')) return false;
				return true;
			}
		);
	}
	function focusedIndex(items) {
		for (var i = 0; i < items.length; i++) {
			if (items[i].classList.contains('cc-kb-focused')) return i;
		}
		return -1;
	}
	function focusItem(idx) {
		var items = visibleItems();
		if (!items.length) return;
		idx = Math.max(0, Math.min(items.length - 1, idx));
		items.forEach(function (it, i) { it.classList.toggle('cc-kb-focused', i === idx); });
		items[idx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	}
	function moveFocus(delta) {
		var items = visibleItems();
		if (!items.length) return;
		var cur = focusedIndex(items);
		focusItem(cur < 0 ? 0 : cur + delta);
	}
	function isTypingTarget(t) {
		if (!t) return false;
		var tag = (t.tagName || '').toLowerCase();
		if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
		if (t.isContentEditable) return true;
		return false;
	}
	document.addEventListener('keydown', function (e) {
		// Esc handles modal close + filter clear regardless of focus.
		if (e.key === 'Escape') {
			if (confirmModal && !confirmModal.hasAttribute('hidden')) { closeConfirm(false); return; }
			if (kbModal && !kbModal.hasAttribute('hidden')) { closeKb(); return; }
			var pane = activeTabPane() && activeTabPane().dataset.pane;
			if (pane) {
				var s = paneState(pane);
				if (s.term || activeIssueList(pane).length > 0) {
					clearFilters(pane);
					var fi2 = document.querySelector('.cc-filter-input[data-filter-pane="' + pane + '"]');
					if (fi2) fi2.blur();
					return;
				}
			}
		}
		if (e.metaKey || e.ctrlKey || e.altKey) return;
		if (isTypingTarget(e.target)) return;

		switch (e.key) {
			case '/':
				e.preventDefault();
				var f = activeTabPane() && activeTabPane().querySelector('.cc-filter-input');
				if (f) f.focus();
				break;
			case '?':
				e.preventDefault();
				openKb();
				break;
			case 'j': e.preventDefault(); moveFocus(+1); break;
			case 'k': e.preventDefault(); moveFocus(-1); break;
			case 'x': {
				var items = visibleItems();
				var idx = focusedIndex(items);
				if (idx < 0) return;
				var cb = items[idx].querySelector('.cc-row-check');
				if (cb) {
					e.preventDefault();
					cb.checked = !cb.checked;
					cb.dispatchEvent(new Event('change', { bubbles: true }));
				}
				break;
			}
			case 'e': {
				var items2 = visibleItems();
				var idx2   = focusedIndex(items2);
				var grp;
				if (idx2 >= 0) {
					grp = items2[idx2].closest('.cc-post-group');
				} else {
					grp = activeTabPane() && activeTabPane().querySelector('.cc-post-group');
				}
				if (grp) {
					var t = grp.querySelector('.cc-group-toggle');
					if (t) { e.preventDefault(); t.click(); }
				}
				break;
			}
			case 'a': {
				var pane = activeTabPane();
				var btn  = pane && pane.querySelector('.cc-bulk-btn[data-bulk-action="bulk_approve"]:not(:disabled)');
				if (btn) { e.preventDefault(); btn.click(); }
				break;
			}
			case 'r': {
				var pane2 = activeTabPane();
				var btn2  = pane2 && pane2.querySelector('.cc-bulk-btn[data-bulk-action="bulk_reject"]:not(:disabled)');
				if (btn2) { e.preventDefault(); btn2.click(); }
				break;
			}
		}
	});

	// Click on an item also focuses it for keyboard chains.
	document.querySelectorAll('.cc-pending-item').forEach(function (el) {
		el.addEventListener('click', function (e) {
			if (isTypingTarget(e.target)) return;
			document.querySelectorAll('.cc-pending-item.cc-kb-focused').forEach(function (n) { n.classList.remove('cc-kb-focused'); });
			el.classList.add('cc-kb-focused');
		});
	});
})();
</script>

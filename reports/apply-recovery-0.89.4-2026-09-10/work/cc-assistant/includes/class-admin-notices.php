<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notices for CC Assistant — content-workflow alerts only.
 *
 * Used by:
 *   - Edit-outcome verdict landing (when a recent edit's GSC verdict
 *     transitions from pending to positive/negative/flat).
 *   - Site-audit threshold trips (e.g. % of posts failing E-E-A-T crosses
 *     a threshold week-over-week).
 *
 * Notices are stored keyed by stable id so a re-emitting cron doesn't
 * duplicate them. Each notice has a 30-day TTL so dismissed-but-not-
 * cleared entries don't accrue. Renders only on CC Assistant admin
 * screens and the dashboard, to avoid polluting unrelated wp-admin pages.
 */
class CC_Assistant_Admin_Notices {

	const OPT_KEY        = 'cc_assistant_admin_notices';
	const SNOOZE_OPT_KEY = 'cc_assistant_admin_notice_snoozes';
	const TTL_SECONDS    = 30 * DAY_IN_SECONDS;
	const SNOOZE_SECONDS = 7 * DAY_IN_SECONDS;

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_cc_assistant_dismiss_notice', array( __CLASS__, 'ajax_dismiss' ) );
		// One-shot migration. Called DIRECTLY (not via admin_init) because
		// init() runs on the current_screen hook which fires AFTER admin_init —
		// registering the migration on admin_init at this point would attach
		// too late and never fire. The migration self-gates on a flag option
		// so calling it on every CC Assistant admin page load is cheap (one
		// extra get_option after the flag is set).
		self::maybe_migrate_verdict_notices();
	}

	/**
	 * Add or update a notice. Idempotent on $id — calling twice with the
	 * same id replaces in place (so cron handlers can re-add safely).
	 *
	 * v0.20.2: respects snoozes — if the operator dismissed this notice id
	 * within the snooze window, refuse to re-add. Prevents the "dismiss
	 * and it comes back tomorrow" UX failure mode the operator hit with the
	 * site_audit_eeat notice (auto-regenerated daily by the site audit cron).
	 *
	 * $type: 'info' | 'success' | 'warning' | 'error'
	 * $link: optional ['url' => '', 'label' => '']
	 */
	public static function add( $id, $type, $message, $link = null ) {
		$id = sanitize_text_field( (string) $id );
		if ( '' === $id ) {
			return false;
		}
		if ( self::is_snoozed( $id ) ) {
			return false;
		}
		$type    = in_array( $type, array( 'info', 'success', 'warning', 'error' ), true ) ? $type : 'info';
		$notices = (array) get_option( self::OPT_KEY, array() );
		$notices[ $id ] = array(
			'id'         => $id,
			'type'       => $type,
			'message'    => (string) $message,
			'link'       => is_array( $link ) ? $link : null,
			'created_at' => time(),
		);
		// Compact on this write — we're already paying for the option update.
		$notices = self::compact_on_write( $notices );
		update_option( self::OPT_KEY, $notices, false );
		return true;
	}

	public static function dismiss( $id ) {
		$id      = sanitize_text_field( (string) $id );
		$notices = (array) get_option( self::OPT_KEY, array() );
		$removed = false;
		if ( isset( $notices[ $id ] ) ) {
			unset( $notices[ $id ] );
			$notices = self::compact_on_write( $notices );
			update_option( self::OPT_KEY, $notices, false );
			$removed = true;
		}
		// Always set the snooze marker — even if the notice was already gone
		// when this dismiss landed (e.g. user clicked dismiss twice). Stops
		// re-adds for SNOOZE_SECONDS regardless.
		self::set_snooze( $id );
		return $removed;
	}

	/**
	 * Check whether $id is currently snoozed (dismissed within the snooze
	 * window). Returns true when an add() call should be refused.
	 */
	public static function is_snoozed( $id ) {
		$snoozes = (array) get_option( self::SNOOZE_OPT_KEY, array() );
		if ( ! isset( $snoozes[ $id ] ) ) {
			return false;
		}
		return (int) $snoozes[ $id ] > time();
	}

	private static function set_snooze( $id ) {
		$snoozes = (array) get_option( self::SNOOZE_OPT_KEY, array() );
		$snoozes[ $id ] = time() + self::SNOOZE_SECONDS;
		// Prune expired snoozes while we're paying for the write.
		$now = time();
		foreach ( $snoozes as $sid => $ts ) {
			if ( (int) $ts < $now ) {
				unset( $snoozes[ $sid ] );
			}
		}
		update_option( self::SNOOZE_OPT_KEY, $snoozes, false );
	}

	/**
	 * One-shot: delete legacy per-edit `verdict_N` notices. v0.20.2 switched
	 * verdict_notifier to per-post aggregation (`verdict_post_N`) — the old
	 * keys would otherwise stay until their 30-day TTL. Runs once per upgrade.
	 */
	public static function maybe_migrate_verdict_notices() {
		$flag = 'cc_assistant_verdict_notice_migration_v202';
		if ( get_option( $flag ) ) {
			return;
		}
		$notices = (array) get_option( self::OPT_KEY, array() );
		$changed = false;
		foreach ( array_keys( $notices ) as $id ) {
			// Drop only old per-edit keys (verdict_<digits>), not the new
			// per-post keys (verdict_post_<digits>).
			if ( preg_match( '/^verdict_\d+$/', (string) $id ) ) {
				unset( $notices[ $id ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( self::OPT_KEY, $notices, false );
		}
		update_option( $flag, time(), false );
	}

	public static function all_raw() {
		$rows = (array) get_option( self::OPT_KEY, array() );
		// Filter expired entries in memory only — never write back from a read
		// path. Pruning happens on add()/dismiss(), which already pay a write.
		// Avoids an option_update on every admin page render when stale entries
		// exist.
		if ( empty( $rows ) ) {
			return $rows;
		}
		$cutoff = time() - self::TTL_SECONDS;
		$keep   = array();
		foreach ( $rows as $id => $row ) {
			if ( ! empty( $row['created_at'] ) && (int) $row['created_at'] >= $cutoff ) {
				$keep[ $id ] = $row;
			}
		}
		return $keep;
	}

	/**
	 * Compact the option on writes — called from add()/dismiss() to drop
	 * expired entries at the same time we're paying a write.
	 */
	private static function compact_on_write( $rows ) {
		$cutoff = time() - self::TTL_SECONDS;
		$keep   = array();
		foreach ( $rows as $id => $row ) {
			if ( ! empty( $row['created_at'] ) && (int) $row['created_at'] >= $cutoff ) {
				$keep[ $id ] = $row;
			}
		}
		return $keep;
	}

	/**
	 * Map a notice $type to an emergency level. error => critical (act now),
	 * warning => attention, info/success => info.
	 */
	private static function level_for_type( $type ) {
		if ( 'error' === $type ) {
			return 'critical';
		}
		if ( 'warning' === $type ) {
			return 'warning';
		}
		return 'info';
	}

	/**
	 * Render ONE collapsed notification center instead of N stacked banners.
	 * Shows a total count + per-emergency-level breakdown; click to expand a
	 * panel that groups notices by level (Critical / Needs attention / Info),
	 * each individually dismissible. Collapsed by default so it never spams.
	 */
	public static function render() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// Only show on CC Assistant screens + WP dashboard. Keeps the rest of
		// wp-admin clean — content-workflow alerts belong where the workflow lives.
		$allow = false;
		if ( $screen ) {
			if ( false !== strpos( (string) $screen->id, 'cc-assistant' ) ) {
				$allow = true;
			}
			if ( 'dashboard' === $screen->id ) {
				$allow = true;
			}
		}
		if ( ! $allow ) {
			return;
		}

		$notices = self::all_raw();
		if ( empty( $notices ) ) {
			return;
		}

		// Newest first.
		uasort(
			$notices,
			function ( $a, $b ) {
				return ( (int) ( $b['created_at'] ?? 0 ) ) <=> ( (int) ( $a['created_at'] ?? 0 ) );
			}
		);

		$levels = array(
			'critical' => array( 'label' => __( 'Critical', 'cc-assistant' ), 'accent' => '#d63638', 'items' => array() ),
			'warning'  => array( 'label' => __( 'Needs attention', 'cc-assistant' ), 'accent' => '#dba617', 'items' => array() ),
			'info'     => array( 'label' => __( 'Info', 'cc-assistant' ), 'accent' => '#2271b1', 'items' => array() ),
		);
		foreach ( $notices as $n ) {
			$lvl = self::level_for_type( $n['type'] ?? 'info' );
			$levels[ $lvl ]['items'][] = $n;
		}
		$total      = count( $notices );
		$cc         = count( $levels['critical']['items'] );
		$cw         = count( $levels['warning']['items'] );
		$ci         = count( $levels['info']['items'] );
		$top_accent = $cc ? $levels['critical']['accent'] : ( $cw ? $levels['warning']['accent'] : $levels['info']['accent'] );
		$nonce      = wp_create_nonce( 'cc_assistant_dismiss_notice' );
		?>
		<div class="notice cc-assistant-noticecenter" style="border-left-color:<?php echo esc_attr( $top_accent ); ?>;padding:10px 12px;">
			<div class="cc-nc-summary" style="display:flex;align-items:center;gap:10px;cursor:pointer;flex-wrap:wrap;">
				<span class="dashicons dashicons-bell" style="color:<?php echo esc_attr( $top_accent ); ?>;"></span>
				<strong>CC Assistant</strong>
				<span class="cc-nc-total" style="background:<?php echo esc_attr( $top_accent ); ?>;color:#fff;border-radius:10px;padding:1px 9px;font-size:12px;font-weight:600;"><?php echo (int) $total; ?></span>
				<span class="cc-nc-breakdown" style="font-size:12px;color:#646970;display:flex;gap:12px;">
					<?php if ( $cc ) : ?><span style="color:#d63638;">&#9679; <?php echo (int) $cc; ?> critical</span><?php endif; ?>
					<?php if ( $cw ) : ?><span style="color:#bd8600;">&#9679; <?php echo (int) $cw; ?> attention</span><?php endif; ?>
					<?php if ( $ci ) : ?><span style="color:#2271b1;">&#9679; <?php echo (int) $ci; ?> info</span><?php endif; ?>
				</span>
				<button type="button" class="button button-small cc-nc-toggle" style="margin-left:auto;"><?php esc_html_e( 'View', 'cc-assistant' ); ?></button>
			</div>
			<div class="cc-nc-panel" style="display:none;margin-top:12px;">
				<?php
				foreach ( array( 'critical', 'warning', 'info' ) as $lvl ) :
					$bucket = $levels[ $lvl ];
					if ( empty( $bucket['items'] ) ) {
						continue;
					}
					?>
					<div class="cc-nc-group" data-level="<?php echo esc_attr( $lvl ); ?>" style="margin-bottom:12px;">
						<div style="font-weight:600;color:<?php echo esc_attr( $bucket['accent'] ); ?>;text-transform:uppercase;font-size:11px;letter-spacing:.5px;margin-bottom:6px;">
							<?php echo esc_html( $bucket['label'] ); ?> (<span class="cc-nc-group-count"><?php echo count( $bucket['items'] ); ?></span>)
						</div>
						<?php foreach ( $bucket['items'] as $n ) : ?>
							<div class="cc-nc-item" data-notice-id="<?php echo esc_attr( $n['id'] ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" style="display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border-left:3px solid <?php echo esc_attr( $bucket['accent'] ); ?>;background:#fff;margin-bottom:5px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
								<span style="flex:1;line-height:1.5;"><?php echo wp_kses_post( $n['message'] ); ?>
									<?php if ( ! empty( $n['link']['url'] ) ) : ?>
										<a href="<?php echo esc_url( $n['link']['url'] ); ?>" class="button button-small" style="margin-left:6px;"><?php echo esc_html( $n['link']['label'] ?? __( 'Open', 'cc-assistant' ) ); ?></a>
									<?php endif; ?>
								</span>
								<button type="button" class="button-link cc-nc-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'cc-assistant' ); ?>" title="<?php esc_attr_e( 'Dismiss', 'cc-assistant' ); ?>" style="color:#646970;font-size:18px;line-height:1;text-decoration:none;">&times;</button>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<script>
		(function () {
			var center = document.querySelector('.cc-assistant-noticecenter');
			if (!center) return;
			var panel = center.querySelector('.cc-nc-panel');
			var toggle = center.querySelector('.cc-nc-toggle');
			var summary = center.querySelector('.cc-nc-summary');
			function setOpen(open) {
				panel.style.display = open ? 'block' : 'none';
				toggle.textContent = open ? '<?php echo esc_js( __( 'Hide', 'cc-assistant' ) ); ?>' : '<?php echo esc_js( __( 'View', 'cc-assistant' ) ); ?>';
			}
			summary.addEventListener('click', function (e) {
				if (e.target.closest('a')) return; // let links work
				setOpen(panel.style.display === 'none');
			});
			center.querySelectorAll('.cc-nc-dismiss').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var item = btn.closest('.cc-nc-item');
					if (!item) return;
					var id = item.getAttribute('data-notice-id');
					var nonce = item.getAttribute('data-nonce');
					var group = item.closest('.cc-nc-group');
					item.parentNode.removeChild(item);
					// Decrement counts.
					var totalEl = center.querySelector('.cc-nc-total');
					var total = Math.max(0, parseInt(totalEl.textContent, 10) - 1);
					totalEl.textContent = total;
					if (group) {
						var gc = group.querySelector('.cc-nc-group-count');
						var left = group.querySelectorAll('.cc-nc-item').length;
						if (gc) gc.textContent = left;
						if (left === 0) group.parentNode.removeChild(group);
					}
					if (total === 0) center.parentNode.removeChild(center);
					if (id) {
						var fd = new FormData();
						fd.append('action', 'cc_assistant_dismiss_notice');
						fd.append('id', id);
						fd.append('_ajax_nonce', nonce);
						fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd });
					}
				});
			});
		}());
		</script>
		<?php
	}

	public static function ajax_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'cc_assistant_dismiss_notice' );
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		self::dismiss( $id );
		wp_send_json_success();
	}
}

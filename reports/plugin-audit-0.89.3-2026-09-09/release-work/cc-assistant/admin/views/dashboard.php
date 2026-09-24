<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$identity      = CC_Assistant_Site_Identity::whoami();
$pending_count = CC_Assistant_Pending_Changes::count_pending();
$heartbeat     = CC_Assistant_Site_Identity::get_last_heartbeat();
$is_connected  = $heartbeat && ( time() - $heartbeat['timestamp'] < 300 );
$onboarded     = (bool) get_option( 'cc_assistant_onboarding_complete' );

require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
require_once CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
require_once CC_ASSISTANT_DIR . 'includes/class-llm-tracker.php';
require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';

// Weekly advisor: cached server-side, so this just reads.
$advisor = CC_Assistant_Weekly_Advisor::priorities();

$gsc_status = CC_Assistant_GSC::status();
$gsc_ready  = $gsc_status['connected'] && (int) $gsc_status['rows_cached'] > 0;

// Insights are computed in the background (cron) and stored as an option blob.
// Dashboard never runs the heavy aggregates on render — it just reads.
$insights         = $gsc_ready ? CC_Assistant_GSC::get_insights_blob() : null;
$insights_pending = false;
if ( $gsc_ready && null === $insights ) {
	// First load (or stale data after table grew). Schedule a background
	// compute and let the user see a skeleton this render.
	CC_Assistant_GSC::schedule_recompute( 5 );
	$insights_pending = true;
	$insights = array( 'opportunities' => array(), 'trends' => array(), 'intent' => array( 'buckets' => array() ) );
}

$links_cache_key = 'cc_assistant_dashboard_links';
$link_summary    = get_transient( $links_cache_key );
if ( false === $link_summary ) {
	$link_summary = CC_Assistant_Internal_Links::summary();
	set_transient( $links_cache_key, $link_summary, 30 * MINUTE_IN_SECONDS );
}
$top_orphans     = CC_Assistant_Internal_Links::find_orphans( array( 'limit' => 5 ) );

$llm_summary     = CC_Assistant_LLM_Tracker::is_enabled() ? CC_Assistant_LLM_Tracker::summary( 7 ) : null;

// recent_with_status() and pending_outcomes() return one row per EDIT, which
// means a single page edited 6 times shows up 6 times — drowning out other
// posts and burying older pending measurements off the visible window. The
// _by_post() variants group by post_id, surface the latest_edit as the
// headline, and keep edit_count + verdict_breakdown so the badge still tells
// the user "this post has 6 edits applied, 4 measured improved, 2 still
// measuring." Sorted so oldest_pending.ready_in_days ASC surfaces the row
// about to clear first, regardless of how recent the latest edit is.
$recent_edits = get_transient( 'cc_assistant_recent_edits_by_post_v2' );
if ( false === $recent_edits ) {
	$recent_edits = CC_Assistant_Edit_Outcomes::recent_with_status_by_post( 12 );
	set_transient( 'cc_assistant_recent_edits_by_post_v2', $recent_edits, 30 * MINUTE_IN_SECONDS );
}

$pending_outcomes = get_transient( 'cc_assistant_pending_outcomes_by_post_v2' );
if ( false === $pending_outcomes ) {
	$pending_outcomes = CC_Assistant_Edit_Outcomes::pending_outcomes_by_post();
	set_transient( 'cc_assistant_pending_outcomes_by_post_v2', $pending_outcomes, 30 * MINUTE_IN_SECONDS );
}

/**
 * Count pending_changes rows by status in a recent window. Used for the
 * "this week" rollup card. One query, grouped, so it stays cheap as the
 * table grows.
 */
if ( ! function_exists( 'cc_dashboard_counts_since' ) ) {
	function cc_dashboard_counts_since( $days = 7 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_pending_changes';
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - ( (int) $days * DAY_IN_SECONDS ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS c FROM $table WHERE created_at >= %s OR (reviewed_at IS NOT NULL AND reviewed_at >= %s) GROUP BY status",
				$cutoff,
				$cutoff
			)
		);
		$out = array(
			'pending'     => 0,
			'approved'    => 0,
			'rejected'    => 0,
			'rolled_back' => 0,
		);
		foreach ( $rows as $r ) {
			if ( isset( $out[ $r->status ] ) ) {
				$out[ $r->status ] = (int) $r->c;
			}
		}
		return $out;
	}
}

$week_counts = cc_dashboard_counts_since( 7 );
$week_total  = array_sum( $week_counts );

// 30-day outcome rollup against success_metrics targets. Cached for 30 min
// because each underlying outcome computation is itself cached, but the
// rollup loop costs us at least N transient reads per render.
$outcomes_rollup = get_transient( 'cc_assistant_outcomes_rollup_30d' );
if ( false === $outcomes_rollup ) {
	$outcomes_rollup = CC_Assistant_Edit_Outcomes::rollup_against_targets( 30, 50 );
	set_transient( 'cc_assistant_outcomes_rollup_30d', $outcomes_rollup, 30 * MINUTE_IN_SECONDS );
}
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.cc-copy-prompt').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var prompt = btn.getAttribute('data-prompt');
			if (!prompt) return;
			var done = function () {
				var prev = btn.textContent;
				btn.textContent = '<?php echo esc_js( __( 'Copied! Paste in Claude Code', 'cc-assistant' ) ); ?>';
				btn.classList.add('updated');
				setTimeout(function () { btn.textContent = prev; btn.classList.remove('updated'); }, 2200);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(prompt).then(done, function () {
					var ta = document.createElement('textarea');
					ta.value = prompt; document.body.appendChild(ta); ta.select();
					try { document.execCommand('copy'); done(); } catch (err) {}
					document.body.removeChild(ta);
				});
			} else {
				var ta = document.createElement('textarea');
				ta.value = prompt; document.body.appendChild(ta); ta.select();
				try { document.execCommand('copy'); done(); } catch (err) {}
				document.body.removeChild(ta);
			}
		});
	});
});
</script>
<div class="wrap cc-assistant cc-dashboard">
	<h1><?php esc_html_e( 'CC Assistant', 'cc-assistant' ); ?></h1>
	<p class="cc-tagline"><?php esc_html_e( 'Your AI co-pilot for content. Safe by default. You stay in charge.', 'cc-assistant' ); ?></p>

	<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
		<div class="notice notice-warning cc-cron-warning" style="margin: 12px 0;">
			<p>
				<strong><?php esc_html_e( 'WP-Cron is disabled on this site.', 'cc-assistant' ); ?></strong>
				<?php esc_html_e( 'GSC sync, link-graph rebuild, verdict notifier, and site audit will not fire automatically. Set up a system cron pointing at wp-cron.php every 5 minutes, or rely on a managed-cron plugin.', 'cc-assistant' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=health' ) ); ?>"><?php esc_html_e( 'Open Health tab', 'cc-assistant' ); ?> &rarr;</a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $onboarded ) : ?>
		<div class="cc-banner-onboarding">
			<div class="cc-banner-icon"><span class="dashicons dashicons-admin-tools"></span></div>
			<div class="cc-banner-text">
				<strong><?php esc_html_e( 'New here? Run the 4-step setup.', 'cc-assistant' ); ?></strong>
				<span><?php esc_html_e( 'Each step auto-detects when it is done. Takes about a minute.', 'cc-assistant' ); ?></span>
			</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-onboarding' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Get started', 'cc-assistant' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<div class="cc-hero <?php echo $is_connected ? 'cc-hero-connected' : 'cc-hero-idle'; ?>">
		<div class="cc-hero-status">
			<div class="cc-hero-indicator">
				<span class="cc-pulse"></span>
				<span class="cc-hero-label">
					<?php echo $is_connected ? esc_html__( 'Connected', 'cc-assistant' ) : esc_html__( 'Idle', 'cc-assistant' ); ?>
				</span>
			</div>
			<h2 class="cc-hero-site"><?php echo esc_html( $identity['site_name'] ); ?></h2>
			<p class="cc-hero-meta">
				<code><?php echo esc_html( $identity['site_url'] ); ?></code>
				<span class="cc-dot"></span>
				<span><?php esc_html_e( 'Fingerprint', 'cc-assistant' ); ?> <code><?php echo esc_html( $identity['fingerprint'] ); ?></code></span>
			</p>
			<?php if ( $is_connected ) : ?>
				<p class="cc-hero-time">
					<span class="dashicons dashicons-clock"></span>
					<?php
					printf(
						/* translators: %d: seconds */
						esc_html__( 'Last contact %d seconds ago', 'cc-assistant' ),
						(int) ( time() - $heartbeat['timestamp'] )
					);
					?>
				</p>
			<?php else : ?>
				<p class="cc-hero-time"><?php esc_html_e( 'Open Claude Code in this folder to connect.', 'cc-assistant' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="cc-hero-action">
			<?php if ( $pending_count > 0 ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-pending' ) ); ?>" class="button button-primary button-hero">
					<?php
					printf(
						/* translators: %d: pending count */
						esc_html( _n( 'Review %d pending change', 'Review %d pending changes', $pending_count, 'cc-assistant' ) ),
						(int) $pending_count
					);
					?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings' ) ); ?>" class="button button-secondary button-hero">
					<?php esc_html_e( 'Open settings', 'cc-assistant' ); ?>
				</a>
			<?php endif; ?>

			<button type="button" id="cc-flush-cache" class="button button-secondary" title="<?php esc_attr_e( 'Clear plugin caches and reload — use after a graph rebuild or batch apply if dashboard numbers look stale.', 'cc-assistant' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cc_assistant_flush_cache' ) ); ?>" style="margin-top:8px;">
				<span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Refresh data', 'cc-assistant' ); ?>
			</button>
		</div>
	</div>

	<script>
	(function () {
		var btn = document.getElementById('cc-flush-cache');
		if (!btn) return;
		btn.addEventListener('click', function () {
			btn.disabled = true;
			var label = btn.innerHTML;
			btn.innerHTML = '<span class="dashicons dashicons-update" style="vertical-align:middle;animation:cc-spin 1s linear infinite;"></span> <?php echo esc_js( __( 'Refreshing...', 'cc-assistant' ) ); ?>';
			var body = new URLSearchParams();
			body.append('action', 'cc_assistant_flush_cache');
			body.append('_ajax_nonce', btn.dataset.nonce);
			fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json && json.success) {
						window.location.reload();
					} else {
						btn.disabled = false;
						btn.innerHTML = label;
						alert((json && json.data && json.data.message) || 'Refresh failed.');
					}
				})
				.catch(function () {
					btn.disabled = false;
					btn.innerHTML = label;
					alert('<?php echo esc_js( __( 'Refresh failed. Check the browser console.', 'cc-assistant' ) ); ?>');
				});
		});
	})();
	</script>
	<style>@keyframes cc-spin { to { transform: rotate(360deg); } }</style>

	<?php if ( ! $identity['elementor_active'] ) : ?>
		<div class="cc-elementor-warn">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<strong><?php esc_html_e( 'Elementor is not active on this site.', 'cc-assistant' ); ?></strong>
				<span class="description"><?php esc_html_e( 'CC Assistant\'s widget-level edits require Elementor. Activate it to unlock get_elementor_widgets and draft_update_elementor_widget.', 'cc-assistant' ); ?></span>
			</div>
		</div>
	<?php endif; ?>

	<?php
	// Weekly advisor card: leads the dashboard when there is a real signal.
	// 3 states: computing (cache miss, async job scheduled), empty (ran clean),
	// or has-items. Placeholder/empty render keeps the dashboard responsive.
	$advisor_data_ready = $gsc_ready && ! $insights_pending;
	if ( ! empty( $advisor['computing'] ) ) : ?>
		<div class="cc-card cc-card-wide cc-advisor-card" style="background:#fff8e1;border-color:#ffe082;">
			<span class="cc-status cc-status-pending"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Crunching this week\'s priorities', 'cc-assistant' ); ?></span>
			<h3 class="cc-headline"><?php esc_html_e( 'Synthesising signals in the background.', 'cc-assistant' ); ?></h3>
			<p class="cc-narrative"><?php esc_html_e( 'The advisor is computing top priorities from cannibalization, decay, click depth, clusters, orphans, and CTR. Refresh in a few seconds.', 'cc-assistant' ); ?></p>
		</div>
	<?php elseif ( empty( $advisor['items'] ) && $advisor_data_ready ) : ?>
		<div class="cc-card cc-card-wide cc-advisor-card cc-advisor-empty">
			<span class="cc-status cc-status-good"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'No priorities for this week', 'cc-assistant' ); ?></span>
			<h3 class="cc-headline"><?php esc_html_e( 'Nothing urgent across cannibalization, decay, click depth, clusters, orphans, or CTR.', 'cc-assistant' ); ?></h3>
			<p class="cc-narrative"><?php esc_html_e( 'Your site is in a steady-state. Use the calm to plan new content — try the brief generator with a target keyword.', 'cc-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	if ( ! empty( $advisor['items'] ) ) :
		$cat_meta = array(
			'cannibalization' => array( 'icon' => 'admin-network',  'label' => __( 'Cannibalization', 'cc-assistant' ) ),
			'decay'           => array( 'icon' => 'arrow-down-alt', 'label' => __( 'Decay', 'cc-assistant' ) ),
			'click_depth'     => array( 'icon' => 'admin-site',     'label' => __( 'Click depth', 'cc-assistant' ) ),
			'cluster_health'  => array( 'icon' => 'category',       'label' => __( 'Cluster health', 'cc-assistant' ) ),
			'orphans'         => array( 'icon' => 'admin-links',    'label' => __( 'Orphans', 'cc-assistant' ) ),
			'low_ctr'         => array( 'icon' => 'visibility',     'label' => __( 'CTR miss', 'cc-assistant' ) ),
			'unclustered'     => array( 'icon' => 'screenoptions',  'label' => __( 'Unclustered', 'cc-assistant' ) ),
		);
		$top_count = min( 5, count( $advisor['items'] ) );
		?>
		<div class="cc-card cc-card-wide cc-advisor-card">
			<span class="cc-status cc-status-attention"><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'What to do this week', 'cc-assistant' ); ?></span>
			<h3 class="cc-headline">
				<?php
				printf(
					/* translators: %d: count */
					esc_html( _n( '%d priority worth your attention.', '%d priorities worth your attention.', $top_count, 'cc-assistant' ) ),
					(int) $top_count
				);
				?>
			</h3>
			<p class="cc-narrative"><?php esc_html_e( 'Synthesised from cannibalization, decay, click depth, cluster health, orphans, and CTR data. Pick whichever is closest to your week — dismiss the rest.', 'cc-assistant' ); ?></p>
			<?php
			// Transparency: show how many posts were quietly suppressed from
			// the recommendation set because they had cc_edits applied in
			// the last 14 days. Prevents the "I just fixed this and it still
			// shows up" UX bug — the operator can see the cooldown is
			// actually firing rather than wondering why the dashboard is silent.
			$recent_optimized_count = count( CC_Assistant_Edit_Outcomes::recent_post_ids( 14 ) );
			if ( $recent_optimized_count > 0 ) :
				?>
				<p class="cc-advisor-suppressed description" style="background:#f0fdf4;border-left:3px solid #16a34a;padding:6px 10px;margin:6px 0 12px;font-size:12px;color:#166534;">
					<span class="dashicons dashicons-yes-alt" style="color:#16a34a;"></span>
					<?php
					printf(
						/* translators: %d: number of recently-optimized posts that were suppressed from recommendations */
						esc_html( _n(
							'%d post is in the 14-day cooldown after a recent edit. Recommendations on it are hidden until outcome data settles.',
							'%d posts are in the 14-day cooldown after recent edits. Recommendations on them are hidden until outcome data settles.',
							$recent_optimized_count,
							'cc-assistant'
						) ),
						(int) $recent_optimized_count
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-pending&tab=approved' ) ); ?>"><?php esc_html_e( 'See applied edits', 'cc-assistant' ); ?> &rarr;</a>
				</p>
			<?php endif; ?>
			<ol class="cc-advisor-list">
				<?php foreach ( array_slice( $advisor['items'], 0, 5 ) as $item ) :
					$cat   = isset( $item['category'] ) ? $item['category'] : 'other';
					$meta  = isset( $cat_meta[ $cat ] ) ? $cat_meta[ $cat ] : array( 'icon' => 'info', 'label' => $cat );
					$sev   = isset( $item['severity'] ) ? $item['severity'] : 'medium';
					?>
					<li class="cc-advisor-item cc-sev-<?php echo esc_attr( $sev ); ?>" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
						<div class="cc-advisor-head">
							<span class="cc-advisor-cat"><span class="dashicons dashicons-<?php echo esc_attr( $meta['icon'] ); ?>"></span> <?php echo esc_html( $meta['label'] ); ?></span>
							<span class="cc-advisor-sev cc-advisor-sev-<?php echo esc_attr( $sev ); ?>"><?php echo esc_html( ucfirst( $sev ) ); ?></span>
							<button type="button" class="cc-advisor-dismiss" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Dismiss this priority', 'cc-assistant' ); ?>" aria-label="<?php esc_attr_e( 'Dismiss', 'cc-assistant' ); ?>">&times;</button>
						</div>
						<div class="cc-advisor-body">
							<strong class="cc-advisor-headline"><?php echo esc_html( $item['headline'] ); ?></strong>
							<p class="cc-advisor-reason"><?php echo esc_html( $item['reason'] ); ?></p>
							<p class="cc-advisor-next"><span class="dashicons dashicons-arrow-right-alt2"></span> <?php echo esc_html( $item['next_step'] ); ?></p>
							<div class="cc-advisor-actions">
								<a href="#" class="button button-secondary cc-copy-prompt" data-prompt="<?php echo esc_attr( $item['mcp_prompt'] ); ?>"><?php esc_html_e( 'Copy Claude prompt', 'cc-assistant' ); ?></a>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
			<details class="cc-details">
				<summary><?php esc_html_e( 'How is this list built?', 'cc-assistant' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Each priority is scored from a separate signal source: GSC cannibalization, GSC decay (refresh queue), click-depth BFS, cluster linking density, internal-link orphans, low-CTR pages, and unclustered-post counts. Recomputes hourly. Dismissed items hide for 30 days.', 'cc-assistant' ); ?></p>
			</details>
		</div>
		<script>
		(function () {
			var nonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
			var endpoint = '<?php echo esc_url_raw( rest_url( 'cc-assistant/v1/advisor/dismiss' ) ); ?>';
			document.querySelectorAll('.cc-advisor-dismiss').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var id = btn.getAttribute('data-item-id');
					if (!id) return;
					var li = btn.closest('.cc-advisor-item');
					if (li) li.style.opacity = 0.4;
					fetch(endpoint, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
						body: JSON.stringify({ item_id: id })
					}).then(function () { if (li) li.remove(); });
				});
			});
		}());
		</script>
	<?php endif; ?>

	<?php if ( $gsc_ready && $insights_pending ) : ?>
		<div class="cc-card cc-card-wide cc-info-card" style="background:#fff8e1; border-color:#ffe082;">
			<h2 style="color:#b26a00;"><span class="dashicons dashicons-update" style="color:#b26a00;"></span> <?php esc_html_e( 'Crunching your data in the background', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Search Console just synced fresh data. The dashboard insights are being recomputed in the background. Refresh this page in about a minute.', 'cc-assistant' ); ?></p>
			<p class="description"><?php esc_html_e( 'This only happens after a sync. Future loads read from the cache and are instant.', 'cc-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $gsc_ready && ! $insights_pending && ! empty( $insights ) ) : ?>
		<div class="cc-grid cc-grid-2col">
			<div class="cc-card">
				<?php
				$opps      = $insights['opportunities'];
				$opp_count = count( $opps );
				$top_opp   = ! empty( $opps ) ? $opps[0] : null;
				?>
				<?php if ( ! $top_opp ) : ?>
					<span class="cc-status cc-status-pending"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Waiting on data', 'cc-assistant' ); ?></span>
					<h3 class="cc-headline"><?php esc_html_e( 'No quick wins surfaced yet', 'cc-assistant' ); ?></h3>
					<p class="cc-narrative"><?php esc_html_e( 'Either Search Console is still backfilling, or none of your pages are close enough to page 1 to qualify as a quick win right now. Check back in a day.', 'cc-assistant' ); ?></p>
				<?php else : ?>
					<span class="cc-status cc-status-good"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Quick wins available', 'cc-assistant' ); ?></span>
					<h3 class="cc-headline">
						<?php
						printf(
							/* translators: %d: count */
							esc_html( _n( '%d page is one push away from page 1.', '%d pages are one push away from page 1.', $opp_count, 'cc-assistant' ) ),
							(int) $opp_count
						);
						?>
					</h3>
					<p class="cc-narrative">
						<?php
						printf(
							/* translators: 1: query 2: position 3: impressions */
							esc_html__( 'Your strongest one is ranking for %1$s at position %2$s with %3$s monthly impressions. Pages already in this range typically multiply clicks 5x when pushed into the top 3.', 'cc-assistant' ),
							'<em>' . esc_html( $top_opp['query'] ) . '</em>',
							'<strong>' . esc_html( number_format_i18n( $top_opp['position'], 1 ) ) . '</strong>',
							'<strong>' . esc_html( number_format_i18n( $top_opp['impressions'] ) ) . '</strong>'
						);
						?>
					</p>
					<div class="cc-evidence">
						<strong><?php esc_html_e( 'Top wins to attack:', 'cc-assistant' ); ?></strong>
						<ul>
							<?php foreach ( array_slice( $opps, 0, 3 ) as $row ) : ?>
								<li>
									<em><?php echo esc_html( $row['query'] ); ?></em> &mdash;
									<?php
									printf(
										/* translators: 1: position 2: page path */
										esc_html__( 'pos %1$s, page %2$s', 'cc-assistant' ),
										esc_html( number_format_i18n( $row['position'], 1 ) ),
										'<a href="' . esc_url( $row['page'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $row['page'], PHP_URL_PATH ) ?: $row['page'] ) . '</a>'
									);
									?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<div class="cc-primary-action">
						<a href="#" class="button button-primary cc-copy-prompt" data-prompt="<?php echo esc_attr( sprintf( 'Use the cc-assistant tools. Find the page for the query "%s" and propose a content expansion that strengthens its ranking. Do not write to the live site — queue a pending change instead.', $top_opp['query'] ) ); ?>"><?php esc_html_e( 'Copy Claude prompt', 'cc-assistant' ); ?></a>
						<span class="description"><?php esc_html_e( 'Paste it into Claude Code to start the work.', 'cc-assistant' ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<div class="cc-card">
				<?php
				$anomalies = isset( $insights['trends']['anomalies'] ) ? $insights['trends']['anomalies'] : array();
				$rising    = isset( $insights['trends']['rising'] )    ? $insights['trends']['rising']    : array();
				$new_strk  = isset( $insights['trends']['new_striking'] ) ? $insights['trends']['new_striking'] : array();

				$decayers = array_values( array_filter(
					$anomalies,
					function ( $r ) { return isset( $r['direction'] ) && 'decay' === $r['direction']; }
				) );
				$risers   = array_values( array_filter(
					$anomalies,
					function ( $r ) { return isset( $r['direction'] ) && 'rise' === $r['direction']; }
				) );
				$top_decay = ! empty( $decayers ) ? $decayers[0] : null;
				$top_rise  = ! empty( $risers ) ? $risers[0] : ( ! empty( $rising ) ? $rising[0] : null );

				$decay_pct = 0;
				if ( $top_decay && $top_decay['baseline_mean'] > 0 ) {
					$decay_pct = (int) round( abs( $top_decay['window_mean'] - $top_decay['baseline_mean'] ) * 100 / $top_decay['baseline_mean'] );
				}
				?>

				<?php if ( ! empty( $decayers ) ) : ?>
					<span class="cc-status cc-status-attention"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Needs attention', 'cc-assistant' ); ?></span>
					<h3 class="cc-headline">
						<?php
						printf(
							/* translators: %d: count */
							esc_html( _n( '%d page lost significant traffic this week.', '%d pages lost significant traffic this week.', count( $decayers ), 'cc-assistant' ) ),
							(int) count( $decayers )
						);
						?>
					</h3>
					<p class="cc-narrative">
						<?php
						$path = wp_parse_url( $top_decay['page'], PHP_URL_PATH ) ?: $top_decay['page'];
						printf(
							/* translators: 1: page path 2: drop percent */
							esc_html__( 'Your %1$s page dropped %2$s vs its 4-week baseline. That is well beyond normal week-to-week variation, so something changed.', 'cc-assistant' ),
							'<em>' . esc_html( $path ) . '</em>',
							'<strong>' . (int) $decay_pct . '%</strong>'
						);
						?>
					</p>
					<div class="cc-evidence">
						<strong><?php esc_html_e( 'Pages losing traffic:', 'cc-assistant' ); ?></strong>
						<ul>
							<?php foreach ( array_slice( $decayers, 0, 4 ) as $row ) :
								$pct = $row['baseline_mean'] > 0
									? (int) round( ( $row['window_mean'] - $row['baseline_mean'] ) * 100 / $row['baseline_mean'] )
									: 0;
								?>
								<li>
									<a href="<?php echo esc_url( $row['page'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $row['page'], PHP_URL_PATH ) ?: $row['page'] ); ?></a>
									&mdash; <span class="cc-delta-down"><?php echo (int) $pct; ?>%</span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<div class="cc-primary-action">
						<a href="#" class="button button-primary cc-copy-prompt" data-prompt="<?php echo esc_attr( sprintf( 'Use the cc-assistant tools. The page %s lost %d%% of clicks this week. Compare its current GSC queries to last month, identify which queries dropped, and suggest what changed.', $top_decay['page'], (int) $decay_pct ) ); ?>"><?php esc_html_e( 'Investigate with Claude', 'cc-assistant' ); ?></a>
					</div>

				<?php elseif ( ! empty( $risers ) || ! empty( $rising ) ) : ?>
					<span class="cc-status cc-status-good"><span class="dashicons dashicons-arrow-up-alt"></span> <?php esc_html_e( 'Wins to double down on', 'cc-assistant' ); ?></span>
					<h3 class="cc-headline">
						<?php
						$count = ! empty( $risers ) ? count( $risers ) : count( $rising );
						printf(
							/* translators: %d: count */
							esc_html( _n( '%d page is gaining clicks. Worth understanding why.', '%d pages are gaining clicks. Worth understanding why.', $count, 'cc-assistant' ) ),
							(int) $count
						);
						?>
					</h3>
					<p class="cc-narrative">
						<?php
						$rise_path = wp_parse_url( $top_rise['page'], PHP_URL_PATH ) ?: $top_rise['page'];
						esc_html_e( 'Whatever changed for ', 'cc-assistant' );
						?><em><?php echo esc_html( $rise_path ); ?></em><?php
						esc_html_e( ' is working. Identify the pattern and apply it to similar pages — content depth, internal links, or matching new search intent are the usual culprits.', 'cc-assistant' );
						?>
					</p>
					<div class="cc-evidence">
						<strong><?php esc_html_e( 'Pages gaining traffic:', 'cc-assistant' ); ?></strong>
						<ul>
							<?php $merged = ! empty( $risers ) ? $risers : $rising; ?>
							<?php foreach ( array_slice( $merged, 0, 4 ) as $row ) : ?>
								<li>
									<a href="<?php echo esc_url( $row['page'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $row['page'], PHP_URL_PATH ) ?: $row['page'] ); ?></a>
									<?php if ( isset( $row['click_delta'] ) ) : ?>
										&mdash; <span class="cc-delta-up">+<?php echo (int) $row['click_delta']; ?> <?php esc_html_e( 'clicks', 'cc-assistant' ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<div class="cc-primary-action">
						<a href="#" class="button button-primary cc-copy-prompt" data-prompt="<?php echo esc_attr( sprintf( 'Use the cc-assistant tools. The page %s is gaining traffic. Identify which queries are growing and what makes this page different from similar underperforming ones.', $top_rise['page'] ) ); ?>"><?php esc_html_e( 'Find the pattern with Claude', 'cc-assistant' ); ?></a>
					</div>

				<?php elseif ( ! empty( $new_strk ) ) : ?>
					<span class="cc-status cc-status-info"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Easy wins surfacing', 'cc-assistant' ); ?></span>
					<h3 class="cc-headline">
						<?php
						printf(
							/* translators: %d: count */
							esc_html( _n( '%d new search just entered striking distance.', '%d new searches just entered striking distance.', count( $new_strk ), 'cc-assistant' ) ),
							(int) count( $new_strk )
						);
						?>
					</h3>
					<p class="cc-narrative">
						<?php esc_html_e( 'These are searches where Google just started showing your pages on page 2. They are the easiest wins because the algorithm already considers your content relevant.', 'cc-assistant' ); ?>
					</p>
					<div class="cc-evidence">
						<ul>
							<?php foreach ( array_slice( $new_strk, 0, 4 ) as $row ) : ?>
								<li>
									<em><?php echo esc_html( $row['query'] ); ?></em>
									&mdash; <?php
									/* translators: %s: position */
									printf( esc_html__( 'now at position %s', 'cc-assistant' ), '<strong>' . esc_html( number_format_i18n( $row['curr_position'], 1 ) ) . '</strong>' );
									?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>

				<?php else : ?>
					<span class="cc-status cc-status-good"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Steady week', 'cc-assistant' ); ?></span>
					<h3 class="cc-headline"><?php esc_html_e( 'Nothing unusual to report.', 'cc-assistant' ); ?></h3>
					<p class="cc-narrative"><?php esc_html_e( 'Your traffic is moving within normal week-to-week variation. No fires to put out, no anomalies to chase. Use the calm to ship new content.', 'cc-assistant' ); ?></p>
				<?php endif; ?>

				<details class="cc-details">
					<summary><?php esc_html_e( 'Show comparison windows', 'cc-assistant' ); ?></summary>
					<?php
					printf(
						/* translators: 1: current start 2: current end 3: previous start 4: previous end */
						esc_html__( 'Comparing %1$s–%2$s vs %3$s–%4$s. Anomalies use a 28-day baseline; only deviations beyond ±1.5 standard deviations surface here.', 'cc-assistant' ),
						esc_html( $insights['trends']['current']['start'] ),
						esc_html( $insights['trends']['current']['end'] ),
						esc_html( $insights['trends']['previous']['start'] ),
						esc_html( $insights['trends']['previous']['end'] )
					);
					?>
				</details>
			</div>
		</div>
	<?php elseif ( ! $gsc_status['connected'] ) : ?>
		<div class="cc-card cc-card-wide cc-info-card">
			<h2><?php esc_html_e( 'Connect Search Console for live insights', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Once connected, this dashboard shows your top page-1 push opportunities, decayed pages, and rising pages without any tools needed.', 'cc-assistant' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=gsc' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Connect Search Console', 'cc-assistant' ); ?></a></p>
		</div>
	<?php elseif ( $gsc_status['connected'] && (int) $gsc_status['rows_cached'] === 0 ) : ?>
		<div class="cc-card cc-card-wide cc-info-card">
			<h2><?php esc_html_e( 'First sync pending', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Search Console is connected but no data has synced yet. Open Settings > Search Console and click "Sync now" to seed the cache.', 'cc-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="cc-grid cc-grid-2col">
		<div class="cc-card">
			<?php
			$orphan_count    = (int) $link_summary['orphan_count'];
			$published_count = (int) $link_summary['published_count'];
			$orphan_pct      = (float) $link_summary['orphan_percent'];
			$first_orphan    = ! empty( $top_orphans ) ? $top_orphans[0] : null;
			$graph_built     = ! empty( $link_summary['graph_built'] );

			// "Fresh install" state: the link graph has never run, so reporting
			// "100% orphans" would be alarmist and wrong. The activator now
			// schedules a priming-wave rebuild a few minutes after activation;
			// until that finishes, show a calm "scanning your site" card.
			if ( ! $graph_built ) {
				$status_class = 'cc-status-attention';
				$status_icon  = 'update';
				$status_text  = __( 'Scanning your site', 'cc-assistant' );
			} elseif ( 0 === $orphan_count ) {
				$status_class = 'cc-status-good';
				$status_icon  = 'yes-alt';
				$status_text  = __( 'All pages connected', 'cc-assistant' );
			} elseif ( $orphan_pct >= 25 ) {
				$status_class = 'cc-status-critical';
				$status_icon  = 'warning';
				$status_text  = __( 'Major linking gap', 'cc-assistant' );
			} else {
				$status_class = 'cc-status-attention';
				$status_icon  = 'info';
				$status_text  = __( 'Some pages isolated', 'cc-assistant' );
			}
			?>
			<span class="cc-status <?php echo esc_attr( $status_class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>"></span> <?php echo esc_html( $status_text ); ?></span>

			<?php if ( ! $graph_built ) : ?>
				<h3 class="cc-headline"><?php esc_html_e( 'We are still scanning your internal links.', 'cc-assistant' ); ?></h3>
				<p class="cc-narrative">
					<?php esc_html_e( 'The link graph rebuilds in the background a few minutes after activation. Refresh in a minute or two, or open Settings > Health and click "Run now" on "Link graph rebuild" to trigger it immediately.', 'cc-assistant' ); ?>
				</p>
				<div class="cc-primary-action">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=health' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Run rebuild now', 'cc-assistant' ); ?></a>
				</div>
			<?php elseif ( 0 === $orphan_count ) : ?>
				<h3 class="cc-headline"><?php esc_html_e( 'Every page on your site is linked to from somewhere else.', 'cc-assistant' ); ?></h3>
				<p class="cc-narrative">
					<?php esc_html_e( 'No orphan pages. Strong signal to Google that your content is interconnected. Now focus on linking depth — the deeper your hubs are linked, the more authority they accumulate.', 'cc-assistant' ); ?>
				</p>
				<?php if ( ! empty( $link_summary['top_hubs'] ) ) :
					$top_hub = $link_summary['top_hubs'][0];
					?>
					<div class="cc-evidence">
						<strong><?php esc_html_e( 'Most-linked-to page (your hub):', 'cc-assistant' ); ?></strong>
						<?php
						printf(
							/* translators: 1: title 2: link count */
							' %1$s — %2$s pages link to it.',
							'<em><a href="' . esc_url( get_edit_post_link( (int) $top_hub['post_id'], 'raw' ) ) . '">' . esc_html( $top_hub['post_title'] ) . '</a></em>',
							'<strong>' . (int) $top_hub['inbound'] . '</strong>'
						);
						?>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<h3 class="cc-headline">
					<?php
					printf(
						/* translators: 1: count 2: percent */
						esc_html__( '%1$d pages on your site (%2$s%%) get no internal link strength.', 'cc-assistant' ),
						$orphan_count,
						esc_html( number_format_i18n( $orphan_pct, 1 ) )
					);
					?>
				</h3>
				<p class="cc-narrative">
					<?php
					if ( $first_orphan ) {
						printf(
							/* translators: %s: title */
							esc_html__( 'No other page on your site links to %s or %d others. Google sees these as less important — nothing inside your site is recommending them. Adding even one or two relevant inbound links lifts how often they get crawled and ranked.', 'cc-assistant' ),
							'<em>' . esc_html( $first_orphan['post_title'] ) . '</em>',
							max( 0, $orphan_count - 1 )
						);
					}
					?>
				</p>
				<div class="cc-evidence">
					<strong><?php esc_html_e( 'Pages to fix first:', 'cc-assistant' ); ?></strong>
					<ul>
						<?php foreach ( $top_orphans as $row ) : ?>
							<li>
								<a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php echo esc_html( $row['post_title'] ); ?></a>
								<small class="description">(<?php echo esc_html( $row['post_type'] ); ?>)</small>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="cc-primary-action">
					<a href="#" class="button button-primary cc-copy-prompt" data-prompt="<?php echo esc_attr( 'Use the cc-assistant tools (links_orphans, links_audit_post). Pick my top 5 orphan pages and propose 3 internal links each — pages that should link to them and where in the body the link should go. Queue the changes as drafts; do not write live.' ); ?>"><?php esc_html_e( 'Fix orphans with Claude', 'cc-assistant' ); ?></a>
				</div>
			<?php endif; ?>

			<details class="cc-details">
				<summary><?php esc_html_e( 'Show top hubs and emitters', 'cc-assistant' ); ?></summary>
				<?php if ( ! empty( $link_summary['top_hubs'] ) ) : ?>
					<h4><?php esc_html_e( 'Top hubs (most-linked-to)', 'cc-assistant' ); ?></h4>
					<ul class="cc-mini-list">
						<?php foreach ( array_slice( $link_summary['top_hubs'], 0, 5 ) as $hub ) : ?>
							<li>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $hub['post_id'], 'raw' ) ); ?>"><?php echo esc_html( $hub['post_title'] ); ?></a>
								<span class="cc-delta"><?php echo (int) $hub['inbound']; ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Link graph rebuilds nightly via cron.', 'cc-assistant' ); ?></p>
			</details>
		</div>

		<div class="cc-card">
			<?php
			$ai_pages    = ( $gsc_ready && ! $insights_pending && ! empty( $insights['trends']['ai_overview'] ) )
				? $insights['trends']['ai_overview'] : array();
			$top_ai      = ! empty( $ai_pages ) ? $ai_pages[0] : null;
			$bot_total   = $llm_summary ? (int) $llm_summary['total_hits'] : 0;
			$top_bot     = ( $llm_summary && ! empty( $llm_summary['by_bot'] ) ) ? $llm_summary['by_bot'][0] : null;
			$llm_enabled = CC_Assistant_LLM_Tracker::is_enabled();
			?>

			<?php if ( $top_ai ) :
				$ai_path = wp_parse_url( $top_ai['page'], PHP_URL_PATH ) ?: $top_ai['page'];
				$ai_ctr_pct = round( (float) $top_ai['ctr'] * 100, 1 );
				?>
				<span class="cc-status cc-status-info"><span class="dashicons dashicons-format-status"></span> <?php esc_html_e( 'AI is summarizing your content', 'cc-assistant' ); ?></span>
				<h3 class="cc-headline">
					<?php
					printf(
						/* translators: %d: count */
						esc_html( _n( '%d page is showing up in Google AI answers.', '%d pages are showing up in Google AI answers.', count( $ai_pages ), 'cc-assistant' ) ),
						(int) count( $ai_pages )
					);
					?>
				</h3>
				<p class="cc-narrative">
					<?php
					printf(
						/* translators: 1: page path 2: impressions 3: CTR percent */
						esc_html__( '%1$s appeared %2$s times in AI Overview / Featured Snippet panels with %3$s%% CTR. Low CTR there means Google is answering the user directly without sending them to your page. Different fix than a title rewrite — make your content the obvious "go deeper" link by adding distinctive examples, data, or a tool.', 'cc-assistant' ),
						'<em>' . esc_html( $ai_path ) . '</em>',
						'<strong>' . esc_html( number_format_i18n( $top_ai['impressions'] ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $ai_ctr_pct, 1 ) ) . '</strong>'
					);
					?>
				</p>
				<div class="cc-evidence">
					<strong><?php esc_html_e( 'Pages in AI surfaces:', 'cc-assistant' ); ?></strong>
					<ul>
						<?php foreach ( array_slice( $ai_pages, 0, 4 ) as $row ) : ?>
							<li>
								<a href="<?php echo esc_url( $row['page'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $row['page'], PHP_URL_PATH ) ?: $row['page'] ); ?></a>
								&mdash; <?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?> <?php esc_html_e( 'impressions', 'cc-assistant' ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="cc-primary-action">
					<a href="#" class="button button-primary cc-copy-prompt" data-prompt="<?php echo esc_attr( sprintf( 'Use the cc-assistant tools. The page %s appears in Google AI answers but has low CTR. Identify the queries that trigger it, then propose distinctive content additions (examples, data, tool, comparison) that make this page the obvious deeper-read.', $top_ai['page'] ) ); ?>"><?php esc_html_e( 'Strategize with Claude', 'cc-assistant' ); ?></a>
				</div>

			<?php elseif ( $bot_total > 0 ) : ?>
				<span class="cc-status cc-status-info"><span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e( 'AI bots are reading your content', 'cc-assistant' ); ?></span>
				<h3 class="cc-headline">
					<?php
					printf(
						/* translators: 1: bot name 2: total hits */
						esc_html__( '%1$s and friends crawled your site %2$s times this week.', 'cc-assistant' ),
						'<em>' . esc_html( $top_bot['bot_name'] ) . '</em>',
						'<strong>' . esc_html( number_format_i18n( $bot_total ) ) . '</strong>'
					);
					?>
				</h3>
				<p class="cc-narrative">
					<?php esc_html_e( 'AI training bots are actively indexing your content. That means your pages can show up in ChatGPT, Claude, Perplexity, and Google AI answers as cited sources. Make sure your strongest pages are crawlable and that author/expertise signals are visible.', 'cc-assistant' ); ?>
				</p>
				<div class="cc-evidence">
					<strong><?php esc_html_e( 'Most active bots:', 'cc-assistant' ); ?></strong>
					<ul>
						<?php foreach ( array_slice( $llm_summary['by_bot'], 0, 5 ) as $bot ) : ?>
							<li>
								<strong><?php echo esc_html( $bot['bot_name'] ); ?></strong> &mdash;
								<?php
								printf(
									/* translators: %s: hits */
									esc_html__( '%s hits', 'cc-assistant' ),
									esc_html( number_format_i18n( $bot['hits'] ) )
								);
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

			<?php elseif ( ! $llm_enabled ) : ?>
				<span class="cc-status cc-status-pending"><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Tracking is off', 'cc-assistant' ); ?></span>
				<h3 class="cc-headline"><?php esc_html_e( 'You are not seeing how AI engages with your content yet.', 'cc-assistant' ); ?></h3>
				<p class="cc-narrative">
					<?php esc_html_e( 'AI services like ChatGPT, Claude, and Perplexity crawl your content to train models and cite sources in real time. Enabling tracking shows you which bots visit, how often, and which pages they prefer — useful for deciding what to feature, what to gate, and what to optimize for AI citation.', 'cc-assistant' ); ?>
				</p>
				<div class="cc-primary-action">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=general' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Enable AI bot tracking', 'cc-assistant' ); ?></a>
					<span class="description"><?php esc_html_e( 'One toggle. No external services. IPs are hashed.', 'cc-assistant' ); ?></span>
				</div>

			<?php else : ?>
				<span class="cc-status cc-status-good"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Quiet on the AI front', 'cc-assistant' ); ?></span>
				<h3 class="cc-headline"><?php esc_html_e( 'No AI bot visits or AI Overview impressions this week.', 'cc-assistant' ); ?></h3>
				<p class="cc-narrative">
					<?php esc_html_e( 'Either your content is too new or too niche for AI services to have discovered yet, or your robots.txt is blocking them. Worth checking that GPTBot, ClaudeBot, and Google-Extended are not disallowed if you want AI citations.', 'cc-assistant' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php
	if ( $gsc_ready && ! $insights_pending && ! empty( $insights['intent']['buckets'] ) ) :
		// Plain-English label + tooltip per intent bucket.
		$intent_meta = array(
			'informational' => array(
				'label' => __( 'Informational', 'cc-assistant' ),
				'tip'   => __( 'Visitors looking for an answer or how-to. Lean into educational depth, FAQs, examples.', 'cc-assistant' ),
				'color' => '#1976d2',
			),
			'commercial'    => array(
				'label' => __( 'Commercial', 'cc-assistant' ),
				'tip'   => __( 'Visitors comparing options before buying ("best", "vs", "review"). Optimize for trust signals and comparisons.', 'cc-assistant' ),
				'color' => '#7c3aed',
			),
			'transactional' => array(
				'label' => __( 'Transactional', 'cc-assistant' ),
				'tip'   => __( 'Visitors ready to buy, sign up, or download. Make the call to action obvious and remove friction.', 'cc-assistant' ),
				'color' => '#059669',
			),
			'navigational'  => array(
				'label' => __( 'Navigational', 'cc-assistant' ),
				'tip'   => __( 'Visitors trying to reach a specific destination (login, app, dashboard). Keep paths short and obvious.', 'cc-assistant' ),
				'color' => '#0e7490',
			),
			'local'         => array(
				'label' => __( 'Local', 'cc-assistant' ),
				'tip'   => __( 'Visitors searching for a place near them ("near me", city names). Surface address, hours, directions.', 'cc-assistant' ),
				'color' => '#d97706',
			),
			'branded'       => array(
				'label' => __( 'Branded', 'cc-assistant' ),
				'tip'   => __( 'Visitors searching for your brand directly. Already convinced — make sure landing pages match what they expect.', 'cc-assistant' ),
				'color' => '#475569',
			),
			'other'         => array(
				'label' => __( 'Other', 'cc-assistant' ),
				'tip'   => __( 'Searches that did not fit a clear pattern.', 'cc-assistant' ),
				'color' => '#9ca3af',
			),
		);

		$total_imp = 0;
		foreach ( $insights['intent']['buckets'] as $b ) {
			$total_imp += (int) $b['impressions'];
		}
		if ( $total_imp < 1 ) {
			$total_imp = 1;
		}
		?>
		<div class="cc-card cc-card-wide">
			<?php
			$dominant     = ! empty( $insights['intent']['buckets'] ) ? $insights['intent']['buckets'][0] : null;
			$dominant_pct = 0;
			if ( $dominant && $total_imp > 0 ) {
				$dominant_pct = round( ( $dominant['impressions'] / $total_imp ) * 100, 1 );
			}
			$dominant_intent = $dominant ? ( isset( $dominant['intent'] ) ? $dominant['intent'] : 'other' ) : 'other';
			$dominant_meta   = isset( $intent_meta[ $dominant_intent ] ) ? $intent_meta[ $dominant_intent ] : $intent_meta['other'];
			$dominant_action = array(
				'informational' => __( 'invest in deeper guides, FAQs, and explainer content. Visitors are in research mode.', 'cc-assistant' ),
				'commercial'    => __( 'invest in comparison content, reviews, and trust signals. Visitors are choosing between options.', 'cc-assistant' ),
				'transactional' => __( 'tighten your conversion path. Visitors are ready to buy — make CTAs unmissable and remove friction.', 'cc-assistant' ),
				'navigational'  => __( 'make sure landing pages match what users searched for. They want a specific destination.', 'cc-assistant' ),
				'local'         => __( 'feature address, hours, and directions prominently. Make local pages obvious in your sitemap.', 'cc-assistant' ),
				'branded'       => __( 'these visitors already chose you — make sure they land on the page that matches their search intent (homepage, login, support).', 'cc-assistant' ),
				'other'         => __( 'review the sample queries to see what these searchers want.', 'cc-assistant' ),
			);
			$action_text = isset( $dominant_action[ $dominant_intent ] ) ? $dominant_action[ $dominant_intent ] : $dominant_action['other'];
			?>
			<span class="cc-status cc-status-info"><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Audience snapshot', 'cc-assistant' ); ?></span>
			<h3 class="cc-headline">
				<?php
				if ( $dominant ) {
					printf(
						/* translators: 1: percent 2: intent label */
						esc_html__( '%1$s%% of your searches are %2$s.', 'cc-assistant' ),
						esc_html( number_format_i18n( $dominant_pct, 1 ) ),
						'<em>' . esc_html( strtolower( $dominant_meta['label'] ) ) . '</em>'
					);
				} else {
					esc_html_e( 'Audience profile not yet available.', 'cc-assistant' );
				}
				?>
			</h3>
			<p class="cc-narrative">
				<?php
				if ( $dominant ) {
					printf(
						/* translators: 1: intent description 2: action recommendation */
						esc_html__( '%1$s This dominant pattern means you should %2$s', 'cc-assistant' ),
						esc_html( $dominant_meta['tip'] ),
						esc_html( $action_text )
					);
				} else {
					printf(
						/* translators: %d: days */
						esc_html__( 'Every search that landed on your site in the last %d days, grouped by what the user was probably trying to do.', 'cc-assistant' ),
						(int) $insights['intent']['days']
					);
				}
				?>
			</p>

			<?php
			// Roll up sub-1% buckets (other than the dominant) into a single
			// "Other intents" row so the visualization stays uncluttered.
			$display       = array();
			$rolled_other  = array(
				'intent'      => 'other',
				'queries'     => 0,
				'impressions' => 0,
				'clicks'      => 0,
				'samples'     => array(),
				'rolled_from' => array(),
			);
			foreach ( $insights['intent']['buckets'] as $b ) {
				$pct_b = $total_imp > 0 ? ( $b['impressions'] / $total_imp ) * 100 : 0;
				$ib    = isset( $b['intent'] ) ? $b['intent'] : 'other';
				if ( $pct_b < 1.0 && $ib !== $dominant_intent ) {
					$rolled_other['queries']     += (int) $b['queries'];
					$rolled_other['impressions'] += (int) $b['impressions'];
					$rolled_other['clicks']      += (int) $b['clicks'];
					$rolled_other['rolled_from'][] = isset( $intent_meta[ $ib ] ) ? $intent_meta[ $ib ]['label'] : $ib;
					if ( ! empty( $b['samples'] ) ) {
						$rolled_other['samples'] = array_slice(
							array_merge( $rolled_other['samples'], array_slice( $b['samples'], 0, 2 ) ),
							0,
							5
						);
					}
				} else {
					$display[] = $b;
				}
			}
			if ( $rolled_other['impressions'] > 0 ) {
				$display[] = $rolled_other;
			}
			?>

			<div class="cc-intent-list">
				<?php foreach ( $display as $b ) :
					$intent      = isset( $b['intent'] ) ? $b['intent'] : 'other';
					$meta        = isset( $intent_meta[ $intent ] ) ? $intent_meta[ $intent ] : $intent_meta['other'];
					$pct         = round( ( $b['impressions'] / $total_imp ) * 100, 1 );
					$is_dominant = ( $intent === $dominant_intent );
					$rolled_from = ! empty( $b['rolled_from'] ) ? $b['rolled_from'] : array();
					?>
					<details class="cc-intent-row<?php echo $is_dominant ? ' is-dominant' : ''; ?>" <?php echo $is_dominant ? 'open' : ''; ?> style="--intent-color: <?php echo esc_attr( $meta['color'] ); ?>;">
						<summary>
							<span class="cc-intent-fill" style="width: <?php echo esc_attr( $pct ); ?>%;"></span>
							<span class="cc-intent-row-content">
								<span class="cc-intent-row-label">
									<span class="cc-intent-dot"></span>
									<strong><?php echo esc_html( $meta['label'] ); ?></strong>
									<?php if ( $is_dominant ) : ?>
										<span class="cc-intent-pill"><?php esc_html_e( 'dominant', 'cc-assistant' ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $rolled_from ) ) : ?>
										<small class="description">(<?php
											printf(
												/* translators: %s: comma-separated intent labels */
												esc_html__( 'rolled up: %s', 'cc-assistant' ),
												esc_html( implode( ', ', $rolled_from ) )
											);
										?>)</small>
									<?php endif; ?>
								</span>
								<span class="cc-intent-row-meta">
									<span class="cc-intent-row-impr"><?php echo esc_html( number_format_i18n( $b['impressions'] ) ); ?> <?php esc_html_e( 'impr', 'cc-assistant' ); ?></span>
									<span class="cc-intent-row-pct"><?php echo esc_html( number_format_i18n( $pct, 1 ) ); ?>%</span>
								</span>
							</span>
						</summary>

						<div class="cc-intent-row-body">
							<p class="cc-intent-tip"><?php echo esc_html( $meta['tip'] ); ?></p>
							<?php if ( $is_dominant && isset( $dominant_action[ $intent ] ) ) : ?>
								<p class="cc-intent-action">
									<strong><?php esc_html_e( 'Suggested move:', 'cc-assistant' ); ?></strong>
									<?php echo esc_html( $dominant_action[ $intent ] ); ?>
								</p>
							<?php endif; ?>
							<div class="cc-intent-row-stats">
								<span><strong><?php echo esc_html( number_format_i18n( $b['clicks'] ) ); ?></strong> <?php esc_html_e( 'clicks', 'cc-assistant' ); ?></span>
								<span><strong><?php echo esc_html( number_format_i18n( $b['impressions'] ) ); ?></strong> <?php esc_html_e( 'impressions', 'cc-assistant' ); ?></span>
								<span><strong><?php echo esc_html( number_format_i18n( $b['queries'] ) ); ?></strong> <?php esc_html_e( 'unique searches', 'cc-assistant' ); ?></span>
							</div>
							<?php if ( ! empty( $b['samples'] ) ) : ?>
								<div class="cc-intent-samples-block">
									<span class="cc-intent-samples-label"><?php esc_html_e( 'Sample searches:', 'cc-assistant' ); ?></span>
									<?php foreach ( array_slice( $b['samples'], 0, 5 ) as $sample ) : ?>
										<span class="cc-intent-sample-chip" title="<?php echo esc_attr( $sample ); ?>"><?php echo esc_html( $sample ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</div>

			<div class="cc-primary-action">
				<a href="#" class="button button-primary cc-copy-prompt" data-prompt="<?php echo esc_attr( sprintf( 'Use the cc-assistant tools (gsc_intent_breakdown, gsc_opportunities). My audience is dominantly %s. Suggest 5 new posts (with title + outline) that match this intent and target queries I am close to ranking for.', $dominant_intent ) ); ?>"><?php esc_html_e( 'Plan content with Claude', 'cc-assistant' ); ?></a>
				<span class="description"><?php esc_html_e( '5 post ideas matched to your dominant intent and ranking opportunities.', 'cc-assistant' ); ?></span>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $pending_outcomes ) ) :
		$ready_soon       = 0;
		$total_edits_pend = 0;
		foreach ( $pending_outcomes as $po ) {
			if ( (int) $po['oldest_pending']['ready_in_days'] <= 1 ) {
				$ready_soon++;
			}
			$total_edits_pend += (int) $po['pending_count'];
		}
		$post_count = count( $pending_outcomes );
		?>
		<div class="cc-card cc-card-wide">
			<span class="cc-status cc-status-pending"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Still measuring', 'cc-assistant' ); ?></span>
			<h3 class="cc-headline">
				<?php
				if ( $total_edits_pend === $post_count ) {
					printf(
						/* translators: %d: count of posts inside the measurement window */
						esc_html( _n( '%d post is inside its measurement window.', '%d posts are inside their measurement windows.', $post_count, 'cc-assistant' ) ),
						(int) $post_count
					);
				} else {
					printf(
						/* translators: 1: post count, 2: edit count */
						esc_html__( '%1$d posts (%2$d edits) inside the measurement window.', 'cc-assistant' ),
						(int) $post_count,
						(int) $total_edits_pend
					);
				}
				?>
			</h3>
			<p class="cc-narrative">
				<?php if ( $ready_soon > 0 ) : ?>
					<?php
					printf(
						/* translators: %d: count of posts with verdicts ready within 1 day */
						esc_html( _n( '%d will have a verdict within the next day.', '%d will have verdicts within the next day.', $ready_soon, 'cc-assistant' ) ),
						(int) $ready_soon
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Search Console lags 2–3 days and we need a week of post-edit data to score each one. Posts about to clear show first; expand a row to see every pending edit on that post.', 'cc-assistant' ); ?>
				<?php endif; ?>
			</p>
			<details class="cc-details" open>
				<summary><?php esc_html_e( 'Show measuring posts', 'cc-assistant' ); ?></summary>
				<table class="cc-mini-table">
					<thead><tr>
						<th style="width:42%;"><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'Pending edits', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'Oldest applied', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'Next verdict in', 'cc-assistant' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $pending_outcomes as $po ) :
						$oldest       = $po['oldest_pending'];
						$newest       = $po['newest_pending'];
						$post_kept    = ! empty( $po['post_exists'] );
						$page_display = wp_parse_url( $po['page_url'], PHP_URL_PATH ) ?: $po['page_url'];
						$post_label   = ! empty( $po['post_title'] ) ? $po['post_title'] : $page_display;
						$ready        = (int) $oldest['ready_in_days'];
						$count        = (int) $po['pending_count'];
						?>
						<tr<?php echo $post_kept ? '' : ' class="cc-row-deleted" style="opacity:0.6;"'; ?>>
							<td>
								<?php if ( $post_kept ) : ?>
									<strong><?php echo esc_html( $post_label ); ?></strong>
									<br>
									<a href="<?php echo esc_url( $po['page_url'] ); ?>" target="_blank" rel="noopener" style="font-size:11px;color:#555;">
										<?php echo esc_html( $page_display ); ?>
									</a>
									<?php if ( $po['edit_url'] ) : ?>
										&middot;
										<a href="<?php echo esc_url( $po['edit_url'] ); ?>" style="font-size:11px;">Edit</a>
									<?php endif; ?>
								<?php else : ?>
									<?php echo esc_html( $page_display ); ?>
									<small class="description">(<?php esc_html_e( 'post deleted', 'cc-assistant' ); ?>)</small>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $count > 1 ) : ?>
									<span class="cc-trend-tag cc-tag-pending" style="font-weight:600;">
										<?php
										printf(
											/* translators: %d: count of pending edits on this post */
											esc_html( _n( '%d edit', '%d edits', $count, 'cc-assistant' ) ),
											$count
										);
										?>
									</span>
									<br>
									<small class="description"><?php esc_html_e( 'Newest:', 'cc-assistant' ); ?> <?php echo esc_html( $newest['edit']['change_summary'] ?: $newest['edit']['change_type'] ); ?></small>
								<?php else : ?>
									<?php echo esc_html( $oldest['edit']['change_summary'] ?: $oldest['edit']['change_type'] ); ?>
									<br><small class="description"><code><?php echo esc_html( $oldest['edit']['change_type'] ); ?></code></small>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( human_time_diff( strtotime( $oldest['edit']['applied_at'] . ' UTC' ), time() ) ); ?> <?php esc_html_e( 'ago', 'cc-assistant' ); ?>
							</td>
							<td>
								<?php if ( $ready <= 0 ) : ?>
									<span class="cc-trend-tag cc-tag-pending"><?php esc_html_e( 'today / tomorrow', 'cc-assistant' ); ?></span>
								<?php else : ?>
									<span class="cc-trend-tag cc-tag-pending"><?php
										printf(
											/* translators: %d: days until verdict */
											esc_html( _n( '~%d day', '~%d days', $ready, 'cc-assistant' ) ),
											$ready
										);
									?></span>
								<?php endif; ?>
								<?php if ( $count > 1 ) : ?>
									<br><small><?php esc_html_e( 'Earliest of', 'cc-assistant' ); ?> <?php echo (int) $count; ?> <?php esc_html_e( 'pending', 'cc-assistant' ); ?></small>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( $count > 1 ) : ?>
							<tr<?php echo $post_kept ? '' : ' style="opacity:0.6;"'; ?>>
								<td colspan="4" style="background:#fafbfc;padding:6px 12px 10px 24px;border-top:none;">
									<details>
										<summary style="cursor:pointer;font-size:11px;color:#555;"><?php
											printf(
												/* translators: %d: count of pending edits */
												esc_html__( 'Show all %d pending edits on this post', 'cc-assistant' ),
												$count
											);
										?></summary>
										<ul style="margin:6px 0 0 12px;font-size:11px;list-style:disc;">
										<?php foreach ( $po['edits'] as $sub ) : ?>
											<li>
												<code><?php echo esc_html( $sub['edit']['change_type'] ); ?></code>
												— <?php echo esc_html( $sub['edit']['change_summary'] ?: '—' ); ?>
												<span style="color:#777;">
													(<?php echo esc_html( human_time_diff( strtotime( $sub['edit']['applied_at'] . ' UTC' ), time() ) ); ?> ago,
													verdict in
													<?php
													$sr = (int) $sub['ready_in_days'];
													echo esc_html( $sr <= 0 ? __( '<1 day', 'cc-assistant' ) : sprintf( _n( '%d day', '%d days', $sr, 'cc-assistant' ), $sr ) );
													?>)
												</span>
											</li>
										<?php endforeach; ?>
										</ul>
									</details>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $recent_edits ) ) :
		// $recent_edits is grouped by post now. Sum each post's verdict_breakdown
		// so the narrative counts still describe edits (the unit users intuit),
		// not posts (the unit we de-duplicate to).
		$counts = array( 'positive' => 0, 'flat' => 0, 'negative' => 0, 'pending' => 0, 'no_data' => 0 );
		foreach ( $recent_edits as $row ) {
			if ( isset( $row['verdict_breakdown'] ) && is_array( $row['verdict_breakdown'] ) ) {
				foreach ( $counts as $k => $_ ) {
					if ( isset( $row['verdict_breakdown'][ $k ] ) ) {
						$counts[ $k ] += (int) $row['verdict_breakdown'][ $k ];
					}
				}
			}
		}

		if ( $counts['negative'] > 0 ) {
			$status_class = 'cc-status-attention';
			$status_icon  = 'warning';
			$status_text  = __( 'Some edits hurt traffic', 'cc-assistant' );
			$headline     = sprintf(
				/* translators: %d: count */
				_n( '%d recent edit caused a measurable drop in clicks.', '%d recent edits caused measurable drops in clicks.', $counts['negative'], 'cc-assistant' ),
				$counts['negative']
			);
			$narrative = __( 'Worth investigating which queries lost ground. Sometimes the right call is reverting and trying a different angle. Check the table below for details.', 'cc-assistant' );
		} elseif ( $counts['positive'] > 0 ) {
			$status_class = 'cc-status-good';
			$status_icon  = 'yes-alt';
			$status_text  = __( 'Edits are paying off', 'cc-assistant' );
			$headline     = sprintf(
				/* translators: %d: count */
				_n( '%d recent edit improved traffic vs its baseline.', '%d recent edits improved traffic vs their baseline.', $counts['positive'], 'cc-assistant' ),
				$counts['positive']
			);
			$narrative = __( 'Note what worked — content depth, internal linking, title rewrite — and apply the same pattern to other pages with similar profiles.', 'cc-assistant' );
		} elseif ( $counts['pending'] === array_sum( $counts ) ) {
			$status_class = 'cc-status-pending';
			$status_icon  = 'clock';
			$status_text  = __( 'Measuring in progress', 'cc-assistant' );
			$headline     = sprintf(
				/* translators: %d: count */
				_n( '%d edit is still being measured.', '%d edits are still being measured.', $counts['pending'], 'cc-assistant' ),
				$counts['pending']
			);
			$narrative = __( 'Search Console data lags 2–3 days, and we wait at least a week of post-edit data before scoring an outcome. Check back next week.', 'cc-assistant' );
		} else {
			$status_class = 'cc-status-info';
			$status_icon  = 'chart-line';
			$status_text  = __( 'Mixed results', 'cc-assistant' );
			$headline     = __( 'Recent edits have not moved the needle yet.', 'cc-assistant' );
			$narrative    = __( 'Some edits stayed flat — neither big winners nor losers. Either the changes were too small to register, or they targeted the wrong levers. Worth reviewing the strategy with Claude before queueing more.', 'cc-assistant' );
		}
		?>
		<div class="cc-card cc-card-wide">
			<span class="cc-status <?php echo esc_attr( $status_class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>"></span> <?php echo esc_html( $status_text ); ?></span>
			<h3 class="cc-headline"><?php echo esc_html( $headline ); ?></h3>
			<p class="cc-narrative"><?php echo esc_html( $narrative ); ?></p>
			<?php if ( $counts['positive'] || $counts['negative'] || $counts['flat'] || $counts['pending'] ) : ?>
				<div class="cc-evidence">
					<strong><?php esc_html_e( 'Verdict breakdown:', 'cc-assistant' ); ?></strong>
					<?php if ( $counts['positive'] ) : ?>
						<span class="cc-trend-tag cc-tag-up" style="margin-left:6px;"><?php echo (int) $counts['positive']; ?> <?php esc_html_e( 'improved', 'cc-assistant' ); ?></span>
					<?php endif; ?>
					<?php if ( $counts['negative'] ) : ?>
						<span class="cc-trend-tag cc-tag-down" style="margin-left:4px;"><?php echo (int) $counts['negative']; ?> <?php esc_html_e( 'declined', 'cc-assistant' ); ?></span>
					<?php endif; ?>
					<?php if ( $counts['flat'] ) : ?>
						<span class="cc-trend-tag cc-tag-flat" style="margin-left:4px;"><?php echo (int) $counts['flat']; ?> <?php esc_html_e( 'flat', 'cc-assistant' ); ?></span>
					<?php endif; ?>
					<?php if ( $counts['pending'] ) : ?>
						<span class="cc-trend-tag cc-tag-pending" style="margin-left:4px;"><?php echo (int) $counts['pending']; ?> <?php esc_html_e( 'measuring', 'cc-assistant' ); ?></span>
					<?php endif; ?>
					<?php if ( $counts['no_data'] ) : ?>
						<span class="cc-trend-tag cc-tag-nodata" style="margin-left:4px;" title="<?php esc_attr_e( 'GSC has fewer than 10 impressions for these URLs across both windows. Not a verdict on the edit itself.', 'cc-assistant' ); ?>"><?php echo (int) $counts['no_data']; ?> <?php esc_html_e( 'no data', 'cc-assistant' ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<details class="cc-details" open>
				<summary><?php esc_html_e( 'Show post-by-post detail', 'cc-assistant' ); ?></summary>
			<table class="cc-mini-table">
				<thead><tr>
					<th style="width:42%;"><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Latest change', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Applied', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Outcome', 'cc-assistant' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $recent_edits as $post_row ) :
					$latest       = $post_row['latest_edit'];
					$edit         = $latest['edit'];
					$post_kept    = ! empty( $post_row['post_exists'] );
					$page_display = wp_parse_url( $post_row['page_url'], PHP_URL_PATH ) ?: $post_row['page_url'];
					$post_label   = ! empty( $post_row['post_title'] ) ? $post_row['post_title'] : $page_display;
					$edit_count   = (int) $post_row['edit_count'];
					$br           = $post_row['verdict_breakdown'];
					?>
					<tr<?php echo $post_kept ? '' : ' class="cc-row-deleted" style="opacity:0.6;"'; ?>>
						<td>
							<?php if ( $post_kept ) : ?>
								<strong><?php echo esc_html( $post_label ); ?></strong>
								<br>
								<a href="<?php echo esc_url( $post_row['page_url'] ); ?>" target="_blank" rel="noopener" style="font-size:11px;color:#555;">
									<?php echo esc_html( $page_display ); ?>
								</a>
								<?php if ( $post_row['edit_url'] ) : ?>
									&middot;
									<a href="<?php echo esc_url( $post_row['edit_url'] ); ?>" style="font-size:11px;">Edit</a>
								<?php endif; ?>
							<?php else : ?>
								<?php echo esc_html( $page_display ); ?>
								<small class="description">(<?php esc_html_e( 'post deleted', 'cc-assistant' ); ?>)</small>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $edit['change_summary'] ?: $edit['change_type'] ); ?>
							<br><small class="description"><code><?php echo esc_html( $edit['change_type'] ); ?></code></small>
							<?php if ( $edit_count > 1 ) : ?>
								<br>
								<span class="cc-trend-tag cc-tag-pending" style="font-size:10px;margin-top:3px;">
									<?php
									printf(
										/* translators: %d: count of recent edits on this post */
										esc_html( _n( '+%d more edit', '+%d more edits', $edit_count - 1, 'cc-assistant' ) ),
										$edit_count - 1
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( human_time_diff( strtotime( $edit['applied_at'] . ' UTC' ), time() ) ); ?> <?php esc_html_e( 'ago', 'cc-assistant' ); ?></td>
						<td>
							<?php if ( 'pending' === $latest['status'] ) : ?>
								<span class="cc-trend-tag cc-tag-pending cc-tip" tabindex="0" data-tip="Latest edit is too recent. Search Console data lags 2 to 3 days, and we wait at least a week of post-edit data before scoring." title="Latest edit is too recent. Search Console data lags 2 to 3 days, and we wait at least a week of post-edit data before scoring."><?php esc_html_e( 'Measuring', 'cc-assistant' ); ?></span>
								<br><small><?php echo esc_html( $latest['message'] ); ?></small>
							<?php else :
								$verdict_class = 'cc-tag-flat';
								$verdict_label = __( 'Flat', 'cc-assistant' );
								$verdict_tip   = __( 'Click count is within 10% of the 14 days before the latest edit. Hard to credit the edit either way.', 'cc-assistant' );
								if ( 'positive' === $latest['verdict'] ) {
									$verdict_class = 'cc-tag-up';
									$verdict_label = __( 'Improved', 'cc-assistant' );
									$verdict_tip   = __( 'Clicks went up more than 10% vs the 14 days before the latest edit. The change likely helped.', 'cc-assistant' );
								} elseif ( 'negative' === $latest['verdict'] ) {
									$verdict_class = 'cc-tag-down';
									$verdict_label = __( 'Declined', 'cc-assistant' );
									$verdict_tip   = __( 'Clicks dropped more than 10% vs the 14 days before the latest edit. Consider reverting and trying a different angle.', 'cc-assistant' );
								} elseif ( 'no_data' === $latest['verdict'] ) {
									$verdict_class = 'cc-tag-nodata';
									$verdict_label = __( 'No GSC data', 'cc-assistant' );
									$verdict_tip   = __( 'Search Console has fewer than 10 total impressions for this URL across both windows. Either GSC is not connected, this URL is too new to rank, or the page hash has not been synced yet — not a verdict on the edit itself.', 'cc-assistant' );
								}
								?>
								<span class="cc-tag <?php echo esc_attr( $verdict_class ); ?> cc-tip" tabindex="0" data-tip="<?php echo esc_attr( $verdict_tip ); ?>" title="<?php echo esc_attr( $verdict_tip ); ?>"><?php echo esc_html( $verdict_label ); ?></span>
								<?php if ( ! empty( $latest['totals'] ) ) :
									$cd = (int) $latest['totals']['click_delta_norm'];
									// v0.71.0: the old tooltip asserted a flat 14-vs-14
									// comparison. When the after-window is still filling
									// it is really N days scaled up, so state the real
									// window and flag a provisional read rather than
									// letting the number look settled.
									$cd_after   = isset( $latest['windows']['after']['days'] ) ? (int) $latest['windows']['after']['days'] : 0;
									$cd_base    = isset( $latest['windows']['baseline']['days'] ) ? (int) $latest['windows']['baseline']['days'] : 14;
									$cd_prov    = isset( $latest['confidence']['level'] ) && 'provisional' === $latest['confidence']['level'];
									$cd_tip     = sprintf(
										/* translators: 1: after-window days, 2: baseline days */
										__( 'Total clicks in the %1$d day(s) since the latest edit vs the %2$d days before, scaled to the same window length.', 'cc-assistant' ),
										$cd_after,
										$cd_base
									);
									if ( $cd_prov ) {
										$cd_tip .= ' ' . sprintf(
											/* translators: %d: baseline days */
											__( 'PROVISIONAL — the after-window is still filling, so this figure is an extrapolation and will move. Re-check at %d days.', 'cc-assistant' ),
											$cd_base
										);
									}
									?>
									<br><small title="<?php echo esc_attr( $cd_tip ); ?>"><?php echo $cd >= 0 ? '+' : ''; ?><?php echo esc_html( $cd ); ?> <?php esc_html_e( 'clicks vs before', 'cc-assistant' ); ?><?php if ( $cd_prov ) : ?> <span style="opacity:.7"><?php esc_html_e( '(provisional)', 'cc-assistant' ); ?></span><?php endif; ?></small>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( $edit_count > 1 ) :
								// Mini breakdown across all recent edits on this post
								$br_parts = array();
								if ( $br['positive'] ) { $br_parts[] = '+' . $br['positive'] . ' up'; }
								if ( $br['negative'] ) { $br_parts[] = $br['negative'] . ' down'; }
								if ( $br['flat'] )     { $br_parts[] = $br['flat'] . ' flat'; }
								if ( $br['pending'] )  { $br_parts[] = $br['pending'] . ' measuring'; }
								if ( $br['no_data'] )  { $br_parts[] = $br['no_data'] . ' no data'; }
								?>
								<br><small style="color:#777;font-size:10px;">
									<?php esc_html_e( 'Across', 'cc-assistant' ); ?> <?php echo (int) $edit_count; ?> <?php esc_html_e( 'edits:', 'cc-assistant' ); ?>
									<?php echo esc_html( implode( ', ', $br_parts ) ); ?>
								</small>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $edit_count > 1 ) : ?>
						<tr<?php echo $post_kept ? '' : ' style="opacity:0.6;"'; ?>>
							<td colspan="4" style="background:#fafbfc;padding:6px 12px 10px 24px;border-top:none;">
								<details>
									<summary style="cursor:pointer;font-size:11px;color:#555;"><?php
										printf(
											/* translators: %d: count of recent edits on this post */
											esc_html__( 'Show all %d recent edits on this post', 'cc-assistant' ),
											$edit_count
										);
									?></summary>
									<ul style="margin:6px 0 0 12px;font-size:11px;list-style:disc;">
									<?php foreach ( $post_row['edits'] as $sub ) :
										$ssub = $sub['edit'];
										$sv   = isset( $sub['verdict'] ) ? $sub['verdict'] : null;
										$ss   = isset( $sub['status'] ) ? $sub['status'] : 'pending';
										$sym  = '·';
										if ( 'pending' === $ss ) { $sym = '⏱'; }
										elseif ( 'positive' === $sv ) { $sym = '↑'; }
										elseif ( 'negative' === $sv ) { $sym = '↓'; }
										elseif ( 'no_data' === $sv ) { $sym = '–'; }
										?>
										<li>
											<span style="display:inline-block;width:14px;color:#777;"><?php echo esc_html( $sym ); ?></span>
											<code><?php echo esc_html( $ssub['change_type'] ); ?></code>
											— <?php echo esc_html( $ssub['change_summary'] ?: '—' ); ?>
											<span style="color:#777;">(<?php echo esc_html( human_time_diff( strtotime( $ssub['applied_at'] . ' UTC' ), time() ) ); ?> ago)</span>
										</li>
									<?php endforeach; ?>
									</ul>
								</details>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			</details>
		</div>
	<?php endif; ?>

	<div class="cc-grid">
		<div class="cc-card cc-card-week">
			<h2>
				<?php esc_html_e( 'This week with Claude', 'cc-assistant' ); ?>
				<span class="cc-card-meta"><?php esc_html_e( 'Last 7 days', 'cc-assistant' ); ?></span>
			</h2>
			<?php if ( 0 === $week_total ) : ?>
				<p class="description cc-empty-line"><?php esc_html_e( 'No activity yet this week. Open Claude Code in this site folder and ask it to suggest changes.', 'cc-assistant' ); ?></p>
			<?php else : ?>
				<div class="cc-week-stats">
					<div class="cc-week-stat">
						<div class="cc-week-num"><?php echo (int) $week_counts['pending']; ?></div>
						<div class="cc-week-lbl"><?php esc_html_e( 'Proposed', 'cc-assistant' ); ?></div>
					</div>
					<div class="cc-week-stat cc-week-good">
						<div class="cc-week-num"><?php echo (int) $week_counts['approved']; ?></div>
						<div class="cc-week-lbl"><?php esc_html_e( 'Applied', 'cc-assistant' ); ?></div>
					</div>
					<div class="cc-week-stat cc-week-warn">
						<div class="cc-week-num"><?php echo (int) $week_counts['rejected']; ?></div>
						<div class="cc-week-lbl"><?php esc_html_e( 'Rejected', 'cc-assistant' ); ?></div>
					</div>
					<div class="cc-week-stat cc-week-muted">
						<div class="cc-week-num"><?php echo (int) $week_counts['rolled_back']; ?></div>
						<div class="cc-week-lbl"><?php esc_html_e( 'Rolled back', 'cc-assistant' ); ?></div>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="cc-card cc-card-whatsworking">
			<h2>
				<?php esc_html_e( "What's working", 'cc-assistant' ); ?>
				<span class="cc-card-meta"><?php esc_html_e( 'Last 30 days, scored against intent', 'cc-assistant' ); ?></span>
			</h2>
			<?php if ( 0 === (int) $outcomes_rollup['total'] ) : ?>
				<p class="description cc-empty-line"><?php esc_html_e( 'No applied edits in the last 30 days. Once changes get approved, this card will score them against the success_metrics targets they were queued with.', 'cc-assistant' ); ?></p>
			<?php elseif ( 0 === (int) $outcomes_rollup['measured'] && (int) $outcomes_rollup['pending'] > 0 ) : ?>
				<p class="description cc-empty-line">
					<?php
					printf(
						esc_html__( '%d edit(s) still measuring. Outcomes need at least 7 days of post-edit GSC data before they can be scored.', 'cc-assistant' ),
						(int) $outcomes_rollup['pending']
					);
					?>
				</p>
			<?php else : ?>
				<div class="cc-ww-stats">
					<div class="cc-ww-stat cc-ww-good">
						<div class="cc-ww-num"><?php echo (int) $outcomes_rollup['hit']; ?></div>
						<div class="cc-ww-lbl"><?php esc_html_e( 'Hit target', 'cc-assistant' ); ?></div>
					</div>
					<div class="cc-ww-stat cc-ww-partial">
						<div class="cc-ww-num"><?php echo (int) $outcomes_rollup['partial']; ?></div>
						<div class="cc-ww-lbl"><?php esc_html_e( 'Partial', 'cc-assistant' ); ?></div>
					</div>
					<div class="cc-ww-stat cc-ww-warn">
						<div class="cc-ww-num"><?php echo (int) $outcomes_rollup['missed']; ?></div>
						<div class="cc-ww-lbl"><?php esc_html_e( 'Missed', 'cc-assistant' ); ?></div>
					</div>
					<div class="cc-ww-stat cc-ww-bottleneck">
						<div class="cc-ww-num"><?php echo (int) $outcomes_rollup['bottleneck']; ?></div>
						<div class="cc-ww-lbl"><?php esc_html_e( 'Meta-tag bottleneck', 'cc-assistant' ); ?></div>
					</div>
					<div class="cc-ww-stat cc-ww-muted">
						<div class="cc-ww-num"><?php echo (int) $outcomes_rollup['no_metrics']; ?></div>
						<div class="cc-ww-lbl"><?php esc_html_e( 'No metrics set', 'cc-assistant' ); ?></div>
					</div>
				</div>

				<?php if ( ! empty( $outcomes_rollup['top_bottleneck'] ) ) : ?>
					<div class="cc-ww-callout">
						<h3><?php esc_html_e( 'Bottlenecked: body landed, snippet did not', 'cc-assistant' ); ?></h3>
						<ul class="cc-ww-list">
							<?php foreach ( array_slice( $outcomes_rollup['top_bottleneck'], 0, 3 ) as $row ) : ?>
								<li>
									<strong><?php echo esc_html( $row['change_summary'] ); ?></strong><br>
									<span class="cc-ww-query"><?php echo esc_html( $row['target_query'] ); ?></span>
									<span class="cc-ww-message"><?php echo esc_html( $row['message'] ); ?></span>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $row['post_id'] . '&action=edit' ) ); ?>" class="cc-ww-edit"><?php esc_html_e( 'Open post', 'cc-assistant' ); ?> &rarr;</a>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="description"><?php esc_html_e( 'These edits moved the page where you wanted it, but the title or description is still leaking clicks. Propose a draft_update_seo_meta change to close the gap.', 'cc-assistant' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $outcomes_rollup['top_hits'] ) && empty( $outcomes_rollup['top_bottleneck'] ) ) : ?>
					<div class="cc-ww-callout cc-ww-callout-good">
						<h3><?php esc_html_e( 'Recent wins', 'cc-assistant' ); ?></h3>
						<ul class="cc-ww-list">
							<?php foreach ( array_slice( $outcomes_rollup['top_hits'], 0, 3 ) as $row ) : ?>
								<li>
									<strong><?php echo esc_html( $row['change_summary'] ); ?></strong><br>
									<span class="cc-ww-query"><?php echo esc_html( $row['target_query'] ); ?></span>
									<span class="cc-ww-message"><?php echo esc_html( $row['message'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="cc-card">
			<h2><?php esc_html_e( 'Quick actions', 'cc-assistant' ); ?></h2>
			<ul class="cc-action-list">
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-pending' ) ); ?>">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Review pending changes', 'cc-assistant' ); ?></span>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=citations' ) ); ?>">
						<span class="dashicons dashicons-shield"></span>
						<span><?php esc_html_e( 'Adjust citation rules', 'cc-assistant' ); ?></span>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=connection' ) ); ?>">
						<span class="dashicons dashicons-admin-plugins"></span>
						<span><?php esc_html_e( 'Reconnect Claude Code', 'cc-assistant' ); ?></span>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-onboarding' ) ); ?>">
						<span class="dashicons dashicons-admin-tools"></span>
						<span><?php esc_html_e( 'Setup wizard', 'cc-assistant' ); ?></span>
					</a>
				</li>
			</ul>
		</div>

		<?php if ( ! $onboarded ) : ?>
		<div class="cc-card cc-safety-card">
			<h2><?php esc_html_e( 'Built-in safety', 'cc-assistant' ); ?></h2>
			<ul class="cc-safety-list">
				<li><span class="dashicons dashicons-shield"></span> <span><?php esc_html_e( 'Never publishes. Every change waits for your approval.', 'cc-assistant' ); ?></span></li>
				<li><span class="dashicons dashicons-shield"></span> <span><?php esc_html_e( 'Never touches files outside this plugin.', 'cc-assistant' ); ?></span></li>
				<li><span class="dashicons dashicons-shield"></span> <span><?php esc_html_e( 'Snapshots before every write. One-click rollback.', 'cc-assistant' ); ?></span></li>
				<li><span class="dashicons dashicons-shield"></span> <span><?php esc_html_e( 'Sees only the post types you allow.', 'cc-assistant' ); ?></span></li>
				<li><span class="dashicons dashicons-shield"></span> <span><?php esc_html_e( 'Cites only .gov, .edu, and your trusted authority list.', 'cc-assistant' ); ?></span></li>
			</ul>
		</div>
		<?php endif; ?>
	</div>
</div>

<?php
/**
 * Page Performance admin view. Variables provided by the caller
 * (CC_Assistant_Performance_Tracker::render_admin_page):
 *
 *   $rows         array  Each row: post_id, title, permalink, edit_url,
 *                        clicks, impressions, ctr, position, trend,
 *                        impression_delta, prev_clicks, prev_impressions,
 *                        prev_position, position_delta, top_query (assoc).
 *   $days         int    Date range in days (1-90).
 *   $sort         string Column key being sorted.
 *   $dir          string asc|desc.
 *   $trend_filter string all|rise|decay|flat|new.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// View vars come from include scope in CC_Assistant_Performance_Tracker::render_admin_page().
// Defensive defaults so a stray direct include (or static-analysis tooling)
// never trips on undefined-variable notices.
$rows         = isset( $rows ) && is_array( $rows ) ? $rows : array();
$days         = isset( $days ) ? (int) $days : 7;
$sort         = isset( $sort ) ? (string) $sort : 'clicks';
$dir          = isset( $dir ) && 'asc' === $dir ? 'asc' : 'desc';
$trend_filter = isset( $trend_filter ) ? (string) $trend_filter : 'all';

$base_url = admin_url( 'admin.php?page=' . CC_Assistant_Performance_Tracker::MENU_SLUG );

$sort_link = function ( $col ) use ( $base_url, $days, $sort, $dir, $trend_filter ) {
	$new_dir = ( $sort === $col && 'desc' === $dir ) ? 'asc' : 'desc';
	return esc_url(
		add_query_arg(
			array(
				'cc_days'  => $days,
				'cc_sort'  => $col,
				'cc_dir'   => $new_dir,
				'cc_trend' => $trend_filter,
			),
			$base_url
		)
	);
};
$sort_indicator = function ( $col ) use ( $sort, $dir ) {
	if ( $sort !== $col ) {
		return '';
	}
	return 'desc' === $dir ? ' ↓' : ' ↑';
};

$total_clicks = 0;
$total_imp    = 0;
foreach ( $rows as $r ) {
	$total_clicks += (int) $r['clicks'];
	$total_imp    += (int) $r['impressions'];
}
$avg_ctr = $total_imp > 0 ? ( $total_clicks / $total_imp ) : 0;

// Drilldown detection: ?cc_post=N → render the per-page query breakdown
// AT THE TOP of the view, then the main table stays below for reference.
$drill_id   = isset( $_GET['cc_post'] ) ? (int) $_GET['cc_post'] : 0;
$drill_post = $drill_id > 0 ? get_post( $drill_id ) : null;

// v0.33.1 — query-level position decay rows (current 7d vs prior 7d at the
// page+query grain). Surfaces the "page X dropped from pos 8 to pos 25 on
// query Y" pattern that page-level deltas can't catch.
$query_decay  = CC_Assistant_Performance_Tracker::get_query_position_decay( 7, 5, 3, 50 );
$decay_only   = array_filter( $query_decay, function ( $r ) { return 'declined' === $r['direction']; } );
$improve_only = array_filter( $query_decay, function ( $r ) { return 'improved' === $r['direction']; } );
?>
<div class="wrap cc-perf-wrap">
	<h1><?php esc_html_e( 'Page Performance', 'cc-assistant' ); ?></h1>
	<nav class="nav-tab-wrapper" style="margin-bottom:12px;">
		<?php if ( class_exists( 'CC_Assistant_Reports' ) && current_user_can( CC_Assistant_Reports::CAP ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-reports' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Reports', 'cc-assistant' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-performance' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Rankings', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-checkup' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Quality check', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-llm' ) ); ?>" class="nav-tab"><?php esc_html_e( 'AI bots', 'cc-assistant' ); ?></a>
	</nav>
	<p class="description">
		<?php esc_html_e( 'Per-page clicks, impressions, position, and week-over-week deltas. Data comes from your local Search Console cache (synced daily by cron).', 'cc-assistant' ); ?>
	</p>

	<?php
	/*
	 * v0.64.0 — this screen used to render 200 rows of "0 clicks / 0 impressions
	 * / — position" whenever the Search Console cache was empty, with no hint
	 * that the cause was a missing connection rather than a dead site. Reuse the
	 * Reports data-health check so the zeros are always explained.
	 */
	$cc_health = class_exists( 'CC_Assistant_Reports' ) ? CC_Assistant_Reports::data_health( $days ) : array( 'reason' => 'ok' );
	if ( isset( $cc_health['reason'] ) && 'ok' !== $cc_health['reason'] ) :
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'The numbers below are not real yet.', 'cc-assistant' ); ?></strong><br>
				<?php
				if ( 'not_configured' === $cc_health['reason'] || 'not_connected' === $cc_health['reason'] ) {
					esc_html_e( 'Search Console is not connected, so every page shows zero. That is a missing connection, not zero traffic.', 'cc-assistant' );
				} elseif ( 'no_rows' === $cc_health['reason'] ) {
					esc_html_e( 'Search Console is connected but the first sync has not finished. Figures stay at zero until it completes.', 'cc-assistant' );
				} else {
					esc_html_e( 'There is no Search Console data inside the selected range. Try a longer range.', 'cc-assistant' );
				}
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-reports' ) ); ?>"><?php esc_html_e( 'Open Reports', 'cc-assistant' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=gsc' ) ); ?>"><?php esc_html_e( 'Search Console settings', 'cc-assistant' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php // v0.33.1 Query-level position changes — rendered above the page table since this is most-actionable for active monitoring. ?>
	<?php if ( ! empty( $query_decay ) ) : ?>
		<div class="cc-card cc-card-wide cc-qdecay">
			<h2 style="margin:0 0 8px;">
				<?php esc_html_e( 'Query-level position changes', 'cc-assistant' ); ?>
				<span class="cc-qdecay-meta">
					<?php
					printf(
						/* translators: 1: declined count 2: improved count */
						esc_html__( '%1$d declined · %2$d improved (last 7d vs prior 7d)', 'cc-assistant' ),
						count( $decay_only ),
						count( $improve_only )
					);
					?>
				</span>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Per-query position shifts of ≥5 positions, on (page, query) pairs with at least 3 impressions in each week. Captures rank decline even when total page clicks/impressions look flat.', 'cc-assistant' ); ?>
			</p>

			<?php if ( ! empty( $decay_only ) ) : ?>
				<h3 style="color:#c62828; margin:16px 0 6px;">▼ <?php esc_html_e( 'Declined queries', 'cc-assistant' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Query', 'cc-assistant' ); ?></th>
							<th><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
							<th class="cc-col-num"><?php esc_html_e( 'Prior pos', 'cc-assistant' ); ?></th>
							<th class="cc-col-num"><?php esc_html_e( 'Current pos', 'cc-assistant' ); ?></th>
							<th class="cc-col-num"><?php esc_html_e( 'Δ', 'cc-assistant' ); ?></th>
							<th class="cc-col-num"><?php esc_html_e( 'Impr (prior · current)', 'cc-assistant' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $decay_only as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['query'] ); ?></strong></td>
								<td>
									<?php if ( $row['post_id'] > 0 ) : ?>
										<a href="<?php echo esc_url( add_query_arg( 'cc_post', $row['post_id'], $base_url ) ); ?>">
											<?php echo esc_html( $row['post_title'] ?: 'Post ' . $row['post_id'] ); ?>
										</a>
									<?php else : ?>
										<code><?php echo esc_html( str_replace( array( 'https://', 'http://' ), '', $row['page'] ) ); ?></code>
									<?php endif; ?>
								</td>
								<td class="cc-col-num"><?php echo esc_html( number_format( $row['prior_position'], 1 ) ); ?></td>
								<td class="cc-col-num"><strong><?php echo esc_html( number_format( $row['current_position'], 1 ) ); ?></strong></td>
								<td class="cc-col-num cc-delta-neg">
									<strong>▼<?php echo esc_html( number_format( $row['position_delta'], 1 ) ); ?></strong>
								</td>
								<td class="cc-col-num">
									<?php echo (int) $row['prior_impressions']; ?> · <?php echo (int) $row['current_impressions']; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $improve_only ) ) : ?>
				<h3 style="color:#2e7d32; margin:16px 0 6px;">▲ <?php esc_html_e( 'Improved queries', 'cc-assistant' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Query', 'cc-assistant' ); ?></th>
							<th><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
							<th class="cc-col-num"><?php esc_html_e( 'Prior pos', 'cc-assistant' ); ?></th>
							<th class="cc-col-num"><?php esc_html_e( 'Current pos', 'cc-assistant' ); ?></th>
							<th class="cc-col-num"><?php esc_html_e( 'Δ', 'cc-assistant' ); ?></th>
							<th class="cc-col-num"><?php esc_html_e( 'Impr (prior · current)', 'cc-assistant' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $improve_only as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['query'] ); ?></strong></td>
								<td>
									<?php if ( $row['post_id'] > 0 ) : ?>
										<a href="<?php echo esc_url( add_query_arg( 'cc_post', $row['post_id'], $base_url ) ); ?>">
											<?php echo esc_html( $row['post_title'] ?: 'Post ' . $row['post_id'] ); ?>
										</a>
									<?php else : ?>
										<code><?php echo esc_html( str_replace( array( 'https://', 'http://' ), '', $row['page'] ) ); ?></code>
									<?php endif; ?>
								</td>
								<td class="cc-col-num"><?php echo esc_html( number_format( $row['prior_position'], 1 ) ); ?></td>
								<td class="cc-col-num"><strong><?php echo esc_html( number_format( $row['current_position'], 1 ) ); ?></strong></td>
								<td class="cc-col-num cc-delta-pos">
									<strong>▲<?php echo esc_html( number_format( abs( $row['position_delta'] ), 1 ) ); ?></strong>
								</td>
								<td class="cc-col-num">
									<?php echo (int) $row['prior_impressions']; ?> · <?php echo (int) $row['current_impressions']; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<hr style="margin:32px 0;">
	<?php endif; ?>

	<?php if ( $drill_id > 0 && $drill_post ) : ?>
		<?php $queries = CC_Assistant_Performance_Tracker::get_post_queries( $drill_id, $days ); ?>
		<div class="cc-perf-drilldown cc-perf-drilldown-top">
			<div class="cc-perf-drilldown-header">
				<h2>
					<?php
					/* translators: 1: post title 2: days */
					printf( esc_html__( 'Queries for "%1$s" (last %2$d days)', 'cc-assistant' ), esc_html( $drill_post->post_title ), (int) $days );
					?>
				</h2>
				<a href="<?php echo esc_url( remove_query_arg( 'cc_post' ) ); ?>" class="button button-primary">
					← <?php esc_html_e( 'Back to all pages', 'cc-assistant' ); ?>
				</a>
			</div>
			<p>
				<a href="<?php echo esc_url( get_permalink( $drill_id ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( get_permalink( $drill_id ) ); ?>
				</a>
				·
				<a href="<?php echo esc_url( get_edit_post_link( $drill_id, 'raw' ) ); ?>">
					<?php esc_html_e( 'Edit post', 'cc-assistant' ); ?>
				</a>
			</p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Query', 'cc-assistant' ); ?></th>
						<th class="cc-col-num"><?php esc_html_e( 'Impressions', 'cc-assistant' ); ?></th>
						<th class="cc-col-num"><?php esc_html_e( 'Clicks', 'cc-assistant' ); ?></th>
						<th class="cc-col-num"><?php esc_html_e( 'CTR', 'cc-assistant' ); ?></th>
						<th class="cc-col-num"><?php esc_html_e( 'Avg Pos', 'cc-assistant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $queries ) ) : ?>
						<tr><td colspan="5" style="text-align:center;padding:24px;"><?php esc_html_e( 'No queries for this page in the selected window.', 'cc-assistant' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $queries as $q ) : ?>
						<?php $q_ctr = (int) $q['impressions'] > 0 ? ( (int) $q['clicks'] / (int) $q['impressions'] ) : 0; ?>
						<tr>
							<td><?php echo esc_html( $q['query'] ); ?></td>
							<td class="cc-col-num"><?php echo number_format_i18n( (int) $q['impressions'] ); ?></td>
							<td class="cc-col-num"><?php echo number_format_i18n( (int) $q['clicks'] ); ?></td>
							<td class="cc-col-num"><?php echo esc_html( number_format( $q_ctr * 100, 2 ) ); ?>%</td>
							<td class="cc-col-num"><?php echo esc_html( number_format( (float) $q['position'], 1 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<hr style="margin:32px 0;">
		<h2 class="cc-perf-section-h2"><?php esc_html_e( 'All pages', 'cc-assistant' ); ?></h2>
	<?php endif; ?>

	<form method="get" action="" class="cc-perf-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( CC_Assistant_Performance_Tracker::MENU_SLUG ); ?>">
		<input type="hidden" name="cc_sort" value="<?php echo esc_attr( $sort ); ?>">
		<input type="hidden" name="cc_dir" value="<?php echo esc_attr( $dir ); ?>">

		<label>
			<?php esc_html_e( 'Range', 'cc-assistant' ); ?>:
			<select name="cc_days" onchange="this.form.submit()">
				<option value="7" <?php selected( $days, 7 ); ?>>7 days</option>
				<option value="14" <?php selected( $days, 14 ); ?>>14 days</option>
				<option value="28" <?php selected( $days, 28 ); ?>>28 days</option>
				<option value="90" <?php selected( $days, 90 ); ?>>90 days</option>
			</select>
		</label>

		<label>
			<?php esc_html_e( 'Trend', 'cc-assistant' ); ?>:
			<select name="cc_trend" onchange="this.form.submit()">
				<option value="all" <?php selected( $trend_filter, 'all' ); ?>><?php esc_html_e( 'All', 'cc-assistant' ); ?></option>
				<option value="rise" <?php selected( $trend_filter, 'rise' ); ?>><?php esc_html_e( 'Rising ▲', 'cc-assistant' ); ?></option>
				<option value="decay" <?php selected( $trend_filter, 'decay' ); ?>><?php esc_html_e( 'Decaying ▼', 'cc-assistant' ); ?></option>
				<option value="flat" <?php selected( $trend_filter, 'flat' ); ?>><?php esc_html_e( 'Flat →', 'cc-assistant' ); ?></option>
				<option value="new" <?php selected( $trend_filter, 'new' ); ?>><?php esc_html_e( 'New ◇', 'cc-assistant' ); ?></option>
			</select>
		</label>
	</form>

	<div class="cc-perf-summary">
		<span><strong><?php echo number_format_i18n( count( $rows ) ); ?></strong> <?php esc_html_e( 'pages shown', 'cc-assistant' ); ?></span>
		<span><strong><?php echo number_format_i18n( $total_clicks ); ?></strong> <?php esc_html_e( 'total clicks', 'cc-assistant' ); ?></span>
		<span><strong><?php echo number_format_i18n( $total_imp ); ?></strong> <?php esc_html_e( 'total impressions', 'cc-assistant' ); ?></span>
		<span><strong><?php echo number_format( $avg_ctr * 100, 2 ); ?>%</strong> <?php esc_html_e( 'avg CTR', 'cc-assistant' ); ?></span>
	</div>

	<div class="cc-table-scroll"><table class="wp-list-table widefat fixed striped cc-perf-table">
		<thead>
			<tr>
				<th class="cc-col-title"><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
				<th class="cc-col-num">
					<a href="<?php echo $sort_link( 'clicks' ); ?>"><?php esc_html_e( 'Clicks', 'cc-assistant' ); ?><?php echo $sort_indicator( 'clicks' ); ?></a>
				</th>
				<th class="cc-col-num">
					<a href="<?php echo $sort_link( 'impressions' ); ?>"><?php esc_html_e( 'Impressions', 'cc-assistant' ); ?><?php echo $sort_indicator( 'impressions' ); ?></a>
				</th>
				<th class="cc-col-num">
					<a href="<?php echo $sort_link( 'ctr' ); ?>"><?php esc_html_e( 'CTR', 'cc-assistant' ); ?><?php echo $sort_indicator( 'ctr' ); ?></a>
				</th>
				<th class="cc-col-num">
					<a href="<?php echo $sort_link( 'position' ); ?>"><?php esc_html_e( 'Avg Pos', 'cc-assistant' ); ?><?php echo $sort_indicator( 'position' ); ?></a>
				</th>
				<th class="cc-col-num">
					<a href="<?php echo $sort_link( 'position_delta' ); ?>"
						title="<?php esc_attr_e( 'Rank change vs prior period. ▲ = improved (lower number is better). ▼ = declined.', 'cc-assistant' ); ?>">
						<?php esc_html_e( 'Δ Pos', 'cc-assistant' ); ?><?php echo $sort_indicator( 'position_delta' ); ?>
					</a>
				</th>
				<th class="cc-col-num">
					<a href="<?php echo $sort_link( 'impression_delta' ); ?>"><?php esc_html_e( 'Δ Impr', 'cc-assistant' ); ?><?php echo $sort_indicator( 'impression_delta' ); ?></a>
				</th>
				<th class="cc-col-trend"><?php esc_html_e( 'Trend', 'cc-assistant' ); ?></th>
				<th class="cc-col-query"><?php esc_html_e( 'Top query', 'cc-assistant' ); ?></th>
				<th class="cc-col-actions"><?php esc_html_e( 'Actions', 'cc-assistant' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="10" style="text-align:center;padding:32px;"><?php esc_html_e( 'No pages match the current filter.', 'cc-assistant' ); ?></td></tr>
			<?php endif; ?>

			<?php foreach ( $rows as $r ) : ?>
				<?php $pd = CC_Assistant_Performance_Tracker::render_position_delta( isset( $r['position_delta'] ) ? $r['position_delta'] : null ); ?>
			<tr>
				<td class="cc-col-title">
					<strong><a href="<?php echo esc_url( $r['edit_url'] ); ?>"><?php echo esc_html( $r['title'] ); ?></a></strong>
					<br>
					<a href="<?php echo esc_url( $r['permalink'] ); ?>" target="_blank" rel="noopener noreferrer" class="cc-perf-permalink">
						<?php echo esc_html( str_replace( array( 'https://', 'http://' ), '', (string) $r['permalink'] ) ); ?>
					</a>
				</td>
				<td class="cc-col-num"><?php echo number_format_i18n( (int) $r['clicks'] ); ?></td>
				<td class="cc-col-num"><?php echo number_format_i18n( (int) $r['impressions'] ); ?></td>
				<td class="cc-col-num">
					<?php echo $r['impressions'] > 0 ? esc_html( number_format( $r['ctr'] * 100, 2 ) ) . '%' : '—'; ?>
				</td>
				<td class="cc-col-num">
					<?php
					if ( $r['impressions'] > 0 ) {
						echo esc_html( number_format( $r['position'], 1 ) );
						if ( ! empty( $r['prev_position'] ) ) {
							echo ' <span class="cc-perf-was">(was ' . esc_html( number_format( $r['prev_position'], 1 ) ) . ')</span>';
						}
					} else {
						echo '—';
					}
					?>
				</td>
				<td class="cc-col-num cc-<?php echo esc_attr( $pd['class'] ); ?>">
					<?php echo $pd['html'] ?: '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper builds via esc_*. ?>
				</td>
				<td class="cc-col-num cc-delta-<?php echo $r['impression_delta'] >= 0 ? 'pos' : 'neg'; ?>">
					<?php echo ( $r['impression_delta'] >= 0 ? '+' : '' ) . number_format_i18n( (int) $r['impression_delta'] ); ?>
				</td>
				<td class="cc-col-trend">
					<span class="cc-trend cc-trend-<?php echo esc_attr( $r['trend'] ); ?>">
						<?php echo esc_html( CC_Assistant_Performance_Tracker::trend_arrow( $r['trend'] ) ); ?>
					</span>
				</td>
				<td class="cc-col-query">
					<?php if ( ! empty( $r['top_query'] ) ) : ?>
						<span class="cc-perf-query"><?php echo esc_html( $r['top_query']['query'] ); ?></span>
						<br>
						<span class="cc-perf-query-meta"><?php echo (int) $r['top_query']['impressions']; ?> imp · pos <?php echo esc_html( $r['top_query']['position'] ); ?></span>
					<?php else : ?>
						—
					<?php endif; ?>
				</td>
				<td class="cc-col-actions">
					<a href="<?php echo esc_url( add_query_arg( array( 'cc_post' => $r['post_id'] ), $base_url ) ); ?>" class="button button-small">
						<?php esc_html_e( 'View queries', 'cc-assistant' ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table></div>
</div>

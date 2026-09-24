<?php
/**
 * Reports view (v0.64.0). Variables provided by the caller
 * (CC_Assistant_Reports::render_admin_page):
 *
 *   $days     int    Selected window.
 *   $view     string overview|pages|activity.
 *   $health   array  Data-health snapshot (see CC_Assistant_Reports::data_health).
 *   $summary  array|null  Site totals (overview only).
 *   $pages    array  Per-page rows.
 *   $activity array  Per-post applied-change rollup.
 *   $drill    array|null  Single-page report when ?cc_post=N.
 *   $drill_id int
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$days     = isset( $days ) ? (int) $days : CC_Assistant_Reports::DEFAULT_DAYS;
$view     = isset( $view ) ? (string) $view : 'overview';
$health   = isset( $health ) && is_array( $health ) ? $health : array();
$summary  = isset( $summary ) && is_array( $summary ) ? $summary : null;
$pages    = isset( $pages ) && is_array( $pages ) ? $pages : array();
$activity = isset( $activity ) && is_array( $activity ) ? $activity : array();
$drill    = isset( $drill ) && is_array( $drill ) ? $drill : null;
$drill_id = isset( $drill_id ) ? (int) $drill_id : 0;

$base_url = admin_url( 'admin.php?page=' . CC_Assistant_Reports::MENU_SLUG );

$view_url = function ( $v ) use ( $base_url, $days ) {
	return esc_url( add_query_arg( array( 'cc_view' => $v, 'cc_days' => $days ), $base_url ) );
};

/** Signed number with a +/- prefix. */
$signed = function ( $n, $decimals = 0 ) {
	$n = (float) $n;
	$s = number_format_i18n( $n, $decimals );
	return ( $n > 0 ? '+' : '' ) . $s;
};
$delta_class = function ( $n ) {
	if ( (float) $n > 0 ) {
		return 'cc-rep-up';
	}
	if ( (float) $n < 0 ) {
		return 'cc-rep-down';
	}
	return 'cc-rep-flat';
};
?>
<div class="wrap cc-assistant cc-reports">
	<h1><?php esc_html_e( 'Reports', 'cc-assistant' ); ?></h1>

	<nav class="nav-tab-wrapper cc-rep-tabs">
		<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Reports', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-performance' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Rankings', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-checkup' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Quality check', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-llm' ) ); ?>" class="nav-tab"><?php esc_html_e( 'AI bots', 'cc-assistant' ); ?></a>
	</nav>

	<?php
	/* ------------------------------------------------------------------
	 * Data health. Never render zeros without saying whether they mean
	 * "no traffic" or "no data" — that ambiguity is the whole complaint.
	 * ---------------------------------------------------------------- */
	$reason = isset( $health['reason'] ) ? $health['reason'] : 'ok';
	if ( 'ok' !== $reason ) :
		?>
		<div class="notice notice-warning cc-rep-health">
			<?php if ( 'not_configured' === $reason ) : ?>
				<p>
					<strong><?php esc_html_e( 'Search Console is not set up yet.', 'cc-assistant' ); ?></strong><br>
					<?php esc_html_e( 'Clicks, impressions, and position come from Google Search Console. Until it is connected, this screen can only report the changes made to your site, not their search results.', 'cc-assistant' ); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=gsc' ) ); ?>">
						<?php esc_html_e( 'Set up Search Console', 'cc-assistant' ); ?>
					</a>
				</p>
			<?php elseif ( 'not_connected' === $reason ) : ?>
				<p>
					<strong><?php esc_html_e( 'Search Console is configured but not connected.', 'cc-assistant' ); ?></strong><br>
					<?php esc_html_e( 'The credentials are saved, but the Google account has not been authorised (or the token expired). No search data can be fetched until you reconnect.', 'cc-assistant' ); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=gsc' ) ); ?>">
						<?php esc_html_e( 'Reconnect Search Console', 'cc-assistant' ); ?>
					</a>
				</p>
			<?php elseif ( 'no_rows' === $reason ) : ?>
				<p>
					<strong><?php esc_html_e( 'Connected, but no search data has synced yet.', 'cc-assistant' ); ?></strong><br>
					<?php esc_html_e( 'The first sync runs on a scheduled task and can take a few hours after connecting. Search figures below will stay empty until it completes.', 'cc-assistant' ); ?>
				</p>
			<?php elseif ( 'window_empty' === $reason ) : ?>
				<p>
					<strong><?php esc_html_e( 'No search data inside the selected window.', 'cc-assistant' ); ?></strong><br>
					<?php
					if ( ! empty( $health['latest_date'] ) ) {
						printf(
							/* translators: %s: date in Y-m-d */
							esc_html__( 'The most recent day in your cache is %s, which is older than the range you picked. Choose a longer range, or wait for the next sync.', 'cc-assistant' ),
							esc_html( $health['latest_date'] )
						);
					}
					?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $health['last_error'] ) ) : ?>
				<p><em><?php echo esc_html( sprintf( /* translators: %s: error message */ __( 'Last sync error: %s', 'cc-assistant' ), $health['last_error'] ) ); ?></em></p>
			<?php endif; ?>
		</div>
	<?php elseif ( ! empty( $health['lag_days'] ) && (int) $health['lag_days'] >= 3 ) : ?>
		<div class="notice notice-info cc-rep-health">
			<p>
				<?php
				printf(
					/* translators: 1: number of days 2: date */
					esc_html__( 'Search Console data lags by %1$d days (newest day available: %2$s). Anything changed in the last few days will not show results yet. This is normal Google reporting delay, not a problem with your site.', 'cc-assistant' ),
					(int) $health['lag_days'],
					esc_html( $health['latest_date'] )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php /* ---------------- Controls ---------------- */ ?>
	<div class="cc-rep-controls">
		<form method="get" action="" class="cc-rep-filter">
			<input type="hidden" name="page" value="<?php echo esc_attr( CC_Assistant_Reports::MENU_SLUG ); ?>">
			<input type="hidden" name="cc_view" value="<?php echo esc_attr( $view ); ?>">
			<?php if ( $drill_id > 0 ) : ?>
				<input type="hidden" name="cc_post" value="<?php echo esc_attr( $drill_id ); ?>">
			<?php endif; ?>
			<label>
				<?php esc_html_e( 'Period', 'cc-assistant' ); ?>
				<select name="cc_days" onchange="this.form.submit()">
					<?php foreach ( CC_Assistant_Reports::ranges() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $days, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Apply', 'cc-assistant' ); ?></button></noscript>
		</form>

		<?php if ( $drill_id > 0 && $drill ) : ?>
			<a class="button button-primary" href="<?php echo esc_url( CC_Assistant_Reports::export_url( 'page', $days ) . '&cc_post=' . (int) $drill_id ); ?>">
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
				<?php esc_html_e( 'Export this page (CSV)', 'cc-assistant' ); ?>
			</a>
		<?php else : ?>
			<div class="cc-rep-exports">
				<a class="button" href="<?php echo esc_url( CC_Assistant_Reports::export_url( 'summary', $days ) ); ?>">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Summary CSV', 'cc-assistant' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( CC_Assistant_Reports::export_url( 'pages', $days ) ); ?>">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'All pages CSV', 'cc-assistant' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( CC_Assistant_Reports::export_url( 'activity', $days ) ); ?>">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Changes CSV', 'cc-assistant' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $drill_id > 0 ) : ?>
		<?php /* ================= SINGLE PAGE REPORT ================= */ ?>
		<p><a href="<?php echo esc_url( add_query_arg( array( 'cc_view' => 'pages', 'cc_days' => $days ), $base_url ) ); ?>">&larr; <?php esc_html_e( 'Back to all pages', 'cc-assistant' ); ?></a></p>

		<?php if ( ! $drill ) : ?>
			<div class="cc-card"><p><?php esc_html_e( 'That page no longer exists.', 'cc-assistant' ); ?></p></div>
		<?php else : ?>
			<div class="cc-card">
				<h2><?php echo esc_html( $drill['title'] ); ?></h2>
				<p class="description">
					<a href="<?php echo esc_url( $drill['permalink'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $drill['permalink'] ); ?></a>
					<?php if ( ! empty( $drill['edit_url'] ) ) : ?>
						&middot; <a href="<?php echo esc_url( $drill['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'cc-assistant' ); ?></a>
					<?php endif; ?>
				</p>

				<div class="cc-rep-tiles">
					<?php
					$tiles = array(
						array( __( 'Clicks', 'cc-assistant' ), number_format_i18n( $drill['totals']['clicks'] ), $drill['totals']['clicks'] - $drill['previous']['clicks'], 0 ),
						array( __( 'Impressions', 'cc-assistant' ), number_format_i18n( $drill['totals']['impressions'] ), $drill['totals']['impressions'] - $drill['previous']['impressions'], 0 ),
						array( __( 'CTR', 'cc-assistant' ), number_format_i18n( $drill['totals']['ctr'] * 100, 2 ) . '%', ( $drill['totals']['ctr'] - $drill['previous']['ctr'] ) * 100, 2 ),
						array( __( 'Avg position', 'cc-assistant' ), $drill['totals']['impressions'] > 0 ? number_format_i18n( $drill['totals']['position'], 1 ) : '—', $drill['previous']['position'] > 0 ? ( $drill['previous']['position'] - $drill['totals']['position'] ) : 0, 1 ),
					);
					foreach ( $tiles as $t ) :
						?>
						<div class="cc-rollup-stat">
							<div class="cc-rollup-num"><?php echo esc_html( $t[1] ); ?></div>
							<div class="cc-rollup-lbl"><?php echo esc_html( $t[0] ); ?></div>
							<div class="cc-rep-delta <?php echo esc_attr( $delta_class( $t[2] ) ); ?>">
								<?php echo esc_html( $signed( $t[2], $t[3] ) ); ?>
								<span class="cc-rep-vs"><?php esc_html_e( 'vs prev', 'cc-assistant' ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
					<div class="cc-rollup-stat">
						<div class="cc-rollup-num"><?php echo esc_html( number_format_i18n( count( $drill['edits'] ) ) ); ?></div>
						<div class="cc-rollup-lbl"><?php esc_html_e( 'Changes applied', 'cc-assistant' ); ?></div>
					</div>
					<?php if ( $drill['leads'] > 0 ) : ?>
						<div class="cc-rollup-stat cc-rollup-good">
							<div class="cc-rollup-num"><?php echo esc_html( number_format_i18n( $drill['leads'] ) ); ?></div>
							<div class="cc-rollup-lbl"><?php esc_html_e( 'Form leads', 'cc-assistant' ); ?></div>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="cc-card">
				<h2><?php esc_html_e( 'Search queries', 'cc-assistant' ); ?></h2>
				<?php if ( empty( $drill['queries'] ) ) : ?>
					<p class="description"><?php esc_html_e( 'No search queries recorded for this page in this period.', 'cc-assistant' ); ?></p>
				<?php else : ?>
					<div class="cc-table-scroll"><table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Query', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Clicks', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Impressions', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'CTR', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Avg position', 'cc-assistant' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $drill['queries'] as $q ) : ?>
								<?php $qi = (int) $q['impressions']; ?>
								<tr>
									<td><strong><?php echo esc_html( $q['query'] ); ?></strong></td>
									<td><?php echo esc_html( number_format_i18n( (int) $q['clicks'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $qi ) ); ?></td>
									<td><?php echo $qi > 0 ? esc_html( number_format_i18n( ( (int) $q['clicks'] / $qi ) * 100, 2 ) . '%' ) : '—'; ?></td>
									<td><?php echo esc_html( number_format_i18n( (float) $q['position'], 1 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>
			</div>

			<div class="cc-card">
				<h2><?php esc_html_e( 'What we changed on this page', 'cc-assistant' ); ?></h2>
				<?php if ( empty( $drill['edits'] ) ) : ?>
					<p class="description"><?php esc_html_e( 'No changes were applied to this page in this period.', 'cc-assistant' ); ?></p>
				<?php else : ?>
					<div class="cc-table-scroll"><table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width:150px;"><?php esc_html_e( 'Applied', 'cc-assistant' ); ?></th>
								<th style="width:170px;"><?php esc_html_e( 'Type', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Change', 'cc-assistant' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $drill['edits'] as $e ) : ?>
								<tr>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $e['applied_at'] ) ); ?></td>
									<td><?php echo esc_html( CC_Assistant_Reports::type_label( $e['change_type'] ) ); ?></td>
									<td><?php echo esc_html( $e['change_summary'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<?php /* ================= LIST VIEWS ================= */ ?>
		<nav class="cc-rep-subnav">
			<a href="<?php echo $view_url( 'overview' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url applied in closure. ?>" class="<?php echo 'overview' === $view ? 'cc-rep-sub-active' : ''; ?>"><?php esc_html_e( 'Overview', 'cc-assistant' ); ?></a>
			<a href="<?php echo $view_url( 'pages' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="<?php echo 'pages' === $view ? 'cc-rep-sub-active' : ''; ?>"><?php esc_html_e( 'By page', 'cc-assistant' ); ?></a>
			<a href="<?php echo $view_url( 'activity' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="<?php echo 'activity' === $view ? 'cc-rep-sub-active' : ''; ?>"><?php esc_html_e( 'Changes made', 'cc-assistant' ); ?></a>
		</nav>

		<?php if ( 'overview' === $view && $summary ) : ?>
			<div class="cc-card">
				<h2>
					<?php
					printf(
						/* translators: 1: start date 2: end date */
						esc_html__( 'Site totals, %1$s to %2$s', 'cc-assistant' ),
						esc_html( $summary['start'] ),
						esc_html( $summary['end'] )
					);
					?>
				</h2>
				<p class="description"><?php esc_html_e( 'Every published page combined, compared with the previous period of the same length.', 'cc-assistant' ); ?></p>
				<div class="cc-rep-tiles">
					<div class="cc-rollup-stat">
						<div class="cc-rollup-num"><?php echo esc_html( number_format_i18n( $summary['current']['clicks'] ) ); ?></div>
						<div class="cc-rollup-lbl"><?php esc_html_e( 'Clicks', 'cc-assistant' ); ?></div>
						<div class="cc-rep-delta <?php echo esc_attr( $delta_class( $summary['delta']['clicks'] ) ); ?>"><?php echo esc_html( $signed( $summary['delta']['clicks'] ) ); ?> <span class="cc-rep-vs"><?php esc_html_e( 'vs prev', 'cc-assistant' ); ?></span></div>
					</div>
					<div class="cc-rollup-stat">
						<div class="cc-rollup-num"><?php echo esc_html( number_format_i18n( $summary['current']['impressions'] ) ); ?></div>
						<div class="cc-rollup-lbl"><?php esc_html_e( 'Impressions', 'cc-assistant' ); ?></div>
						<div class="cc-rep-delta <?php echo esc_attr( $delta_class( $summary['delta']['impressions'] ) ); ?>"><?php echo esc_html( $signed( $summary['delta']['impressions'] ) ); ?> <span class="cc-rep-vs"><?php esc_html_e( 'vs prev', 'cc-assistant' ); ?></span></div>
					</div>
					<div class="cc-rollup-stat">
						<div class="cc-rollup-num"><?php echo esc_html( number_format_i18n( $summary['current']['ctr'] * 100, 2 ) ); ?>%</div>
						<div class="cc-rollup-lbl"><?php esc_html_e( 'Click-through rate', 'cc-assistant' ); ?></div>
						<div class="cc-rep-delta <?php echo esc_attr( $delta_class( $summary['delta']['ctr'] ) ); ?>"><?php echo esc_html( $signed( $summary['delta']['ctr'] * 100, 2 ) ); ?> <span class="cc-rep-vs"><?php esc_html_e( 'points', 'cc-assistant' ); ?></span></div>
					</div>
					<div class="cc-rollup-stat">
						<div class="cc-rollup-num"><?php echo $summary['current']['impressions'] > 0 ? esc_html( number_format_i18n( $summary['current']['position'], 1 ) ) : '—'; ?></div>
						<div class="cc-rollup-lbl"><?php esc_html_e( 'Average position', 'cc-assistant' ); ?></div>
						<div class="cc-rep-delta <?php echo esc_attr( $delta_class( $summary['delta']['position'] ) ); ?>"><?php echo esc_html( $signed( $summary['delta']['position'], 1 ) ); ?> <span class="cc-rep-vs"><?php esc_html_e( 'places', 'cc-assistant' ); ?></span></div>
					</div>
					<div class="cc-rollup-stat">
						<div class="cc-rollup-num"><?php echo esc_html( number_format_i18n( $summary['edits']['total'] ) ); ?></div>
						<div class="cc-rollup-lbl"><?php esc_html_e( 'Changes applied', 'cc-assistant' ); ?></div>
						<div class="cc-rep-delta cc-rep-flat">
							<?php
							printf(
								/* translators: %s: number of pages */
								esc_html__( 'across %s pages', 'cc-assistant' ),
								esc_html( number_format_i18n( $summary['edits']['posts'] ) )
							);
							?>
						</div>
					</div>
					<?php if ( null !== $summary['leads'] ) : ?>
						<div class="cc-rollup-stat<?php echo $summary['leads'] > 0 ? ' cc-rollup-good' : ''; ?>">
							<div class="cc-rollup-num"><?php echo esc_html( number_format_i18n( $summary['leads'] ) ); ?></div>
							<div class="cc-rollup-lbl"><?php esc_html_e( 'Form leads', 'cc-assistant' ); ?></div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'overview' === $view || 'pages' === $view ) : ?>
			<div class="cc-card">
				<h2>
					<?php echo 'overview' === $view ? esc_html__( 'Top pages', 'cc-assistant' ) : esc_html__( 'Every page with search data', 'cc-assistant' ); ?>
					<?php if ( 'overview' === $view && ! empty( $pages ) ) : ?>
						<a class="cc-card-link" href="<?php echo $view_url( 'pages' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php esc_html_e( 'See all pages', 'cc-assistant' ); ?></a>
					<?php endif; ?>
				</h2>
				<?php if ( empty( $pages ) ) : ?>
					<p class="description"><?php esc_html_e( 'No pages have search data in this period yet.', 'cc-assistant' ); ?></p>
				<?php else : ?>
					<div class="cc-table-scroll"><table class="wp-list-table widefat fixed striped cc-rep-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Clicks', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Impressions', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'CTR', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Avg pos', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Changes', 'cc-assistant' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $pages as $r ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $r['title'] ? $r['title'] : $r['url'] ); ?></strong>
										<br>
										<span class="cc-rep-url"><?php echo esc_html( str_replace( array( 'https://', 'http://' ), '', $r['url'] ) ); ?></span>
									</td>
									<td>
										<?php echo esc_html( number_format_i18n( $r['clicks'] ) ); ?>
										<?php if ( 0 !== $r['clicks_delta'] ) : ?>
											<span class="cc-rep-delta <?php echo esc_attr( $delta_class( $r['clicks_delta'] ) ); ?>"><?php echo esc_html( $signed( $r['clicks_delta'] ) ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( number_format_i18n( $r['impressions'] ) ); ?></td>
									<td><?php echo $r['impressions'] > 0 ? esc_html( number_format_i18n( $r['ctr'] * 100, 2 ) . '%' ) : '—'; ?></td>
									<td>
										<?php echo $r['position'] > 0 ? esc_html( number_format_i18n( $r['position'], 1 ) ) : '—'; ?>
										<?php if ( null !== $r['position_delta'] && abs( $r['position_delta'] ) >= 0.1 ) : ?>
											<span class="cc-rep-delta <?php echo esc_attr( $delta_class( $r['position_delta'] ) ); ?>"><?php echo esc_html( $signed( $r['position_delta'], 1 ) ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo $r['edits'] > 0 ? esc_html( number_format_i18n( $r['edits'] ) ) : '—'; ?></td>
									<td>
										<?php if ( $r['post_id'] > 0 ) : ?>
											<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'cc_post' => $r['post_id'], 'cc_days' => $days ), $base_url ) ); ?>">
												<?php esc_html_e( 'Report', 'cc-assistant' ); ?>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( 'overview' === $view || 'activity' === $view ) : ?>
			<div class="cc-card">
				<h2>
					<?php esc_html_e( 'Changes made', 'cc-assistant' ); ?>
					<?php if ( 'overview' === $view && ! empty( $activity ) ) : ?>
						<a class="cc-card-link" href="<?php echo $view_url( 'activity' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php esc_html_e( 'See all', 'cc-assistant' ); ?></a>
					<?php endif; ?>
				</h2>
				<p class="description"><?php esc_html_e( 'One row per page, not per change, so a large batch on a single page cannot hide everything else that happened.', 'cc-assistant' ); ?></p>
				<?php if ( empty( $activity ) ) : ?>
					<p class="description"><?php esc_html_e( 'No changes were applied in this period.', 'cc-assistant' ); ?></p>
				<?php else : ?>
					<div class="cc-table-scroll"><table class="wp-list-table widefat fixed striped cc-rep-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Changes', 'cc-assistant' ); ?></th>
								<th><?php esc_html_e( 'Last applied', 'cc-assistant' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $activity as $a ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $a['title'] ); ?></strong></td>
									<td><?php echo esc_html( number_format_i18n( $a['edit_count'] ) ); ?></td>
									<td>
										<?php
										if ( null === $a['days_since'] ) {
											echo '—';
										} elseif ( 0 === $a['days_since'] ) {
											esc_html_e( 'Today', 'cc-assistant' );
										} else {
											printf(
												/* translators: %s: number of days */
												esc_html( _n( '%s day ago', '%s days ago', $a['days_since'], 'cc-assistant' ) ),
												esc_html( number_format_i18n( $a['days_since'] ) )
											);
										}
										?>
									</td>
									<td>
										<?php if ( $a['exists'] ) : ?>
											<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'cc_post' => $a['post_id'], 'cc_days' => $days ), $base_url ) ); ?>">
												<?php esc_html_e( 'Report', 'cc-assistant' ); ?>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

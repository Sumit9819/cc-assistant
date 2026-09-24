<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Month navigation. Defaults to the current month.
$now      = current_time( 'timestamp' );
$year     = isset( $_GET['cc_year'] ) ? max( 2000, min( 2100, (int) $_GET['cc_year'] ) ) : (int) date( 'Y', $now );
$month    = isset( $_GET['cc_month'] ) ? max( 1, min( 12, (int) $_GET['cc_month'] ) ) : (int) date( 'n', $now );
$first_ts = mktime( 0, 0, 0, $month, 1, $year );
$last_day = (int) date( 't', $first_ts );
$first_dow = (int) date( 'w', $first_ts ); // 0..6 starting Sunday

// Fetch published posts in this month.
$start_dt = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
$end_dt   = sprintf( '%04d-%02d-%02d 23:59:59', $year, $month, $last_day );

$published = get_posts( array(
	'post_type'      => array( 'post', 'page' ),
	'post_status'    => array( 'publish' ),
	'date_query'     => array( array( 'after' => $start_dt, 'before' => $end_dt, 'inclusive' => true ) ),
	'posts_per_page' => 200,
) );
$scheduled = get_posts( array(
	'post_type'      => array( 'post', 'page' ),
	'post_status'    => array( 'future' ),
	'date_query'     => array( array( 'after' => $start_dt, 'before' => $end_dt, 'inclusive' => true ) ),
	'posts_per_page' => 200,
) );

// Refresh queue: cached read-through. The actual computation
// (trends_summary GROUP BYs + url_to_postid lookups per row) is expensive
// — on big GSC tables it can take 5-30s. We cache for 6 hours. On miss
// we schedule an async cron and render the calendar without refresh
// items so the page never blocks.
$refresh_cache_key = 'cc_calendar_refresh_queue';
$refresh = get_transient( $refresh_cache_key );
$refresh_loading = false;
if ( false === $refresh ) {
	$refresh_loading = true;
	$refresh = array( 'queue' => array() );
	if ( ! wp_next_scheduled( 'cc_assistant_calendar_refresh_warm' ) ) {
		wp_schedule_single_event( time() + 5, 'cc_assistant_calendar_refresh_warm' );
	}
}
// v0.61 UX audit: refresh suggestions used to be scattered onto calendar
// DAYS via crc32(post_id) — fabricated dates rendered indistinguishably
// from real scheduled posts (a doctrine violation: never invent figures).
// They are now a ranked list below the grid, in queue (priority) order.
$refresh_list = array();
foreach ( (array) $refresh['queue'] as $r ) {
	if ( empty( $r['post_id'] ) ) {
		continue;
	}
	$refresh_list[] = $r;
}

$by_day = array();
foreach ( $published as $p ) {
	$d = (int) date( 'j', strtotime( $p->post_date ) );
	$by_day[ $d ]['published'][] = $p;
}
foreach ( $scheduled as $p ) {
	$d = (int) date( 'j', strtotime( $p->post_date ) );
	$by_day[ $d ]['scheduled'][] = $p;
}

$prev_month = $month - 1; $prev_year = $year;
if ( $prev_month < 1 ) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year;
if ( $next_month > 12 ) { $next_month = 1; $next_year++; }
?>
<div class="wrap cc-assistant cc-calendar">
	<h1><?php esc_html_e( 'Editorial calendar', 'cc-assistant' ); ?></h1>
	<nav class="nav-tab-wrapper" style="margin-bottom:12px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-clusters' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Clusters', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-calendar' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Calendar', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-brief' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Brief generator', 'cc-assistant' ); ?></a>
	</nav>
	<p class="description"><?php esc_html_e( 'A read-only month view of published posts, scheduled posts, and the refresh queue. Use it to balance workload and spot weeks that are content-light.', 'cc-assistant' ); ?></p>

	<div class="cc-cal-nav">
		<a class="button" href="<?php echo esc_url( add_query_arg( array( 'cc_year' => $prev_year, 'cc_month' => $prev_month ) ) ); ?>">&larr; <?php esc_html_e( 'Prev', 'cc-assistant' ); ?></a>
		<strong class="cc-cal-title"><?php echo esc_html( date_i18n( 'F Y', $first_ts ) ); ?></strong>
		<a class="button" href="<?php echo esc_url( add_query_arg( array( 'cc_year' => $next_year, 'cc_month' => $next_month ) ) ); ?>"><?php esc_html_e( 'Next', 'cc-assistant' ); ?> &rarr;</a>
		<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-calendar' ) ); ?>"><?php esc_html_e( 'Today', 'cc-assistant' ); ?></a>
	</div>

	<div class="cc-cal-legend">
		<span><span class="cc-cal-dot cc-cal-dot-pub"></span> <?php esc_html_e( 'Published', 'cc-assistant' ); ?></span>
		<span><span class="cc-cal-dot cc-cal-dot-sched"></span> <?php esc_html_e( 'Scheduled', 'cc-assistant' ); ?></span>
		<span><span class="cc-cal-dot cc-cal-dot-refresh"></span> <?php esc_html_e( 'Refresh', 'cc-assistant' ); ?></span>
	</div>

	<?php if ( $refresh_loading ) : ?>
		<div class="notice notice-info inline" style="margin:8px 0;">
			<p><?php esc_html_e( 'Computing refresh suggestions in the background. Reload the page in a minute to see them.', 'cc-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<table class="cc-cal-grid">
		<thead>
			<tr>
				<?php foreach ( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ) as $dow ) : ?>
					<th><?php echo esc_html( $dow ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
		<?php
		$day = 1;
		$row_count = 0;
		while ( $day <= $last_day ) :
			echo '<tr>';
			for ( $col = 0; $col < 7; $col++ ) :
				if ( ( 0 === $row_count && $col < $first_dow ) || $day > $last_day ) {
					echo '<td class="cc-cal-empty"></td>';
					continue;
				}
				$is_today = ( $day === (int) date( 'j', $now ) && $month === (int) date( 'n', $now ) && $year === (int) date( 'Y', $now ) );
				$cell_class = 'cc-cal-cell' . ( $is_today ? ' cc-cal-today' : '' );
				echo '<td class="' . esc_attr( $cell_class ) . '"><div class="cc-cal-daynum">' . (int) $day . '</div>';
				if ( ! empty( $by_day[ $day ]['published'] ) ) {
					foreach ( $by_day[ $day ]['published'] as $p ) {
						printf(
							'<div class="cc-cal-item cc-cal-pub" title="%s"><a href="%s">%s</a></div>',
							esc_attr( $p->post_title ),
							esc_url( get_edit_post_link( $p->ID, 'raw' ) ),
							esc_html( wp_trim_words( $p->post_title, 6 ) )
						);
					}
				}
				if ( ! empty( $by_day[ $day ]['scheduled'] ) ) {
					foreach ( $by_day[ $day ]['scheduled'] as $p ) {
						printf(
							'<div class="cc-cal-item cc-cal-sched" title="%s"><a href="%s">%s</a></div>',
							esc_attr( $p->post_title ),
							esc_url( get_edit_post_link( $p->ID, 'raw' ) ),
							esc_html( wp_trim_words( $p->post_title, 6 ) )
						);
					}
				}
				echo '</td>';
				$day++;
			endfor;
			echo '</tr>';
			$row_count++;
		endwhile;
		?>
		</tbody>
	</table>

	<?php if ( ! empty( $refresh_list ) ) : ?>
		<div class="cc-card" style="margin-top:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Refresh next', 'cc-assistant' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Ranked by priority — no dates, because refreshes have an order, not deadlines. Work from the top.', 'cc-assistant' ); ?></p>
			<ol style="margin:8px 0 0 20px;">
				<?php foreach ( array_slice( $refresh_list, 0, 10 ) as $r ) : ?>
					<li style="margin-bottom:4px;">
						<a href="<?php echo esc_url( $r['edit_url'] ?: '#' ); ?>"><?php echo esc_html( $r['title'] ?: $r['page'] ); ?></a>
						<?php if ( ! empty( $r['reason'] ) ) : ?>
							<span class="description">— <?php echo esc_html( $r['reason'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php elseif ( $refresh_loading ) : ?>
		<p class="description" style="margin-top:14px;"><?php esc_html_e( 'Refresh suggestions are being computed in the background — reload in a minute.', 'cc-assistant' ); ?></p>
	<?php endif; ?>
</div>

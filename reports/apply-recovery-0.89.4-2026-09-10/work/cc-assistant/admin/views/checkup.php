<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_type   = isset( $_GET['cc_pt'] ) ? sanitize_key( $_GET['cc_pt'] ) : 'page';
$post_status = isset( $_GET['cc_status'] ) ? sanitize_key( $_GET['cc_status'] ) : 'publish';
$fails_only  = ! empty( $_GET['cc_fails_only'] );
$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page    = 25;

$allowed_post_types = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
	$post_type = $allowed_post_types[0] ?? 'page';
}

$query = new WP_Query(
	array(
		'post_type'      => $post_type,
		'post_status'    => $post_status,
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	)
);

$results = array();
foreach ( $query->posts as $post ) {
	$check = CC_Assistant_Pre_Publish::check_post( $post->ID );
	if ( is_wp_error( $check ) ) {
		continue;
	}
	if ( $fails_only && ! empty( $check['pass'] ) ) {
		continue;
	}
	$results[] = $check;
}

$total_pages   = (int) $query->max_num_pages;
// found_posts is the site-wide match count. count($query->posts) is just this
// page's slice (<= $per_page), and printing that as the total made a 400-page
// site read as a 25-page one.
$total_matching = (int) $query->found_posts;
$scanned_here   = count( $query->posts );
$post_types    = get_post_types( array( 'public' => true ), 'objects' );

// Friendly label/category/help map lives on CC_Assistant_Pre_Publish so the
// editor-sidebar metabox uses the same source of truth.
$cc_check_meta = CC_Assistant_Pre_Publish::check_meta();

if ( ! function_exists( 'cc_check_label_for' ) ) {
	function cc_check_label_for( $check_name, $meta ) {
		return CC_Assistant_Pre_Publish::label_for( $check_name );
	}
}
?>
<div class="wrap cc-assistant cc-checkup">
	<h1><?php esc_html_e( 'Check up', 'cc-assistant' ); ?></h1>
	<nav class="nav-tab-wrapper" style="margin-bottom:12px;">
		<?php if ( class_exists( 'CC_Assistant_Reports' ) && current_user_can( CC_Assistant_Reports::CAP ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-reports' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Reports', 'cc-assistant' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-performance' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Rankings', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-checkup' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Quality check', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-llm' ) ); ?>" class="nav-tab"><?php esc_html_e( 'AI bots', 'cc-assistant' ); ?></a>
	</nav>
	<p class="cc-tagline"><?php esc_html_e( 'Quality audit per post. See what is failing, copy the post ID, and tell Claude exactly what to fix.', 'cc-assistant' ); ?></p>

	<?php
	require_once CC_ASSISTANT_DIR . 'includes/class-site-audit.php';
	require_once CC_ASSISTANT_DIR . 'includes/class-cannibalization-trends.php';
	$audit_blob = CC_Assistant_Site_Audit::get_blob();
	$trend_dir  = CC_Assistant_Cannibalization_Trends::direction();
	$trend_rows = CC_Assistant_Cannibalization_Trends::rows( 8 );
	?>

	<div class="cc-card cc-card-wide cc-checkup-rollup">
		<h2><?php esc_html_e( 'Site-wide rollup', 'cc-assistant' ); ?>
			<?php if ( $audit_blob ) : ?>
				<span class="description" style="font-weight:normal; margin-left:8px;">
					<?php
					/* translators: %s: human time diff */
					printf( esc_html__( 'updated %s ago', 'cc-assistant' ), esc_html( human_time_diff( (int) $audit_blob['computed_at'], time() ) ) );
					?>
				</span>
			<?php endif; ?>
			<form method="post" style="display:inline; margin-left:12px;">
				<?php wp_nonce_field( 'cc_run_audit', 'cc_run_audit_nonce' ); ?>
				<button type="submit" name="cc_run_audit" value="1" class="button button-small"><?php esc_html_e( 'Re-run now', 'cc-assistant' ); ?></button>
			</form>
		</h2>
		<?php
		if ( isset( $_POST['cc_run_audit'] ) && check_admin_referer( 'cc_run_audit', 'cc_run_audit_nonce' ) ) {
			CC_Assistant_Site_Audit::schedule_now();
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Audit scheduled. Refresh in about a minute.', 'cc-assistant' ) . '</p></div>';
		}
		?>
		<?php if ( ! $audit_blob ) : ?>
			<p class="description"><?php esc_html_e( 'No audit yet. Click "Re-run now" to schedule one — it runs in the background and the page stays responsive.', 'cc-assistant' ); ?></p>
		<?php else :
			$total_posts = (int) $audit_blob['posts_total'];
			$passing     = (int) $audit_blob['posts_passing_all'];
			$pct_pass    = $total_posts > 0 ? round( ( $passing / $total_posts ) * 100 ) : 0;
			?>
			<div class="cc-rollup-grid">
				<div class="cc-rollup-stat">
					<div class="cc-rollup-num"><?php echo (int) $total_posts; ?></div>
					<div class="cc-rollup-lbl"><?php esc_html_e( 'posts audited', 'cc-assistant' ); ?></div>
				</div>
				<div class="cc-rollup-stat cc-rollup-good">
					<div class="cc-rollup-num"><?php echo (int) $passing; ?></div>
					<div class="cc-rollup-lbl"><?php /* translators: %d: percent */ printf( esc_html__( 'passing all (%d%%)', 'cc-assistant' ), $pct_pass ); ?></div>
				</div>
				<div class="cc-rollup-stat cc-rollup-warn">
					<div class="cc-rollup-num"><?php echo (int) ( $total_posts - $passing ); ?></div>
					<div class="cc-rollup-lbl"><?php esc_html_e( 'failing one or more', 'cc-assistant' ); ?></div>
				</div>
			</div>

			<h3 style="margin-top:18px;"><?php esc_html_e( 'Failure rate by check', 'cc-assistant' ); ?></h3>
			<?php
			$by_check = $audit_blob['by_check'];
			uasort( $by_check, function ( $a, $b ) { return $b['fail_count'] - $a['fail_count']; } );
			?>
			<table class="cc-mini-table">
				<thead><tr>
					<th><?php esc_html_e( 'Check', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Failing', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( '%', 'cc-assistant' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $by_check as $name => $b ) :
					if ( 0 === (int) $b['fail_count'] ) {
						continue;
					}
					$total_for_check = (int) $b['fail_count'] + (int) $b['pass_count'];
					$pct = $total_for_check > 0 ? round( ( $b['fail_count'] / $total_for_check ) * 100 ) : 0;
					?>
					<tr>
						<td><?php echo esc_html( CC_Assistant_Pre_Publish::label_for( $name ) ); ?> <small class="description"><code><?php echo esc_html( $name ); ?></code></small></td>
						<td><?php echo (int) $b['fail_count']; ?> / <?php echo (int) $total_for_check; ?></td>
						<td><?php echo (int) $pct; ?>%</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $audit_blob['worst_posts'] ) ) : ?>
				<h3 style="margin-top:18px;"><?php esc_html_e( 'Worst-failing posts', 'cc-assistant' ); ?></h3>
				<ul class="cc-mini-list">
					<?php foreach ( array_slice( $audit_blob['worst_posts'], 0, 10 ) as $w ) : ?>
						<li>
							<a href="<?php echo esc_url( $w['edit_url'] ); ?>"><?php echo esc_html( $w['title'] ); ?></a>
							<small class="description"><?php
								/* translators: %d: count */
								printf( esc_html( _n( '%d check failing', '%d checks failing', $w['fail_count'], 'cc-assistant' ) ), (int) $w['fail_count'] );
							?></small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $trend_rows ) ) : ?>
		<div class="cc-card cc-card-wide cc-checkup-trends">
			<h2><?php esc_html_e( 'Query overlap observations', 'cc-assistant' ); ?>
				<?php if ( 'fewer_candidates' === $trend_dir['direction'] ) : ?>
					<span class="cc-trend-good">↓ <?php esc_html_e( 'fewer candidates', 'cc-assistant' ); ?></span>
				<?php elseif ( 'more_candidates' === $trend_dir['direction'] ) : ?>
					<span class="cc-trend-bad">↑ <?php esc_html_e( 'more candidates', 'cc-assistant' ); ?></span>
				<?php else : ?>
					<span class="cc-trend-flat">→ <?php esc_html_e( 'flat', 'cc-assistant' ); ?></span>
				<?php endif; ?>
			</h2>
			<p class="description"><?php esc_html_e( 'Weekly query/page overlap candidates. Their impressions are observed exposure, not proven lost traffic. Demand changes can move these totals.', 'cc-assistant' ); ?></p>
			<table class="cc-mini-table">
				<thead><tr>
					<th><?php esc_html_e( 'Captured', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Conflicts', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Candidate impressions', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Top query', 'cc-assistant' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $trend_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( human_time_diff( strtotime( $row->captured_at . ' UTC' ), time() ) ); ?> ago</td>
						<td><?php echo (int) $row->conflict_count; ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->leak_impressions ) ); ?></td>
						<td><?php echo $row->top_query ? esc_html( $row->top_query ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<form method="get" class="cc-checkup-filters">
		<input type="hidden" name="page" value="cc-assistant-checkup">

		<label>
			<?php esc_html_e( 'Type', 'cc-assistant' ); ?>
			<select name="cc_pt">
				<?php foreach ( $post_types as $type ) : ?>
					<?php if ( ! in_array( $type->name, $allowed_post_types, true ) ) continue; ?>
					<option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( $post_type, $type->name ); ?>><?php echo esc_html( $type->labels->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<label>
			<?php esc_html_e( 'Status', 'cc-assistant' ); ?>
			<select name="cc_status">
				<option value="publish" <?php selected( $post_status, 'publish' ); ?>><?php esc_html_e( 'Published', 'cc-assistant' ); ?></option>
				<option value="draft" <?php selected( $post_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'cc-assistant' ); ?></option>
				<option value="any" <?php selected( $post_status, 'any' ); ?>><?php esc_html_e( 'Any', 'cc-assistant' ); ?></option>
			</select>
		</label>

		<label class="cc-checkbox-inline">
			<input type="checkbox" name="cc_fails_only" value="1" <?php checked( $fails_only ); ?>>
			<?php esc_html_e( 'Only show posts with failures', 'cc-assistant' ); ?>
		</label>

		<button type="submit" class="button"><?php esc_html_e( 'Refresh', 'cc-assistant' ); ?></button>
	</form>

	<p class="cc-checkup-meta">
		<?php
		printf(
			/* translators: 1: results shown, 2: posts scanned on this page, 3: site-wide match count, 4: post type label, 5: current page, 6: total pages */
			esc_html__( 'Showing %1$d of %2$d scanned on this page — %3$d %4$s match the filter site-wide (page %5$d of %6$d).', 'cc-assistant' ),
			(int) count( $results ),
			(int) $scanned_here,
			(int) $total_matching,
			esc_html( $post_type ),
			(int) $paged,
			(int) max( 1, $total_pages )
		);
		?>
		<?php if ( $fails_only ) : ?>
			<br><em><?php esc_html_e( 'The failures-only filter is applied per page, after paging. A page with no failures does not mean the site has none — check the remaining pages.', 'cc-assistant' ); ?></em>
		<?php endif; ?>
	</p>

	<?php if ( empty( $results ) ) : ?>
		<?php
		// "Nothing to flag" is only true when this is the whole result set.
		// With fails_only on and more pages behind this one, an empty page says
		// nothing about the site — claiming otherwise sent operators away from
		// real failures sitting on page 2.
		$more_pages_remain = $total_pages > 1;
		?>
		<div class="cc-empty-state">
			<?php if ( $fails_only && $more_pages_remain ) : ?>
				<span class="dashicons dashicons-search"></span>
				<h2><?php esc_html_e( 'No failures on this page', 'cc-assistant' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Every post scanned on page %1$d of %2$d passed. Other pages have not been scanned — step through the remaining pages to check the rest of the site.', 'cc-assistant' ),
						(int) $paged,
						(int) $total_pages
					);
					?>
				</p>
			<?php else : ?>
				<span class="dashicons dashicons-yes"></span>
				<h2><?php esc_html_e( 'Nothing to flag', 'cc-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Either everything is passing or there are no posts to check with this filter.', 'cc-assistant' ); ?></p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="cc-table-scroll"><table class="wp-list-table widefat striped cc-checkup-table">
			<thead>
				<tr>
					<th class="cc-col-id"><?php esc_html_e( 'ID', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Post', 'cc-assistant' ); ?></th>
					<th class="cc-col-score"><?php esc_html_e( 'Score', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Failed checks', 'cc-assistant' ); ?></th>
					<th class="cc-col-actions"><?php esc_html_e( 'Actions', 'cc-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $results as $r ) :
					$failed = array();
					foreach ( $r['checks'] as $name => $c ) {
						if ( empty( $c['pass'] ) ) {
							$failed[] = $name;
						}
					}
					$score    = $r['summary']['pass_count'] . '/' . $r['summary']['total'];
					$pass_all = ! empty( $r['pass'] );
					$prompt   = sprintf(
						'Use pre_publish_check on post %d, then fix the failing checks (%s) using draft_update_post_meta, draft_update_postmeta, or draft_update_elementor_widget. Follow the style guide.',
						$r['post_id'],
						implode( ', ', $failed )
					);
					?>
					<tr class="<?php echo $pass_all ? 'cc-row-pass' : 'cc-row-fail'; ?>">
						<td class="cc-col-id">
							<button type="button" class="cc-copy-btn" data-copy="<?php echo esc_attr( (string) $r['post_id'] ); ?>" title="<?php esc_attr_e( 'Copy ID', 'cc-assistant' ); ?>">
								<span class="cc-id-num"><?php echo esc_html( $r['post_id'] ); ?></span>
								<span class="dashicons dashicons-admin-page"></span>
							</button>
						</td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $r['post_id'] ) ); ?>" class="cc-post-link"><?php echo esc_html( $r['post_title'] ); ?></a>
							<div class="cc-checkup-permalink"><?php echo esc_html( str_replace( home_url(), '', $r['permalink'] ) ); ?></div>
						</td>
						<td class="cc-col-score">
							<span class="cc-score <?php echo $pass_all ? 'cc-score-pass' : 'cc-score-fail'; ?>">
								<?php echo esc_html( $score ); ?>
							</span>
						</td>
						<td class="cc-checkup-failed">
							<?php if ( empty( $failed ) ) : ?>
								<span class="cc-pass-all"><?php esc_html_e( 'All passing', 'cc-assistant' ); ?></span>
							<?php else : ?>
								<?php foreach ( $failed as $check_name ) :
									$msg          = $r['checks'][ $check_name ]['message'] ?? '';
									$check_source = $r['checks'][ $check_name ]['source'] ?? '';
									$label        = cc_check_label_for( $check_name, $cc_check_meta );
									$category     = $cc_check_meta[ $check_name ]['category'] ?? 'other';
									$help         = $cc_check_meta[ $check_name ]['help'] ?? '';
									?>
									<div class="cc-failed-line cc-failed-cat-<?php echo esc_attr( $category ); ?>">
										<span class="dashicons dashicons-warning"></span>
										<div class="cc-failed-text">
											<div class="cc-failed-label">
												<?php echo esc_html( $label ); ?>
												<?php if ( $help ) : ?>
													<span class="cc-failed-help" title="<?php echo esc_attr( $help ); ?>">?</span>
												<?php endif; ?>
												<code class="cc-failed-key"><?php echo esc_html( $check_name ); ?></code>
												<?php if ( 'parsed' === $check_source ) : ?>
													<span class="cc-failed-source" title="<?php esc_attr_e( 'Counted from authored content. Rendered-page fetch was unavailable, so hidden / conditional widgets may inflate this count. Visit the post once to seed the rendered cache.', 'cc-assistant' ); ?>"><?php esc_html_e( 'authored', 'cc-assistant' ); ?></span>
												<?php elseif ( 'rendered' === $check_source ) : ?>
													<span class="cc-failed-source cc-source-rendered" title="<?php esc_attr_e( 'Counted from the actual rendered page DOM.', 'cc-assistant' ); ?>"><?php esc_html_e( 'rendered', 'cc-assistant' ); ?></span>
												<?php endif; ?>
											</div>
											<?php if ( $msg ) : ?>
												<div class="cc-failed-msg"><?php echo esc_html( $msg ); ?></div>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</td>
						<td class="cc-col-actions">
							<a href="<?php echo esc_url( get_edit_post_link( $r['post_id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Open', 'cc-assistant' ); ?></a>
							<?php if ( ! $pass_all ) : ?>
								<button type="button" class="button button-small button-primary cc-copy-btn" data-copy="<?php echo esc_attr( $prompt ); ?>" title="<?php esc_attr_e( 'Copy a ready Claude prompt to fix this post', 'cc-assistant' ); ?>">
									<?php esc_html_e( 'Copy fix prompt', 'cc-assistant' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="cc-pagination">
				<?php
				$base_url = remove_query_arg( 'paged' );
				if ( $paged > 1 ) {
					echo '<a href="' . esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ) . '" class="button">&laquo; ' . esc_html__( 'Previous', 'cc-assistant' ) . '</a>';
				}
				echo '<span class="cc-pagination-info">' . esc_html( sprintf( __( 'Page %d of %d', 'cc-assistant' ), $paged, $total_pages ) ) . '</span>';
				if ( $paged < $total_pages ) {
					echo '<a href="' . esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ) . '" class="button">' . esc_html__( 'Next', 'cc-assistant' ) . ' &raquo;</a>';
				}
				?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<script>
(function () {
	document.querySelectorAll('.cc-copy-btn').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var text = this.dataset.copy;
			if (!text) return;
			navigator.clipboard.writeText(text).then(function () {
				var prev = btn.innerHTML;
				btn.classList.add('cc-copy-flash');
				btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied';
				setTimeout(function () {
					btn.innerHTML = prev;
					btn.classList.remove('cc-copy-flash');
				}, 1200);
			});
		});
	});
})();
</script>

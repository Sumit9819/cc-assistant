<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CC_ASSISTANT_DIR . 'includes/class-llm-tracker.php';

$enabled = CC_Assistant_LLM_Tracker::is_enabled();
$days    = isset( $_GET['cc_days'] ) ? max( 1, min( 90, (int) $_GET['cc_days'] ) ) : 30;
$summary = $enabled ? CC_Assistant_LLM_Tracker::summary( $days ) : null;
$paths   = $enabled ? CC_Assistant_LLM_Tracker::per_path_breakdown( $days, 30 ) : null;
?>
<div class="wrap cc-assistant cc-llm-activity">
	<h1><?php esc_html_e( 'AI bot activity', 'cc-assistant' ); ?></h1>
	<nav class="nav-tab-wrapper" style="margin-bottom:12px;">
		<?php if ( class_exists( 'CC_Assistant_Reports' ) && current_user_can( CC_Assistant_Reports::CAP ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-reports' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Reports', 'cc-assistant' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-performance' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Rankings', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-checkup' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Quality check', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-llm' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'AI bots', 'cc-assistant' ); ?></a>
	</nav>
	<p class="description"><?php esc_html_e( 'Per-page breakdown of which AI training/citation bots are reading your content. Use it to decide which content deserves richer E-E-A-T signals — bots index what they like, citations flow from there.', 'cc-assistant' ); ?></p>

	<?php if ( ! $enabled ) : ?>
		<div class="cc-card cc-card-wide cc-info-card">
			<h2><?php esc_html_e( 'Tracking is off', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Enable AI bot tracking to see which of your pages GPTBot, ClaudeBot, PerplexityBot, and Google-Extended are reading.', 'cc-assistant' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-settings&tab=general' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Open settings', 'cc-assistant' ); ?></a></p>
		</div>
	<?php else : ?>
		<form method="get" class="cc-llm-filters">
			<input type="hidden" name="page" value="cc-assistant-llm">
			<label>
				<?php esc_html_e( 'Window', 'cc-assistant' ); ?>
				<select name="cc_days" onchange="this.form.submit()">
					<?php foreach ( array( 7, 14, 30, 60, 90 ) as $d ) : ?>
						<option value="<?php echo (int) $d; ?>" <?php selected( $days, $d ); ?>><?php /* translators: %d days */ printf( esc_html__( 'Last %d days', 'cc-assistant' ), $d ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</form>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Summary', 'cc-assistant' ); ?></h2>
			<div class="cc-rollup-grid">
				<div class="cc-rollup-stat">
					<div class="cc-rollup-num"><?php echo (int) $summary['total_hits']; ?></div>
					<div class="cc-rollup-lbl"><?php esc_html_e( 'total bot hits', 'cc-assistant' ); ?></div>
				</div>
				<div class="cc-rollup-stat">
					<div class="cc-rollup-num"><?php echo (int) count( $summary['by_bot'] ); ?></div>
					<div class="cc-rollup-lbl"><?php esc_html_e( 'distinct bots', 'cc-assistant' ); ?></div>
				</div>
				<div class="cc-rollup-stat">
					<?php
					// Site-wide distinct paths, not the number of rows in the
					// top-N table below — those are two different numbers and
					// this tile was showing the smaller one.
					$crawled_total = isset( $paths['distinct_paths'] ) ? (int) $paths['distinct_paths'] : (int) count( $paths['paths'] );
					?>
					<div class="cc-rollup-num"><?php echo esc_html( number_format_i18n( $crawled_total ) ); ?></div>
					<div class="cc-rollup-lbl"><?php esc_html_e( 'pages crawled', 'cc-assistant' ); ?></div>
				</div>
			</div>

			<?php if ( ! empty( $summary['by_bot'] ) ) : ?>
				<h3 style="margin-top:14px;"><?php esc_html_e( 'Top bots', 'cc-assistant' ); ?></h3>
				<table class="cc-mini-table">
					<thead><tr>
						<th><?php esc_html_e( 'Bot', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'Hits', 'cc-assistant' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $summary['by_bot'] as $b ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $b['bot_name'] ); ?></strong></td>
							<td><?php echo esc_html( number_format_i18n( (int) $b['hits'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $paths['paths'] ) ) : ?>
			<div class="cc-card cc-card-wide">
				<h2><?php esc_html_e( 'Pages getting the most AI bot attention', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Each row shows a page, total hits, distinct bots reading it, and the per-bot breakdown. High-hit pages are your AI-citation pipeline — invest E-E-A-T (author bio, schema, sources) where the bots already concentrate.', 'cc-assistant' ); ?></p>
				<table class="cc-mini-table">
					<thead><tr>
						<th><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'Hits', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'Bots', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'Last seen', 'cc-assistant' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $paths['paths'] as $p ) : ?>
						<tr>
							<td>
								<?php if ( $p['post_id'] ) : ?>
									<a href="<?php echo esc_url( $p['edit_url'] ); ?>"><?php echo esc_html( $p['title'] ); ?></a>
									<br><small class="description"><code><?php echo esc_html( $p['path'] ); ?></code></small>
								<?php else : ?>
									<code><?php echo esc_html( $p['path'] ); ?></code>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( (int) $p['hits'] ) ); ?></td>
							<td>
								<?php foreach ( $p['bots'] as $b ) : ?>
									<span class="cc-tag cc-tag-pending"><?php echo esc_html( $b['bot_name'] ); ?> · <?php echo (int) $b['hits']; ?></span>
								<?php endforeach; ?>
							</td>
							<td><small><?php echo esc_html( human_time_diff( strtotime( $p['last_seen'] . ' UTC' ), time() ) ); ?> <?php esc_html_e( 'ago', 'cc-assistant' ); ?></small></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="cc-card cc-card-wide cc-info-card">
				<p><?php esc_html_e( 'No bot activity in this window. Either no AI bots have visited yet, or your robots.txt is blocking them.', 'cc-assistant' ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

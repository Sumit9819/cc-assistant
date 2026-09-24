<?php
/**
 * Database health view.
 *
 * Surfaces wp_cc_* table sizes + row counts plus the last GSC maintenance
 * outcome so admins can spot bloat without needing PHPMyAdmin. Origin: 2026-05-19
 * incident where wp_cc_gsc_queries crossed a 1 GB host quota.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$tables = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT table_name AS name,
		        data_length + index_length AS bytes,
		        table_rows AS rows
		 FROM information_schema.TABLES
		 WHERE table_schema = DATABASE() AND table_name LIKE %s
		 ORDER BY (data_length + index_length) DESC",
		$wpdb->prefix . 'cc_%'
	)
);

$total_bytes = 0;
foreach ( $tables as $t ) {
	$total_bytes += (int) $t->bytes;
}

$last_maintain = get_option( 'cc_assistant_gsc_last_maintain', null );
$flash         = get_transient( 'cc_assistant_db_health_flash' );
if ( $flash ) {
	delete_transient( 'cc_assistant_db_health_flash' );
}

$fmt_mb = function ( $bytes ) {
	return number_format( $bytes / 1024 / 1024, 1 );
};

// Per-table guidance: which ones are unbounded / risky and what to do.
// Updated v0.33.0 — three new retention paths via CC_Assistant_Storage_Maintenance.
$_storage_retention = class_exists( 'CC_Assistant_Storage_Maintenance' )
	? CC_Assistant_Storage_Maintenance::get_retention()
	: array( 'cc_snapshots' => 90, 'cc_pending_settled' => 60, 'cc_edits' => 365 );

$guidance = array(
	'cc_gsc_queries' => array(
		'risk' => 'high',
		'note' => 'Auto-pruned daily: drops impressions=1 after 14d, impressions≤2 after 30d, everything after 60d. Hard cap at 250 MB.',
	),
	'cc_snapshots' => array(
		'risk' => 'medium',
		'note' => sprintf(
			'Stores full Elementor LONGTEXT per rollback point. Auto-pruned daily: drops snapshots older than %d days.',
			(int) $_storage_retention['cc_snapshots']
		),
	),
	'cc_pending_changes' => array(
		'risk' => 'medium',
		'note' => sprintf(
			'Pending rows are kept indefinitely (the human reviewer queue). Settled rows (approved/rejected) are auto-pruned daily after %d days.',
			(int) $_storage_retention['cc_pending_settled']
		),
	),
	'cc_edits' => array(
		'risk' => 'low',
		'note' => sprintf(
			'Edit-history metadata (no body content). Auto-pruned daily after %d days.',
			(int) $_storage_retention['cc_edits']
		),
	),
	'cc_activity_log' => array(
		'risk' => 'low',
		'note' => 'Rolling 2-day window. Auto-pruned daily.',
	),
	'cc_llm_crawls' => array(
		'risk' => 'low',
		'note' => 'AI bot crawl events. Auto-pruned by class-llm-tracker on its own schedule.',
	),
	'cc_link_graph' => array(
		'risk' => 'low',
		'note' => 'Internal link edges. Full DELETE + rebuild nightly — self-bounded by post count.',
	),
);

// Storage maintenance flash (v0.33.0). Separate from the GSC flash so each
// "Run now" action lights up its own success banner.
$storage_flash = isset( $_GET['cc_storage_maintained'] ) && '1' === $_GET['cc_storage_maintained'];
$storage_last  = class_exists( 'CC_Assistant_Storage_Maintenance' )
	? CC_Assistant_Storage_Maintenance::get_last_run()
	: null;
?>
<div class="wrap cc-assistant cc-db-health">
	<h1><?php esc_html_e( 'Database health', 'cc-assistant' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Sizes of every CC Assistant table in your WordPress database. Most hosts cap shared databases at 1 GB; the plugin auto-prunes the largest table (GSC queries) but other tables grow with usage.', 'cc-assistant' ); ?>
	</p>

	<?php if ( $flash && 'success' === ( $flash['type'] ?? '' ) ) :
		$r = $flash['result'];
		$dropped = (int) $r['deleted_imp1'] + (int) $r['deleted_imp2'] + (int) $r['deleted_old'] + (int) $r['deleted_cap'];
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Maintenance complete.', 'cc-assistant' ); ?></strong>
				<?php
				printf(
					/* translators: 1: rows dropped, 2: final table size in MB */
					esc_html__( 'Dropped %1$s rows from wp_cc_gsc_queries. Table now %2$s MB.', 'cc-assistant' ),
					esc_html( number_format( $dropped ) ),
					esc_html( $r['final_mb'] )
				);
				?>
				<?php if ( $dropped > 0 ) : ?>
					<br><em><?php esc_html_e( 'Note: MySQL does not always release freed pages back to the OS. Run OPTIMIZE TABLE in PHPMyAdmin if your host shows the database still oversized.', 'cc-assistant' ); ?></em>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="cc-card cc-card-wide">
		<h2><?php esc_html_e( 'CC Assistant tables', 'cc-assistant' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Table', 'cc-assistant' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Size (MB)', 'cc-assistant' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Rows', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Retention', 'cc-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $tables ) ) : ?>
					<tr><td colspan="4"><em><?php esc_html_e( 'No CC Assistant tables found.', 'cc-assistant' ); ?></em></td></tr>
				<?php else : ?>
					<?php foreach ( $tables as $t ) :
						$short = preg_replace( '/^' . preg_quote( $wpdb->prefix, '/' ) . '/', '', $t->name );
						$g     = $guidance[ $short ] ?? array( 'risk' => 'low', 'note' => '—' );
						$risk_color = array( 'high' => '#d63638', 'medium' => '#dba617', 'low' => '#646970' );
						?>
						<tr>
							<td><code><?php echo esc_html( $t->name ); ?></code></td>
							<td style="text-align:right"><strong><?php echo esc_html( $fmt_mb( $t->bytes ) ); ?></strong></td>
							<td style="text-align:right"><?php echo esc_html( number_format( (int) $t->rows ) ); ?></td>
							<td>
								<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr( $risk_color[ $g['risk'] ] ); ?>;margin-right:6px"></span>
								<?php echo esc_html( $g['note'] ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<td><strong><?php esc_html_e( 'Total', 'cc-assistant' ); ?></strong></td>
						<td style="text-align:right"><strong><?php echo esc_html( $fmt_mb( $total_bytes ) ); ?></strong></td>
						<td colspan="2"></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div class="cc-card cc-card-wide">
		<h2><?php esc_html_e( 'GSC table maintenance', 'cc-assistant' ); ?></h2>
		<?php if ( $last_maintain ) : ?>
			<p>
				<strong><?php esc_html_e( 'Last run:', 'cc-assistant' ); ?></strong>
				<?php echo esc_html( $last_maintain['ts'] ); ?> (UTC<?php echo esc_html( wp_date( 'P' ) ); ?>)
				—
				<?php
				$total = (int) $last_maintain['deleted_imp1'] + (int) $last_maintain['deleted_imp2'] + (int) $last_maintain['deleted_old'] + (int) $last_maintain['deleted_cap'];
				printf(
					/* translators: 1: rows dropped, 2: imp1, 3: imp2, 4: old, 5: cap, 6: final MB */
					esc_html__( 'dropped %1$s rows (imp=1: %2$s · imp≤2: %3$s · expired: %4$s · cap: %5$s). Table now %6$s MB.', 'cc-assistant' ),
					esc_html( number_format( $total ) ),
					esc_html( number_format( (int) $last_maintain['deleted_imp1'] ) ),
					esc_html( number_format( (int) $last_maintain['deleted_imp2'] ) ),
					esc_html( number_format( (int) $last_maintain['deleted_old'] ) ),
					esc_html( number_format( (int) $last_maintain['deleted_cap'] ) ),
					esc_html( $last_maintain['final_mb'] )
				);
				?>
			</p>
		<?php else : ?>
			<p><em><?php esc_html_e( 'Maintenance has not run yet — it executes after each daily GSC sync.', 'cc-assistant' ); ?></em></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em">
			<?php wp_nonce_field( 'cc_db_maintenance' ); ?>
			<input type="hidden" name="action" value="cc_db_maintenance">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Run maintenance now', 'cc-assistant' ); ?></button>
			<span class="description" style="margin-left:1em">
				<?php esc_html_e( 'Force a prune outside the daily cron — useful after a plugin update to reclaim space immediately.', 'cc-assistant' ); ?>
			</span>
		</form>
	</div>

	<?php if ( $storage_flash ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php esc_html_e( 'Storage maintenance complete.', 'cc-assistant' ); ?></strong>
			<?php esc_html_e( 'Snapshots, settled pending changes, and edit history were pruned according to the retention windows below.', 'cc-assistant' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="cc-card cc-card-wide">
		<h2><?php esc_html_e( 'Storage retention (snapshots, pending, edits)', 'cc-assistant' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Daily cron prunes the three tables that previously had no retention. Pending rows currently in the human review queue are NEVER touched — only settled (approved / rejected) rows past the retention window are deleted.', 'cc-assistant' ); ?>
		</p>

		<table class="widefat striped" style="margin-top:1em">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Bucket', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Retention', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Last run rows deleted', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Final size after prune', 'cc-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$_buckets = array(
					'snapshots'       => array( 'label' => 'wp_cc_snapshots', 'key' => 'cc_snapshots' ),
					'pending_settled' => array( 'label' => 'wp_cc_pending_changes (settled)', 'key' => 'cc_pending_settled' ),
					'edits'           => array( 'label' => 'wp_cc_edits', 'key' => 'cc_edits' ),
				);
				foreach ( $_buckets as $bucket_id => $meta ) :
					$stat = ( $storage_last && isset( $storage_last['stats'][ $bucket_id ] ) ) ? $storage_last['stats'][ $bucket_id ] : null;
					$retention_days = (int) ( $_storage_retention[ $meta['key'] ] ?? 0 );
				?>
					<tr>
						<td><code><?php echo esc_html( $meta['label'] ); ?></code></td>
						<td><?php echo $retention_days > 0
							? esc_html( sprintf( __( 'Older than %d days', 'cc-assistant' ), $retention_days ) )
							: '<em>' . esc_html__( 'Disabled', 'cc-assistant' ) . '</em>';
						?></td>
						<td><?php echo $stat ? esc_html( number_format( (int) $stat['deleted'] ) ) : '—'; ?></td>
						<td><?php echo $stat ? esc_html( $stat['final_mb'] ) . ' MB' : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $storage_last ) : ?>
			<p style="margin-top:1em">
				<strong><?php esc_html_e( 'Last storage maintenance:', 'cc-assistant' ); ?></strong>
				<?php echo esc_html( $storage_last['ts'] ); ?> (UTC<?php echo esc_html( wp_date( 'P' ) ); ?>)
			</p>
		<?php else : ?>
			<p style="margin-top:1em"><em><?php esc_html_e( 'Storage maintenance has not run yet — first run occurs at 00:30 UTC tomorrow, or trigger it now via the button below.', 'cc-assistant' ); ?></em></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em">
			<?php wp_nonce_field( 'cc_storage_maintain_now' ); ?>
			<input type="hidden" name="action" value="cc_storage_maintain_now">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Run storage maintenance now', 'cc-assistant' ); ?></button>
			<span class="description" style="margin-left:1em">
				<?php esc_html_e( 'Prunes snapshots, settled pending rows, and old edits using the retention windows above. Also re-runs GSC maintenance.', 'cc-assistant' ); ?>
			</span>
		</form>

		<details style="margin-top:1.5em">
			<summary style="cursor:pointer; font-weight:600;"><?php esc_html_e( 'Customize retention windows', 'cc-assistant' ); ?></summary>
			<p style="margin-top:1em" class="description">
				<?php esc_html_e( 'To override the defaults, set the cc_assistant_storage_retention option from WP-CLI or a snippet plugin:', 'cc-assistant' ); ?>
			</p>
			<pre style="background:#f0f0f1;padding:8px 12px;font-family:Consolas,Monaco,monospace;white-space:pre-wrap">update_option( 'cc_assistant_storage_retention', array(
    'cc_snapshots'       => 30,   // default 90
    'cc_pending_settled' => 30,   // default 60
    'cc_edits'           => 90,   // default 365
) );</pre>
			<p class="description">
				<?php esc_html_e( 'Smaller values delete more aggressively. Tighten these on hosts with low database quotas (≤1 GB). The next daily cron picks up the new values automatically.', 'cc-assistant' ); ?>
			</p>
		</details>
	</div>

	<div class="cc-card cc-card-wide cc-info-card">
		<h2><?php esc_html_e( 'When the database is still too big', 'cc-assistant' ); ?></h2>
		<p>
			<?php esc_html_e( 'MySQL does not always release freed disk pages back to the host even after DELETE. If your host still shows the database oversized after a maintenance run, the fix is one SQL command in PHPMyAdmin:', 'cc-assistant' ); ?>
		</p>
		<pre style="background:#f0f0f1;padding:8px 12px;font-family:Consolas,Monaco,monospace">OPTIMIZE TABLE <?php echo esc_html( $wpdb->prefix ); ?>cc_gsc_queries;</pre>
		<p>
			<?php esc_html_e( 'This rebuilds the table file and reclaims the freed pages. Safe to run on a live site; the table is briefly locked during the rebuild.', 'cc-assistant' ); ?>
		</p>
	</div>
</div>

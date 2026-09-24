<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post-edit metabox that surfaces CC Assistant context for the post being
 * edited: cluster memberships, pending changes count, recent snapshots, and
 * one-click links to the inbox / checkup / snapshots filtered to this post.
 *
 * Renders only on allowed post types (per cc_assistant_allowed_post_types).
 * Fires only inside admin post-edit screens, so it pays nothing on the front
 * end or on other admin pages.
 */
class CC_Assistant_Editor_Sidebar {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
	}

	public static function register_metabox() {
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		foreach ( $allowed as $post_type ) {
			add_meta_box(
				'cc-assistant-editor-sidebar',
				__( 'CC Assistant', 'cc-assistant' ),
				array( __CLASS__, 'render' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public static function render( $post ) {
		$post_id = (int) $post->ID;
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';

		$pending     = self::pending_for_post( $post_id );
		$clusters    = CC_Assistant_Topic_Clusters::clusters_for_post( $post_id );
		$snapshots   = CC_Assistant_Snapshots::list_snapshots( $post_id, 3 );
		$last_edit   = self::last_edit_outcome( $post_id );
		$check       = self::cached_pre_publish( $post_id, $post );

		$inbox_url     = add_query_arg( 'cc_post_filter', $post_id, admin_url( 'admin.php?page=cc-assistant-pending' ) );
		$checkup_url   = admin_url( 'admin.php?page=cc-assistant-checkup' );
		$snapshots_url = add_query_arg( 'post_id', $post_id, admin_url( 'admin.php?page=cc-assistant-snapshots' ) );
		?>
		<div class="cc-editor-sidebar">

			<?php if ( ! empty( $clusters ) ) : ?>
				<section class="cc-eds-section">
					<h4><span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Topic clusters', 'cc-assistant' ); ?></h4>
					<ul class="cc-eds-clusters">
						<?php foreach ( $clusters as $c ) :
							$role_label = CC_Assistant_Topic_Clusters::ROLE_PILLAR === $c->role
								? __( 'pillar', 'cc-assistant' )
								: __( 'supporting', 'cc-assistant' );
							?>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-clusters#cc-cluster-' . (int) $c->id ) ); ?>">
									<?php echo esc_html( $c->name ); ?>
								</a>
								<span class="cc-eds-role cc-eds-role-<?php echo esc_attr( $c->role ); ?>"><?php echo esc_html( $role_label ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php else : ?>
				<section class="cc-eds-section cc-eds-empty">
					<p>
						<span class="dashicons dashicons-category"></span>
						<?php esc_html_e( 'Not in any topic cluster yet.', 'cc-assistant' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-clusters' ) ); ?>"><?php esc_html_e( 'Manage clusters', 'cc-assistant' ); ?> &rarr;</a>
					</p>
				</section>
			<?php endif; ?>

			<section class="cc-eds-section">
				<h4><span class="dashicons dashicons-format-status"></span> <?php esc_html_e( 'Pending changes', 'cc-assistant' ); ?></h4>
				<?php if ( $pending['pending'] > 0 ) : ?>
					<div class="cc-eds-pending-stat cc-eds-has-pending">
						<strong><?php echo (int) $pending['pending']; ?></strong>
						<span><?php echo esc_html( _n( 'waiting', 'waiting', $pending['pending'], 'cc-assistant' ) ); ?></span>
					</div>
					<p>
						<a class="button button-small button-primary" href="<?php echo esc_url( $inbox_url ); ?>"><?php esc_html_e( 'Review now', 'cc-assistant' ); ?></a>
					</p>
				<?php elseif ( $pending['total'] > 0 ) : ?>
					<p class="cc-eds-muted">
						<?php
						printf(
							/* translators: 1: applied count, 2: rejected count */
							esc_html__( 'No changes pending. %1$d applied, %2$d rejected on this post.', 'cc-assistant' ),
							(int) $pending['approved'],
							(int) $pending['rejected']
						);
						?>
					</p>
				<?php else : ?>
					<p class="cc-eds-muted"><?php esc_html_e( 'No proposals on this post yet.', 'cc-assistant' ); ?></p>
				<?php endif; ?>
			</section>

			<?php if ( $last_edit ) : ?>
				<section class="cc-eds-section">
					<h4><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e( 'Last edit outcome', 'cc-assistant' ); ?></h4>
					<div class="cc-eds-outcome">
						<div class="cc-eds-outcome-summary"><?php echo esc_html( $last_edit['summary'] ); ?></div>
						<div class="cc-eds-outcome-meta">
							<?php echo esc_html( $last_edit['ago'] ); ?>
							<?php if ( ! empty( $last_edit['verdict'] ) ) : ?>
								&middot;
								<span class="cc-eds-verdict cc-eds-verdict-<?php echo esc_attr( $last_edit['verdict'] ); ?>">
									<?php echo esc_html( $last_edit['verdict_label'] ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $snapshots ) ) : ?>
				<section class="cc-eds-section">
					<h4><span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'Recent snapshots', 'cc-assistant' ); ?></h4>
					<ul class="cc-eds-snapshots">
						<?php foreach ( $snapshots as $s ) :
							$age = human_time_diff( strtotime( $s->created_at ), current_time( 'timestamp' ) );
							$type_short = self::short_snapshot_label( $s->snapshot_type );
							?>
							<li>
								<span class="cc-eds-snap-type"><?php echo esc_html( $type_short ); ?></span>
								<span class="cc-eds-snap-age"><?php echo esc_html( $age ); ?> <?php esc_html_e( 'ago', 'cc-assistant' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<p>
						<a class="button button-small" href="<?php echo esc_url( $snapshots_url ); ?>"><?php esc_html_e( 'Browse all', 'cc-assistant' ); ?></a>
					</p>
				</section>
			<?php endif; ?>

			<?php if ( $check && ! is_wp_error( $check ) ) :
				$failed = array();
				foreach ( $check['checks'] as $name => $c ) {
					if ( empty( $c['pass'] ) ) {
						$failed[ $name ] = $c;
					}
				}
				$pass_count = (int) $check['summary']['pass_count'];
				$total      = (int) $check['summary']['total'];
				$score_pct  = $total > 0 ? (int) round( ( $pass_count / $total ) * 100 ) : 0;
				$meta_map   = CC_Assistant_Pre_Publish::check_meta();
				?>
				<section class="cc-eds-section">
					<h4>
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Pre-publish checks', 'cc-assistant' ); ?>
					</h4>
					<div class="cc-eds-score cc-eds-score-<?php echo $check['pass'] ? 'pass' : 'fail'; ?>">
						<strong><?php echo (int) $pass_count; ?>/<?php echo (int) $total; ?></strong>
						<span><?php echo (int) $score_pct; ?>%</span>
					</div>
					<?php if ( ! empty( $failed ) ) : ?>
						<ul class="cc-eds-checks">
							<?php foreach ( $failed as $name => $c ) :
								$label    = CC_Assistant_Pre_Publish::label_for( $name );
								$category = $meta_map[ $name ]['category'] ?? 'other';
								$msg      = $c['message'] ?? '';
								?>
								<li class="cc-eds-check cc-eds-cat-<?php echo esc_attr( $category ); ?>" title="<?php echo esc_attr( $msg ); ?>">
									<span class="cc-eds-check-dot"></span>
									<span class="cc-eds-check-label"><?php echo esc_html( $label ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="cc-eds-muted"><?php esc_html_e( 'All checks passing. Ship it.', 'cc-assistant' ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<section class="cc-eds-section cc-eds-actions">
				<a class="button button-small" href="<?php echo esc_url( $checkup_url ); ?>"><?php esc_html_e( 'Run check up', 'cc-assistant' ); ?></a>
				<button type="button" class="button button-small cc-eds-copy-prompt" data-copy="<?php echo esc_attr( sprintf( 'Use post_dossier on post %d, then suggest the highest-impact improvement.', $post_id ) ); ?>">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy Claude prompt', 'cc-assistant' ); ?>
				</button>
			</section>

			<p class="cc-eds-footer">
				<?php
				printf(
					/* translators: %d: post id */
					esc_html__( 'Post ID: %d', 'cc-assistant' ),
					$post_id
				);
				?>
			</p>
		</div>

		<script>
		(function () {
			var btn = document.querySelector('.cc-eds-copy-prompt');
			if (!btn) return;
			btn.addEventListener('click', function () {
				var text = this.dataset.copy || '';
				if (!text) return;
				var done = function () {
					var prev = btn.innerHTML;
					btn.innerHTML = '<span class="dashicons dashicons-yes"></span> <?php echo esc_js( __( 'Copied', 'cc-assistant' ) ); ?>';
					setTimeout(function () { btn.innerHTML = prev; }, 1400);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done, function () {});
				} else {
					var ta = document.createElement('textarea');
					ta.value = text; document.body.appendChild(ta); ta.select();
					try { document.execCommand('copy'); done(); } catch (e) {}
					document.body.removeChild(ta);
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * One grouped query for all pending-changes statuses on this post.
	 */
	private static function pending_for_post( $post_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) AS c FROM {$wpdb->prefix}cc_pending_changes WHERE post_id = %d GROUP BY status",
			$post_id
		) );
		$out = array( 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'rolled_back' => 0, 'total' => 0 );
		foreach ( (array) $rows as $r ) {
			if ( isset( $out[ $r->status ] ) ) {
				$out[ $r->status ] = (int) $r->c;
			}
			$out['total'] += (int) $r->c;
		}
		return $out;
	}

	/**
	 * Most recent applied edit on this post + its measured outcome (if any).
	 * Returns null if there's no edit history.
	 */
	private static function last_edit_outcome( $post_id ) {
		global $wpdb;
		$edits_table = $wpdb->prefix . 'cc_edits';

		// cc_edits may not exist on older installs.
		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $edits_table ) ) );
		if ( $exists !== $edits_table ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, change_type, change_summary, applied_at FROM {$edits_table}
			 WHERE post_id = %d ORDER BY applied_at DESC LIMIT 1",
			$post_id
		) );
		if ( ! $row ) {
			return null;
		}

		$verdict       = '';
		$verdict_label = '';
		// Ask class-edit-outcomes for the verdict on this edit. compute_outcome
		// returns status='pending' (still inside the measurement window) or
		// status='measured' with a verdict of positive | flat | negative |
		// no_data. The sidebar maps all of those to a colored pill.
		$outcomes_class = CC_ASSISTANT_DIR . 'includes/class-edit-outcomes.php';
		if ( file_exists( $outcomes_class ) ) {
			require_once $outcomes_class;
			$o = CC_Assistant_Edit_Outcomes::compute_outcome( (int) $row->id );
			if ( is_array( $o ) ) {
				if ( 'pending' === ( $o['status'] ?? '' ) ) {
					$verdict       = 'pending';
					$verdict_label = self::verdict_label( 'pending' );
				} elseif ( 'measured' === ( $o['status'] ?? '' ) && ! empty( $o['verdict'] ) ) {
					$verdict       = (string) $o['verdict'];
					$verdict_label = self::verdict_label( $verdict );
				}
			}
		}

		return array(
			'summary'       => $row->change_summary ?: ucfirst( str_replace( '_', ' ', $row->change_type ) ),
			'ago'           => sprintf(
				/* translators: %s: time-ago string */
				__( '%s ago', 'cc-assistant' ),
				human_time_diff( strtotime( $row->applied_at . ' UTC' ), time() )
			),
			'verdict'       => $verdict,
			'verdict_label' => $verdict_label,
		);
	}

	private static function verdict_label( $verdict ) {
		$map = array(
			'positive' => __( 'Improved', 'cc-assistant' ),
			'negative' => __( 'Declined', 'cc-assistant' ),
			'flat'     => __( 'Flat', 'cc-assistant' ),
			'pending'  => __( 'Measuring', 'cc-assistant' ),
			'no_data'  => __( 'No GSC data', 'cc-assistant' ),
		);
		return $map[ $verdict ] ?? ucfirst( $verdict );
	}

	private static function short_snapshot_label( $type ) {
		$map = array(
			'pre_apply'    => __( 'Before apply', 'cc-assistant' ),
			'pre_rollback' => __( 'Before rollback', 'cc-assistant' ),
			'pre_restore'  => __( 'Before restore', 'cc-assistant' ),
			'manual'       => __( 'Manual', 'cc-assistant' ),
		);
		return $map[ $type ] ?? ucfirst( str_replace( '_', ' ', $type ) );
	}

	/**
	 * Run the pre-publish check with a per-post transient cache so the metabox
	 * does not re-run Elementor parsing + link auditing on every editor refresh.
	 * Cache key includes post_modified AND _cc_assistant_last_internal_apply so
	 * the result auto-invalidates on either a normal post save OR a plugin
	 * apply that only touched postmeta (schema injection, Rank Math SEO meta
	 * routing, alt text on attachments) without bumping post_modified_gmt.
	 */
	private static function cached_pre_publish( $post_id, $post ) {
		$last_apply = get_post_meta( $post_id, '_cc_assistant_last_internal_apply', true );
		$cache_key  = 'cc_eds_check_' . $post_id . '_' . md5( (string) $post->post_modified_gmt . '|' . (string) $last_apply );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
		$result = CC_Assistant_Pre_Publish::check_post( $post_id );
		// Cache only the success path; errors should retry next render.
		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );
		}
		return $result;
	}
}

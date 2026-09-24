<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action_message = '';
$action_error   = '';

if ( isset( $_POST['cc_clusters_action'], $_POST['cc_clusters_nonce'] )
	&& wp_verify_nonce( $_POST['cc_clusters_nonce'], 'cc_clusters_manage' ) ) {

	$action = sanitize_key( $_POST['cc_clusters_action'] );

	if ( 'create_cluster' === $action ) {
		$result = CC_Assistant_Topic_Clusters::create_cluster( array(
			'name'           => isset( $_POST['cluster_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cluster_name'] ) ) : '',
			'description'    => isset( $_POST['cluster_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cluster_description'] ) ) : '',
			'pillar_post_id' => isset( $_POST['cluster_pillar_post_id'] ) ? (int) $_POST['cluster_pillar_post_id'] : null,
			'created_by'     => 'human',
		) );
		if ( is_wp_error( $result ) ) {
			$action_error = $result->get_error_message();
		} else {
			$action_message = __( 'Cluster created.', 'cc-assistant' );
		}
	} elseif ( 'update_cluster' === $action && ! empty( $_POST['cluster_id'] ) ) {
		$ok = CC_Assistant_Topic_Clusters::update_cluster( (int) $_POST['cluster_id'], array(
			'name'           => isset( $_POST['cluster_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cluster_name'] ) ) : '',
			'description'    => isset( $_POST['cluster_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cluster_description'] ) ) : '',
			'pillar_post_id' => isset( $_POST['cluster_pillar_post_id'] ) && (int) $_POST['cluster_pillar_post_id'] > 0 ? (int) $_POST['cluster_pillar_post_id'] : null,
		) );
		// If a pillar was selected via the edit form, also update its membership role.
		if ( isset( $_POST['cluster_pillar_post_id'] ) && (int) $_POST['cluster_pillar_post_id'] > 0 ) {
			CC_Assistant_Topic_Clusters::add_member( (int) $_POST['cluster_id'], (int) $_POST['cluster_pillar_post_id'], CC_Assistant_Topic_Clusters::ROLE_PILLAR, 'human' );
		}
		$action_message = $ok ? __( 'Cluster updated.', 'cc-assistant' ) : __( 'Could not update cluster.', 'cc-assistant' );
	} elseif ( 'delete_cluster' === $action && ! empty( $_POST['cluster_id'] ) ) {
		CC_Assistant_Topic_Clusters::delete_cluster( (int) $_POST['cluster_id'] );
		$action_message = __( 'Cluster deleted. Posts were not modified.', 'cc-assistant' );
	} elseif ( 'add_member' === $action && ! empty( $_POST['cluster_id'] ) && ! empty( $_POST['member_post_id'] ) ) {
		CC_Assistant_Topic_Clusters::add_member(
			(int) $_POST['cluster_id'],
			(int) $_POST['member_post_id'],
			isset( $_POST['member_role'] ) && 'pillar' === $_POST['member_role'] ? CC_Assistant_Topic_Clusters::ROLE_PILLAR : CC_Assistant_Topic_Clusters::ROLE_SUPPORTING,
			'human'
		);
		$action_message = __( 'Member added.', 'cc-assistant' );
	} elseif ( 'remove_member' === $action && ! empty( $_POST['cluster_id'] ) && ! empty( $_POST['member_post_id'] ) ) {
		CC_Assistant_Topic_Clusters::remove_member( (int) $_POST['cluster_id'], (int) $_POST['member_post_id'] );
		$action_message = __( 'Member removed from cluster.', 'cc-assistant' );
	}
}

$clusters       = CC_Assistant_Topic_Clusters::list_clusters();
$total_clusters = count( $clusters );

// For the "add member" dropdowns we need a list of allowed posts. Cap at a
// reasonable number; users with thousands of pages will use Claude to populate.
$allowed_post_types = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
$post_pool = get_posts( array(
	'post_type'      => $allowed_post_types,
	'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
	'posts_per_page' => 500,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );

$unclustered_count = 0;
foreach ( $allowed_post_types as $pt ) {
	$unclustered_count += count( CC_Assistant_Topic_Clusters::unclustered_post_ids( $pt, 1000 ) );
}
?>
<div class="wrap cc-assistant cc-clusters">
	<h1><?php esc_html_e( 'Topic Clusters', 'cc-assistant' ); ?></h1>
	<nav class="nav-tab-wrapper" style="margin-bottom:12px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-clusters' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Clusters', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-calendar' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Calendar', 'cc-assistant' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-brief' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Brief generator', 'cc-assistant' ); ?></a>
	</nav>
	<p class="cc-tagline">
		<?php esc_html_e( 'Group your pages into pillar + supporting clusters. Once set up, Claude reads only the relevant cluster instead of every page on the site.', 'cc-assistant' ); ?>
	</p>

	<?php if ( $action_message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $action_message ); ?></p></div>
	<?php endif; ?>
	<?php if ( $action_error ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $action_error ); ?></p></div>
	<?php endif; ?>

	<?php if ( 0 === $total_clusters ) : ?>

		<div class="cc-card cc-empty-clusters">
			<span class="dashicons dashicons-category"></span>
			<h2><?php esc_html_e( 'No topic clusters yet', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'You have two ways to set this up:', 'cc-assistant' ); ?></p>
			<div class="cc-empty-options">
				<div class="cc-empty-option">
					<h3><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Let Claude propose clusters', 'cc-assistant' ); ?></h3>
					<p><?php esc_html_e( 'Open Claude Code in this folder and ask: "Read all my pages and propose topic clusters with pillar + supporting structure for SEO." Claude will draft a clustering and queue it in Pending Changes for your approval.', 'cc-assistant' ); ?></p>
				</div>
				<div class="cc-empty-option">
					<h3><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Or build one by hand', 'cc-assistant' ); ?></h3>
					<p><?php esc_html_e( 'Use the form below to create your first cluster, then add member pages to it.', 'cc-assistant' ); ?></p>
				</div>
			</div>
		</div>

	<?php else : ?>

		<div class="cc-clusters-summary">
			<div class="cc-summary-stat">
				<div class="cc-summary-num"><?php echo (int) $total_clusters; ?></div>
				<div class="cc-summary-lbl"><?php esc_html_e( 'Clusters', 'cc-assistant' ); ?></div>
			</div>
			<div class="cc-summary-stat">
				<div class="cc-summary-num"><?php echo (int) $unclustered_count; ?></div>
				<div class="cc-summary-lbl"><?php esc_html_e( 'Unclustered pages', 'cc-assistant' ); ?></div>
			</div>
			<?php if ( $unclustered_count > 0 ) : ?>
				<p class="cc-summary-hint">
					<?php esc_html_e( 'Ask Claude:', 'cc-assistant' ); ?>
					<em>"<?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Classify the %d pages that aren\'t in any topic cluster yet.', 'cc-assistant' ), (int) $unclustered_count ) ); ?>"</em>
				</p>
			<?php endif; ?>
		</div>

		<?php foreach ( $clusters as $cluster ) :
			$members      = CC_Assistant_Topic_Clusters::get_members( $cluster->id );
			$pillar       = null;
			$supporting   = array();
			foreach ( $members as $m ) {
				if ( CC_Assistant_Topic_Clusters::ROLE_PILLAR === $m->role ) {
					$pillar = $m;
				} else {
					$supporting[] = $m;
				}
			}
			$pillar_title = $pillar ? get_the_title( $pillar->post_id ) : '';
			$pillar_link  = $pillar ? get_edit_post_link( $pillar->post_id ) : '';
			$dom_id       = 'cc-cluster-' . (int) $cluster->id;
			?>
			<div class="cc-cluster-card" id="<?php echo esc_attr( $dom_id ); ?>">
				<div class="cc-cluster-head">
					<button type="button" class="cc-cluster-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $dom_id ); ?>-body">
						<span class="dashicons dashicons-arrow-right"></span>
					</button>
					<div class="cc-cluster-headline">
						<h2><?php echo esc_html( $cluster->name ); ?></h2>
						<?php if ( $cluster->description ) : ?>
							<p class="description"><?php echo esc_html( $cluster->description ); ?></p>
						<?php endif; ?>
						<div class="cc-cluster-meta">
							<?php if ( $pillar ) : ?>
								<span class="cc-cluster-pillar">
									<span class="dashicons dashicons-flag"></span>
									<?php esc_html_e( 'Pillar:', 'cc-assistant' ); ?>
									<a href="<?php echo esc_url( $pillar_link ); ?>"><?php echo esc_html( $pillar_title ); ?></a>
								</span>
							<?php else : ?>
								<span class="cc-cluster-pillar cc-pillar-missing">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'No pillar set', 'cc-assistant' ); ?>
								</span>
							<?php endif; ?>
							<span class="cc-cluster-count">
								<?php
								$count = count( $supporting );
								printf( esc_html( _n( '%d supporting page', '%d supporting pages', $count, 'cc-assistant' ) ), (int) $count );
								?>
							</span>
						</div>
					</div>
				</div>

				<div class="cc-cluster-body" id="<?php echo esc_attr( $dom_id ); ?>-body" hidden>

					<?php
					$health = CC_Assistant_Topic_Clusters::cluster_health( (int) $cluster->id );
					$verdict_label = array(
						'healthy'      => __( 'Healthy', 'cc-assistant' ),
						'weak_linking' => __( 'Weak internal linking', 'cc-assistant' ),
						'no_pillar'    => __( 'No pillar set', 'cc-assistant' ),
						'pillar_only'  => __( 'Pillar only — add supporting pages', 'cc-assistant' ),
						'empty'        => __( 'Empty', 'cc-assistant' ),
					);
					$verdict_key   = $health ? $health['verdict'] : 'empty';
					$link_complete = $health && $health['supporting_count'] > 0
						? round( ( $health['supporting_linking_pillar'] / $health['supporting_count'] ) * 100 )
						: 0;
					?>
					<div class="cc-cluster-health cc-health-<?php echo esc_attr( $verdict_key ); ?>">
						<div class="cc-health-verdict">
							<span class="cc-health-dot"></span>
							<strong><?php echo esc_html( $verdict_label[ $verdict_key ] ?? $verdict_key ); ?></strong>
							<button type="button" class="button-link cc-cluster-gsc-toggle" data-cluster-id="<?php echo (int) $cluster->id; ?>" data-loaded="0">
								<span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'GSC overlay', 'cc-assistant' ); ?>
							</button>
						</div>
						<div class="cc-health-stats">
							<?php if ( $health && $health['supporting_count'] > 0 ) : ?>
								<div class="cc-health-stat" title="<?php esc_attr_e( 'Supporting pages that link to the pillar', 'cc-assistant' ); ?>">
									<span class="cc-health-num"><?php echo (int) $health['supporting_linking_pillar']; ?>/<?php echo (int) $health['supporting_count']; ?></span>
									<span class="cc-health-lbl"><?php esc_html_e( 'link to pillar', 'cc-assistant' ); ?> (<?php echo (int) $link_complete; ?>%)</span>
								</div>
								<div class="cc-health-stat" title="<?php esc_attr_e( 'Pages the pillar links out to', 'cc-assistant' ); ?>">
									<span class="cc-health-num"><?php echo (int) $health['pillar_outbound_to_cluster']; ?>/<?php echo (int) $health['supporting_count']; ?></span>
									<span class="cc-health-lbl"><?php esc_html_e( 'pillar links back', 'cc-assistant' ); ?></span>
								</div>
							<?php endif; ?>
							<?php if ( $health && ! empty( $health['gsc'] ) ) : ?>
								<div class="cc-health-stat" title="<?php echo esc_attr( sprintf( /* translators: %d: window in days */ __( 'Last %d days', 'cc-assistant' ), (int) $health['gsc']['window_days'] ) ); ?>">
									<span class="cc-health-num"><?php echo esc_html( number_format_i18n( (int) $health['gsc']['impressions'] ) ); ?></span>
									<span class="cc-health-lbl"><?php esc_html_e( 'GSC impressions', 'cc-assistant' ); ?></span>
								</div>
								<div class="cc-health-stat">
									<span class="cc-health-num"><?php echo esc_html( number_format_i18n( (int) $health['gsc']['clicks'] ) ); ?></span>
									<span class="cc-health-lbl"><?php esc_html_e( 'clicks', 'cc-assistant' ); ?></span>
								</div>
								<div class="cc-health-stat">
									<span class="cc-health-num"><?php echo esc_html( number_format_i18n( $health['gsc']['avg_position'], 1 ) ); ?></span>
									<span class="cc-health-lbl"><?php esc_html_e( 'avg position', 'cc-assistant' ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="cc-cluster-gsc-panel" data-cluster-id="<?php echo (int) $cluster->id; ?>" hidden></div>

					<?php if ( ! empty( $supporting ) ) : ?>
						<table class="cc-cluster-members">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Page', 'cc-assistant' ); ?></th>
									<th><?php esc_html_e( 'Added', 'cc-assistant' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $supporting as $m ) :
									$title = get_the_title( $m->post_id );
									$elink = get_edit_post_link( $m->post_id );
									$plink = get_permalink( $m->post_id );
									?>
									<tr>
										<td>
											<a href="<?php echo esc_url( $elink ); ?>"><?php echo esc_html( $title ); ?></a>
											<?php if ( $plink ) : ?>
												<a href="<?php echo esc_url( $plink ); ?>" target="_blank" class="cc-view-live" title="<?php esc_attr_e( 'View live', 'cc-assistant' ); ?>"><span class="dashicons dashicons-external"></span></a>
											<?php endif; ?>
										</td>
										<td><span class="description"><?php echo esc_html( human_time_diff( strtotime( $m->added_at ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'cc-assistant' ); ?></span></td>
										<td class="cc-cluster-row-actions">
											<form method="post" style="display:inline">
												<?php wp_nonce_field( 'cc_clusters_manage', 'cc_clusters_nonce' ); ?>
												<input type="hidden" name="cc_clusters_action" value="add_member">
												<input type="hidden" name="cluster_id" value="<?php echo (int) $cluster->id; ?>">
												<input type="hidden" name="member_post_id" value="<?php echo (int) $m->post_id; ?>">
												<input type="hidden" name="member_role" value="pillar">
												<button type="submit" class="button-link" title="<?php esc_attr_e( 'Promote to pillar', 'cc-assistant' ); ?>"><span class="dashicons dashicons-flag"></span></button>
											</form>
											<form method="post" style="display:inline" onsubmit="return confirm('<?php esc_attr_e( 'Remove this page from the cluster?', 'cc-assistant' ); ?>');">
												<?php wp_nonce_field( 'cc_clusters_manage', 'cc_clusters_nonce' ); ?>
												<input type="hidden" name="cc_clusters_action" value="remove_member">
												<input type="hidden" name="cluster_id" value="<?php echo (int) $cluster->id; ?>">
												<input type="hidden" name="member_post_id" value="<?php echo (int) $m->post_id; ?>">
												<button type="submit" class="button-link cc-row-remove" title="<?php esc_attr_e( 'Remove from cluster', 'cc-assistant' ); ?>"><span class="dashicons dashicons-trash"></span></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="description cc-empty-line"><?php esc_html_e( 'No supporting pages yet. Add some below or ask Claude to suggest them.', 'cc-assistant' ); ?></p>
					<?php endif; ?>

					<details class="cc-cluster-add">
						<summary><?php esc_html_e( 'Add a page to this cluster', 'cc-assistant' ); ?></summary>
						<form method="post" class="cc-cluster-form">
							<?php wp_nonce_field( 'cc_clusters_manage', 'cc_clusters_nonce' ); ?>
							<input type="hidden" name="cc_clusters_action" value="add_member">
							<input type="hidden" name="cluster_id" value="<?php echo (int) $cluster->id; ?>">
							<select name="member_post_id" required>
								<option value=""><?php esc_html_e( '— Pick a page —', 'cc-assistant' ); ?></option>
								<?php foreach ( $post_pool as $p ) : ?>
									<option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html( $p->post_title ?: '(no title)' ); ?></option>
								<?php endforeach; ?>
							</select>
							<select name="member_role">
								<option value="supporting"><?php esc_html_e( 'Supporting', 'cc-assistant' ); ?></option>
								<option value="pillar"><?php esc_html_e( 'Pillar', 'cc-assistant' ); ?></option>
							</select>
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'Add', 'cc-assistant' ); ?></button>
						</form>
					</details>

					<details class="cc-cluster-edit">
						<summary><?php esc_html_e( 'Edit cluster details', 'cc-assistant' ); ?></summary>
						<form method="post" class="cc-cluster-form">
							<?php wp_nonce_field( 'cc_clusters_manage', 'cc_clusters_nonce' ); ?>
							<input type="hidden" name="cc_clusters_action" value="update_cluster">
							<input type="hidden" name="cluster_id" value="<?php echo (int) $cluster->id; ?>">
							<label>
								<span><?php esc_html_e( 'Name', 'cc-assistant' ); ?></span>
								<input type="text" name="cluster_name" value="<?php echo esc_attr( $cluster->name ); ?>" required>
							</label>
							<label>
								<span><?php esc_html_e( 'Description', 'cc-assistant' ); ?></span>
								<textarea name="cluster_description" rows="2"><?php echo esc_textarea( $cluster->description ); ?></textarea>
							</label>
							<label>
								<span><?php esc_html_e( 'Pillar page', 'cc-assistant' ); ?></span>
								<select name="cluster_pillar_post_id">
									<option value=""><?php esc_html_e( '— None —', 'cc-assistant' ); ?></option>
									<?php foreach ( $post_pool as $p ) : ?>
										<option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) $cluster->pillar_post_id, (int) $p->ID ); ?>><?php echo esc_html( $p->post_title ?: '(no title)' ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'cc-assistant' ); ?></button>
						</form>
					</details>

					<form method="post" class="cc-cluster-delete-form" onsubmit="return confirm('<?php esc_attr_e( 'Delete this cluster? Posts will remain — only the cluster grouping is removed.', 'cc-assistant' ); ?>');">
						<?php wp_nonce_field( 'cc_clusters_manage', 'cc_clusters_nonce' ); ?>
						<input type="hidden" name="cc_clusters_action" value="delete_cluster">
						<input type="hidden" name="cluster_id" value="<?php echo (int) $cluster->id; ?>">
						<button type="submit" class="button-link cc-cluster-delete-btn"><?php esc_html_e( 'Delete cluster', 'cc-assistant' ); ?></button>
					</form>

				</div>
			</div>
		<?php endforeach; ?>

	<?php endif; ?>

	<div class="cc-card cc-cluster-create">
		<h2><?php esc_html_e( 'Create a new cluster', 'cc-assistant' ); ?></h2>
		<form method="post" class="cc-cluster-form">
			<?php wp_nonce_field( 'cc_clusters_manage', 'cc_clusters_nonce' ); ?>
			<input type="hidden" name="cc_clusters_action" value="create_cluster">
			<label>
				<span><?php esc_html_e( 'Name', 'cc-assistant' ); ?></span>
				<input type="text" name="cluster_name" placeholder="<?php esc_attr_e( 'e.g. Pediatric Emergency Care', 'cc-assistant' ); ?>" required>
			</label>
			<label>
				<span><?php esc_html_e( 'Description (optional)', 'cc-assistant' ); ?></span>
				<textarea name="cluster_description" rows="2" placeholder="<?php esc_attr_e( 'What this cluster is about. Helps Claude when classifying new pages.', 'cc-assistant' ); ?>"></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Pillar page (optional, can be set later)', 'cc-assistant' ); ?></span>
				<select name="cluster_pillar_post_id">
					<option value=""><?php esc_html_e( '— None for now —', 'cc-assistant' ); ?></option>
					<?php foreach ( $post_pool as $p ) : ?>
						<option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html( $p->post_title ?: '(no title)' ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Create cluster', 'cc-assistant' ); ?></button>
		</form>
	</div>
</div>

<script>
(function () {
	document.querySelectorAll('.cc-cluster-toggle').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var card = this.closest('.cc-cluster-card');
			var body = card.querySelector('.cc-cluster-body');
			var open = !body.hasAttribute('hidden');
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
			}
		});
	});

	// Cluster GSC overlay: lazy-loaded so the page doesn't run a heavy
	// query per cluster on render. Cached server-side for 30 minutes.
	var nonce  = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
	var rest   = '<?php echo esc_url_raw( rest_url( 'cc-assistant/v1/topic-clusters/' ) ); ?>';
	function escHtml(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]; }); }
	function num(n) { return Number(n || 0).toLocaleString(); }

	function renderGsc(panel, payload) {
		if (!payload || !payload.has_gsc) {
			panel.innerHTML = '<p class="description"><?php echo esc_js( __( 'No Search Console data for this cluster yet. Either GSC isn\'t connected or no member URLs have impressions in the last 28 days.', 'cc-assistant' ) ); ?></p>';
			return;
		}
		var t = payload.totals;
		var html = '<div class="cc-cluster-gsc-totals">'
			+ '<div class="cc-health-stat"><span class="cc-health-num">' + num(t.impressions) + '</span><span class="cc-health-lbl"><?php echo esc_js( __( 'cluster impressions', 'cc-assistant' ) ); ?></span></div>'
			+ '<div class="cc-health-stat"><span class="cc-health-num">' + num(t.clicks) + '</span><span class="cc-health-lbl"><?php echo esc_js( __( 'clicks', 'cc-assistant' ) ); ?></span></div>'
			+ '<div class="cc-health-stat"><span class="cc-health-num">' + Number(t.avg_position).toFixed(1) + '</span><span class="cc-health-lbl"><?php echo esc_js( __( 'avg position', 'cc-assistant' ) ); ?></span></div>'
			+ '<div class="cc-health-stat"><span class="cc-health-num">' + num(t.queries) + '</span><span class="cc-health-lbl"><?php echo esc_js( __( 'unique queries', 'cc-assistant' ) ); ?></span></div>'
			+ '</div>';

		if (payload.top_queries && payload.top_queries.length) {
			html += '<h4><?php echo esc_js( __( 'Top cluster queries', 'cc-assistant' ) ); ?></h4>'
				+ '<table class="cc-mini-table cc-cluster-gsc-table"><thead><tr>'
				+ '<th><?php echo esc_js( __( 'Query', 'cc-assistant' ) ); ?></th>'
				+ '<th><?php echo esc_js( __( 'Impr', 'cc-assistant' ) ); ?></th>'
				+ '<th><?php echo esc_js( __( 'Clicks', 'cc-assistant' ) ); ?></th>'
				+ '<th><?php echo esc_js( __( 'Pos', 'cc-assistant' ) ); ?></th>'
				+ '<th><?php echo esc_js( __( 'Top page', 'cc-assistant' ) ); ?></th>'
				+ '</tr></thead><tbody>';
			payload.top_queries.slice(0, 15).forEach(function (q) {
				var topPage = q.pages && q.pages.length ? q.pages[0] : null;
				html += '<tr><td>' + escHtml(q.query) + '</td>'
					+ '<td>' + num(q.impressions) + '</td>'
					+ '<td>' + num(q.clicks) + '</td>'
					+ '<td>' + Number(q.avg_position).toFixed(1) + '</td>'
					+ '<td>' + (topPage ? '<span class="cc-role-' + escHtml(topPage.role) + '">' + escHtml(topPage.title) + '</span>' : '—') + '</td>'
					+ '</tr>';
			});
			html += '</tbody></table>';
		}

		if (payload.pillar_misses && payload.pillar_misses.length) {
			html += '<h4 class="cc-warn-h"><?php echo esc_js( __( 'Pillar misses', 'cc-assistant' ) ); ?> <span class="description"><?php echo esc_js( __( '(supporting page outranks pillar)', 'cc-assistant' ) ); ?></span></h4><ul class="cc-mini-list">';
			payload.pillar_misses.forEach(function (m) {
				html += '<li><em>' + escHtml(m.query) + '</em> — '
					+ num(m.impressions) + ' <?php echo esc_js( __( 'impr', 'cc-assistant' ) ); ?>, '
					+ '<?php echo esc_js( __( 'winning page', 'cc-assistant' ) ); ?>: ' + escHtml(m.winning_page.title) + ' (' + Number(m.winning_page.avg_position).toFixed(1) + ')</li>';
			});
			html += '</ul>';
		}

		if (payload.internal_cannibalization && payload.internal_cannibalization.length) {
			html += '<h4 class="cc-warn-h"><?php echo esc_js( __( 'Internal cannibalization', 'cc-assistant' ) ); ?> <span class="description"><?php echo esc_js( __( '(2+ cluster pages competing)', 'cc-assistant' ) ); ?></span></h4><ul class="cc-mini-list">';
			payload.internal_cannibalization.forEach(function (c) {
				var titles = c.pages.map(function (p) { return escHtml(p.title); }).join(' / ');
				html += '<li><em>' + escHtml(c.query) + '</em> — ' + num(c.impressions) + ' <?php echo esc_js( __( 'impr', 'cc-assistant' ) ); ?>: ' + titles + '</li>';
			});
			html += '</ul>';
		}

		// Copy-prompt button when we found something Claude-actionable.
		var hasFinding = (payload.pillar_misses && payload.pillar_misses.length)
			|| (payload.internal_cannibalization && payload.internal_cannibalization.length);
		if (hasFinding) {
			var prompt = 'Use cc-assistant tools (cluster_gsc, get_topic_cluster, post_dossier). Cluster ' + payload.cluster_id
				+ ' ("' + (payload.cluster_name || '') + '") has '
				+ (payload.pillar_misses ? payload.pillar_misses.length : 0) + ' pillar misses and '
				+ (payload.internal_cannibalization ? payload.internal_cannibalization.length : 0) + ' internal cannibalization conflicts. '
				+ 'Read the cluster, identify which queries the pillar should own, and propose pending changes that consolidate authority on the pillar.';
			html += '<div class="cc-cluster-gsc-actions"><button type="button" class="button button-secondary cc-copy-prompt" data-prompt="' + escHtml(prompt) + '"><?php echo esc_js( __( 'Copy investigation prompt', 'cc-assistant' ) ); ?></button></div>';
		}

		panel.innerHTML = html;

		// Re-bind copy-prompt buttons we just injected (the dashboard binds
		// once at DOMContentLoaded, but this panel renders dynamically).
		panel.querySelectorAll('.cc-copy-prompt').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var p = btn.getAttribute('data-prompt');
				if (!p) return;
				navigator.clipboard && navigator.clipboard.writeText
					? navigator.clipboard.writeText(p).then(function () {
						var prev = btn.textContent;
						btn.textContent = '<?php echo esc_js( __( 'Copied!', 'cc-assistant' ) ); ?>';
						btn.classList.add('updated');
						setTimeout(function () { btn.textContent = prev; btn.classList.remove('updated'); }, 1800);
					})
					: (function () {
						var ta = document.createElement('textarea');
						ta.value = p; document.body.appendChild(ta); ta.select();
						try { document.execCommand('copy'); } catch (err) {}
						document.body.removeChild(ta);
					})();
			});
		});
	}

	document.querySelectorAll('.cc-cluster-gsc-toggle').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var cid = btn.getAttribute('data-cluster-id');
			var panel = document.querySelector('.cc-cluster-gsc-panel[data-cluster-id="' + cid + '"]');
			if (!panel) return;
			if (!panel.hasAttribute('hidden')) {
				panel.setAttribute('hidden', '');
				return;
			}
			panel.removeAttribute('hidden');
			if (btn.getAttribute('data-loaded') === '1') return;
			panel.innerHTML = '<p class="description"><?php echo esc_js( __( 'Loading Search Console overlay…', 'cc-assistant' ) ); ?></p>';
			fetch(rest + cid + '/gsc?days=28', {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': nonce, 'Accept': 'application/json' }
			})
			.then(function (r) { return r.json(); })
			.then(function (resp) {
				renderGsc(panel, resp && resp.data ? resp.data : resp);
				btn.setAttribute('data-loaded', '1');
			})
			.catch(function () {
				panel.innerHTML = '<p class="description"><?php echo esc_js( __( 'Could not load GSC overlay.', 'cc-assistant' ) ); ?></p>';
			});
		});
	});
})();
</script>

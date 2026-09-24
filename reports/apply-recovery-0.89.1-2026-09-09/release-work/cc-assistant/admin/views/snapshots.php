<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action_message = '';
$action_error   = '';

// Handle restore action.
if ( isset( $_POST['cc_snapshot_action'], $_POST['cc_snapshot_nonce'], $_POST['snapshot_id'] )
	&& wp_verify_nonce( $_POST['cc_snapshot_nonce'], 'cc_snapshot_manage' )
	&& 'restore' === $_POST['cc_snapshot_action'] ) {

	$force = ! empty( $_POST['cc_snapshot_force'] );
	$result = CC_Assistant_Snapshots::restore_snapshot( (int) $_POST['snapshot_id'], $force );
	$action_error_html = '';
	if ( is_wp_error( $result ) ) {
		$action_error = $result->get_error_message();
		if ( 'snapshot_drift_detected' === $result->get_error_code() ) {
			// Signal the render block below to use the with-override-form
			// layout. The form itself is rendered there as a sibling of the
			// <p> so the HTML5 parser doesn't auto-close it.
			$action_error_html = '1';
		}
	} else {
		$action_message = sprintf(
			/* translators: 1: restored snapshot id, 2: pre-restore snapshot id */
			__( 'Restored from snapshot #%1$d. Pre-restore snapshot #%2$d saved so you can undo.', 'cc-assistant' ),
			(int) $result['restored_from'],
			(int) $result['pre_restore_id']
		);
		if ( ! empty( $result['warning'] ) ) { $action_message .= ' ' . $result['warning']; }
	}
}

// Filters.
$filter_post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
$filter_type    = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
$paged          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page       = 25;
$offset         = ( $paged - 1 ) * $per_page;

global $wpdb;
$table = $wpdb->prefix . 'cc_snapshots';

// Build query with filters.
$where_clauses = array( '1=1' );
$where_args    = array();
if ( $filter_post_id > 0 ) {
	$where_clauses[] = 'post_id = %d';
	$where_args[]    = $filter_post_id;
}
if ( $filter_type ) {
	$where_clauses[] = 'snapshot_type = %s';
	$where_args[]    = $filter_type;
}
$where_sql = implode( ' AND ', $where_clauses );

$count_args = $where_args;
$total_rows = (int) ( empty( $where_args )
	? $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where_sql" )
	: $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $where_sql", $count_args ) )
);

$list_args = array_merge( $where_args, array( $per_page, $offset ) );
$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT id, post_id, snapshot_type, post_title, created_at, created_by, note
	 FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
	$list_args
) );

// Prime post cache for everything we'll render.
$post_ids = array();
foreach ( (array) $rows as $r ) {
	$post_ids[] = (int) $r->post_id;
}
if ( $post_ids ) {
	_prime_post_caches( array_unique( $post_ids ), false, false );
}

$total_pages = (int) ceil( $total_rows / $per_page );
$total_count = CC_Assistant_Snapshots::count_snapshots();

// Distinct snapshot types for the filter dropdown.
$types = $wpdb->get_col( "SELECT DISTINCT snapshot_type FROM $table ORDER BY snapshot_type ASC" );

if ( ! function_exists( 'cc_snapshot_type_label' ) ) {
	function cc_snapshot_type_label( $type ) {
		$map = array(
			'pre_apply'    => __( 'Before apply', 'cc-assistant' ),
			'pre_rollback' => __( 'Before rollback', 'cc-assistant' ),
			'pre_restore'  => __( 'Before restore', 'cc-assistant' ),
			'manual'       => __( 'Manual', 'cc-assistant' ),
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : ucfirst( str_replace( '_', ' ', $type ) );
	}
}
?>
<div class="wrap cc-assistant cc-snapshots-view">
	<h1><?php esc_html_e( 'Snapshots', 'cc-assistant' ); ?></h1>
	<p class="cc-tagline">
		<?php
		printf(
			/* translators: %d: total count */
			esc_html__( '%d snapshots saved across all posts. Restore any post to a captured state. Every restore takes a fresh snapshot first so the action is itself reversible.', 'cc-assistant' ),
			(int) $total_count
		);
		?>
	</p>

	<?php if ( $action_message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $action_message ); ?></p></div>
	<?php endif; ?>
	<?php if ( $action_error ) : ?>
		<?php if ( ! empty( $action_error_html ) ) : ?>
			<?php // Drift error: render the message in a <p> and the override
			      // <form> as a SIBLING (not nested in <p>) so the HTML5 parser
			      // doesn't auto-close the paragraph and break the notice layout. ?>
			<div class="notice notice-error is-dismissible" style="padding-bottom:12px;">
				<p><?php echo esc_html( $action_error ); ?></p>
				<form method="post" style="margin:0 0 4px 12px;">
					<?php wp_nonce_field( 'cc_snapshot_manage', 'cc_snapshot_nonce' ); ?>
					<input type="hidden" name="cc_snapshot_action" value="restore">
					<input type="hidden" name="snapshot_id" value="<?php echo (int) $_POST['snapshot_id']; ?>">
					<input type="hidden" name="cc_snapshot_force" value="1">
					<button type="submit" class="button button-small button-link-delete">
						<?php esc_html_e( 'Restore anyway (overwrites later edits)', 'cc-assistant' ); ?>
					</button>
				</form>
			</div>
		<?php else : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $action_error ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<form method="get" class="cc-snapshots-filters">
		<input type="hidden" name="page" value="cc-assistant-snapshots">
		<label>
			<?php esc_html_e( 'Post ID', 'cc-assistant' ); ?>
			<input type="number" name="post_id" value="<?php echo $filter_post_id ? esc_attr( $filter_post_id ) : ''; ?>" placeholder="any" min="0">
		</label>
		<label>
			<?php esc_html_e( 'Type', 'cc-assistant' ); ?>
			<select name="type">
				<option value=""><?php esc_html_e( 'Any type', 'cc-assistant' ); ?></option>
				<?php foreach ( (array) $types as $t ) : ?>
					<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $filter_type, $t ); ?>>
						<?php echo esc_html( cc_snapshot_type_label( $t ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'cc-assistant' ); ?></button>
		<?php if ( $filter_post_id || $filter_type ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-snapshots' ) ); ?>" class="button-link">
				<?php esc_html_e( 'Clear', 'cc-assistant' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<?php if ( empty( $rows ) ) : ?>
		<div class="cc-empty-state">
			<span class="dashicons dashicons-backup"></span>
			<h2><?php esc_html_e( 'No snapshots yet', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Snapshots are taken automatically before every applied change. Once Claude proposes and you approve a change, snapshots show up here.', 'cc-assistant' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat striped cc-snapshots-table">
			<thead>
				<tr>
					<th class="cc-col-id"><?php esc_html_e( 'ID', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Post', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Type', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Captured', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Note', 'cc-assistant' ); ?></th>
					<th class="cc-col-actions"><?php esc_html_e( 'Actions', 'cc-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $r ) :
					$post        = get_post( (int) $r->post_id );
					$post_title  = $post ? get_the_title( $post ) : sprintf( __( '(post #%d, deleted)', 'cc-assistant' ), (int) $r->post_id );
					$edit_link   = $post ? get_edit_post_link( $r->post_id ) : '';
					$type_label  = cc_snapshot_type_label( $r->snapshot_type );
					$type_class  = 'cc-snap-type-' . sanitize_html_class( $r->snapshot_type );
					$age         = human_time_diff( strtotime( $r->created_at ), current_time( 'timestamp' ) );
					?>
					<tr>
						<td class="cc-col-id">#<?php echo (int) $r->id; ?></td>
						<td>
							<?php if ( $edit_link ) : ?>
								<a href="<?php echo esc_url( $edit_link ); ?>" class="cc-post-link"><?php echo esc_html( $post_title ); ?></a>
							<?php else : ?>
								<em><?php echo esc_html( $post_title ); ?></em>
							<?php endif; ?>
							<div class="cc-snap-postid"><code><?php echo (int) $r->post_id; ?></code></div>
						</td>
						<td>
							<span class="cc-snap-type-badge <?php echo esc_attr( $type_class ); ?>">
								<?php echo esc_html( $type_label ); ?>
							</span>
						</td>
						<td>
							<span title="<?php echo esc_attr( $r->created_at ); ?>">
								<?php echo esc_html( $age ); ?> <?php esc_html_e( 'ago', 'cc-assistant' ); ?>
							</span>
						</td>
						<td class="cc-snap-note">
							<?php if ( $r->note ) : ?>
								<?php echo esc_html( $r->note ); ?>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td class="cc-col-actions">
							<?php if ( $post ) : ?>
								<button type="button" class="button button-small cc-snap-preview" data-snap-id="<?php echo (int) $r->id; ?>" data-post-id="<?php echo (int) $r->post_id; ?>">
									<span class="dashicons dashicons-visibility"></span>
									<?php esc_html_e( 'Preview', 'cc-assistant' ); ?>
								</button>
								<form method="post" class="cc-snap-restore-form" data-post-title="<?php echo esc_attr( $post_title ); ?>" data-snap-id="<?php echo (int) $r->id; ?>">
									<?php wp_nonce_field( 'cc_snapshot_manage', 'cc_snapshot_nonce' ); ?>
									<input type="hidden" name="cc_snapshot_action" value="restore">
									<input type="hidden" name="snapshot_id" value="<?php echo (int) $r->id; ?>">
									<button type="submit" class="button button-small">
										<span class="dashicons dashicons-undo"></span>
										<?php esc_html_e( 'Restore', 'cc-assistant' ); ?>
									</button>
								</form>
							<?php else : ?>
								<span class="description"><?php esc_html_e( '(post deleted)', 'cc-assistant' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr class="cc-snap-preview-row" id="cc-snap-preview-<?php echo (int) $r->id; ?>" hidden>
						<td colspan="6" class="cc-snap-preview-cell">
							<div class="cc-snap-preview-body" data-loaded="0"></div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

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
	// Confirm before restoring. Native confirm() is fine here — it's a destructive
	// admin action and feeling a tiny friction prevents accidental clicks.
	document.querySelectorAll('.cc-snap-restore-form').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			var title = form.dataset.postTitle || 'this post';
			var snapId = form.dataset.snapId || '';
			var msg = 'Restore "' + title + '" from snapshot #' + snapId + '?\n\n' +
				'This overwrites the current post content and meta. A pre-restore snapshot will be saved first so you can undo.';
			if (!confirm(msg)) {
				e.preventDefault();
			}
		});
	});

	// Preview diff: snapshot vs current post. Lazy-loaded so the page renders
	// fast and only the snapshots the user actually inspects pull the diff.
	var nonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
	var rest  = '<?php echo esc_url_raw( rest_url( 'cc-assistant/v1/snapshots/' ) ); ?>';
	document.querySelectorAll('.cc-snap-preview').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var sid = btn.getAttribute('data-snap-id');
			var row = document.getElementById('cc-snap-preview-' + sid);
			if (!row) return;
			if (!row.hasAttribute('hidden')) { row.setAttribute('hidden', ''); return; }
			row.removeAttribute('hidden');
			var body = row.querySelector('.cc-snap-preview-body');
			if (body.getAttribute('data-loaded') === '1') return;
			body.innerHTML = '<p class="description">Loading diff…</p>';
			fetch(rest + sid + '/diff', {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': nonce, 'Accept': 'application/json' }
			})
			.then(function (r) { return r.json(); })
			.then(function (resp) {
				var data = resp && resp.data ? resp.data : resp;
				if (data && data.html) {
					body.innerHTML = data.html;
				} else if (data && data.message) {
					body.innerHTML = '<p class="description">' + data.message + '</p>';
				} else {
					body.innerHTML = '<p class="description">No diff available.</p>';
				}
				body.setAttribute('data-loaded', '1');
			})
			.catch(function () { body.innerHTML = '<p class="description">Could not load diff.</p>'; });
		});
	});
})();
</script>

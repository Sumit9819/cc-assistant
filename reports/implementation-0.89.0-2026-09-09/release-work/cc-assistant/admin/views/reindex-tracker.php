<?php
/**
 * Reindex Tracker view.
 *
 * Lists every post that has had at least one applied edit through cc-assistant,
 * grouped by post_id with last_applied date + change types, and surfaces a
 * one-click "Open in GSC URL Inspection" action plus a "mark as submitted"
 * toggle so the human can track which URLs they have already requested
 * indexing for after a content change.
 *
 * Persistence: postmeta key `_cc_gsc_reindexed_at` stores the timestamp the
 * human marked the post as submitted. If a NEW edit is applied to the same
 * post after that timestamp, the row resurfaces as unsubmitted automatically
 * (because the indexing request only covers state-at-the-time-of-submit).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Insufficient permissions.' );
}

global $wpdb;

// Form POSTs go to admin-post.php?action=cc_reindex_toggle which is wired in
// CC_Assistant_Admin::init() to handle_reindex_toggle(). Doing the redirect
// at admin_post_* time runs BEFORE admin headers send, so wp_safe_redirect
// works. The previous inline handler ran inside the view, AFTER headers were
// already sent, which produced a silent white screen.

// Filters from query string.
$filter_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'unsubmitted';
$filter_window = isset( $_GET['window'] ) ? sanitize_key( $_GET['window'] ) : '90';
if ( ! in_array( $filter_status, array( 'all', 'submitted', 'unsubmitted' ), true ) ) {
	$filter_status = 'unsubmitted';
}
if ( ! in_array( $filter_window, array( '7', '30', '90', 'all' ), true ) ) {
	$filter_window = '90';
}

$cutoff_clause = '';
if ( 'all' !== $filter_window ) {
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( (int) $filter_window * DAY_IN_SECONDS ) );
	$cutoff_clause = $wpdb->prepare( 'AND applied_at >= %s', $cutoff );
}

$table = $wpdb->prefix . 'cc_edits';

$sql = "SELECT
			post_id,
			MAX(applied_at) AS last_applied,
			COUNT(*) AS change_count,
			GROUP_CONCAT(DISTINCT change_type ORDER BY change_type SEPARATOR '|') AS change_types
		FROM {$table}
		WHERE post_id IS NOT NULL AND post_id > 0
		{$cutoff_clause}
		GROUP BY post_id
		ORDER BY last_applied DESC
		LIMIT 500";

$rows = $wpdb->get_results( $sql );
$rows = is_array( $rows ) ? $rows : array();

// Enrich with permalink + submitted status.
$enriched = array();
$skipped_nonpublic = 0;
foreach ( $rows as $r ) {
	$post = get_post( (int) $r->post_id );
	if ( ! $post ) {
		continue;
	}

	// Drafted / trashed / pending posts are not live URLs that Google can
	// re-index. They show up here only because they had an applied edit
	// (e.g. setting status=draft on a duplicate). Skip them — but count so
	// the user knows they were filtered.
	if ( 'publish' !== $post->post_status ) {
		$skipped_nonpublic++;
		continue;
	}

	// Both sides are GMT (cc_edits.applied_at always was; the submit stamp is
	// GMT from v0.71.0 on). Stamps written by earlier versions are site-local
	// and therefore read as slightly older than they really are — that errs
	// toward showing a post as still needing submission, which is the safe
	// direction, so no migration is required.
	$submitted_at = get_post_meta( (int) $r->post_id, '_cc_gsc_reindexed_at', true );
	$is_submitted = ! empty( $submitted_at ) && strtotime( (string) $submitted_at . ' UTC' ) >= strtotime( (string) $r->last_applied . ' UTC' );

	if ( 'submitted' === $filter_status && ! $is_submitted ) {
		continue;
	}
	if ( 'unsubmitted' === $filter_status && $is_submitted ) {
		continue;
	}

	// Resolve the friendly permalink. For published posts get_permalink
	// returns the slug-based URL. As a defensive fallback (in case a custom
	// permalink structure or filter returns the ugly ?p=ID form), we
	// reconstruct from post_name so the user can recognize the post from
	// the URL alone.
	$permalink = get_permalink( $post );
	if ( ! $permalink || false !== strpos( $permalink, '?p=' ) ) {
		if ( ! empty( $post->post_name ) ) {
			$permalink = home_url( '/' . $post->post_name . '/' );
		}
	}

	$enriched[] = array(
		'post_id'       => (int) $r->post_id,
		'title'         => $post->post_title,
		'permalink'     => $permalink,
		'slug'          => $post->post_name,
		'edit_url'      => get_edit_post_link( (int) $r->post_id, 'raw' ),
		'last_applied'  => $r->last_applied,
		'change_count'  => (int) $r->change_count,
		'change_types'  => array_filter( explode( '|', (string) $r->change_types ) ),
		'submitted_at'  => $submitted_at,
		'is_submitted'  => $is_submitted,
		'post_type'     => $post->post_type,
		'post_status'   => $post->post_status,
	);
}

$total_unsubmitted = 0;
$total_submitted   = 0;
foreach ( $enriched as $row ) {
	if ( $row['is_submitted'] ) {
		$total_submitted++;
	} else {
		$total_unsubmitted++;
	}
}

// Build the GSC URL Inspection deep-link scoped to the connected property.
//
// IMPORTANT: GSC's `id=` parameter is an opaque hash token generated server-side
// by Google when you submit a URL through the search bar. It is NOT the URL
// itself. There's no documented public deep-link format that accepts a URL
// parameter and pre-fills the inspect bar. Best we can do is open the URL
// Inspection tool scoped to the right property and let the user paste the URL.
// The "Copy URL" button next to each row makes the paste step one click.
function cc_gsc_inspect_link() {
	$site_url = get_option( 'cc_assistant_gsc_property', home_url( '/' ) );
	return add_query_arg(
		array(
			'resource_id' => $site_url,
		),
		'https://search.google.com/search-console/inspect'
	);
}

?>
<div class="wrap">
	<h1>Reindex Tracker</h1>

	<p style="max-width:800px;color:#555;">
		Posts and pages with applied edits through CC Assistant. After approving content changes, submit each URL for re-indexing in Google Search Console.
	</p>
	<div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 16px;margin:12px 0;max-width:800px;">
		<strong>How to submit:</strong> (1) Click <em>Copy URL</em>, (2) click <em>Inspect in GSC</em> to open URL Inspection in a new tab, (3) paste the URL into the search bar at the top of GSC, (4) click <em>Request Indexing</em>. Then come back here and click <em>Mark submitted</em>.
		<br><br>
		<small style="color:#666;">Why two steps? Google Search Console doesn't support deep-links that pre-fill the URL bar — the inspection tool requires an opaque token Google generates internally. Copy + paste is the practical workaround.</small>
	</div>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Updated.</p></div>
	<?php endif; ?>

	<?php if ( $skipped_nonpublic > 0 ) : ?>
		<div class="notice notice-info" style="padding:10px 14px;">
			<p style="margin:0;">
				<strong><?php echo esc_html( $skipped_nonpublic ); ?></strong> drafted/trashed/pending post(s) hidden from the list. They had applied edits (e.g. status change to draft for a duplicate) but are no longer live URLs that Google can re-index.
			</p>
		</div>
	<?php endif; ?>

	<div style="margin:18px 0;display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
		<div>
			<strong>Status:</strong>
			<?php $base = remove_query_arg( array( 'status', 'updated' ) ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'unsubmitted', $base ) ); ?>" class="<?php echo 'unsubmitted' === $filter_status ? 'button button-primary' : 'button'; ?>">Unsubmitted (<?php echo esc_html( $total_unsubmitted ); ?>)</a>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'submitted', $base ) ); ?>" class="<?php echo 'submitted' === $filter_status ? 'button button-primary' : 'button'; ?>">Submitted (<?php echo esc_html( $total_submitted ); ?>)</a>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'all', $base ) ); ?>" class="<?php echo 'all' === $filter_status ? 'button button-primary' : 'button'; ?>">All</a>
		</div>
		<div>
			<strong>Window:</strong>
			<?php $base2 = remove_query_arg( array( 'window', 'updated' ) ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'window', '7', $base2 ) ); ?>" class="<?php echo '7' === $filter_window ? 'button button-primary' : 'button'; ?>">7 days</a>
			<a href="<?php echo esc_url( add_query_arg( 'window', '30', $base2 ) ); ?>" class="<?php echo '30' === $filter_window ? 'button button-primary' : 'button'; ?>">30 days</a>
			<a href="<?php echo esc_url( add_query_arg( 'window', '90', $base2 ) ); ?>" class="<?php echo '90' === $filter_window ? 'button button-primary' : 'button'; ?>">90 days</a>
			<a href="<?php echo esc_url( add_query_arg( 'window', 'all', $base2 ) ); ?>" class="<?php echo 'all' === $filter_window ? 'button button-primary' : 'button'; ?>">All time</a>
		</div>
	</div>

	<?php if ( empty( $enriched ) ) : ?>
		<div class="notice notice-info" style="padding:18px;">
			<p>No posts in this view. Try widening the time window or switching to "All".</p>
		</div>
	<?php else : ?>
		<table class="widefat striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th style="width:40%;">URL / Title</th>
					<th>Last edit</th>
					<th>Changes</th>
					<th>Type(s)</th>
					<th>Status</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $enriched as $row ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $row['title'] ); ?></strong>
							<br>
							<a href="<?php echo esc_url( $row['permalink'] ); ?>" target="_blank" rel="noopener" style="font-size:12px;color:#555;">
								<?php echo esc_html( str_replace( home_url( '/' ), '/', $row['permalink'] ) ); ?>
							</a>
							<?php if ( $row['edit_url'] ) : ?>
								&middot;
								<a href="<?php echo esc_url( $row['edit_url'] ); ?>" style="font-size:12px;">Edit</a>
							<?php endif; ?>
							<br>
							<span style="font-size:11px;color:#777;">
								Type: <?php echo esc_html( $row['post_type'] ); ?>
								&middot; Status: <?php echo esc_html( $row['post_status'] ); ?>
								&middot; ID: <?php echo esc_html( $row['post_id'] ); ?>
							</span>
						</td>
						<td><?php echo esc_html( mysql2date( 'M j, Y H:i', $row['last_applied'] ) ); ?></td>
						<td><?php echo esc_html( $row['change_count'] ); ?></td>
						<td>
							<?php foreach ( $row['change_types'] as $ct ) : ?>
								<code style="font-size:11px;background:#f0f0f1;padding:2px 6px;border-radius:3px;display:inline-block;margin:1px;">
									<?php echo esc_html( $ct ); ?>
								</code>
							<?php endforeach; ?>
						</td>
						<td>
							<?php if ( $row['is_submitted'] ) : ?>
								<span style="color:#1d7d3a;">Submitted</span>
								<br>
								<small style="color:#777;"><?php echo esc_html( mysql2date( 'M j, H:i', $row['submitted_at'] ) ); ?></small>
							<?php elseif ( $row['submitted_at'] ) : ?>
								<span style="color:#a00;">Stale</span>
								<br>
								<small style="color:#777;">New edit since submit</small>
							<?php else : ?>
								<span style="color:#a00;">Not submitted</span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button button-small cc-copy-url" data-url="<?php echo esc_attr( $row['permalink'] ); ?>">Copy URL</button>
							<a href="<?php echo esc_url( cc_gsc_inspect_link() ); ?>" target="_blank" rel="noopener" class="button button-small">Inspect in GSC</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
								<?php wp_nonce_field( 'cc_reindex_toggle' ); ?>
								<input type="hidden" name="action" value="cc_reindex_toggle">
								<input type="hidden" name="cc_reindex_post_id" value="<?php echo esc_attr( $row['post_id'] ); ?>">
								<input type="hidden" name="cc_reindex_status" value="<?php echo esc_attr( $filter_status ); ?>">
								<input type="hidden" name="cc_reindex_window" value="<?php echo esc_attr( $filter_window ); ?>">
								<?php if ( $row['is_submitted'] ) : ?>
									<input type="hidden" name="cc_reindex_action" value="unmark">
									<button class="button button-small">Unmark</button>
								<?php else : ?>
									<input type="hidden" name="cc_reindex_action" value="mark">
									<button class="button button-small button-primary">Mark submitted</button>
								<?php endif; ?>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<p style="margin-top:24px;color:#777;font-size:12px;max-width:800px;">
		Data source: <code><?php echo esc_html( $wpdb->prefix . 'cc_edits' ); ?></code> (every applied change). Submitted status is stored per-post in postmeta key <code>_cc_gsc_reindexed_at</code>. The "Stale" indicator shows posts you marked submitted before, but a NEW edit has applied since &mdash; submit again to refresh Google's view.
	</p>
</div>

<script>
( function () {
	document.querySelectorAll( '.cc-copy-url' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var url = btn.getAttribute( 'data-url' );
			if ( ! url ) { return; }
			var orig = btn.textContent;
			var done = function () {
				btn.textContent = 'Copied!';
				btn.style.background = '#d1e7dd';
				setTimeout( function () {
					btn.textContent = orig;
					btn.style.background = '';
				}, 1500 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( url ).then( done ).catch( function () {
					// Fallback for non-https admin contexts.
					var ta = document.createElement( 'textarea' );
					ta.value = url;
					ta.style.position = 'fixed';
					ta.style.left = '-9999px';
					document.body.appendChild( ta );
					ta.select();
					try { document.execCommand( 'copy' ); done(); } catch ( e ) { btn.textContent = 'Copy failed'; }
					document.body.removeChild( ta );
				} );
			} else {
				var ta2 = document.createElement( 'textarea' );
				ta2.value = url;
				ta2.style.position = 'fixed';
				ta2.style.left = '-9999px';
				document.body.appendChild( ta2 );
				ta2.select();
				try { document.execCommand( 'copy' ); done(); } catch ( e ) { btn.textContent = 'Copy failed'; }
				document.body.removeChild( ta2 );
			}
		} );
	} );
} )();
</script>

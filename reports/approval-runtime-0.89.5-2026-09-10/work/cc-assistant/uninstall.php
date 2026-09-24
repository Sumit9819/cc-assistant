<?php
/**
 * CC Assistant uninstall handler.
 *
 * Fires when the user clicks Delete on the plugin row in wp-admin/plugins.
 * Cleans up everything the plugin created so the database returns to a
 * pristine state with no orphan tables, options, transients, or scheduled
 * cron events.
 *
 * Triggered only by WordPress (via WP_UNINSTALL_PLUGIN constant). Direct
 * loads exit immediately.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/* ---------------------------------------------------------------------------
 * 0. Data-destruction guards (v0.60.1 security audit).
 *
 * (a) Opt-in: nothing is deleted unless the operator explicitly set
 *     cc_assistant_delete_data_on_uninstall. Uninstalling the plugin without
 *     it leaves every table/option intact for reinstall — this fleet's
 *     site-memory, tokens, and pending queue are irreplaceable.
 * (b) Duplicate-folder guard: deleting a COPY of the plugin folder
 *     (cc-assistant-2, cc-assistant.old) runs THAT copy's uninstall.php
 *     against the live database — the exact incident this fleet already
 *     suffered once. Only the folder named 'cc-assistant' may wipe data.
 * ------------------------------------------------------------------------- */
if ( ! get_option( 'cc_assistant_delete_data_on_uninstall' ) ) {
	return;
}
if ( 'cc-assistant' !== basename( __DIR__ ) ) {
	return;
}

/* ---------------------------------------------------------------------------
 * 1. Drop all plugin-owned database tables.
 * ------------------------------------------------------------------------- */
$tables = array(
	$wpdb->prefix . 'cc_snapshots',
	$wpdb->prefix . 'cc_pending_changes',
	$wpdb->prefix . 'cc_embeddings',
	$wpdb->prefix . 'cc_gsc_queries',
	$wpdb->prefix . 'cc_gsc_appearances',
	$wpdb->prefix . 'cc_edits',
	$wpdb->prefix . 'cc_llm_crawls',
	$wpdb->prefix . 'cc_link_graph',
	$wpdb->prefix . 'cc_topic_clusters',
	$wpdb->prefix . 'cc_cluster_members',
	$wpdb->prefix . 'cc_cannibalization_trends',
	// v0.60.1: previously orphaned on uninstall.
	$wpdb->prefix . 'cc_activity_log',
	$wpdb->prefix . 'cc_lead_events',
	$wpdb->prefix . 'cc_page_facts',
);
foreach ( $tables as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS `$t`" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/* ---------------------------------------------------------------------------
 * 2. Delete every option whose key starts with cc_assistant_.
 * Wildcard SQL is safer than enumerating — captures all detected-stack
 * caches, GSC tokens, dismissed-notice states, etc., even ones added by
 * features after this file was last touched.
 * ------------------------------------------------------------------------- */
$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", $wpdb->esc_like( 'cc_assistant_' ) . '%' ) );

// Two more options the plugin owns that don't follow the prefix:
$other_options = array(
	'cc_weekly_advisor_priorities',
	'cc_weekly_advisor_dismissed',
	'cc_weekly_advisor_category_dismiss',
);
foreach ( $other_options as $opt ) {
	delete_option( $opt );
}

/* ---------------------------------------------------------------------------
 * 3. Delete every transient with a cc_ or cc_assistant_ prefix.
 * Transients are stored in wp_options (or wp_sitemeta on multisite). Both
 * the value row and the timeout row need to go.
 * ------------------------------------------------------------------------- */
$wpdb->query(
	"DELETE FROM `{$wpdb->options}`
	 WHERE option_name LIKE '\\_transient\\_cc\\_%' ESCAPE '\\\\'
	    OR option_name LIKE '\\_transient\\_timeout\\_cc\\_%' ESCAPE '\\\\'"
);

/* ---------------------------------------------------------------------------
 * 4. Clear all scheduled cron events the plugin registered.
 * wp_unschedule_hook removes ALL scheduled instances for a hook,
 * including single-events with stored args.
 * ------------------------------------------------------------------------- */
$cron_hooks = array(
	'cc_assistant_gsc_sync',
	'cc_assistant_gsc_sync_now',
	'cc_assistant_gsc_backfill_chunk',
	'cc_assistant_recompute_insights',
	'cc_assistant_link_graph_rebuild',
	'cc_assistant_llm_prune',
	'cc_assistant_site_audit',
	'cc_assistant_cannib_trends',
	'cc_assistant_verdict_notify',
	'cc_assistant_warm_rendered',
	'cc_assistant_warm_rendered_batch',
	'cc_assistant_advisor_recompute',
	'cc_assistant_calendar_refresh_warm',
	// v0.60.1: previously orphaned.
	'cc_assistant_activity_log_prune',
	'cc_assistant_reviews_refresh',
	'cc_assistant_storage_maintenance',
	'cc_assistant_page_facts_sweep',
	'cc_assistant_page_facts_capture',
	'cc_assistant_post_apply_audit',
	'cc_assistant_run_verification',
);
foreach ( $cron_hooks as $hook ) {
	wp_unschedule_hook( $hook );
}

/* ---------------------------------------------------------------------------
 * 5. Drop user meta the plugin set on individual users (none currently,
 * but reserved for the future — leaving a stub here so a feature added
 * later doesn't strand its user_meta).
 * ------------------------------------------------------------------------- */
// $wpdb->query( "DELETE FROM `{$wpdb->usermeta}` WHERE meta_key LIKE 'cc_assistant_%'" );

/* ---------------------------------------------------------------------------
 * 6. Drop post meta the plugin sets on individual posts.
 * Currently only _cc_assistant_schema_jsonld (from class-schema-generator).
 * ------------------------------------------------------------------------- */
// v0.60.1: sweep ALL plugin postmeta (was only schema_jsonld; ~13 _cc_ keys
// were orphaned across every post).
$wpdb->query( "DELETE FROM `{$wpdb->postmeta}` WHERE meta_key LIKE '\\_cc\\_%'" );

/* ---------------------------------------------------------------------------
 * 7. Object cache flush so any cached options/transients held in memory
 * (Redis/Memcached) drop too.
 * ------------------------------------------------------------------------- */
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Activator {

	const DB_VERSION = '0.10.0';

	/**
	 * One-shot DB migrations for installs that activated before a column or
	 * index was added. Runs cheaply on admin_init: checks a stored version
	 * flag and only does work when out of date.
	 */
	public static function maybe_upgrade() {
		$current = get_option( 'cc_assistant_db_version', '0.0.0' );
		if ( version_compare( $current, self::DB_VERSION, '>=' ) ) {
			return;
		}

		global $wpdb;
		$gsc_table = $wpdb->prefix . 'cc_gsc_queries';

		// 0.2.0: composite indexes for trend lenses on big GSC tables.
		if ( version_compare( $current, '0.2.0', '<' ) ) {
			$existing = $wpdb->get_results( "SHOW INDEX FROM {$gsc_table}", ARRAY_A );
			$names    = array();
			foreach ( (array) $existing as $row ) {
				$names[ $row['Key_name'] ] = true;
			}
			if ( ! isset( $names['date_page'] ) ) {
				$wpdb->query( "ALTER TABLE {$gsc_table} ADD INDEX date_page (date, page_hash)" );
			}
			if ( ! isset( $names['date_query'] ) ) {
				$wpdb->query( "ALTER TABLE {$gsc_table} ADD INDEX date_query (date, query_hash)" );
			}
		}

		// 0.3.0: cannibalization trend history table. Previously created
		// lazily via ensure_table() on first cron — add to upgrade path so
		// fresh installs and existing installs both have it on day one.
		if ( version_compare( $current, '0.3.0', '<' ) ) {
			$trends_table    = $wpdb->prefix . 'cc_cannibalization_trends';
			$charset_collate = $wpdb->get_charset_collate();
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta(
				"CREATE TABLE $trends_table (
					id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					captured_at DATETIME NOT NULL,
					conflict_count INT UNSIGNED NOT NULL DEFAULT 0,
					leak_impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
					top_query VARCHAR(255) NULL,
					PRIMARY KEY (id),
					KEY captured_at (captured_at)
				) $charset_collate;"
			);
		}

		// 0.4.0: pending-changes lint_report + success_metrics. Lets the queue
		// step attach a content-quality report (paragraph length, redundancy,
		// reading level, etc.) and the model attach optional success metrics
		// (target position, target CTR) for outcome scoring.
		if ( version_compare( $current, '0.4.0', '<' ) ) {
			$pending_table = $wpdb->prefix . 'cc_pending_changes';
			$existing      = $wpdb->get_results( "SHOW COLUMNS FROM {$pending_table}", ARRAY_A );
			$cols          = array();
			foreach ( (array) $existing as $row ) {
				$cols[ $row['Field'] ] = true;
			}
			if ( ! isset( $cols['lint_report'] ) ) {
				$wpdb->query( "ALTER TABLE {$pending_table} ADD COLUMN lint_report LONGTEXT NULL AFTER reasoning" );
			}
			if ( ! isset( $cols['success_metrics'] ) ) {
				$wpdb->query( "ALTER TABLE {$pending_table} ADD COLUMN success_metrics LONGTEXT NULL AFTER lint_report" );
			}
		}

		// 0.5.0: pending-changes pre_check_baseline + verification_result.
		// Captures cosine cluster snapshot at queue time (pre_check_baseline)
		// and the post-apply re-check delta (verification_result) so the
		// inbox can surface a Differentiated / Stable / Regressed pill per
		// applied body rewrite. See class-post-apply-verifier.php.
		if ( version_compare( $current, '0.5.0', '<' ) ) {
			$pending_table = $wpdb->prefix . 'cc_pending_changes';
			$existing      = $wpdb->get_results( "SHOW COLUMNS FROM {$pending_table}", ARRAY_A );
			$cols          = array();
			foreach ( (array) $existing as $row ) {
				$cols[ $row['Field'] ] = true;
			}
			if ( ! isset( $cols['pre_check_baseline'] ) ) {
				$wpdb->query( "ALTER TABLE {$pending_table} ADD COLUMN pre_check_baseline LONGTEXT NULL AFTER success_metrics" );
			}
			if ( ! isset( $cols['verification_result'] ) ) {
				$wpdb->query( "ALTER TABLE {$pending_table} ADD COLUMN verification_result LONGTEXT NULL AFTER pre_check_baseline" );
			}
		}

		// 0.7.0: activity_log table — the 2-day session-bootstrap timeline that
		// gives a fresh chat instant context on what happened recently without
		// having to scan pending_changes + edits + snapshots + memory notes
		// separately. Pruned daily by cc_assistant_activity_log_prune cron.
		if ( version_compare( $current, '0.7.0', '<' ) ) {
			$activity_table  = $wpdb->prefix . 'cc_activity_log';
			$charset_collate = $wpdb->get_charset_collate();
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta(
				"CREATE TABLE $activity_table (
					id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					ts DATETIME NOT NULL,
					type VARCHAR(40) NOT NULL,
					actor VARCHAR(40) NOT NULL DEFAULT 'system',
					post_id BIGINT(20) UNSIGNED NULL,
					change_id BIGINT(20) UNSIGNED NULL,
					summary VARCHAR(500) NOT NULL DEFAULT '',
					PRIMARY KEY (id),
					KEY ts (ts),
					KEY type (type),
					KEY post_id (post_id)
				) $charset_collate;"
			);
			if ( ! wp_next_scheduled( 'cc_assistant_activity_log_prune' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cc_assistant_activity_log_prune' );
			}
		}

		// 0.6.0: pending-changes superseded_by. Auto-marks older same-target
		// pending rows as superseded when a v2 lands, so the inbox does not
		// show duplicate rows for the same intent. The column is a back-pointer
		// to the row that replaced it; queries filter superseded_by IS NULL
		// for the active inbox view. See CC_Assistant_Pending_Changes::queue().
		if ( version_compare( $current, '0.6.0', '<' ) ) {
			$pending_table = $wpdb->prefix . 'cc_pending_changes';
			$existing      = $wpdb->get_results( "SHOW COLUMNS FROM {$pending_table}", ARRAY_A );
			$cols          = array();
			foreach ( (array) $existing as $row ) {
				$cols[ $row['Field'] ] = true;
			}
			if ( ! isset( $cols['superseded_by'] ) ) {
				$wpdb->query( "ALTER TABLE {$pending_table} ADD COLUMN superseded_by BIGINT(20) UNSIGNED NULL AFTER verification_result" );
				$wpdb->query( "ALTER TABLE {$pending_table} ADD INDEX superseded_by (superseded_by)" );
			}
		}

		// 0.10.0: guarantee cc_gsc_queries.page_canonical_hash exists.
		//
		// The column has been in create_tables() and ensure_table() for a
		// while, but ensure_table() early-returns when the table is already
		// present and maybe_upgrade() never re-runs dbDelta — so any install
		// whose GSC table predates the column never received it. From v0.71.0
		// the outcome engine matches on this column, and a missing column is a
		// fatal SQL error rather than a degraded result, so it has to be an
		// explicit migration.
		//
		// No backfill is required for correctness: the outcome query matches
		// (page_canonical_hash = X OR page_hash = Y), so legacy rows left at ''
		// still resolve exactly as they did before. The backfill below simply
		// lets historical rows benefit from canonical matching too, and is
		// bounded so a very large table cannot stall an admin request.
		if ( version_compare( $current, '0.10.0', '<' ) ) {
			$gsc_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $gsc_table ) ) );
			if ( $gsc_exists === $gsc_table ) {
				$gsc_cols  = $wpdb->get_results( "SHOW COLUMNS FROM {$gsc_table}", ARRAY_A );
				$gsc_names = array();
				foreach ( (array) $gsc_cols as $row ) {
					$gsc_names[ $row['Field'] ] = true;
				}
				if ( ! isset( $gsc_names['page_canonical_hash'] ) ) {
					$wpdb->query( "ALTER TABLE {$gsc_table} ADD COLUMN page_canonical_hash CHAR(40) NOT NULL DEFAULT '' AFTER query_hash" );
					$wpdb->query( "ALTER TABLE {$gsc_table} ADD INDEX page_canonical_hash (page_canonical_hash)" );
				}

				// Bounded backfill over DISTINCT pages, not rows.
				require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
				$pages = $wpdb->get_col(
					"SELECT DISTINCT page FROM {$gsc_table} WHERE page_canonical_hash = '' LIMIT 2000"
				);
				foreach ( (array) $pages as $page_url ) {
					$canon = CC_Assistant_Query_Tagger::canonical_hash( (string) $page_url );
					if ( '' === $canon ) {
						continue;
					}
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$gsc_table} SET page_canonical_hash = %s WHERE page = %s AND page_canonical_hash = ''",
							$canon,
							$page_url
						)
					);
				}
			}
		}

		// 0.8.0: one-shot Laser Genesis pillar self-heal. v0.18.3 ran this on
		// every admin_init as a site-fingerprint-gated hot-patch; it has long
		// since fired on the only affected install, so the always-on cost is
		// pure waste. Move to a one-time migration run: if site is the
		// irvingwellnessclinic install and post 9983's first root is the
		// reversed-state signature, reverse it once and never again. Cost on
		// every other site is one option read (already paid by maybe_upgrade
		// itself).
		if ( version_compare( $current, '0.8.0', '<' ) ) {
			self::lg_oneshot_heal();
		}

		// 0.9.0: garbage-collect defective plugin-managed schema postmeta.
		// Older schema writers stored (a) syntactically invalid JSON-LD blobs
		// (~10KB each) that the wp_head emitter silently skips and (b) empty
		// 0-byte emergency-service rows — dormant garbage invisible to
		// rendered-output audits (found via managed_schema on a production
		// install: 6 invalid blobs + 5 empty rows). The queue and apply paths
		// now refuse both at write time; this one-shot sweep cleans what
		// older versions already stored. Valid rows are untouched.
		if ( version_compare( $current, '0.9.0', '<' ) ) {
			$schema_keys  = array( '_cc_assistant_schema_jsonld', '_cc_emergency_service_schema' );
			$gc           = array( 'empty_removed' => 0, 'invalid_removed' => 0, 'valid_kept' => 0 );
			$placeholders = implode( ',', array_fill( 0, count( $schema_keys ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are %s, values bound below.
			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)",
					$schema_keys
				)
			);
			foreach ( (array) $meta_rows as $mr ) {
				$raw = (string) $mr->meta_value;
				if ( '' === trim( $raw ) ) {
					delete_metadata_by_mid( 'post', (int) $mr->meta_id );
					$gc['empty_removed']++;
					continue;
				}
				$schema_decoded = json_decode( $raw, true );
				if ( ! is_array( $schema_decoded ) ) {
					delete_metadata_by_mid( 'post', (int) $mr->meta_id );
					$gc['invalid_removed']++;
					continue;
				}
				$gc['valid_kept']++;
			}
			$gc['ran_at'] = current_time( 'mysql' );
			update_option( 'cc_assistant_schema_gc_result', $gc, false );
		}

		update_option( 'cc_assistant_db_version', self::DB_VERSION, false );
	}

	/**
	 * One-shot LG pillar reverse-state heal. Lifted verbatim from the v0.18.3
	 * admin_init hot-patch but invoked exactly once during the 0.7.0→0.8.0
	 * migration. After this runs, the trigger signature no longer matches even
	 * if the heal is invoked again, so re-running is a no-op.
	 */
	private static function lg_oneshot_heal() {
		$site_url = (string) get_site_url();
		if ( false === stripos( $site_url, 'irvingwellnessclinic' ) ) {
			return;
		}
		$post_id = 9983;
		$raw     = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return;
		}
		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) || count( $tree ) < 5 ) {
			return;
		}
		$first = $tree[0];
		if ( empty( $first['elements'][0] ) || ! is_array( $first['elements'][0] ) ) {
			return;
		}
		$first_widget = $first['elements'][0];
		$is_html = ( isset( $first_widget['widgetType'] ) && 'html' === $first_widget['widgetType'] );
		if ( ! $is_html ) {
			return;
		}
		$first_html = isset( $first_widget['settings']['html'] ) ? (string) $first_widget['settings']['html'] : '';
		if ( false === strpos( $first_html, 'MedicalProcedure' ) ) {
			return;
		}
		$reversed = array_reverse( $tree );
		$json     = wp_json_encode( $reversed );
		if ( false === $json ) {
			return;
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
		delete_post_meta( $post_id, '_elementor_css' );
		delete_post_meta( $post_id, '_elementor_inline_svg' );
		delete_post_meta( $post_id, '_elementor_page_assets' );
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $post_id, 'post_meta' );
		}
		$audit   = (array) get_option( 'cc_assistant_lg_reverse_audit', array() );
		$audit[] = array(
			'ts'        => current_time( 'mysql' ),
			'post_id'   => $post_id,
			'roots'     => count( $tree ),
			'meta_size' => strlen( $raw ),
			'via'       => '0.8.0_oneshot',
		);
		if ( count( $audit ) > 10 ) {
			$audit = array_slice( $audit, -10 );
		}
		update_option( 'cc_assistant_lg_reverse_audit', $audit, false );
	}

	public static function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$snapshots_table  = $wpdb->prefix . 'cc_snapshots';
		$pending_table    = $wpdb->prefix . 'cc_pending_changes';
		$embeddings_table = $wpdb->prefix . 'cc_embeddings';
		$gsc_table        = $wpdb->prefix . 'cc_gsc_queries';
		$edits_table      = $wpdb->prefix . 'cc_edits';
		$llm_crawls_table = $wpdb->prefix . 'cc_llm_crawls';
		$link_graph_table = $wpdb->prefix . 'cc_link_graph';
		$clusters_table   = $wpdb->prefix . 'cc_topic_clusters';
		$members_table    = $wpdb->prefix . 'cc_cluster_members';
		$trends_table     = $wpdb->prefix . 'cc_cannibalization_trends';
		$activity_table   = $wpdb->prefix . 'cc_activity_log';

		$sql_snapshots = "CREATE TABLE $snapshots_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			snapshot_type VARCHAR(50) NOT NULL,
			post_content LONGTEXT,
			post_title TEXT,
			post_meta LONGTEXT,
			elementor_data LONGTEXT,
			created_at DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED,
			note TEXT,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) $charset_collate;";

		$sql_pending = "CREATE TABLE $pending_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED,
			change_type VARCHAR(50) NOT NULL,
			change_summary TEXT,
			current_value LONGTEXT,
			proposed_value LONGTEXT,
			reasoning TEXT,
			lint_report LONGTEXT,
			success_metrics LONGTEXT,
			pre_check_baseline LONGTEXT,
			verification_result LONGTEXT,
			superseded_by BIGINT(20) UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			created_by VARCHAR(50),
			reviewed_at DATETIME,
			reviewed_by BIGINT(20) UNSIGNED,
			review_note TEXT,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY superseded_by (superseded_by)
		) $charset_collate;";

		$sql_embeddings = "CREATE TABLE $embeddings_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			content_hash VARCHAR(64) NOT NULL,
			embedding LONGTEXT,
			model VARCHAR(100),
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY post_id (post_id),
			KEY content_hash (content_hash)
		) $charset_collate;";

		$sql_gsc = "CREATE TABLE $gsc_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			date DATE NOT NULL,
			page VARCHAR(500) NOT NULL,
			query VARCHAR(500) NOT NULL,
			search_appearance VARCHAR(50) NOT NULL DEFAULT '',
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			impressions INT UNSIGNED NOT NULL DEFAULT 0,
			ctr DECIMAL(6,5) NOT NULL DEFAULT 0,
			position DECIMAL(6,2) NOT NULL DEFAULT 0,
			page_hash CHAR(40) NOT NULL,
			query_hash CHAR(40) NOT NULL,
			page_canonical_hash CHAR(40) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			UNIQUE KEY uniq_row (date, page_hash, query_hash, search_appearance),
			KEY date (date),
			KEY page_hash (page_hash),
			KEY page_canonical_hash (page_canonical_hash),
			KEY search_appearance (search_appearance),
			KEY impressions (impressions),
			KEY date_page (date, page_hash),
			KEY date_query (date, query_hash)
		) $charset_collate;";

		$sql_edits = "CREATE TABLE $edits_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			pending_change_id BIGINT(20) UNSIGNED,
			change_type VARCHAR(50) NOT NULL,
			change_summary TEXT,
			page_url VARCHAR(500) NOT NULL,
			page_hash CHAR(40) NOT NULL,
			applied_at DATETIME NOT NULL,
			applied_by BIGINT(20) UNSIGNED,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY page_hash (page_hash),
			KEY applied_at (applied_at)
		) $charset_collate;";

		$sql_llm = "CREATE TABLE $llm_crawls_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			seen_at DATETIME NOT NULL,
			bot_name VARCHAR(50) NOT NULL,
			user_agent VARCHAR(500) NOT NULL,
			path VARCHAR(500) NOT NULL,
			path_hash CHAR(40) NOT NULL,
			post_id BIGINT(20) UNSIGNED,
			status_code SMALLINT UNSIGNED,
			referer VARCHAR(500),
			ip_hash CHAR(40),
			PRIMARY KEY (id),
			KEY seen_at (seen_at),
			KEY bot_name (bot_name),
			KEY path_hash (path_hash),
			KEY post_id (post_id)
		) $charset_collate;";

		$sql_link_graph = "CREATE TABLE $link_graph_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_post_id BIGINT(20) UNSIGNED NOT NULL,
			target_post_id BIGINT(20) UNSIGNED NOT NULL,
			anchor_text TEXT,
			href VARCHAR(500) NOT NULL,
			rel_attr VARCHAR(50) DEFAULT '',
			discovered_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY edge (source_post_id, target_post_id, href(150)),
			KEY source_post_id (source_post_id),
			KEY target_post_id (target_post_id)
		) $charset_collate;";

		// Topic clusters: pillar + supporting page model. Persisted so Claude
		// can read just one cluster at a time instead of every page.
		$sql_clusters = "CREATE TABLE $clusters_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(100) NOT NULL,
			name VARCHAR(200) NOT NULL,
			description TEXT,
			pillar_post_id BIGINT(20) UNSIGNED,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			created_by VARCHAR(50),
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY pillar_post_id (pillar_post_id)
		) $charset_collate;";

		// Many-to-many: a post can belong to multiple clusters (real content overlaps).
		// PRIMARY KEY (cluster_id, post_id) prevents dupes within one cluster.
		$sql_members = "CREATE TABLE $members_table (
			cluster_id BIGINT(20) UNSIGNED NOT NULL,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			role VARCHAR(20) NOT NULL DEFAULT 'supporting',
			added_at DATETIME NOT NULL,
			added_by VARCHAR(50),
			notes TEXT,
			PRIMARY KEY (cluster_id, post_id),
			KEY post_id (post_id),
			KEY role (role)
		) $charset_collate;";

		// Cannibalization trend history. One row per weekly cron run.
		$sql_trends = "CREATE TABLE $trends_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			captured_at DATETIME NOT NULL,
			conflict_count INT UNSIGNED NOT NULL DEFAULT 0,
			leak_impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
			top_query VARCHAR(255) NULL,
			PRIMARY KEY (id),
			KEY captured_at (captured_at)
		) $charset_collate;";

		// 2-day rolling activity log. Every claude-driven mutation + every
		// reviewer decision lands here so a fresh chat session can pull the
		// short history without scanning multiple tables. Pruned daily.
		$sql_activity = "CREATE TABLE $activity_table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ts DATETIME NOT NULL,
			type VARCHAR(40) NOT NULL,
			actor VARCHAR(40) NOT NULL DEFAULT 'system',
			post_id BIGINT(20) UNSIGNED NULL,
			change_id BIGINT(20) UNSIGNED NULL,
			summary VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY ts (ts),
			KEY type (type),
			KEY post_id (post_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// v0.60.1: lead-events table registered here (was raw DDL on every
		// form submission with no schema-upgrade path).
		require_once CC_ASSISTANT_DIR . 'includes/class-lead-events.php';
		CC_Assistant_Lead_Events::ensure_table();
		dbDelta( $sql_snapshots );
		dbDelta( $sql_pending );
		dbDelta( $sql_embeddings );
		dbDelta( $sql_gsc );
		dbDelta( $sql_edits );
		dbDelta( $sql_llm );
		dbDelta( $sql_link_graph );
		dbDelta( $sql_clusters );
		dbDelta( $sql_members );
		dbDelta( $sql_trends );
		dbDelta( $sql_activity );

		if ( ! wp_next_scheduled( 'cc_assistant_gsc_sync' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cc_assistant_gsc_sync' );
		}
		if ( ! wp_next_scheduled( 'cc_assistant_link_graph_rebuild' ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'cc_assistant_link_graph_rebuild' );
		}
		if ( ! wp_next_scheduled( 'cc_assistant_activity_log_prune' ) ) {
			wp_schedule_event( time() + 3 * HOUR_IN_SECONDS, 'daily', 'cc_assistant_activity_log_prune' );
		}

		// v0.33.0 unified storage maintenance — prunes the three previously
		// unbounded tables (cc_snapshots, cc_pending_changes, cc_edits) on a
		// daily schedule. Scheduled here on activation; class-storage-maintenance
		// also has schedule_cron() so a missed activation gets self-healed
		// from the admin bootstrap.
		if ( file_exists( CC_ASSISTANT_DIR . 'includes/class-storage-maintenance.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-storage-maintenance.php';
			CC_Assistant_Storage_Maintenance::schedule_cron();
		}

		// First-run priming wave: rather than greeting the operator with empty
		// dashboards and a misleading "100% orphans" badge, schedule one-time
		// builds of the heaviest derived caches a few minutes after activation.
		// Each runs in its own cron tick so a slow one cannot block the others.
		// Idempotent — re-activating the plugin reschedules cleanly.
		//
		// Hook names below MUST match the registered listeners in cc-assistant.php.
		// `cc_assistant_warm_rendered` is the per-post variant (takes $post_id);
		// for a no-arg priming pass we use the batch hook `cc_assistant_warm_rendered_batch`.
		$priming_offset = 2 * MINUTE_IN_SECONDS;
		foreach ( array(
			'cc_assistant_link_graph_rebuild',
			'cc_assistant_advisor_recompute',
			'cc_assistant_site_audit',
			'cc_assistant_warm_rendered_batch',
		) as $hook ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_single_event( time() + $priming_offset, $hook );
				$priming_offset += MINUTE_IN_SECONDS;
			}
		}
		update_option( 'cc_assistant_priming_started_at', current_time( 'mysql' ), false );

		if ( ! get_option( 'cc_assistant_site_id' ) ) {
			update_option( 'cc_assistant_site_id', wp_generate_uuid4() );
		}

		$defaults = array(
			'cc_assistant_allowed_post_types' => array( 'page', 'post' ),
			'cc_assistant_competitor_domains' => array(),
			'cc_assistant_authority_domains'  => array(
				// Health and medicine
				'mayoclinic.org',
				'clevelandclinic.org',
				'hopkinsmedicine.org',
				'who.int',
				'cdc.gov',
				'nih.gov',
				'jamanetwork.com',
				'thelancet.com',
				'bmj.com',
				'apa.org',
				// Science and academia
				'nature.com',
				'sciencemag.org',
				'sciencedirect.com',
				'harvard.edu',
				'mit.edu',
				'stanford.edu',
				// News and journalism
				'nytimes.com',
				'wsj.com',
				'reuters.com',
				'apnews.com',
				'bbc.com',
				'npr.org',
				'theguardian.com',
				// Business and economics
				'hbr.org',
				'bloomberg.com',
				'ft.com',
				'economist.com',
				// Research and data
				'pewresearch.org',
				'brookings.edu',
				'statista.com',
			),
			'cc_assistant_onboarding_complete' => false,
			'cc_assistant_llm_tracking_enabled' => false,
			'cc_assistant_brand_terms'         => array(),
			// v0.60.1 perf audit: these six are read on the PUBLIC render path
			// but were never seeded — every anonymous page view paid a
			// notoptions DB miss per option. Seeding puts them in alloptions
			// (autoload) so the FE reads are free.
			'cc_assistant_hero_preload_enabled'    => true,
			'cc_assistant_xfo_enabled'             => true,
			'cc_assistant_schema_cleanup_enabled'  => true,
			'cc_assistant_emergency_schema_enabled' => true,
			'cc_assistant_page_schema_enabled'     => true,
			'cc_assistant_aria_fix_enabled'        => true,
			'cc_assistant_toc_enabled'             => false,
			'cc_assistant_clarity_project_id'      => '',
		);

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				update_option( $key, $value );
			}
		}

		update_option( 'cc_assistant_version', CC_ASSISTANT_VERSION );
		update_option( 'cc_assistant_db_version', self::DB_VERSION, false );
	}
}

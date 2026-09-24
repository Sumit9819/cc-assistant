<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar' ), 100 );
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'enqueue_admin_bar_styles' ) );
		// Reindex Tracker form handler. Hooked at admin_post (not inside the
		// view) so wp_safe_redirect runs BEFORE admin headers are sent.
		add_action( 'admin_post_cc_reindex_toggle', array( __CLASS__, 'handle_reindex_toggle' ) );
		add_action( 'admin_post_cc_db_maintenance', array( __CLASS__, 'handle_db_maintenance' ) );
	}

	public static function register_menu() {
		$pending_count = CC_Assistant_Pending_Changes::count_pending();
		$bubble        = $pending_count > 0
			? ' <span class="awaiting-mod">' . esc_html( $pending_count ) . '</span>'
			: '';

		add_menu_page(
			__( 'CC Assistant', 'cc-assistant' ),
			'CC Assistant' . $bubble,
			'manage_options',
			'cc-assistant',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			'cc-assistant',
			__( 'Dashboard', 'cc-assistant' ),
			__( 'Dashboard', 'cc-assistant' ),
			'manage_options',
			'cc-assistant',
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			'cc-assistant',
			__( 'Pending Changes', 'cc-assistant' ),
			__( 'Pending Changes', 'cc-assistant' ) . $bubble,
			'manage_options',
			'cc-assistant-pending',
			array( __CLASS__, 'render_pending' )
		);

		add_submenu_page(
			'cc-assistant',
			__( 'Topic Clusters', 'cc-assistant' ),
			__( 'Topic Clusters', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-clusters',
			array( __CLASS__, 'render_clusters' )
		);

		add_submenu_page(
			null, // v0.63 IA: merged page, hidden from menu (tab-linked from its hub)
			__( 'Check up', 'cc-assistant' ),
			__( 'Check up', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-checkup',
			array( __CLASS__, 'render_checkup' )
		);

		add_submenu_page(
			'cc-assistant',
			__( 'Snapshots', 'cc-assistant' ),
			__( 'Snapshots', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-snapshots',
			array( __CLASS__, 'render_snapshots' )
		);

		add_submenu_page(
			null, // v0.63 IA: merged into Changes (linked from pending header)
			__( 'Reindex Tracker', 'cc-assistant' ),
			__( 'Reindex Tracker', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-reindex',
			array( __CLASS__, 'render_reindex_tracker' )
		);

		add_submenu_page(
			null, // v0.63 IA: merged into Planning (tab on Topic Clusters)
			__( 'Editorial calendar', 'cc-assistant' ),
			__( 'Calendar', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-calendar',
			array( __CLASS__, 'render_calendar' )
		);

		add_submenu_page(
			null, // v0.63 IA: merged into Planning (tab on Topic Clusters)
			__( 'Brief generator', 'cc-assistant' ),
			__( 'Brief generator', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-brief',
			array( __CLASS__, 'render_brief' )
		);

		add_submenu_page(
			null, // v0.63 IA: merged into Performance (tab on Page Performance)
			__( 'AI bot activity', 'cc-assistant' ),
			__( 'AI bot activity', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-llm',
			array( __CLASS__, 'render_llm_activity' )
		);

		add_submenu_page(
			null, // v0.63 IA: merged into Settings > Health (linked there)
			__( 'Database health', 'cc-assistant' ),
			__( 'Database health', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-db-health',
			array( __CLASS__, 'render_db_health' )
		);

		// v0.61 UX audit: Settings registers on a LATE admin_menu hook
		// (priority 30) so it renders LAST in the sidebar — Page Performance
		// (priority 15) and Reviews (priority 20), registered in their own
		// classes, used to land below it.
		add_action(
			'admin_menu',
			function () {
				add_submenu_page(
					'cc-assistant',
					__( 'Settings', 'cc-assistant' ),
					__( 'Settings', 'cc-assistant' ),
					'manage_options',
					'cc-assistant-settings',
					array( __CLASS__, 'render_settings' )
				);
			},
			30
		);

		// Onboarding lives at its own URL but is hidden from the menu after completion.
		$onboarded = (bool) get_option( 'cc_assistant_onboarding_complete' );
		add_submenu_page(
			$onboarded ? null : 'cc-assistant',
			__( 'Setup', 'cc-assistant' ),
			__( 'Setup', 'cc-assistant' ),
			'manage_options',
			'cc-assistant-onboarding',
			array( __CLASS__, 'render_onboarding' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'cc-assistant' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'cc-assistant-admin',
			CC_ASSISTANT_URL . 'admin/css/admin.css',
			array(),
			CC_ASSISTANT_VERSION
		);
	}

	public static function register_admin_bar( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$heartbeat    = CC_Assistant_Site_Identity::get_last_heartbeat();
		$is_connected = $heartbeat && ( time() - $heartbeat['timestamp'] < 300 );
		$pending      = CC_Assistant_Pending_Changes::count_pending();

		$status_label = $is_connected ? __( 'Connected', 'cc-assistant' ) : __( 'Idle', 'cc-assistant' );
		$class        = $is_connected ? 'cc-ab-connected' : 'cc-ab-idle';
		$bubble       = $pending > 0 ? ' <span class="cc-ab-bubble">' . (int) $pending . '</span>' : '';

		$wp_admin_bar->add_node(
			array(
				'id'    => 'cc-assistant',
				'title' => '<span class="ab-icon dashicons dashicons-format-chat"></span><span class="ab-label">CC: ' . esc_html( $status_label ) . '</span>' . $bubble,
				'href'  => admin_url( 'admin.php?page=cc-assistant' ),
				'meta'  => array( 'class' => $class ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'cc-assistant',
				'id'     => 'cc-assistant-dashboard',
				'title'  => __( 'Dashboard', 'cc-assistant' ),
				'href'   => admin_url( 'admin.php?page=cc-assistant' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'cc-assistant',
				'id'     => 'cc-assistant-pending-bar',
				'title'  => $pending > 0
					? sprintf( /* translators: %d: pending count */ __( 'Pending changes (%d)', 'cc-assistant' ), $pending )
					: __( 'Pending changes', 'cc-assistant' ),
				'href'   => admin_url( 'admin.php?page=cc-assistant-pending' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'cc-assistant',
				'id'     => 'cc-assistant-settings-bar',
				'title'  => __( 'Settings', 'cc-assistant' ),
				'href'   => admin_url( 'admin.php?page=cc-assistant-settings' ),
			)
		);
	}

	public static function enqueue_admin_bar_styles() {
		// Inline minimal CSS for the admin bar item so we do not load a separate file on the front-end.
		?>
		<style>
		#wp-admin-bar-cc-assistant.cc-ab-connected > .ab-item { color: #6ee7b7 !important; }
		#wp-admin-bar-cc-assistant.cc-ab-idle > .ab-item { color: #cbd5e1 !important; }
		#wp-admin-bar-cc-assistant .ab-label { margin-left: 4px; font-weight: 600; }
		#wp-admin-bar-cc-assistant .cc-ab-bubble {
			display: inline-block; margin-left: 6px; padding: 0 6px;
			background: #d63638; color: #fff; border-radius: 9px;
			font-size: 11px; line-height: 16px; height: 16px;
		}
		</style>
		<?php
	}

	public static function render_dashboard() {
		require CC_ASSISTANT_DIR . 'admin/views/dashboard.php';
	}

	public static function render_pending() {
		require CC_ASSISTANT_DIR . 'admin/views/pending.php';
	}

	public static function render_settings() {
		require CC_ASSISTANT_DIR . 'admin/views/settings.php';
	}

	public static function render_onboarding() {
		require CC_ASSISTANT_DIR . 'admin/views/onboarding.php';
	}

	public static function render_checkup() {
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		require CC_ASSISTANT_DIR . 'admin/views/checkup.php';
	}

	public static function render_clusters() {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		CC_Assistant_Topic_Clusters::ensure_tables();
		require CC_ASSISTANT_DIR . 'admin/views/clusters.php';
	}

	public static function render_snapshots() {
		require_once CC_ASSISTANT_DIR . 'includes/class-snapshots.php';
		require CC_ASSISTANT_DIR . 'admin/views/snapshots.php';
	}

	public static function render_reindex_tracker() {
		require CC_ASSISTANT_DIR . 'admin/views/reindex-tracker.php';
	}

	/**
	 * Admin-post handler for the Reindex Tracker mark/unmark toggle.
	 *
	 * Hooks into admin_post_cc_reindex_toggle so the redirect runs BEFORE the
	 * admin page chrome sends headers. Doing this inside the view (after
	 * render_reindex_tracker fires) caused wp_safe_redirect to fail silently
	 * with a white screen, because admin headers are already on the wire by
	 * the time the view executes.
	 */
	public static function handle_reindex_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.', 403 );
		}
		check_admin_referer( 'cc_reindex_toggle' );

		$action  = isset( $_POST['cc_reindex_action'] ) ? sanitize_key( wp_unslash( $_POST['cc_reindex_action'] ) ) : '';
		$post_id = isset( $_POST['cc_reindex_post_id'] ) ? (int) $_POST['cc_reindex_post_id'] : 0;

		if ( $post_id > 0 ) {
			if ( 'mark' === $action ) {
				// GMT, not site-local. This value is compared against
				// cc_edits.applied_at, which is stored in GMT. Writing local
				// time here meant that on any site west of UTC the submit stamp
				// landed hours "before" the edit it was acknowledging, so
				// is_submitted never became true and the queue never drained.
				update_post_meta( $post_id, '_cc_gsc_reindexed_at', current_time( 'mysql', true ) );
			} elseif ( 'unmark' === $action ) {
				delete_post_meta( $post_id, '_cc_gsc_reindexed_at' );
			}
		}

		// Preserve filter state on the way back.
		$args = array(
			'page'    => 'cc-assistant-reindex',
			'updated' => '1',
		);
		foreach ( array( 'status', 'window' ) as $key ) {
			if ( isset( $_POST[ 'cc_reindex_' . $key ] ) ) {
				$args[ $key ] = sanitize_key( wp_unslash( $_POST[ 'cc_reindex_' . $key ] ) );
			}
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_calendar() {
		require CC_ASSISTANT_DIR . 'admin/views/calendar.php';
	}

	public static function render_brief() {
		require CC_ASSISTANT_DIR . 'admin/views/brief.php';
	}

	public static function render_llm_activity() {
		require CC_ASSISTANT_DIR . 'admin/views/llm-activity.php';
	}

	public static function render_db_health() {
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		require CC_ASSISTANT_DIR . 'admin/views/db-health.php';
	}

	/**
	 * Admin-post handler for the Database health "Run maintenance now" button.
	 * Triggers GSC maintain() outside the daily sync cadence so admins can
	 * reclaim space immediately after install/update without waiting for cron.
	 */
	public static function handle_db_maintenance() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.', 403 );
		}
		check_admin_referer( 'cc_db_maintenance' );

		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		$result = CC_Assistant_GSC::maintain();

		set_transient(
			'cc_assistant_db_health_flash',
			array(
				'type'   => 'success',
				'result' => $result,
			),
			60
		);

		wp_safe_redirect( admin_url( 'admin.php?page=cc-assistant-db-health' ) );
		exit;
	}
}

<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Topic-cluster REST routes. Lives in its own class so the file can be added
 * without touching class-rest-api.php (and without merge churn against
 * other in-flight features).
 *
 * Permission and site identity follow the same pattern as the main REST API:
 * manage_options + heartbeat record.
 */
class CC_Assistant_REST_Clusters {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/topic-clusters',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_list' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/topic-clusters/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/topic-clusters/propose',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_propose_create' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/topic-clusters/(?P<id>\d+)/members/propose',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_propose_assign' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/topic-clusters/(?P<id>\d+)/gsc',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_cluster_gsc' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'days' => array( 'default' => 28 ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/topic-clusters/bulk-suggest',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_bulk_suggest' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	/**
	 * Run the bulk cluster auto-suggester. Defaults to dry_run=true so a first
	 * call never queues anything until the operator reviews the matches and
	 * re-runs with dry_run=false (or override per-call).
	 */
	public static function handle_bulk_suggest( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		$args = array();
		if ( null !== $req->get_param( 'min_score' ) ) {
			$args['min_score'] = (float) $req->get_param( 'min_score' );
		}
		if ( null !== $req->get_param( 'limit' ) ) {
			$args['limit'] = (int) $req->get_param( 'limit' );
		}
		if ( null !== $req->get_param( 'max_queue' ) ) {
			$args['max_queue'] = (int) $req->get_param( 'max_queue' );
		}
		if ( null !== $req->get_param( 'dry_run' ) ) {
			$args['dry_run'] = filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN );
		}
		if ( null !== $req->get_param( 'post_types' ) ) {
			$pt = $req->get_param( 'post_types' );
			if ( is_array( $pt ) ) {
				$args['post_types'] = array_values( array_map( 'sanitize_key', $pt ) );
			}
		}
		$result = CC_Assistant_Topic_Clusters::suggest_assignments( $args );
		return self::wrap( $result );
	}

	public static function check_permission() {
		if ( ! CC_Assistant_Access::can_use() ) {
			return new WP_Error(
				'rest_forbidden',
				'You do not have permission to use CC Assistant.',
				array( 'status' => 403 )
			);
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	private static function ensure() {
		require_once CC_ASSISTANT_DIR . 'includes/class-topic-clusters.php';
		CC_Assistant_Topic_Clusters::ensure_tables();
	}

	private static function wrap( $data ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		return array(
			'site' => array(
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url(),
				'fingerprint' => CC_Assistant_Site_Identity::fingerprint(),
			),
			'data' => $data,
		);
	}

	public static function handle_list() {
		self::ensure();
		$rows = CC_Assistant_Topic_Clusters::list_clusters();
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'id'             => (int) $r->id,
				'slug'           => $r->slug,
				'name'           => $r->name,
				'description'    => $r->description,
				'pillar_post_id' => $r->pillar_post_id ? (int) $r->pillar_post_id : null,
				'member_count'   => (int) $r->member_count,
				'created_at'     => $r->created_at,
				'updated_at'     => $r->updated_at,
			);
		}
		return self::wrap( array( 'clusters' => $out, 'count' => count( $out ) ) );
	}

	public static function handle_get( WP_REST_Request $req ) {
		self::ensure();
		$cluster_id = (int) $req->get_param( 'id' );
		$cluster    = CC_Assistant_Topic_Clusters::get_cluster( $cluster_id );
		if ( ! $cluster ) {
			return new WP_Error( 'not_found', 'Cluster not found.', array( 'status' => 404 ) );
		}
		$members = CC_Assistant_Topic_Clusters::get_members( $cluster_id );
		$pages   = array();
		foreach ( $members as $m ) {
			$pages[] = array(
				'post_id'  => (int) $m->post_id,
				'title'    => get_the_title( (int) $m->post_id ),
				'role'     => $m->role,
				'edit_url' => get_edit_post_link( (int) $m->post_id, 'raw' ),
				'url'      => get_permalink( (int) $m->post_id ),
				'added_at' => $m->added_at,
			);
		}
		return self::wrap( array(
			'cluster' => array(
				'id'             => (int) $cluster->id,
				'slug'           => $cluster->slug,
				'name'           => $cluster->name,
				'description'    => $cluster->description,
				'pillar_post_id' => $cluster->pillar_post_id ? (int) $cluster->pillar_post_id : null,
				'created_at'     => $cluster->created_at,
				'updated_at'     => $cluster->updated_at,
			),
			'members' => $pages,
		) );
	}

	public static function handle_propose_create( WP_REST_Request $req ) {
		self::ensure();
		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? CC_Assistant_REST_API::sanitize_success_metrics_public( $success_raw )
			: null;
		$dry_run = filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN );
		if ( $dry_run ) {
			return self::wrap( array(
				'dry_run'     => true,
				'pending_id'  => null,
				'change_type' => 'cluster_create',
				'proposed'    => array(
					'name'                => (string) $req->get_param( 'name' ),
					'pillar_post_id'      => $req->get_param( 'pillar_post_id' ),
					'supporting_post_ids' => $req->get_param( 'supporting_post_ids' ),
				),
				'review_url'  => admin_url( 'admin.php?page=cc-assistant-pending' ),
			) );
		}
		$pending_id = CC_Assistant_Topic_Clusters::propose_cluster_create( array(
			'name'                => $req->get_param( 'name' ),
			'description'         => $req->get_param( 'description' ),
			'pillar_post_id'      => $req->get_param( 'pillar_post_id' ),
			'supporting_post_ids' => $req->get_param( 'supporting_post_ids' ),
			'summary'             => $req->get_param( 'summary' ),
			'reasoning'           => $req->get_param( 'reasoning' ),
			'success_metrics'     => $success,
		) );
		if ( is_wp_error( $pending_id ) ) {
			$pending_id->add_data( array( 'status' => 400 ) );
			return $pending_id;
		}
		return self::wrap( array(
			'pending_id' => (int) $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
		) );
	}

	public static function handle_cluster_gsc( WP_REST_Request $req ) {
		self::ensure();
		$cluster_id = (int) $req->get_param( 'id' );
		$days       = (int) $req->get_param( 'days' );
		$summary    = CC_Assistant_Topic_Clusters::cluster_gsc_summary( $cluster_id, $days );
		if ( null === $summary ) {
			return new WP_Error( 'not_found', 'Cluster not found.', array( 'status' => 404 ) );
		}
		return self::wrap( $summary );
	}

	public static function handle_propose_assign( WP_REST_Request $req ) {
		self::ensure();
		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? CC_Assistant_REST_API::sanitize_success_metrics_public( $success_raw )
			: null;
		$dry_run = filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN );
		if ( $dry_run ) {
			return self::wrap( array(
				'dry_run'     => true,
				'pending_id'  => null,
				'change_type' => 'cluster_assign',
				'proposed'    => array(
					'cluster_id' => (int) $req->get_param( 'id' ),
					'post_id'    => (int) $req->get_param( 'post_id' ),
					'role'       => (string) $req->get_param( 'role' ),
				),
				'review_url'  => admin_url( 'admin.php?page=cc-assistant-pending' ),
			) );
		}
		$pending_id = CC_Assistant_Topic_Clusters::propose_cluster_assignment( array(
			'cluster_id'      => (int) $req->get_param( 'id' ),
			'post_id'         => $req->get_param( 'post_id' ),
			'role'            => $req->get_param( 'role' ),
			'summary'         => $req->get_param( 'summary' ),
			'reasoning'       => $req->get_param( 'reasoning' ),
			'success_metrics' => $success,
		) );
		if ( is_wp_error( $pending_id ) ) {
			$pending_id->add_data( array( 'status' => 400 ) );
			return $pending_id;
		}
		return self::wrap( array(
			'pending_id' => (int) $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
		) );
	}
}

<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO analysis REST routes (cannibalization, image audit, refresh queue,
 * click depth, post dossier, structure, competitor brief).
 *
 * Lives in its own class so the file can be added without touching
 * class-rest-api.php (where parallel work happens).
 */
class CC_Assistant_REST_SEO {

	const REST_NAMESPACE = 'cc-assistant/v1';

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/cannibalization',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_cannibalization' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'days'            => array( 'default' => 28 ),
					'min_impressions' => array( 'default' => 25 ),
					'max_position'    => array( 'default' => 30 ),
					'limit'           => array( 'default' => 50 ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/image-audit/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_image_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/refresh-queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_refresh_queue' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'days'           => array( 'default' => 28 ),
					'min_click_drop' => array( 'default' => 5 ),
					'min_age_days'   => array( 'default' => 90 ),
					'limit'          => array( 'default' => 25 ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/click-depth',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_click_depth' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'max_depth_warn' => array( 'default' => 4 ),
					'buried_only'    => array( 'default' => false ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/dossier/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_dossier' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/structure/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_structure' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/competitor-brief',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_competitor_brief' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'keyword' => array( 'required' => true ),
					'days'    => array( 'default' => 28 ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/accessibility/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_accessibility' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/canonical-audit/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_canonical_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/hreflang-audit/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_hreflang_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/eeat-coverage/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_eeat_coverage_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/schema-parity/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_schema_parity_check' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/self-review/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_self_review_detection' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/helpful-content/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_helpful_content_score' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'refresh' => array( 'default' => false ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/site-quality',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_site_quality_score' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'refresh_stale'  => array( 'default' => false ),
					'freshness_days' => array( 'default' => 14 ),
					'bottom_n'       => array( 'default' => 10 ),
					'rescore_cap'    => array( 'default' => 10 ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/originality/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_external_originality_check' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'keyword' => array( 'default' => '' ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/keyword-research',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_keyword_research' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'seed' => array( 'required' => true ),
					'days' => array( 'default' => 28 ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/schema/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_schema_get' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/schema/(?P<id>\d+)/propose',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_schema_propose' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/seo/rewrite-brief/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_rewrite_brief' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'keyword' => array( 'default' => '' ),
				),
			)
		);
	}

	public static function check_permission() {
		if ( ! CC_Assistant_Access::can_use() ) {
			return new WP_Error( 'rest_forbidden', 'You do not have permission to use CC Assistant.', array( 'status' => 403 ) );
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	private static function ensure() {
		require_once CC_ASSISTANT_DIR . 'includes/class-seo-tools.php';
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

	public static function handle_cannibalization( WP_REST_Request $req ) {
		self::ensure();
		return self::wrap( CC_Assistant_SEO_Tools::cannibalization( array(
			'days'            => (int) $req->get_param( 'days' ),
			'min_impressions' => (int) $req->get_param( 'min_impressions' ),
			'max_position'    => (int) $req->get_param( 'max_position' ),
			'limit'           => (int) $req->get_param( 'limit' ),
		) ) );
	}

	public static function handle_image_audit( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::audit_post_images( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_refresh_queue( WP_REST_Request $req ) {
		self::ensure();
		return self::wrap( CC_Assistant_SEO_Tools::refresh_queue( array(
			'days'           => (int) $req->get_param( 'days' ),
			'min_click_drop' => (int) $req->get_param( 'min_click_drop' ),
			'min_age_days'   => (int) $req->get_param( 'min_age_days' ),
			'limit'          => (int) $req->get_param( 'limit' ),
		) ) );
	}

	public static function handle_click_depth( WP_REST_Request $req ) {
		self::ensure();
		return self::wrap( CC_Assistant_SEO_Tools::click_depth( array(
			'max_depth_warn' => (int) $req->get_param( 'max_depth_warn' ),
			'buried_only'    => filter_var( $req->get_param( 'buried_only' ), FILTER_VALIDATE_BOOLEAN ),
		) ) );
	}

	public static function handle_dossier( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::post_dossier( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_structure( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::analyze_post_structure( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_accessibility( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::accessibility_audit( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_canonical_audit( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::canonical_audit( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_hreflang_audit( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::hreflang_audit( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_eeat_coverage_audit( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::eeat_coverage_audit( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_schema_parity_check( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::schema_parity_check( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_self_review_detection( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::self_review_detection( (int) $req->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_helpful_content_score( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::helpful_content_score(
			(int) $req->get_param( 'id' ),
			array( 'refresh' => filter_var( $req->get_param( 'refresh' ), FILTER_VALIDATE_BOOLEAN ) )
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_site_quality_score( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::site_quality_score( array(
			'refresh_stale'  => filter_var( $req->get_param( 'refresh_stale' ), FILTER_VALIDATE_BOOLEAN ),
			'freshness_days' => (int) $req->get_param( 'freshness_days' ),
			'bottom_n'       => (int) $req->get_param( 'bottom_n' ),
			'rescore_cap'    => (int) $req->get_param( 'rescore_cap' ),
		) );
		return self::wrap( $result );
	}

	public static function handle_external_originality_check( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::external_originality_check(
			(int) $req->get_param( 'id' ),
			array( 'keyword' => (string) $req->get_param( 'keyword' ) )
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_keyword_research( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::keyword_research( array(
			'seed' => (string) $req->get_param( 'seed' ),
			'days' => (int) $req->get_param( 'days' ),
		) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_schema_get( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-schema-generator.php';
		$result = CC_Assistant_Schema_Generator::generate(
			(int) $req->get_param( 'id' ),
			array( 'include_breadcrumb' => filter_var( $req->get_param( 'include_breadcrumb' ), FILTER_VALIDATE_BOOLEAN ) )
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_schema_propose( WP_REST_Request $req ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-schema-generator.php';
		$success_raw = $req->get_param( 'success_metrics' );
		$success     = is_array( $success_raw ) && ! empty( $success_raw )
			? CC_Assistant_REST_API::sanitize_success_metrics_public( $success_raw )
			: null;

		$post_id  = (int) $req->get_param( 'id' );
		$gen_args = array(
			'include_breadcrumb' => filter_var( $req->get_param( 'include_breadcrumb' ), FILTER_VALIDATE_BOOLEAN ),
		);
		$dry_run  = filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN );
		if ( $dry_run ) {
			// Dry-run runs the generator + lint but does NOT queue a pending row.
			$preview = CC_Assistant_Schema_Generator::generate( $post_id, $gen_args );
			if ( is_wp_error( $preview ) ) {
				$preview->add_data( array( 'status' => 400 ) );
				return $preview;
			}
			$lint = CC_Assistant_Schema_Generator::lint_schema_proposal( $post_id, $preview );
			return self::wrap( array(
				'dry_run'     => true,
				'pending_id'  => null,
				'change_type' => 'postmeta_update',
				'types'       => $preview['types'],
				'jsonld'      => $preview['jsonld'],
				'lint'        => $lint,
				'review_url'  => admin_url( 'admin.php?page=cc-assistant-pending' ),
			) );
		}

		$pending_id = CC_Assistant_Schema_Generator::propose_for_post(
			$post_id,
			(string) $req->get_param( 'reasoning' ),
			$success,
			$gen_args
		);
		if ( is_wp_error( $pending_id ) ) {
			$pending_id->add_data( array( 'status' => 400 ) );
			return $pending_id;
		}
		$out = array(
			'pending_id' => (int) $pending_id,
			'review_url' => admin_url( 'admin.php?page=cc-assistant-pending' ),
		);
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		$claim_warnings = CC_Assistant_Pending_Changes::claim_removal_warnings( (int) $pending_id );
		if ( ! empty( $claim_warnings ) ) {
			$out['claim_removal_warnings'] = $claim_warnings;
		}
		// Superseding is silent by design and the hidden row keeps status='pending',
		// so a buried change looks identical to one that was never queued. Say it here.
		$superseded = CC_Assistant_Pending_Changes::superseded_notices( (int) $pending_id );
		if ( ! empty( $superseded ) ) {
			$out['superseded'] = $superseded;
		}
		return self::wrap( $out );
	}

	public static function handle_competitor_brief( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::competitor_brief( array(
			'keyword' => $req->get_param( 'keyword' ),
			'days'    => (int) $req->get_param( 'days' ),
		) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return self::wrap( $result );
	}

	public static function handle_rewrite_brief( WP_REST_Request $req ) {
		self::ensure();
		$result = CC_Assistant_SEO_Tools::prepare_rewrite_brief(
			(int) $req->get_param( 'id' ),
			array( 'keyword' => (string) $req->get_param( 'keyword' ) )
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 404 ) );
			return $result;
		}
		return self::wrap( $result );
	}
}

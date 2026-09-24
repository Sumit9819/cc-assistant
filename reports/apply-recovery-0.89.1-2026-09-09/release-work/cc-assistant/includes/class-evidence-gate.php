<?php
/** Server-owned observations, scoped to the authenticated WordPress/API session. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-integrity.php';

class CC_Assistant_Evidence_Gate {
	const TTL = 600;
	private static $before = array();
	private static $created = array();

	public static function init() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_read' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_read' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'track_created' ), 10, 3 );
	}
	public static function active() { return defined( 'REST_REQUEST' ) && REST_REQUEST; }
	private static function actor() {
		$id = get_current_user_id();
		if ( ! $id ) { return ''; }
		$app = function_exists( 'rest_get_authenticated_app_password' ) ? rest_get_authenticated_app_password() : '';
		$session = $app ?: ( function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '' );
		return $id . ':' . (string) $session;
	}
	private static function key( $kind, $target ) { return 'cc_assistant_evidence_' . hash( 'sha256', self::actor() . '|' . $kind . '|' . $target ); }
	private static function store( $kind, $target, $value ) {
		if ( self::actor() ) { set_transient( self::key( $kind, $target ), $value, self::TTL ); }
	}
	private static function forget( $kind, $target ) { if ( self::actor() ) { delete_transient( self::key( $kind, $target ) ); } }
	private static function receipt( $kind, $target ) {
		$r = get_transient( self::key( $kind, $target ) );
		$age = is_array( $r ) ? time() - (int) ( $r['observed_at'] ?? 0 ) : self::TTL + 1;
		return self::actor() && $age >= 0 && $age <= self::TTL ? $r : null;
	}
	public static function environment_hash() {
		if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$plugins = get_plugins(); $versions = array();
		$active = array_unique( array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) );
		foreach ( $active as $file ) { $versions[$file] = $plugins[$file]['Version'] ?? 'missing'; }
		ksort( $versions );
		$themes = array();
		if ( function_exists( 'wp_get_theme' ) ) { foreach ( array_unique( array( get_option( 'stylesheet' ), get_option( 'template' ) ) ) as $theme ) { $themes[$theme] = wp_get_theme( $theme )->get( 'Version' ); } }
		$mu = function_exists( 'get_mu_plugins' ) ? get_mu_plugins() : array();
		foreach ( $mu as $file => $info ) { $mu[$file] = $info['Version'] ?? ''; }
		ksort( $mu );
		$kit = (int) get_option( 'elementor_active_kit', 0 );
		$context = array( 'wp_version' => $GLOBALS['wp_version'] ?? '', 'mu_plugins' => $mu, 'theme_versions' => $themes, 'plugins' => $versions, 'stylesheet' => get_option( 'stylesheet' ), 'template' => get_option( 'template' ), 'blog_public' => get_option( 'blog_public' ), 'kit' => $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : null );
		foreach ( array( 'home', 'siteurl', 'permalink_structure', 'show_on_front', 'page_on_front', 'polylang', 'rank_math_modules', 'wpseo', 'rank-math-options-titles', 'rank-math-options-general', 'wpseo_titles', 'elementor_experiment-container' ) as $option ) { $context[$option] = get_option( $option, null ); }
		return hash( 'sha256', wp_json_encode( $context ) );
	}
	private static function handler_name( $handler ) { return is_array( $handler['callback'] ?? null ) ? (string) ( $handler['callback'][1] ?? '' ) : ''; }
	public static function before_read( $response, $handler, $request ) {
		if ( ! self::active() || 0 !== strpos( $request->get_route(), '/cc-assistant/v1/' ) ) { return $response; }
		$name = self::handler_name( $handler );
		$id = (int) $request->get_param( 'id' );
		if ( in_array( $name, array( 'handle_verified_page_audit', 'handle_get_post' ), true ) && $id > 0 ) {
			self::$before[spl_object_hash( $request )] = array( CC_Assistant_Integrity::post_hash( $id ), self::environment_hash() );
		}
		return $response;
	}
	public static function after_read( $response, $handler, $request ) {
		if ( ! self::active() || ! self::actor() || 0 !== strpos( $request->get_route(), '/cc-assistant/v1/' ) ) { return $response; }
		$name = self::handler_name( $handler );
		$data = is_object( $response ) && method_exists( $response, 'get_data' ) ? $response->get_data() : $response;
		$good = ! is_wp_error( $response ) && is_array( $data ) && ! isset( $data['code'], $data['message'] ) && ! isset( $data['error'] );
		if ( is_object( $response ) && method_exists( $response, 'get_status' ) && $response->get_status() >= 400 ) { $good = false; }
		$data = is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;
		if ( 'handle_whoami' === $name ) {
			self::forget( 'identity', 0 );
			if ( $good && ! empty( $data['plugin_version'] ) ) { self::store( 'identity', 0, array( 'observed_at' => time() ) ); }
		}
		$id = (int) $request->get_param( 'id' );
		if ( in_array( $name, array( 'handle_verified_page_audit', 'handle_get_post', 'handle_page_facts', 'handle_render_probe' ), true ) && $id > 0 ) {
			$before = self::$before[spl_object_hash( $request )] ?? null;
			unset( self::$before[spl_object_hash( $request )] );
			$valid_audit = $good && 'handle_verified_page_audit' === $name && ! empty( $data['usable'] ) && (int) ( $data['source']['post_id'] ?? 0 ) === $id;
			$post = get_post( $id );
			$editor_scope = $post && ( 'publish' !== $post->post_status || 'elementor_library' === $post->post_type );
			$valid_editor = $good && 'handle_get_post' === $name && $editor_scope && (int) ( $data['id'] ?? 0 ) === $id && isset( $data['status'] ) && ! filter_var( $request->get_param( 'slim' ), FILTER_VALIDATE_BOOLEAN ) && ! $request->get_param( 'widget_id' ) && empty( $data['auto_slimmed'] );
			$hash = CC_Assistant_Integrity::post_hash( $id ); $environment = self::environment_hash();
			if ( ( $valid_audit || $valid_editor ) && $before && '' !== $hash && $before === array( $hash, $environment ) ) {
				self::store( 'post', $id, array( 'observed_at' => time(), 'post_hash' => $hash, 'environment_hash' => $environment, 'kind' => $valid_audit ? 'server_html' : 'editor', 'body_sha1' => $valid_audit ? ( $data['source']['body_sha1'] ?? null ) : null ) );
			} elseif ( 'handle_verified_page_audit' === $name || ! $good || ! empty( $data['stale'] ) || ! empty( $data['refresh_error'] ) ) {
				self::forget( 'post', $id );
			}
		}
		if ( 'handle_stack_settings' === $name ) {
			$slug = (string) $request->get_param( 'slug' );
			$previous = self::receipt( 'option_list', $slug );
			foreach ( $previous['names'] ?? array() as $option ) { self::forget( 'option', $option ); }
			$names = array(); $environment = self::environment_hash();
			if ( $good && ! empty( $data['installed'] ) && ! empty( $data['active'] ) ) {
				foreach ( $data['options'] ?? array() as $row ) {
					$option = $row['option'] ?? ''; if ( ! is_string( $option ) || '' === $option ) { continue; }
					if ( ! CC_Assistant_Stack_Introspect::observed_value_matches( $option, $row['value'] ?? null ) ) { continue; }
					$names[] = $option;
					self::store( 'option', $option, array( 'observed_at' => time(), 'value_hash' => hash( 'sha256', serialize( get_option( $option, null ) ) ), 'environment_hash' => $environment ) );
				}
			}
			self::store( 'option_list', $slug, array( 'observed_at' => time(), 'names' => $names ) );
		}
		if ( 'handle_get_kit_settings' === $name ) {
			$kit = (int) get_option( 'elementor_active_kit', 0 ); self::forget( 'post', $kit );
			if ( $good && $kit > 0 && (int) ( $data['kit_id'] ?? 0 ) === $kit ) {
				self::store( 'post', $kit, array( 'observed_at' => time(), 'post_hash' => CC_Assistant_Integrity::post_hash( $kit ), 'environment_hash' => self::environment_hash(), 'kind' => 'kit_editor' ) );
			}
		}
		return $response;
	}
	public static function track_created( $id, $post, $update ) { if ( self::active() && ! $update && 'draft' === $post->post_status ) { self::$created[(int) $id] = true; } }
	public static function identity_check() {
		if ( ! self::active() ) { return true; }
		return self::receipt( 'identity', 0 ) ? true : new WP_Error( 'evidence_identity_required', 'Run whoami using this WordPress user and connection within ten minutes before proposing changes.', array( 'status' => 409 ) );
	}
	/** Returns only server-owned evidence; clients cannot supply these receipts. */
	public static function validate_queue( $args ) {
		if ( ! self::active() ) { return array(); } // Internal cron/CLI callers have no REST client session.
		$identity = self::identity_check(); if ( is_wp_error( $identity ) ) { return $identity; }
		$p = is_array( $args['proposed_value'] ?? null ) ? $args['proposed_value'] : json_decode( (string) ( $args['proposed_value'] ?? '' ), true );
		$p = is_array( $p ) ? $p : array(); $type = $args['change_type'] ?? '';
		$ids = empty( $args['post_id'] ) ? array() : array( (int) $args['post_id'] );
		if ( 'kit_setting_update' === $type ) { $ids[] = (int) ( $p['kit_id'] ?? get_option( 'elementor_active_kit', 0 ) ); }
		foreach ( (array) ( $p['targets'] ?? array() ) as $target ) { if ( is_array( $target ) && ! empty( $target['post_id'] ) ) { $ids[] = (int) $target['post_id']; } }
		if ( 'bulk_term_assign' === $type ) { foreach ( $p['targets'] ?? array() as $target ) { $ids[] = (int) ( $target['id'] ?? 0 ); } }
		$evidence = array( 'version' => 1, 'actor_hash' => hash( 'sha256', self::actor() ), 'posts' => array(), 'environment_hash' => self::environment_hash() );
		foreach ( array_unique( $ids ) as $id ) {
			$r = self::receipt( 'post', $id );
			if ( isset( self::$created[$id] ) && get_post_status( $id ) === 'draft' ) { $r = array( 'observed_at' => time(), 'post_hash' => CC_Assistant_Integrity::post_hash( $id ), 'environment_hash' => $evidence['environment_hash'], 'kind' => 'created_in_this_request' ); }
			if ( ! $r || empty( $r['post_hash'] ) || $r['post_hash'] !== CC_Assistant_Integrity::post_hash( $id ) || $r['environment_hash'] !== $evidence['environment_hash'] ) {
				return new WP_Error( 'page_evidence_required', 'Fresh evidence for post ' . $id . ' is missing or its state changed. Run verified_page_audit for a published page; get_post for a draft/template; get_kit_settings for the active kit. Then rebuild the proposal.', array( 'status' => 409, 'post_id' => $id ) );
			}
			$evidence['posts'][$id] = $r;
		}
		if ( 'plugin_setting_update' === $type ) {
			$option = $p['option_name'] ?? ''; $r = self::receipt( 'option', $option );
			if ( ! $r || $r['value_hash'] !== hash( 'sha256', serialize( get_option( $option, null ) ) ) || $r['environment_hash'] !== $evidence['environment_hash'] ) {
				return new WP_Error( 'setting_evidence_required', 'Read get_plugin_settings for this installed active plugin before drafting. This option must have been observed within ten minutes and must be unchanged.', array( 'status' => 409 ) );
			}
			$evidence['option'] = $r;
		}
		return $evidence;
	}
	public static function validate_apply( $pending, array $batch_posts = array() ) {
		$evidence = CC_Assistant_Integrity::baseline( $pending )['evidence'] ?? null;
		if ( is_array( $evidence ) && ( $evidence['environment_hash'] ?? '' ) !== self::environment_hash() ) {
			return new WP_Error( 'environment_changed', 'The plugin/theme/kit or SEO configuration changed after this plan was drafted. Inspect the current site and queue a new plan.', array( 'status' => 409 ) );
		}
		foreach ( (array) ( $evidence['posts'] ?? array() ) as $id => $receipt ) {
			$expected = $batch_posts[$id] ?? ( $receipt['post_hash'] ?? '' );
			if ( $expected !== CC_Assistant_Integrity::post_hash( (int) $id ) ) {
				return new WP_Error( 'evidence_state_changed', 'Post ' . $id . ' changed after this proposal was observed. Read the current state and rebuild the proposal.', array( 'status' => 409 ) );
			}
		}
		return true;
	}
}

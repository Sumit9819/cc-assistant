<?php
/** Server-owned observations, scoped to the authenticated WordPress/API session. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-integrity.php';

class CC_Assistant_Evidence_Gate {
	const TTL = 600;
	const MATERIAL_PREFIX = 'm2:';
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
	/** Version of every active plugin, keyed by plugin file, sorted. */
	public static function active_plugin_versions( $prior_cc_version = null ) {
		if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$plugins = get_plugins(); $versions = array();
		$active = array_unique( array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) );
		foreach ( $active as $file ) { $versions[$file] = $plugins[$file]['Version'] ?? 'missing'; }
		if ( null !== $prior_cc_version && defined( 'CC_ASSISTANT_BASENAME' ) && isset( $versions[CC_ASSISTANT_BASENAME] ) ) { $versions[CC_ASSISTANT_BASENAME] = $prior_cc_version; }
		ksort( $versions );
		return $versions;
	}
	/** The pre-0.90.0 whole-site fingerprint. Kept so plans queued before 0.90.0 still validate. */
	public static function environment_hash( $prior_cc_version = null ) {
		$versions = self::active_plugin_versions( $prior_cc_version );
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
	/**
	 * Active plugins whose VERSION can change how a queued change is stored,
	 * routed or rendered: the SEO plugin (which meta key a logical key routes
	 * to), the page builder and multilingual plugin (how content parses), the
	 * permalink rewriter (where the page lives) and this plugin itself.
	 */
	public static function material_plugin_slugs() {
		$slugs = array(
			'seo-by-rank-math', 'seo-by-rank-math-pro', 'wordpress-seo', 'wordpress-seo-premium',
			'all-in-one-seo-pack', 'all-in-one-seo-pack-pro', 'wp-seopress', 'wp-seopress-pro',
			'the-seo-framework', 'slim-seo', 'squirrly-seo',
			'elementor', 'elementor-pro', 'divi-builder', 'bb-plugin', 'beaver-builder-lite-version',
			'js_composer', 'breakdance',
			'polylang', 'polylang-pro', 'sitepress-multilingual-cms', 'translatepress-multilingual',
			'custom-permalinks',
		);
		if ( defined( 'CC_ASSISTANT_BASENAME' ) ) { $slugs[] = strtok( CC_ASSISTANT_BASENAME, '/' ); }
		if ( function_exists( 'apply_filters' ) ) { $slugs = apply_filters( 'cc_assistant_material_plugin_slugs', $slugs ); }
		return array_values( array_unique( array_map( 'strval', (array) $slugs ) ) );
	}
	/**
	 * The environment as named, separately hashed parts. Only these gate an
	 * apply. Before 0.90.0 one opaque hash covered the version of EVERY active
	 * plugin, so an overnight auto-update of an unrelated plugin invalidated
	 * every queued change and the refusal could not say what had moved.
	 * Activating or deactivating a plugin still blocks: the active set is a part.
	 */
	public static function environment_components( $prior_cc_version = null ) {
		$versions = self::active_plugin_versions( $prior_cc_version );
		$slugs = self::material_plugin_slugs(); $material = array();
		foreach ( $versions as $file => $version ) { if ( in_array( strtok( (string) $file, '/' ), $slugs, true ) ) { $material[$file] = $version; } }
		$themes = array();
		if ( function_exists( 'wp_get_theme' ) ) { foreach ( array_unique( array( get_option( 'stylesheet' ), get_option( 'template' ) ) ) as $theme ) { $themes[$theme] = wp_get_theme( $theme )->get( 'Version' ); } }
		$mu = function_exists( 'get_mu_plugins' ) ? get_mu_plugins() : array();
		foreach ( $mu as $file => $info ) { $mu[$file] = $info['Version'] ?? ''; }
		ksort( $mu );
		$kit = (int) get_option( 'elementor_active_kit', 0 );
		$options = array();
		foreach ( array( 'home', 'siteurl', 'permalink_structure', 'show_on_front', 'page_on_front', 'polylang', 'rank_math_modules', 'wpseo', 'rank-math-options-titles', 'rank-math-options-general', 'wpseo_titles', 'elementor_experiment-container' ) as $option ) { $options[$option] = get_option( $option, null ); }
		$parts = array(
			'wp_version' => $GLOBALS['wp_version'] ?? '',
			'theme' => array( 'stylesheet' => get_option( 'stylesheet' ), 'template' => get_option( 'template' ), 'versions' => $themes ),
			'mu_plugins' => $mu,
			'active_plugins' => array_keys( $versions ),
			'material_plugins' => $material,
			'elementor_kit' => $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : null,
			'seo_and_routing_options' => $options,
			'blog_public' => get_option( 'blog_public' ),
		);
		foreach ( $parts as $name => $value ) { $parts[$name] = hash( 'sha256', wp_json_encode( $value ) ); }
		return $parts;
	}
	/** The prefix makes a stored fingerprint self-describing, so pre-0.90.0 plans still route to the legacy comparison. */
	public static function hash_components( $components ) { return self::MATERIAL_PREFIX . hash( 'sha256', wp_json_encode( $components ) ); }
	public static function environment_material_hash( $prior_cc_version = null ) { return self::hash_components( self::environment_components( $prior_cc_version ) ); }
	/** Names the environment parts that moved since this evidence was recorded. */
	public static function environment_drift( $evidence ) {
		$stored = is_array( $evidence ) && is_array( $evidence['environment_components'] ?? null ) ? $evidence['environment_components'] : array();
		if ( ! $stored ) { return array(); }
		$now = self::environment_components(); $moved = array();
		foreach ( $now as $name => $hash ) { if ( ! isset( $stored[$name] ) || ! hash_equals( (string) $stored[$name], $hash ) ) { $moved[] = $name; } }
		foreach ( array_keys( $stored ) as $name ) { if ( ! isset( $now[$name] ) ) { $moved[] = $name; } }
		return array_values( array_unique( $moved ) );
	}
	/** Names the material plugins whose version moved, so a refusal points at the actual upgrade. */
	public static function material_plugin_drift( $evidence ) {
		$before = is_array( $evidence ) && is_array( $evidence['environment_material_plugins'] ?? null ) ? $evidence['environment_material_plugins'] : array();
		if ( ! $before ) { return array(); }
		$now = array(); $slugs = self::material_plugin_slugs();
		foreach ( self::active_plugin_versions() as $file => $version ) { if ( in_array( strtok( (string) $file, '/' ), $slugs, true ) ) { $now[$file] = $version; } }
		$moved = array();
		foreach ( $now as $file => $version ) { if ( ! isset( $before[$file] ) ) { $moved[$file] = 'activated at ' . $version; } elseif ( $before[$file] !== $version ) { $moved[$file] = $before[$file] . ' to ' . $version; } }
		foreach ( $before as $file => $version ) { if ( ! isset( $now[$file] ) ) { $moved[$file] = $version . ' to not active'; } }
		return $moved;
	}
	/** This tested guard-only upgrade preserves 0.89.3 plans; every other material input must match. */
	public static function environment_matches( $hash ) {
		if ( ! is_string( $hash ) || '' === $hash ) { return false; }
		$material = 0 === strpos( $hash, self::MATERIAL_PREFIX );
		if ( hash_equals( $material ? self::environment_material_hash() : self::environment_hash(), $hash ) ) { return true; }
		$compatible = array( '0.89.4' => array( '0.89.3' ), '0.89.5' => array( '0.89.3', '0.89.4' ), '0.89.6' => array( '0.89.3', '0.89.4', '0.89.5' ), '0.89.7' => array( '0.89.3', '0.89.4', '0.89.5', '0.89.6' ), '0.89.8' => array( '0.89.3', '0.89.4', '0.89.5', '0.89.6', '0.89.7' ), '0.89.9' => array( '0.89.3', '0.89.4', '0.89.5', '0.89.6', '0.89.7', '0.89.8' ), '0.89.10' => array( '0.89.3', '0.89.4', '0.89.5', '0.89.6', '0.89.7', '0.89.8', '0.89.9' ), '0.89.11' => array( '0.89.3', '0.89.4', '0.89.5', '0.89.6', '0.89.7', '0.89.8', '0.89.9', '0.89.10' ), '0.89.12' => array( '0.89.3', '0.89.4', '0.89.5', '0.89.6', '0.89.7', '0.89.8', '0.89.9', '0.89.10', '0.89.11' ), '0.90.0' => array( '0.89.3', '0.89.4', '0.89.5', '0.89.6', '0.89.7', '0.89.8', '0.89.9', '0.89.10', '0.89.11', '0.89.12' ) );
		foreach ( defined( 'CC_ASSISTANT_VERSION' ) ? ( $compatible[CC_ASSISTANT_VERSION] ?? array() ) : array() as $prior ) {
			if ( defined( 'CC_ASSISTANT_BASENAME' ) && hash_equals( $material ? self::environment_material_hash( $prior ) : self::environment_hash( $prior ), $hash ) ) { return true; }
		}
		return false;
	}
	private static function handler_name( $handler ) { return is_array( $handler['callback'] ?? null ) ? (string) ( $handler['callback'][1] ?? '' ) : ''; }
	public static function before_read( $response, $handler, $request ) {
		if ( ! self::active() || 0 !== strpos( $request->get_route(), '/cc-assistant/v1/' ) ) { return $response; }
		$name = self::handler_name( $handler );
		$id = (int) $request->get_param( 'id' );
		if ( in_array( $name, array( 'handle_verified_page_audit', 'handle_get_post' ), true ) && $id > 0 ) {
			$state = CC_Assistant_Integrity::post_state( $id );
			self::$before[spl_object_hash( $request )] = array( CC_Assistant_Integrity::state_hash( $state ), self::environment_material_hash(), $state );
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
		// A refused read hands back a WP_Error, not an array. $good already captured
		// that, but the diagnostic branches below read $data with array syntax, and
		// on PHP 8 that is a fatal error against an object rather than a null read.
		if ( ! is_array( $data ) ) { $data = array(); }
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
			$hash = CC_Assistant_Integrity::post_hash( $id ); $environment = self::environment_material_hash();
			$unchanged = $before && $hash === ( $before[0] ?? null ) && $environment === ( $before[1] ?? null );
			if ( ( $valid_audit || $valid_editor ) && $unchanged && '' !== $hash ) {
				self::forget( 'diag', $id );
				// Keep the per-key digests with the receipt. If the fingerprint
				// moves before the proposal arrives, this is the only way to name
				// the key that moved: the receipt otherwise holds one opaque hash.
				self::store( 'post', $id, array( 'observed_at' => time(), 'post_hash' => $hash, 'environment_hash' => $environment, 'kind' => $valid_audit ? 'server_html' : 'editor', 'body_sha1' => $valid_audit ? ( $data['source']['body_sha1'] ?? null ) : null, 'meta_hashes' => CC_Assistant_Integrity::state_meta_hashes( CC_Assistant_Integrity::post_state( $id ) ) ) );
			} elseif ( $valid_audit || $valid_editor ) {
				// A qualifying read that still produced no receipt. Record WHICH
				// precondition failed: "state changed" alone cannot distinguish a
				// builder cache the render rewrote from a missing before-snapshot,
				// and without that the caller can only guess.
				$diag = array( 'observed_at' => time(), 'reason' => $before ? 'fingerprint_moved_during_read' : 'no_before_snapshot',
					'handler' => $name, 'post_id' => $id, 'hash_empty' => ( '' === $hash ) );
				if ( $before ) {
					$diag['environment_changed'] = $environment !== ( $before[1] ?? null );
					$diag['changed_meta'] = CC_Assistant_Integrity::state_meta_diff( (array) ( $before[2] ?? array() ), CC_Assistant_Integrity::post_state( $id ) );
				}
				self::store( 'diag', $id, $diag );
				self::forget( 'post', $id );
			} elseif ( $id > 0 && ( 'handle_verified_page_audit' === $name || 'handle_get_post' === $name ) ) {
				// The read did not qualify as evidence at all. For a published page
				// only verified_page_audit can qualify, so say so rather than leaving
				// the caller to rediscover it from the source.
				$post_now = get_post( $id );
				self::store( 'diag', $id, array( 'observed_at' => time(), 'reason' => 'read_did_not_qualify',
					'handler' => $name, 'post_id' => $id, 'response_ok' => (bool) $good,
					'post_status' => $post_now ? $post_now->post_status : 'missing',
					'post_type' => $post_now ? $post_now->post_type : 'missing',
					'usable' => isset( $data['usable'] ) ? (bool) $data['usable'] : null,
					'reported_post_id' => isset( $data['source']['post_id'] ) ? (int) $data['source']['post_id'] : ( isset( $data['id'] ) ? (int) $data['id'] : null ),
					'hint' => 'A published page needs verified_page_audit; get_post only qualifies for a draft or an elementor_library template, with slim and widget_id both unset.' ) );
				self::forget( 'post', $id );
			} elseif ( 'handle_verified_page_audit' === $name || ! $good || ! empty( $data['stale'] ) || ! empty( $data['refresh_error'] ) ) {
				self::forget( 'post', $id );
			}
		}
		if ( 'handle_stack_settings' === $name ) {
			$slug = (string) $request->get_param( 'slug' );
			$previous = self::receipt( 'option_list', $slug );
			foreach ( $previous['names'] ?? array() as $option ) { self::forget( 'option', $option ); }
			$names = array(); $environment = self::environment_material_hash();
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
				self::store( 'post', $kit, array( 'observed_at' => time(), 'post_hash' => CC_Assistant_Integrity::post_hash( $kit ), 'environment_hash' => self::environment_material_hash(), 'kind' => 'kit_editor' ) );
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
		$components = self::environment_components();
		$material_plugins = array(); $slugs = self::material_plugin_slugs();
		foreach ( self::active_plugin_versions() as $file => $version ) { if ( in_array( strtok( (string) $file, '/' ), $slugs, true ) ) { $material_plugins[$file] = $version; } }
		$evidence = array( 'version' => 1, 'actor_hash' => hash( 'sha256', self::actor() ), 'posts' => array(), 'environment_hash' => self::hash_components( $components ), 'environment_components' => $components, 'environment_material_plugins' => $material_plugins );
		foreach ( array_unique( $ids ) as $id ) {
			$r = self::receipt( 'post', $id );
			if ( isset( self::$created[$id] ) && get_post_status( $id ) === 'draft' ) { $r = array( 'observed_at' => time(), 'post_hash' => CC_Assistant_Integrity::post_hash( $id ), 'environment_hash' => $evidence['environment_hash'], 'kind' => 'created_in_this_request' ); }
			if ( ! $r || empty( $r['post_hash'] ) || $r['post_hash'] !== CC_Assistant_Integrity::post_hash( $id ) || $r['environment_hash'] !== $evidence['environment_hash'] ) {
				$diag = self::receipt( 'diag', $id );
				$why = '';
				// A receipt that exists but no longer matches means the post moved
				// AFTER the read stored it, so no diag was ever written. That case
				// used to report nothing at all; name the keys instead.
				if ( ! is_array( $diag ) && is_array( $r ) && ! empty( $r['post_hash'] ) ) {
					$now = CC_Assistant_Integrity::post_state( $id );
					$moved = is_array( $r['meta_hashes'] ?? null )
						? CC_Assistant_Integrity::meta_hash_diff( $r['meta_hashes'], CC_Assistant_Integrity::state_meta_hashes( $now ) )
						: array();
					$diag = array(
						'observed_at' => time(),
						'reason' => 'receipt_went_stale_after_read',
						'post_id' => $id,
						'receipt_kind' => $r['kind'] ?? null,
						'receipt_age_seconds' => time() - (int) ( $r['observed_at'] ?? 0 ),
						'environment_changed' => ( $r['environment_hash'] ?? null ) !== $evidence['environment_hash'],
						'post_hash_changed' => $r['post_hash'] !== CC_Assistant_Integrity::post_hash( $id ),
						'moved_meta' => $moved,
						'meta_hashes_recorded' => is_array( $r['meta_hashes'] ?? null ),
					);
					$why = ' The evidence read succeeded, then the post moved before this proposal arrived';
					if ( $moved ) {
						$pairs = array();
						foreach ( array_slice( $moved, 0, 8, true ) as $key => $how ) { $pairs[] = $key . ' (' . $how . ')'; }
						$why .= ': meta ' . implode( ', ', $pairs ) . '.';
						$why .= ' If those are builder-generated caches rather than content, add them via the cc_assistant_volatile_meta_keys filter.';
					} elseif ( ! empty( $diag['environment_changed'] ) ) {
						$why .= ' because the plugin/theme environment changed.';
					} elseif ( ! $diag['meta_hashes_recorded'] ) {
						$why .= '; this receipt predates per-key digests, so re-run the read once and retry.';
					} else {
						$why .= ', with no meta difference, so the change is in post fields or terms.';
					}
				}
				if ( is_array( $diag ) && '' === $why ) {
					$keys = (array) ( $diag['changed_meta'] ?? array() );
					$reason = (string) ( $diag['reason'] ?? '' );
					if ( 'read_did_not_qualify' === $reason ) {
						$why = ' The last read (' . ( $diag['handler'] ?? '?' ) . ') did not qualify as evidence for this ' . ( $diag['post_status'] ?? '?' ) . ' ' . ( $diag['post_type'] ?? '?' ) . '. ' . ( $diag['hint'] ?? '' );
					} elseif ( 'no_before_snapshot' === $reason ) {
						$why = ' The last read produced no before-snapshot to compare against, so no receipt could be stored.';
					} else {
						$why = ' The last read did not produce usable evidence because its fingerprint moved during the read itself';
						$why .= $keys ? ': meta ' . implode( ', ', array_slice( $keys, 0, 8 ) ) . ' changed.' : ( ! empty( $diag['environment_changed'] ) ? ' because the plugin/theme environment changed.' : ', with no content meta difference (check the environment hash).' );
						if ( $keys ) { $why .= ' If those are builder-generated caches rather than content, add them via the cc_assistant_volatile_meta_keys filter.'; }
					}
				}
				return new WP_Error( 'page_evidence_required', 'Fresh evidence for post ' . $id . ' is missing or its state changed. Run verified_page_audit for a published page; get_post for a draft/template; get_kit_settings for the active kit. Then rebuild the proposal.' . $why, array( 'status' => 409, 'post_id' => $id, 'evidence_diagnostics' => $diag ) );
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
	public static function validate_apply( $pending ) {
		$baseline = CC_Assistant_Integrity::baseline( $pending );
		$evidence = $baseline['evidence'] ?? null;
		if ( is_array( $evidence ) && ! self::environment_matches( $evidence['environment_hash'] ?? '' ) ) {
			// Name what moved. The old message left the caller diffing plugin
			// lists by hand to find the one component the hash had covered.
			$parts = self::environment_drift( $evidence );
			$plugins = self::material_plugin_drift( $evidence );
			$named = array();
			foreach ( array_slice( $plugins, 0, 6, true ) as $file => $how ) { $named[] = strtok( (string) $file, '/' ) . ' ' . $how; }
			$why = $parts ? ' Changed: ' . implode( ', ', $parts ) . '.' : '';
			$why .= $named ? ' Plugin versions: ' . implode( ', ', $named ) . '.' : '';
			return new WP_Error( 'environment_changed', 'The plugin/theme/kit or SEO configuration changed after this plan was drafted.' . $why . ' Inspect the current site and queue a new plan.', array( 'status' => 409, 'environment_changed_parts' => $parts, 'material_plugin_drift' => $plugins ) );
		}
		foreach ( (array) ( $evidence['posts'] ?? array() ) as $id => $receipt ) {
			$expected = $receipt['post_hash'] ?? '';
			$live_hash = CC_Assistant_Integrity::post_hash( (int) $id );
			if ( $expected !== $live_hash ) {
				require_once __DIR__ . '/class-approval-continuation.php';
				$continuation = CC_Assistant_Approval_Continuation::prove( $pending, $live_hash );
				if ( ! empty( $continuation['safe'] ) ) { continue; }
				return new WP_Error( 'evidence_state_changed', 'Post ' . $id . ' changed after this proposal was observed. Evidence check: ' . ( $continuation['reason'] ?? 'unexplained_state_change' ) . '. Inspect verify_change diagnostics before rebuilding.', array( 'status' => 409, 'evidence_diagnostics' => $continuation ) );
			}
		}
		if ( isset( $baseline['publication_workflow_basis'] ) ) {
			if ( ! class_exists( 'CC_Assistant_Workflow_Verifier' ) ) { require_once __DIR__ . '/class-workflow-verifier.php'; }
			if ( ! CC_Assistant_Workflow_Verifier::basis( $baseline['publication_workflow_basis'] )['current'] ) {
				return new WP_Error( 'publication_sources_changed', 'The reviewed publication sources or strategy changed. Have Claude refresh the existing publication proposal after rechecking the draft.', array( 'status' => 409 ) );
			}
		}
		return true;
	}
}

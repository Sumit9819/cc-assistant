<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
$valid_tabs = array( 'general', 'citations', 'style', 'memory', 'connection', 'gsc', 'gbp', 'health' );
if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
	$active_tab = 'general';
}

if ( isset( $_POST['cc_assistant_settings_nonce'] )
	&& wp_verify_nonce( $_POST['cc_assistant_settings_nonce'], 'cc_assistant_save_settings' ) ) {

	$saved_tab = isset( $_POST['cc_active_tab'] ) ? sanitize_key( $_POST['cc_active_tab'] ) : 'general';

	if ( 'general' === $saved_tab ) {
		$allowed_post_types = isset( $_POST['allowed_post_types'] )
			? array_map( 'sanitize_text_field', (array) $_POST['allowed_post_types'] )
			: array( 'page', 'post' );
		update_option( 'cc_assistant_allowed_post_types', $allowed_post_types );

		$brand_terms_raw = isset( $_POST['brand_terms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['brand_terms'] ) ) : '';
		$brand_terms     = array_filter( array_map( 'trim', explode( "\n", $brand_terms_raw ) ) );
		update_option( 'cc_assistant_brand_terms', $brand_terms, false );

		$llm_enabled = ! empty( $_POST['llm_tracking_enabled'] );
		update_option( 'cc_assistant_llm_tracking_enabled', $llm_enabled );

		$toc_enabled = ! empty( $_POST['cc_toc_enabled'] );
		update_option( 'cc_assistant_toc_enabled', $toc_enabled );

		// v0.55 Microsoft Clarity project ID — validates the predicted
		// attention_audit against real heatmaps. Empty = no script injected.
		// is_string guard: an array POST would survive preg_replace as an
		// array and render as "Array" on the front end. Autoload stays ON
		// (default) so the wp_head read costs zero extra queries.
		$clarity_raw = ( isset( $_POST['cc_clarity_project_id'] ) && is_string( $_POST['cc_clarity_project_id'] ) )
			? wp_unslash( $_POST['cc_clarity_project_id'] )
			: '';
		update_option( 'cc_assistant_clarity_project_id', preg_replace( '/[^a-z0-9]/i', '', $clarity_raw ) );

		// v0.35 facility address — used by address_consistency lint to
		// refuse content that mentions a different street address. Leaving
		// it empty disables the check (no-op).
		$facility_address = isset( $_POST['cc_facility_address'] )
			? sanitize_text_field( wp_unslash( $_POST['cc_facility_address'] ) )
			: '';
		update_option( 'cc_assistant_facility_address', $facility_address, false );

		// v0.48 form email standard — recipients/senders the build_page_from_spec
		// `form` template bakes into every composed Elementor form. Empty
		// fields fall back to admin_email / "<site name> Website" at compose
		// time; the _2 (agency copy) recipient is emitted only when set.
		$form_std = array();
		foreach ( array( 'email_to', 'email_from', 'email_to_2', 'email_from_2', 'email_reply_to_2' ) as $fk ) {
			$v = isset( $_POST[ 'cc_form_' . $fk ] ) ? sanitize_email( wp_unslash( $_POST[ 'cc_form_' . $fk ] ) ) : '';
			if ( '' !== $v ) {
				$form_std[ $fk ] = $v;
			}
		}
		foreach ( array( 'email_from_name', 'email_from_name_2' ) as $fk ) {
			$v = isset( $_POST[ 'cc_form_' . $fk ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'cc_form_' . $fk ] ) ) : '';
			if ( '' !== $v ) {
				$form_std[ $fk ] = $v;
			}
		}
		update_option( 'cc_assistant_form_email_standard', $form_std, false );

		// v0.49 opt-in: allow the Elementor read/edit tools to touch Theme
		// Builder templates (headers/footers/single-post — the elementor_library
		// post type). Off by default because one template edit changes every
		// page that uses it. Trashing templates stays blocked regardless.
		update_option( 'cc_assistant_allow_template_editing', ! empty( $_POST['cc_allow_template_editing'] ) );

		// Industry override drives the per-site SEO playbook overlay. "auto"
		// clears the manual choice and lets detection populate it again.
		if ( isset( $_POST['cc_industry_slug'] ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
			$industry_slug = sanitize_key( wp_unslash( $_POST['cc_industry_slug'] ) );
			CC_Assistant_Industry_Profile::set_manual( $industry_slug );
		}

		// Bust dashboard caches so new settings take effect immediately.
		delete_transient( 'cc_assistant_dashboard_insights' );
	}

	if ( 'citations' === $saved_tab ) {
		$competitors    = isset( $_POST['competitor_domains'] ) ? sanitize_textarea_field( wp_unslash( $_POST['competitor_domains'] ) ) : '';
		$competitor_list = array_filter( array_map( 'trim', explode( "\n", $competitors ) ) );
		update_option( 'cc_assistant_competitor_domains', $competitor_list, false );

		$authority      = isset( $_POST['authority_domains'] ) ? sanitize_textarea_field( wp_unslash( $_POST['authority_domains'] ) ) : '';
		$authority_list = array_filter( array_map( 'trim', explode( "\n", $authority ) ) );
		update_option( 'cc_assistant_authority_domains', $authority_list, false );
	}

	if ( 'style' === $saved_tab ) {
		$style = isset( $_POST['style_guide'] ) ? wp_unslash( $_POST['style_guide'] ) : '';
		update_option( 'cc_assistant_style_guide', $style, false );
	}

	if ( 'memory' === $saved_tab ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
		$notes = isset( $_POST['site_notes'] ) ? wp_unslash( $_POST['site_notes'] ) : '';
		CC_Assistant_Site_Memory::set_notes( $notes );
	}

	if ( 'gsc' === $saved_tab ) {
		$client_id     = isset( $_POST['gsc_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gsc_client_id'] ) ) : '';
		$client_secret = isset( $_POST['gsc_client_secret'] ) ? trim( wp_unslash( $_POST['gsc_client_secret'] ) ) : '';
		update_option( 'cc_assistant_gsc_client_id', $client_id, false );
		if ( '' !== $client_secret ) {
			update_option( 'cc_assistant_gsc_client_secret', $client_secret, false );
		}
		$redirect_override = isset( $_POST['gsc_redirect_override'] ) ? esc_url_raw( trim( wp_unslash( $_POST['gsc_redirect_override'] ) ) ) : '';
		update_option( 'cc_assistant_gsc_redirect_override', $redirect_override, false );
	}

	if ( 'gbp' === $saved_tab ) {
		$gbp_client_id     = isset( $_POST['gbp_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gbp_client_id'] ) ) : '';
		$gbp_client_secret = isset( $_POST['gbp_client_secret'] ) ? trim( wp_unslash( $_POST['gbp_client_secret'] ) ) : '';
		update_option( 'cc_assistant_gbp_client_id', $gbp_client_id, false );
		if ( '' !== $gbp_client_secret ) {
			update_option( 'cc_assistant_gbp_client_secret', $gbp_client_secret, false );
		}
		$gbp_redirect_override = isset( $_POST['gbp_redirect_override'] ) ? esc_url_raw( trim( wp_unslash( $_POST['gbp_redirect_override'] ) ) ) : '';
		update_option( 'cc_assistant_gbp_redirect_override', $gbp_redirect_override, false );
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'cc-assistant' ) . '</p></div>';
}

if ( isset( $_GET['cc_gsc_msg'] ) ) {
	$msg_map = array(
		'connected'      => array( 'success', __( 'Search Console connected.', 'cc-assistant' ) ),
		'disconnected'   => array( 'success', __( 'Search Console disconnected.', 'cc-assistant' ) ),
		'sync_queued'    => array( 'success', __( 'Sync queued. Data will appear within a minute or two.', 'cc-assistant' ) ),
		'property_set'   => array( 'success', __( 'Property selected.', 'cc-assistant' ) ),
		'oauth_error'    => array( 'error',   __( 'OAuth failed. See "Last error" below.', 'cc-assistant' ) ),
		'state_mismatch' => array( 'error',   __( 'Security check failed (state mismatch). Please try again.', 'cc-assistant' ) ),
		'missing_creds'  => array( 'error',   __( 'Save the Client ID and Client Secret first.', 'cc-assistant' ) ),
	);
	$msg_key = sanitize_key( $_GET['cc_gsc_msg'] );
	if ( isset( $msg_map[ $msg_key ] ) ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $msg_map[ $msg_key ][0] ),
			esc_html( $msg_map[ $msg_key ][1] )
		);
	}
}

if ( isset( $_GET['cc_gbp_msg'] ) ) {
	$gbp_msg_map = array(
		'connected'           => array( 'success', __( 'Business Profile connected.', 'cc-assistant' ) ),
		'disconnected'        => array( 'success', __( 'Business Profile disconnected.', 'cc-assistant' ) ),
		'locations_refreshed' => array( 'success', __( 'Locations refreshed.', 'cc-assistant' ) ),
		'fetch_error'         => array( 'error',   __( 'Could not fetch locations. See "Last error" below.', 'cc-assistant' ) ),
		'oauth_error'         => array( 'error',   __( 'OAuth failed. See "Last error" below.', 'cc-assistant' ) ),
		'state_mismatch'      => array( 'error',   __( 'Security check failed (state mismatch). Please try again.', 'cc-assistant' ) ),
		'missing_creds'       => array( 'error',   __( 'Save the Client ID and Client Secret first.', 'cc-assistant' ) ),
	);
	$gbp_msg_key = sanitize_key( $_GET['cc_gbp_msg'] );
	if ( isset( $gbp_msg_map[ $gbp_msg_key ] ) ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $gbp_msg_map[ $gbp_msg_key ][0] ),
			esc_html( $gbp_msg_map[ $gbp_msg_key ][1] )
		);
	}
}

$allowed_post_types = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
$competitor_domains = get_option( 'cc_assistant_competitor_domains', array() );
$authority_domains  = get_option( 'cc_assistant_authority_domains', array() );
$post_types         = get_post_types( array( 'public' => true ), 'objects' );
$site_id            = get_option( 'cc_assistant_site_id', '' );
$plugin_path        = wp_normalize_path( CC_ASSISTANT_DIR );
$project_root       = wp_normalize_path( ABSPATH );
?>
<div class="wrap cc-assistant cc-settings">
	<h1><?php esc_html_e( 'CC Assistant Settings', 'cc-assistant' ); ?></h1>
	<p class="cc-tagline"><?php esc_html_e( 'Tell Claude what to read, what to cite, and how to write.', 'cc-assistant' ); ?></p>

	<nav class="nav-tab-wrapper cc-tab-nav">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'general' ) ); ?>" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'General', 'cc-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'citations' ) ); ?>" class="nav-tab <?php echo 'citations' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Citation Rules', 'cc-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'style' ) ); ?>" class="nav-tab <?php echo 'style' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Style Guide', 'cc-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'memory' ) ); ?>" class="nav-tab <?php echo 'memory' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Site Memory', 'cc-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'connection' ) ); ?>" class="nav-tab <?php echo 'connection' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Connection', 'cc-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'gsc' ) ); ?>" class="nav-tab <?php echo 'gsc' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Search Console', 'cc-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'gbp' ) ); ?>" class="nav-tab <?php echo 'gbp' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Business Profile', 'cc-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'health' ) ); ?>" class="nav-tab <?php echo 'health' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Health', 'cc-assistant' ); ?>
		</a>
	</nav>

	<div class="cc-tab-content">

	<?php if ( 'general' === $active_tab ) : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'cc_assistant_save_settings', 'cc_assistant_settings_nonce' ); ?>
			<input type="hidden" name="cc_active_tab" value="general" />

			<div class="cc-card">
				<h2><?php esc_html_e( 'Allowed post types', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Pick which post types Claude is allowed to read or draft for. Anything not checked is invisible.', 'cc-assistant' ); ?></p>
				<?php foreach ( $post_types as $type ) : ?>
					<label class="cc-checkbox">
						<input type="checkbox" name="allowed_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $allowed_post_types, true ) ); ?> />
						<?php echo esc_html( $type->label ); ?> <code><?php echo esc_html( $type->name ); ?></code>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="cc-card">
				<h2><?php esc_html_e( 'Site identity', 'cc-assistant' ); ?></h2>
				<p><strong><?php esc_html_e( 'Site ID:', 'cc-assistant' ); ?></strong> <code><?php echo esc_html( $site_id ); ?></code></p>
				<p><strong><?php esc_html_e( 'Fingerprint:', 'cc-assistant' ); ?></strong> <code><?php echo esc_html( CC_Assistant_Site_Identity::fingerprint() ); ?></code></p>
				<p class="description"><?php esc_html_e( 'These identifiers help Claude verify which site it is connected to before any change.', 'cc-assistant' ); ?></p>
			</div>

			<?php
			require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
			$industry_profile = CC_Assistant_Industry_Profile::get();
			$industry_current = isset( $industry_profile['industry'] ) ? $industry_profile['industry'] : 'general';
			$industry_source  = isset( $industry_profile['source'] ) ? $industry_profile['source'] : 'auto';
			$industry_conf    = isset( $industry_profile['confidence'] ) ? (int) $industry_profile['confidence'] : 0;
			?>
			<div class="cc-card">
				<h2><?php esc_html_e( 'Industry', 'cc-assistant' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Determines which industry overlay the SEO playbook composes for this site. Universal SEO rules apply everywhere; the overlay adds vertical-specific citation hosts, schema types, credentialing patterns, and content rules.', 'cc-assistant' ); ?>
				</p>
				<p>
					<label for="cc_industry_slug" class="screen-reader-text"><?php esc_html_e( 'Industry', 'cc-assistant' ); ?></label>
					<select name="cc_industry_slug" id="cc_industry_slug">
						<option value="auto"><?php esc_html_e( 'Auto-detect (re-detect on save)', 'cc-assistant' ); ?></option>
						<?php foreach ( CC_Assistant_Industry_Profile::INDUSTRIES as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $industry_current, $slug ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description">
					<strong><?php esc_html_e( 'Currently active:', 'cc-assistant' ); ?></strong>
					<code><?php echo esc_html( $industry_current ); ?></code>
					(<?php echo esc_html( CC_Assistant_Industry_Profile::label( $industry_current ) ); ?>)
					&middot; <?php esc_html_e( 'source:', 'cc-assistant' ); ?> <code><?php echo esc_html( $industry_source ); ?></code>
					<?php if ( 'auto' === $industry_source && $industry_conf > 0 ) : ?>
						&middot; <?php esc_html_e( 'confidence:', 'cc-assistant' ); ?> <code><?php echo esc_html( $industry_conf ); ?>/100</code>
					<?php endif; ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Set to "Auto-detect" if you want the plugin to re-derive the industry from your schema markup and content corpus the next time the playbook is requested.', 'cc-assistant' ); ?>
				</p>
			</div>

			<?php
			require_once CC_ASSISTANT_DIR . 'includes/class-query-tagger.php';
			$brand_terms_saved = (array) get_option( 'cc_assistant_brand_terms', array() );
			$brand_terms_value = empty( $brand_terms_saved ) ? '' : implode( "\n", $brand_terms_saved );
			$brand_terms_auto  = CC_Assistant_Query_Tagger::brand_terms();
			?>
			<div class="cc-card">
				<h2><?php esc_html_e( 'Brand terms', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Queries containing any of these strings get tagged as branded and excluded from the GSC opportunity lens. One per line.', 'cc-assistant' ); ?></p>
				<textarea name="brand_terms" rows="4" class="large-text code" placeholder="yourbrand&#10;your brand name&#10;yourbrand.com"><?php echo esc_textarea( $brand_terms_value ); ?></textarea>
				<?php if ( empty( $brand_terms_saved ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Auto-detected from your site:', 'cc-assistant' ); ?>
						<?php foreach ( $brand_terms_auto as $t ) : ?>
							<code><?php echo esc_html( $t ); ?></code>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</div>

			<?php $facility_address_saved = (string) get_option( 'cc_assistant_facility_address', '' ); ?>
			<div class="cc-card">
				<h2><?php esc_html_e( 'Facility address', 'cc-assistant' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Used by lint to refuse content that mentions a different street address. Enter the canonical address as it should appear in copy (e.g. "1234 Main St, Suite 200, Yourtown TX 75001"). Leave empty to disable the check.', 'cc-assistant' ); ?>
				</p>
				<input type="text"
					name="cc_facility_address"
					class="large-text"
					value="<?php echo esc_attr( $facility_address_saved ); ?>"
					placeholder="1234 Main St, Suite 200, Yourtown TX 75001" />
				<p class="description">
					<?php esc_html_e( 'When set, the address_consistency_check hard-fails any draft_update_elementor_widget / draft_add_elementor_container that introduces a different street address. Soft-warns on zip codes that do not match.', 'cc-assistant' ); ?>
				</p>
			</div>

			<?php $allow_templates = (bool) get_option( 'cc_assistant_allow_template_editing', false ); ?>
			<div class="cc-card">
				<h2><?php esc_html_e( 'Theme Builder template editing', 'cc-assistant' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Lets the assistant read and propose edits to Elementor Theme Builder templates (headers, footers, single-post layouts). Off by default. A template edit changes EVERY page that uses it, so all changes still go through the Pending Changes inbox for your approval, and templates can never be trashed by the tool.', 'cc-assistant' ); ?>
				</p>
				<label class="cc-checkbox">
					<input type="checkbox" name="cc_allow_template_editing" value="1" <?php checked( $allow_templates ); ?> />
					<?php esc_html_e( 'Allow editing Theme Builder templates (site-wide impact)', 'cc-assistant' ); ?>
				</label>
			</div>

			<?php $form_std_saved = (array) get_option( 'cc_assistant_form_email_standard', array() ); ?>
			<div class="cc-card">
				<h2><?php esc_html_e( 'Form email standard', 'cc-assistant' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Recipients and senders baked into every contact form the page builder composes. Subjects are set automatically ("New website form submission - {page} page"). Leave a field empty to fall back to the site admin email; leave the second recipient empty to send to one inbox only.', 'cc-assistant' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cc_form_email_to"><?php esc_html_e( 'Send submissions to', 'cc-assistant' ); ?></label></th>
						<td><input type="email" id="cc_form_email_to" name="cc_form_email_to" class="regular-text" value="<?php echo esc_attr( isset( $form_std_saved['email_to'] ) ? $form_std_saved['email_to'] : '' ); ?>" placeholder="info@example.com" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_form_email_from"><?php esc_html_e( 'From address', 'cc-assistant' ); ?></label></th>
						<td><input type="email" id="cc_form_email_from" name="cc_form_email_from" class="regular-text" value="<?php echo esc_attr( isset( $form_std_saved['email_from'] ) ? $form_std_saved['email_from'] : '' ); ?>" placeholder="info@example.com" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_form_email_from_name"><?php esc_html_e( 'From name', 'cc-assistant' ); ?></label></th>
						<td><input type="text" id="cc_form_email_from_name" name="cc_form_email_from_name" class="regular-text" value="<?php echo esc_attr( isset( $form_std_saved['email_from_name'] ) ? $form_std_saved['email_from_name'] : '' ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) . ' Website' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_form_email_to_2"><?php esc_html_e( 'Second recipient (optional)', 'cc-assistant' ); ?></label></th>
						<td><input type="email" id="cc_form_email_to_2" name="cc_form_email_to_2" class="regular-text" value="<?php echo esc_attr( isset( $form_std_saved['email_to_2'] ) ? $form_std_saved['email_to_2'] : '' ); ?>" placeholder="agency@example.com" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_form_email_from_2"><?php esc_html_e( 'Second copy: from address', 'cc-assistant' ); ?></label></th>
						<td><input type="email" id="cc_form_email_from_2" name="cc_form_email_from_2" class="regular-text" value="<?php echo esc_attr( isset( $form_std_saved['email_from_2'] ) ? $form_std_saved['email_from_2'] : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_form_email_from_name_2"><?php esc_html_e( 'Second copy: from name', 'cc-assistant' ); ?></label></th>
						<td><input type="text" id="cc_form_email_from_name_2" name="cc_form_email_from_name_2" class="regular-text" value="<?php echo esc_attr( isset( $form_std_saved['email_from_name_2'] ) ? $form_std_saved['email_from_name_2'] : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_form_email_reply_to_2"><?php esc_html_e( 'Second copy: reply-to', 'cc-assistant' ); ?></label></th>
						<td><input type="email" id="cc_form_email_reply_to_2" name="cc_form_email_reply_to_2" class="regular-text" value="<?php echo esc_attr( isset( $form_std_saved['email_reply_to_2'] ) ? $form_std_saved['email_reply_to_2'] : '' ); ?>" /></td>
					</tr>
				</table>
			</div>

			<?php $llm_enabled = (bool) get_option( 'cc_assistant_llm_tracking_enabled', false ); ?>
			<div class="cc-card">
				<h2><?php esc_html_e( 'AI bot crawl tracking', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Logs visits from GPTBot, ClaudeBot, PerplexityBot, Google-Extended, and other AI crawlers so you can see who is reading your content for AI training and citation. Off by default.', 'cc-assistant' ); ?></p>
				<label class="cc-checkbox">
					<input type="checkbox" name="llm_tracking_enabled" value="1" <?php checked( $llm_enabled ); ?> />
					<?php esc_html_e( 'Enable AI bot crawl logging', 'cc-assistant' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'When enabled, every front-end request is checked against a list of bot user-agents. Only matching requests trigger a database insert. IPs are stored as one-way hashes.', 'cc-assistant' ); ?></p>
			</div>

			<?php $clarity_id = (string) get_option( 'cc_assistant_clarity_project_id', '' ); ?>
			<div class="cc-card">
				<h2><?php esc_html_e( 'Microsoft Clarity heatmaps', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Paste your Clarity project ID to load its (free) heatmap + scroll-map tracker on the public site. NOTE for healthcare sites: enable Clarity&#039;s strict masking mode in the Clarity dashboard and confirm your cookie-consent policy covers behavioral analytics before enabling. Used to validate the attention_audit\'s predicted heatmap against real visitor behavior. Empty = nothing is loaded. Logged-in admins are never tracked, so your own sessions do not pollute the heatmaps.', 'cc-assistant' ); ?></p>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="cc_clarity_project_id"><?php esc_html_e( 'Clarity project ID', 'cc-assistant' ); ?></label></th>
						<td><input type="text" id="cc_clarity_project_id" name="cc_clarity_project_id" class="regular-text" placeholder="abcd1efgh2" value="<?php echo esc_attr( $clarity_id ); ?>" /></td>
					</tr>
				</table>
			</div>

			<?php $toc_enabled = (bool) get_option( 'cc_assistant_toc_enabled', false ); ?>
			<div class="cc-card">
				<h2><?php esc_html_e( 'Table of Contents (posts)', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Auto-generates a clickable Table of Contents from each post\'s H2 and H3 headings, placed just above the first paragraph. Posts only (not pages). OFF by default — enable it only on a site whose theme or TOC plugin is not handling this (e.g. ER of Irving).', 'cc-assistant' ); ?></p>
				<label class="cc-checkbox">
					<input type="checkbox" name="cc_toc_enabled" value="1" <?php checked( $toc_enabled ); ?> />
					<?php esc_html_e( 'Enable auto Table of Contents on posts', 'cc-assistant' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'When off, the plugin adds zero overhead to page loads. Disable any third-party TOC plugin before turning this on to avoid two tables of contents.', 'cc-assistant' ); ?></p>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'cc-assistant' ); ?></button>
			</p>
		</form>

	<?php elseif ( 'citations' === $active_tab ) : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'cc_assistant_save_settings', 'cc_assistant_settings_nonce' ); ?>
			<input type="hidden" name="cc_active_tab" value="citations" />

			<div class="cc-card">
				<h2><?php esc_html_e( 'Competitor domains', 'cc-assistant' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Used in two places:', 'cc-assistant' ); ?>
					<br>
					<strong><?php esc_html_e( 'Citations:', 'cc-assistant' ); ?></strong>
					<?php esc_html_e( 'Claude will never cite or link to these domains in your content, even if they have high authority.', 'cc-assistant' ); ?>
					<br>
					<strong><?php esc_html_e( 'SEO benchmarks:', 'cc-assistant' ); ?></strong>
					<?php esc_html_e( 'The competitor_brief MCP tool reads this list to know who to compare your pages against on a target keyword.', 'cc-assistant' ); ?>
					<br>
					<em><?php esc_html_e( 'One domain per line, no http or www.', 'cc-assistant' ); ?></em>
				</p>
				<textarea name="competitor_domains" rows="6" class="large-text code" placeholder="competitor1.com&#10;competitor2.com"><?php echo esc_textarea( implode( "\n", (array) $competitor_domains ) ); ?></textarea>
			</div>

			<div class="cc-card">
				<h2><?php esc_html_e( 'Trusted authority domains', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Citations from .gov and .edu are always allowed. Add other high-authority .com sources here. One per line.', 'cc-assistant' ); ?></p>
				<textarea name="authority_domains" rows="10" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $authority_domains ) ); ?></textarea>
			</div>

			<div class="cc-card cc-info-card">
				<h2><?php esc_html_e( 'How citation rules work', 'cc-assistant' ); ?></h2>
				<ul class="cc-rules-list">
					<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( '.gov and .edu sources are always allowed.', 'cc-assistant' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Domains in your authority list above are allowed.', 'cc-assistant' ); ?></li>
					<li><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Domains in your competitor list are blocked, even if they have high authority.', 'cc-assistant' ); ?></li>
					<li><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'All other .com sources are blocked by default.', 'cc-assistant' ); ?></li>
				</ul>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'cc-assistant' ); ?></button>
			</p>
		</form>

	<?php elseif ( 'style' === $active_tab ) :
		$style_guide = get_option( 'cc_assistant_style_guide', '' );
		if ( '' === $style_guide ) {
			// Show the default in the textarea so the user can fork it.
			$style_guide = "# Style guide\n\n" .
				"## Voice\n" .
				"- Natural, human, conversational. Not robotic.\n" .
				"- Short paragraphs (2 to 3 sentences max).\n" .
				"- Active voice over passive.\n\n" .
				"## Banned\n" .
				"- Em dashes (use periods, commas, parentheses)\n" .
				"- Phrases: \"in today's fast-paced world\", \"it is important to note\", \"in conclusion\", \"unleash\", \"leverage\", \"delve\", \"tapestry\", \"dive into\", \"navigate\", \"unlock\", \"game-changer\".\n\n" .
				"## EEAT\n" .
				"- Quote real, named professionals with sources.\n" .
				"- First-hand experience markers when realistic.\n" .
				"- Cite only .gov, .edu, or domains in the trusted authority list. Never cite competitors.\n" .
				"- Visible last-updated date and author byline.\n\n" .
				"## AI/LLM friendly\n" .
				"- TL;DR or key takeaways block at the top of long posts.\n" .
				"- Definition sentence near the top (\"X is Y that does Z\").\n" .
				"- Question-style H2s where natural.\n";
		}
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'cc_assistant_save_settings', 'cc_assistant_settings_nonce' ); ?>
			<input type="hidden" name="cc_active_tab" value="style" />
			<div class="cc-card">
				<h2><?php esc_html_e( 'Brand style guide', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Claude reads this before any content write. Markdown supported. Edit freely. Saving overrides the default.', 'cc-assistant' ); ?></p>
				<textarea name="style_guide" rows="20" class="large-text code"><?php echo esc_textarea( $style_guide ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Tip: keep this short and concrete. Long style guides get ignored.', 'cc-assistant' ); ?></p>
			</div>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save style guide', 'cc-assistant' ); ?></button>
			</p>
		</form>

	<?php elseif ( 'memory' === $active_tab ) :
		require_once CC_ASSISTANT_DIR . 'includes/class-site-memory.php';
		$memory   = CC_Assistant_Site_Memory::full_memory();
		$detected = $memory['detected'];
		$notes    = $memory['notes'];
		?>
		<div class="cc-card">
			<h2><?php esc_html_e( 'Auto-detected stack', 'cc-assistant' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Claude reads this at session start so it never writes the wrong meta keys for your SEO plugin or page builder. Refreshes every time the page loads.', 'cc-assistant' ); ?></p>
			<table class="cc-memory-table">
				<tr>
					<th><?php esc_html_e( 'SEO plugin', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( ! empty( $detected['seo']['plugin'] ) ) : ?>
							<strong><?php echo esc_html( $detected['seo']['plugin_name'] ); ?></strong>
							<?php if ( ! empty( $detected['seo']['version'] ) ) : ?>
								<span class="cc-mem-version">v<?php echo esc_html( $detected['seo']['version'] ); ?></span>
							<?php endif; ?>
							<div class="cc-mem-detail">
								<?php esc_html_e( 'Meta keys:', 'cc-assistant' ); ?>
								<?php foreach ( $detected['seo']['meta_keys'] as $logical => $key ) : ?>
									<code><?php echo esc_html( $logical ); ?> &rarr; <?php echo esc_html( $key ); ?></code>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<em><?php esc_html_e( 'None detected', 'cc-assistant' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Page builder', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( ! empty( $detected['page_builder']['plugin'] ) ) : ?>
							<strong><?php echo esc_html( $detected['page_builder']['plugin_name'] ); ?></strong>
							<?php if ( ! empty( $detected['page_builder']['version'] ) ) : ?>
								<span class="cc-mem-version">v<?php echo esc_html( $detected['page_builder']['version'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $detected['page_builder']['pro'] ) ) : ?>
								<span class="cc-mem-tag">Pro</span>
							<?php endif; ?>
						<?php else : ?>
							<em><?php esc_html_e( 'None detected', 'cc-assistant' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Theme', 'cc-assistant' ); ?></th>
					<td>
						<strong><?php echo esc_html( $detected['theme']['name'] ); ?></strong>
						<span class="cc-mem-version">v<?php echo esc_html( $detected['theme']['version'] ); ?></span>
						<?php if ( ! empty( $detected['theme']['is_child'] ) ) : ?>
							<span class="cc-mem-tag">child of <?php echo esc_html( $detected['theme']['parent'] ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Multilingual', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( ! empty( $detected['multilingual']['plugin'] ) ) : ?>
							<strong><?php echo esc_html( $detected['multilingual']['plugin_name'] ); ?></strong>
						<?php else : ?>
							<em><?php esc_html_e( 'Single language', 'cc-assistant' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Commerce', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( ! empty( $detected['commerce']['plugin'] ) ) : ?>
							<strong><?php echo esc_html( $detected['commerce']['plugin_name'] ); ?></strong>
							<?php if ( ! empty( $detected['commerce']['version'] ) ) : ?>
								<span class="cc-mem-version">v<?php echo esc_html( $detected['commerce']['version'] ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<em><?php esc_html_e( 'Not present', 'cc-assistant' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Caching', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( ! empty( $detected['caching'] ) ) : ?>
							<?php foreach ( $detected['caching'] as $c ) : ?>
								<code><?php echo esc_html( $c ); ?></code>
							<?php endforeach; ?>
						<?php else : ?>
							<em><?php esc_html_e( 'No cache plugin detected', 'cc-assistant' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Forms', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( ! empty( $detected['forms'] ) ) : ?>
							<?php foreach ( $detected['forms'] as $f ) : ?>
								<code><?php echo esc_html( $f ); ?></code>
							<?php endforeach; ?>
						<?php else : ?>
							<em><?php esc_html_e( 'None detected', 'cc-assistant' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Permalinks', 'cc-assistant' ); ?></th>
					<td>
						<code><?php echo esc_html( $detected['permalinks']['structure'] ?: 'plain' ); ?></code>
					</td>
				</tr>
			</table>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'cc_assistant_save_settings', 'cc_assistant_settings_nonce' ); ?>
			<input type="hidden" name="cc_active_tab" value="memory" />
			<div class="cc-card">
				<h2><?php esc_html_e( 'Site notes (free-form)', 'cc-assistant' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Anything Claude should remember about this site that auto-detection cannot pick up. Brand voice quirks, things to never touch, custom fields it should know about, audience facts. Claude reads these at session start.', 'cc-assistant' ); ?></p>
				<textarea name="site_notes" rows="14" class="large-text code" placeholder="<?php esc_attr_e( "## Brand voice\n- Plain English. No medical jargon without explanation.\n\n## Never touch\n- The /careers/ page (legal copy)\n\n## Audience\n- Local DFW residents searching for emergency care.", 'cc-assistant' ); ?>"><?php echo esc_textarea( $notes ); ?></textarea>
				<?php if ( ! empty( $memory['updated_at'] ) ) : ?>
					<p class="description"><?php printf( esc_html__( 'Last updated: %s', 'cc-assistant' ), esc_html( $memory['updated_at'] ) ); ?></p>
				<?php endif; ?>
			</div>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save notes', 'cc-assistant' ); ?></button>
			</p>
		</form>

	<?php elseif ( 'connection' === $active_tab ) :
		$snippet        = CC_Assistant_Site_Identity::mcp_config_snippet();
		$is_local       = CC_Assistant_Site_Identity::is_local_install();
		$detected_php   = ! empty( $snippet['detected'] );
		$generated_json = wp_json_encode( $snippet['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		?>
		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Quick start', 'cc-assistant' ); ?></h2>
			<ol class="cc-steps">
				<li>
					<strong><?php esc_html_e( 'Create an Application Password.', 'cc-assistant' ); ?></strong><br>
					<?php
					printf(
						/* translators: %s: link */
						esc_html__( 'Create a dedicated user with the CC Assistant Operator role under %s, then open that user profile and generate an Application Password named "Claude Code".', 'cc-assistant' ),
						'<a href="' . esc_url( admin_url( 'users.php' ) ) . '">' . esc_html__( 'Users', 'cc-assistant' ) . '</a>'
					);
					?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Copy the config block below.', 'cc-assistant' ); ?></strong><br>
					<?php esc_html_e( 'It is pre-filled with your URL and current username. Set CC_WP_USER to the operator username and replace PASTE_YOUR_APP_PASSWORD_HERE with its password. Review changes using your separate administrator account.', 'cc-assistant' ); ?>
				</li>
				<?php if ( $is_local ) : ?>
					<li>
						<strong><?php esc_html_e( 'Save it as .mcp.json in this site\'s folder on your computer.', 'cc-assistant' ); ?></strong><br>
						<?php
						printf(
							/* translators: %s: local site path */
							esc_html__( 'For this Local site that folder is %s. Save the file at the very top of it (next to wp-content, wp-config.php, etc.).', 'cc-assistant' ),
							'<code>' . esc_html( $project_root ) . '</code>'
						);
						?>
					</li>
				<?php else : ?>
					<li>
						<strong><?php esc_html_e( 'Make a folder on YOUR computer for this site.', 'cc-assistant' ); ?></strong><br>
						<?php esc_html_e( 'Anywhere is fine — for example C:\\Users\\you\\projects\\my-site\\ on Windows, or ~/projects/my-site/ on Mac. The .mcp.json file lives on YOUR computer, NOT on this live server. Save the snippet below as .mcp.json inside that folder.', 'cc-assistant' ); ?>
					</li>
				<?php endif; ?>
				<li>
					<strong><?php esc_html_e( 'Open that folder in Claude Code and verify.', 'cc-assistant' ); ?></strong><br>
					<?php esc_html_e( 'Ask: "Use the whoami tool to tell me about the connected site." The fingerprint should match the one on the dashboard.', 'cc-assistant' ); ?>
				</li>
			</ol>
		</div>

		<?php if ( ! $is_local ) : ?>
			<div class="cc-card cc-card-wide cc-info-card" style="border-left:4px solid #2271b1;">
				<h2><?php esc_html_e( 'Where does .mcp.json go? (read this first)', 'cc-assistant' ); ?></h2>
				<p><strong><?php esc_html_e( 'On YOUR computer, in any folder you want to open in Claude Code. NOT inside the WordPress files on this live server.', 'cc-assistant' ); ?></strong></p>
				<p><?php esc_html_e( 'The MCP server is a small PHP script that runs on your local machine. It reads the credentials from .mcp.json and talks to this site\'s REST API over HTTPS. Nothing is uploaded to the live server.', 'cc-assistant' ); ?></p>
				<p><?php esc_html_e( 'A folder dedicated to this site is the cleanest setup. Example layout:', 'cc-assistant' ); ?></p>
<pre class="cc-code-block">C:\Users\you\projects\
  my-site\
    .mcp.json    &larr; the file you save below
</pre>
				<p class="description"><?php esc_html_e( 'You can also add this site as another entry inside an existing .mcp.json that already lists other cc-assistant sites — paste the inner block (everything inside "mcpServers") alongside the others. The "command" and "args" fields will be the same as your other entries since they all run on your machine.', 'cc-assistant' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="cc-card cc-card-wide cc-config-card">
			<div class="cc-config-header">
				<h2><?php esc_html_e( 'Your MCP config', 'cc-assistant' ); ?></h2>
				<div class="cc-config-actions">
					<button type="button" class="button" id="cc-copy-config"><?php esc_html_e( 'Copy', 'cc-assistant' ); ?></button>
					<button type="button" class="button" id="cc-download-config"><?php esc_html_e( 'Download .mcp.json', 'cc-assistant' ); ?></button>
				</div>
			</div>
			<?php if ( $detected_php ) : ?>
				<div class="notice notice-success inline" style="margin:6px 0 10px;">
					<p>
						<span class="dashicons dashicons-yes-alt" style="color:#2e7d32;"></span>
						<strong><?php esc_html_e( 'Auto-filled for your environment.', 'cc-assistant' ); ?></strong>
						<?php
						printf(
							/* translators: %s: detected PHP binary path */
							esc_html__( 'Detected Local-by-Flywheel\'s bundled PHP at %s and pre-filled the command, args, and curl/openssl extension flags. The snippet below works copy-paste — only the application password needs filling in.', 'cc-assistant' ),
							'<code>' . esc_html( $snippet['detected']['php'] ) . '</code>'
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Pre-filled with your site URL and username. The "command" field is "php" — if php is on your shell PATH this works as-is. If not (typical on Windows), see the PHP path note further down.', 'cc-assistant' ); ?></p>
			<?php endif; ?>
			<pre class="cc-code-block" id="cc-config-block"><?php echo esc_html( $generated_json ); ?></pre>
			<?php if ( $is_local ) : ?>
				<p class="description"><?php esc_html_e( 'For other sites (live sites, other local sites), install this plugin on each one and visit its Connection tab to get a config block tailored to that site.', 'cc-assistant' ); ?></p>
			<?php endif; ?>
			<script>
			(function () {
				var copyBtn = document.getElementById('cc-copy-config');
				var downloadBtn = document.getElementById('cc-download-config');
				var block = document.getElementById('cc-config-block');
				if (copyBtn && block) {
					copyBtn.addEventListener('click', function () {
						navigator.clipboard.writeText(block.textContent).then(function () {
							var prev = copyBtn.textContent;
							copyBtn.textContent = '<?php echo esc_js( __( 'Copied', 'cc-assistant' ) ); ?>';
							setTimeout(function () { copyBtn.textContent = prev; }, 1500);
						});
					});
				}
				if (downloadBtn && block) {
					downloadBtn.addEventListener('click', function () {
						var blob = new Blob([block.textContent], { type: 'application/json' });
						var a = document.createElement('a');
						a.href = URL.createObjectURL(blob);
						a.download = '.mcp.json';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
					});
				}
			})();
			</script>
		</div>

		<div class="cc-card cc-card-wide cc-info-card">
			<h2><?php esc_html_e( 'Live site vs local site', 'cc-assistant' ); ?></h2>
			<p><strong><?php esc_html_e( 'Local site.', 'cc-assistant' ); ?></strong> <?php esc_html_e( 'The site folder on your computer doubles as the folder you open in Claude Code. Save .mcp.json at the top of that folder and you\'re done.', 'cc-assistant' ); ?></p>
			<p><strong><?php esc_html_e( 'Live site.', 'cc-assistant' ); ?></strong> <?php esc_html_e( 'There is no local site folder. Make a small folder on your own computer (anywhere) just for the .mcp.json file, and open that folder in Claude Code. The PHP script still runs locally on your machine and talks to the live site over HTTPS — nothing gets uploaded to the live server.', 'cc-assistant' ); ?></p>
			<p class="description"><?php esc_html_e( 'Coming in v0.2: a remote MCP mode where Claude Code talks directly to the live site over HTTPS, with no local PHP needed.', 'cc-assistant' ); ?></p>
		</div>

		<div class="cc-card cc-card-wide cc-info-card">
			<h2><?php esc_html_e( 'Test connection', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Verifies this WordPress install is reachable, your application password works, and that the host PHP has the extensions needed for outbound HTTPS. Run this before opening Claude Code on a new site.', 'cc-assistant' ); ?></p>
			<p>
				<button type="button" class="button button-primary" id="cc-test-connection">
					<span class="dashicons dashicons-admin-network" style="font-size:14px; height:14px; width:14px; vertical-align:text-bottom;"></span>
					<?php esc_html_e( 'Test connection', 'cc-assistant' ); ?>
				</button>
			</p>
			<div id="cc-test-connection-result" style="margin-top:10px;"></div>
			<script>
			(function () {
				var btn = document.getElementById('cc-test-connection');
				var out = document.getElementById('cc-test-connection-result');
				if (!btn || !out) { return; }
				btn.addEventListener('click', function () {
					out.innerHTML = '<em><?php echo esc_js( __( 'Testing...', 'cc-assistant' ) ); ?></em>';
					var url = '<?php echo esc_url_raw( rest_url( 'cc-assistant/v1/connection/test' ) ); ?>';
					fetch(url, {
						method: 'GET',
						credentials: 'same-origin',
						headers: { 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' }
					}).then(function (r) {
						return r.json().then(function (j) { return { ok: r.ok, body: j }; });
					}).then(function (res) {
						if (!res.ok) {
							out.innerHTML = '<div class="notice notice-error inline"><p>' +
								'<?php echo esc_js( __( 'Connection failed:', 'cc-assistant' ) ); ?> ' +
								(res.body && res.body.message ? res.body.message : 'unknown error') +
								'</p></div>';
							return;
						}
						var d = res.body && res.body.data ? res.body.data : {};
						var ext = d.php_extensions || {};
						var rows = [
							'<strong><?php echo esc_js( __( 'Site:', 'cc-assistant' ) ); ?></strong> ' + (d.site_url || ''),
							'<strong><?php echo esc_js( __( 'User:', 'cc-assistant' ) ); ?></strong> ' + (d.wp_user || ''),
							'<strong><?php echo esc_js( __( 'Fingerprint:', 'cc-assistant' ) ); ?></strong> <code>' + (d.fingerprint || '') + '</code>',
							'<strong><?php echo esc_js( __( 'Plugin:', 'cc-assistant' ) ); ?></strong> ' + (d.plugin_version || ''),
							'<strong><?php echo esc_js( __( 'PHP curl:', 'cc-assistant' ) ); ?></strong> ' + (ext.curl ? 'OK' : '<span style="color:#b32d2e">MISSING</span>'),
							'<strong><?php echo esc_js( __( 'PHP openssl:', 'cc-assistant' ) ); ?></strong> ' + (ext.openssl ? 'OK' : '<span style="color:#b32d2e">MISSING</span>')
						];
						var warn = '';
						if (!ext.curl || !ext.openssl) {
							warn = '<div class="notice notice-warning inline" style="margin-top:8px;"><p>' +
								'<?php echo esc_js( __( 'The web-server PHP is missing curl/openssl. The MCP server uses the bundled CLI PHP which on Local by Flywheel often lacks these too — the snippet above already includes the extension flags to load them when this site\'s install was auto-detected.', 'cc-assistant' ) ); ?>' +
								'</p></div>';
						}
						out.innerHTML = '<div class="notice notice-success inline"><p>' + rows.join(' &nbsp;|&nbsp; ') + '</p></div>' + warn;
					}).catch(function (e) {
						out.innerHTML = '<div class="notice notice-error inline"><p>' +
							'<?php echo esc_js( __( 'Network error:', 'cc-assistant' ) ); ?> ' + e.message +
							'</p></div>';
					});
				});
			})();
			</script>
		</div>

		<?php if ( ! $detected_php ) : ?>
			<div class="cc-card cc-card-wide cc-info-card">
				<h2><?php esc_html_e( 'PHP path note', 'cc-assistant' ); ?></h2>
				<p><?php esc_html_e( 'The "command" field above is "php", which assumes a php binary is on your shell PATH. Mac with Homebrew and most Linux distros have this by default. Windows usually does not.', 'cc-assistant' ); ?></p>
				<p><strong><?php esc_html_e( 'On Windows + Local by Flywheel:', 'cc-assistant' ); ?></strong> <?php esc_html_e( 'the cleanest approach is to install this plugin on a Local site too — its Connection tab auto-detects and pre-fills the bundled PHP path for you. Or build the path manually:', 'cc-assistant' ); ?></p>
<pre class="cc-code-block">"command": "C:/Users/&lt;you&gt;/AppData/Local/Programs/Local/resources/extraResources/lightning-services/php-X.Y.Z+0/bin/win64/php.exe",
"args": [
  "-d", "extension_dir=C:/Users/&lt;you&gt;/AppData/Local/Programs/Local/resources/extraResources/lightning-services/php-X.Y.Z+0/bin/win64/ext",
  "-d", "extension=curl",
  "-d", "extension=openssl",
  "./wp-content/plugins/cc-assistant/bin/mcp-server.php"
]</pre>
				<p class="description"><?php esc_html_e( 'X.Y.Z is your bundled PHP version (visible in Local: Tools > Custom Site Settings). The two -d extension flags load curl and openssl, which Local\'s bundled CLI PHP does not load by default — both are required for MCP tools that hit HTTPS endpoints.', 'cc-assistant' ); ?></p>
			</div>
		<?php endif; ?>

	<?php elseif ( 'gbp' === $active_tab ) :
		require_once CC_ASSISTANT_DIR . 'includes/class-gbp.php';
		$gbp_status     = CC_Assistant_GBP::status();
		$gbp_client_id  = (string) get_option( 'cc_assistant_gbp_client_id', '' );
		$gbp_has_secret = (bool) get_option( 'cc_assistant_gbp_client_secret', '' );
		$gbp_redirect   = CC_Assistant_GBP::redirect_uri();
		$gbp_base       = admin_url( 'admin.php?page=cc-assistant-settings&tab=gbp' );
		?>
		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Google Business Profile', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Connect your Business Profile so Claude can audit local categories and pull local-pack performance (impressions, calls, direction requests, website clicks) per location. One OAuth connection covers every location you manage.', 'cc-assistant' ); ?></p>
			<ul class="cc-rules-list">
				<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Audit each location\'s primary + additional categories (categories drive map rank)', 'cc-assistant' ); ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Track local-pack KPIs: impressions, calls, directions, website clicks', 'cc-assistant' ); ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Keep NAP (name / address / phone) consistent with the website', 'cc-assistant' ); ?></li>
			</ul>
			<p class="description">
				<span class="dashicons dashicons-info" style="color:#b26a00;"></span>
				<?php esc_html_e( 'Reviews (read + reply) are NOT part of these APIs — they require a separate Google access approval (legacy Business Profile API v4). If your quota shows 0, access has not been granted: submit the "Application for Basic API Access" on the Business Profile API contact form, not a quota-increase request. Approved access reports 300 QPM.', 'cc-assistant' ); ?>
			</p>
			<p class="description">
				<span class="dashicons dashicons-yes-alt" style="color:#2e7d32;"></span>
				<?php esc_html_e( 'You do not have to wait for that approval to monitor reviews. The Google Places API needs only an API key (no application, no allowlist) and returns the star rating, the total review count, and the 5 most recent reviews with timestamps — enough to track rating, count and recency per location. Set it up here:', 'cc-assistant' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-reviews' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Set up review data (Places API key + Place ID)', 'cc-assistant' ); ?></a>
			</p>
		</div>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Status', 'cc-assistant' ); ?></h2>
			<table class="cc-memory-table">
				<tr>
					<th><?php esc_html_e( 'Credentials', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( $gbp_status['configured'] ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#2e7d32;"></span> <?php esc_html_e( 'Saved', 'cc-assistant' ); ?>
						<?php else : ?>
							<span class="dashicons dashicons-no-alt" style="color:#c62828;"></span> <?php esc_html_e( 'Not set. Add Client ID and Secret below.', 'cc-assistant' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Connection', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( $gbp_status['connected'] ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#2e7d32;"></span> <?php esc_html_e( 'Connected', 'cc-assistant' ); ?>
							&nbsp;<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'cc_gbp_oauth', 'disconnect', $gbp_base ), 'cc_gbp_disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect', 'cc-assistant' ); ?></a>
						<?php else : ?>
							<span class="dashicons dashicons-no-alt" style="color:#c62828;"></span> <?php esc_html_e( 'Not connected', 'cc-assistant' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Locations', 'cc-assistant' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (int) $gbp_status['location_count'] ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last fetch', 'cc-assistant' ); ?></th>
					<td>
						<?php echo $gbp_status['last_fetch_at'] ? esc_html( $gbp_status['last_fetch_at'] ) : '<em>' . esc_html__( 'Never', 'cc-assistant' ) . '</em>'; ?>
					</td>
				</tr>
				<?php if ( ! empty( $gbp_status['last_error'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Last error', 'cc-assistant' ); ?></th>
						<td><code style="color:#c62828;"><?php echo esc_html( $gbp_status['last_error'] ); ?></code></td>
					</tr>
				<?php endif; ?>
			</table>
		</div>

		<?php if ( CC_Assistant_GBP::host_is_local_only() ) : ?>
			<div class="cc-card cc-card-wide" style="background:#fff8e1; border-color:#ffe082;">
				<h2 style="color:#b26a00;"><span class="dashicons dashicons-warning" style="color:#b26a00;"></span> <?php esc_html_e( 'Local-only site — Google OAuth needs a public URL.', 'cc-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Google rejects redirect URIs on .local / .test TLDs. Use a Local "Live Link" or a tunnel (ngrok / Cloudflare), or connect from a live site, then paste the tunnel callback URL in the "Redirect URI override" field below. (Same workflow as the Search Console tab.)', 'cc-assistant' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Step 1. Enable the APIs + create an OAuth client', 'cc-assistant' ); ?></h2>
			<ol class="cc-steps">
				<li><?php printf( /* translators: %s: link */ esc_html__( 'In %s, enable: My Business Account Management API, My Business Business Information API, and Business Profile Performance API.', 'cc-assistant' ), '<a href="https://console.cloud.google.com/apis/library" target="_blank" rel="noopener">Google Cloud → APIs Library</a>' ); ?></li>
				<li><?php printf( /* translators: %s: link */ esc_html__( 'Configure the %s (User type External), and add the scope %s. Stay in Testing mode and add your Google account as a test user.', 'cc-assistant' ), '<a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" rel="noopener">OAuth consent screen</a>', '<code>.../auth/business.manage</code>' ); ?></li>
				<li><?php printf( /* translators: %s: link */ esc_html__( 'In %s → Create credentials → OAuth client ID → Web application.', 'cc-assistant' ), '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Credentials</a>' ); ?></li>
				<li>
					<?php esc_html_e( 'Add this Authorized redirect URI:', 'cc-assistant' ); ?>
					<br><code id="cc-gbp-redirect"><?php echo esc_html( $gbp_redirect ); ?></code>
					<button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('cc-gbp-redirect').textContent)"><?php esc_html_e( 'Copy', 'cc-assistant' ); ?></button>
				</li>
				<li><?php esc_html_e( 'Also submit the separate Business Profile API access request if you want reviews later.', 'cc-assistant' ); ?></li>
			</ol>
		</div>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Step 2. Save credentials', 'cc-assistant' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'cc_assistant_save_settings', 'cc_assistant_settings_nonce' ); ?>
				<input type="hidden" name="cc_active_tab" value="gbp" />
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gbp_client_id"><?php esc_html_e( 'Client ID', 'cc-assistant' ); ?></label></th>
						<td><input type="text" id="gbp_client_id" name="gbp_client_id" value="<?php echo esc_attr( $gbp_client_id ); ?>" class="regular-text code" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gbp_client_secret"><?php esc_html_e( 'Client Secret', 'cc-assistant' ); ?></label></th>
						<td>
							<input type="password" id="gbp_client_secret" name="gbp_client_secret" value="" class="regular-text code" autocomplete="off" placeholder="<?php echo $gbp_has_secret ? esc_attr__( '•••••••• (saved, leave blank to keep)', 'cc-assistant' ) : ''; ?>" />
							<p class="description"><?php esc_html_e( 'Stored encrypted using the WordPress salt.', 'cc-assistant' ); ?></p>
						</td>
					</tr>
					<?php $gbp_redirect_saved = (string) get_option( 'cc_assistant_gbp_redirect_override', '' ); ?>
					<tr>
						<th scope="row"><label for="gbp_redirect_override"><?php esc_html_e( 'Redirect URI override', 'cc-assistant' ); ?></label></th>
						<td>
							<input type="url" id="gbp_redirect_override" name="gbp_redirect_override" value="<?php echo esc_attr( $gbp_redirect_saved ); ?>" class="large-text code" autocomplete="off" placeholder="https://random.localwp.com/wp-admin/admin.php?page=cc-assistant-settings&amp;tab=gbp&amp;cc_gbp_oauth=callback" />
							<p class="description"><?php esc_html_e( 'Optional. Leave blank in production. Use on a local site where Google rejects the auto-derived URL.', 'cc-assistant' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save credentials', 'cc-assistant' ); ?></button></p>
			</form>
		</div>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Step 3. Connect + fetch locations', 'cc-assistant' ); ?></h2>
			<?php if ( ! $gbp_status['configured'] ) : ?>
				<p><em><?php esc_html_e( 'Save your Client ID and Secret above first.', 'cc-assistant' ); ?></em></p>
			<?php elseif ( ! $gbp_status['connected'] ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'cc_gbp_oauth', 'connect', $gbp_base ), 'cc_gbp_connect' ) ); ?>"><?php esc_html_e( 'Connect with Google', 'cc-assistant' ); ?></a>
			<?php else : ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'cc_gbp_oauth', 'refresh_locations', $gbp_base ), 'cc_gbp_refresh_locations' ) ); ?>"><?php esc_html_e( 'Refresh locations', 'cc-assistant' ); ?></a>
				<?php $gbp_locations = $gbp_status['locations']; ?>
				<?php if ( ! empty( $gbp_locations ) ) : ?>
					<table class="cc-memory-table" style="margin-top:12px;">
						<thead><tr>
							<th><?php esc_html_e( 'Location', 'cc-assistant' ); ?></th>
							<th><?php esc_html_e( 'Primary category', 'cc-assistant' ); ?></th>
							<th><?php esc_html_e( 'Address', 'cc-assistant' ); ?></th>
							<th><?php esc_html_e( 'Website', 'cc-assistant' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $gbp_locations as $loc ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $loc['title'] ?? '' ); ?></strong></td>
								<td><?php echo esc_html( $loc['primary_category'] ?? '' ); ?></td>
								<td><?php echo esc_html( $loc['address'] ?? '' ); ?></td>
								<td><?php echo ! empty( $loc['website'] ) ? '<a href="' . esc_url( $loc['website'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $loc['website'], PHP_URL_HOST ) ) . '</a>' : '<em>&mdash;</em>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Connected, but no locations cached yet. Click "Refresh locations".', 'cc-assistant' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>

	<?php elseif ( 'gsc' === $active_tab ) :
		require_once CC_ASSISTANT_DIR . 'includes/class-gsc.php';
		$gsc_status     = CC_Assistant_GSC::status();
		$gsc_client_id  = (string) get_option( 'cc_assistant_gsc_client_id', '' );
		$gsc_has_secret = (bool) get_option( 'cc_assistant_gsc_client_secret', '' );
		$gsc_redirect   = CC_Assistant_GSC::redirect_uri();
		?>
		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Google Search Console', 'cc-assistant' ); ?></h2>
			<p><?php esc_html_e( 'Connect a verified GSC property so Claude can pull query data per page and surface optimization opportunities. Data lives in this database only.', 'cc-assistant' ); ?></p>
			<ul class="cc-rules-list">
				<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Find pages ranking for queries they do not even mention', 'cc-assistant' ); ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Find queries at position 5 to 15 you can push to page 1', 'cc-assistant' ); ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Fix titles where impressions are high but clicks are low', 'cc-assistant' ); ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'List every query a single page ranks for', 'cc-assistant' ); ?></li>
			</ul>
		</div>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Status', 'cc-assistant' ); ?></h2>
			<table class="cc-memory-table">
				<tr>
					<th><?php esc_html_e( 'Credentials', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( $gsc_status['configured'] ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#2e7d32;"></span> <?php esc_html_e( 'Saved', 'cc-assistant' ); ?>
						<?php else : ?>
							<span class="dashicons dashicons-no-alt" style="color:#c62828;"></span> <?php esc_html_e( 'Not set. Add Client ID and Secret below.', 'cc-assistant' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Connection', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( $gsc_status['connected'] ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#2e7d32;"></span> <?php esc_html_e( 'Connected', 'cc-assistant' ); ?>
						<?php else : ?>
							<span class="dashicons dashicons-no-alt" style="color:#c62828;"></span> <?php esc_html_e( 'Not connected', 'cc-assistant' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Property', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( $gsc_status['property'] ) : ?>
							<code><?php echo esc_html( $gsc_status['property'] ); ?></code>
						<?php else : ?>
							<em><?php esc_html_e( 'No property selected', 'cc-assistant' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last sync', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( $gsc_status['last_sync_at'] ) : ?>
							<?php echo esc_html( $gsc_status['last_sync_at'] ); ?>
						<?php else : ?>
							<em><?php esc_html_e( 'Never', 'cc-assistant' ); ?></em>
						<?php endif; ?>
						<?php if ( $gsc_status['next_scheduled'] ) : ?>
							&middot; <span class="description"><?php printf( /* translators: %s: time */ esc_html__( 'next scheduled %s', 'cc-assistant' ), esc_html( gmdate( 'Y-m-d H:i', (int) $gsc_status['next_scheduled'] ) . ' UTC' ) ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Cached rows', 'cc-assistant' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (int) $gsc_status['rows_cached'] ) ); ?></td>
				</tr>
				<?php if ( ! empty( $gsc_status['last_error'] ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Last error', 'cc-assistant' ); ?></th>
						<td><code style="color:#c62828;"><?php echo esc_html( $gsc_status['last_error'] ); ?></code></td>
					</tr>
				<?php endif; ?>
			</table>
		</div>

		<?php if ( CC_Assistant_GSC::host_is_local_only() ) :
			$default_uri = CC_Assistant_GSC::default_redirect_uri();
			?>
			<div class="cc-card cc-card-wide" style="background:#fff8e1; border-color:#ffe082;">
				<h2 style="color:#b26a00;">
					<span class="dashicons dashicons-warning" style="color:#b26a00;"></span>
					<?php esc_html_e( 'This is a local-only site. Google OAuth needs a public URL.', 'cc-assistant' ); ?>
				</h2>
				<p>
					<?php
					printf(
						/* translators: %s: domain */
						esc_html__( 'Your site runs on %s. Google rejects redirect URIs on .local, .test, and other non-public TLDs (only public domains, %1$slocalhost%2$s, or %1$s127.0.0.1%2$s are allowed).', 'cc-assistant' ),
						'<code>' . esc_html( wp_parse_url( $default_uri, PHP_URL_HOST ) ) . '</code>',
						'<code>',
						'</code>'
					);
					?>
				</p>
				<p><strong><?php esc_html_e( 'Three ways to get past this:', 'cc-assistant' ); ?></strong></p>
				<ol class="cc-steps">
					<li>
						<strong><?php esc_html_e( 'Local by Flywheel "Live Link" (easiest).', 'cc-assistant' ); ?></strong>
						<?php esc_html_e( 'In the Local app, click the Live Link toggle for this site. Local gives you a temporary public URL like', 'cc-assistant' ); ?>
						<code>https://random-words.localwp.com</code>.
						<?php esc_html_e( 'Use that URL as the redirect (and access wp-admin via that URL when authorizing).', 'cc-assistant' ); ?>
					</li>
					<li>
						<strong>ngrok / Cloudflare Tunnel.</strong>
						<?php esc_html_e( 'Same idea: tunnel your local site to a public HTTPS URL and use that as the redirect.', 'cc-assistant' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Authorize on a live site.', 'cc-assistant' ); ?></strong>
						<?php esc_html_e( 'Install the plugin on your live WordPress and connect there instead. Tokens persist on that site.', 'cc-assistant' ); ?>
					</li>
				</ol>
				<p class="description">
					<?php esc_html_e( 'After you pick a tunnel, paste its full callback URL in the "Redirect URI override" field in Step 2 below — the plugin will use it during OAuth and accept the callback through the tunnel.', 'cc-assistant' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Step 1. Create an OAuth client in Google Cloud', 'cc-assistant' ); ?></h2>
			<ol class="cc-steps">
				<li><?php
					printf(
						/* translators: %s: link */
						esc_html__( 'Open %s and create a project (or use an existing one).', 'cc-assistant' ),
						'<a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>'
					);
				?></li>
				<li><?php
					printf(
						/* translators: %s: link */
						esc_html__( 'Enable the %s for that project.', 'cc-assistant' ),
						'<a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener">Google Search Console API</a>'
					);
				?></li>
				<li>
					<strong><?php esc_html_e( 'Configure the OAuth consent screen.', 'cc-assistant' ); ?></strong>
					<br>
					<?php
					printf(
						/* translators: %s: link */
						esc_html__( 'Open %s, choose User type "External", and fill in the basics (app name, support email, your email).', 'cc-assistant' ),
						'<a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" rel="noopener">APIs & Services > OAuth consent screen</a>'
					);
					?>
					<div class="cc-warn-inline" style="margin-top:8px; padding:10px 12px; background:#fff8e1; border-left:3px solid #ffb300; border-radius:4px;">
						<strong style="color:#b26a00;"><?php esc_html_e( 'Critical: stay in Testing mode and add yourself as a test user.', 'cc-assistant' ); ?></strong>
						<br>
						<?php esc_html_e( 'On the consent screen page, look for "Publishing status". It should say "Testing" — if it says "In production", click "Back to testing". Then scroll to "Test users" and add the Google email you will authorize with.', 'cc-assistant' ); ?>
						<br>
						<small class="description"><?php esc_html_e( 'If you skip this, Google will block the connection with an "unverified app, verification will take some time" message. Testing mode lets up to 100 test users authorize instantly with no verification needed.', 'cc-assistant' ); ?></small>
					</div>
				</li>
				<li><?php
					printf(
						/* translators: %s: link */
						esc_html__( 'Open %s, click "Create credentials" > "OAuth client ID" > "Web application".', 'cc-assistant' ),
						'<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">APIs & Services > Credentials</a>'
					);
				?></li>
				<li>
					<?php esc_html_e( 'Add an Authorized redirect URI:', 'cc-assistant' ); ?>
					<br><code id="cc-gsc-redirect"><?php echo esc_html( $gsc_redirect ); ?></code>
					<button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('cc-gsc-redirect').textContent)"><?php esc_html_e( 'Copy', 'cc-assistant' ); ?></button>
					<?php if ( CC_Assistant_GSC::host_is_local_only() && ! get_option( 'cc_assistant_gsc_redirect_override' ) ) : ?>
						<br><span class="description" style="color:#c62828;"><?php esc_html_e( 'Google will reject this URL because of the .local TLD. Set a tunnel URL in Step 2 first, then come back here to copy the corrected redirect.', 'cc-assistant' ); ?></span>
					<?php endif; ?>
				</li>
				<li><?php esc_html_e( 'Copy the Client ID and Client Secret into the form below.', 'cc-assistant' ); ?></li>
			</ol>
		</div>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Step 2. Save credentials', 'cc-assistant' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'cc_assistant_save_settings', 'cc_assistant_settings_nonce' ); ?>
				<input type="hidden" name="cc_active_tab" value="gsc" />
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gsc_client_id"><?php esc_html_e( 'Client ID', 'cc-assistant' ); ?></label></th>
						<td><input type="text" id="gsc_client_id" name="gsc_client_id" value="<?php echo esc_attr( $gsc_client_id ); ?>" class="regular-text code" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gsc_client_secret"><?php esc_html_e( 'Client Secret', 'cc-assistant' ); ?></label></th>
						<td>
							<input type="password" id="gsc_client_secret" name="gsc_client_secret" value="" class="regular-text code" autocomplete="off" placeholder="<?php echo $gsc_has_secret ? esc_attr__( '•••••••• (saved, leave blank to keep)', 'cc-assistant' ) : ''; ?>" />
							<p class="description"><?php esc_html_e( 'Stored encrypted using the WordPress salt.', 'cc-assistant' ); ?></p>
						</td>
					</tr>
					<?php
					$redirect_override_saved = (string) get_option( 'cc_assistant_gsc_redirect_override', '' );
					$default_callback        = CC_Assistant_GSC::default_redirect_uri();
					?>
					<tr>
						<th scope="row"><label for="gsc_redirect_override"><?php esc_html_e( 'Redirect URI override', 'cc-assistant' ); ?></label></th>
						<td>
							<input type="url" id="gsc_redirect_override" name="gsc_redirect_override" value="<?php echo esc_attr( $redirect_override_saved ); ?>" class="large-text code" autocomplete="off" placeholder="https://random.localwp.com/wp-admin/admin.php?page=cc-assistant-settings&amp;tab=gsc&amp;cc_gsc_oauth=callback" />
							<p class="description">
								<?php esc_html_e( 'Optional. Leave blank in production. Use this on a local site (.local, .test) where Google rejects the auto-derived URL.', 'cc-assistant' ); ?>
								<br>
								<?php
								printf(
									/* translators: %s: keyword */
									esc_html__( 'Format: %s with your tunnel host swapped in.', 'cc-assistant' ),
									'<code>' . esc_html( str_replace( wp_parse_url( $default_callback, PHP_URL_HOST ), '<your-public-host>', wp_parse_url( $default_callback, PHP_URL_SCHEME ) . '://' . wp_parse_url( $default_callback, PHP_URL_HOST ) . wp_parse_url( $default_callback, PHP_URL_PATH ) . '?' . wp_parse_url( $default_callback, PHP_URL_QUERY ) ) ) . '</code>'
								);
								?>
							</p>
							<?php if ( $redirect_override_saved ) : ?>
								<p class="description" style="color:#2e7d32;">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Override active. The OAuth flow and the URL shown in Step 1 above will use this URL.', 'cc-assistant' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save credentials', 'cc-assistant' ); ?></button>
				</p>
			</form>
		</div>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Step 3. Connect your Google account', 'cc-assistant' ); ?></h2>
			<?php if ( ! $gsc_status['configured'] ) : ?>
				<p class="description"><?php esc_html_e( 'Save credentials in step 2 first.', 'cc-assistant' ); ?></p>
			<?php elseif ( $gsc_status['connected'] ) : ?>
				<p><?php esc_html_e( 'Connected. You can disconnect at any time. Disconnecting clears stored tokens but keeps the cached query data.', 'cc-assistant' ); ?></p>
				<?php
				$disconnect_url = wp_nonce_url(
					add_query_arg(
						array( 'page' => 'cc-assistant-settings', 'tab' => 'gsc', 'cc_gsc_oauth' => 'disconnect' ),
						admin_url( 'admin.php' )
					),
					'cc_gsc_disconnect'
				);
				?>
				<p><a class="button" href="<?php echo esc_url( $disconnect_url ); ?>"><?php esc_html_e( 'Disconnect', 'cc-assistant' ); ?></a></p>
			<?php else :
				$connect_url = wp_nonce_url(
					add_query_arg(
						array( 'page' => 'cc-assistant-settings', 'tab' => 'gsc', 'cc_gsc_oauth' => 'connect' ),
						admin_url( 'admin.php' )
					),
					'cc_gsc_connect'
				);
				?>
				<p><a class="button button-primary button-large" href="<?php echo esc_url( $connect_url ); ?>"><?php esc_html_e( 'Connect with Google', 'cc-assistant' ); ?></a></p>
				<p class="description"><?php esc_html_e( 'Read-only Search Console access. You can revoke any time at myaccount.google.com.', 'cc-assistant' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $gsc_status['connected'] ) :
			$properties = CC_Assistant_GSC::list_properties();
			?>
			<div class="cc-card cc-card-wide">
				<h2><?php esc_html_e( 'Step 4. Pick a property', 'cc-assistant' ); ?></h2>
				<?php if ( is_wp_error( $properties ) ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( $properties->get_error_message() ); ?></p></div>
				<?php elseif ( empty( $properties ) ) : ?>
					<p><?php esc_html_e( 'No verified properties found on this Google account. Verify your site in Search Console first.', 'cc-assistant' ); ?></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => 'cc-assistant-settings', 'tab' => 'gsc', 'cc_gsc_oauth' => 'set_property' ), admin_url( 'admin.php' ) ) ); ?>">
						<?php wp_nonce_field( 'cc_gsc_set_property' ); ?>
						<select name="cc_gsc_property" class="regular-text">
							<?php foreach ( $properties as $p ) : ?>
								<option value="<?php echo esc_attr( $p['siteUrl'] ); ?>" <?php selected( $gsc_status['property'], $p['siteUrl'] ); ?>>
									<?php echo esc_html( $p['siteUrl'] . ' (' . $p['permissionLevel'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Use this property', 'cc-assistant' ); ?></button>
					</form>
					<p class="description"><?php
						$home = home_url();
						printf(
							/* translators: %s: site URL */
							esc_html__( 'Tip: pick the property that matches %s.', 'cc-assistant' ),
							'<code>' . esc_html( $home ) . '</code>'
						);
					?></p>
				<?php endif; ?>
			</div>

			<?php if ( $gsc_status['property'] ) : ?>
				<div class="cc-card cc-card-wide">
					<h2><?php esc_html_e( 'Step 5. Sync', 'cc-assistant' ); ?></h2>
					<p><?php esc_html_e( 'A daily sync runs in the background and pulls the last 28 days of query data into the local cache. You can also trigger an on-demand sync.', 'cc-assistant' ); ?></p>
					<?php
					$sync_url = wp_nonce_url(
						add_query_arg(
							array( 'page' => 'cc-assistant-settings', 'tab' => 'gsc', 'cc_gsc_oauth' => 'sync_now' ),
							admin_url( 'admin.php' )
						),
						'cc_gsc_sync_now'
					);
					?>
					<p><a class="button" href="<?php echo esc_url( $sync_url ); ?>"><?php esc_html_e( 'Sync now (background)', 'cc-assistant' ); ?></a></p>
					<p class="description"><?php esc_html_e( 'Sync runs via WP-Cron a few seconds after you click. Refresh this page in a minute to see updated stats.', 'cc-assistant' ); ?></p>
				</div>
			<?php endif; ?>
		<?php endif; ?>

	<?php elseif ( 'health' === $active_tab ) : ?>
		<p style="margin:8px 0 0;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-db-health' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Full database console', 'cc-assistant' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cc-assistant-reviews' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Review data (Places API)', 'cc-assistant' ); ?></a>
		</p>
	<?php
		require_once CC_ASSISTANT_DIR . 'includes/class-error-log.php';
		// Handle clear-log action.
		if ( isset( $_POST['cc_clear_error_log'] ) && check_admin_referer( 'cc_clear_error_log', 'cc_clear_error_log_nonce' ) ) {
			CC_Assistant_Error_Log::clear();
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Error log cleared.', 'cc-assistant' ) . '</p></div>';
		}
		// Handle "Run now" — schedules the chosen hook 5 seconds out so wp-cron
		// fires it on the next admin request without blocking this one.
		if ( isset( $_POST['cc_run_cron_now'], $_POST['cc_run_cron_nonce'] )
			&& wp_verify_nonce( $_POST['cc_run_cron_nonce'], 'cc_run_cron_now' ) ) {
			$run_hook   = sanitize_text_field( wp_unslash( $_POST['cc_run_cron_now'] ) );
			$valid_hooks = array(
				'cc_assistant_gsc_sync',
				'cc_assistant_link_graph_rebuild',
				'cc_assistant_site_audit',
				'cc_assistant_cannib_trends',
				'cc_assistant_verdict_notify',
				'cc_assistant_warm_rendered_batch',
				'cc_assistant_llm_prune',
				'cc_assistant_advisor_recompute',
				'cc_assistant_calendar_refresh_warm',
				'cc_assistant_recompute_insights',
			);
			if ( in_array( $run_hook, $valid_hooks, true ) ) {
				wp_schedule_single_event( time() + 5, $run_hook );
				echo '<div class="notice notice-success inline"><p>'
					. sprintf(
						/* translators: %s: cron hook */
						esc_html__( '%s scheduled to run in 5 seconds. Refresh this page in 30-60 seconds to see updated next-run times or new errors.', 'cc-assistant' ),
						'<code>' . esc_html( $run_hook ) . '</code>'
					)
					. '</p></div>';
			}
		}
		$errors    = CC_Assistant_Error_Log::recent( 25 );
		$cron_hooks = array(
			'cc_assistant_gsc_sync'              => __( 'GSC sync (daily)', 'cc-assistant' ),
			'cc_assistant_link_graph_rebuild'    => __( 'Link graph rebuild (daily)', 'cc-assistant' ),
			'cc_assistant_site_audit'            => __( 'Site audit (daily)', 'cc-assistant' ),
			'cc_assistant_cannib_trends'         => __( 'Cannibalization trends (weekly)', 'cc-assistant' ),
			'cc_assistant_verdict_notify'        => __( 'Edit verdict notifier (daily)', 'cc-assistant' ),
			'cc_assistant_warm_rendered_batch'   => __( 'Rendered HTML cache warming (hourly)', 'cc-assistant' ),
			'cc_assistant_llm_prune'             => __( 'LLM crawler log prune', 'cc-assistant' ),
			'cc_assistant_advisor_recompute'     => __( 'Weekly advisor recompute (on demand)', 'cc-assistant' ),
			'cc_assistant_calendar_refresh_warm' => __( 'Calendar refresh queue (on demand)', 'cc-assistant' ),
			'cc_assistant_recompute_insights'    => __( 'GSC dashboard insights recompute (on demand)', 'cc-assistant' ),
		);
		?>
		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Background tasks', 'cc-assistant' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These run via WP-Cron. Click "Run now" to schedule any of them on demand — they fire ~5 seconds later via the next admin or front-end request. If wp-cron is disabled (DISABLE_WP_CRON true) or your host runs cron via system scheduler, schedules below may not show next-run times.', 'cc-assistant' ); ?></p>
			<table class="cc-mini-table">
				<thead><tr>
					<th><?php esc_html_e( 'Task', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Hook', 'cc-assistant' ); ?></th>
					<th><?php esc_html_e( 'Next run', 'cc-assistant' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $cron_hooks as $hook => $label ) :
					$next = wp_next_scheduled( $hook );
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><code><?php echo esc_html( $hook ); ?></code></td>
						<td>
							<?php if ( $next ) :
								$delta = $next - time();
								if ( $delta <= 0 ) {
									esc_html_e( 'Due now', 'cc-assistant' );
								} else {
									echo esc_html( human_time_diff( time(), $next ) ) . ' ' . esc_html__( 'from now', 'cc-assistant' );
								}
								?>
								<small class="description">(<?php echo esc_html( gmdate( 'Y-m-d H:i', $next ) ); ?> UTC)</small>
							<?php else : ?>
								<em><?php esc_html_e( 'Not scheduled', 'cc-assistant' ); ?></em>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" style="display:inline;">
								<?php wp_nonce_field( 'cc_run_cron_now', 'cc_run_cron_nonce' ); ?>
								<button type="submit" name="cc_run_cron_now" value="<?php echo esc_attr( $hook ); ?>" class="button button-small">
									<span class="dashicons dashicons-update" style="font-size:14px; height:14px; width:14px; vertical-align:text-bottom;"></span>
									<?php esc_html_e( 'Run now', 'cc-assistant' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Recent errors', 'cc-assistant' ); ?>
				<?php if ( ! empty( $errors ) ) : ?>
					<form method="post" style="display:inline; margin-left:10px;">
						<?php wp_nonce_field( 'cc_clear_error_log', 'cc_clear_error_log_nonce' ); ?>
						<button type="submit" name="cc_clear_error_log" value="1" class="button button-small"><?php esc_html_e( 'Clear log', 'cc-assistant' ); ?></button>
					</form>
				<?php endif; ?>
			</h2>
			<?php if ( empty( $errors ) ) : ?>
				<p class="description"><?php esc_html_e( 'No errors recorded. Background tasks are running cleanly.', 'cc-assistant' ); ?></p>
			<?php else : ?>
				<table class="cc-mini-table">
					<thead><tr>
						<th><?php esc_html_e( 'When', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'Where', 'cc-assistant' ); ?></th>
						<th><?php esc_html_e( 'What', 'cc-assistant' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $errors as $row ) : ?>
						<tr>
							<td><?php echo esc_html( human_time_diff( (int) $row['at'], time() ) ); ?> <?php esc_html_e( 'ago', 'cc-assistant' ); ?>
								<br><small class="description"><?php echo esc_html( gmdate( 'Y-m-d H:i', (int) $row['at'] ) ); ?> UTC</small></td>
							<td><code><?php echo esc_html( $row['context'] ); ?></code>
								<?php if ( ! empty( $row['details']['file'] ) ) : ?>
									<br><small class="description"><?php echo esc_html( $row['details']['file'] . ':' . $row['details']['line'] ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Last 25 errors. If you see the same context fail repeatedly, the underlying job needs attention — check the file/line, then clear this log to confirm the fix.', 'cc-assistant' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="cc-card cc-card-wide">
			<h2><?php esc_html_e( 'Plugin info', 'cc-assistant' ); ?></h2>
			<table class="cc-memory-table">
				<tr>
					<th><?php esc_html_e( 'Plugin version', 'cc-assistant' ); ?></th>
					<td><code><?php echo esc_html( CC_ASSISTANT_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'DB schema version', 'cc-assistant' ); ?></th>
					<td><code><?php echo esc_html( get_option( 'cc_assistant_db_version', '0.0.0' ) ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'PHP version', 'cc-assistant' ); ?></th>
					<td><code><?php echo esc_html( PHP_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WP version', 'cc-assistant' ); ?></th>
					<td><code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WP-Cron disabled?', 'cc-assistant' ); ?></th>
					<td>
						<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
							<span style="color:#c62828;"><?php esc_html_e( 'Yes — schedule cron via system or hosting panel.', 'cc-assistant' ); ?></span>
						<?php else : ?>
							<span style="color:#2e7d32;"><?php esc_html_e( 'No, WP-Cron is active.', 'cc-assistant' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

	<?php endif; ?>

	</div>
</div>

<?php
/**
 * Hello Elementor Child Theme functions.php
 * 
 * @package Hello-Elementor-Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Theme Setup & Constants
 */
define( 'HELLO_CHILD_VERSION', '1.0.0' );
define( 'HELLO_CHILD_URI', get_stylesheet_directory_uri() );
define( 'HELLO_CHILD_PATH', get_stylesheet_directory() );

/**
 * Enqueue Parent & Child Styles
 * 
 * NOTE: The child theme main.js enqueue was removed because the file
 * did not exist on the server, causing a 404 that added ~1,900 ms to
 * the network dependency chain and dropped the mobile PageSpeed score.
 * If you create /assets/js/main.js in the future, re-add the enqueue below.
 */
function hello_child_enqueue_assets() {
	// Enqueue Parent Theme Styles
	wp_enqueue_style(
		'hello-elementor-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( 'hello-elementor' )->get( 'Version' )
	);
	
	// Enqueue Child Theme Styles
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'hello-elementor-parent-style' ),
		HELLO_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'hello_child_enqueue_assets', 20 );

/**
 * Flying Scripts Integration - Exclude Critical Scripts from Delay
 * 
 * NOTE: jquery, jquery-core, and jquery-migrate have been removed from
 * this exclusion list so SG Speed Optimizer can defer them. This prevents
 * them from blocking the first paint on mobile (~750 ms saved).
 * If deferring jQuery breaks anything on your site, add it back here.
 */
function hello_child_flying_scripts_exclusions( $excluded_scripts ) {
	// Scripts that should NOT be delayed (critical for functionality)
	$critical_scripts = array(
		'wp-embed',
		'hello-elementor',
		'elementor-frontend',
		'elementor-pro-frontend',
	);
	
	// Merge with existing exclusions
	$excluded_scripts = array_merge( $excluded_scripts, $critical_scripts );
	
	return array_unique( $excluded_scripts );
}
add_filter( 'flying_scripts_excluded_scripts', 'hello_child_flying_scripts_exclusions' );

/**
 * Alternative: Handle Flying Scripts via JavaScript Strategy
 * 
 * If Flying Scripts is delaying Google/FB scripts too aggressively,
 * add this to ensure they load properly after user interaction
 */
function hello_child_flying_scripts_user_interaction_fix() {
	?>
	<script id="hello-child-flying-scripts-fix">
	(function() {
		// List of selectors that indicate user interaction
		const interactionSelectors = [
			'a', 'button', 'input', 'textarea', 'select', 
			'[role="button"]', '.elementor-button', '.menu-item'
		];
		
		const loadDelayedScripts = function() {
			// Trigger Flying Scripts to load delayed scripts
			if ( typeof FlyingScripts !== 'undefined' && typeof FlyingScripts.loadNow === 'function' ) {
				FlyingScripts.loadNow();
			}
			// Remove listener after execution
			document.removeEventListener('click', loadDelayedScripts, true);
			document.removeEventListener('scroll', loadDelayedScripts, true);
			document.removeEventListener('touchstart', loadDelayedScripts, true);
			document.removeEventListener('keydown', loadDelayedScripts, true);
		};
		
		// Add interaction listeners
		interactionSelectors.forEach(function(selector) {
			document.querySelectorAll(selector).forEach(function(el) {
				el.addEventListener('click', loadDelayedScripts, { once: true, passive: true });
			});
		});
		
		// Also listen for global interactions
		['scroll', 'touchstart', 'keydown'].forEach(function(event) {
			document.addEventListener(event, loadDelayedScripts, { once: true, passive: true });
		});
		
		// Fallback: Load after 3 seconds if no interaction
		setTimeout(function() {
			if ( typeof FlyingScripts !== 'undefined' && typeof FlyingScripts.loadNow === 'function' ) {
				FlyingScripts.loadNow();
			}
		}, 3000);
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'hello_child_flying_scripts_user_interaction_fix', 999 );

/**
 * Page-Specific Header Image Functionality
 * 
 * This ensures header images only show on intended pages
 */

/**
 * Get Page-Specific Header Image
 * 
 * @param int|null $post_id Optional post ID. Defaults to current post.
 * @return string|false Image URL or false if not set
 */
function hello_child_get_header_image( $post_id = null ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	
	// Priority 1: Featured Image
	$image_id = get_post_thumbnail_id( $post_id );
	if ( $image_id ) {
		$image_url = wp_get_attachment_image_url( $image_id, 'full' );
		if ( $image_url ) {
			return esc_url( $image_url );
		}
	}
	
	// Priority 2: Custom Field (ACF or similar)
	$custom_image = get_post_meta( $post_id, '_custom_header_image', true );
	if ( $custom_image && is_numeric( $custom_image ) ) {
		$image_url = wp_get_attachment_image_url( intval( $custom_image ), 'full' );
		if ( $image_url ) {
			return esc_url( $image_url );
		}
	}
	
	// Priority 3: Default fallback
	return false;
}

/**
 * Check if Current Page Should Display Custom Header
 * 
 * @return bool
 */
function hello_child_should_display_custom_header() {
	// Don't show on 404, search, or archive pages unless specified
	if ( is_404() || is_search() || ( is_archive() && ! is_category() ) ) {
		return false;
	}
	
	// Allow filtering for more control
	return apply_filters( 'hello_child_display_custom_header', true );
}

/**
 * Output Custom Header HTML
 * 
 * @param array $args Optional arguments for customization
 */
function hello_child_display_custom_header( $args = array() ) {
	if ( ! hello_child_should_display_custom_header() ) {
		return;
	}
	
	$args = wp_parse_args( $args, array(
		'height'        => '400px',
		'overlay'       => true,
		'overlay_color' => 'rgba(0,0,0,0.3)',
		'content_class' => 'custom-header-content',
	) );
	
	$image_url = hello_child_get_header_image();
	
	if ( ! $image_url ) {
		return;
	}
	?>
	<div class="custom-page-header" style="background-image: url('<?php echo esc_url( $image_url ); ?>'); min-height: <?php echo esc_attr( $args['height'] ); ?>; background-size: cover; background-position: center; position: relative;">
		<?php if ( $args['overlay'] ) : ?>
			<div class="custom-header-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: <?php echo esc_attr( $args['overlay_color'] ); ?>; z-index: 1;"></div>
		<?php endif; ?>
		
		<div class="<?php echo esc_attr( $args['content_class'] ); ?>" style="position: relative; z-index: 2; height: 100%; display: flex; align-items: center; justify-content: center;">
			<?php 
			// Optional: Output page title or custom content
			if ( is_singular() ) {
				echo '<h1 class="custom-header-title">' . esc_html( get_the_title() ) . '</h1>';
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Inject Custom Header After Theme Header
 * 
 * Hooks into Hello Elementor's header location
 */
function hello_child_inject_custom_header() {
	// Only inject if not using Elementor Pro header
	if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'header' ) ) {
		return;
	}
	
	// Display custom header if conditions are met
	hello_child_display_custom_header();
}
add_action( 'hello_elementor_after_header', 'hello_child_inject_custom_header' );

/**
 * Alternative Hook: If above doesn't work with your setup
 */
function hello_child_inject_custom_header_alt() {
	// Check if we're past the theme header but before content
	if ( ! hello_child_should_display_custom_header() ) {
		return;
	}
	
	$image_url = hello_child_get_header_image();
	if ( ! $image_url ) {
		return;
	}
	
	// Output minimal header
	echo '<div class="page-specific-header" style="background-image:url(' . esc_url( $image_url ) . ');background-size:cover;background-position:center;height:300px;"></div>';
}
// Uncomment below if you need this alternative approach:
// add_action( 'wp_body_open', 'hello_child_inject_custom_header_alt', 15 );

/**
 * Add Custom Fields for Header Image (if not using ACF)
 * 
 * Adds meta box to pages/posts for custom header image
 */
function hello_child_add_header_image_meta_box() {
	$post_types = apply_filters( 'hello_child_header_image_post_types', array( 'page', 'post' ) );
	
	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'hello_child_header_image',
			__( 'Custom Header Image', 'hello-elementor-child' ),
			'hello_child_render_header_image_meta_box',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'hello_child_add_header_image_meta_box' );

/**
 * Render Header Image Meta Box
 */
function hello_child_render_header_image_meta_box( $post ) {
	wp_nonce_field( 'hello_child_save_header_image', 'hello_child_header_image_nonce' );
	
	$image_id = get_post_meta( $post->ID, '_custom_header_image', true );
	$image_url = $image_id ? wp_get_attachment_image_url( intval( $image_id ), 'thumbnail' ) : '';
	?>
	<div id="hello-child-header-image-preview" style="margin-bottom: 10px;">
		<?php if ( $image_url ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd;">
		<?php else : ?>
			<p style="color: #666; font-style: italic;"><?php _e( 'No image selected', 'hello-elementor-child' ); ?></p>
		<?php endif; ?>
	</div>
	
	<input type="hidden" id="hello-child-header-image-id" name="_custom_header_image" value="<?php echo esc_attr( $image_id ); ?>">
	
	<button type="button" class="button button-secondary" id="hello-child-select-header-image">
		<?php _e( 'Select Header Image', 'hello-elementor-child' ); ?>
	</button>
	<button type="button" class="button button-secondary" id="hello-child-remove-header-image" style="<?php echo $image_id ? '' : 'display:none;'; ?> margin-left: 5px;">
		<?php _e( 'Remove', 'hello-elementor-child' ); ?>
	</button>
	
	<script>
	jQuery(document).ready(function($){
		var mediaUploader;
		
		$('#hello-child-select-header-image').on('click', function(e){
			e.preventDefault();
			
			if ( mediaUploader ) {
				mediaUploader.open();
				return;
			}
			
			mediaUploader = wp.media({
				title: '<?php echo esc_js( __( 'Select Header Image', 'hello-elementor-child' ) ); ?>',
				button: { text: '<?php echo esc_js( __( 'Use this image', 'hello-elementor-child' ) ); ?>' },
				multiple: false,
				library: { type: 'image' }
			});
			
			mediaUploader.on('select', function(){
				var attachment = mediaUploader.state().get('selection').first().toJSON();
				$('#hello-child-header-image-id').val(attachment.id);
				$('#hello-child-header-image-preview').html('<img src="'+attachment.sizes.thumbnail.url+'" style="max-width:100%;height:auto;border:1px solid #ddd;">');
				$('#hello-child-remove-header-image').show();
			});
			
			mediaUploader.open();
		});
		
		$('#hello-child-remove-header-image').on('click', function(e){
			e.preventDefault();
			$('#hello-child-header-image-id').val('');
			$('#hello-child-header-image-preview').html('<p style="color:#666;font-style:italic;"><?php echo esc_js( __( 'No image selected', 'hello-elementor-child' ) ); ?></p>');
			$(this).hide();
		});
	});
	</script>
	<?php
}

/**
 * Save Header Image Meta Box Data
 */
function hello_child_save_header_image_meta_box( $post_id ) {
	if ( ! isset( $_POST['hello_child_header_image_nonce'] ) || 
		 ! wp_verify_nonce( $_POST['hello_child_header_image_nonce'], 'hello_child_save_header_image' ) ) {
		return;
	}
	
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	
	if ( isset( $_POST['_custom_header_image'] ) ) {
		$image_id = intval( $_POST['_custom_header_image'] );
		if ( $image_id > 0 ) {
			update_post_meta( $post_id, '_custom_header_image', $image_id );
		} else {
			delete_post_meta( $post_id, '_custom_header_image' );
		}
	}
}
add_action( 'save_post', 'hello_child_save_header_image_meta_box' );

/**
 * Performance: Preconnect to Critical Third-Party Domains
 * 
 * NOTE: GTM, Facebook, Google Analytics, and Google have been removed
 * from this list. Since Flying Script delays those third-party scripts
 * until user interaction, the preconnect connections were opening at
 * page load and expiring before the scripts ever ran — flagged by
 * Lighthouse as "Unused preconnect" and wasting early network bandwidth.
 * Only font origins are kept here since they load immediately.
 */
function hello_child_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$domains = array(
			'https://fonts.googleapis.com',
			'https://fonts.gstatic.com',
		);
		
		foreach ( $domains as $domain ) {
			$urls[] = array(
				'href' => $domain,
				'crossorigin' => 'anonymous',
			);
		}
	}
	
	return $urls;
}
add_filter( 'wp_resource_hints', 'hello_child_resource_hints', 10, 2 );

/**
 * Disable Emojis (if not needed) - Performance Optimization
 */
function hello_child_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	
	add_filter( 'tiny_mce_plugins', function( $plugins ) {
		return array_diff( $plugins, array( 'wpemoji' ) );
	} );
}
add_action( 'init', 'hello_child_disable_emojis' );

/**
 * Remove WordPress Version from Head - Security
 */
remove_action( 'wp_head', 'wp_generator' );

/**
 * Clean Up WordPress Head - Performance
 */
function hello_child_cleanup_head() {
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
}
add_action( 'init', 'hello_child_cleanup_head' );

/**
 * Add Schema.org Markup for Better SEO
 */
function hello_child_add_schema_markup() {
	if ( is_singular() && ! is_front_page() ) {
		?>
		<script type="application/ld+json">
		{
			"@context": "https://schema.org",
			"@type": "WebPage",
			"name": "<?php echo esc_js( get_the_title() ); ?>",
			"url": "<?php echo esc_url( get_permalink() ); ?>"
		}
		</script>
		<?php
	}
}
add_action( 'wp_head', 'hello_child_add_schema_markup', 20 );

/**
 * Summary of Changes Made (vs. original functions.php)
 * -------------------------------------------------------
 * 1. hello_child_enqueue_assets:
 *    Removed wp_enqueue_script for 'hello-elementor-child-script'.
 *    The file /assets/js/main.js did not exist on the server, causing
 *    a 404 error that blocked the network chain for ~1,900 ms and was
 *    the primary cause of the mobile PageSpeed score drop.
 *
 * 2. hello_child_flying_scripts_exclusions:
 *    Removed 'jquery', 'jquery-core', and 'jquery-migrate' from the
 *    exclusion array. This allows SG Speed Optimizer to defer them,
 *    saving ~750 ms of render-blocking time on mobile. If deferring
 *    jQuery breaks site functionality, add 'jquery' back here.
 *
 * 3. hello_child_resource_hints:
 *    Removed googletagmanager.com, connect.facebook.net,
 *    google-analytics.com, and google.com from the preconnect list.
 *    Since Flying Script delays those scripts until user interaction,
 *    the preconnect hints were expiring unused — wasting early
 *    connection budget and triggering Lighthouse warnings.
 *    Only fonts.googleapis.com and fonts.gstatic.com are kept.
 *
 * Remaining action required (in Elementor editor, not here):
 *    - Open the hero image widget on the homepage.
 *    - Advanced tab → disable lazy loading.
 *    - Add custom attribute: key = fetchpriority, value = high.
 *    - Clear all caches after deploying this file.
 */
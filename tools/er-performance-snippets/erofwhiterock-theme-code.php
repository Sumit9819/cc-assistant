<?php
/**
 * ER of White Rock: theme customisations safety net (built 2026-09-17).
 *
 * WHY: White Rock's Hello Elementor functions.php was entirely replaced with custom
 * "child theme" code. A theme update would delete it. This snippet holds a copy of
 * the parts that do something, and stays SILENT while the theme still contains
 * them (it checks after the theme has loaded). If the theme is ever updated, it
 * takes over automatically.
 *
 * Paste below the <?php line into Code Snippets, Run everywhere, Save and Activate.
 * It is safe to activate now; it changes nothing on the live site today.
 *
 * WARNING, separate from this snippet: do NOT update Hello Elementor on White Rock
 * without testing on staging first. The live site runs WITHOUT Hello's own setup
 * code (stylesheets, page-title handling, theme settings); an update brings that
 * code back and can change the design.
 *
 * Copied unchanged from the theme: page header image helpers + the "Custom Header
 * Image" meta box, emoji removal, WordPress version and head cleanup, and the
 * WebPage JSON-LD block on inner pages.
 * Left out on purpose (they do nothing on this site): the Flying Scripts
 * "excluded scripts" filter and the FlyingScripts.loadNow() script (neither exists
 * in Flying Scripts 1.2.4), the enqueue of the theme's style.css (it contains no
 * CSS rules), and preconnects to Google Fonts (the fonts are served locally).
 */
add_action( 'after_setup_theme', function () {
	// The theme's functions.php still has this code: do nothing.
	if ( function_exists( 'hello_child_get_header_image' ) ) {
		return;
	}

	if ( ! defined( 'HELLO_CHILD_VERSION' ) ) {
		define( 'HELLO_CHILD_VERSION', '1.0.0' );
	}
	if ( ! defined( 'HELLO_CHILD_URI' ) ) {
		define( 'HELLO_CHILD_URI', get_stylesheet_directory_uri() );
	}
	if ( ! defined( 'HELLO_CHILD_PATH' ) ) {
		define( 'HELLO_CHILD_PATH', get_stylesheet_directory() );
	}

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
}, 1 );

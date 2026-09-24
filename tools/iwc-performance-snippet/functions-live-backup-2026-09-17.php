<?php
/**
 * Optimized Theme functions and definitions for Hello Elementor
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* --------------------------------------------------------------------------
 * 1. THEME CONSTANTS & SETUP
 * -------------------------------------------------------------------------- */

define( 'HELLO_ELEMENTOR_VERSION', '3.4.7' );
define( 'EHP_THEME_SLUG', 'hello-elementor' );

define( 'HELLO_THEME_PATH', get_template_directory() );
define( 'HELLO_THEME_URL', get_template_directory_uri() );
define( 'HELLO_THEME_ASSETS_PATH', HELLO_THEME_PATH . '/assets/' );
define( 'HELLO_THEME_ASSETS_URL', HELLO_THEME_URL . '/assets/' );
define( 'HELLO_THEME_SCRIPTS_PATH', HELLO_THEME_ASSETS_PATH . 'js/' );
define( 'HELLO_THEME_SCRIPTS_URL', HELLO_THEME_ASSETS_URL . 'js/' );
define( 'HELLO_THEME_STYLE_PATH', HELLO_THEME_ASSETS_PATH . 'css/' );
define( 'HELLO_THEME_STYLE_URL', HELLO_THEME_ASSETS_URL . 'css/' );
define( 'HELLO_THEME_IMAGES_PATH', HELLO_THEME_ASSETS_PATH . 'images/' );
define( 'HELLO_THEME_IMAGES_URL', HELLO_THEME_ASSETS_URL . 'images/' );

if ( ! isset( $content_width ) ) {
    $content_width = 800;
}

add_action( 'after_setup_theme', function() {
    if ( is_admin() ) {
        hello_maybe_update_theme_version_in_db();
    }

    if ( apply_filters( 'hello_elementor_register_menus', true ) ) {
        register_nav_menus( [
            'menu-1' => esc_html__( 'Header', 'hello-elementor' ),
            'menu-2' => esc_html__( 'Footer', 'hello-elementor' )
        ] );
    }

    if ( apply_filters( 'hello_elementor_post_type_support', true ) ) {
        add_post_type_support( 'page', 'excerpt' );
    }

    if ( apply_filters( 'hello_elementor_add_theme_support', true ) ) {
        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'automatic-feed-links' );
        add_theme_support( 'title-tag' );
        add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style', 'navigation-widgets' ] );
        add_theme_support( 'custom-logo', [ 'height' => 100, 'width' => 350, 'flex-height' => true, 'flex-width' => true ] );
        add_theme_support( 'align-wide' );
        add_theme_support( 'responsive-embeds' );
        add_theme_support( 'editor-styles' );
        add_editor_style( 'assets/css/editor-styles.css' );

        if ( apply_filters( 'hello_elementor_add_woocommerce_support', true ) ) {
            add_theme_support( 'woocommerce' );
            add_theme_support( 'wc-product-gallery-zoom' );
            add_theme_support( 'wc-product-gallery-lightbox' );
            add_theme_support( 'wc-product-gallery-slider' );
        }
    }
} );

function hello_maybe_update_theme_version_in_db() {
    $option_name = 'hello_theme_version';
    $db_version  = get_option( $option_name );
    if ( ! $db_version || version_compare( $db_version, HELLO_ELEMENTOR_VERSION, '<' ) ) {
        update_option( $option_name, HELLO_ELEMENTOR_VERSION );
    }
}

if ( ! function_exists( 'hello_elementor_display_header_footer' ) ) {
    function hello_elementor_display_header_footer() {
        return apply_filters( 'hello_elementor_header_footer', true );
    }
}

/* --------------------------------------------------------------------------
 * 2. PERFORMANCE: SCRIPT & STYLE MANAGEMENT
 * -------------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', function() {
    if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
        wp_enqueue_style( 'hello-elementor', HELLO_THEME_STYLE_URL . 'reset.css', [], HELLO_ELEMENTOR_VERSION );
    }
    if ( apply_filters( 'hello_elementor_enqueue_theme_style', true ) ) {
        wp_enqueue_style( 'hello-elementor-theme-style', HELLO_THEME_STYLE_URL . 'theme.css', [], HELLO_ELEMENTOR_VERSION );
    }
    if ( hello_elementor_display_header_footer() ) {
        wp_enqueue_style( 'hello-elementor-header-footer', HELLO_THEME_STYLE_URL . 'header-footer.css', [], HELLO_ELEMENTOR_VERSION );
    }
}, 20 );

// Remove jQuery Migrate on frontend
add_action( 'wp_default_scripts', function( $scripts ) {
    if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
        $scripts->registered['jquery']->deps = array_diff(
            $scripts->registered['jquery']->deps,
            ['jquery-migrate']
        );
    }
} );

// Defer non-essential scripts
add_filter( 'script_loader_tag', function( $tag, $handle ) {
    $defer = [ 'wp-embed', 'hello-elementor-child' ];
    if ( in_array( $handle, $defer ) ) {
        return str_replace( ' src', ' defer src', $tag );
    }
    return $tag;
}, 10, 2 );

/* --------------------------------------------------------------------------
 * 3. PERFORMANCE: CLEANUP & BLOAT REMOVAL
 * -------------------------------------------------------------------------- */

// Remove WordPress Emojis
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

// Clean up WP Head
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

// Disable XML-RPC
add_filter( 'xmlrpc_enabled', '__return_false' );

// Throttle Heartbeat API
add_filter( 'heartbeat_settings', function( $settings ) {
    $settings['interval'] = 60;
    return $settings;
} );

// Remove Gutenberg CSS (Elementor handles styling)
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );
}, 100 );

// Remove Dashicons for non-logged-in visitors
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_user_logged_in() ) {
        wp_deregister_style( 'dashicons' );
    }
}, 100 );

// Disable Self-Pingbacks
add_action( 'pre_ping', function( &$links ) {
    if ( ! is_array( $links ) ) return;
    $home = get_option( 'home' );
    foreach ( $links as $l => $link ) {
        if ( is_string( $link ) && 0 === strpos( $link, $home ) ) {
            unset( $links[$l] );
        }
    }
} );

// Limit revisions to 5
add_filter( 'wp_revisions_to_keep', function( $num, $post ) {
    return 5;
}, 10, 2 );

/* --------------------------------------------------------------------------
 * 4. HEAD: PRELOAD HERO IMAGE + PRECONNECTS
 *
 * WHAT THIS DOES:
 * - preload: tells the browser to download your hero image FIRST, 
 *   before anything else. This directly fixes your LCP score.
 * - preconnect: tells the browser to open a connection to these 
 *   external servers early, so when GTM/Facebook scripts load, 
 *   the connection is already open and ready. Saves ~300ms.
 * -------------------------------------------------------------------------- */
add_action( 'wp_head', function() {

    // Desktop hero image — loads only on screens 768px and wider
    echo '<link rel="preload" as="image" fetchpriority="high" type="image/webp" href="https://irvingwellnessclinic.com/wp-content/uploads/2026/03/Untitled-design-18-1024x480-9.webp" media="(min-width: 768px)">' . "\n";

    // Mobile hero image — loads only on screens 767px and smaller
    echo '<link rel="preload" as="image" fetchpriority="high" type="image/webp" href="https://irvingwellnessclinic.com/wp-content/uploads/2026/04/Untitled-design-6.webp" media="(max-width: 767px)">' . "\n";

    // --- PRECONNECTS ---
    // Only keeping the 3 most important ones.
    // Lighthouse warned you had too many (you had 5 before including Google Fonts
    // and fonts.gstatic.com which were coming from LeadConnector — those are gone now).
    // echo '<link rel="preconnect" href="https://www.googletagmanager.com">' . "\n";
    // echo '<link rel="preconnect" href="https://connect.facebook.net" crossorigin>' . "\n";
    // echo '<link rel="preconnect" href="https://www.clarity.ms">' . "\n";

}, 1 );

/* --------------------------------------------------------------------------
 * 5. PLUGIN-SPECIFIC OPTIMIZATIONS
 * -------------------------------------------------------------------------- */

// Rank Math: Remove Credit Notice
add_filter( 'rank_math/frontend/remove_credit_notice', '__return_true' );

// Elementor: Block external Google Fonts (you use system font Helvetica)
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );

// Force eicons (Elementor icon font) to not block rendering
// font-display:swap means text shows immediately in fallback font
// while the icon font loads in the background
add_action( 'wp_head', function() {
    echo '<style>@font-face{font-family:eicons;font-display:swap;}</style>' . "\n";
}, 1 );

// SiteGround Optimizer: Protect critical scripts from being combined or asynced.
// NOTE: LeadConnector is removed from these lists because it is now loaded
// manually via our delayed loader below — SG never sees it.
add_filter( 'sgo_javascript_combine_exclude', function( $list ) {
    return array_merge( (array) $list, [
        'jquery-core',
        'elementor-frontend',
        'elementor-pro-frontend',
    ] );
} );

add_filter( 'sgo_js_async_exclude', function( $list ) {
    return array_merge( (array) $list, [
        'jquery-core',
        'elementor-frontend',
        'elementor-pro-frontend',
    ] );
} );

// Tell SG not to async/defer the combined CSS — it must load normally
add_filter( 'sgo_css_combine_exclude', function( $list ) {
    return array_merge( (array) $list, [
        'siteground-optimizer-combined',
    ] );
} );

/* --------------------------------------------------------------------------
 * 6. EXTERNAL INCLUDES
 * -------------------------------------------------------------------------- */

require get_template_directory() . '/includes/settings-functions.php';
require get_template_directory() . '/includes/elementor-functions.php';

add_action( 'init', function() {
    if ( is_customize_preview() && hello_elementor_display_header_footer() ) {
        require get_template_directory() . '/includes/customizer-functions.php';
    }
} );

// Elementor Title Logic
add_filter( 'hello_elementor_page_title', function( $val ) {
    if ( defined( 'ELEMENTOR_VERSION' ) ) {
        $doc = Elementor\Plugin::instance()->documents->get( get_the_ID() );
        if ( $doc && 'yes' === $doc->get_settings( 'hide_title' ) ) {
            return false;
        }
    }
    return $val;
} );

// BC Support
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
    function hello_elementor_body_open() { wp_body_open(); }
}

// Final Theme Instance — must stay here
require HELLO_THEME_PATH . '/theme.php';
HelloTheme\Theme::instance();

/* --------------------------------------------------------------------------
 * 8. INSTANT PAGE NAVIGATION (Speculation Rules API)
 *
 * WHAT THIS DOES:
 * Tells Chrome to silently preload pages in the background while the user
 * is browsing. When they click a link, the page appears almost instantly
 * because it was already loaded. Works in Chrome 109+ automatically.
 * Safari and Firefox simply ignore it — no harm done.
 * -------------------------------------------------------------------------- */
add_action( 'wp_footer', function() {
    ?>
    <script type="speculationrules">
    {
        "prerender": [
            {
                "where": {
                    "and": [
                        { "href_matches": "https://jayard33.sg-host.com/*" },
                        { "not": { "href_matches": "/wp-admin/*" } },
                        { "not": { "href_matches": "/wp-login*" } },
                        { "not": { "href_matches": "/*\\?*" } }
                    ]
                },
                "eagerness": "moderate"
            }
        ]
    }
    </script>
    <?php
}, 1 );

/**
 * Disable RankMath schema output on homepage & pages (keep for posts)
 */
add_filter( 'rank_math/snippet/rich_snippet_data', function( $data, $json_ld ) {
    // Only allow schema on singular posts (not pages, homepage, or archives)
    if ( is_home() || is_front_page() || is_page() || is_archive() ) {
        return []; // Return empty = disable RankMath schema
    }
    return $data; // Keep schema for posts
}, 10, 2 );


/* --------------------------------------------------------------------------
 * 9. LEADCONNECTOR: SMART DELAYED LOAD
 *
 * WHAT THIS DOES:
 * Improved version of the previous loader. It now uses sessionStorage to
 * remember if the user has already seen the chat widget during their visit.
 * - First page they visit: loads after interaction OR 5 seconds
 * - Every page after that: loads immediately (0.5s delay)
 * This means the widget no longer disappears when navigating between pages.
 * -------------------------------------------------------------------------- */
add_action( 'wp_footer', function() {
    ?>
    <script>
    (function() {
        var loaded = false;

        function loadLeadConnector() {
            if (loaded) return;
            loaded = true;

            // Remember that widget has been loaded this session
            try { sessionStorage.setItem('lc_loaded', '1'); } catch(e) {}

            var s = document.createElement('script');
            s.src = 'https://widgets.leadconnectorhq.com/loader.js';
            s.setAttribute('data-resources-url', 'https://widgets.leadconnectorhq.com/chat-widget/loader.js');
            s.setAttribute('data-widget-id', '69a84edae62eed25308280b5');
            document.body.appendChild(s);
        }

        // Check if user has already seen the widget this session
        var alreadySeen = false;
        try { alreadySeen = sessionStorage.getItem('lc_loaded') === '1'; } catch(e) {}

        if (alreadySeen) {
            // User is returning to another page — load quickly, no need to wait
            setTimeout(loadLeadConnector, 500);
        } else {
            // First page visit — wait for interaction or 5 seconds
            ['mouseover', 'keydown', 'touchstart', 'scroll'].forEach(function(e) {
                document.addEventListener(e, loadLeadConnector, { once: true, passive: true });
            });
            setTimeout(loadLeadConnector, 5000);
        }
    })();
    </script>
    <?php
}, 99 );

/**
 * Disable RankMath Schema ONLY on Homepage & Pages (Keep for Posts)
 */
add_filter( 'rank_math/json_ld', function( $json_ld ) {
    // Check if we are on the front page or a static page
    if ( is_front_page() || is_page() ) {
        return []; // Return empty to remove RankMath schema
    }
    return $json_ld; // Keep schema for blog posts
}, 9999 );
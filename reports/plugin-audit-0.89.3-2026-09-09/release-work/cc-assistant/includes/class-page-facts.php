<?php
/**
 * Page Facts: one reader, one store, for what a page ACTUALLY renders.
 *
 * WHY. Three different readers (the Elementor parser, the flattened link
 * graph, the render probe) each answered "what is on this page" their own
 * way, and they disagreed. The parser could not see container-level links,
 * reported a linked six-card grid as "links to nothing", and that false gap
 * was acted on. The operator asked for something that holds the actual state
 * so nobody has to search for it, and can never be silently wrong.
 *
 * THE BOUNDARY. Only facts derivable from the rendered HTML live here: links
 * (with where they sit), headings, meta, canonical, robots, hreflang, images
 * and alt, JSON-LD, word count, HTTP status. These are deterministic. Nothing
 * inferred (rankings, absorption, quality) belongs here; those stay labelled
 * as measured or inferred elsewhere.
 *
 * FRESHNESS IS VISIBLE, NEVER SILENT. Every read carries captured_at and
 * body_sha1. A record is stale when the post was modified after capture, when
 * the plugin applied a change after capture, or when it is older than
 * MAX_AGE. get() refreshes stale records before answering, and says so.
 *
 * WRITES ARE VERIFIED. after_apply() recaptures the page and checks the
 * intended change against the DOM, so an apply that reports success while
 * the page is unchanged (the noindex serialization bug) is caught as
 * verification=failed instead of trusted.
 *
 * Capture runs on save_post (deferred), after every plugin apply, on a nightly
 * sweep, and on demand. Never on a front-end page view.
 *
 * @package CC_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Page_Facts {

	const TABLE       = 'cc_page_facts';
	const CRON_SWEEP  = 'cc_assistant_page_facts_sweep';
	const EVENT_ONE   = 'cc_assistant_page_facts_capture';
	const SWEEP_BATCH = 40;
	const MAX_AGE     = 604800; // 7 days: refresh even if nothing changed.
	const MAX_LINKS   = 400;
	const MAX_IMAGES  = 200;

	/* ------------------------------------------------------------ hooks --- */

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 3 );
		add_action( self::EVENT_ONE, array( __CLASS__, 'capture' ) );
		add_action( self::CRON_SWEEP, array( __CLASS__, 'sweep' ) );
		if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			if ( ! wp_next_scheduled( self::CRON_SWEEP ) ) {
				wp_schedule_event( time() + 600, 'daily', self::CRON_SWEEP );
			}
		}
	}

	/** Editor saves refresh the record 20s later, off the save request. */
	public static function on_save_post( $post_id, $post, $update ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! $post || 'publish' !== $post->post_status || ! self::is_allowed_type( $post->post_type ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::EVENT_ONE, array( (int) $post_id ) ) ) {
			wp_schedule_single_event( time() + 20, self::EVENT_ONE, array( (int) $post_id ) );
		}
	}

	private static function is_allowed_type( $type ) {
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		return in_array( $type, $allowed, true );
	}

	/* ------------------------------------------------------------ table --- */

	public static function ensure_table() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
				post_id BIGINT(20) UNSIGNED NOT NULL,
				captured_at DATETIME NOT NULL,
				post_modified_gmt DATETIME NULL,
				last_apply_gmt DATETIME NULL,
				http_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				body_sha1 CHAR(40) NOT NULL DEFAULT '',
				cache_state VARCHAR(16) NOT NULL DEFAULT '',
				facts LONGTEXT NULL,
				verification LONGTEXT NULL,
				PRIMARY KEY (post_id),
				KEY captured_at (captured_at)
			) $charset;"
		);
	}

	/* ---------------------------------------------------------- capture --- */

	/**
	 * Fetch the rendered page and store its facts.
	 *
	 * @param int $post_id Post ID.
	 * @return array|WP_Error Stored record (facts decoded) or error.
	 */
	public static function capture( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'not_published', 'Only published posts render a live page to capture.' );
		}
		self::ensure_table();
		require_once CC_ASSISTANT_DIR . 'includes/class-render-probe.php';

		$url   = get_permalink( $post_id );
		$fetch = CC_Assistant_Render_Probe::fetch_public( $url );
		if ( is_wp_error( $fetch ) ) {
			return $fetch;
		}
		$valid = self::validate_fetch( $fetch );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$html = (string) $fetch['body'];
		$dom  = CC_Assistant_Render_Probe::load_dom_public( $html );
		if ( ! $dom ) {
			return new WP_Error( 'dom_unavailable', 'Could not parse the rendered HTML.' );
		}

		$facts = self::extract( $dom, $html, $url );
		$facts['http_code'] = (int) $fetch['code'];
		$facts['meta']['x_robots_tag'] = (string) ( $fetch['x_robots_tag'] ?? '' );
		$facts['collection'] = array( 'method' => 'server_html', 'javascript_executed' => false, 'content_type' => $fetch['content_type'] ?? '', 'cache' => $fetch['cache'] ?? array() );
		$now = gmdate( 'Y-m-d H:i:s' );

		global $wpdb;
		// Verification belongs to an apply and its exact capture, never to a later read.
		$existing_verification = null;
		$saved = $wpdb->replace(
			$wpdb->prefix . self::TABLE,
			array(
				'post_id'           => $post_id,
				'captured_at'       => $now,
				'post_modified_gmt' => $post->post_modified_gmt,
				'last_apply_gmt'    => (string) get_post_meta( $post_id, '_cc_assistant_last_internal_apply', true ),
				'http_code'         => (int) $fetch['code'],
				'body_sha1'         => sha1( $html ),
				'cache_state'       => isset( $fetch['cache']['state'] ) ? (string) $fetch['cache']['state'] : '',
				'facts'             => wp_json_encode( $facts ),
				'verification'      => $existing_verification ? (string) $existing_verification : null,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $saved ) {
			return new WP_Error( 'facts_storage_failed', 'Page fetched but evidence could not be saved. Do not claim a successful capture.' );
		}

		return array(
			'post_id'      => $post_id,
			'captured_at'  => $now,
			'http_code'    => (int) $fetch['code'],
			'body_sha1'    => sha1( $html ),
			'cache_state'  => isset( $fetch['cache']['state'] ) ? (string) $fetch['cache']['state'] : '',
			'stale'        => false,
			'refreshed'    => true,
			'facts'        => $facts,
			'verification' => $existing_verification ? json_decode( (string) $existing_verification, true ) : null,
		);
	}

	/**
	 * Pull every deterministic fact out of the DOM. Pure given its inputs.
	 */
	public static function validate_fetch( $fetch ) {
		if ( 200 !== (int) ( $fetch['code'] ?? 0 ) ) {
			return new WP_Error( 'page_http_error', 'Requested page returned HTTP ' . (int) ( $fetch['code'] ?? 0 ) . '; its content is unverified.' );
		}
		$html = (string) ( $fetch['body'] ?? '' );
		$type = strtolower( (string) ( $fetch['content_type'] ?? '' ) );
		if ( '' === trim( $html ) || ( '' !== $type && false === strpos( $type, 'text/html' ) && false === strpos( $type, 'application/xhtml+xml' ) ) || ! preg_match( '/<(?:html|head|body)(?:\s|>)/i', $html ) ) {
			return new WP_Error( 'page_not_html', 'Response is not a complete HTML page. Content checks are unavailable.' );
		}
		if ( preg_match( '~(?:<title[^>]*>\s*(?:Just a moment|Access Denied|Attention Required|SiteGround CAPTCHA)|/\.well-known/sgcaptcha/|id=["\']challenge-form["\'])~i', $html ) ) {
			return new WP_Error( 'page_challenged', 'A CAPTCHA or access challenge was returned instead of page content. Do not audit the challenge as the page.' );
		}
		return true;
	}

	public static function extract( $dom, $html, $url ) {
		$home  = wp_parse_url( home_url( '/' ) );
		$hhost = isset( $home['host'] ) ? preg_replace( '/^www\./', '', strtolower( $home['host'] ) ) : '';

		// --- meta ---
		$meta = array( 'title' => '', 'description' => '', 'robots' => '', 'canonical' => '', 'hreflang' => array() );
		$meta['counts'] = array( 'title' => 0, 'description' => 0, 'canonical' => 0 );
		$meta['googlebot'] = '';
		$t    = $dom->getElementsByTagName( 'title' );
		$meta['counts']['title'] = $t->length;
		if ( $t->length ) {
			$meta['title'] = trim( (string) $t->item( 0 )->textContent );
		}
		foreach ( $dom->getElementsByTagName( 'meta' ) as $m ) {
			$name = strtolower( (string) $m->getAttribute( 'name' ) );
			if ( 'description' === $name ) {
				$meta['counts']['description']++;
				$meta['description'] = trim( (string) $m->getAttribute( 'content' ) );
			} elseif ( 'robots' === $name ) {
				$meta['robots'] .= ( '' === $meta['robots'] ? '' : ', ' ) . trim( (string) $m->getAttribute( 'content' ) );
			} elseif ( 'googlebot' === $name ) {
				$meta['googlebot'] .= ( '' === $meta['googlebot'] ? '' : ', ' ) . trim( (string) $m->getAttribute( 'content' ) );
			}
		}
		foreach ( $dom->getElementsByTagName( 'link' ) as $l ) {
			$rel = strtolower( (string) $l->getAttribute( 'rel' ) );
			if ( 'canonical' === $rel ) {
				$meta['counts']['canonical']++;
				$meta['canonical'] = trim( (string) $l->getAttribute( 'href' ) );
			} elseif ( 'alternate' === $rel && '' !== (string) $l->getAttribute( 'hreflang' ) ) {
				$meta['hreflang'][] = array( 'lang' => (string) $l->getAttribute( 'hreflang' ), 'href' => (string) $l->getAttribute( 'href' ) );
			}
		}

		// --- headings ---
		$headings = CC_Assistant_Render_Probe::extract_headings_public( $dom );

		// --- links ---
		$links   = array();
		$in      = 0;
		$out     = 0;
		$content_internal = 0;
		foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
			$href = trim( (string) $a->getAttribute( 'href' ) );
			if ( '' === $href ) {
				continue;
			}
			$kind = self::href_kind( $href, $hhost );
			if ( 'skip' === $kind ) {
				continue;
			}
			$loc = self::classify_link_location( self::ancestors_of( $a ) );
			$has_child_elements = false;
			foreach ( $a->childNodes as $c ) {
				if ( XML_ELEMENT_NODE === $c->nodeType && 'span' !== strtolower( $c->nodeName ) ) {
					$has_child_elements = true;
					break;
				}
			}
			if ( 'internal' === $kind ) {
				$in++;
				if ( 'content' === $loc ) {
					$content_internal++;
				}
			} else {
				$out++;
			}
			if ( count( $links ) < self::MAX_LINKS ) {
				$links[] = array(
					'href'           => $href,
					'path'           => 'internal' === $kind ? self::norm_path( $href ) : '',
					'anchor'         => mb_substr( CC_Assistant_Render_Probe::accessible_name_public( $a ), 0, 120 ),
					'internal'       => 'internal' === $kind,
					'location'       => $loc,
					'wraps_children' => $has_child_elements,
					'nofollow'       => false !== stripos( (string) $a->getAttribute( 'rel' ), 'nofollow' ),
				);
			}
		}

		// --- images ---
		$images      = array();
		$missing_alt = 0;
		$absent_alt = 0;
		$empty_alt = 0;
		$img_total   = 0;
		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			$img_total++;
			$alt = trim( (string) $img->getAttribute( 'alt' ) );
			if ( ! $img->hasAttribute( 'alt' ) ) { $absent_alt++; } elseif ( '' === $alt ) { $empty_alt++; }
			if ( '' === $alt ) {
				$missing_alt++;
			}
			if ( count( $images ) < self::MAX_IMAGES ) {
				$src = (string) $img->getAttribute( 'src' );
				if ( '' === $src ) {
					$src = (string) $img->getAttribute( 'data-src' );
				}
				$images[] = array( 'src' => mb_substr( $src, 0, 300 ), 'alt' => mb_substr( $alt, 0, 200 ) );
			}
		}

		// --- schema ---
		$schema_raw = CC_Assistant_Render_Probe::extract_schema_public( $html );
		$schema     = array( 'blocks' => array(), 'issues' => isset( $schema_raw['issues'] ) ? $schema_raw['issues'] : array() );
		foreach ( (array) ( isset( $schema_raw['blocks'] ) ? $schema_raw['blocks'] : array() ) as $b ) {
			$schema['blocks'][] = array(
				'types'   => isset( $b['types'] ) ? $b['types'] : ( isset( $b['type'] ) ? (array) $b['type'] : array() ),
				'emitter' => isset( $b['emitter'] ) ? $b['emitter'] : '',
				'valid'   => isset( $b['valid'] ) ? (bool) $b['valid'] : true,
			);
		}

		$link_base = $url;
		foreach ( $dom->getElementsByTagName( 'base' ) as $base_element ) {
			if ( $base_element->hasAttribute( 'href' ) ) { $link_base = self::normalise_link( $base_element->getAttribute( 'href' ), $url ); break; }
		}

		// --- word count (approximate: body text minus nav/header/footer) ---
		$word_count = self::approx_word_count( $dom );

		return array(
			'url'        => $url,
			'link_base_url' => $link_base,
			'meta'       => $meta,
			'headings'   => $headings,
			'links'      => array(
				'total'            => $in + $out,
				'internal'         => $in,
				'external'         => $out,
				'content_internal' => $content_internal,
				'items'            => $links,
			),
			'images'     => array( 'total' => $img_total, 'missing_alt' => $missing_alt, 'absent_alt_attribute' => $absent_alt, 'empty_alt_attribute' => $empty_alt, 'items' => $images ),
			'schema'     => $schema,
			'word_count' => $word_count,
		);
	}

	/* --------------------------------------------------- pure helpers ----- */

	/** internal | external | skip (anchors, tel, mailto, javascript). */
	public static function href_kind( $href, $home_host ) {
		$h = trim( (string) $href );
		if ( '' === $h || '#' === $h[0] ) {
			return 'skip';
		}
		if ( preg_match( '/^(tel|mailto|javascript|sms):/i', $h ) ) {
			return 'skip';
		}
		$p = wp_parse_url( $h );
		if ( ! is_array( $p ) ) {
			return 'skip';
		}
		if ( empty( $p['host'] ) ) {
			return 'internal';
		}
		$host = preg_replace( '/^www\./', '', strtolower( $p['host'] ) );
		return ( '' !== $home_host && $host === $home_host ) ? 'internal' : 'external';
	}

	/** Resolve links conservatively: keep origin, query and fragment identity. */
	public static function normalise_link( $href, $base_url ) {
		$href = trim( (string) $href ); // DOM attributes have already been entity-decoded once.
		$base = wp_parse_url( (string) $base_url );
		if ( '' === $href || ! is_array( $base ) || empty( $base['host'] ) ) { return null; }
		$scheme = strtolower( $base['scheme'] ?? 'https' );
		if ( 0 === strpos( $href, '//' ) ) { $href = $scheme . ':' . $href; }
		$parts = wp_parse_url( $href );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return null; }
		if ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) { return null; }
		if ( isset( $parts['scheme'] ) && empty( $parts['host'] ) ) { return null; }
		if ( empty( $parts['host'] ) ) {
			$parts['scheme'] = $scheme; $parts['host'] = $base['host'];
			if ( isset( $base['port'] ) ) { $parts['port'] = $base['port']; }
			$path = $parts['path'] ?? '';
			if ( '' === $path ) {
				$parts['path'] = $base['path'] ?? '/';
				if ( ! isset( $parts['query'] ) && isset( $base['query'] ) ) { $parts['query'] = $base['query']; }
			} elseif ( '/' !== $path[0] ) {
				$base_path = $base['path'] ?? '/';
				$parts['path'] = substr( $base_path, 0, strrpos( $base_path, '/' ) + 1 ) . $path;
			}
		}
		$scheme = strtolower( $parts['scheme'] ?? $scheme );
		$path = $parts['path'] ?? '/';
		$segments = array();
		foreach ( explode( '/', $path ) as $segment ) {
			if ( '.' === $segment ) { continue; }
			if ( '..' === $segment ) { if ( count( $segments ) > 1 ) { array_pop( $segments ); } continue; }
			$segments[] = $segment;
		}
		$path = implode( '/', $segments );
		if ( '' === $path ) { $path = '/'; }
		if ( preg_match( '~/(?:\.|\.\.)$~', $parts['path'] ?? '' ) && '/' !== substr( $path, -1 ) ) { $path .= '/'; }
		$port = isset( $parts['port'] ) && ! ( ( 'https' === $scheme && 443 === $parts['port'] ) || ( 'http' === $scheme && 80 === $parts['port'] ) ) ? ':' . $parts['port'] : '';
		return $scheme . '://' . strtolower( $parts['host'] ) . $port . $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
	}

	public static function norm_path( $href ) {
		$p    = wp_parse_url( (string) $href );
		$path = is_array( $p ) && isset( $p['path'] ) ? $p['path'] : '/';
		$path = '/' . trim( $path, '/' );
		return '/' === $path ? '/' : $path . '/';
	}

	/**
	 * Ancestor descriptors for a DOM node: [{tag, class, id}] nearest first.
	 */
	private static function ancestors_of( $el ) {
		$out = array();
		$n   = $el->parentNode;
		while ( $n && XML_ELEMENT_NODE === $n->nodeType && count( $out ) < 40 ) {
			$out[] = array(
				'tag'   => strtolower( $n->nodeName ),
				'class' => strtolower( (string) $n->getAttribute( 'class' ) ),
				'id'    => strtolower( (string) $n->getAttribute( 'id' ) ),
			);
			$n = $n->parentNode;
		}
		return $out;
	}

	/**
	 * Where does a link live? content | nav | header | footer | breadcrumb.
	 * Decided from the ancestor chain so a footer menu link is never mistaken
	 * for an in-body editorial link. Pure: takes descriptors, not DOM nodes.
	 */
	public static function classify_link_location( $ancestors ) {
		// Two passes on purpose. A footer MENU link has a menu-item ancestor
		// nearer than the footer wrapper; returning on the first match would
		// label it nav and hide that it lives in site chrome. The structural
		// region (footer/header/breadcrumb) is decided over the whole chain
		// first; nav is only the answer when no region claims the link.
		$region = '';
		$is_nav = false;
		foreach ( (array) $ancestors as $a ) {
			$tag = isset( $a['tag'] ) ? $a['tag'] : '';
			$cls = isset( $a['class'] ) ? $a['class'] : '';
			$id  = isset( $a['id'] ) ? $a['id'] : '';
			if ( 'footer' === $tag || false !== strpos( $cls, 'elementor-location-footer' ) || false !== strpos( $cls, 'site-footer' ) || 'footer' === $id ) {
				$region = 'footer';
				break;
			}
			if ( 'header' === $tag || false !== strpos( $cls, 'elementor-location-header' ) || false !== strpos( $cls, 'site-header' ) || 'header' === $id || 'masthead' === $id ) {
				$region = 'header';
				break;
			}
			if ( false !== strpos( $cls, 'breadcrumb' ) ) {
				$region = 'breadcrumb';
				break;
			}
			if ( 'nav' === $tag || false !== strpos( $cls, 'elementor-nav-menu' ) || false !== strpos( $cls, 'menu-item' ) || false !== strpos( $cls, 'main-navigation' ) ) {
				$is_nav = true;
			}
		}
		if ( '' !== $region ) {
			return $region;
		}
		return $is_nav ? 'nav' : 'content';
	}

	/**
	 * Is a stored record stale? Pure: strings in, bool out.
	 */
	public static function is_stale( $captured_at, $post_modified_gmt, $last_apply_gmt, $now_ts, $max_age = self::MAX_AGE ) {
		if ( empty( $captured_at ) ) {
			return true;
		}
		$cap = strtotime( $captured_at . ' UTC' );
		if ( ! $cap ) {
			return true;
		}
		if ( ! empty( $post_modified_gmt ) && strtotime( $post_modified_gmt . ' UTC' ) > $cap ) {
			return true;
		}
		if ( ! empty( $last_apply_gmt ) && strtotime( $last_apply_gmt . ' UTC' ) > $cap ) {
			return true;
		}
		return ( $now_ts - $cap ) > $max_age;
	}

	/**
	 * Check intended changes against captured facts. Pure.
	 *
	 * Expectation shapes:
	 *   {check: robots_contains,     value: noindex}
	 *   {check: robots_not_contains, value: noindex}
	 *   {check: link_present,        href: URL-or-path}
	 *   {check: link_absent,         href: URL-or-path}
	 *   {check: heading_present,     text}
	 *   {check: text_present,        text}   (anchor text / heading / meta)
	 *   {check: meta_contains,       key: title|description|canonical, value}
	 *   {check: schema_type_present, value: FAQPage}
	 *
	 * @return array {pass, checks: [{check, pass, evidence}]}
	 */
	public static function check_expectations( $facts, $expectations ) {
		$checks = array();
		$all    = true;
		$robots = strtolower( implode( ', ', array( $facts['meta']['robots'] ?? '', $facts['meta']['googlebot'] ?? '', $facts['meta']['x_robots_tag'] ?? '' ) ) );
		$urls = array(); $unresolved_links = false;
		$base_url = array_key_exists( 'link_base_url', $facts ) ? $facts['link_base_url'] : ( $facts['url'] ?? home_url( '/' ) );
		$anchors = array();
		foreach ( (array) ( isset( $facts['links']['items'] ) ? $facts['links']['items'] : array() ) as $l ) {
			$resolved = self::normalise_link( $l['href'] ?? ( ! empty( $l['internal'] ) ? ( $l['path'] ?? '' ) : '' ), $base_url );
			if ( null !== $resolved ) { $urls[$resolved] = true; } else { $unresolved_links = true; }
			$anchors[] = strtolower( (string) $l['anchor'] );
		}
		$heads = array();
		foreach ( (array) ( isset( $facts['headings']['outline'] ) ? $facts['headings']['outline'] : array() ) as $h ) {
			$heads[] = strtolower( (string) $h['text'] );
		}
		$types = array();
		foreach ( (array) ( isset( $facts['schema']['blocks'] ) ? $facts['schema']['blocks'] : array() ) as $b ) {
			foreach ( (array) $b['types'] as $ty ) {
				$types[ strtolower( (string) $ty ) ] = true;
			}
		}

		foreach ( (array) $expectations as $e ) {
			$c    = isset( $e['check'] ) ? $e['check'] : '';
			$pass = false;
			$ev   = '';
			switch ( $c ) {
				case 'robots_contains':
					$pass = false !== strpos( $robots, strtolower( (string) $e['value'] ) );
					$ev   = 'robots=' . ( '' === $robots ? '(none)' : $robots );
					break;
				case 'robots_not_contains':
					$pass = false === strpos( $robots, strtolower( (string) $e['value'] ) );
					$ev   = 'robots=' . ( '' === $robots ? '(none)' : $robots );
					break;
				case 'link_present':
				case 'link_absent':
					$p    = self::normalise_link( (string) ( $e['href'] ?? '' ), $base_url );
					$has  = null !== $p && isset( $urls[$p] );
					$pass = null === $p ? null : ( ( 'link_present' === $c ) ? $has : ! $has );
					$ev   = $p . ( $has ? ' is in the DOM' : ' is NOT in the DOM' );
					break;
				case 'heading_present':
					$needle = strtolower( trim( (string) $e['text'] ) );
					$pass   = '' !== $needle && in_array( $needle, $heads, true );
					$ev     = $pass ? 'heading found' : 'heading not found among ' . count( $heads );
					break;
				case 'text_present':
					$needle = strtolower( trim( (string) $e['text'] ) );
					$pool   = implode( "\n", array_merge( $heads, $anchors, array( strtolower( (string) ( $facts['meta']['title'] ?? '' ) ), strtolower( (string) ( $facts['meta']['description'] ?? '' ) ) ) ) );
					$pass   = '' !== $needle && false !== strpos( $pool, $needle );
					$ev     = $pass ? 'text found in headings/anchors/meta' : 'text not found in headings/anchors/meta';
					break;
				case 'meta_contains':
					$key  = isset( $e['key'] ) ? $e['key'] : '';
					$have = strtolower( (string) ( $facts['meta'][ $key ] ?? '' ) );
					$needle = trim( strtolower( (string) ( $e['value'] ?? '' ) ) );
					$pass = '' === $needle ? null : ( '' !== $have && false !== strpos( $have, $needle ) );
					$ev   = $key . '=' . mb_substr( $have, 0, 120 );
					break;
				case 'unresolved_meta_template':
					$pass = null;
					$ev = 'SEO template requires the active plugin to resolve its full value; no literal verification is available.';
					break;
				case 'schema_type_present':
					$pass = isset( $types[ strtolower( (string) $e['value'] ) ] );
					$ev   = 'types=' . implode( ',', array_keys( $types ) );
					break;
				default:
					$pass = false;
					$ev   = 'unknown check';
			}
			// Bounded lists and a text subset cannot prove absence on the whole page.
			$incomplete_links = $unresolved_links || ( $facts['links']['total'] ?? 0 ) > count( $facts['links']['items'] ?? array() );
			if ( ( in_array( $c, array( 'link_present', 'link_absent' ), true ) && ! $has && $incomplete_links ) || ( 'text_present' === $c && ! $pass ) || ( 'heading_present' === $c && ! $pass ) ) {
				$pass = null;
				$ev .= '; unavailable: bounded/subset evidence cannot establish absence';
			}
			$all = $all && true === $pass;
			$checks[] = array( 'check' => $c, 'pass' => $pass, 'evidence' => $ev, 'expect' => $e );
		}
		return array( 'pass' => $all, 'checks' => $checks );
	}

	private static function approx_word_count( $dom ) {
		$body = $dom->getElementsByTagName( 'body' );
		if ( ! $body->length ) {
			return 0;
		}
		$clone = $body->item( 0 )->cloneNode( true );
		foreach ( array( 'nav', 'header', 'footer', 'script', 'style', 'noscript' ) as $tag ) {
			$nodes = array();
			foreach ( $clone->getElementsByTagName( $tag ) as $n ) {
				$nodes[] = $n;
			}
			foreach ( $nodes as $n ) {
				if ( $n->parentNode ) {
					$n->parentNode->removeChild( $n );
				}
			}
		}
		$text = preg_replace( '/\s+/', ' ', (string) $clone->textContent );
		return str_word_count( (string) $text );
	}

	/* --------------------------------------------------------------- read -- */

	/**
	 * Read facts, refreshing first if stale (and saying so).
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $refresh Force a recapture.
	 * @return array|WP_Error
	 */
	public static function get( $post_id, $refresh = false ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		self::ensure_table();
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}" . self::TABLE . ' WHERE post_id = %d', $post_id ),
			ARRAY_A
		);
		$last_apply = (string) get_post_meta( $post_id, '_cc_assistant_last_internal_apply', true );
		$stale      = ! $row || self::is_stale( $row['captured_at'], $post->post_modified_gmt, $last_apply, time() );

		if ( $refresh || $stale ) {
			$cap = self::capture( $post_id );
			if ( ! is_wp_error( $cap ) ) {
				$cap['stale_before_read'] = (bool) $stale;
				$cap['refreshed']         = true;
				return $cap;
			}
			if ( ! $row ) {
				return $cap; // nothing stored and capture failed
			}
			// Capture failed but we have an older record: return it, loudly.
			$out = self::row_to_result( $row );
			$out['stale']             = true;
			$out['refreshed']         = false;
			$out['verification'] = null;
			$out['refresh_error']     = $cap->get_error_message();
			$out['warning']           = 'STALE: this record predates the latest change and a refresh just failed. Do not treat it as current.';
			return $out;
		}
		$out = self::row_to_result( $row );
		$out['stale']     = false;
		$out['refreshed'] = false;
		return $out;
	}

	private static function row_to_result( $row ) {
		$facts = json_decode( (string) $row['facts'], true );
		return array(
			'post_id'      => (int) $row['post_id'],
			'captured_at'  => $row['captured_at'],
			'http_code'    => (int) $row['http_code'],
			'body_sha1'    => $row['body_sha1'],
			'cache_state'  => $row['cache_state'],
			'facts'        => is_array( $facts ) ? $facts : array(),
			'verification' => $row['verification'] ? json_decode( (string) $row['verification'], true ) : null,
		);
	}

	/* ------------------------------------------------------------ sweep --- */

	/** Nightly: stale-first, bounded batch. */
	public static function sweep() {
		self::ensure_table();
		global $wpdb;
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$ph      = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_modified_gmt, f.captured_at
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->prefix}" . self::TABLE . " f ON f.post_id = p.ID
				 WHERE p.post_status = 'publish' AND p.post_type IN ($ph)
				 ORDER BY (f.captured_at IS NULL) DESC, f.captured_at ASC
				 LIMIT %d",
				array_merge( $allowed, array( self::SWEEP_BATCH ) )
			),
			ARRAY_A
		);
		$done = 0;
		foreach ( (array) $rows as $r ) {
			$last = (string) get_post_meta( (int) $r['ID'], '_cc_assistant_last_internal_apply', true );
			if ( self::is_stale( $r['captured_at'], $r['post_modified_gmt'], $last, time() ) ) {
				self::capture( (int) $r['ID'] );
				$done++;
			}
		}
		return $done;
	}

	/** How much of the site has fresh facts. Surfaced in whoami. */
	public static function coverage() {
		self::ensure_table();
		global $wpdb;
		$allowed = (array) get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		$ph      = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_modified_gmt, f.captured_at
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->prefix}" . self::TABLE . " f ON f.post_id = p.ID
				 WHERE p.post_status = 'publish' AND p.post_type IN ($ph)",
				$allowed
			),
			ARRAY_A
		);
		$total = 0; $fresh = 0; $stale = 0; $missing = 0;
		foreach ( (array) $rows as $r ) {
			$total++;
			if ( empty( $r['captured_at'] ) ) {
				$missing++;
				continue;
			}
			// last_apply is per-post meta; skip it here to keep coverage one query.
			if ( self::is_stale( $r['captured_at'], $r['post_modified_gmt'], '', time() ) ) {
				$stale++;
			} else {
				$fresh++;
			}
		}
		return array(
			'published' => $total,
			'fresh'     => $fresh,
			'stale'     => $stale,
			'missing'   => $missing,
			'note'      => $missing + $stale > 0
				? 'page_facts(post_id) refreshes a stale/missing record on read. The nightly sweep fills the rest.'
				: 'Every published page has a fresh rendered-facts record.',
		);
	}

	/* ------------------------------------------------------- after apply --- */

	/**
	 * Recapture after a plugin apply and verify the intended change is
	 * actually rendered. Writes the verdict into the facts row and merges it
	 * into the pending row's verification_result under "page_facts".
	 *
	 * @return array|null Verification payload, or null when nothing to verify.
	 */
	public static function after_apply( $post_id, $pending, $proposed ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}
		$cap = self::capture( $post_id );
		if ( is_wp_error( $cap ) ) {
			return array( 'verdict' => 'unavailable', 'note' => 'Recapture failed: ' . $cap->get_error_message() );
		}
		$expect = self::expectations_for_pending( $pending, $proposed );
		if ( empty( $expect ) ) {
			return array( 'verdict' => 'captured', 'note' => 'Facts refreshed; this change type has no automatic DOM expectation.', 'cache_state' => $cap['cache_state'] );
		}
		$res = self::check_expectations( $cap['facts'], $expect );
		$verdict = $res['pass'] ? 'verified' : 'FAILED';
		if ( in_array( null, array_column( $res['checks'], 'pass' ), true ) ) { $verdict = 'inconclusive_evidence'; }
		if ( 'miss' !== $cap['cache_state'] ) {
			$verdict = 'inconclusive_cache';
		}
		$payload = array(
			'verdict'     => $verdict,
			'cache_state' => $cap['cache_state'],
			'captured_at' => $cap['captured_at'],
			'checks'      => $res['checks'],
			'body_sha1'   => $cap['body_sha1'],
			'rules_version' => '0.83.0',
			'note'        => 'verified' === $verdict
				? 'The rendered page shows the intended change.'
				: ( 'inconclusive_cache' === $verdict
					? 'Cache freshness was not established. These checks describe the fetched HTML; they do not verify the latest write. Purge the cache and recapture before concluding.'
					: ( 'inconclusive_evidence' === $verdict ? 'Captured evidence is incomplete for this expectation. Inspect the exact affected output before concluding.' : 'The fetched HTML does not meet the expected change. Inspect stored values and the relevant output before concluding the write failed.' ) ),
		);

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . self::TABLE,
			array( 'verification' => wp_json_encode( $payload ) ),
			array( 'post_id' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( ! empty( $pending->id ) ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT verification_result FROM {$wpdb->prefix}cc_pending_changes WHERE id = %d", (int) $pending->id ) );
			$merged   = $existing ? json_decode( (string) $existing, true ) : array();
			if ( ! is_array( $merged ) ) {
				$merged = array( 'previous' => $existing );
			}
			$merged['page_facts'] = $payload;
			$wpdb->update(
				$wpdb->prefix . 'cc_pending_changes',
				array( 'verification_result' => wp_json_encode( $merged ) ),
				array( 'id' => (int) $pending->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
		return $payload;
	}

	/**
	 * Derive DOM expectations from what a pending change said it would do.
	 * Conservative: only change types with an unambiguous rendered signature.
	 */
	public static function expectations_for_pending( $pending, $proposed ) {
		$type = isset( $pending->change_type ) ? (string) $pending->change_type : '';
		$p    = is_array( $proposed ) ? $proposed : ( is_string( $proposed ) ? json_decode( $proposed, true ) : array() );
		if ( ! is_array( $p ) ) {
			$p = array();
		}
		$out = array();

		if ( 'postmeta_update' === $type ) {
			$key = isset( $p['key'] ) ? (string) $p['key'] : '';
			$val = isset( $p['value'] ) ? $p['value'] : '';
			if ( in_array( $key, array( 'rank_math_robots', '_yoast_wpseo_meta-robots-noindex', '_seopress_robots_index' ), true ) ) {
				$noindex = false;
				if ( is_array( $val ) ) {
					$noindex = in_array( 'noindex', array_map( 'strval', $val ), true );
				} else {
					$sv = strtolower( (string) $val );
					$noindex = ( '1' === $sv || 'yes' === $sv || false !== strpos( $sv, 'noindex' ) );
				}
				$out[] = array( 'check' => $noindex ? 'robots_contains' : 'robots_not_contains', 'value' => 'noindex' );
			} elseif ( in_array( $key, array( 'rank_math_description', '_yoast_wpseo_metadesc', '_aioseo_description', '_seopress_titles_desc' ), true ) && is_string( $val ) && '' !== trim( $val ) ) {
				$out[] = array( 'check' => preg_match( '/%[^%]+%/', $val ) ? 'unresolved_meta_template' : 'meta_contains', 'key' => 'description', 'value' => trim( $val ) );
			} elseif ( in_array( $key, array( 'rank_math_title', '_yoast_wpseo_title', '_aioseo_title', '_seopress_titles_title' ), true ) && is_string( $val ) && '' !== trim( $val ) ) {
				$out[] = array( 'check' => preg_match( '/%[^%]+%/', $val ) ? 'unresolved_meta_template' : 'meta_contains', 'key' => 'title', 'value' => trim( $val ) );
			}
		} elseif ( 'elementor_widget_update' === $type ) {
			$s = isset( $p['settings'] ) && is_array( $p['settings'] ) ? $p['settings'] : array();
			if ( ! empty( $s['link']['url'] ) && '#' !== substr( (string) $s['link']['url'], 0, 1 ) ) {
				$out[] = array( 'check' => 'link_present', 'href' => (string) $s['link']['url'] );
			}
			foreach ( array( 'text', 'title', 'title_text' ) as $k ) {
				if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
					$out[] = array( 'check' => 'text_present', 'text' => wp_strip_all_tags( $s[ $k ] ) );
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * v0.76 — which published page should stand in for a Theme Builder
	 * template or popup when verifying an apply? The template has no front
	 * end of its own; its display conditions (_elementor_conditions, the
	 * same grammar popups use) say where it renders. Candidates in order:
	 * the static front page, the newest published post, the newest published
	 * page. First one the conditions definitely cover wins; 0 when none does
	 * or when the conditions cannot be parsed (never guess a verify target).
	 */
	public static function sample_page_for_template( $template_id ) {
		$template_id = (int) $template_id;
		if ( $template_id <= 0 ) {
			return 0;
		}
		$conditions = get_post_meta( $template_id, '_elementor_conditions', true );
		if ( ! is_array( $conditions ) || empty( $conditions ) ) {
			return 0;
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-popups.php';
		$candidates = array();
		$front      = (int) get_option( 'page_on_front', 0 );
		if ( $front > 0 && 'publish' === get_post_status( $front ) ) {
			$candidates[] = $front;
		}
		foreach ( array( 'post', 'page' ) as $type ) {
			$latest = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'exclude' => $front ? array( $front ) : array() ) );
			foreach ( (array) $latest as $id ) {
				$candidates[] = (int) $id;
			}
		}
		foreach ( array_unique( $candidates ) as $cand ) {
			if ( true === CC_Assistant_Popups::covers_post( $conditions, $cand ) ) {
				return $cand;
			}
		}
		return 0;
	}
}

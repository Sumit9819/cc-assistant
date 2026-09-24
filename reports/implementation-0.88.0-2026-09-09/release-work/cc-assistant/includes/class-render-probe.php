<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.44 — Render-introspection probe.
 *
 * The plugin runs server-side, so it can fetch its OWN front end over the
 * loopback interface and read the page Google actually receives — bypassing
 * any edge WAF (e.g. SiteGround) that 403s external crawlers. This closes the
 * single biggest blind spot the assistant kept hitting: it was editing pages
 * it could not see, so it could never verify rendered schema, alt text, or
 * which plugin emitted what.
 *
 * probe() returns, per requested facet:
 *   - schema:   every <script type="application/ld+json"> block, decoded,
 *               attributed to its emitter (Rank Math / hand-built snippet /
 *               cc-assistant), and linted for the exact problems this project
 *               hit: self-serving aggregateRating, duplicate @id, duplicate
 *               BreadcrumbList, and breadcrumb items missing a name.
 *   - links:    every <a> with its RESOLVED accessible name (link text OR
 *               child img[alt] OR aria-label), so "empty link" stops firing
 *               on properly alt'd image links.
 *   - images:   rendered <img> alt coverage.
 *   - headings: the h1-h6 outline as actually rendered.
 *
 * Every probe also returns a `cache` block ({state: hit|miss|unknown, markers})
 * read off the response headers. A query-string cache-bust does NOT defeat
 * SiteGround's path-keyed Dynamic Cache, so on a HIT the result carries a loud
 * stale-risk warning telling the caller to purge + re-probe — the probe must
 * never pass off a cached pre-edit page as the live render (v0.46.1).
 */
class CC_Assistant_Render_Probe {

	const FACETS = array( 'schema', 'links', 'images', 'headings' );

	/** Schema @types whose self-emitted aggregateRating violates Google's review guidelines. */
	const SELF_RATING_TYPES = array(
		'Organization', 'LocalBusiness', 'MedicalOrganization', 'MedicalClinic',
		'EmergencyService', 'Hospital', 'Physician', 'Dentist',
	);

	/**
	 * Fetch + introspect a post's rendered front end.
	 *
	 * @param int        $post_id
	 * @param array|null $extract subset of FACETS; null = all.
	 * @return array|WP_Error
	 */
	public static function probe( $post_id, $extract = null ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}
		$facets = ( is_array( $extract ) && ! empty( $extract ) )
			? array_values( array_intersect( $extract, self::FACETS ) )
			: self::FACETS;
		if ( empty( $facets ) ) {
			$facets = self::FACETS;
		}

		$url   = get_permalink( $post_id );
		$fetch = self::fetch( $url );
		if ( is_wp_error( $fetch ) ) {
			return $fetch;
		}

		$html   = $fetch['body'];
		$result = array(
			'post_id'     => $post_id,
			'url'         => $url,
			'http_code'   => $fetch['code'],
			'fetched_via' => 'loopback',
			'bytes'       => strlen( $html ),
			'post_status' => $post->post_status,
			'cache'       => $fetch['cache'],
		);
		if ( 'hit' === $fetch['cache']['state'] ) {
			// The single most dangerous failure mode: a cache HIT means this markup
			// can predate recent edits. Never let the caller trust it as live.
			$result['cache']['warning'] = 'STALE-RISK: served from a full-page cache HIT, so this markup may NOT reflect edits made since the cache was built (a query-string cache-bust does not defeat SiteGround Dynamic Cache, which is path-keyed). Purge the host/CDN cache (SiteGround: SG Optimizer -> Caching -> Purge SG Cache, plus the CDN) and re-probe before trusting schema/links/headings/alt as current.';
		}
		if ( 200 !== (int) $fetch['code'] ) {
			$result['warning'] = sprintf(
				'Front end returned HTTP %d. Drafts 404 over loopback and redirects will not reflect live markup — publish or check the redirect before trusting this.',
				(int) $fetch['code']
			);
		}

		$dom = self::load_dom( $html );

		if ( in_array( 'schema', $facets, true ) ) {
			$result['schema'] = self::extract_schema( $html );
		}
		if ( $dom ) {
			if ( in_array( 'links', $facets, true ) ) {
				$result['links'] = self::extract_links( $dom );
			}
			if ( in_array( 'images', $facets, true ) ) {
				$result['images'] = self::extract_images( $dom );
			}
			if ( in_array( 'headings', $facets, true ) ) {
				$result['headings'] = self::extract_headings( $dom );
			}
		}

		return $result;
	}

	/**
	 * v0.74.1: every internal href actually present in the RENDERED page.
	 *
	 * This is the ground truth the static parsers must be checked against. It
	 * exists because a parser blind spot (container-level links) produced a
	 * confident "this page links to nothing" that was wrong, and there was no
	 * tool-level mechanism to catch it. Returns canonical site-relative paths,
	 * de-duplicated, with the accessible name of the first occurrence.
	 *
	 * @param int $post_id Post to fetch over loopback.
	 * @return array|WP_Error { ok, cache_state, hrefs: [path => name] }
	 */
	public static function live_internal_links( $post_id ) {
		$post_id = (int) $post_id;
		$url     = get_permalink( $post_id );
		if ( ! $url ) {
			return new WP_Error( 'no_permalink', 'Post has no permalink.' );
		}
		$fetch = self::fetch( $url );
		if ( is_wp_error( $fetch ) ) {
			return $fetch;
		}
		$dom = self::load_dom( $fetch['body'] );
		if ( ! $dom ) {
			return new WP_Error( 'dom_unavailable', 'Could not parse the rendered HTML.' );
		}
		$home  = wp_parse_url( home_url( '/' ) );
		$hhost = isset( $home['host'] ) ? preg_replace( '/^www\./', '', strtolower( $home['host'] ) ) : '';
		$out   = array();
		foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
			$href = trim( (string) $a->getAttribute( 'href' ) );
			if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'tel:' ) || 0 === stripos( $href, 'mailto:' ) || 0 === stripos( $href, 'javascript:' ) ) {
				continue;
			}
			$p = wp_parse_url( $href );
			if ( ! is_array( $p ) ) {
				continue;
			}
			$host = isset( $p['host'] ) ? preg_replace( '/^www\./', '', strtolower( $p['host'] ) ) : '';
			if ( '' !== $host && $host !== $hhost ) {
				continue; // external
			}
			$path = isset( $p['path'] ) ? $p['path'] : '/';
			$path = '/' . trim( $path, '/' );
			if ( '/' !== $path ) {
				$path .= '/';
			}
			if ( ! isset( $out[ $path ] ) ) {
				$out[ $path ] = self::accessible_name( $a );
			}
		}
		return array(
			'ok'          => true,
			'http_code'   => (int) $fetch['code'],
			'cache_state' => isset( $fetch['cache']['state'] ) ? $fetch['cache']['state'] : 'unknown',
			'hrefs'       => $out,
		);
	}

	// --- fetch ------------------------------------------------------------

	/** v0.75.0 public wrappers so Page Facts reuses ONE fetch/parse path. */
	public static function fetch_public( $url ) { return self::fetch( $url ); }
	public static function load_dom_public( $html ) { return self::load_dom( $html ); }
	public static function extract_schema_public( $html ) { return self::extract_schema( $html ); }
	public static function extract_headings_public( $dom ) { return self::extract_headings( $dom ); }
	public static function accessible_name_public( $el ) { return self::accessible_name( $el ); }

	private static function fetch( $url ) {
		// Cache-bust HARD. A query-string buster alone is not enough: SiteGround's
		// full-page Dynamic Cache is keyed on PATH and ignores query strings, so it
		// will keep serving stale HTML (this silently invalidated a whole verify
		// session — the probe reported a pre-edit page as if it were live). Send
		// no-cache request headers as a best-effort bypass AND capture the response
		// cache markers so probe() can WARN when it was likely handed a stale copy.
		$bust = add_query_arg(
			array(
				'cc_probe' => (string) microtime( true ),
				'cc_nonce' => uniqid( '', true ),
			),
			$url
		);
		$args = array(
			'timeout'     => 15,
			'redirection' => 0, // A redirect is not evidence about the requested page.
			'sslverify'   => true,
			'user-agent'  => 'cc-assistant-render-probe (internal loopback)',
			'headers'     => array(
				'Accept'        => 'text/html',
				'Cache-Control' => 'no-cache, no-store, max-age=0',
				'Pragma'        => 'no-cache',
			),
		);
		$resp = wp_remote_get( $bust, $args );
		if ( is_wp_error( $resp ) ) {
			// Custom Permalinks can 404 the cache-bust arg; retry the clean URL.
			$resp = wp_remote_get( $url, $args );
		}
		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'fetch_failed', 'Loopback fetch failed: ' . $resp->get_error_message() );
		}
		$result = array(
			'code'  => (int) wp_remote_retrieve_response_code( $resp ),
			'body'  => (string) wp_remote_retrieve_body( $resp ),
			'cache' => self::cache_state( $resp ),
			'content_type' => (string) wp_remote_retrieve_header( $resp, 'content-type' ),
			'x_robots_tag' => implode( ', ', (array) wp_remote_retrieve_header( $resp, 'x-robots-tag' ) ),
		);
		require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
		$valid = CC_Assistant_Page_Facts::validate_fetch( $result );
		return is_wp_error( $valid ) ? $valid : $result;
	}

	/**
	 * Read full-page-cache HIT/MISS markers off the response. SiteGround
	 * (X-Proxy-Cache / X-SG-Cache / SG-F-Cache / X-SG-CDN), Cloudflare
	 * (cf-cache-status), Varnish/generic proxies (X-Cache, Age) all advertise
	 * cache state here. Returns a normalized {state, markers} so the probe can
	 * tell the caller "this markup may be stale" instead of lying by omission.
	 */
	private static function cache_state( $resp ) {
		$want = array(
			'x-proxy-cache', 'x-proxy-cache-info', 'x-sg-cache', 'sg-f-cache',
			'x-sg-cdn', 'x-cache', 'x-cache-status', 'cf-cache-status', 'age',
		);
		$markers = array();
		foreach ( $want as $h ) {
			$v = wp_remote_retrieve_header( $resp, $h );
			if ( null !== $v && '' !== $v ) {
				$markers[ $h ] = is_array( $v ) ? implode( ',', $v ) : (string) $v;
			}
		}
		$blob  = strtolower( implode( ' ', $markers ) );
		$state = 'unknown';
		if ( false !== strpos( $blob, 'hit' ) ) {
			$state = 'hit';
		} elseif ( false !== strpos( $blob, 'miss' ) || false !== strpos( $blob, 'bypass' ) || false !== strpos( $blob, 'dynamic' ) ) {
			$state = 'miss';
		}
		// A positive Age on an otherwise-unlabeled proxy also means a hit.
		if ( 'unknown' === $state && isset( $markers['age'] ) && (int) $markers['age'] > 0 ) {
			$state = 'hit';
		}
		return array( 'state' => $state, 'markers' => $markers );
	}

	private static function load_dom( $html ) {
		if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
			return null;
		}
		$dom  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		// Prepend an encoding hint so DOMDocument does not mangle UTF-8.
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $dom;
	}

	// --- schema -----------------------------------------------------------

	private static function extract_schema( $html ) {
		$blocks = array();
		$issues = array();

		$dom = self::load_dom( $html );
		$m = array();
		if ( $dom ) {
			$xp = new DOMXPath( $dom );
			foreach ( $xp->query( '//script[not(ancestor::template) and not(ancestor::noscript)]' ) as $script ) {
				if ( 'application/ld+json' === strtolower( trim( $script->getAttribute( 'type' ) ) ) ) {
					$m[] = array( $dom->saveHTML( $script ), $script->textContent );
				}
			}
		}
		if ( $m ) {
			foreach ( $m as $i => $match ) {
				$raw     = trim( $match[1] );
				$emitter = self::guess_emitter( $match[0], $html, $raw );
				$decoded = json_decode( $raw, true );
				$block   = array(
					'index'   => $i,
					'emitter' => $emitter,
					'valid'   => ( JSON_ERROR_NONE === json_last_error() ),
				);
				if ( JSON_ERROR_NONE !== json_last_error() ) {
					$block['error'] = 'Invalid JSON: ' . json_last_error_msg();
					$issues[]       = array(
						'severity' => 'critical',
						'emitter'  => $emitter,
						'message'  => 'JSON-LD block #' . $i . ' (' . $emitter . ') is not valid JSON.',
					);
					$blocks[] = $block;
					continue;
				}
				if ( ! is_array( $decoded ) ) {
					$issues[] = array( 'severity' => 'warning', 'message' => 'JSON-LD must describe an object or array; this block contains a scalar.' );
				}
				$nodes          = self::flatten_graph( $decoded );
				$block['types'] = self::collect_types( $nodes );
				$blocks[]       = $block;
				$issues         = array_merge( $issues, self::lint_nodes( $nodes, $emitter ) );
			}
		}

		$issues = array_merge( $issues, self::lint_cross_block( $blocks ) );

		return array(
			'block_count' => count( $blocks ),
			'blocks'      => $blocks,
			'issues'      => $issues,
			'verdict'     => empty( $issues ) ? 'clean' : ( self::has_critical( $issues ) ? 'critical' : 'warn' ),
		);
	}

	private static function guess_emitter( $tag, $html, $raw ) {
		if ( false !== stripos( $tag, 'rank-math' ) ) {
			return 'rank_math';
		}
		if ( false !== stripos( $tag, 'yoast' ) || false !== stripos( $raw, 'yoast.com' ) ) {
			return 'yoast';
		}
		// cc-assistant's OWN wp_head injections tag the <script> with a
		// data-cc-assistant="<variant>" attribute (emergency-service / page-jsonld).
		// The marker lives on the TAG, not in the JSON body — check it first so the
		// probe never mislabels its own output as "unknown" (which sent a whole
		// schema-source hunt down the wrong path looking in themes / code plugins).
		if ( preg_match( '/data-cc-assistant=["\']?([a-z0-9_-]+)/i', $tag, $ccm ) ) {
			return 'cc_assistant:' . strtolower( $ccm[1] );
		}
		// Older cc-assistant blobs embedded the meta key inside the JSON body.
		if ( false !== strpos( $raw, '_cc_assistant' ) ) {
			return 'cc_assistant';
		}
		// Hand-built snippets on these sites carry an admin-only HTML comment
		// banner immediately before the script (e.g. "Local Business Schema").
		$pos = strpos( $html, $tag );
		if ( false !== $pos ) {
			$before = substr( $html, max( 0, $pos - 400 ), 400 );
			if ( preg_match( '/<!--\s*\n?\s*Title:\s*([^\n]+)/i', $before, $cm ) ) {
				return 'snippet:' . trim( $cm[1] );
			}
		}
		return 'unknown';
	}

	/** Walk a decoded JSON-LD doc into a flat list of typed nodes. */
	private static function flatten_graph( $decoded ) {
		$nodes = array();
		$roots = array();
		if ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) {
			$roots = $decoded['@graph'];
		} elseif ( isset( $decoded[0] ) ) {
			$roots = $decoded;
		} else {
			$roots = array( $decoded );
		}
		foreach ( $roots as $node ) {
			if ( is_array( $node ) && isset( $node['@type'] ) ) {
				$nodes[] = $node;
			}
		}
		return $nodes;
	}

	private static function collect_types( $nodes ) {
		$types = array();
		foreach ( $nodes as $n ) {
			$t = $n['@type'];
			foreach ( (array) $t as $one ) {
				$types[] = $one;
			}
		}
		return array_values( array_unique( $types ) );
	}

	/** Per-node lint: the exact failure modes this project hit. */
	private static function lint_nodes( $nodes, $emitter ) {
		$issues = array();
		foreach ( $nodes as $node ) {
			$types = (array) $node['@type'];

			// Self-serving aggregateRating on an org/business node.
			if ( isset( $node['aggregateRating'] ) && array_intersect( $types, self::SELF_RATING_TYPES ) ) {
				$rv = isset( $node['aggregateRating']['ratingValue'] ) ? $node['aggregateRating']['ratingValue'] : '?';
				$issues[] = array(
					'severity' => 'critical',
					'emitter'  => $emitter,
					'check'    => 'self_serving_rating',
					'message'  => 'aggregateRating (' . $rv . ') on ' . implode( '/', $types ) . ' is self-serving — Google only allows ratings from reviews collected on your own page, not GBP. Manual-action risk. Verify the value matches reality and consider removing it.',
				);
			}

			// BreadcrumbList: items must carry a name.
			if ( in_array( 'BreadcrumbList', $types, true ) && isset( $node['itemListElement'] ) ) {
				foreach ( (array) $node['itemListElement'] as $li ) {
					$has_name = ! empty( $li['name'] )
						|| ( isset( $li['item'] ) && is_array( $li['item'] ) && ! empty( $li['item']['name'] ) );
					if ( ! $has_name ) {
						$issues[] = array(
							'severity' => 'critical',
							'emitter'  => $emitter,
							'check'    => 'breadcrumb_missing_name',
							'message'  => 'BreadcrumbList item position ' . ( $li['position'] ?? '?' ) . ' is missing "name" / "item.name" (the GSC "Either name or item.name should be specified" error).',
						);
					}
					if ( isset( $li['position'] ) && is_string( $li['position'] ) ) {
						$issues[] = array(
							'severity' => 'info',
							'emitter'  => $emitter,
							'check'    => 'breadcrumb_position_string',
							'message'  => 'BreadcrumbList position is a string ("' . $li['position'] . '"); Google prefers an integer.',
						);
					}
				}
			}
		}
		return $issues;
	}

	/** Cross-block lint: duplicate @id, multiple BreadcrumbList (conflicting emitters). */
	private static function lint_cross_block( $blocks ) {
		$issues          = array();
		$breadcrumb_from = array();
		foreach ( $blocks as $b ) {
			if ( ! empty( $b['types'] ) && in_array( 'BreadcrumbList', $b['types'], true ) ) {
				$breadcrumb_from[] = $b['emitter'];
			}
		}
		if ( count( $breadcrumb_from ) > 1 ) {
			$issues[] = array(
				'severity' => 'warn',
				'check'    => 'duplicate_breadcrumb',
				'message'  => 'Multiple BreadcrumbList nodes on one page (from: ' . implode( ', ', $breadcrumb_from ) . '). Keep one emitter; disable the others.',
			);
		}
		return $issues;
	}

	private static function has_critical( $issues ) {
		foreach ( $issues as $i ) {
			if ( 'critical' === $i['severity'] ) {
				return true;
			}
		}
		return false;
	}

	// --- links (the empty_link truth) -------------------------------------

	private static function extract_links( $dom ) {
		$empty = array();
		$total = 0;
		foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
			$href = trim( (string) $a->getAttribute( 'href' ) );
			if ( '' === $href || '#' === $href ) {
				continue; // anchors/buttons, not navigational links.
			}
			$total++;
			$name = self::accessible_name( $a );
			if ( '' === $name ) {
				$empty[] = array(
					'href'    => $href,
					'snippet' => self::snippet( $a ),
				);
			}
		}
		return array(
			'total'            => $total,
			'truly_empty'      => count( $empty ),
			'empty_links'      => array_slice( $empty, 0, 25 ),
			'note'             => 'truly_empty resolves link text + child img[alt] + aria-label, so an alt\'d image link is NOT reported empty (fixes the false positive).',
		);
	}

	/** WCAG accessible-name resolution: aria-label > text > child img[alt] > title. */
	private static function accessible_name( $el ) {
		$aria = trim( (string) $el->getAttribute( 'aria-label' ) );
		if ( '' !== $aria ) {
			return $aria;
		}
		$text = trim( (string) $el->textContent );
		if ( '' !== $text ) {
			return $text;
		}
		foreach ( $el->getElementsByTagName( 'img' ) as $img ) {
			$alt = trim( (string) $img->getAttribute( 'alt' ) );
			if ( '' !== $alt ) {
				return $alt;
			}
		}
		$title = trim( (string) $el->getAttribute( 'title' ) );
		return $title;
	}

	// --- images -----------------------------------------------------------

	private static function extract_images( $dom ) {
		$missing = array();
		$total   = 0;
		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			$src = (string) $img->getAttribute( 'src' );
			if ( '' === $src || false !== strpos( $src, 'data:image/svg' ) ) {
				continue; // skip inline svg / spacer pixels.
			}
			$total++;
			if ( '' === trim( (string) $img->getAttribute( 'alt' ) ) ) {
				$missing[] = $src;
			}
		}
		return array(
			'total'        => $total,
			'missing_alt'  => count( $missing ),
			'missing_urls' => array_slice( $missing, 0, 25 ),
		);
	}

	// --- headings ---------------------------------------------------------

	private static function extract_headings( $dom ) {
		$outline = array();
		$counts  = array();
		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
			foreach ( $dom->getElementsByTagName( $tag ) as $h ) {
				$text = trim( preg_replace( '/\s+/', ' ', (string) $h->textContent ) );
				if ( '' === $text ) {
					continue;
				}
				$counts[ $tag ] = ( $counts[ $tag ] ?? 0 ) + 1;
				if ( count( $outline ) < 60 ) {
					$outline[] = array( 'level' => $tag, 'text' => mb_substr( $text, 0, 80 ) );
				}
			}
		}
		return array(
			'counts'  => $counts,
			'h1_count' => $counts['h1'] ?? 0,
			'outline' => $outline,
		);
	}

	private static function snippet( $el ) {
		$html = '';
		if ( $el->ownerDocument ) {
			$html = (string) $el->ownerDocument->saveHTML( $el );
		}
		$html = preg_replace( '/\s+/', ' ', $html );
		return mb_substr( trim( $html ), 0, 160 );
	}
}

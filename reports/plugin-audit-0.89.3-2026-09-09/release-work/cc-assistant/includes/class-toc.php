<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.46.0 — Auto Table of Contents for single posts.
 *
 * Reads H2/H3 from the rendered post content, gives each one a stable anchor id
 * (reusing any id already present), and injects a numbered, collapsible TOC
 * immediately above the first paragraph. Entries are auto-numbered (1, 1.1 via
 * CSS counters), the box collapses via a native <details>/<summary> (no JS), the
 * page scrolls smoothly to a heading (respecting prefers-reduced-motion, with
 * scroll-margin so targets aren't hidden under a sticky header), and a tiny
 * IntersectionObserver highlights the TOC link of the section in view. Replaces
 * flaky third-party TOC plugins with a reliable, self-contained one.
 *
 * Controls:
 *  - option `cc_assistant_toc_enabled` (default false) — site-wide on/off,
 *    toggled from cc-assistant → Settings → General.
 *  - postmeta `_cc_assistant_toc_disabled` — per-post opt-out.
 *  - filter `cc_assistant_toc_min_headings` (default 3) — minimum headings
 *    before a TOC is worth showing.
 *  - filter `cc_assistant_toc_post_types` (default ['post']).
 *
 * Hooked on `the_content` at priority 20 (after wpautop) so headings are intact.
 */
class CC_Assistant_TOC {

	/** the_content filter entrypoint. */
	public static function filter_content( $content ) {
		if ( is_admin() || is_feed() ) {
			return $content;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$types = (array) apply_filters( 'cc_assistant_toc_post_types', array( 'post' ) );
		if ( ! is_singular( $types ) ) {
			return $content;
		}
		if ( ! get_option( 'cc_assistant_toc_enabled', false ) ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( $post_id && get_post_meta( $post_id, '_cc_assistant_toc_disabled', true ) ) {
			return $content;
		}
		return self::inject( $content );
	}

	/** Add ids to H2/H3, collect them, and prepend the TOC if there are enough. */
	public static function inject( $content ) {
		$min   = (int) apply_filters( 'cc_assistant_toc_min_headings', 3 );
		$items = array();
		$used  = array();

		$content = preg_replace_callback(
			'#<h([23])\b([^>]*)>(.*?)</h\1>#is',
			function ( $m ) use ( &$items, &$used ) {
				$level = (int) $m[1];
				$attrs = $m[2];
				$inner = $m[3];
				$text  = trim( html_entity_decode( wp_strip_all_tags( $inner ), ENT_QUOTES ) );
				if ( '' === $text ) {
					return $m[0]; // skip empty headings
				}
				if ( preg_match( '#\bid\s*=\s*["\']([^"\']+)["\']#i', $attrs, $idm ) ) {
					$id = $idm[1];
				} else {
					$id     = self::unique_slug( $text, $used );
					$attrs .= ' id="' . esc_attr( $id ) . '"';
				}
				$used[ $id ] = true;
				$items[]     = array(
					'level' => $level,
					'text'  => $text,
					'id'    => $id,
				);
				return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
			},
			$content
		);

		if ( count( $items ) < $min ) {
			return $content;
		}

		$toc = self::style() . self::markup( $items );

		// Place the TOC immediately above the first paragraph. Falls back to the
		// top of the content if the post has no <p> (rare).
		if ( preg_match( '/<p[\s>]/i', $content, $pm, PREG_OFFSET_CAPTURE ) ) {
			$pos     = (int) $pm[0][1];
			$content = substr( $content, 0, $pos ) . $toc . substr( $content, $pos );
		} else {
			$content = $toc . $content;
		}

		// Scroll-spy script goes at the very END so every heading id already
		// exists in the DOM when it runs.
		return $content . self::script();
	}

	private static function unique_slug( $text, $used ) {
		$slug = sanitize_title( $text );
		if ( '' === $slug ) {
			$slug = 'section';
		}
		$base = $slug;
		$i    = 2;
		while ( isset( $used[ $slug ] ) ) {
			$slug = $base . '-' . $i;
			$i++;
		}
		return $slug;
	}

	private static function markup( $items ) {
		$li = '';
		foreach ( $items as $it ) {
			$li .= '<li class="cc-toc__item cc-toc__l' . (int) $it['level'] . '">'
				. '<a href="#' . esc_attr( $it['id'] ) . '">' . esc_html( $it['text'] ) . '</a></li>';
		}
		return '<nav class="cc-toc" role="navigation" aria-label="Table of contents">'
			. '<details class="cc-toc__box" open>'
			. '<summary class="cc-toc__title">Table of Contents</summary>'
			. '<ul class="cc-toc__list">' . $li . '</ul>'
			. '</details>'
			. '</nav>';
	}

	/** Inline CSS, printed once per request. Brand-neutral; links inherit the theme color. */
	private static function style() {
		static $printed = false;
		if ( $printed ) {
			return '';
		}
		$printed = true;
		return '<style id="cc-toc-style">'
			. 'html{scroll-behavior:smooth;}'
			. '@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto;}}'
			. 'h2[id],h3[id]{scroll-margin-top:90px;}'
			. '.cc-toc{margin:0 0 28px;}'
			. '.cc-toc__box{padding:14px 22px;background:#f6f7f9;border:1px solid #e3e6ea;border-radius:6px;}'
			. '.cc-toc__title{cursor:pointer;font-weight:700;font-size:16px;line-height:1.3;list-style:none;}'
			. '.cc-toc__title::-webkit-details-marker{display:none;}'
			. '.cc-toc__title::before{content:"\\25B8";display:inline-block;margin-right:8px;font-size:0.8em;transition:transform .2s ease;}'
			. '.cc-toc__box[open] .cc-toc__title::before{transform:rotate(90deg);}'
			. '.cc-toc__list{margin:12px 0 0;padding:0;list-style:none;counter-reset:cc-h2;}'
			. '.cc-toc__item{margin:6px 0;line-height:1.45;}'
			. '.cc-toc__item a{color:inherit;text-decoration:none;}'
			. '.cc-toc__item a:hover,.cc-toc__item a:focus{text-decoration:underline;}'
			. '.cc-toc__item a.cc-toc--active{font-weight:700;text-decoration:underline;}'
			. '.cc-toc__l2{counter-increment:cc-h2;counter-reset:cc-h3;}'
			. '.cc-toc__l2 a::before{content:counter(cc-h2) ". ";font-weight:600;}'
			. '.cc-toc__l3{counter-increment:cc-h3;padding-left:24px;font-size:0.95em;}'
			. '.cc-toc__l3 a::before{content:counter(cc-h2) "." counter(cc-h3) " ";color:#6b7280;}'
			. '</style>';
	}

	/** Scroll-spy: highlight the TOC link of the section currently in view. Tiny, dependency-free. */
	private static function script() {
		static $printed = false;
		if ( $printed ) {
			return '';
		}
		$printed = true;
		$js = '(function(){'
			. 'var toc=document.querySelector(\'.cc-toc\');'
			. 'if(!toc||!(\'IntersectionObserver\' in window))return;'
			. 'var links={};'
			. 'toc.querySelectorAll(\'a[href^="#"]\').forEach(function(a){links[decodeURIComponent(a.getAttribute(\'href\').slice(1))]=a;});'
			. 'var heads=[];Object.keys(links).forEach(function(id){var el=document.getElementById(id);if(el)heads.push(el);});'
			. 'if(!heads.length)return;'
			. 'var current=null;'
			. 'var obs=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){if(current&&links[current])links[current].classList.remove(\'cc-toc--active\');current=e.target.id;if(links[current])links[current].classList.add(\'cc-toc--active\');}});},{rootMargin:\'-90px 0px -70% 0px\'});'
			. 'heads.forEach(function(h){obs.observe(h);});'
			. '})();';
		return '<script id="cc-toc-js">' . $js . '</script>';
	}
}

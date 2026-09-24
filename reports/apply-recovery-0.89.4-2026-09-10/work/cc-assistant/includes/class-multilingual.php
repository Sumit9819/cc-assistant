<?php
/**
 * Multilingual support — single source of truth for WPML / Polylang /
 * TranslatePress detection and per-post language operations.
 *
 * Every public method is null-safe: when no engine is active the methods
 * return sensible single-language defaults so callers can use them
 * unconditionally without `if ( is_active() )` branches everywhere.
 *
 * Per-post language calls are cached per request because rebuild_graph,
 * find_clusters, and pre-publish checks all hit them in tight loops over
 * hundreds of posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Multilingual {

	private static $lang_cache         = array(); // post_id => lang code
	private static $translations_cache = array(); // post_id => array
	private static $front_page_cache   = null;    // lang => post_id

	/**
	 * Returns 'wpml' | 'polylang' | 'translatepress' | null.
	 */
	public static function engine() {
		static $engine = false;
		if ( false !== $engine ) {
			return $engine;
		}
		// Polylang FIRST: it defines ICL_LANGUAGE_CODE as a WPML-compat shim,
		// so checking the WPML constant first would misidentify Polylang sites
		// as WPML and the WPML filter branch (wpml_element_trid) returns
		// nothing on Polylang because the compat shim is incomplete.
		if ( function_exists( 'pll_current_language' ) ) {
			$engine = 'polylang';
		} elseif ( class_exists( 'SitePress' ) ) {
			$engine = 'wpml';
		} elseif ( defined( 'TRP_PLUGIN_VERSION' ) ) {
			$engine = 'translatepress';
		} else {
			$engine = null;
		}
		return $engine;
	}

	public static function is_active() {
		return null !== self::engine();
	}

	/**
	 * Default language ISO code (e.g. 'en'). Falls back to WP locale's
	 * leading two chars when no engine is active.
	 */
	public static function default_language() {
		$engine = self::engine();
		if ( 'wpml' === $engine && function_exists( 'apply_filters' ) ) {
			$lang = apply_filters( 'wpml_default_language', null );
			if ( $lang ) {
				return (string) $lang;
			}
		}
		if ( 'polylang' === $engine && function_exists( 'pll_default_language' ) ) {
			$lang = pll_default_language();
			if ( $lang ) {
				return (string) $lang;
			}
		}
		// TranslatePress: option 'trp_settings' has 'default-language'.
		if ( 'translatepress' === $engine ) {
			$opts = get_option( 'trp_settings' );
			if ( is_array( $opts ) && ! empty( $opts['default-language'] ) ) {
				return strtolower( substr( (string) $opts['default-language'], 0, 2 ) );
			}
		}
		return strtolower( substr( get_locale() ?: 'en', 0, 2 ) );
	}

	/**
	 * All languages this site publishes in. Returns at least the default.
	 */
	public static function all_languages() {
		$engine = self::engine();
		if ( 'wpml' === $engine && function_exists( 'apply_filters' ) ) {
			$langs = apply_filters( 'wpml_active_languages', null );
			if ( is_array( $langs ) && ! empty( $langs ) ) {
				return array_values( array_map( 'strval', array_keys( $langs ) ) );
			}
		}
		if ( 'polylang' === $engine && function_exists( 'pll_languages_list' ) ) {
			$langs = pll_languages_list();
			if ( is_array( $langs ) && ! empty( $langs ) ) {
				return array_values( array_map( 'strval', $langs ) );
			}
		}
		return array( self::default_language() );
	}

	/**
	 * Language code for a post, or null if undetermined / single-lang site.
	 * Returns null on single-language sites so callers can branch on
	 * "is this site multilingual at all" via a single check.
	 */
	public static function language_of( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return null;
		}
		if ( array_key_exists( $post_id, self::$lang_cache ) ) {
			return self::$lang_cache[ $post_id ];
		}
		$engine = self::engine();
		$lang   = null;

		if ( 'wpml' === $engine && function_exists( 'apply_filters' ) ) {
			$details = apply_filters( 'wpml_post_language_details', null, $post_id );
			if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
				$lang = (string) $details['language_code'];
			}
		} elseif ( 'polylang' === $engine && function_exists( 'pll_get_post_language' ) ) {
			$got = pll_get_post_language( $post_id, 'slug' );
			if ( $got ) {
				$lang = (string) $got;
			}
		}
		// TranslatePress doesn't store per-post language — the same post is
		// served in multiple languages with on-the-fly translation. We treat
		// every post as default-language for TRP.
		if ( null === $lang && self::is_active() ) {
			$lang = self::default_language();
		}

		self::$lang_cache[ $post_id ] = $lang;
		return $lang;
	}

	/**
	 * Translation map for a post: lang_code => post_id (includes itself).
	 * Returns empty array on single-language sites.
	 */
	public static function translations_of( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array();
		}
		if ( array_key_exists( $post_id, self::$translations_cache ) ) {
			return self::$translations_cache[ $post_id ];
		}
		$engine = self::engine();
		$out    = array();

		if ( 'wpml' === $engine && function_exists( 'apply_filters' ) ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$type = $post->post_type;
				$trid = apply_filters( 'wpml_element_trid', null, $post_id, 'post_' . $type );
				if ( $trid ) {
					$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_' . $type );
					if ( is_array( $translations ) ) {
						foreach ( $translations as $code => $row ) {
							if ( isset( $row->element_id ) ) {
								$out[ (string) $code ] = (int) $row->element_id;
							}
						}
					}
				}
			}
		} elseif ( 'polylang' === $engine ) {
			$tx = null;
			if ( function_exists( 'pll_get_post_translations' ) ) {
				$tx = pll_get_post_translations( $post_id );
			}
			// Some Polylang versions short-circuit pll_get_post_translations
			// in non-admin / REST context. Fall back to the model directly.
			if ( ( ! is_array( $tx ) || empty( $tx ) ) && function_exists( 'PLL' ) ) {
				$pll = PLL();
				if ( $pll && isset( $pll->model ) && isset( $pll->model->post ) && method_exists( $pll->model->post, 'get_translations' ) ) {
					$tx = $pll->model->post->get_translations( $post_id );
				}
			}
			if ( is_array( $tx ) ) {
				foreach ( $tx as $code => $pid ) {
					if ( (int) $pid > 0 ) {
						$out[ (string) $code ] = (int) $pid;
					}
				}
			}
		}

		self::$translations_cache[ $post_id ] = $out;
		return $out;
	}

	/**
	 * True if A and B are translations of the same content. Symmetric.
	 * Always false on single-language sites (no translations exist).
	 */
	public static function are_translations( $a, $b ) {
		$a = (int) $a;
		$b = (int) $b;
		if ( $a <= 0 || $b <= 0 || $a === $b ) {
			return false;
		}
		$tx = self::translations_of( $a );
		if ( empty( $tx ) ) {
			return false;
		}
		return in_array( $b, $tx, true );
	}

	/**
	 * Front page post_id for a given language. Falls back to the global
	 * page_on_front when no per-language front page is configured.
	 */
	public static function front_page_for_language( $lang ) {
		if ( null === self::$front_page_cache ) {
			self::$front_page_cache = self::compute_front_pages();
		}
		$lang = (string) $lang;
		if ( '' !== $lang && isset( self::$front_page_cache[ $lang ] ) ) {
			return (int) self::$front_page_cache[ $lang ];
		}
		// Fall back to whatever is the default-language front page.
		$default = self::default_language();
		if ( isset( self::$front_page_cache[ $default ] ) ) {
			return (int) self::$front_page_cache[ $default ];
		}
		// Final fallback: the global page_on_front (single-language sites).
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$front = (int) get_option( 'page_on_front' );
			if ( $front > 0 ) {
				return $front;
			}
		}
		$front = (int) url_to_postid( home_url( '/' ) );
		return $front > 0 ? $front : 0;
	}

	private static function compute_front_pages() {
		$out = array();
		$default_front = 0;
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$default_front = (int) get_option( 'page_on_front' );
		}
		if ( $default_front <= 0 ) {
			$default_front = (int) url_to_postid( home_url( '/' ) );
		}

		// Map each language to a front page. If the engine exposes
		// translations of the default front page, use those; else everyone
		// shares the same root.
		$default_lang = self::default_language();
		if ( $default_front > 0 ) {
			$out[ $default_lang ] = $default_front;
			$translations = self::translations_of( $default_front );
			foreach ( $translations as $lang => $pid ) {
				if ( $pid > 0 ) {
					$out[ (string) $lang ] = (int) $pid;
				}
			}
		}
		return $out;
	}

	/**
	 * Language code assigned to a registered nav menu (WPML/Polylang
	 * register one menu per language). Returns null on single-language
	 * sites or when the menu isn't language-tagged.
	 */
	public static function language_of_menu( $menu ) {
		if ( ! $menu || empty( $menu->term_id ) ) {
			return null;
		}
		$engine = self::engine();
		if ( 'polylang' === $engine && function_exists( 'pll_get_term_language' ) ) {
			$lang = pll_get_term_language( (int) $menu->term_id, 'slug' );
			if ( $lang ) {
				return (string) $lang;
			}
		}
		// WPML stores menu locations per language; the best signal is the
		// language of the first item in the menu.
		if ( 'wpml' === $engine && function_exists( 'wp_get_nav_menu_items' ) ) {
			$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
			if ( is_array( $items ) ) {
				foreach ( $items as $item ) {
					if ( isset( $item->type ) && 'post_type' === $item->type && (int) $item->object_id > 0 ) {
						$lang = self::language_of( (int) $item->object_id );
						if ( $lang ) {
							return $lang;
						}
					}
				}
			}
		}
		return null;
	}

	/**
	 * Reset the per-request memo caches. Called from rebuild loops that
	 * iterate the entire site so a long-running cron doesn't hold stale
	 * data in memory across phases.
	 */
	public static function reset_caches() {
		self::$lang_cache         = array();
		self::$translations_cache = array();
		self::$front_page_cache   = null;
	}

	/**
	 * True if $lang is one of the site's configured language slugs.
	 * Always false on single-language sites.
	 */
	public static function is_known_language( $lang ) {
		$lang = (string) $lang;
		if ( '' === $lang || ! self::is_active() ) {
			return false;
		}
		return in_array( $lang, self::all_languages(), true );
	}

	/**
	 * Assign $lang to $post_id in the active multilingual engine.
	 *
	 * Returns true on apparent success, WP_Error on failure (engine inactive,
	 * unknown language, post missing). Polylang exposes pll_set_post_language()
	 * directly. WPML is intentionally not supported here yet — it needs a trid
	 * dance that depends on the source post and is safer handled at the
	 * link_translations level.
	 */
	public static function set_language( $post_id, $lang ) {
		$post_id = (int) $post_id;
		$lang    = (string) $lang;
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new WP_Error( 'post_missing', sprintf( 'Post %d does not exist.', $post_id ) );
		}
		if ( ! self::is_active() ) {
			return new WP_Error( 'multilingual_inactive', 'No multilingual engine is active.' );
		}
		if ( ! self::is_known_language( $lang ) ) {
			return new WP_Error(
				'unknown_language',
				sprintf( '"%s" is not a configured language. Known: %s', $lang, implode( ', ', self::all_languages() ) )
			);
		}
		$engine = self::engine();
		if ( 'polylang' === $engine && function_exists( 'pll_set_post_language' ) ) {
			pll_set_post_language( $post_id, $lang );
			unset( self::$lang_cache[ $post_id ], self::$translations_cache[ $post_id ] );
			return true;
		}
		// WPML / TranslatePress: caller should use link_translations() or the
		// engine's native UI. We do not silently no-op so the gap is visible.
		return new WP_Error(
			'engine_unsupported',
			sprintf( 'set_language is implemented for Polylang only; active engine is "%s".', (string) $engine )
		);
	}

	/**
	 * Link a batch of translation pairs in the active multilingual engine.
	 *
	 * Each pair is a map of language_slug => post_id covering all translations
	 * of one content item, e.g. array( 'en' => 4986, 'es' => 5009 ). On
	 * Polylang the language taxonomy is set per post and then
	 * pll_save_post_translations() writes the post_translations term.
	 *
	 * $pairs:           array of pair arrays
	 * $force_language:  when true, overwrite a post's existing language if it
	 *                   conflicts with the pair. Default false (refuse the
	 *                   pair and report the conflict).
	 *
	 * Returns array(
	 *   'count'   => int,
	 *   'ok'      => int,
	 *   'failed'  => int,
	 *   'results' => array of per-pair {index, ok, linked?, errors?, pair?},
	 * )
	 * Or WP_Error if the engine isn't supported at all.
	 */
	public static function link_translations( $pairs, $force_language = false ) {
		if ( ! is_array( $pairs ) || empty( $pairs ) ) {
			return new WP_Error( 'invalid_pairs', 'pairs must be a non-empty array.' );
		}
		$engine = self::engine();
		if ( 'polylang' !== $engine ) {
			return new WP_Error(
				'engine_unsupported',
				sprintf( 'link_translations is implemented for Polylang only; active engine is "%s".', (string) $engine )
			);
		}
		if ( ! function_exists( 'pll_save_post_translations' ) || ! function_exists( 'pll_get_post_language' ) ) {
			return new WP_Error( 'polylang_functions_missing', 'Required Polylang functions are not available.' );
		}

		$results = array();
		$ok      = 0;
		$failed  = 0;

		foreach ( $pairs as $idx => $pair ) {
			if ( ! is_array( $pair ) || empty( $pair ) ) {
				$results[] = array( 'index' => $idx, 'ok' => false, 'errors' => array( 'pair must be a non-empty array' ) );
				$failed++;
				continue;
			}

			$cleaned = array();
			$errors  = array();

			foreach ( $pair as $lang => $post_id ) {
				$post_id = (int) $post_id;
				$lang    = (string) $lang;
				if ( $post_id <= 0 || '' === $lang ) {
					$errors[] = sprintf( 'invalid lang/post_id: %s => %s', $lang, $post_id );
					continue;
				}
				if ( ! self::is_known_language( $lang ) ) {
					$errors[] = sprintf( 'unknown language "%s" — site languages are: %s', $lang, implode( ', ', self::all_languages() ) );
					continue;
				}
				if ( ! get_post( $post_id ) ) {
					$errors[] = sprintf( 'post %d (%s) does not exist', $post_id, $lang );
					continue;
				}
				$detected = pll_get_post_language( $post_id, 'slug' );
				if ( $detected && $detected !== $lang ) {
					if ( ! $force_language ) {
						$errors[] = sprintf(
							'post %d is in language "%s" but pair says "%s" — refusing to overwrite. Pass force_language=true if intentional.',
							$post_id,
							$detected,
							$lang
						);
						continue;
					}
					if ( function_exists( 'pll_set_post_language' ) ) {
						pll_set_post_language( $post_id, $lang );
					}
				} elseif ( ! $detected && function_exists( 'pll_set_post_language' ) ) {
					pll_set_post_language( $post_id, $lang );
				}
				$cleaned[ $lang ] = $post_id;
			}

			if ( ! empty( $errors ) ) {
				$results[] = array( 'index' => $idx, 'ok' => false, 'pair' => $pair, 'errors' => $errors );
				$failed++;
				continue;
			}

			pll_save_post_translations( $cleaned );

			// Invalidate per-request caches for every post in the cleaned pair
			// so subsequent reads in the same request see the new state.
			foreach ( $cleaned as $pid ) {
				unset( self::$lang_cache[ (int) $pid ], self::$translations_cache[ (int) $pid ] );
			}

			$results[] = array( 'index' => $idx, 'ok' => true, 'linked' => $cleaned );
			$ok++;
		}

		return array(
			'count'   => count( $pairs ),
			'ok'      => $ok,
			'failed'  => $failed,
			'results' => $results,
		);
	}
}

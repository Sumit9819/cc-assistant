<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-site memory: auto-detected stack facts (SEO plugin, page builder, etc.)
 * plus free-form notes the user maintains. Claude reads this at session start
 * so it never tries to write a Yoast meta key on a Rank Math site.
 */
require_once __DIR__ . '/class-memory-policy.php';
class CC_Assistant_Site_Memory {

	public static function full_memory() {
		return array(
			'detected'   => self::detect(),
			'notes'      => self::get_notes(),
			'memory_consistency' => CC_Assistant_Memory_Policy::audit( self::get_notes() ),
			'updated_at' => get_option( 'cc_assistant_site_notes_updated', '' ),
		);
	}

	public static function detect() {
		require_once CC_ASSISTANT_DIR . 'includes/class-industry-profile.php';
		$industry = CC_Assistant_Industry_Profile::get();
		return array(
			'seo'           => self::detect_seo(),
			'page_builder'  => self::detect_page_builder(),
			'multilingual'  => self::detect_multilingual(),
			'commerce'      => self::detect_commerce(),
			'caching'       => self::detect_caching(),
			'forms'         => self::detect_forms(),
			'schema'        => self::detect_schema(),
			'theme'         => self::detect_theme(),
			'permalinks'    => self::detect_permalinks(),
			'industry'      => array(
				'slug'       => isset( $industry['industry'] ) ? $industry['industry'] : 'general',
				'label'      => CC_Assistant_Industry_Profile::label( isset( $industry['industry'] ) ? $industry['industry'] : 'general' ),
				'source'     => isset( $industry['source'] ) ? $industry['source'] : 'auto',
				'confidence' => isset( $industry['confidence'] ) ? (int) $industry['confidence'] : 0,
			),
			'detected_at'   => current_time( 'mysql' ),
		);
	}

	public static function detect_seo() {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			return array(
				'plugin'      => 'yoast',
				'plugin_name' => 'Yoast SEO',
				'version'     => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : null,
				'meta_keys'   => array(
					'title'         => '_yoast_wpseo_title',
					'description'   => '_yoast_wpseo_metadesc',
					'focus_keyword' => '_yoast_wpseo_focuskw',
					'canonical'     => '_yoast_wpseo_canonical',
					'og_title'      => '_yoast_wpseo_opengraph-title',
					'og_description' => '_yoast_wpseo_opengraph-description',
					// v0.71.4: Yoast stores robots as a per-directive STRING flag
					// ('1' = noindex, '2' = index), not as an array like Rank Math.
					// The value transformer in handle_draft_seo_meta() converts.
					'robots'        => '_yoast_wpseo_meta-robots-noindex',
				),
			);
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) || class_exists( 'RankMath\Helper' ) ) {
			return array(
				'plugin'      => 'rank-math',
				'plugin_name' => 'Rank Math',
				'version'     => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : null,
				'meta_keys'   => array(
					'title'         => 'rank_math_title',
					'description'   => 'rank_math_description',
					'focus_keyword' => 'rank_math_focus_keyword',
					'canonical'     => 'rank_math_canonical_url',
					'og_title'      => 'rank_math_facebook_title',
					'og_description' => 'rank_math_facebook_description',
					// v0.71.4: Rank Math stores robots as a serialized ARRAY of
					// directive strings. It CANNOT be written as a pre-serialized
					// string - WordPress maybe_serialize() double-serializes any
					// string that already looks serialized, so the reader gets a
					// string back and the override is silently ignored.
					'robots'        => 'rank_math_robots',
				),
			);
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return array(
				'plugin'      => 'aioseo',
				'plugin_name' => 'All in One SEO',
				'version'     => defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : null,
				'meta_keys'   => array(
					'title'       => '_aioseo_title',
					'description' => '_aioseo_description',
				),
				'note'        => 'AIOSEO stores most data in custom tables, not postmeta. Use their API for full edits. Robots/noindex is deliberately NOT exposed here: it is not in postmeta at all, so a write would silently do nothing.',
			);
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return array(
				'plugin'      => 'seopress',
				'plugin_name' => 'SEOPress',
				'meta_keys'   => array(
					'title'       => '_seopress_titles_title',
					'description' => '_seopress_titles_desc',
					// v0.71.4: SEOPress uses 'yes' to MEAN noindex on this key.
					'robots'      => '_seopress_robots_index',
				),
			);
		}
		return array(
			'plugin'      => null,
			'plugin_name' => null,
			'meta_keys'   => array(),
			'note'        => 'No SEO plugin detected. SEO meta will fall back to post excerpt.',
		);
	}

	public static function detect_page_builder() {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			return array(
				'plugin'      => 'elementor',
				'plugin_name' => 'Elementor',
				'version'     => ELEMENTOR_VERSION,
				'pro'         => defined( 'ELEMENTOR_PRO_VERSION' ),
			);
		}
		if ( defined( 'FL_BUILDER_VERSION' ) ) {
			return array( 'plugin' => 'beaver-builder', 'plugin_name' => 'Beaver Builder' );
		}
		if ( defined( 'ET_BUILDER_VERSION' ) ) {
			return array( 'plugin' => 'divi', 'plugin_name' => 'Divi Builder' );
		}
		if ( defined( 'WPB_VC_VERSION' ) ) {
			return array( 'plugin' => 'wpbakery', 'plugin_name' => 'WPBakery Page Builder' );
		}
		if ( function_exists( 'register_block_type' ) ) {
			return array( 'plugin' => 'gutenberg', 'plugin_name' => 'Gutenberg (block editor)' );
		}
		return array( 'plugin' => null );
	}

	public static function detect_multilingual() {
		// Polylang FIRST: it defines ICL_LANGUAGE_CODE as a WPML-compat shim,
		// so checking the WPML constant first misidentifies Polylang sites.
		if ( function_exists( 'pll_current_language' ) ) {
			return array( 'plugin' => 'polylang', 'plugin_name' => 'Polylang' );
		}
		if ( class_exists( 'SitePress' ) ) {
			return array( 'plugin' => 'wpml', 'plugin_name' => 'WPML' );
		}
		if ( defined( 'TRP_PLUGIN_VERSION' ) ) {
			return array( 'plugin' => 'translatepress', 'plugin_name' => 'TranslatePress' );
		}
		return array( 'plugin' => null );
	}

	public static function detect_commerce() {
		if ( class_exists( 'WooCommerce' ) ) {
			$version = defined( 'WC_VERSION' ) ? WC_VERSION : null;
			return array( 'plugin' => 'woocommerce', 'plugin_name' => 'WooCommerce', 'version' => $version );
		}
		if ( class_exists( 'Easy_Digital_Downloads' ) ) {
			return array( 'plugin' => 'edd', 'plugin_name' => 'Easy Digital Downloads' );
		}
		return array( 'plugin' => null );
	}

	public static function detect_caching() {
		$found = array();
		if ( defined( 'W3TC_VERSION' ) ) {
			$found[] = 'w3-total-cache';
		}
		if ( defined( 'WPCACHEHOME' ) ) {
			$found[] = 'wp-super-cache';
		}
		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			$found[] = 'wp-rocket';
		}
		if ( defined( 'LSCWP_V' ) ) {
			$found[] = 'litespeed-cache';
		}
		if ( defined( 'WPFC_MAIN_PATH' ) ) {
			$found[] = 'wp-fastest-cache';
		}
		if ( class_exists( 'autoptimizeMain' ) ) {
			$found[] = 'autoptimize';
		}
		return $found;
	}

	public static function detect_forms() {
		$found = array();
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$found[] = 'elementor-forms';
		}
		if ( class_exists( 'WPCF7' ) ) {
			$found[] = 'contact-form-7';
		}
		if ( defined( 'GF_PLUGIN_DIR_PATH' ) || class_exists( 'GFForms' ) ) {
			$found[] = 'gravity-forms';
		}
		if ( defined( 'WPFORMS_VERSION' ) ) {
			$found[] = 'wpforms';
		}
		if ( class_exists( 'Ninja_Forms' ) ) {
			$found[] = 'ninja-forms';
		}
		return $found;
	}

	public static function detect_schema() {
		if ( class_exists( 'Schema_WP' ) || defined( 'SCHEMA_WP_VERSION' ) ) {
			return array( 'plugin' => 'schema-wp' );
		}
		if ( class_exists( 'Schema_Pro' ) ) {
			return array( 'plugin' => 'schema-pro' );
		}
		// Yoast and Rank Math both ship schema, note that.
		return array( 'plugin' => null, 'note' => 'Schema may be provided by your SEO plugin.' );
	}

	public static function detect_theme() {
		$theme = wp_get_theme();
		return array(
			'name'     => $theme->get( 'Name' ),
			'version'  => $theme->get( 'Version' ),
			'author'   => $theme->get( 'Author' ),
			'is_child' => is_child_theme(),
			'parent'   => is_child_theme() ? $theme->parent()->get( 'Name' ) : null,
		);
	}

	public static function detect_permalinks() {
		return array(
			'structure' => get_option( 'permalink_structure' ),
			'is_pretty' => '' !== get_option( 'permalink_structure' ),
		);
	}

	public static function get_notes() {
		return (string) get_option( 'cc_assistant_site_notes', '' );
	}

	public static function set_notes( $notes ) {
		$result = update_option( 'cc_assistant_site_notes', (string) $notes, false );
		update_option( 'cc_assistant_site_notes_updated', current_time( 'mysql' ) );
		return $result;
	}

	const NOTES_SOFT_CAP_BYTES = 30 * 1024; // 30 KB. Whoami only surfaces the last ~500 chars; older notes still live in the blob until consolidated.

	const SECTION_RULES    = 'rules';     // permanent: site-specific conventions, stack quirks, do/don'ts
	const SECTION_DECISIONS = 'decisions'; // architecture decisions worth keeping; rationale + tradeoff
	const SECTION_SESSIONS = 'sessions';  // append-only session log (default; what the model writes daily)

	/**
	 * Notes are now structured into three top-level sections. Rules and
	 * Decisions persist forever; Sessions is the timestamped append-only
	 * log the model writes to at the end of every session. The notes blob
	 * stored in cc_assistant_site_notes is a single markdown document with
	 * three "# Rules", "# Decisions", "# Sessions" H1 sections. Older
	 * unstructured blobs are auto-treated as the Sessions section so the
	 * upgrade is transparent.
	 *
	 * Append a note. Default section is sessions (preserves the historical
	 * behaviour: append timestamped block to the bottom of the blob).
	 */
	public static function append_note( $note, $section = self::SECTION_SESSIONS ) {
		$clean = self::sanitize_note( (string) $note );
		if ( '' === $clean ) {
			return false;
		}
		$section = self::normalize_section( $section );

		$existing = self::get_notes();
		$parsed   = self::parse_sections( $existing );

		// Dedup: identical back-to-back appends are a no-op regardless of
		// section. Catches the model retrying the same memory log within
		// seconds.
		if ( self::section_last_body( $parsed[ $section ] ) === $clean ) {
			return true;
		}

		// Sessions get a timestamped H2 header. Rules and Decisions are
		// freer-form: append as a bullet under the section so consolidation
		// is easy and the section reads as a checklist.
		if ( self::SECTION_SESSIONS === $section ) {
			$stamp = '## ' . current_time( 'Y-m-d H:i' ) . "\n";
			$parsed[ $section ] = trim( (string) $parsed[ $section ] ) . "\n\n" . $stamp . $clean . "\n";
		} else {
			$parsed[ $section ] = trim( (string) $parsed[ $section ] ) . "\n- " . $clean;
		}

		$new = self::serialize_sections( $parsed );

		if ( strlen( $new ) > self::NOTES_SOFT_CAP_BYTES ) {
			set_transient( 'cc_assistant_notes_oversized', strlen( $new ), DAY_IN_SECONDS );
		}

		// 0.14: record the note in the rolling activity log so a fresh chat
		// session sees the structured event "Claude appended a note about X"
		// in addition to the free-form Sessions tail. Rule + Decision adds
		// get their own activity types so the next session can spot when
		// permanent guidance was added without rereading the whole blob.
		if ( class_exists( 'CC_Assistant_Activity_Log' ) ) {
			$activity_type = ( self::SECTION_RULES === $section )
				? CC_Assistant_Activity_Log::TYPE_RULE_ADDED
				: CC_Assistant_Activity_Log::TYPE_NOTE_ADDED;
			CC_Assistant_Activity_Log::record(
				$activity_type,
				sprintf( '[%s] %s', $section, mb_substr( $clean, 0, 200 ) ),
				null,
				null,
				'claude'
			);
		}

		return self::set_notes( $new );
	}

	/**
	 * Normalize a section parameter from the model. Falls back to 'sessions'
	 * for backward compat with unstructured callers.
	 */
	public static function normalize_section( $section ) {
		$s = sanitize_key( (string) $section );
		if ( in_array( $s, array( self::SECTION_RULES, self::SECTION_DECISIONS, self::SECTION_SESSIONS ), true ) ) {
			return $s;
		}
		// Singular-form aliases as a kindness.
		if ( 'rule' === $s ) { return self::SECTION_RULES; }
		if ( 'decision' === $s ) { return self::SECTION_DECISIONS; }
		if ( 'session' === $s || 'log' === $s ) { return self::SECTION_SESSIONS; }
		return self::SECTION_SESSIONS;
	}

	/**
	 * Parse the notes blob into the three structured sections. Backward-
	 * compatible: legacy unstructured blobs (pre-v0.5.0) end up entirely in
	 * the Sessions section so nothing is lost.
	 */
	public static function parse_sections( $blob ) {
		$blob = (string) $blob;
		$out  = array(
			self::SECTION_RULES     => '',
			self::SECTION_DECISIONS => '',
			self::SECTION_SESSIONS  => '',
		);
		if ( '' === trim( $blob ) ) {
			return $out;
		}
		// Look for the explicit "# Rules", "# Decisions", "# Sessions" H1 markers.
		// Match case-insensitive, anchored at start-of-line.
		$pattern = '/^#\s+(Rules|Decisions|Sessions)\s*$/im';
		if ( ! preg_match_all( $pattern, $blob, $matches, PREG_OFFSET_CAPTURE ) ) {
			// Legacy blob — treat the whole thing as Sessions.
			$out[ self::SECTION_SESSIONS ] = trim( $blob );
			return $out;
		}
		// Walk the matches and slice the blob between H1s.
		$count   = count( $matches[0] );
		for ( $i = 0; $i < $count; $i++ ) {
			$header_start = $matches[0][ $i ][1];
			$header_text  = $matches[0][ $i ][0];
			$content_start = $header_start + strlen( $header_text );
			$content_end   = ( $i + 1 < $count ) ? $matches[0][ $i + 1 ][1] : strlen( $blob );
			$body          = trim( substr( $blob, $content_start, $content_end - $content_start ) );
			$key           = strtolower( $matches[1][ $i ][0] );
			if ( isset( $out[ $key ] ) ) {
				$out[ $key ] = $body;
			}
		}
		// Anything before the first H1 is treated as legacy Sessions content.
		$first_start = $matches[0][0][1];
		if ( $first_start > 0 ) {
			$preamble = trim( substr( $blob, 0, $first_start ) );
			if ( '' !== $preamble ) {
				$out[ self::SECTION_SESSIONS ] = trim( $preamble . "\n\n" . $out[ self::SECTION_SESSIONS ] );
			}
		}
		return $out;
	}

	/**
	 * Reassemble the parsed sections into a single markdown blob with the
	 * three H1 markers in canonical order. Empty sections still get their
	 * H1 header so the structure stays consistent across writes.
	 */
	public static function serialize_sections( $parsed ) {
		$rules     = isset( $parsed[ self::SECTION_RULES ] ) ? trim( (string) $parsed[ self::SECTION_RULES ] ) : '';
		$decisions = isset( $parsed[ self::SECTION_DECISIONS ] ) ? trim( (string) $parsed[ self::SECTION_DECISIONS ] ) : '';
		$sessions  = isset( $parsed[ self::SECTION_SESSIONS ] ) ? trim( (string) $parsed[ self::SECTION_SESSIONS ] ) : '';
		$out  = "# Rules\n\n" . $rules . "\n\n";
		$out .= "# Decisions\n\n" . $decisions . "\n\n";
		$out .= "# Sessions\n\n" . $sessions . "\n";
		return trim( $out ) . "\n";
	}

	/**
	 * Body of the latest entry within a section, used for dedup.
	 */
	private static function section_last_body( $section_text ) {
		$section_text = (string) $section_text;
		if ( '' === $section_text ) {
			return '';
		}
		// Sessions: extract the latest "## YYYY-MM-DD HH:MM" block.
		$pos = strrpos( $section_text, "\n## " );
		if ( false !== $pos || 0 === strpos( $section_text, '## ' ) ) {
			$tail = false === $pos ? $section_text : substr( $section_text, $pos + 1 );
			$tail = preg_replace( '/^##\s+\S+\s+\S+\s*\n/', '', $tail );
			return trim( (string) $tail );
		}
		// Rules / Decisions: latest bullet starting with "- ".
		$lines = preg_split( '/\R/', $section_text );
		$last  = '';
		foreach ( (array) $lines as $line ) {
			if ( preg_match( '/^\s*-\s+(.+)$/', $line, $m ) ) {
				$last = trim( $m[1] );
			}
		}
		return $last;
	}

	/**
	 * Strip the kinds of garbage the model accidentally writes into a note:
	 *   - Literal XML/HTML-ish tag fragments (</invoke>, </function_calls>, etc.)
	 *     that leak from tool-call boilerplate when the model forgets to close
	 *     a JSON value before pasting prose.
	 *   - Control characters and zero-width characters that break grep + greps
	 *     on the notes blob downstream.
	 *   - Non-breaking spaces and double spaces.
	 *   - Anything that isn't valid UTF-8.
	 *
	 * Never rewrites the meaning of text — only strips characters and tag-shaped
	 * residue. Returns the cleaned note (may be empty if the input was all junk).
	 */
	public static function sanitize_note( $note ) {
		$note = (string) $note;

		// Drop anything that isn't valid UTF-8 outright.
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $note, 'UTF-8' ) ) {
			$note = mb_convert_encoding( $note, 'UTF-8', 'UTF-8' );
		}

		// Strip C0 control chars except \t, \n, \r.
		$note = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $note );

		// Strip zero-width characters.
		$note = preg_replace( '/[\xE2\x80\x8B-\xE2\x80\x8F]/u', '', $note );

		// Strip literal closing-tag fragments that look like tool-call residue
		// when the model accidentally writes raw XML scaffolding into a note.
		// We only strip recognised harness-side tags so we never accidentally
		// strip user code samples discussing real HTML.
		$tool_tags = array( 'invoke', 'function_calls', 'parameter', 'antml:function_calls', 'antml:invoke', 'antml:parameter' );
		foreach ( $tool_tags as $t ) {
			$note = preg_replace( '#</?' . preg_quote( $t, '#' ) . '\b[^>]*>#i', '', $note );
		}

		// Collapse non-breaking spaces and runs of regular spaces.
		$note = str_replace( "\xC2\xA0", ' ', $note );
		$note = preg_replace( '/[ \t]{2,}/', ' ', $note );

		// Trim leading/trailing whitespace and stray surrounding quotes.
		$note = trim( $note );
		$note = trim( $note, "\"' \t\r\n" );

		return $note;
	}

	/**
	 * Resolve a logical SEO field name (title, description, focus_keyword, etc.)
	 * to the actual postmeta key for the detected SEO plugin.
	 */
	public static function resolve_seo_meta_key( $logical_key ) {
		$seo = self::detect_seo();
		if ( empty( $seo['meta_keys'] ) || ! isset( $seo['meta_keys'][ $logical_key ] ) ) {
			return null;
		}
		return $seo['meta_keys'][ $logical_key ];
	}
}

<?php
/**
 * v0.19 build_service_page macro tool.
 *
 * One-call clone of a working service-page pillar's Elementor tree into a
 * NEW draft page with text replacements applied. Replaces the 13-container
 * manual build that produced the misshapen Laser Genesis page in v0.18.x:
 *
 *   - reads $mirror_post_id's _elementor_data
 *   - deep-clones the entire tree with fresh ids (so the source page is
 *     untouched and the new tree has no id collisions)
 *   - drops any subtree whose root id is in $skip_widget_ids (used to
 *     omit sections like a pricing menu that the new page should not
 *     inherit)
 *   - runs str_ireplace across all text-bearing settings using the
 *     $replacements ordered map ("IV Therapy" -> "Laser Genesis", etc.)
 *   - runs lint on the assembled body text (placeholder/em-dash/AI-tells)
 *   - creates a new WP_Post (status=draft) and writes the tree + edit_mode
 *   - queues ONE publish_draft pending change so the operator approves
 *     the whole page in a single click
 *
 * The mirror page's container-level settings (flex_direction, flex_wrap,
 * widths, etc.) are preserved byte-for-byte, which is the part that
 * manual-build mirroring kept getting wrong.
 *
 * @since 0.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Build_Service_Page {

	/**
	 * Orchestrator. Returns associative array on success or WP_Error on failure.
	 *
	 * @param array $args {
	 *     @type int    $mirror_post_id   Required. Post id of working pillar.
	 *     @type string $title            Required. New page post_title.
	 *     @type string $slug             Optional. Derived from title if empty.
	 *     @type string $post_type        Optional. Default 'page'.
	 *     @type array  $replacements     Optional. Ordered search => replace map.
	 *     @type array  $skip_widget_ids  Optional. Drop subtrees with these ids.
	 *     @type string $seo_title        Optional. Rank Math/Yoast title to set.
	 *     @type string $seo_description  Optional. Rank Math/Yoast description.
	 *     @type string $focus_keyword    Optional. Rank Math focus_keyword.
	 *     @type bool   $dry_run          Optional. Validate without writing.
	 *     @type bool   $override_lint    Optional. Bypass hard lint violations.
	 * }
	 * @return array|WP_Error
	 */
	public static function build( array $args ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-builder.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';

		$mirror_id        = isset( $args['mirror_post_id'] ) ? (int) $args['mirror_post_id'] : 0;
		$title            = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
		$slug             = isset( $args['slug'] ) ? trim( (string) $args['slug'] ) : '';
		$post_type        = isset( $args['post_type'] ) ? (string) $args['post_type'] : 'page';
		$replacements     = isset( $args['replacements'] ) && is_array( $args['replacements'] ) ? $args['replacements'] : array();
		$skip_widget_ids  = isset( $args['skip_widget_ids'] ) && is_array( $args['skip_widget_ids'] ) ? array_values( array_filter( $args['skip_widget_ids'], 'is_string' ) ) : array();
		$seo_title        = isset( $args['seo_title'] ) ? (string) $args['seo_title'] : '';
		$seo_description  = isset( $args['seo_description'] ) ? (string) $args['seo_description'] : '';
		$focus_keyword    = isset( $args['focus_keyword'] ) ? (string) $args['focus_keyword'] : '';
		$target_language  = isset( $args['target_language'] ) ? sanitize_key( (string) $args['target_language'] ) : '';
		$dry_run          = ! empty( $args['dry_run'] );
		$override_lint    = ! empty( $args['override_lint'] );

		if ( $mirror_id <= 0 ) {
			return new WP_Error( 'invalid_payload', 'mirror_post_id is required.', array( 'status' => 400 ) );
		}
		if ( '' === $title ) {
			return new WP_Error( 'invalid_payload', 'title is required.', array( 'status' => 400 ) );
		}
		$allowed_types = get_option( 'cc_assistant_allowed_post_types', array( 'page', 'post' ) );
		if ( ! in_array( $post_type, (array) $allowed_types, true ) ) {
			return new WP_Error( 'post_type_not_allowed', 'post_type is not in the allowlist.', array( 'status' => 403 ) );
		}

		$mirror = get_post( $mirror_id );
		if ( ! $mirror ) {
			return new WP_Error( 'mirror_not_found', sprintf( 'Mirror post %d not found.', $mirror_id ), array( 'status' => 404 ) );
		}
		$mirror_raw = get_post_meta( $mirror_id, '_elementor_data', true );
		if ( empty( $mirror_raw ) ) {
			return new WP_Error( 'mirror_no_elementor', sprintf( 'Mirror post %d has no _elementor_data — cannot clone.', $mirror_id ), array( 'status' => 422 ) );
		}
		$mirror_tree = json_decode( $mirror_raw, true );
		if ( ! is_array( $mirror_tree ) ) {
			return new WP_Error( 'mirror_decode_failed', 'Could not parse mirror page _elementor_data.', array( 'status' => 500 ) );
		}

		// Cross-language clone awareness. Polylang/WPML sites can mix EN/ES
		// pillars and the str_ireplace pass cannot translate the body — the
		// new page would land with body text in the wrong language. If a
		// target_language was supplied, hard fail when it doesn't match the
		// mirror. Otherwise soft warn through the response so the operator
		// reviews before approval.
		$mirror_language = '';
		$language_warning = '';
		if ( class_exists( 'CC_Assistant_Multilingual' ) || file_exists( CC_ASSISTANT_DIR . 'includes/class-multilingual.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';
			if ( CC_Assistant_Multilingual::is_active() ) {
				$mirror_language = (string) CC_Assistant_Multilingual::language_of( $mirror_id );
				if ( '' !== $target_language && '' !== $mirror_language && $target_language !== $mirror_language ) {
					return new WP_Error(
						'cross_language_clone',
						sprintf(
							'Mirror post %d is in language "%s" but target_language is "%s". str_ireplace cannot translate the body; clone from a same-language pillar or remove target_language to soft-warn instead.',
							$mirror_id,
							$mirror_language,
							$target_language
						),
						array( 'status' => 422 )
					);
				}
				if ( '' === $target_language && '' !== $mirror_language ) {
					$language_warning = sprintf(
						'Mirror is in language "%s". Text replacements will not translate the body. If the new page should be in a different language, pass target_language and clone from a same-language pillar instead.',
						$mirror_language
					);
				}
			}
		}

		// Mirror quality gate. Clone-propagated defects (bad heading depth,
		// alt-less images, contrast issues, thin word count) become defects
		// on the new page automatically. Refuse to clone a mirror whose
		// pre-publish pass rate is below 70% so the operator fixes the
		// pillar first. Override with override_mirror_quality=true when
		// you've reviewed the failures and accept them.
		$override_mirror = ! empty( $args['override_mirror_quality'] );
		$mirror_quality  = self::audit_mirror_quality( $mirror_id );
		if ( ! $override_mirror && ! $mirror_quality['ok'] ) {
			return new WP_Error(
				'mirror_quality_failed',
				sprintf(
					'Mirror post %d failed pre-publish checks (%d of %d passing, threshold 70%%). Failing: %s. Fix the pillar first, or resubmit with override_mirror_quality=true.',
					$mirror_id,
					$mirror_quality['pass_count'],
					$mirror_quality['total'],
					implode( ', ', $mirror_quality['failing'] )
				),
				array( 'status' => 422, 'mirror_quality' => $mirror_quality )
			);
		}

		// 1. Deep-clone tree with fresh ids, dropping any skipped subtrees.
		$cloned = CC_Assistant_Elementor_Builder::clone_tree_with_fresh_ids( $mirror_tree, $skip_widget_ids );

		// 2. Apply text replacements across all text-bearing settings (including
		//    icon-list items and link.url). Order-aware: longer phrases first.
		if ( ! empty( $replacements ) ) {
			CC_Assistant_Elementor_Builder::apply_text_replacements_to_tree( $cloned, $replacements );
		}

		// 3. Validate the resulting tree (catches duplicate ids that should be
		//    impossible after fresh-id clone, plus missing elType).
		$validation = CC_Assistant_Elementor_Builder::validate_tree( $cloned );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// 4. Lint the assembled body text. v0.35: per-widget evaluation so
		//    wall_of_text doesn't false-positive on a multi-card service page.
		//    em_dashes / ai_tells / style_guide / placeholders / address /
		//    hospital still aggregate. Same gate as widget_update / widget_add.
		//    This catches placeholders (the v0.19 failure that put $TBD on the
		//    live page), em dashes, AI-tells surviving the str_ireplace, and
		//    address/hospital-comparison violations introduced by the clone.
		$per_widget = self::collect_per_widget_lint_payloads( $cloned );
		if ( ! empty( $per_widget ) && method_exists( 'CC_Assistant_Pre_Publish', 'lint_html_block_per_widget' ) ) {
			$lint = CC_Assistant_Pre_Publish::lint_html_block_per_widget( $per_widget );
		} else {
			// Legacy fallback — keeps the method backwards-compatible if a
			// future caller passes an empty tree.
			$lint_blob = self::collect_text_for_lint( $cloned );
			$lint      = CC_Assistant_Pre_Publish::lint_html_block( $lint_blob );
		}
		$hard = array();
		foreach ( array( 'em_dashes', 'ai_tells', 'style_guide', 'placeholders', 'wall_of_text', 'address_consistency', 'hospital_comparison' ) as $name ) {
			if ( isset( $lint[ $name ]['pass'] ) && empty( $lint[ $name ]['pass'] ) ) {
				$hard[] = $name;
			}
		}
		if ( ! empty( $hard ) && ! $override_lint ) {
			return new WP_Error(
				'lint_hard_violation',
				sprintf(
					'Cloned content fails: %s. Fix the mirror page, the replacement map, or resubmit with override_lint=true.',
					implode( ', ', $hard )
				),
				array( 'status' => 422, 'lint' => $lint )
			);
		}

		// 4b. Mirror-leakage check. Catches three propagation defects the body
		//     lint alone cannot see:
		//       - Mirror permalink surviving in cloned link.url / button hrefs
		//         (clicking a CTA on the new page would jump back to the source).
		//       - Mirror post_title surviving in cloned headings / text
		//         (typical when the replacement map missed a phrasing).
		//       - JSON-LD @id pointing at the mirror URL inside html widgets.
		//     Hard fail; override_lint also clears this gate.
		$leakage = self::detect_mirror_leakage( $cloned, $mirror );
		if ( ! empty( $leakage['issues'] ) && ! $override_lint ) {
			return new WP_Error(
				'mirror_leakage_detected',
				sprintf(
					'Cloned content still references the mirror page: %s. Add the missing replacements or resubmit with override_lint=true.',
					implode( '; ', $leakage['issues'] )
				),
				array( 'status' => 422, 'leakage' => $leakage )
			);
		}

		// 5. Build a structure summary for the response so the operator can
		//    eyeball what they got before the publish_draft pending applies.
		$summary = self::summarize_tree( $cloned );
		$summary['text_replacements_applied'] = count( $replacements );
		$summary['skipped_subtree_count']     = count( $skip_widget_ids );

		require_once __DIR__ . '/class-evidence-gate.php';
		require_once __DIR__ . '/class-elementor-validation.php';
		$identity = CC_Assistant_Evidence_Gate::identity_check();
		if ( is_wp_error( $identity ) ) { return $identity; }
		$valid = CC_Assistant_Elementor_Validation::validate_tree( array(), $cloned );
		if ( is_wp_error( $valid ) ) { return $valid; }
		if ( $dry_run ) {
			return array(
				'dry_run'         => true,
				'mirror_post_id'  => $mirror_id,
				'structure'       => $summary,
				'lint'            => $lint,
				'mirror_quality'  => $mirror_quality,
			);
		}

		// 6. Create the new draft post with the cloned tree.
		$insert = array(
			'post_title'   => $title,
			'post_status'  => 'draft',
			'post_type'    => $post_type,
			'post_content' => '', // Elementor renders from _elementor_data.
		);
		if ( '' !== $slug ) {
			$insert['post_name'] = sanitize_title( $slug );
		}
		$new_id = wp_insert_post( $insert, true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		// Stamp the race-safety marker BEFORE any other postmeta write. The
		// v0.18.1 guard compares post_modified against max(queued_at, this
		// stamp) to decide if a queued change conflicts with external edits.
		// Without this stamp, the subsequent publish_draft pending sees the
		// fresh wp_insert_post's post_modified as "external" and refuses.
		// Mirrors what CC_Assistant_Apply::apply_one does on every normal apply.
		update_post_meta( $new_id, '_cc_assistant_last_internal_apply', current_time( 'mysql', true ) );

		$json = wp_json_encode( $cloned );
		if ( false === $json ) {
			// Roll back the post — we won't have a renderable Elementor body.
			wp_delete_post( $new_id, true );
			return new WP_Error( 'encode_failed', 'Could not encode cloned _elementor_data.', array( 'status' => 500 ) );
		}
		require_once __DIR__ . '/class-elementor-builder.php';
		$saved = CC_Assistant_Elementor_Builder::save_tree( $new_id, $cloned );
		if ( is_wp_error( $saved ) ) { wp_delete_post( $new_id, true ); return $saved; }
		update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
		// Copy the Elementor template type (e.g., "wp-page") from mirror so the
		// new page renders the same way (theme header/footer wrappers).
		$mirror_tpl = get_post_meta( $mirror_id, '_elementor_template_type', true );
		if ( ! empty( $mirror_tpl ) ) {
			update_post_meta( $new_id, '_elementor_template_type', $mirror_tpl );
		}
		// Inherit the page template / sidebar setting where possible so the
		// theme renders the new page like the source.
		$page_template = get_post_meta( $mirror_id, '_wp_page_template', true );
		if ( ! empty( $page_template ) && 'default' !== $page_template ) {
			update_post_meta( $new_id, '_wp_page_template', $page_template );
		}

		// 7. SEO meta — set directly on the draft (not via pending) since the
		//    page itself is queued for publish; the SEO fields apply when the
		//    draft is published.
		if ( '' !== $seo_title ) {
			self::set_seo_field( $new_id, 'title', $seo_title );
		}
		if ( '' !== $seo_description ) {
			self::set_seo_field( $new_id, 'description', $seo_description );
		}
		if ( '' !== $focus_keyword ) {
			self::set_seo_field( $new_id, 'focus_keyword', $focus_keyword );
		}

		// 6b. Duplicate-content gate. A str_ireplace clone can stay ~85-95%
		//     similar to the mirror after replacements — close enough that
		//     Google may collapse the two URLs as near-duplicates and pick
		//     one canonical. Run a similarity check between the new post
		//     and the mirror; refuse to consider the build "complete" if
		//     they cluster above 0.85 and stamp a `duplicate_risk` warning
		//     on the response. Override with override_lint to publish anyway.
		$duplicate_risk = null;
		if ( file_exists( CC_ASSISTANT_DIR . 'includes/class-similarity.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-similarity.php';
			$cluster = CC_Assistant_Similarity::find_clusters( array( (int) $new_id, (int) $mirror_id ), 0.85 );
			// Fail-closed when the embeddings system can't actually evaluate
			// both pages. `considered` is the count of vectors successfully
			// built; <2 means we didn't get to do an apples-to-apples
			// comparison (missing wp_cc_embeddings table on fresh installs,
			// empty body after parsing, or vector compute returned null).
			// Silently passing here would let a near-duplicate ship.
			if ( isset( $cluster['considered'] ) && (int) $cluster['considered'] < 2 && ! $override_lint ) {
				global $wpdb;
				$wpdb->delete( $wpdb->prefix . 'cc_embeddings', array( 'post_id' => (int) $new_id ), array( '%d' ) );
				wp_delete_post( (int) $new_id, true );
				return new WP_Error(
					'duplicate_check_unavailable',
					'Could not compute similarity vectors for the new page and its mirror (embeddings table missing or vector compute returned null). The draft has been cleaned up. Verify wp_cc_embeddings exists and the mirror has indexable content, or pass override_lint=true to skip the gate.',
					array(
						'status'              => 422,
						'similarity_response' => $cluster,
					)
				);
			}
			if ( ! empty( $cluster['clusters'] ) ) {
				$duplicate_risk = array(
					'mirror_post_id' => (int) $mirror_id,
					'new_post_id'    => (int) $new_id,
					'clusters'       => $cluster['clusters'],
					'note'           => 'New page is highly similar to its mirror after replacements. Differentiate the body before publishing or expect Google to canonicalize one URL away.',
				);
				if ( ! $override_lint ) {
					// Hard cleanup: wp_insert_post + the 6 postmeta writes +
					// the SEO meta routing above all already ran. If we just
					// returned WP_Error, an orphan draft + Rank Math/Yoast
					// focus-keyword tally + Elementor template-type meta all
					// stay behind. Delete the post (force=true so it skips
					// the trash) and drop the cc_embeddings row created by
					// the similarity check itself. The operator gets a fresh
					// state to retry from, not a half-built ghost page.
					global $wpdb;
					$wpdb->delete( $wpdb->prefix . 'cc_embeddings', array( 'post_id' => (int) $new_id ), array( '%d' ) );
					wp_delete_post( (int) $new_id, true );
					return new WP_Error(
						'duplicate_risk_detected',
						'New page is too similar to mirror (cosine ≥ 0.85). The draft + its postmeta have been cleaned up. Differentiate the body in the replacement map and re-run, or pass override_lint=true to publish anyway.',
						array(
							'status'         => 422,
							'duplicate_risk' => $duplicate_risk,
						)
					);
				}
			}
		}

		// 7a. Auto-find inbound link opportunities so the new page does not
		//     land as an instant orphan. Walks existing posts whose body
		//     already contains the new page's title verbatim — those are
		//     natural inline-link insertion points the operator can queue
		//     after publishing. We do NOT auto-queue pending changes here:
		//     each insertion needs a unique edit context and an Elementor
		//     widget update is too brittle to synthesize blind. Returning
		//     the suggestion list keeps the human + AI in the loop.
		$inbound_suggestions = array();
		if ( file_exists( CC_ASSISTANT_DIR . 'includes/class-internal-links.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-internal-links.php';
			$inbound_suggestions = CC_Assistant_Internal_Links::suggest_inbound(
				(int) $new_id,
				array( 'limit' => 5 )
			);
		}

		// 7b. Auto-generate base schema for the new page. New service pages
		//     have no structured data on day 1 unless Rank Math/Yoast already
		//     route by post_type — we surface a generator-output blob in
		//     postmeta so post_dossier sees it and the operator can promote
		//     it (or override with propose_schema for Service/LocalBusiness).
		$schema_generated = null;
		if ( 'page' !== get_post_type( (int) $new_id ) && file_exists( CC_ASSISTANT_DIR . 'includes/class-schema-generator.php' ) ) {
			// Pages are skipped: the SEO plugin (Rank Math/Yoast) already emits a
			// WebPage node with @id {url}#webpage, and a second generator-written
			// WebPage would @id-collide and trip the singleton-collision audits.
			// Same rule as build_page_from_spec.
			require_once CC_ASSISTANT_DIR . 'includes/class-schema-generator.php';
			$gen = CC_Assistant_Schema_Generator::generate( (int) $new_id );
			if ( ! is_wp_error( $gen ) && ! empty( $gen['jsonld'] ) ) {
				update_post_meta( (int) $new_id, CC_Assistant_Schema_Generator::META_KEY, wp_slash( $gen['jsonld'] ) );
				$schema_generated = array( 'types' => $gen['types'] ?? array() );
			}
		}

		// Re-stamp the race-safety marker right before queueing the publish_draft
		// pending. Any internal operation between the insert above and this point
		// (SEO meta writes, schema injection, category assignment) that bumps
		// post_modified is now covered by max(queued_at, stamp) in the guard.
		// Defensive — most internal writes go through update_post_meta which
		// does not bump post_modified, but set_seo_field/category assignment
		// can route through wp_update_post depending on the SEO plugin.
		update_post_meta( $new_id, '_cc_assistant_last_internal_apply', current_time( 'mysql', true ) );

		// 8. Queue ONE publish_draft pending so the operator approves the page
		//    in a single click. Reuses the existing publish_draft change_type.
		$summary_line = sprintf(
			'Build new %s "%s" by cloning post %d. %d root containers, %d widgets, %d text replacements%s.',
			$post_type,
			$title,
			$mirror_id,
			$summary['root_containers'],
			$summary['total_widgets'],
			count( $replacements ),
			count( $skip_widget_ids ) > 0 ? sprintf( ', %d subtree(s) skipped', count( $skip_widget_ids ) ) : ''
		);
		$proposed_blob = array(
			'mirror_post_id' => $mirror_id,
			'title'          => $title,
			'slug'           => $slug,
			'structure'      => $summary,
		);
		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => $new_id,
				'change_type'    => 'publish_draft',
				'change_summary' => $summary_line,
				'proposed_value' => wp_json_encode( $proposed_blob ),
				'current_value'  => '',
			)
		);
		if ( is_wp_error( $pending_id ) ) {
			return $pending_id;
		}

		return array(
			'new_post_id'      => (int) $new_id,
			'pending_id'       => (int) $pending_id,
			'edit_url'         => get_edit_post_link( $new_id, '' ),
			'preview_url'      => add_query_arg( array( 'page_id' => $new_id, 'preview' => 'true' ), home_url() ),
			'review_url'       => admin_url( 'admin.php?page=cc-assistant-pending' ),
			'structure'        => $summary,
			'lint'             => $lint,
			'mirror_quality'   => $mirror_quality,
			'mirror_language'  => $mirror_language,
			'language_warning' => $language_warning,
			'schema_generated' => $schema_generated,
			'inbound_link_suggestions' => $inbound_suggestions,
			'duplicate_risk'   => $duplicate_risk,
		);
	}

	/**
	 * Suggest the best existing service page to use as a clone source. Walks
	 * pages tagged as service pages (presence of _elementor_data + length
	 * over 8KB as a rough "real Elementor build" gate), scores each against
	 * pre-publish checks (mirror quality), and returns the top N by
	 * pass_rate then word_count desc. Filters out the post being cloned-from
	 * if $exclude_id provided and pages with a target_language mismatch when
	 * Polylang/WPML is active. Lightweight — uses the same audit pass as
	 * audit_mirror_quality so per-candidate cost is bounded by check_post.
	 *
	 * @param array $opts {
	 *     @type string $service_keyword  Optional. Filter candidates by
	 *                                    keyword present in title or slug.
	 *     @type string $target_language  Optional. Polylang/WPML language tag
	 *                                    to filter by.
	 *     @type int    $exclude_id       Optional. Post id to omit.
	 *     @type int    $limit            Optional. Max results (default 5).
	 * }
	 * @return array { candidates: [{post_id, title, permalink, pass_rate,
	 *                  word_count, mirror_quality, language}, ...] }
	 */
	public static function suggest_best_mirror( array $opts = array() ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pre-publish.php';
		global $wpdb;

		$keyword = isset( $opts['service_keyword'] ) ? trim( (string) $opts['service_keyword'] ) : '';
		$lang    = isset( $opts['target_language'] ) ? sanitize_key( (string) $opts['target_language'] ) : '';
		$exclude = isset( $opts['exclude_id'] ) ? (int) $opts['exclude_id'] : 0;
		$limit   = max( 1, min( 10, isset( $opts['limit'] ) ? (int) $opts['limit'] : 5 ) );

		// Candidate set: pages with _elementor_data over 8KB (excludes thin
		// auto-saved drafts and ad-hoc widget tests). Pull more than needed
		// since we filter post-query for language + quality.
		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_name
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			 WHERE p.post_status = 'publish'
			   AND p.post_type IN ('page','service','services')
			   AND LENGTH(m.meta_value) > 8192
			 ORDER BY p.post_modified DESC
			 LIMIT 50",
			ARRAY_A
		);

		$multi_active = false;
		if ( '' !== $lang && file_exists( CC_ASSISTANT_DIR . 'includes/class-multilingual.php' ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';
			$multi_active = CC_Assistant_Multilingual::is_active();
		}

		$kw_lc = '' !== $keyword ? mb_strtolower( $keyword ) : '';
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$pid = (int) $row['ID'];
			if ( $exclude > 0 && $pid === $exclude ) {
				continue;
			}
			if ( '' !== $kw_lc ) {
				$haystack = mb_strtolower( $row['post_title'] . ' ' . $row['post_name'] );
				if ( false === mb_strpos( $haystack, $kw_lc ) ) {
					continue;
				}
			}
			$post_lang = '';
			if ( $multi_active ) {
				$post_lang = (string) CC_Assistant_Multilingual::language_of( $pid );
				if ( '' !== $lang && $post_lang !== $lang ) {
					continue;
				}
			}
			$quality = self::audit_mirror_quality( $pid );
			$check   = CC_Assistant_Pre_Publish::check_post( $pid );
			$wc      = ( ! is_wp_error( $check ) && isset( $check['word_count'] ) ) ? (int) $check['word_count'] : 0;
			$out[]   = array(
				'post_id'        => $pid,
				'title'          => (string) $row['post_title'],
				'permalink'      => (string) get_permalink( $pid ),
				'pass_rate'      => $quality['pass_rate'],
				'word_count'     => $wc,
				'mirror_quality' => $quality,
				'language'       => $post_lang,
			);
		}
		usort( $out, function ( $a, $b ) {
			if ( $a['pass_rate'] === $b['pass_rate'] ) {
				return $b['word_count'] <=> $a['word_count'];
			}
			return $b['pass_rate'] <=> $a['pass_rate'];
		} );
		return array( 'candidates' => array_slice( $out, 0, $limit ) );
	}

	/**
	 * Detect references to the mirror page that survived the str_ireplace
	 * pass. Returns an array of human-readable issue descriptions; empty
	 * array means the clone is clean. Checks:
	 *   - link.url / button href / link.url settings exactly equal to or
	 *     prefixed by the mirror permalink
	 *   - html widgets containing the mirror permalink or JSON-LD @id
	 *     pointing at it
	 *   - heading_text / title / editor / text containing the full mirror
	 *     post_title as a standalone word boundary
	 */
	private static function detect_mirror_leakage( array $tree, $mirror_post ) {
		$mirror_url   = (string) get_permalink( $mirror_post->ID );
		$mirror_url   = rtrim( $mirror_url, '/' );
		$mirror_title = trim( (string) $mirror_post->post_title );
		$issues       = array();
		$url_hits     = 0;
		$title_hits   = 0;

		// Mirror title must be at least 4 chars and not the literal site name
		// to avoid false positives (a service page titled "Home" would match
		// every navigation link).
		$check_title = ( mb_strlen( $mirror_title ) >= 4 );
		$title_re    = $check_title ? '/\b' . preg_quote( $mirror_title, '/' ) . '\b/iu' : '';

		$walker = function ( $nodes ) use ( &$walker, &$url_hits, &$title_hits, $mirror_url, $title_re, $check_title ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) || empty( $n['settings'] ) ) {
					if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
						$walker( $n['elements'] );
					}
					continue;
				}
				$s = $n['settings'];
				// link.url variants — Elementor stores button hrefs as {url:..., is_external:..., nofollow:...}.
				foreach ( array( 'link', 'button_link', 'icon_link', 'cta_link' ) as $lk ) {
					if ( isset( $s[ $lk ]['url'] ) && is_string( $s[ $lk ]['url'] ) ) {
						$u = rtrim( $s[ $lk ]['url'], '/' );
						if ( '' !== $mirror_url && $u === $mirror_url ) {
							$url_hits++;
						}
					}
				}
				// Direct url setting (some widgets use 'url' at top level).
				if ( isset( $s['url'] ) && is_string( $s['url'] ) ) {
					$u = rtrim( $s['url'], '/' );
					if ( '' !== $mirror_url && $u === $mirror_url ) {
						$url_hits++;
					}
				}
				// html widget — full string search for mirror URL or @id.
				if ( isset( $s['html'] ) && is_string( $s['html'] ) ) {
					if ( '' !== $mirror_url && false !== stripos( $s['html'], $mirror_url ) ) {
						$url_hits++;
					}
				}
				// Text-bearing fields — check for the mirror post_title.
				if ( $check_title ) {
					foreach ( array( 'title', 'editor', 'text', 'title_text', 'description_text', 'html', 'heading' ) as $tk ) {
						if ( isset( $s[ $tk ] ) && is_string( $s[ $tk ] ) && preg_match( $title_re, $s[ $tk ] ) ) {
							$title_hits++;
						}
					}
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walker( $n['elements'] );
				}
			}
		};
		$walker( $tree );

		if ( $url_hits > 0 ) {
			$issues[] = sprintf( '%d setting(s) still point to mirror URL %s', $url_hits, $mirror_url );
		}
		if ( $title_hits > 0 ) {
			$issues[] = sprintf( '%d text setting(s) still contain mirror title "%s"', $title_hits, $mirror_title );
		}
		return array(
			'issues'     => $issues,
			'url_hits'   => $url_hits,
			'title_hits' => $title_hits,
			'mirror_url' => $mirror_url,
		);
	}

	/**
	 * Score the mirror page against pre-publish checks. Returns ok=true when
	 * pass_rate >= 0.7, plus the list of failing check names so the error
	 * response can name them. Skips checks that don't propagate from mirror
	 * to clone (internal_links, meta_title, meta_description — those are
	 * specific to the new page and supplied separately).
	 */
	private static function audit_mirror_quality( $mirror_id ) {
		$check = CC_Assistant_Pre_Publish::check_post( (int) $mirror_id );
		if ( is_wp_error( $check ) || empty( $check['checks'] ) ) {
			// Fail-closed: if the pre-publish check returned an error
			// (post deleted between suggest + build, parser blew up, etc.)
			// we genuinely don't know if the mirror is clone-safe. Don't
			// silently pass — surface the unknown state so the operator
			// decides via override_mirror_quality instead of getting a
			// false sense of safety.
			return array(
				'ok'           => false,
				'pass_count'   => 0,
				'total'        => 0,
				'failing'      => array( 'pre_publish_check_unavailable' ),
				'pass_rate'    => 0.0,
				'reason'       => 'pre_publish_check_unavailable',
				'error_detail' => is_wp_error( $check ) ? $check->get_error_message() : 'check_post returned no checks',
			);
		}
		$irrelevant = array( 'internal_links', 'meta_title', 'meta_description', 'inbound_links' );
		$relevant   = array_diff_key( $check['checks'], array_flip( $irrelevant ) );
		$pass = 0;
		$fail = 0;
		$failing = array();
		foreach ( $relevant as $name => $c ) {
			if ( ! empty( $c['pass'] ) ) {
				$pass++;
			} else {
				$fail++;
				$failing[] = $name;
			}
		}
		$total     = $pass + $fail;
		$pass_rate = $total > 0 ? ( $pass / $total ) : 1.0;
		return array(
			'ok'         => $pass_rate >= 0.70,
			'pass_count' => $pass,
			'total'      => $total,
			'pass_rate'  => round( $pass_rate, 2 ),
			'failing'    => $failing,
		);
	}

	/**
	 * Walk the cloned tree and concatenate every text-bearing setting into
	 * one HTML blob for lint. Same field set as lint_widget_settings_payload.
	 */
	private static function collect_text_for_lint( array $tree ) {
		$buf       = '';
		$text_keys = array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' );
		$walker    = function ( $nodes ) use ( &$walker, &$buf, $text_keys ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				if ( isset( $n['settings'] ) && is_array( $n['settings'] ) ) {
					foreach ( $text_keys as $k ) {
						if ( isset( $n['settings'][ $k ] ) && is_string( $n['settings'][ $k ] ) ) {
							$buf .= "\n" . $n['settings'][ $k ];
						}
					}
					if ( isset( $n['settings']['icon_list'] ) && is_array( $n['settings']['icon_list'] ) ) {
						foreach ( $n['settings']['icon_list'] as $item ) {
							if ( isset( $item['text'] ) && is_string( $item['text'] ) ) {
								$buf .= "\n" . $item['text'];
							}
						}
					}
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walker( $n['elements'] );
				}
			}
		};
		$walker( $tree );
		return $buf;
	}

	/**
	 * v0.35 per-widget payload collector — Elementor-tree shape (uses
	 * `elements` + `widgetType`). Mirrors the REST-API spec-tree variant
	 * but walks the live Elementor format the cloned tree uses. Returns
	 * the array shape lint_html_block_per_widget consumes.
	 */
	private static function collect_per_widget_lint_payloads( array $tree ) {
		$out       = array();
		$text_keys = array( 'editor', 'text', 'title', 'title_text', 'description_text', 'html', 'content' );
		$walker    = function ( $nodes, $path ) use ( &$walker, &$out, $text_keys ) {
			foreach ( $nodes as $i => $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$el = isset( $n['elType'] ) ? (string) $n['elType'] : '';
				$wt = isset( $n['widgetType'] ) ? (string) $n['widgetType'] : '';
				if ( 'widget' === $el && isset( $n['settings'] ) && is_array( $n['settings'] ) ) {
					$widget_html = '';
					foreach ( $text_keys as $k ) {
						if ( isset( $n['settings'][ $k ] ) && is_string( $n['settings'][ $k ] ) ) {
							$widget_html .= "\n" . $n['settings'][ $k ];
						}
					}
					if ( isset( $n['settings']['icon_list'] ) && is_array( $n['settings']['icon_list'] ) ) {
						foreach ( $n['settings']['icon_list'] as $item ) {
							if ( isset( $item['text'] ) && is_string( $item['text'] ) ) {
								$widget_html .= "\n" . $item['text'];
							}
						}
					}
					$out[] = array(
						'html'     => $widget_html,
						'settings' => $n['settings'],
						'label'    => ( '' !== $wt ? $wt : 'widget' ) . ' at ' . $path . '[' . (int) $i . ']',
					);
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walker( $n['elements'], $path . '[' . (int) $i . '].elements' );
				}
			}
		};
		$walker( $tree, 'tree' );
		return $out;
	}

	/**
	 * Lightweight structure tally so the response can show what was built.
	 */
	private static function summarize_tree( array $tree ) {
		$root_containers = 0;
		$total_widgets   = 0;
		$widget_types    = array();
		$heading_levels  = array();
		$walker          = function ( $nodes, $depth ) use ( &$walker, &$total_widgets, &$widget_types, &$heading_levels, &$root_containers ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$el = isset( $n['elType'] ) ? $n['elType'] : '';
				if ( 'widget' === $el ) {
					$total_widgets++;
					$wt = isset( $n['widgetType'] ) ? $n['widgetType'] : 'unknown';
					$widget_types[ $wt ] = ( $widget_types[ $wt ] ?? 0 ) + 1;
					if ( 'heading' === $wt && isset( $n['settings']['header_size'] ) ) {
						$lvl = (string) $n['settings']['header_size'];
						$heading_levels[ $lvl ] = ( $heading_levels[ $lvl ] ?? 0 ) + 1;
					}
				} elseif ( 0 === $depth && in_array( $el, array( 'container', 'section' ), true ) ) {
					$root_containers++;
				}
				if ( ! empty( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walker( $n['elements'], $depth + 1 );
				}
			}
		};
		$walker( $tree, 0 );
		return array(
			'root_containers' => $root_containers,
			'total_widgets'   => $total_widgets,
			'widget_types'    => $widget_types,
			'heading_levels'  => $heading_levels,
		);
	}

	/**
	 * Smart SEO field writer — same key resolution as draft_update_seo_meta
	 * but writes directly (since the post is still a draft, not live).
	 * Public since v0.40 so CC_Assistant_Build_From_Spec reuses it instead of
	 * duplicating the SEO-plugin key map.
	 */
	public static function set_seo_field( $post_id, $logical_key, $value ) {
		// Detect the active SEO plugin and write the appropriate postmeta key.
		// Order: Rank Math, Yoast, AIOSEO.
		$is_rank_math = defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) || class_exists( 'RankMath\\Helper' );
		$is_yoast     = defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
		$is_aioseo    = defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
		$key_map = array();
		if ( $is_rank_math ) {
			$key_map = array(
				'title'         => 'rank_math_title',
				'description'   => 'rank_math_description',
				'focus_keyword' => 'rank_math_focus_keyword',
				'canonical'     => 'rank_math_canonical_url',
			);
		} elseif ( $is_yoast ) {
			$key_map = array(
				'title'         => '_yoast_wpseo_title',
				'description'   => '_yoast_wpseo_metadesc',
				'focus_keyword' => '_yoast_wpseo_focuskw',
				'canonical'     => '_yoast_wpseo_canonical',
			);
		} elseif ( $is_aioseo ) {
			$key_map = array(
				'title'         => '_aioseo_title',
				'description'   => '_aioseo_description',
				'focus_keyword' => '_aioseo_keywords',
			);
		}
		if ( isset( $key_map[ $logical_key ] ) ) {
			update_post_meta( $post_id, $key_map[ $logical_key ], $value );
			return true;
		}
		return false;
	}
}

<?php
/**
 * v0.69 — bulk taxonomy-term assignment.
 *
 * Why this exists: assigning a brand (or any taxonomy term) to a few hundred
 * products through wp-admin means filtering, paging, select-all, bulk-edit —
 * repeated per brand, and easy to get wrong. Two real failure modes seen on a
 * live store: the category filter did not reach products sitting in deeper
 * sub-categories (74 of 105 Kichler products were missed), and the admin
 * search box matches DESCRIPTIONS as well as titles, so an accessory that
 * merely mentions a brand gets tagged with it.
 *
 * This matches on post_title only, via SQL LIKE, so the target set is exactly
 * what the operator can predict. Like every other write in this plugin it is
 * draft-only: build_plan() is pure read, the plan goes through the Pending
 * Changes inbox, and apply happens on human approval.
 *
 * Revert fidelity: the plan records each post's PRIOR term ids in the target
 * taxonomy, so rolling back restores the exact previous state rather than
 * merely removing the term we added.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Bulk_Terms {

	/**
	 * Hard cap on one plan. Keeps the stored payload reviewable and bounds the
	 * apply loop; a catalogue-wide job is several approvals, which is correct
	 * for a change the human is signing off on.
	 */
	const MAX_TARGETS = 600;

	/**
	 * Post types this tool may touch. Deliberately WIDER than the content
	 * allowlist (post/page): assigning a term cannot corrupt post_content,
	 * page-builder data, or product pricing — it writes term relationships
	 * only, and the plan carries exact revert data. That is why products are
	 * safe here while remaining off-limits to body edits.
	 */
	private static function allowed_post_types() {
		$types = array( 'post', 'page', 'product' );
		/**
		 * Filter the post types eligible for bulk term assignment.
		 *
		 * @param array $types Post type slugs.
		 */
		return array_values( array_unique( (array) apply_filters( 'cc_assistant_bulk_term_post_types', $types ) ) );
	}

	/**
	 * Build a plan without writing anything.
	 *
	 * @param array $args {
	 *   @type string $taxonomy    Target taxonomy, e.g. product_brand.
	 *   @type int    $term_id     Term to assign. Must already exist.
	 *   @type string $post_type   Defaults to product.
	 *   @type string $match_type  title_contains | post_ids | in_term
	 *   @type string $match_value Search string (title_contains).
	 *   @type array  $post_ids    Explicit ids (post_ids).
	 *   @type int    $source_term Term id whose members to match (in_term).
	 *   @type string $mode        append (default) | replace
	 * }
	 * @return array|WP_Error
	 */
	public static function build_plan( $args ) {
		global $wpdb;

		$taxonomy  = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';
		$term_id   = isset( $args['term_id'] ) ? (int) $args['term_id'] : 0;
		$post_type = isset( $args['post_type'] ) && $args['post_type'] ? sanitize_key( $args['post_type'] ) : 'product';
		$mode      = ( isset( $args['mode'] ) && 'replace' === $args['mode'] ) ? 'replace' : 'append';
		$match     = isset( $args['match_type'] ) ? sanitize_key( $args['match_type'] ) : 'title_contains';

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'taxonomy_missing', sprintf( 'Taxonomy "%s" is not registered on this site.', $taxonomy ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $post_type, self::allowed_post_types(), true ) ) {
			return new WP_Error(
				'post_type_not_allowed',
				sprintf( 'Post type "%s" is not eligible. Allowed: %s.', $post_type, implode( ', ', self::allowed_post_types() ) ),
				array( 'status' => 403 )
			);
		}
		if ( ! in_array( $taxonomy, get_object_taxonomies( $post_type ), true ) ) {
			return new WP_Error(
				'taxonomy_post_type_mismatch',
				sprintf( 'Taxonomy "%s" is not registered for post type "%s".', $taxonomy, $post_type ),
				array( 'status' => 400 )
			);
		}
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error(
				'term_not_found',
				sprintf( 'Term %d not found in taxonomy "%s". Create the term first — this tool never creates terms.', $term_id, $taxonomy ),
				array( 'status' => 404 )
			);
		}

		// ---- resolve the candidate post ids -------------------------------
		$ids   = array();
		$label = '';
		if ( 'post_ids' === $match ) {
			$ids   = array_values( array_filter( array_map( 'intval', (array) ( isset( $args['post_ids'] ) ? $args['post_ids'] : array() ) ) ) );
			$label = sprintf( '%d explicit id(s)', count( $ids ) );
			if ( empty( $ids ) ) {
				return new WP_Error( 'no_post_ids', 'match_type=post_ids requires a non-empty post_ids array.', array( 'status' => 400 ) );
			}
			$ph  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$sql = $wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ('publish','private','draft') AND ID IN ($ph)",
				array_merge( array( $post_type ), $ids )
			);
		} elseif ( 'in_term' === $match ) {
			$source = isset( $args['source_term'] ) ? (int) $args['source_term'] : 0;
			$src    = $source ? get_term( $source ) : null;
			if ( ! $src || is_wp_error( $src ) ) {
				return new WP_Error( 'source_term_not_found', sprintf( 'Source term %d not found.', $source ), array( 'status' => 404 ) );
			}
			// include_children: the whole point — a brand category's products
			// usually sit in its leaf sub-categories, not on the parent.
			$desc   = get_term_children( $source, $src->taxonomy );
			$all    = array_merge( array( $source ), is_wp_error( $desc ) ? array() : array_map( 'intval', $desc ) );
			$ph     = implode( ',', array_fill( 0, count( $all ), '%d' ) );
			$label  = sprintf( 'in "%s" (incl. %d sub-term(s))', $src->name, count( $all ) - 1 );
			$sql    = $wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 WHERE p.post_type = %s AND p.post_status IN ('publish','private','draft')
				   AND tt.term_id IN ($ph)",
				array_merge( array( $post_type ), $all )
			);
		} else {
			$needle = isset( $args['match_value'] ) ? trim( (string) $args['match_value'] ) : '';
			if ( '' === $needle ) {
				return new WP_Error( 'match_value_required', 'match_type=title_contains requires match_value.', array( 'status' => 400 ) );
			}
			// TITLE ONLY. The admin search box also searches post_content and
			// excerpt, which is how "accessory compatible with Kichler" ends up
			// tagged as a Kichler product.
			$label = sprintf( 'title contains "%s"', $needle );
			$sql   = $wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ('publish','private','draft')
				   AND post_title LIKE %s
				 ORDER BY post_title ASC",
				$post_type,
				'%' . $wpdb->esc_like( $needle ) . '%'
			);
		}

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( empty( $rows ) ) {
			return array(
				'taxonomy'   => $taxonomy,
				'term_id'    => $term_id,
				'term_name'  => $term->name,
				'post_type'  => $post_type,
				'mode'       => $mode,
				'match'      => array( 'type' => $match, 'label' => $label ),
				'targets'    => array(),
				'total_matched' => 0,
				'already_had'   => 0,
				'to_change'     => 0,
			);
		}

		$targets     = array();
		$already_had = 0;
		$truncated   = false;
		foreach ( $rows as $row ) {
			$pid   = (int) $row->ID;
			$prior = wp_get_object_terms( $pid, $taxonomy, array( 'fields' => 'ids' ) );
			$prior = is_wp_error( $prior ) ? array() : array_map( 'intval', $prior );

			// Already carries the term in append mode → nothing to do. Counted
			// so the operator sees coverage, not silently dropped.
			if ( 'append' === $mode && in_array( $term_id, $prior, true ) ) {
				$already_had++;
				continue;
			}
			if ( 'replace' === $mode && array( $term_id ) === $prior ) {
				$already_had++;
				continue;
			}
			if ( count( $targets ) >= self::MAX_TARGETS ) {
				$truncated = true;
				break;
			}
			$targets[] = array(
				'id'    => $pid,
				'title' => mb_substr( (string) $row->post_title, 0, 120 ),
				'prior' => $prior,
			);
		}

		$plan = array(
			'taxonomy'      => $taxonomy,
			'term_id'       => $term_id,
			'term_name'     => $term->name,
			'post_type'     => $post_type,
			'mode'          => $mode,
			'match'         => array( 'type' => $match, 'label' => $label ),
			'targets'       => $targets,
			'total_matched' => count( $rows ),
			'already_had'   => $already_had,
			'to_change'     => count( $targets ),
		);
		if ( $truncated ) {
			$plan['truncated'] = sprintf(
				'Matched %d posts but this plan is capped at %d. Approve this batch, then run the same call again for the remainder.',
				count( $rows ),
				self::MAX_TARGETS
			);
		}
		return $plan;
	}

	/**
	 * Apply a stored plan. Partial success is a success: a post deleted or
	 * re-termed after queueing is skipped and reported, not fatal.
	 *
	 * @return array|WP_Error {written:int, skipped:array}
	 */
	public static function apply_plan( $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['targets'] ) ) {
			return new WP_Error( 'bulk_term_plan_invalid', 'Stored plan has no targets.' );
		}
		$taxonomy = sanitize_key( $payload['taxonomy'] );
		$term_id  = (int) $payload['term_id'];
		$append   = ( ! isset( $payload['mode'] ) || 'replace' !== $payload['mode'] );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'taxonomy_missing', sprintf( 'Taxonomy "%s" no longer exists.', $taxonomy ) );
		}
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'term_not_found', sprintf( 'Term %d no longer exists in "%s".', $term_id, $taxonomy ) );
		}

		$written = 0;
		$skipped = array();
		foreach ( $payload['targets'] as $t ) {
			$pid = isset( $t['id'] ) ? (int) $t['id'] : 0;
			if ( $pid <= 0 || ! get_post( $pid ) ) {
				$skipped[] = array( 'id' => $pid, 'reason' => 'post no longer exists' );
				continue;
			}
			$res = wp_set_object_terms( $pid, array( $term_id ), $taxonomy, $append );
			if ( is_wp_error( $res ) ) {
				$skipped[] = array( 'id' => $pid, 'reason' => $res->get_error_message() );
				continue;
			}
			$written++;
		}

		if ( 0 === $written ) {
			$why = array();
			foreach ( array_slice( $skipped, 0, 5 ) as $s ) {
				$why[] = '#' . $s['id'] . ' ' . $s['reason'];
			}
			return new WP_Error(
				'bulk_term_wrote_nothing',
				'No post could be updated. ' . ( $why ? implode( '; ', $why ) : 'No reason recorded.' )
			);
		}

		wp_cache_delete( 'last_changed', 'terms' );
		return array( 'written' => $written, 'skipped' => $skipped );
	}

	/**
	 * Restore each target's prior terms in the taxonomy. Exact inverse: uses
	 * the snapshot captured at plan time, so a post that had two brands keeps
	 * both and a post that had none goes back to none.
	 */
	public static function revert_plan( $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['targets'] ) ) {
			return new WP_Error( 'bulk_term_plan_invalid', 'Stored plan has no targets to revert.' );
		}
		$taxonomy = sanitize_key( $payload['taxonomy'] );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'taxonomy_missing', sprintf( 'Taxonomy "%s" no longer exists.', $taxonomy ) );
		}
		$restored = 0;
		foreach ( $payload['targets'] as $t ) {
			$pid = isset( $t['id'] ) ? (int) $t['id'] : 0;
			if ( $pid <= 0 || ! get_post( $pid ) ) {
				continue;
			}
			$prior = isset( $t['prior'] ) ? array_map( 'intval', (array) $t['prior'] ) : array();
			// false = replace, which is what restores "had nothing" correctly.
			$res = wp_set_object_terms( $pid, $prior, $taxonomy, false );
			if ( ! is_wp_error( $res ) ) {
				$restored++;
			}
		}
		if ( 0 === $restored ) {
			return new WP_Error( 'bulk_term_revert_nothing', 'No post could be restored.' );
		}
		wp_cache_delete( 'last_changed', 'terms' );
		return true;
	}
}

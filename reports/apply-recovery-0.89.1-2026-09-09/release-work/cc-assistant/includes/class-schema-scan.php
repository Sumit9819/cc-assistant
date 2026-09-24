<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * v0.44 — Site-wide schema-source scanner.
 *
 * Closes the blind spot that let a wrong aggregateRating (4.9 vs real 4.8) and
 * a broken BreadcrumbList sit live for months: the plugin audited only the
 * content IT writes, never the full emitted output. This runs the render_probe
 * over a representative sample of pages (loopback, so the edge WAF can't 403
 * it), aggregates every JSON-LD issue by emitter + check, and tells the
 * operator exactly where the self-serving ratings, duplicate breadcrumbs, and
 * missing-name errors live — including the ones in hand-built snippets and
 * Rank Math that cc-assistant cannot itself edit.
 */
class CC_Assistant_Schema_Scan {

	/**
	 * @param array|null $post_ids explicit ids, or null = representative sample.
	 * @param int        $limit    max pages to probe (each is one loopback HTTP).
	 * @return array
	 */
	public static function scan( $post_ids = null, $limit = 12 ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-render-probe.php';
		$limit = max( 1, min( 40, (int) $limit ) );
		$ids   = self::resolve_ids( $post_ids, $limit );

		$pages        = array();
		$by_check     = array();
		$by_emitter   = array();
		$total_issues = 0;
		$critical     = 0;

		foreach ( $ids as $id ) {
			$probe = CC_Assistant_Render_Probe::probe( (int) $id, array( 'schema' ) );
			if ( is_wp_error( $probe ) || empty( $probe['schema'] ) ) {
				continue;
			}
			$schema   = $probe['schema'];
			$issues   = isset( $schema['issues'] ) ? $schema['issues'] : array();
			$emitters = array();
			foreach ( ( isset( $schema['blocks'] ) ? $schema['blocks'] : array() ) as $b ) {
				if ( ! empty( $b['emitter'] ) ) {
					$emitters[] = $b['emitter'];
					$by_emitter[ $b['emitter'] ] = ( $by_emitter[ $b['emitter'] ] ?? 0 ) + 1;
				}
			}
			if ( empty( $issues ) ) {
				continue;
			}
			$pages[] = array(
				'post_id'  => (int) $id,
				'url'      => $probe['url'],
				'emitters' => array_values( array_unique( $emitters ) ),
				'issues'   => $issues,
			);
			foreach ( $issues as $iss ) {
				$total_issues++;
				$check               = isset( $iss['check'] ) ? $iss['check'] : 'other';
				$by_check[ $check ]  = ( $by_check[ $check ] ?? 0 ) + 1;
				if ( isset( $iss['severity'] ) && 'critical' === $iss['severity'] ) {
					$critical++;
				}
			}
		}

		return array(
			'scanned'           => count( $ids ),
			'scanned_ids'       => $ids,
			'pages_with_issues' => count( $pages ),
			'total_issues'      => $total_issues,
			'critical_count'    => $critical,
			'by_check'          => $by_check,
			'emitters_seen'     => $by_emitter,
			'pages'             => $pages,
			'verdict'           => $critical > 0 ? 'critical' : ( $total_issues > 0 ? 'warn' : 'clean' ),
			'note'              => 'Schema lives in shared emitters (Rank Math, hand-built snippets) so a sample catches site-wide issues. Pass explicit post_ids to target specific pages. cc-assistant cannot edit snippet/Rank Math output directly — fix those at their source.',
		);
	}

	private static function resolve_ids( $post_ids, $limit ) {
		if ( is_array( $post_ids ) && ! empty( $post_ids ) ) {
			return array_slice( array_values( array_unique( array_map( 'intval', $post_ids ) ) ), 0, $limit );
		}
		// Representative sample: the front page (where the org schema snippet
		// usually lives) + the most recently modified published pages (service
		// + location templates), where breadcrumb/schema issues cluster.
		$ids   = array();
		$front = (int) get_option( 'page_on_front' );
		if ( $front ) {
			$ids[] = $front;
		}
		$pages = get_posts( array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => $limit,
			'fields'           => 'ids',
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => true,
		) );
		foreach ( (array) $pages as $pid ) {
			if ( ! in_array( (int) $pid, $ids, true ) ) {
				$ids[] = (int) $pid;
			}
		}
		return array_slice( $ids, 0, $limit );
	}
}

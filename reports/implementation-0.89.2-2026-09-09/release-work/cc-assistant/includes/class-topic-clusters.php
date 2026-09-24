<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Topic clusters: pillar + supporting page groupings.
 *
 * Each cluster has one pillar (the canonical page on a topic) and any number of
 * supporting pages (deeper dives that link back to the pillar). A post can be
 * in multiple clusters — content overlaps in the real world.
 *
 * Once Claude has clustered the site once (via a Pending Changes proposal that
 * the human approves), future sessions only read the relevant cluster instead
 * of every page on the site.
 */
class CC_Assistant_Topic_Clusters {

	const ROLE_PILLAR     = 'pillar';
	const ROLE_SUPPORTING = 'supporting';

	/**
	 * Lazy-create the tables on first use, so existing installs that haven't
	 * been re-activated still get them.
	 */
	public static function ensure_tables() {
		global $wpdb;
		$clusters_table = $wpdb->prefix . 'cc_topic_clusters';
		// Underscores in the table name are LIKE wildcards. Escape them so the
		// existence check only matches the literal table name.
		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $clusters_table ) ) );
		if ( $exists !== $clusters_table ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-activator.php';
			CC_Assistant_Activator::activate();
		}
	}

	public static function clusters_table() {
		global $wpdb;
		return $wpdb->prefix . 'cc_topic_clusters';
	}

	public static function members_table() {
		global $wpdb;
		return $wpdb->prefix . 'cc_cluster_members';
	}

	/**
	 * List all clusters with member counts. One query, no N+1.
	 */
	public static function list_clusters() {
		global $wpdb;
		$c = self::clusters_table();
		$m = self::members_table();
		$rows = $wpdb->get_results(
			"SELECT c.*, COUNT(m.post_id) AS member_count
			 FROM $c c
			 LEFT JOIN $m m ON m.cluster_id = c.id
			 GROUP BY c.id
			 ORDER BY c.name ASC"
		);
		return $rows ?: array();
	}

	public static function get_cluster( $cluster_id ) {
		global $wpdb;
		$c = self::clusters_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $c WHERE id = %d", (int) $cluster_id ) );
	}

	public static function get_cluster_by_slug( $slug ) {
		global $wpdb;
		$c = self::clusters_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $c WHERE slug = %s", (string) $slug ) );
	}

	/**
	 * Returns members for a cluster as objects with post_id, role, added_at.
	 * Primes the post cache so subsequent get_the_title / get_permalink calls
	 * do not hit the DB once per row.
	 */
	public static function get_members( $cluster_id ) {
		global $wpdb;
		$m = self::members_table();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $m WHERE cluster_id = %d ORDER BY role ASC, added_at ASC",
				(int) $cluster_id
			)
		);
		if ( $rows ) {
			$ids = array();
			foreach ( $rows as $r ) {
				$ids[] = (int) $r->post_id;
			}
			if ( $ids ) {
				_prime_post_caches( $ids, false, false );
			}
		}
		return $rows ?: array();
	}

	/**
	 * Returns clusters that contain the given post. A post can belong to many.
	 */
	public static function clusters_for_post( $post_id ) {
		global $wpdb;
		$c = self::clusters_table();
		$m = self::members_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, m.role FROM $c c
				 INNER JOIN $m m ON m.cluster_id = c.id
				 WHERE m.post_id = %d
				 ORDER BY c.name ASC",
				(int) $post_id
			)
		) ?: array();
	}

	/**
	 * Posts that aren't in any cluster yet. Useful for the "uncategorized" view
	 * Claude can use to figure out where to slot a new page.
	 */
	public static function unclustered_post_ids( $post_type = 'page', $limit = 200 ) {
		global $wpdb;
		$m = self::members_table();
		$post_status = "'publish','draft','pending','private','future'";
		// For pages, over-fetch so the PHP-side utility filter below still
		// returns up to $limit genuine content pages.
		$fetch = ( 'page' === $post_type ) ? max( (int) $limit * 3, 100 ) : (int) $limit;
		$sql = $wpdb->prepare(
			"SELECT p.ID, p.post_name FROM {$wpdb->posts} p
			 LEFT JOIN $m m ON m.post_id = p.ID
			 WHERE p.post_type = %s
			   AND p.post_status IN ($post_status)
			   AND m.post_id IS NULL
			 ORDER BY p.post_modified DESC
			 LIMIT %d",
			$post_type,
			(int) $fetch
		);
		$rows = $wpdb->get_results( $sql );
		if ( empty( $rows ) ) {
			return array();
		}

		// Pages that should never live in a topic cluster (home, blog index,
		// contact, about, careers, and legal/policy pages in EN + ES) were
		// being counted as "unclustered", producing a misleading backlog
		// (e.g. "36 unclustered pages" that were ALL utility/legal pages while
		// every content page was already clustered). Filter them out so the
		// advisor count, the admin stat, and the bulk-assign scan all reflect
		// only genuine clusterable content. Posts are left unfiltered.
		$exclude_ids = array();
		$deny_slugs  = array();
		if ( 'page' === $post_type ) {
			$front = (int) get_option( 'page_on_front' );
			$posts = (int) get_option( 'page_for_posts' );
			if ( $front ) {
				$exclude_ids[] = $front;
			}
			if ( $posts ) {
				$exclude_ids[] = $posts;
			}
			$deny_slugs = self::non_clusterable_slug_needles();
		}

		$out = array();
		foreach ( $rows as $r ) {
			$id = (int) $r->ID;
			if ( in_array( $id, $exclude_ids, true ) ) {
				continue;
			}
			$slug = strtolower( (string) $r->post_name );
			if ( '' !== $slug && $deny_slugs ) {
				$skip = false;
				foreach ( $deny_slugs as $needle ) {
					if ( '' !== $needle && false !== strpos( $slug, $needle ) ) {
						$skip = true;
						break;
					}
				}
				if ( $skip ) {
					continue;
				}
			}
			$out[] = $id;
			if ( count( $out ) >= (int) $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Slug needles that mark a PAGE as non-clusterable: home/front, blog
	 * index, contact, about, careers, and legal/policy pages, in English
	 * and the Spanish equivalents these multilingual ER sites use. A slug
	 * containing any needle is excluded from the unclustered set. Filterable
	 * so an operator can tune per site without a code change.
	 */
	private static function non_clusterable_slug_needles() {
		$needles = array(
			// Home / blog / nav
			'home', 'pagina-principal', 'inicio', 'blog',
			// Contact / about / careers
			'contact', 'contacten', 'contactanos', 'about', 'acerca', 'nosotros',
			'career', 'carrera', 'team-section',
			// Legal / policy (EN + ES)
			'privacy', 'privacidad', 'terms', 'terminos', 'hipaa',
			'accessibility', 'accesibilidad', 'disclaimer', 'aviso-legal',
			'billing', 'facturacion', 'divulgaciones', 'insurance-billing',
			'seguros-y-facturacion', 'cookie', 'sitemap', 'thank-you', 'gracias',
		);
		/**
		 * Filter the slug needles that exclude a page from the unclustered set.
		 *
		 * @param string[] $needles Lower-case substrings matched against post_name.
		 */
		return apply_filters( 'cc_assistant_non_clusterable_slugs', $needles );
	}

	/**
	 * Create a cluster. Returns the new cluster_id, or WP_Error on conflict.
	 * Slug is auto-generated from name if not provided, with collision suffix.
	 */
	public static function create_cluster( $args ) {
		global $wpdb;
		$defaults = array(
			'name'           => '',
			'slug'           => '',
			'description'    => '',
			'pillar_post_id' => null,
			'created_by'     => 'human',
		);
		$args = wp_parse_args( $args, $defaults );

		$name = trim( (string) $args['name'] );
		if ( '' === $name ) {
			return new WP_Error( 'name_required', 'Cluster name is required.' );
		}

		$slug = $args['slug'] ? sanitize_title( $args['slug'] ) : sanitize_title( $name );
		$slug = self::ensure_unique_slug( $slug );

		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert(
			self::clusters_table(),
			array(
				'slug'           => $slug,
				'name'           => $name,
				'description'    => (string) $args['description'],
				'pillar_post_id' => $args['pillar_post_id'] ? (int) $args['pillar_post_id'] : null,
				'created_at'     => $now,
				'updated_at'     => $now,
				'created_by'     => (string) $args['created_by'],
			)
		);
		if ( false === $ok ) {
			return new WP_Error( 'db_insert_failed', 'Failed to create cluster.' );
		}
		$cluster_id = (int) $wpdb->insert_id;

		// If a pillar was given, also add it as a member with role=pillar.
		if ( $args['pillar_post_id'] ) {
			self::add_member( $cluster_id, (int) $args['pillar_post_id'], self::ROLE_PILLAR, $args['created_by'] );
		}
		return $cluster_id;
	}

	public static function update_cluster( $cluster_id, $args ) {
		global $wpdb;
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		if ( isset( $args['name'] ) ) {
			$update['name'] = trim( (string) $args['name'] );
		}
		if ( isset( $args['description'] ) ) {
			$update['description'] = (string) $args['description'];
		}
		if ( array_key_exists( 'pillar_post_id', $args ) ) {
			$update['pillar_post_id'] = $args['pillar_post_id'] ? (int) $args['pillar_post_id'] : null;
		}
		$ok = $wpdb->update( self::clusters_table(), $update, array( 'id' => (int) $cluster_id ) );
		return false !== $ok;
	}

	/**
	 * Delete a cluster and all its memberships. Posts themselves are untouched.
	 */
	public static function delete_cluster( $cluster_id ) {
		global $wpdb;
		$wpdb->delete( self::members_table(), array( 'cluster_id' => (int) $cluster_id ) );
		return false !== $wpdb->delete( self::clusters_table(), array( 'id' => (int) $cluster_id ) );
	}

	/**
	 * Add a post to a cluster. Idempotent: re-adding updates role.
	 * If role is 'pillar', the cluster's pillar_post_id is updated to match
	 * (and any other pillar member in this cluster is demoted to supporting).
	 */
	public static function add_member( $cluster_id, $post_id, $role = self::ROLE_SUPPORTING, $by = 'human' ) {
		global $wpdb;
		$cluster_id = (int) $cluster_id;
		$post_id    = (int) $post_id;
		$role       = self::ROLE_PILLAR === $role ? self::ROLE_PILLAR : self::ROLE_SUPPORTING;

		if ( self::ROLE_PILLAR === $role ) {
			// Demote any existing pillar in this cluster.
			$wpdb->update(
				self::members_table(),
				array( 'role' => self::ROLE_SUPPORTING ),
				array( 'cluster_id' => $cluster_id, 'role' => self::ROLE_PILLAR )
			);
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . self::members_table() . " WHERE cluster_id = %d AND post_id = %d",
				$cluster_id,
				$post_id
			)
		);
		if ( (int) $existing > 0 ) {
			$wpdb->update(
				self::members_table(),
				array( 'role' => $role ),
				array( 'cluster_id' => $cluster_id, 'post_id' => $post_id )
			);
		} else {
			$wpdb->insert(
				self::members_table(),
				array(
					'cluster_id' => $cluster_id,
					'post_id'    => $post_id,
					'role'       => $role,
					'added_at'   => current_time( 'mysql' ),
					'added_by'   => $by,
				)
			);
		}

		// Keep cluster.pillar_post_id in sync.
		if ( self::ROLE_PILLAR === $role ) {
			$wpdb->update(
				self::clusters_table(),
				array( 'pillar_post_id' => $post_id, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $cluster_id )
			);
		}
		return true;
	}

	public static function remove_member( $cluster_id, $post_id ) {
		global $wpdb;
		$cluster_id = (int) $cluster_id;
		$post_id    = (int) $post_id;
		$wpdb->delete(
			self::members_table(),
			array( 'cluster_id' => $cluster_id, 'post_id' => $post_id )
		);
		// If we just removed the pillar, clear the back-reference.
		$cluster = self::get_cluster( $cluster_id );
		if ( $cluster && (int) $cluster->pillar_post_id === $post_id ) {
			$wpdb->update(
				self::clusters_table(),
				array( 'pillar_post_id' => null, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $cluster_id )
			);
		}
		return true;
	}

	private static function ensure_unique_slug( $base ) {
		global $wpdb;
		$slug = $base ?: 'cluster';
		$i    = 1;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::clusters_table() . " WHERE slug = %s", $slug ) ) > 0 ) {
			$i++;
			$slug = $base . '-' . $i;
		}
		return $slug;
	}

	public static function count_clusters() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::clusters_table() );
	}

	/**
	 * Cluster-aware GSC overlay. Aggregates GSC data across every member
	 * URL in the cluster, so Claude (and the user) can see which queries
	 * the cluster as a whole ranks for, where the pillar is winning vs
	 * losing to its supporting pages, and where queries are split across
	 * multiple pages inside one topic.
	 *
	 * Heavy GROUP BY against page_hash (sha1 indexed) — cached for 30 min
	 * keyed by cluster_id + days so calling this on the admin page or
	 * from the advisor is cheap.
	 */
	public static function cluster_gsc_summary( $cluster_id, $days = 28 ) {
		global $wpdb;
		$cluster_id = (int) $cluster_id;
		$days       = max( 7, min( 90, (int) $days ) );
		$ck         = 'cc_cluster_gsc_' . $cluster_id . '_' . $days;
		$cached     = get_transient( $ck );
		if ( false !== $cached ) {
			return $cached;
		}

		$cluster = self::get_cluster( $cluster_id );
		if ( ! $cluster ) {
			return null;
		}
		$members = self::get_members( $cluster_id );

		$gsc_table = $wpdb->prefix . 'cc_gsc_queries';
		$exists    = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $gsc_table ) ) );
		$payload = array(
			'cluster_id'             => $cluster_id,
			'cluster_name'           => $cluster->name,
			'pillar_post_id'         => $cluster->pillar_post_id ? (int) $cluster->pillar_post_id : null,
			'window_days'            => $days,
			'has_gsc'                => false,
			'totals'                 => null,
			'top_queries'            => array(),
			'pillar_misses'          => array(),
			'internal_cannibalization' => array(),
			'member_breakdown'       => array(),
		);

		if ( $exists !== $gsc_table || empty( $members ) ) {
			set_transient( $ck, $payload, 30 * MINUTE_IN_SECONDS );
			return $payload;
		}

		// Map post_id -> { permalink, hash, role } for every member.
		$post_to_meta = array();
		$hash_to_post = array();
		foreach ( $members as $m ) {
			$pl = get_permalink( (int) $m->post_id );
			if ( ! $pl ) {
				continue;
			}
			$h = sha1( $pl );
			$post_to_meta[ (int) $m->post_id ] = array(
				'permalink' => $pl,
				'hash'      => $h,
				'role'      => $m->role,
				'title'     => get_the_title( (int) $m->post_id ),
			);
			$hash_to_post[ $h ] = (int) $m->post_id;
		}
		if ( empty( $hash_to_post ) ) {
			set_transient( $ck, $payload, 30 * MINUTE_IN_SECONDS );
			return $payload;
		}

		$cutoff       = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		$placeholders = implode( ',', array_fill( 0, count( $hash_to_post ), '%s' ) );
		$sql_args     = array_merge( array( $cutoff ), array_keys( $hash_to_post ) );

		// Totals: rolled up across all member URLs.
		$totals = $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM(impressions) AS impressions,
			        SUM(clicks)      AS clicks,
			        AVG(position)    AS avg_position,
			        COUNT(DISTINCT query) AS queries
			 FROM {$gsc_table}
			 WHERE date >= %s AND page_hash IN ($placeholders)",
			$sql_args
		) );
		if ( ! $totals || ! $totals->impressions ) {
			set_transient( $ck, $payload, 30 * MINUTE_IN_SECONDS );
			return $payload;
		}

		$payload['has_gsc'] = true;
		$payload['totals'] = array(
			'impressions'  => (int) $totals->impressions,
			'clicks'       => (int) $totals->clicks,
			'avg_position' => round( (float) $totals->avg_position, 2 ),
			'queries'      => (int) $totals->queries,
		);

		// Top queries across the cluster, with the page that ranks best per query.
		// Two queries: one for top queries by impressions, one for the (query, page)
		// rows in those top queries so we can pick the leading page per query.
		$top_q_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT query,
			        SUM(impressions) AS impressions,
			        SUM(clicks)      AS clicks,
			        AVG(position)    AS avg_position
			 FROM {$gsc_table}
			 WHERE date >= %s AND page_hash IN ($placeholders)
			 GROUP BY query
			 ORDER BY impressions DESC
			 LIMIT 25",
			$sql_args
		) );

		$top_queries = array();
		foreach ( (array) $top_q_rows as $r ) {
			// For each query, get the page-level breakdown.
			$page_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT page_hash,
				        SUM(impressions) AS impressions,
				        SUM(clicks)      AS clicks,
				        AVG(position)    AS position
				 FROM {$gsc_table}
				 WHERE date >= %s AND query = %s AND page_hash IN ($placeholders)
				 GROUP BY page_hash
				 ORDER BY impressions DESC",
				array_merge( array( $cutoff, $r->query ), array_keys( $hash_to_post ) )
			) );
			$pages_for_q = array();
			foreach ( (array) $page_rows as $pr ) {
				if ( ! isset( $hash_to_post[ $pr->page_hash ] ) ) {
					continue;
				}
				$pid  = $hash_to_post[ $pr->page_hash ];
				$meta = $post_to_meta[ $pid ];
				$pages_for_q[] = array(
					'post_id'      => $pid,
					'title'        => $meta['title'],
					'role'         => $meta['role'],
					'impressions'  => (int) $pr->impressions,
					'clicks'       => (int) $pr->clicks,
					'avg_position' => round( (float) $pr->position, 2 ),
				);
			}
			$top_queries[] = array(
				'query'        => $r->query,
				'impressions'  => (int) $r->impressions,
				'clicks'       => (int) $r->clicks,
				'avg_position' => round( (float) $r->avg_position, 2 ),
				'pages'        => $pages_for_q,
			);
		}
		$payload['top_queries'] = $top_queries;

		// Pillar misses: queries where a supporting page outranks the pillar
		// (or pillar doesn't appear). Strong refresh signal — if supporting
		// pages are stealing pillar queries, the pillar isn't doing its job.
		if ( $cluster->pillar_post_id && isset( $post_to_meta[ (int) $cluster->pillar_post_id ] ) ) {
			$pillar_post_id = (int) $cluster->pillar_post_id;
			foreach ( $top_queries as $q ) {
				if ( count( $q['pages'] ) < 2 ) {
					continue;
				}
				$pillar_pos    = null;
				$best_other    = null;
				foreach ( $q['pages'] as $pg ) {
					if ( $pg['post_id'] === $pillar_post_id ) {
						$pillar_pos = $pg['avg_position'];
					} elseif ( null === $best_other || $pg['avg_position'] < $best_other['avg_position'] ) {
						$best_other = $pg;
					}
				}
				// Pillar absent OR pillar ranks worse than supporting → miss.
				if ( ( null === $pillar_pos && $best_other ) || ( null !== $pillar_pos && $best_other && $best_other['avg_position'] < $pillar_pos - 2 ) ) {
					$payload['pillar_misses'][] = array(
						'query'           => $q['query'],
						'impressions'     => $q['impressions'],
						'pillar_position' => $pillar_pos,
						'winning_page'    => $best_other,
					);
				}
				if ( count( $payload['pillar_misses'] ) >= 10 ) {
					break;
				}
			}
		}

		// Internal cannibalization: same query, 2+ member pages within striking distance.
		foreach ( $top_queries as $q ) {
			$competing = array();
			foreach ( $q['pages'] as $pg ) {
				if ( $pg['avg_position'] <= 30 && $pg['impressions'] >= 10 ) {
					$competing[] = $pg;
				}
			}
			if ( count( $competing ) >= 2 ) {
				$payload['internal_cannibalization'][] = array(
					'query'       => $q['query'],
					'impressions' => $q['impressions'],
					'pages'       => $competing,
				);
			}
			if ( count( $payload['internal_cannibalization'] ) >= 10 ) {
				break;
			}
		}

		// Per-member breakdown (impressions/clicks per member URL).
		$mb_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT page_hash,
			        SUM(impressions) AS impressions,
			        SUM(clicks)      AS clicks,
			        AVG(position)    AS avg_position
			 FROM {$gsc_table}
			 WHERE date >= %s AND page_hash IN ($placeholders)
			 GROUP BY page_hash
			 ORDER BY impressions DESC",
			$sql_args
		) );
		foreach ( (array) $mb_rows as $r ) {
			if ( ! isset( $hash_to_post[ $r->page_hash ] ) ) {
				continue;
			}
			$pid  = $hash_to_post[ $r->page_hash ];
			$meta = $post_to_meta[ $pid ];
			$payload['member_breakdown'][] = array(
				'post_id'      => $pid,
				'title'        => $meta['title'],
				'role'         => $meta['role'],
				'impressions'  => (int) $r->impressions,
				'clicks'       => (int) $r->clicks,
				'avg_position' => round( (float) $r->avg_position, 2 ),
			);
		}

		set_transient( $ck, $payload, 30 * MINUTE_IN_SECONDS );
		return $payload;
	}

	/**
	 * Health snapshot for a cluster: linking density (do supporting pages link
	 * to the pillar? does the pillar link to supporting pages?) plus GSC
	 * impressions/clicks aggregated across all member URLs.
	 *
	 * Returns NULL for the GSC bits if the gsc_queries table doesn't exist or
	 * has no rows in the window. The link counts always work since they only
	 * read cc_link_graph (which the plugin maintains).
	 */
	public static function cluster_health( $cluster_id, $days = 28 ) {
		global $wpdb;
		$cluster = self::get_cluster( $cluster_id );
		if ( ! $cluster ) {
			return null;
		}
		$members = self::get_members( $cluster_id );
		if ( empty( $members ) ) {
			return array(
				'cluster_id'      => (int) $cluster_id,
				'pillar_post_id'  => $cluster->pillar_post_id ? (int) $cluster->pillar_post_id : null,
				'member_count'    => 0,
				'supporting_count'         => 0,
				'supporting_linking_pillar' => 0,
				'pillar_outbound_to_cluster' => 0,
				'gsc'                      => null,
				'verdict'                  => 'empty',
			);
		}
		$pillar_post_id = $cluster->pillar_post_id ? (int) $cluster->pillar_post_id : null;
		$supporting_ids = array();
		foreach ( $members as $m ) {
			if ( self::ROLE_SUPPORTING === $m->role ) {
				$supporting_ids[] = (int) $m->post_id;
			}
		}

		// Bilingual pillar awareness: on Polylang sites the canonical pillar
		// often has a translation twin (e.g. EN pillar 5054 + ES pillar 5058).
		// ES supporting pages link to the ES twin, not the EN pillar. Treat
		// any pillar-language twin as a valid pillar target so we don't
		// undercount cluster reciprocity on multilingual sites.
		$pillar_target_ids = array();
		if ( $pillar_post_id ) {
			$pillar_target_ids[] = $pillar_post_id;
			if ( class_exists( 'CC_Assistant_Multilingual' )
				&& CC_Assistant_Multilingual::is_active() ) {
				$twins = CC_Assistant_Multilingual::translations_of( $pillar_post_id );
				if ( is_array( $twins ) ) {
					foreach ( $twins as $twin_id ) {
						$twin_id = (int) $twin_id;
						if ( $twin_id > 0 && ! in_array( $twin_id, $pillar_target_ids, true ) ) {
							$pillar_target_ids[] = $twin_id;
						}
					}
				}
			}
		}

		// Count: how many supporting pages link to the pillar OR its language twin?
		$supporting_linking_pillar = 0;
		if ( ! empty( $pillar_target_ids ) && ! empty( $supporting_ids ) ) {
			$src_placeholders = implode( ',', array_fill( 0, count( $supporting_ids ), '%d' ) );
			$tgt_placeholders = implode( ',', array_fill( 0, count( $pillar_target_ids ), '%d' ) );
			$args             = array_merge( $supporting_ids, $pillar_target_ids );
			$supporting_linking_pillar = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT source_post_id) FROM {$wpdb->prefix}cc_link_graph
				 WHERE source_post_id IN ($src_placeholders) AND target_post_id IN ($tgt_placeholders)",
				$args
			) );
		}

		// Count: how many supporting pages does the pillar (or twin) link out to?
		$pillar_outbound_to_cluster = 0;
		if ( ! empty( $pillar_target_ids ) && ! empty( $supporting_ids ) ) {
			$src_placeholders = implode( ',', array_fill( 0, count( $pillar_target_ids ), '%d' ) );
			$tgt_placeholders = implode( ',', array_fill( 0, count( $supporting_ids ), '%d' ) );
			$args             = array_merge( $pillar_target_ids, $supporting_ids );
			$pillar_outbound_to_cluster = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT target_post_id) FROM {$wpdb->prefix}cc_link_graph
				 WHERE source_post_id IN ($src_placeholders) AND target_post_id IN ($tgt_placeholders)",
				$args
			) );
		}

		// GSC totals across all member URLs in the window.
		$gsc       = null;
		$gsc_table = $wpdb->prefix . 'cc_gsc_queries';
		$exists    = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $gsc_table ) ) );
		if ( $exists === $gsc_table ) {
			$urls = array();
			foreach ( $members as $m ) {
				$pl = get_permalink( (int) $m->post_id );
				if ( $pl ) {
					$urls[] = sha1( $pl );
				}
			}
			if ( ! empty( $urls ) ) {
				$cutoff       = gmdate( 'Y-m-d', time() - max( 7, min( 90, (int) $days ) ) * DAY_IN_SECONDS );
				$placeholders = implode( ',', array_fill( 0, count( $urls ), '%s' ) );
				$args         = array_merge( array( $cutoff ), $urls );
				$row          = $wpdb->get_row( $wpdb->prepare(
					"SELECT
					   SUM(impressions) AS impressions,
					   SUM(clicks)      AS clicks,
					   AVG(position)    AS avg_position
					 FROM {$gsc_table}
					 WHERE date >= %s AND page_hash IN ($placeholders)",
					$args
				) );
				if ( $row && $row->impressions > 0 ) {
					$gsc = array(
						'impressions'  => (int) $row->impressions,
						'clicks'       => (int) $row->clicks,
						'avg_position' => round( (float) $row->avg_position, 2 ),
						'window_days'  => (int) $days,
					);
				}
			}
		}

		// Verdict: simple rules so the UI can color the cluster card.
		$supporting_count = count( $supporting_ids );
		$verdict          = 'healthy';
		if ( ! $pillar_post_id ) {
			$verdict = 'no_pillar';
		} elseif ( 0 === $supporting_count ) {
			$verdict = 'pillar_only';
		} elseif ( $supporting_linking_pillar < $supporting_count ) {
			$verdict = 'weak_linking';
		}

		return array(
			'cluster_id'                 => (int) $cluster_id,
			'pillar_post_id'             => $pillar_post_id,
			'member_count'               => count( $members ),
			'supporting_count'           => $supporting_count,
			'supporting_linking_pillar'  => $supporting_linking_pillar,
			'pillar_outbound_to_cluster' => $pillar_outbound_to_cluster,
			'gsc'                        => $gsc,
			'verdict'                    => $verdict,
		);
	}

	/**
	 * Queue a cluster creation as a pending change for human approval.
	 * proposed_value carries the full plan (name, pillar, supporting ids).
	 * post_id is left NULL because no single post is being modified.
	 *
	 * @return int|WP_Error The pending_change id on success.
	 */
	public static function propose_cluster_create( $args ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		$name = trim( (string) ( $args['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'name_required', 'Cluster name is required.' );
		}
		$payload = array(
			'name'                => $name,
			'description'         => (string) ( $args['description'] ?? '' ),
			'pillar_post_id'      => isset( $args['pillar_post_id'] ) && (int) $args['pillar_post_id'] > 0 ? (int) $args['pillar_post_id'] : null,
			'supporting_post_ids' => array_values( array_unique( array_map( 'intval', (array) ( $args['supporting_post_ids'] ?? array() ) ) ) ),
		);
		$summary = isset( $args['summary'] ) && $args['summary'] !== ''
			? (string) $args['summary']
			: sprintf( 'Create cluster "%s" with %d page(s)', $name, count( $payload['supporting_post_ids'] ) + ( $payload['pillar_post_id'] ? 1 : 0 ) );

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'         => null,
			'change_type'     => 'cluster_create',
			'change_summary'  => $summary,
			'current_value'   => wp_json_encode( array( 'cluster' => null ) ),
			'proposed_value'  => wp_json_encode( $payload ),
			'reasoning'       => (string) ( $args['reasoning'] ?? '' ),
			'success_metrics' => isset( $args['success_metrics'] ) ? $args['success_metrics'] : null,
			'status'          => 'pending',
			'created_by'      => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }
		return $pending_id;
	}

	/**
	 * Bulk-suggest cluster assignments for currently-unclustered posts.
	 *
	 * v0.21.0: built to clear the "84 unclustered pages" backlog without
	 * forcing the operator to triage one-by-one.
	 *
	 * v0.21.2: scoring rewritten to fix two real failures from the first
	 * dry-run on eroflufkin:
	 *   1) Tie-breaks were resolved by iteration order (alphabetical cluster
	 *      name first), so post 1091 Appendicitis hit BOTH cluster 9 Cardiac
	 *      and cluster 5 Gastrointestinal at score 0.263 and got picked into
	 *      Cardiac — wrong.
	 *   2) Big clusters (Cardiac with 11 members) had bloated signatures from
	 *      member-title tokens, so generic posts ("Treatment", "Care") matched
	 *      Cardiac better than their actual topical cluster.
	 *
	 * New scoring:
	 *   - Per post token, weight match by source: cluster CORE (name + description)
	 *     = 1.0, member-titles-only = 0.3. So topical posts whose tokens hit the
	 *     cluster's defining vocabulary win, instead of whichever cluster has the
	 *     largest member-title vocabulary by accident.
	 *   - Light stemming (s / es / ies) so "emergencies" matches "emergency",
	 *     "babies" matches "baby", "guides" matches "guide". Same stemming v0.20.1
	 *     applied to keyword_coverage.
	 *   - Tie-break: prefer (a) more core matches (stronger name signal),
	 *     then (b) smaller cluster (more topically specific), then post-id desc.
	 *
	 * Returns the full candidate list (queued OR skipped) so the operator
	 * can see exactly what was matched, what was skipped, and why. Defaults
	 * are conservative — runs in dry_run mode by default so a first call
	 * never queues anything until the operator has reviewed the matches.
	 *
	 * @param array $args {
	 *   @type float $min_score   Weighted-score threshold 0..1. Default 0.4.
	 *   @type int   $limit       Max unclustered posts to evaluate. Default 200.
	 *   @type int   $max_queue   Max pending changes to queue in one call. Default 20.
	 *   @type bool  $dry_run     When true, returns candidates without queueing. Default TRUE.
	 *   @type array $post_types  Post types to scan. Default ['post','page'].
	 * }
	 *
	 * @return array {candidates, queued, skipped_low_score, total_eval, dry_run}.
	 */
	public static function suggest_assignments( $args = array() ) {
		$defaults = array(
			'min_score'  => 0.4,
			'limit'      => 200,
			'max_queue'  => 20,
			'dry_run'    => true,
			'post_types' => array( 'post', 'page' ),
		);
		$args = wp_parse_args( $args, $defaults );

		$clusters = self::list_clusters();
		if ( empty( $clusters ) ) {
			return array(
				'candidates'         => array(),
				'queued'             => 0,
				'skipped_low_score'  => 0,
				'total_eval'         => 0,
				'dry_run'            => (bool) $args['dry_run'],
				'error'              => 'No clusters defined on this site. Create at least one cluster before running bulk-suggest.',
			);
		}

		// Build a per-cluster signature with TWO token sets:
		//   - core: tokens from cluster name + description (strongest topical signal)
		//   - members: tokens only present in member titles (weaker signal)
		// Member-only tokens are core-subtracted so each token gets exactly one
		// weight when scored, never double-counted.
		$cluster_sigs = array();
		foreach ( $clusters as $c ) {
			$core_text   = (string) $c->name . ' ' . (string) $c->description;
			$member_text = '';
			$members     = self::get_members( (int) $c->id );
			foreach ( $members as $m ) {
				$title = get_the_title( (int) $m->post_id );
				if ( $title ) {
					$member_text .= ' ' . $title;
				}
			}
			$core_tokens   = self::cluster_tokenize( $core_text );
			$member_tokens = array_values( array_diff( self::cluster_tokenize( $member_text ), $core_tokens ) );
			$cluster_sigs[ (int) $c->id ] = array(
				'cluster'      => $c,
				'core'         => array_flip( $core_tokens ),
				'members'      => array_flip( $member_tokens ),
				'member_count' => count( $members ),
			);
		}

		// Gather unclustered post IDs across all requested post types.
		$unclustered = array();
		$per_type    = max( 10, (int) ceil( $args['limit'] / max( 1, count( $args['post_types'] ) ) ) );
		foreach ( (array) $args['post_types'] as $pt ) {
			$ids = self::unclustered_post_ids( (string) $pt, $per_type );
			foreach ( $ids as $id ) {
				$unclustered[] = (int) $id;
			}
		}
		$unclustered = array_values( array_unique( $unclustered ) );

		if ( empty( $unclustered ) ) {
			return array(
				'candidates'         => array(),
				'queued'             => 0,
				'skipped_low_score'  => 0,
				'total_eval'         => 0,
				'dry_run'            => (bool) $args['dry_run'],
				'message'            => 'No unclustered posts found.',
			);
		}

		// Per-post scoring loop.
		$candidates = array();
		foreach ( $unclustered as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			// Title counted 2x so title tokens dominate excerpt tokens.
			$post_text   = $post->post_title . ' ' . $post->post_title . ' ' .
				( $post->post_excerpt !== '' ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ) );
			$post_tokens = self::cluster_tokenize( $post_text );
			if ( empty( $post_tokens ) ) {
				continue;
			}
			$post_token_count = count( $post_tokens );

			// Compute per-cluster weighted result, then pick with tie-break.
			$cluster_results = array();
			$score_table     = array();
			foreach ( $cluster_sigs as $cid => $sig ) {
				$core_matches   = array();
				$member_matches = array();
				$raw_score      = 0.0;
				foreach ( $post_tokens as $t ) {
					if ( isset( $sig['core'][ $t ] ) ) {
						$raw_score       += 1.0;
						$core_matches[]   = $t;
					} elseif ( isset( $sig['members'][ $t ] ) ) {
						$raw_score       += 0.3;
						$member_matches[] = $t;
					}
				}
				$weighted_score      = $raw_score / $post_token_count;
				$score_table[ $cid ] = round( $weighted_score, 3 );
				$cluster_results[ $cid ] = array(
					'cluster'        => $sig['cluster'],
					'score'          => $weighted_score,
					'core_matches'   => $core_matches,
					'member_matches' => $member_matches,
					'member_count'   => $sig['member_count'],
				);
			}

			// Pick best with deterministic tie-break.
			$best_cid    = 0;
			$best_result = null;
			foreach ( $cluster_results as $cid => $r ) {
				if ( $best_result === null ) {
					$best_cid    = $cid;
					$best_result = $r;
					continue;
				}
				// Primary: higher weighted score wins. Use small epsilon for float compare.
				if ( $r['score'] > $best_result['score'] + 0.0001 ) {
					$best_cid    = $cid;
					$best_result = $r;
				} elseif ( abs( $r['score'] - $best_result['score'] ) < 0.0001 ) {
					// Tied on score — prefer more core matches (stronger name signal).
					$r_core    = count( $r['core_matches'] );
					$best_core = count( $best_result['core_matches'] );
					if ( $r_core > $best_core ) {
						$best_cid    = $cid;
						$best_result = $r;
					} elseif ( $r_core === $best_core && $r['member_count'] < $best_result['member_count'] ) {
						// Still tied — prefer SMALLER cluster (more topically specific).
						$best_cid    = $cid;
						$best_result = $r;
					}
				}
			}

			$best_cluster   = $best_result ? $best_result['cluster'] : null;
			$best_score     = $best_result ? $best_result['score'] : 0.0;
			$matched_combo  = $best_result ? array_merge( $best_result['core_matches'], $best_result['member_matches'] ) : array();
			$matched_combo  = array_slice( array_values( array_unique( $matched_combo ) ), 0, 8 );

			$candidates[] = array(
				'post_id'         => $post_id,
				'post_title'      => $post->post_title,
				'post_type'       => $post->post_type,
				'cluster_id'      => $best_cluster ? (int) $best_cluster->id : 0,
				'cluster_name'    => $best_cluster ? (string) $best_cluster->name : '',
				'score'           => round( $best_score, 3 ),
				'core_matches'    => $best_result ? array_slice( $best_result['core_matches'], 0, 6 ) : array(),
				'member_matches'  => $best_result ? array_slice( $best_result['member_matches'], 0, 6 ) : array(),
				'matched_terms'   => $matched_combo,
				'meets_threshold' => $best_score >= (float) $args['min_score'],
				'all_scores'      => $score_table,
			);
		}

		// Sort by score descending so the strongest matches are at the top.
		usort( $candidates, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		// Queue assignments (or just mark in dry_run mode) for those meeting threshold.
		$queued      = 0;
		$skipped_low = 0;
		foreach ( $candidates as $i => $c ) {
			if ( ! $c['meets_threshold'] ) {
				$skipped_low++;
				$candidates[ $i ]['queued']         = false;
				$candidates[ $i ]['skipped_reason'] = 'score_below_threshold';
				continue;
			}
			if ( $queued >= (int) $args['max_queue'] ) {
				$candidates[ $i ]['queued']         = false;
				$candidates[ $i ]['skipped_reason'] = 'max_queue_reached';
				continue;
			}
			if ( $args['dry_run'] ) {
				$candidates[ $i ]['queued']         = false;
				$candidates[ $i ]['skipped_reason'] = 'dry_run';
				continue;
			}
			$pending = self::propose_cluster_assignment( array(
				'cluster_id' => $c['cluster_id'],
				'post_id'    => $c['post_id'],
				'role'       => self::ROLE_SUPPORTING,
				'reasoning'  => sprintf(
					'Bulk auto-suggest (v0.21.2): post tokens hit cluster "%s" core vocabulary [%s] and member-title vocabulary [%s]; weighted score %.2f.',
					$c['cluster_name'],
					implode( ', ', $c['core_matches'] ),
					implode( ', ', $c['member_matches'] ),
					$c['score']
				),
				'summary'    => sprintf( 'Bulk-assign "%s" → cluster "%s" (score %.2f)', $c['post_title'], $c['cluster_name'], $c['score'] ),
			) );
			if ( is_wp_error( $pending ) ) {
				$candidates[ $i ]['queued'] = false;
				$candidates[ $i ]['error']  = $pending->get_error_message();
			} else {
				$candidates[ $i ]['queued']     = true;
				$candidates[ $i ]['pending_id'] = (int) $pending;
				$queued++;
			}
		}

		return array(
			'candidates'        => $candidates,
			'queued'            => $queued,
			'skipped_low_score' => $skipped_low,
			'total_eval'        => count( $candidates ),
			'dry_run'           => (bool) $args['dry_run'],
			'min_score'         => (float) $args['min_score'],
			'max_queue'         => (int) $args['max_queue'],
			'algorithm'         => 'weighted_v0.21.2',
		);
	}

	/**
	 * Tokenize text for cluster matching. Strip HTML, lowercase, drop short/long
	 * tokens, drop stopwords + generic content-marketing words that don't carry
	 * topical signal ('guide', 'tips', 'symptoms' — these appear in every post
	 * on a content site and would dominate the score). Then light-stem each
	 * token (s / es / ies normalization) so "emergencies" ↔ "emergency",
	 * "babies" ↔ "baby", "guides" ↔ "guide" all match across post-vs-cluster
	 * comparisons. De-duplicated so a token that appears 10 times in the post
	 * body counts the same as once — keeps the score about coverage, not frequency.
	 */
	private static function cluster_tokenize( $text ) {
		$text = mb_strtolower( wp_strip_all_tags( (string) $text ) );
		$text = preg_replace( '/[^a-z0-9\s]+/', ' ', $text );
		$tokens = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$stop = array_flip( array(
			// Standard English stopwords
			'the', 'a', 'an', 'and', 'or', 'but', 'of', 'in', 'on', 'for', 'to', 'with', 'from',
			'when', 'what', 'how', 'why', 'where', 'who', 'are', 'is', 'was', 'were', 'this', 'that',
			'these', 'those', 'your', 'my', 'you', 'our', 'we', 'by', 'at', 'as', 'it', 'be', 'do',
			'can', 'should', 'will', 'have', 'has', 'had', 'about', 'into', 'than', 'then', 'so',
			'if', 'no', 'yes', 'not', 'all', 'any', 'one', 'two',
			// Content-marketing filler that appears in every post title/excerpt
			'guide', 'tips', 'help', 'best', 'top', 'new', 'quick', 'easy', 'every', 'much',
			'see', 'get', 'know', 'use', 'make', 'find', 'need', 'take', 'goes', 'comes',
		) );
		$out = array();
		foreach ( $tokens as $t ) {
			if ( strlen( $t ) < 3 || strlen( $t ) > 30 ) {
				continue;
			}
			if ( isset( $stop[ $t ] ) ) {
				continue;
			}
			$out[] = self::cluster_stem( $t );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Light singular/plural stemmer. Same shape as the keyword_coverage stem
	 * helper added in v0.20.1 — handles the common medical-content cases:
	 *   emergencies ↔ emergency, infants ↔ infant, babies ↔ baby, guides ↔ guide
	 * Crude by design — works on long-enough tokens; leaves short tokens alone.
	 */
	private static function cluster_stem( $word ) {
		$len = strlen( $word );
		if ( $len >= 5 && substr( $word, -3 ) === 'ies' ) {
			return substr( $word, 0, -3 ) . 'y';
		}
		if ( $len >= 5 && substr( $word, -2 ) === 'es' ) {
			return substr( $word, 0, -2 );
		}
		if ( $len >= 4 && substr( $word, -1 ) === 's' ) {
			return substr( $word, 0, -1 );
		}
		return $word;
	}

	/**
	 * Queue a cluster-membership assignment as a pending change. Used when a
	 * new or existing post should join an existing cluster.
	 *
	 * @return int|WP_Error The pending_change id on success.
	 */
	public static function propose_cluster_assignment( $args ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		$cluster_id = (int) ( $args['cluster_id'] ?? 0 );
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$role       = ( ( $args['role'] ?? '' ) === self::ROLE_PILLAR ) ? self::ROLE_PILLAR : self::ROLE_SUPPORTING;

		if ( $cluster_id <= 0 || $post_id <= 0 ) {
			return new WP_Error( 'invalid_payload', 'cluster_id and post_id are required.' );
		}
		$cluster = self::get_cluster( $cluster_id );
		if ( ! $cluster ) {
			return new WP_Error( 'cluster_not_found', 'Cluster does not exist.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post does not exist.' );
		}

		$payload = array(
			'cluster_id' => $cluster_id,
			'post_id'    => $post_id,
			'role'       => $role,
		);
		$summary = isset( $args['summary'] ) && $args['summary'] !== ''
			? (string) $args['summary']
			: sprintf( 'Add "%s" to cluster "%s" as %s', get_the_title( $post_id ), $cluster->name, $role );

		$pending_id = CC_Assistant_Pending_Changes::queue( array(
			'post_id'         => $post_id,
			'change_type'     => 'cluster_assign',
			'change_summary'  => $summary,
			'current_value'   => wp_json_encode( array( 'cluster_id' => null, 'role' => null ) ),
			'proposed_value'  => wp_json_encode( $payload ),
			'reasoning'       => (string) ( $args['reasoning'] ?? '' ),
			'success_metrics' => isset( $args['success_metrics'] ) ? $args['success_metrics'] : null,
			'status'          => 'pending',
			'created_by'      => 'claude',
		) );
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }
		return $pending_id;
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Topic similarity detection via TF-IDF cosine similarity.
 *
 * Computes a TF (term frequency) vector per post, caches it, then layers IDF
 * (inverse document frequency) at query time. Pure PHP, no external service.
 * Good enough for sites up to a few thousand posts.
 */
class CC_Assistant_Similarity {

	const TITLE_WEIGHT   = 3;
	const HEADING_WEIGHT = 2;
	const MODEL_NAME     = 'tf-idf-v2';

	private static $stopwords = array(
		'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'if', 'in',
		'into', 'is', 'it', 'its', 'no', 'not', 'of', 'on', 'or', 'such', 'that',
		'the', 'their', 'then', 'there', 'these', 'they', 'this', 'to', 'was',
		'will', 'with', 'we', 'our', 'us', 'you', 'your', 'i', 'me', 'my', 'mine',
		'he', 'she', 'his', 'her', 'him', 'them', 'who', 'what', 'when', 'where',
		'why', 'how', 'all', 'any', 'each', 'few', 'more', 'most', 'other', 'some',
		'than', 'too', 'very', 'can', 'just', 'should', 'now', 'also', 'about',
		'over', 'have', 'has', 'had', 'do', 'does', 'did', 'been', 'being', 'were',
		'am', 'so', 'up', 'down', 'out', 'off', 'from', 'after', 'before', 'while',
		'because', 'against', 'between', 'through', 'during', 'above', 'below',
		'only', 'same', 'further', 'here', 'both', 'against',
	);

	/**
	 * Find clusters of similar posts.
	 *
	 * @param array  $post_ids   Posts to compare.
	 * @param float  $threshold  Minimum cosine similarity to count as overlap (0..1).
	 * @return array Clusters, sorted by average similarity desc.
	 */
	public static function find_clusters( $post_ids, $threshold = 0.7 ) {
		// Self-heal stale cache: any embedding row whose `model` column does not
		// match the current MODEL_NAME was computed with an older tokenizer and
		// must be discarded so this run uses fresh vectors.
		self::purge_stale_cache();

		$vectors = array();
		$skipped = array();

		foreach ( $post_ids as $id ) {
			$v = self::get_or_compute_vector( $id );
			if ( null === $v || empty( $v['vector'] ) ) {
				$skipped[] = $id;
				continue;
			}
			$vectors[ $id ] = $v['vector'];
		}

		if ( count( $vectors ) < 2 ) {
			return array(
				'clusters'        => array(),
				'considered'      => count( $vectors ),
				'skipped_post_ids' => $skipped,
				'threshold'       => $threshold,
			);
		}

		$idf    = self::build_idf( $vectors );
		$tfidfs = array();
		foreach ( $vectors as $id => $tf ) {
			$tfidfs[ $id ] = self::tfidf( $tf, $idf );
		}

		// Body cosine alone, with TF-IDF over the weighted (title*3 + headings*2 + body)
		// text. IDF is corpus-aware, so headings shared across many posts (Symptoms,
		// Causes, Treatment, FAQs) automatically lose weight, while distinctive
		// topic words drive the score. Raw heading Jaccard was removed because it
		// over-rewarded the structural template that every medical post reuses.
		require_once CC_ASSISTANT_DIR . 'includes/class-multilingual.php';
		$ids               = array_keys( $tfidfs );
		$pairs             = array();
		$translation_drops = 0;
		$count             = count( $ids );
		for ( $i = 0; $i < $count; $i++ ) {
			$mag_i = self::magnitude( $tfidfs[ $ids[ $i ] ] );
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$body_sim = self::cosine( $tfidfs[ $ids[ $i ] ], $tfidfs[ $ids[ $j ] ], $mag_i, null );
				if ( $body_sim >= $threshold ) {
					// Translation pairs (the Spanish copy of an English post)
					// are not duplicates — they target different language
					// audiences. Drop them so the cluster output focuses on
					// genuine cannibalization candidates.
					if ( CC_Assistant_Multilingual::are_translations( $ids[ $i ], $ids[ $j ] ) ) {
						$translation_drops++;
						continue;
					}
					$pairs[] = array(
						'a'           => (int) $ids[ $i ],
						'b'           => (int) $ids[ $j ],
						'similarity'  => round( $body_sim, 4 ),
						'body_cosine' => round( $body_sim, 4 ),
					);
				}
			}
		}

		$clusters = self::group_pairs( $pairs );
		usort(
			$clusters,
			function ( $x, $y ) {
				return $y['avg_similarity'] <=> $x['avg_similarity'];
			}
		);

		return array(
			'clusters'         => $clusters,
			'considered'       => count( $vectors ),
			'skipped_post_ids' => $skipped,
			'threshold'        => $threshold,
			'total_pairs'      => count( $pairs ),
			'translation_pairs_dropped' => $translation_drops,
			'model'            => self::MODEL_NAME,
		);
	}

	/**
	 * Return raw text + headings for a list of posts so Claude can analyze the cluster.
	 */
	public static function get_cluster_payload( $post_ids, $excerpt_chars = 500 ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';

		$result = array();
		foreach ( $post_ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$has_elementor = (bool) get_post_meta( $id, '_elementor_data', true );
			$headings      = array();
			$body_text     = '';

			if ( $has_elementor ) {
				$parsed = CC_Assistant_Elementor_Parser::parse( $id );
				if ( $parsed ) {
					$headings  = $parsed['headings'];
					$body_text = $parsed['all_text'];
				}
			} else {
				$body_text = wp_strip_all_tags( $post->post_content );
				if ( preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', $post->post_content, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $row ) {
						$headings[] = array(
							'level' => 'h' . $row[1],
							'text'  => trim( wp_strip_all_tags( $row[2] ) ),
						);
					}
				}
			}

			$result[] = array(
				'post_id'       => $post->ID,
				'title'         => $post->post_title,
				'permalink'     => get_permalink( $post->ID ),
				'edit_url'      => get_edit_post_link( $post->ID, 'raw' ),
				'word_count'    => $body_text ? str_word_count( $body_text ) : 0,
				'modified'      => $post->post_modified,
				'has_elementor' => $has_elementor,
				'headings'      => $headings,
				'body_excerpt'  => mb_substr( $body_text, 0, $excerpt_chars ),
			);
		}
		return $result;
	}

	/**
	 * Get vector from cache or compute fresh, updating cache as needed.
	 */
	public static function get_or_compute_vector( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_embeddings';

		$current = self::compute_vector( $post_id );
		if ( null === $current ) {
			return null;
		}

		$cached = $wpdb->get_row( $wpdb->prepare( "SELECT content_hash, embedding, model FROM $table WHERE post_id = %d", $post_id ) );
		if ( $cached
			&& $cached->content_hash === $current['hash']
			&& $cached->model === self::MODEL_NAME ) {
			return array(
				'vector' => json_decode( $cached->embedding, true ),
				'hash'   => $cached->content_hash,
				'cached' => true,
			);
		}

		$wpdb->replace(
			$table,
			array(
				'post_id'      => $post_id,
				'content_hash' => $current['hash'],
				'embedding'    => wp_json_encode( $current['vector'] ),
				'model'        => self::MODEL_NAME,
				'created_at'   => current_time( 'mysql' ),
			)
		);

		return array(
			'vector' => $current['vector'],
			'hash'   => $current['hash'],
			'cached' => false,
		);
	}

	/**
	 * Compute the TF vector for one post.
	 */
	private static function compute_vector( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$title    = $post->post_title;
		$body     = '';
		$headings = array();

		if ( get_post_meta( $post_id, '_elementor_data', true ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';
			$parsed = CC_Assistant_Elementor_Parser::parse( $post_id );
			if ( $parsed ) {
				$body = $parsed['all_text'];
				foreach ( $parsed['headings'] as $h ) {
					$headings[] = $h['text'];
				}
			}
		} else {
			$body = wp_strip_all_tags( $post->post_content );
			if ( preg_match_all( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', $post->post_content, $m ) ) {
				foreach ( $m[1] as $h ) {
					$headings[] = wp_strip_all_tags( $h );
				}
			}
		}

		if ( empty( $title ) && empty( $body ) ) {
			return null;
		}

		$weighted_text = str_repeat( ' ' . $title, self::TITLE_WEIGHT );
		if ( ! empty( $headings ) ) {
			$weighted_text .= str_repeat( ' ' . implode( ' ', $headings ), self::HEADING_WEIGHT );
		}
		$weighted_text .= ' ' . $body;

		$tokens = self::tokenize( $weighted_text );
		if ( empty( $tokens ) ) {
			return null;
		}

		$tf = array();
		foreach ( $tokens as $t ) {
			$tf[ $t ] = isset( $tf[ $t ] ) ? $tf[ $t ] + 1 : 1;
		}

		// Hash includes MODEL_NAME so bumping the algorithm version automatically
		// invalidates every cached vector without a separate migration.
		$hash = hash( 'sha256', self::MODEL_NAME . '|' . $title . '|' . $body . '|' . implode( '|', $headings ) );
		return array(
			'vector' => $tf,
			'hash'   => $hash,
		);
	}

	/**
	 * Public text-input term extraction (v0.41). Same pipeline tokenize() uses
	 * (URL/phone/brand stripping, stopwords, length bounds) but callable on any
	 * raw text — used by the win-audit to diff our page's term coverage against
	 * a fetched competitor body. Returns unique terms.
	 */
	public static function terms_for_text( $text ) {
		return array_values( array_unique( self::tokenize( wp_strip_all_tags( (string) $text ) ) ) );
	}

	private static function tokenize( $text ) {
		$text = strtolower( (string) $text );

		// Strip URLs (CTAs, service links, phone-tel hrefs all leave URL fragments
		// in post_content even after wp_strip_all_tags). The hostname token in
		// particular gets repeated in every post on the site and inflates similarity.
		$text = preg_replace( '#https?://\S+#u', ' ', $text );
		$text = preg_replace( '#\b[\w.-]+\.(?:com|org|net|io|local|gov|edu|co|us)\b#u', ' ', $text );

		// Strip US-style phone numbers (every ER post repeats the office number).
		$text = preg_replace( '/\+?\d?[\s.\-(]*\d{3}[\s.\-)]*\d{3}[\s.\-]?\d{4}/u', ' ', $text );

		// Strip the site's own brand / domain tokens so the brand name does not
		// dominate every post's vector.
		foreach ( self::brand_stop_terms() as $term ) {
			if ( '' === $term ) {
				continue;
			}
			$text = preg_replace( '/\b' . preg_quote( $term, '/' ) . '\b/u', ' ', $text );
		}

		$text   = preg_replace( '/[^a-z0-9\s]+/', ' ', $text );
		$tokens = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		$stop = array_flip( self::$stopwords );
		$out  = array();
		foreach ( $tokens as $t ) {
			if ( strlen( $t ) < 3 || strlen( $t ) > 30 ) {
				continue;
			}
			if ( isset( $stop[ $t ] ) ) {
				continue;
			}
			if ( ctype_digit( $t ) && strlen( $t ) > 4 ) {
				continue;
			}
			$out[] = $t;
		}
		return $out;
	}

	/**
	 * Site-specific terms to strip from the corpus before tokenizing. These are
	 * brand tokens and common CTA boilerplate that every post on the site shares,
	 * which would otherwise dominate the similarity score.
	 *
	 * Cached per-request because tokenize() is called once per post.
	 */
	private static function brand_stop_terms() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}

		$out = array();

		// User-set brand terms (Settings > General > Brand terms).
		if ( class_exists( 'CC_Assistant_Query_Tagger' ) ) {
			$out = array_merge( $out, CC_Assistant_Query_Tagger::brand_terms() );
		}

		// Hostname components.
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host ) {
			$host  = preg_replace( '/^www\./', '', mb_strtolower( $host ) );
			$out[] = $host;
			$first = explode( '.', $host );
			if ( ! empty( $first[0] ) && strlen( $first[0] ) > 2 ) {
				$out[] = $first[0];
			}
		}

		// Site title tokens (e.g. "ER of White Rock" -> white, rock; "Irving
		// Health and Wellness Clinic" -> irving, health, wellness, clinic) so a
		// site's own brand/city words don't dominate similarity. DERIVED PER
		// SITE — never hardcode one tenant's city/state. (Before v0.37 this line
		// hardcoded 'irving','tx','texas', which silently skewed clustering /
		// cannibalization / topical-authority on every NON-Irving tenant.)
		$title = get_bloginfo( 'name' );
		if ( $title ) {
			foreach ( preg_split( '/[\s\-_,&]+/', mb_strtolower( (string) $title ) ) as $tok ) {
				$tok = trim( $tok );
				if ( '' !== $tok && mb_strlen( $tok ) >= 3 ) {
					$out[] = $tok;
				}
			}
		}
		// Optional operator-supplied geo/region words (state, county, metro) that
		// the host+title don't capture. Filterable + option-backed; empty default.
		$geo_terms = get_option( 'cc_assistant_geo_terms', array() );
		$geo_terms = apply_filters( 'cc_assistant_geo_terms', $geo_terms );
		if ( is_array( $geo_terms ) ) {
			foreach ( $geo_terms as $g ) {
				$g = trim( mb_strtolower( (string) $g ) );
				if ( '' !== $g ) {
					$out[] = $g;
				}
			}
		}

		$cached = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'mb_strtolower', $out ) ) ) ) );
		return $cached;
	}

	/**
	 * Drop any cached embedding rows that were computed with a different
	 * algorithm version. Called at the start of find_clusters() so cache
	 * invalidation is automatic when MODEL_NAME bumps.
	 */
	public static function purge_stale_cache() {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_embeddings';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE model IS NULL OR model <> %s", self::MODEL_NAME ) );
	}

	private static function build_idf( $vectors ) {
		$doc_count = count( $vectors );
		$df        = array();
		foreach ( $vectors as $tf ) {
			foreach ( array_keys( $tf ) as $term ) {
				$df[ $term ] = isset( $df[ $term ] ) ? $df[ $term ] + 1 : 1;
			}
		}
		$idf = array();
		foreach ( $df as $term => $count ) {
			$idf[ $term ] = log( ( 1 + $doc_count ) / ( 1 + $count ) ) + 1;
		}
		return $idf;
	}

	private static function tfidf( $tf, $idf ) {
		$tfidf = array();
		$total = array_sum( $tf );
		if ( 0 === $total ) {
			return $tfidf;
		}
		foreach ( $tf as $term => $count ) {
			if ( ! isset( $idf[ $term ] ) ) {
				continue;
			}
			$tfidf[ $term ] = ( $count / $total ) * $idf[ $term ];
		}
		return $tfidf;
	}

	private static function magnitude( $vec ) {
		$sum = 0.0;
		foreach ( $vec as $v ) {
			$sum += $v * $v;
		}
		return sqrt( $sum );
	}

	private static function cosine( $a, $b, $mag_a = null, $mag_b = null ) {
		if ( null === $mag_a ) {
			$mag_a = self::magnitude( $a );
		}
		if ( null === $mag_b ) {
			$mag_b = self::magnitude( $b );
		}
		if ( 0.0 === $mag_a || 0.0 === $mag_b ) {
			return 0.0;
		}
		$dot          = 0.0;
		$intersection = array_intersect_key( $a, $b );
		foreach ( $intersection as $term => $weight ) {
			$dot += $a[ $term ] * $b[ $term ];
		}
		return $dot / ( $mag_a * $mag_b );
	}

	/**
	 * Get a normalized set of heading tokens for a post (for Jaccard similarity).
	 * Tokens are lowercased and stripped of city/state names so location pages
	 * with otherwise identical headings score as similar.
	 */
	private static function heading_set( $post_id ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-elementor-parser.php';

		$headings = array();
		if ( get_post_meta( $post_id, '_elementor_data', true ) ) {
			$parsed = CC_Assistant_Elementor_Parser::parse( $post_id );
			if ( $parsed ) {
				foreach ( $parsed['headings'] as $h ) {
					$headings[] = $h['text'];
				}
			}
		} else {
			$post = get_post( $post_id );
			if ( $post && preg_match_all( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', $post->post_content, $m ) ) {
				$headings = array_map( 'wp_strip_all_tags', $m[1] );
			}
		}

		// Bag-of-tokens across all headings. City names and other unique tokens
		// dilute the set; common topic words drive the Jaccard score up.
		$all_heading_text = implode( ' ', $headings );
		$tokens           = self::tokenize( $all_heading_text );
		return array_values( array_unique( $tokens ) );
	}

	private static function jaccard( $a, $b ) {
		if ( empty( $a ) || empty( $b ) ) {
			return 0.0;
		}
		$set_a        = array_flip( $a );
		$set_b        = array_flip( $b );
		$intersection = count( array_intersect_key( $set_a, $set_b ) );
		$union        = count( $set_a + $set_b );
		return $union > 0 ? $intersection / $union : 0.0;
	}

	private static function group_pairs( $pairs ) {
		$parent = array();

		$find = function ( $x ) use ( &$parent, &$find ) {
			while ( isset( $parent[ $x ] ) && $parent[ $x ] !== $x ) {
				$parent[ $x ] = isset( $parent[ $parent[ $x ] ] ) ? $parent[ $parent[ $x ] ] : $parent[ $x ];
				$x            = $parent[ $x ];
			}
			return $x;
		};

		foreach ( $pairs as $p ) {
			if ( ! isset( $parent[ $p['a'] ] ) ) {
				$parent[ $p['a'] ] = $p['a'];
			}
			if ( ! isset( $parent[ $p['b'] ] ) ) {
				$parent[ $p['b'] ] = $p['b'];
			}
			$ra = $find( $p['a'] );
			$rb = $find( $p['b'] );
			if ( $ra !== $rb ) {
				$parent[ $ra ] = $rb;
			}
		}

		$groups = array();
		foreach ( array_keys( $parent ) as $id ) {
			$root              = $find( $id );
			$groups[ $root ][] = $id;
		}

		$clusters = array();
		foreach ( $groups as $group ) {
			if ( count( $group ) < 2 ) {
				continue;
			}
			sort( $group );
			$cluster_pairs = array();
			$total         = 0.0;
			foreach ( $pairs as $p ) {
				if ( in_array( $p['a'], $group, true ) && in_array( $p['b'], $group, true ) ) {
					$cluster_pairs[] = $p;
					$total          += $p['similarity'];
				}
			}
			$avg = count( $cluster_pairs ) > 0 ? $total / count( $cluster_pairs ) : 0;

			$titles = array();
			foreach ( $group as $pid ) {
				$titles[] = array(
					'post_id' => $pid,
					'title'   => get_the_title( $pid ),
				);
			}

			$clusters[] = array(
				'post_ids'       => array_map( 'intval', $group ),
				'titles'         => $titles,
				'size'           => count( $group ),
				'avg_similarity' => round( $avg, 4 ),
				'pairs'          => $cluster_pairs,
			);
		}

		return $clusters;
	}
}

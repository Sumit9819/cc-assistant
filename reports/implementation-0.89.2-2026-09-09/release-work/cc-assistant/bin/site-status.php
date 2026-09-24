<?php
/**
 * site_status tool: one synthesis answering "how is this site doing, what is
 * happening, and where is it lacking".
 *
 * WHY THIS EXISTS. The plugin already had fourteen admin screens and a dozen
 * audits, each answering one narrow question well. None of them surfaced the
 * things that actually mattered on a real diagnostic pass: nine of the site's
 * own URLs competing for one commercial query, a page at position 2.4 earning
 * zero clicks because an AI Overview ate it, another page at position 1.0 on
 * synthetic queries because an AI was CITING it, thousands of impressions in a
 * pool no content can reach, and seven posts with zero clicks in nine months.
 * Every one of those had to be derived by hand in SQL.
 *
 * The hardest distinction here is absorbed vs cited. Both look identical on
 * every existing screen (high impressions, no clicks) and they mean opposite
 * things: one is demand being taken from you, the other is you becoming the
 * source an AI quotes. Treating them the same way leads to deleting your best
 * asset, so this tool separates them explicitly.
 *
 * Reads the local full-fidelity warehouse only. No REST round trip.
 *
 * @package CC_Assistant
 */

/**
 * Group-key for a page URL.
 *
 * Search Console reports tracking variants as separate pages, so the GMB
 * ?utm_source=gmb homepage lands beside the plain homepage and each shows only
 * a fraction of the real total. For a status synthesis the question is "which
 * PAGE is working", so query strings and fragments are stripped and the
 * trailing slash normalised before aggregating.
 */
function cc_ss_page_key( $url ) {
	$u = preg_replace( '/[?#].*$/', '', (string) $url );
	$u = rtrim( (string) $u, '/' );
	return '' === $u ? (string) $url : $u;
}

/**
 * Classify a URL that should probably never have been indexed.
 *
 * v0.73.0. This is the pattern that was strangling one production site: the
 * head query "emergency treatment irving" returned 33 of its own URLs, and
 * among them were /category/emergency-care/page/2/, /blog/2/ and
 * /blog/?e-page-fcb762c=3 - all returning 200 with robots "index". Pagination
 * and archive URLs cannot satisfy a commercial query, but they are eligible
 * for it, so they take a share of the impressions and dilute the page that
 * should win.
 *
 * Two kinds, because they are not the same decision:
 *   pagination - never has unique content; deindexing is uncontroversial.
 *   archive    - a term/date listing; usually dilution for a service business,
 *                but occasionally a real landing page, so it is a judgement.
 *
 * @return string 'pagination' | 'archive' | '' (keep)
 */
function cc_ss_dilution_kind( $url ) {
	$u    = strtolower( (string) $url );
	$path = preg_replace( '/[?#].*$/', '', $u );
	$qs   = ( false !== strpos( $u, '?' ) ) ? substr( $u, strpos( $u, '?' ) ) : '';

	// Pagination: WP /page/N/, a bare numeric leaf under a listing, Elementor's
	// widget pagination param, or the classic ?paged=/?page= query.
	if ( preg_match( '#/page/\d+/?$#', $path ) ) {
		return 'pagination';
	}
	if ( preg_match( '#/(blog|news|articles|category|tag)/\d+/?$#', $path ) ) {
		return 'pagination';
	}
	if ( '' !== $qs && preg_match( '#[?&](e-page-[a-z0-9]+|paged?)=#', $qs ) ) {
		return 'pagination';
	}

	// Archives: term, author and date listings.
	if ( preg_match( '#/(category|tag|author|archives?)/#', $path ) ) {
		return 'archive';
	}
	if ( preg_match( '#/\d{4}/\d{2}/?$#', $path ) ) {
		return 'archive';
	}
	return '';
}

/** Map-pack intent: content cannot win these, GBP can. */
function cc_ss_is_near_me( $q ) {
	return (bool) preg_match( '/\bnear me\b|\bnearby\b|\bnear by\b/i', $q );
}

/**
 * AI fan-out queries are machine-generated, not typed by humans: long natural
 * sentences, or a query that names the authority the AI is cross-checking
 * against. Ranking on these means being used as a source.
 */
function cc_ss_is_fanout( $q ) {
	$q = strtolower( $q );
	if ( false !== strpos( $q, '?' ) ) {
		return true;
	}
	// Whitespace tokens, NOT str_word_count: that function ignores numerics, so
	// "american heart association hypertensive crisis 180 120 symptoms
	// emergency" counted as 7 words and fell through to "absorbed". It is
	// plainly a fan-out query, and mislabelling it inverts the advice from
	// "protect this, you are being cited" to "stop optimising this".
	if ( count( preg_split( '/\s+/', trim( $q ) ) ) >= 8 ) {
		return true;
	}
	$authorities = array(
		'cleveland clinic', 'mayo clinic', 'johns hopkins', 'harvard health',
		'medlineplus', 'webmd', 'healthline', 'niddk', 'uptodate', 'merck manual',
		'american academy', 'american heart', 'american college', 'american diabetes',
		'endocrine society', 'world health organization', 'nih ', 'cdc ', 'aad ', 'fda ',
	);
	foreach ( $authorities as $a ) {
		if ( false !== strpos( $q, $a ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Derive brand tokens from the site host without a dictionary.
 *
 * The host is usually one unspaced blob ("irvingwellnessclinic"), so we take
 * the site's own frequent query tokens and keep the ones that appear inside
 * that blob, ordered by where they appear. A query counts as branded only if
 * it contains the FIRST token plus at least one other, which keeps generic
 * pairs like "wellness clinic near me" out.
 *
 * @return array Ordered brand tokens (may be empty).
 */
function cc_ss_brand_tokens( $db, $since ) {
	$host = '';
	$wp   = getenv( 'CC_WP_URL' );
	if ( $wp ) {
		$parts = parse_url( $wp );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	}
	$host = preg_replace( '/^www\./', '', $host );
	$blob = preg_replace( '/\.[a-z.]+$/', '', $host );   // strip TLD
	$blob = preg_replace( '/[^a-z0-9]/', '', (string) $blob );
	if ( '' === $blob ) {
		return array( 'tokens' => array(), 'blob' => '' );
	}

	$counts = array();
	$stmt   = $db->prepare( 'SELECT query FROM gsc_daily WHERE date >= :s GROUP BY query LIMIT 4000' );
	$stmt->bindValue( ':s', $since, SQLITE3_TEXT );
	$res = $stmt->execute();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		foreach ( preg_split( '/[^a-z0-9]+/', strtolower( $r['query'] ) ) as $tok ) {
			// >=5 chars: a 4-char token like "well" is a substring of "wellness"
			// and would make "irving get well soon" read as branded.
			if ( strlen( $tok ) < 5 ) {
				continue;
			}
			if ( false !== strpos( $blob, $tok ) ) {
				$counts[ $tok ] = isset( $counts[ $tok ] ) ? $counts[ $tok ] + 1 : 1;
			}
		}
	}
	if ( empty( $counts ) ) {
		return array( 'tokens' => array(), 'blob' => $blob );
	}
	// Drop one-off misspellings that happen to be substrings of the host
	// ("wellnes"). A real brand token recurs across the corpus.
	$counts = array_filter( $counts, function ( $n ) { return $n >= 3; } );
	if ( empty( $counts ) ) {
		return array( 'tokens' => array(), 'blob' => $blob );
	}
	arsort( $counts );
	$tokens = array_slice( array_keys( $counts ), 0, 4 );
	usort(
		$tokens,
		function ( $a, $b ) use ( $blob ) {
			return strpos( $blob, $a ) <=> strpos( $blob, $b );
		}
	);
	return array( 'tokens' => $tokens, 'blob' => $blob );
}

/**
 * Is this a branded query?
 *
 * Two independent routes, because token matching alone fails on abbreviation
 * hosts. "erofirving" is ER of Irving, and a searcher types "er of irving",
 * which tokenises to er/of/irving. Only "irving" survives the 5-char floor, so
 * token matching sees one token and (correctly) refuses to guess. Collapsing
 * the query to alphanumerics and comparing it against the host blob recovers
 * exactly that case: "er of irving" -> "erofirving".
 */
function cc_ss_is_branded( $q, $brand ) {
	$tokens = isset( $brand['tokens'] ) ? $brand['tokens'] : array();
	$blob   = isset( $brand['blob'] ) ? $brand['blob'] : '';
	$q      = strtolower( $q );

	// Route A: the de-spaced query contains the host blob.
	if ( strlen( $blob ) >= 6 ) {
		$flat = preg_replace( '/[^a-z0-9]/', '', $q );
		if ( '' !== $flat && false !== strpos( $flat, $blob ) ) {
			return true;
		}
	}

	// Route B: lead brand token plus at least one more.
	if ( count( $tokens ) < 2 ) {
		return false;
	}
	if ( false === strpos( $q, $tokens[0] ) ) {
		return false;
	}
	foreach ( array_slice( $tokens, 1 ) as $t ) {
		if ( false !== strpos( $q, $t ) ) {
			return true;
		}
	}
	return false;
}

function cc_ss_pct( $now, $prior ) {
	if ( $prior <= 0 ) {
		return $now > 0 ? null : 0.0;   // null = "new", not an infinite gain
	}
	return round( 100.0 * ( $now - $prior ) / $prior, 1 );
}

/**
 * site_status tool entry point.
 *
 * @param array $arguments days (default 28, 7-90), max_findings (default 8).
 * @return array
 */
function cc_wh_tool_site_status( $arguments ) {
	$days = isset( $arguments['days'] ) ? max( 7, min( 90, (int) $arguments['days'] ) ) : 28;
	$maxf = isset( $arguments['max_findings'] ) ? max( 3, min( 20, (int) $arguments['max_findings'] ) ) : 8;

	$db = cc_wh_open( true );
	if ( is_array( $db ) ) {
		return $db;
	}

	$latest = $db->querySingle( 'SELECT MAX(date) FROM gsc_daily' );
	if ( ! $latest ) {
		$db->close();
		return array( 'error' => 'no_data', 'message' => 'Warehouse is empty. Run gsc_warehouse_sync first.' );
	}
	$end         = $latest;
	$start       = cc_wh_date_sub( $end, $days - 1 );
	$prior_end   = cc_wh_date_sub( $start, 1 );
	$prior_start = cc_wh_date_sub( $prior_end, $days - 1 );

	/* ------------------------------------------------------- 1. HAPPENING -- */
	$tot = array();
	foreach ( array( 'now' => array( $start, $end ), 'prior' => array( $prior_start, $prior_end ) ) as $k => $w ) {
		$stmt = $db->prepare(
			'SELECT SUM(clicks) ck, SUM(impressions) imp,
			        SUM(position*impressions) posw
			 FROM gsc_daily WHERE date BETWEEN :a AND :b'
		);
		$stmt->bindValue( ':a', $w[0], SQLITE3_TEXT );
		$stmt->bindValue( ':b', $w[1], SQLITE3_TEXT );
		$r         = $stmt->execute()->fetchArray( SQLITE3_ASSOC );
		$imp       = (int) $r['imp'];
		$tot[ $k ] = array(
			'clicks'      => (int) $r['ck'],
			'impressions' => $imp,
			'position'    => $imp > 0 ? round( $r['posw'] / $imp, 1 ) : null,
		);
	}

	$brand = cc_ss_brand_tokens( $db, $prior_start );

	// One pass over the window's queries classifies branded / fan-out / near-me.
	$seg = array(
		'branded'  => array( 'now' => 0, 'prior' => 0 ),
		'nonbrand' => array( 'now' => 0, 'prior' => 0 ),
		'fanout'   => array( 'now' => 0, 'prior' => 0, 'queries' => 0 ),
		'near_me'  => array( 'imp' => 0, 'clicks' => 0, 'queries' => 0 ),
	);
	$stmt = $db->prepare(
		'SELECT query,
		        SUM(CASE WHEN date >= :s THEN clicks ELSE 0 END) ck_now,
		        SUM(CASE WHEN date <  :s THEN clicks ELSE 0 END) ck_prior,
		        SUM(CASE WHEN date >= :s THEN impressions ELSE 0 END) imp_now,
		        SUM(CASE WHEN date <  :s THEN impressions ELSE 0 END) imp_prior
		 FROM gsc_daily WHERE date BETWEEN :p AND :e GROUP BY query'
	);
	$stmt->bindValue( ':s', $start, SQLITE3_TEXT );
	$stmt->bindValue( ':p', $prior_start, SQLITE3_TEXT );
	$stmt->bindValue( ':e', $end, SQLITE3_TEXT );
	$res = $stmt->execute();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$q = (string) $r['query'];
		if ( cc_ss_is_branded( $q, $brand ) ) {
			$seg['branded']['now']   += (int) $r['ck_now'];
			$seg['branded']['prior'] += (int) $r['ck_prior'];
		} else {
			$seg['nonbrand']['now']   += (int) $r['ck_now'];
			$seg['nonbrand']['prior'] += (int) $r['ck_prior'];
		}
		if ( cc_ss_is_fanout( $q ) ) {
			$seg['fanout']['now']   += (int) $r['imp_now'];
			$seg['fanout']['prior'] += (int) $r['imp_prior'];
			if ( (int) $r['imp_now'] > 0 ) {
				$seg['fanout']['queries']++;
			}
		}
		if ( cc_ss_is_near_me( $q ) && (int) $r['imp_now'] > 0 ) {
			$seg['near_me']['imp']    += (int) $r['imp_now'];
			$seg['near_me']['clicks'] += (int) $r['ck_now'];
			$seg['near_me']['queries']++;
		}
	}

	$happening = array(
		'clicks'      => array( 'now' => $tot['now']['clicks'], 'prior' => $tot['prior']['clicks'], 'delta_pct' => cc_ss_pct( $tot['now']['clicks'], $tot['prior']['clicks'] ) ),
		'impressions' => array( 'now' => $tot['now']['impressions'], 'prior' => $tot['prior']['impressions'], 'delta_pct' => cc_ss_pct( $tot['now']['impressions'], $tot['prior']['impressions'] ) ),
		'position'    => array( 'now' => $tot['now']['position'], 'prior' => $tot['prior']['position'] ),
		'branded'     => array(
			'clicks_now'   => $seg['branded']['now'],
			'clicks_prior' => $seg['branded']['prior'],
			'share_pct'    => $tot['now']['clicks'] > 0 ? round( 100.0 * $seg['branded']['now'] / $tot['now']['clicks'], 1 ) : 0,
			'tokens'       => $brand['tokens'],
			'host_match'   => $brand['blob'],
			'note'         => 'Branded clicks measure awareness, not ranking. Rising branded share with flat non-branded means the brand is growing while organic reach is not.',
		),
		'non_branded' => array( 'clicks_now' => $seg['nonbrand']['now'], 'clicks_prior' => $seg['nonbrand']['prior'] ),
		'ai_fanout'   => array(
			'impressions_now'   => $seg['fanout']['now'],
			'impressions_prior' => $seg['fanout']['prior'],
			'queries_now'       => $seg['fanout']['queries'],
			'note'              => 'Lexical query-pattern candidates only. Long questions or authority names do not verify machine generation or AI citation.',
		),
	);

	/* --------------------------------------------------------- 2. WORKING -- */
	$by_page = array();
	$stmt    = $db->prepare(
		'SELECT page,
		        SUM(CASE WHEN date >= :s THEN clicks ELSE 0 END) ck_now,
		        SUM(CASE WHEN date <  :s THEN clicks ELSE 0 END) ck_prior,
		        SUM(CASE WHEN date >= :s THEN impressions ELSE 0 END) imp_now
		 FROM gsc_daily WHERE date BETWEEN :p AND :e GROUP BY page'
	);
	$stmt->bindValue( ':s', $start, SQLITE3_TEXT );
	$stmt->bindValue( ':p', $prior_start, SQLITE3_TEXT );
	$stmt->bindValue( ':e', $end, SQLITE3_TEXT );
	$res = $stmt->execute();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$k = cc_ss_page_key( $r['page'] );
		if ( ! isset( $by_page[ $k ] ) ) {
			$by_page[ $k ] = array( 'page' => $k, 'clicks_now' => 0, 'clicks_prior' => 0, 'impressions' => 0, 'variants' => 0 );
		}
		$by_page[ $k ]['clicks_now']   += (int) $r['ck_now'];
		$by_page[ $k ]['clicks_prior'] += (int) $r['ck_prior'];
		$by_page[ $k ]['impressions']  += (int) $r['imp_now'];
		$by_page[ $k ]['variants']++;
	}
	$working = array();
	foreach ( $by_page as $row ) {
		if ( $row['clicks_now'] <= $row['clicks_prior'] ) {
			continue;
		}
		$row['gain'] = $row['clicks_now'] - $row['clicks_prior'];
		if ( $row['variants'] < 2 ) {
			unset( $row['variants'] );
		}
		$working[] = $row;
	}
	usort( $working, function ( $a, $b ) { return $b['gain'] <=> $a['gain']; } );
	$working = array_slice( $working, 0, 8 );

	$decay_src = $by_page;

	/* --------------------------------------------------------- 3. LACKING -- */
	$lacking = array();

	// (a) Self-competition: one query, many of the site's own URLs.
	$rows = array();
	$stmt = $db->prepare(
		'SELECT query, COUNT(DISTINCT page) pages, SUM(impressions) imp, SUM(clicks) ck
		 FROM gsc_daily WHERE date BETWEEN :a AND :b
		 GROUP BY query HAVING pages >= 3 ORDER BY imp DESC LIMIT 10'
	);
	$stmt->bindValue( ':a', $start, SQLITE3_TEXT );
	$stmt->bindValue( ':b', $end, SQLITE3_TEXT );
	$res = $stmt->execute();
	$self_imp = 0;
	$contested = array();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$rows[]    = array( 'query' => $r['query'], 'own_pages' => (int) $r['pages'], 'impressions' => (int) $r['imp'], 'clicks' => (int) $r['ck'] );
		$contested[] = (string) $r['query'];
		$self_imp += (int) $r['imp'];
	}
	// v0.73.0: naming the problem without naming the answer left the operator to
	// re-derive it by hand every time. For each contested query, report the page
	// Google already prefers (best average position) so there is a default owner
	// to consolidate onto, plus how many of the rivals are pure dilution
	// (zero-click) versus real contenders.
	foreach ( $rows as $i => $row ) {
		$stmt2 = $db->prepare(
			'SELECT page, SUM(impressions) imp, SUM(clicks) ck,
			        SUM(position*impressions)/SUM(impressions) pos
			 FROM gsc_daily WHERE date BETWEEN :a AND :b AND query = :q
			 GROUP BY page ORDER BY pos ASC'
		);
		$stmt2->bindValue( ':a', $start, SQLITE3_TEXT );
		$stmt2->bindValue( ':b', $end, SQLITE3_TEXT );
		$stmt2->bindValue( ':q', $row['query'], SQLITE3_TEXT );
		$r2       = $stmt2->execute();
		$best     = null;
		$zeroclick = 0;
		$total    = 0;
		while ( $r2 && ( $x = $r2->fetchArray( SQLITE3_ASSOC ) ) ) {
			$total++;
			if ( (int) $x['ck'] === 0 ) {
				$zeroclick++;
			}
			if ( null === $best ) {
				$best = array(
					'page'     => cc_ss_page_key( $x['page'] ),
					'position' => round( (float) $x['pos'], 1 ),
					'clicks'   => (int) $x['ck'],
				);
			}
		}
		$rows[ $i ]['google_prefers']    = $best;
		$rows[ $i ]['zero_click_rivals'] = $zeroclick;
	}
	if ( ! empty( $rows ) ) {
		$worst = $rows[0];
		$lacking[] = array(
			'issue'    => 'self_competition',
			'severity' => $worst['own_pages'] >= 5 ? 'high' : 'medium',
			'headline' => sprintf( '%d of your own URLs were observed for "%s"', $worst['own_pages'], $worst['query'] ),
			'detail'   => 'Several URLs were observed for a query during this period. This does not establish simultaneous competition, ranking harm or a preferred reader task.',
			'action'   => 'Use content_decision to compare reader tasks and useful coverage. google_prefers is the best observed average position, not verified Google preference. Zero-click URLs are candidates to inspect, never automatic merge or noindex targets.',
			'evidence' => $rows,
		);
	}

	// (b) Absorbed vs (c) cited. Same symptom, opposite meaning.
	$absorbed = array();
	$cited    = array();
	$stmt = $db->prepare(
		'SELECT page, query, SUM(impressions) imp, SUM(clicks) ck,
		        SUM(position*impressions)/SUM(impressions) pos
		 FROM gsc_daily WHERE date BETWEEN :a AND :b
		 GROUP BY page, query HAVING imp >= 50 ORDER BY imp DESC LIMIT 400'
	);
	$stmt->bindValue( ':a', $start, SQLITE3_TEXT );
	$stmt->bindValue( ':b', $end, SQLITE3_TEXT );
	$res = $stmt->execute();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$imp = (int) $r['imp'];
		$pos = (float) $r['pos'];
		$ctr = $imp > 0 ? (int) $r['ck'] / $imp : 0.0;
		if ( $pos > 20 || $ctr >= cc_expected_ctr( $pos ) * 0.25 ) {
			continue;
		}
		$row = array( 'query' => $r['query'], 'page' => $r['page'], 'impressions' => $imp, 'clicks' => (int) $r['ck'], 'position' => round( $pos, 1 ) );
		if ( cc_ss_is_fanout( (string) $r['query'] ) ) {
			$cited[] = $row;
		} else {
			$absorbed[] = $row;
		}
	}
	if ( ! empty( $absorbed ) ) {
		$imp_sum = array_sum( array_column( $absorbed, 'impressions' ) );
		$lacking[] = array(
			'issue'    => 'low_ctr_queries',
			'severity' => $imp_sum >= 2000 ? 'high' : 'medium',
			'headline' => sprintf( '%s impressions rank well but earn almost nothing', number_format( $imp_sum ) ),
			'detail'   => 'Observed CTR is below a heuristic curve. Query mix, demand, result presentation and technical factors need investigation; AI causation is not established.',
			'action'   => 'Diagnose the low CTR before choosing a content or metadata change. Do not prescribe a rewrite or stop investment from this metric alone.',
			'evidence' => array_slice( $absorbed, 0, 6 ),
		);
	}

	// (d) Unreachable: map-pack demand.
	if ( $seg['near_me']['imp'] >= 300 ) {
		$lacking[] = array(
			'issue'    => 'local_query_review',
			'severity' => $seg['near_me']['imp'] >= 3000 ? 'high' : 'medium',
			'headline' => sprintf( '%s impressions involve near-me query wording', number_format( $seg['near_me']['imp'] ) ),
			'detail'   => sprintf( '%d near-me queries produced %d clicks. Query wording does not identify the search surface or establish that content cannot help.', $seg['near_me']['queries'], $seg['near_me']['clicks'] ),
			'action'   => 'Inspect actual search results, business profile information and reader needs. Relevant niche content remains eligible; query wording alone is not a reason to reject a new blog.',
			'evidence' => array( 'impressions' => $seg['near_me']['imp'], 'clicks' => $seg['near_me']['clicks'], 'queries' => $seg['near_me']['queries'] ),
		);
	}

	// (d2) Indexable dilution: pagination and archive URLs drawing impressions.
	// Queried on RAW page values deliberately: cc_ss_page_key() strips the query
	// string, which would fold ?e-page-... straight into its base page and hide
	// exactly the URLs this check exists to find.
	$dilution = array( 'pagination' => array(), 'archive' => array() );
	$dil_imp  = 0;
	$stmt     = $db->prepare(
		'SELECT page, SUM(impressions) imp, SUM(clicks) ck
		 FROM gsc_daily WHERE date BETWEEN :a AND :b
		 GROUP BY page ORDER BY imp DESC'
	);
	$stmt->bindValue( ':a', $start, SQLITE3_TEXT );
	$stmt->bindValue( ':b', $end, SQLITE3_TEXT );
	$res = $stmt->execute();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$kind = cc_ss_dilution_kind( $r['page'] );
		if ( '' === $kind ) {
			continue;
		}
		$dilution[ $kind ][] = array(
			'page'        => $r['page'],
			'impressions' => (int) $r['imp'],
			'clicks'      => (int) $r['ck'],
		);
		$dil_imp += (int) $r['imp'];
	}
	$dil_count = count( $dilution['pagination'] ) + count( $dilution['archive'] );
	if ( $dil_count > 0 && $dil_imp >= 100 ) {
		$dil_clicks = 0;
		foreach ( array( 'pagination', 'archive' ) as $kk ) {
			foreach ( $dilution[ $kk ] as $row ) {
				$dil_clicks += $row['clicks'];
			}
		}
		$lacking[] = array(
			'issue'    => 'indexable_dilution',
			'severity' => count( $dilution['pagination'] ) >= 3 || $dil_imp >= 2000 ? 'high' : 'medium',
			'headline' => sprintf(
				'%d pagination/archive URLs are indexed and drawing %s impressions',
				$dil_count,
				number_format( $dil_imp )
			),
			'detail'   => sprintf(
				'These URL-pattern candidates earned %d clicks. Their content, purpose, indexing state and business value have not been inspected; the pattern does not establish ranking dilution.',
				$dil_clicks
			),
			'action'   => 'Inspect actual archive/pagination content and discovery dependencies. Do not noindex or retire pages from URL shape or click counts alone; use content_decision.',
			'evidence' => array(
				'pagination' => array_slice( $dilution['pagination'], 0, 8 ),
				'archive'    => array_slice( $dilution['archive'], 0, 8 ),
			),
		);
	}

	// (e) Dead inventory: indexed, seen, never clicked.
	$dead_agg = array();
	$stmt = $db->prepare( 'SELECT page, SUM(impressions) imp, SUM(clicks) ck, MAX(date) last_seen FROM gsc_daily GROUP BY page' );
	$res  = $stmt->execute();
	while ( $res && ( $r = $res->fetchArray( SQLITE3_ASSOC ) ) ) {
		$k = cc_ss_page_key( $r['page'] );
		if ( ! isset( $dead_agg[ $k ] ) ) {
			$dead_agg[ $k ] = array( 'page' => $k, 'imp' => 0, 'ck' => 0, 'last_seen' => '' );
		}
		$dead_agg[ $k ]['imp'] += (int) $r['imp'];
		$dead_agg[ $k ]['ck']  += (int) $r['ck'];
		if ( $r['last_seen'] > $dead_agg[ $k ]['last_seen'] ) {
			$dead_agg[ $k ]['last_seen'] = $r['last_seen'];
		}
	}
	$dead = array();
	foreach ( $dead_agg as $row ) {
		// Canonical grouping matters here: a page whose tracking variant earned
		// a click is NOT dead, and grouping first prevents calling it dead.
		if ( $row['ck'] > 0 || $row['imp'] < 25 ) {
			continue;
		}
		$dead[] = array( 'page' => $row['page'], 'impressions_lifetime' => $row['imp'], 'clicks_lifetime' => 0, 'last_seen' => $row['last_seen'] );
	}
	usort( $dead, function ( $a, $b ) { return $b['impressions_lifetime'] <=> $a['impressions_lifetime']; } );
	$dead = array_slice( $dead, 0, 15 );
	if ( count( $dead ) >= 3 ) {
		$lacking[] = array(
			'issue'    => 'dead_inventory',
			'severity' => count( $dead ) >= 7 ? 'high' : 'low',
			'headline' => sprintf( '%d pages have never earned a single click', count( $dead ) ),
			'detail'   => 'Each has been shown in search and never clicked across the whole warehouse history. They still stay eligible for the queries your money pages want, and thin pages drag host-level quality under the 2026 site-wide reweighting.',
			'action'   => 'Do NOT noindex (operator rule: a page shown at position 8-15 has simply not been clicked yet, and deindexing turns a maybe into a certain zero). Improve the title and snippet, add inbound links from the pillar, or merge the page into the one that already wins the topic while keeping the URL indexable. Check first whether any covers a service you actually sell.',
			'evidence' => $dead,
		);
	}

	// (f) Decay: pages losing clicks fastest.
	$decay   = array();
	$retired = 0;
	foreach ( $decay_src as $row ) {
		if ( $row['clicks_prior'] < 3 || $row['clicks_now'] >= $row['clicks_prior'] ) {
			continue;
		}
		// A page with NO impressions left has not decayed, it has been removed
		// (410/redirect/deleted). Reporting a deliberate retirement as "losing
		// ground" sends the operator to re-investigate a decision they already
		// made on purpose. Counted separately instead.
		if ( $row['impressions'] <= 0 ) {
			$retired++;
			continue;
		}
		$decay[] = array(
			'page'         => $row['page'],
			'clicks_now'   => $row['clicks_now'],
			'clicks_prior' => $row['clicks_prior'],
			'lost'         => $row['clicks_prior'] - $row['clicks_now'],
		);
	}
	usort( $decay, function ( $a, $b ) { return $b['lost'] <=> $a['lost']; } );
	$decay = array_slice( $decay, 0, 6 );
	if ( ! empty( $decay ) ) {
		$detail = 'A real decline on a page that was already earning, and still drawing impressions, so the demand is there and the page is losing it.';
		if ( $retired > 0 ) {
			$detail .= sprintf( ' %d further page(s) dropped to zero impressions and are excluded here: that is removal, not decay, and is usually a retirement you carried out on purpose.', $retired );
		}
		$lacking[] = array(
			'issue'            => 'losing_ground',
			'severity'         => 'medium',
			'headline'         => sprintf( '%d pages lost clicks versus the previous %d days', count( $decay ), $days ),
			'detail'           => $detail,
			'action'           => 'Check whether an edit landed just before the drop (outcome_report), then whether the query set shifted rather than the ranking.',
			'excluded_removed' => $retired,
			'evidence'         => $decay,
		);
	}

	$db->close();

	// Rank by severity so the caller reads the expensive problems first.
	$rank = array( 'high' => 0, 'medium' => 1, 'low' => 2 );
	usort(
		$lacking,
		function ( $a, $b ) use ( $rank ) {
			return $rank[ $a['severity'] ] <=> $rank[ $b['severity'] ];
		}
	);
	$lacking = array_slice( $lacking, 0, $maxf );

	// Headline: state the honest shape of the period in one line.
	$cd = $happening['clicks']['delta_pct'];
	$id = $happening['impressions']['delta_pct'];
	if ( null !== $cd && null !== $id && $id > 20 && $cd < 5 ) {
		$headline = 'Visibility increased faster than clicks. Investigate the metric change; useful new niche topics remain eligible through plan_blog_content.';
	} elseif ( null !== $cd && $cd < -10 ) {
		$headline = 'Clicks are down on the previous period. Work the losing_ground finding first.';
	} elseif ( null !== $cd && $cd > 10 ) {
		$headline = 'Clicks are up on the previous period. Confirm which pages drove it before repeating the tactic.';
	} else {
		$headline = 'Broadly flat period. The findings below are hypotheses to investigate.';
	}
	if ( $happening['branded']['share_pct'] >= 50 ) {
		$headline .= sprintf( ' Note %s%% of clicks are branded, so organic reach is narrower than the headline number suggests.', $happening['branded']['share_pct'] );
	}

	return array(
		'window'    => array( 'days' => $days, 'start' => $start, 'end' => $end, 'prior_start' => $prior_start, 'prior_end' => $prior_end ),
		'headline'  => $headline,
		'assessment_type' => 'observations_and_hypotheses_not_causal_findings',
		'new_content_policy' => 'GSC history is not a prerequisite. Use plan_blog_content for supported niche expansion.',
		'happening' => $happening,
		'working'   => $working,
		'lacking'   => $lacking,
		'caveats'   => array(
			'Warehouse data only. Conversions and calls are not visible here, so a page with few clicks may still convert well.',
			'Branded detection is derived from the site host, not a curated list. Check happening.branded.tokens if the split looks wrong.',
			'Fan-out detection is heuristic (long sentences, or a named authority). It identifies the pattern, not a Google-confirmed label.',
		),
	);
}

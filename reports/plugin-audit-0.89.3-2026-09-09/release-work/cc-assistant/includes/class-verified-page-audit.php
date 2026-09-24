<?php
/** Repeatable assessments of captured server HTML, with explicit evidence limits. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CC_Assistant_Verified_Page_Audit {
	const RULES_VERSION = '1.0.0';

	public static function run( $post_id ) {
		require_once CC_ASSISTANT_DIR . 'includes/class-page-facts.php';
		// Never substitute yesterday's successful result after today's failed fetch.
		$record = CC_Assistant_Page_Facts::capture( (int) $post_id );
		if ( is_wp_error( $record ) ) {
			$record = array( 'post_id' => (int) $post_id, 'captured_at' => gmdate( 'Y-m-d H:i:s' ), 'stale' => true, 'error' => $record->get_error_code(), 'message' => $record->get_error_message() );
		}
		$report = self::evaluate( $record );
		$key = 'cc_assistant_audit_' . (int) $post_id;
		$previous = get_option( $key, null );
		$report['comparison'] = self::compare( $previous, $report );
		// Only the latest bounded report per page; no change to post metadata/hashes.
		$snapshot = $report;
		unset( $snapshot['comparison'] );
		$saved = update_option( $key, $snapshot, false );
		$report['history_saved'] = $saved || get_option( $key ) === $snapshot;
		$report['reporting_instruction'] = 'Report rule ID, status, observed evidence and capture time. Describe changes using comparison. Unknown is not pass or fail; review needs human context. Do not claim all SEO, Google indexing, visual design or plugin behaviour is correct.';
		return $report;
	}

	/** Same facts and collection conditions produce the same rule findings. */
	public static function evaluate( $record ) {
		$f = $record['facts'] ?? array();
		$cache = $record['cache_state'] ?? 'unknown';
		$usable = empty( $record['error'] ) && empty( $record['stale'] ) && ! empty( $record['refreshed'] ) && 200 === (int) ( $record['http_code'] ?? 0 ) && ! empty( $f ) && 'hit' !== $cache;
		$source = array(
			'post_id' => (int) ( $record['post_id'] ?? 0 ), 'url' => $f['url'] ?? null,
			'captured_at_utc' => $record['captured_at'] ?? null, 'body_sha1' => $record['body_sha1'] ?? null,
			'http_code' => $record['http_code'] ?? null, 'cache_state' => $cache,
			'method' => 'server_html', 'javascript_executed' => false,
			'cache_freshness_proven' => 'miss' === $cache,
			'error' => $record['error'] ?? null, 'message' => $record['message'] ?? null,
		);
		$checks = array();
		$add = static function ( $id, $status, $evidence, $meaning ) use ( &$checks, $usable ) {
			$checks[] = array( 'rule_id' => $id, 'status' => $usable ? $status : 'unknown', 'evidence' => $usable ? $evidence : null, 'meaning' => $usable ? $meaning : 'Fresh, usable page evidence was not obtained. See source; do not reuse a previous verdict.' );
		};
		$m = $f['meta'] ?? array();
		foreach ( array( 'title', 'description' ) as $key ) {
			$value = trim( (string) ( $m[$key] ?? '' ) );
			$count = $m['counts'][$key] ?? null;
			$add( 'seo.' . $key, null === $count ? 'unknown' : ( 1 === $count && '' !== $value ? 'pass' : 'review' ), array( 'count' => $count, 'value' => mb_substr( $value, 0, 500 ) ), 'Checks presence and uniqueness only. Wording, search intent and snippet choice require separate review; no rigid character limit.' );
		}
		$canonical = (string) ( $m['canonical'] ?? '' );
		$count = $m['counts']['canonical'] ?? null;
		$absolute = (bool) preg_match( '~^https?://[^/\s]+(?:/|$)~i', $canonical );
		$add( 'seo.canonical', null === $count ? 'unknown' : ( 1 === $count && $absolute && $canonical === ( $f['url'] ?? '' ) ? 'pass' : 'review' ), array( 'count' => $count, 'href' => mb_substr( $canonical, 0, 500 ), 'requested_url' => $f['url'] ?? null ), 'Self-referencing absolute canonical observed when pass. A different, missing or duplicate canonical needs intent review. Google selection and destination status are untested.' );
		$directives = array( 'robots_meta' => $m['robots'] ?? '', 'googlebot_meta' => $m['googlebot'] ?? '', 'x_robots_tag' => $m['x_robots_tag'] ?? '' );
		$restricted = preg_match( '/\b(noindex|none)\b/i', implode( ', ', $directives ) );
		$add( 'seo.index_directives', $restricted ? 'review' : 'pass', $directives, 'Checks observed noindex/none directives, including HTTP headers. A restriction may be intentional or user-agent scoped. No restriction does not prove indexability; robots.txt and Google indexing are separate.' );
		$h1 = $f['headings']['h1_count'] ?? null;
		$add( 'content.h1', null === $h1 ? 'unknown' : ( 1 === $h1 ? 'pass' : 'review' ), array( 'count' => $h1, 'outline' => $f['headings']['outline'] ?? array() ), 'Heading structure observation. Multiple H1s are not an automatic SEO failure. Visual hierarchy and hidden headings need browser review.' );
		$blocks = $f['schema']['blocks'] ?? array();
		$invalid = count( array_filter( $blocks, static function ( $b ) { return empty( $b['valid'] ); } ) );
		$add( 'schema.json_syntax', empty( $blocks ) ? 'not_applicable' : ( $invalid ? 'fail' : 'pass' ), array( 'block_count' => count( $blocks ), 'invalid_blocks' => $invalid, 'types' => array_values( array_unique( array_merge( array(), ...array_map( static function ( $b ) { return (array) ( $b['types'] ?? array() ); }, $blocks ) ) ) ) ), 'Valid JSON syntax only. This does not validate Google eligibility, factual accuracy or rich-result appearance. No JSON-LD is not automatically a defect.' );
		$add( 'schema.local_lint', empty( $f['schema']['issues'] ) ? 'pass' : 'review', $f['schema']['issues'] ?? array(), 'Local heuristic lint; potential issues need review against visible content and the applicable schema documentation.' );
		$images = $f['images'] ?? array();
		$absent = $images['absent_alt_attribute'] ?? null;
		$empty = $images['empty_alt_attribute'] ?? null;
		$add( 'accessibility.image_alt', null === $absent ? 'unknown' : ( $absent || $empty ? 'review' : 'pass' ), array( 'total' => $images['total'] ?? null, 'absent_attribute' => $absent, 'empty_attribute' => $empty ), 'Empty alt may correctly mark decoration. Missing attributes and meaningful images need context review. Text quality and CSS background images are not assessed.' );
		$add( 'links.inventory', 'pass', array( 'total' => $f['links']['total'] ?? 0, 'internal' => $f['links']['internal'] ?? 0, 'content_internal' => $f['links']['content_internal'] ?? 0, 'listed' => count( $f['links']['items'] ?? array() ), 'truncated' => ( $f['links']['total'] ?? 0 ) > count( $f['links']['items'] ?? array() ) ), 'Inventory collected; pass means the count was obtained, not that links are healthy or sufficient. Destinations and topic relevance were not checked.' );
		foreach ( array(
			'google.indexing' => 'Requires Google Search Console URL Inspection evidence with its own observation time.',
			'performance.field_cwv' => 'Requires field measurements; no Core Web Vitals conclusion from this HTML.',
			'browser.appearance' => 'Requires browser rendering at relevant viewports, JavaScript and interaction checks.',
			'content.accuracy' => 'Requires subject-matter and search-intent review; word count is not a quality test.',
			'crawl.robots_and_sitemap' => 'Requires separate robots.txt, sitemap and crawl checks.',
			'links.destinations' => 'Destination URLs were not fetched; no broken-link verdict is available.',
		) as $id => $reason ) {
			$checks[] = array( 'rule_id' => $id, 'status' => 'unknown', 'evidence' => null, 'meaning' => $reason );
		}
		$counts = array_fill_keys( array( 'pass', 'fail', 'review', 'unknown', 'not_applicable' ), 0 );
		foreach ( $checks as $check ) { $counts[$check['status']]++; }
		return array( 'rules_version' => self::RULES_VERSION, 'usable' => $usable, 'assessment' => $usable ? 'partial' : 'unverified', 'source' => $source, 'findings' => $checks, 'counts' => $counts, 'findings_sha256' => hash( 'sha256', wp_json_encode( $checks ) ), 'facts_sha256' => hash( 'sha256', wp_json_encode( $f ) ) );
	}

	public static function compare( $previous, $current ) {
		if ( ! is_array( $previous ) ) { return array( 'status' => 'no_previous_audit', 'changes' => array() ); }
		$old = array_column( $previous['findings'] ?? array(), null, 'rule_id' );
		$new = array_column( $current['findings'] ?? array(), null, 'rule_id' );
        $changes = array();
        foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) ) as $id ) {
            $before = $old[$id] ?? null; $after = $new[$id] ?? null;
            if ( $before !== $after ) {
                $changes[] = array( 'rule_id' => $id, 'before' => $before, 'after' => $after,
                    'change_type' => null === $before ? 'added' : ( null === $after ? 'removed' : 'changed' ) );
            }
        }
		return array( 'status' => empty( $changes ) ? 'unchanged_findings' : 'changed_findings', 'previous_captured_at_utc' => $previous['source']['captured_at_utc'] ?? null, 'rules_changed' => ( $previous['rules_version'] ?? '' ) !== $current['rules_version'], 'body_changed' => ( $previous['source']['body_sha1'] ?? null ) !== ( $current['source']['body_sha1'] ?? null ), 'collection_changed' => ( $previous['source']['cache_state'] ?? null ) !== ( $current['source']['cache_state'] ?? null ) || ( $previous['source']['error'] ?? null ) !== ( $current['source']['error'] ?? null ), 'changes' => $changes );
	}
}

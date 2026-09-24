<?php
/**
 * LLM crawler tracking.
 *
 * Detects requests from known AI bots (GPTBot, ClaudeBot, Perplexity, etc.) and
 * logs them to wp_cc_llm_crawls. Off by default — enabled only when the user
 * opts in via Settings, per the plugin's "no front-end DB queries unless the
 * user enabled the feature" rule.
 *
 * The detection itself is a single regex on the user-agent header. Logging is
 * one INSERT per matched request, which only happens for actual AI-bot hits
 * (a tiny fraction of real traffic).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_LLM_Tracker {

	const OPT_ENABLED = 'cc_assistant_llm_tracking_enabled';
	const RETENTION_DAYS = 90;

	/**
	 * Bot signatures. Key is the canonical bot name, value is a regex that
	 * matches the user-agent. Source: each operator's documented UA string,
	 * cross-referenced with Cloudflare radar / Dark Visitors as of 2026.
	 */
	public static function signatures() {
		return array(
			'GPTBot'           => '/GPTBot\b/i',
			'OAI-SearchBot'    => '/OAI-SearchBot\b/i',
			'ChatGPT-User'     => '/ChatGPT-User\b/i',
			'ClaudeBot'        => '/ClaudeBot\b/i',
			'Claude-Web'       => '/Claude-Web\b/i',
			'anthropic-ai'     => '/anthropic-ai\b/i',
			'PerplexityBot'    => '/PerplexityBot\b/i',
			'Perplexity-User'  => '/Perplexity-User\b/i',
			'Google-Extended'  => '/Google-Extended\b/i',
			'Bytespider'       => '/Bytespider\b/i',
			'CCBot'            => '/CCBot\b/i',
			'Diffbot'          => '/Diffbot\b/i',
			'FacebookBot'      => '/FacebookBot\b/i',
			'Meta-ExternalAgent' => '/Meta-ExternalAgent\b/i',
			'YouBot'           => '/YouBot\b/i',
			'Applebot-Extended' => '/Applebot-Extended\b/i',
			'Amazonbot'        => '/Amazonbot\b/i',
			'cohere-ai'        => '/cohere-ai\b/i',
			'Mistral-AI'       => '/Mistral-AI\b/i',
		);
	}

	public static function is_enabled() {
		return (bool) get_option( self::OPT_ENABLED, false );
	}

	public static function maybe_track() {
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return;
		}
		$ua = (string) $_SERVER['HTTP_USER_AGENT'];

		// Cheap pre-check: only inspect UAs that contain "bot", "ai", "claude", "perplexity", or "gpt".
		if ( ! preg_match( '/(bot|ai|claude|perplexity|gpt|spider|extended|external)/i', $ua ) ) {
			return;
		}

		$matched = null;
		foreach ( self::signatures() as $name => $pattern ) {
			if ( preg_match( $pattern, $ua ) ) {
				$matched = $name;
				break;
			}
		}
		if ( ! $matched ) {
			return;
		}

		self::record( $matched, $ua );
	}

	private static function record( $bot_name, $ua ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_llm_crawls';

		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
		$path = wp_strip_all_tags( wp_unslash( $path ) );
		$path = mb_substr( $path, 0, 500 );

		$post_id = self::resolve_post_id( $path );
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? mb_substr( wp_strip_all_tags( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), 0, 500 ) : '';

		$insert_data = array(
			'seen_at'    => current_time( 'mysql', true ),
			'bot_name'   => $bot_name,
			'user_agent' => mb_substr( $ua, 0, 500 ),
			'path'       => $path,
			'path_hash'  => sha1( $path ),
			'post_id'    => $post_id ? (int) $post_id : null,
			'referer'    => $referer,
			'ip_hash'    => $ip ? sha1( $ip . wp_salt( 'auth' ) ) : null,
		);
		$insert_format = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

		// Only include status_code when we actually have one — otherwise wpdb's
		// %d placeholder coerces null to 0, polluting the column with bogus zeros.
		$status = function_exists( 'http_response_code' ) ? http_response_code() : false;
		if ( is_int( $status ) && $status > 0 ) {
			$insert_data['status_code'] = $status;
			$insert_format[]            = '%d';
		}

		$wpdb->insert( $table, $insert_data, $insert_format );
	}

	private static function resolve_post_id( $path ) {
		// AI crawlers frequently request old URLs they learned from a stale
		// index. Those hits are real attention on the destination post, so
		// resolve through redirects rather than recording them as unattributed.
		$id = CC_Assistant_URL_Resolver::to_post_id( home_url( $path ) );
		return $id > 0 ? $id : null;
	}

	/* ---------------------------------------------------------------------
	 * Aggregations for dashboard + MCP
	 * ------------------------------------------------------------------- */

	public static function summary( $days = 7 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_llm_crawls';
		$days  = max( 1, min( 90, (int) $days ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE seen_at >= %s", $cutoff )
		);

		$by_bot = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_name, COUNT(*) AS hits FROM {$table} WHERE seen_at >= %s GROUP BY bot_name ORDER BY hits DESC",
				$cutoff
			),
			ARRAY_A
		);

		$top_paths = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT path, COUNT(*) AS hits, COUNT(DISTINCT bot_name) AS bots
				FROM {$table}
				WHERE seen_at >= %s
				GROUP BY path
				ORDER BY hits DESC
				LIMIT 25",
				$cutoff
			),
			ARRAY_A
		);

		return array(
			'days'        => $days,
			'enabled'     => self::is_enabled(),
			'total_hits'  => $total,
			'by_bot'      => is_array( $by_bot ) ? $by_bot : array(),
			'top_paths'   => is_array( $top_paths ) ? $top_paths : array(),
		);
	}

	public static function prune() {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_llm_crawls';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE seen_at < %s", $cutoff ) );
	}

	/**
	 * Per-path bot breakdown — for each top-crawled URL, which bots are
	 * hitting it and how often. Resolves the path back to a post_id so
	 * the user can jump straight to editing it.
	 *
	 * Used by the LLM dashboard view to answer "which of my pages are
	 * AI bots actually reading", which directly informs which content
	 * to invest E-E-A-T signals into.
	 */
	public static function per_path_breakdown( $days = 30, $limit = 25 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cc_llm_crawls';
		$days  = max( 1, min( 90, (int) $days ) );
		$limit = max( 1, min( 100, (int) $limit ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		// Top paths by hits.
		$paths = $wpdb->get_results( $wpdb->prepare(
			"SELECT path,
			        COUNT(*) AS hits,
			        COUNT(DISTINCT bot_name) AS bot_count,
			        MAX(seen_at) AS last_seen
			 FROM {$table}
			 WHERE seen_at >= %s
			 GROUP BY path
			 ORDER BY hits DESC
			 LIMIT %d",
			$cutoff,
			$limit
		) );

		// Per-path bot breakdown — single GROUP BY (path, bot_name) for the
		// top paths, then we organise the rows in PHP. Avoids the N+1 (one
		// query per top path) we had before.
		$bots_by_path = array();
		if ( ! empty( $paths ) ) {
			$path_list = array();
			foreach ( $paths as $p ) {
				$path_list[] = $p->path;
			}
			$placeholders = implode( ',', array_fill( 0, count( $path_list ), '%s' ) );
			$args         = array_merge( array( $cutoff ), $path_list );
			$rows         = $wpdb->get_results( $wpdb->prepare(
				"SELECT path, bot_name, COUNT(*) AS hits
				 FROM {$table}
				 WHERE seen_at >= %s AND path IN ($placeholders)
				 GROUP BY path, bot_name
				 ORDER BY path ASC, hits DESC",
				$args
			) );
			foreach ( (array) $rows as $r ) {
				$bots_by_path[ $r->path ][] = array(
					'bot_name' => $r->bot_name,
					'hits'     => (int) $r->hits,
				);
			}
		}

		// Distinct pages crawled site-wide in the window. The dashboard tile
		// used to count the rows returned above, which are capped by $limit —
		// so "pages crawled" silently maxed out at the page size and a site
		// with 400 crawled URLs reported 30.
		$distinct_paths = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT path) FROM {$table} WHERE seen_at >= %s",
			$cutoff
		) );

		$out = array();
		foreach ( (array) $paths as $p ) {
			$post_id = self::resolve_post_id( $p->path );
			$out[] = array(
				'path'      => $p->path,
				'hits'      => (int) $p->hits,
				'bot_count' => (int) $p->bot_count,
				'last_seen' => $p->last_seen,
				'post_id'   => $post_id ? (int) $post_id : null,
				'title'     => $post_id ? get_the_title( (int) $post_id ) : '',
				'edit_url'  => $post_id ? get_edit_post_link( (int) $post_id, 'raw' ) : null,
				'bots'      => isset( $bots_by_path[ $p->path ] ) ? $bots_by_path[ $p->path ] : array(),
			);
		}
		return array(
			'days'           => $days,
			'count'          => count( $out ),
			'distinct_paths' => $distinct_paths,
			'truncated'      => $distinct_paths > count( $out ),
			'limit'          => $limit,
			'paths'          => $out,
		);
	}
}

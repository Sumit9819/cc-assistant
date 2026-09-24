<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lead-event logging (v0.58): one daily counter row per (date, post, form)
 * from Elementor Pro form submissions. Nothing personal is stored — no
 * fields, no emails, no IPs, just counts — so outcome_report can talk about
 * LEADS (the money metric) instead of only clicks. Costs nothing on the
 * render path: the hook fires only on actual form submissions.
 */
class CC_Assistant_Lead_Events {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cc_lead_events';
	}

	/** Self-heal table creation, same pattern as the GSC table. */
	public static function ensure_table() {
		global $wpdb;
		$table = self::table();
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table} (
				event_date DATE NOT NULL,
				post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				form_name VARCHAR(100) NOT NULL DEFAULT '',
				count INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (event_date, post_id, form_name)
			) {$wpdb->get_charset_collate()}"
		);
	}

	/** elementor_pro/forms/new_record handler. */
	public static function record( $record ) {
		global $wpdb;

		$form_name = '';
		if ( is_object( $record ) && method_exists( $record, 'get_form_settings' ) ) {
			$form_name = sanitize_text_field( (string) $record->get_form_settings( 'form_name' ) );
		}
		$form_name = mb_substr( '' !== $form_name ? $form_name : 'form', 0, 100 );

		$post_id = 0;
		$referer = wp_get_referer();
		if ( is_string( $referer ) && '' !== $referer ) {
			$post_id = (int) url_to_postid( $referer );
			// v0.60.1: retry with the referer path rebuilt onto home_url —
			// fixes scheme/www mismatches that defeat url_to_postid.
			if ( 0 === $post_id ) {
				$ref_path = (string) wp_parse_url( $referer, PHP_URL_PATH );
				if ( '' !== $ref_path && '/' !== $ref_path ) {
					$post_id = (int) url_to_postid( home_url( $ref_path ) );
				}
			}
		}

		// v0.60.1: table creation moved to the activator (dbDelta); the
		// self-heal DDL now runs only if the INSERT fails (dropped table),
		// not on every submission.
		$table = self::table();
		$ok    = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (event_date, post_id, form_name, count) VALUES (%s, %d, %s, 1)
				 ON DUPLICATE KEY UPDATE count = count + 1",
				current_time( 'Y-m-d' ),
				$post_id,
				$form_name
			)
		);
		if ( false === $ok ) {
			self::ensure_table();
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (event_date, post_id, form_name, count) VALUES (%s, %d, %s, 1)
					 ON DUPLICATE KEY UPDATE count = count + 1",
					current_time( 'Y-m-d' ),
					$post_id,
					$form_name
				)
			);
		}
	}

	/** Daily rows for the last N days, compact for the REST layer. */
	public static function query_rows( $days = 90 ) {
		global $wpdb;
		$days  = max( 1, min( 400, (int) $days ) );
		$table = self::table();
		$since = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );

		// Table may not exist yet on a site with no submissions — that is a
		// legitimate "no leads" answer, not an error.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_date, post_id, form_name, count FROM {$table} WHERE event_date >= %s ORDER BY event_date ASC",
				$since
			),
			ARRAY_N
		);
		return is_array( $rows ) ? $rows : array();
	}
}

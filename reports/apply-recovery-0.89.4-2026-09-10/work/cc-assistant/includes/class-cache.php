<?php
/**
 * Plugin cache management — one-shot flush of every transient the plugin
 * sets, plus a manual recompute trigger for the heavy advisor cards.
 *
 * Why this exists: dashboard cards (orphans, click depth, weekly priorities)
 * read from transients with TTLs ranging from 30 minutes to 1 hour. After a
 * link graph rebuild or batch of pending-change applies, the underlying
 * data is fresh but the cards keep showing stale numbers until the TTLs
 * expire. The cache invalidation paths in apply.php / pending-changes.php
 * cover most cases automatically, but on hosts with persistent object
 * cache backends (SiteGround Memcached, Kinsta, WP Engine) a manual
 * "flush everything" escape hatch is the fastest way to verify the dash
 * matches reality.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Assistant_Cache {

	/**
	 * Wire the AJAX endpoint used by the dashboard "Refresh data" button.
	 * Called from the main plugin file's plugins_loaded hook.
	 */
	public static function init() {
		add_action( 'wp_ajax_cc_assistant_flush_cache', array( __CLASS__, 'ajax_flush' ) );
	}

	/**
	 * Delete every transient owned by this plugin, plus any ancillary
	 * advisor/recompute locks. Returns the number of cache entries cleared.
	 *
	 * Approach: query the options table for all rows with a `_transient_*`
	 * (or `_transient_timeout_*`) prefix that contains the plugin's `cc_*`
	 * naming convention, then call delete_transient on each. Going through
	 * delete_transient (rather than a raw DELETE) guarantees object-cache
	 * backends like SiteGround Memcached also drop the entries.
	 */
	public static function flush_all() {
		global $wpdb;

		$rows = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE '\\_transient\\_cc\\_%'
			    OR option_name LIKE '\\_transient\\_timeout\\_cc\\_%'"
		);

		// Strip the prefix so we can call delete_transient with the bare
		// transient name. Dedupe — a single transient produces both a
		// _transient_NAME row and a _transient_timeout_NAME row.
		$keys = array();
		foreach ( (array) $rows as $opt ) {
			$key = preg_replace( '/^_transient_(timeout_)?/', '', (string) $opt );
			if ( '' !== $key ) {
				$keys[ $key ] = true;
			}
		}

		$count = 0;
		foreach ( array_keys( $keys ) as $key ) {
			delete_transient( $key );
			$count++;
		}

		// Belt-and-suspenders: explicit invalidation of the advisor
		// priorities cache via its class so the constant remains the
		// single source of truth even if the prefix scan misses it
		// (e.g. site-transient flavor on a future multisite migration).
		require_once CC_ASSISTANT_DIR . 'includes/class-weekly-advisor.php';
		CC_Assistant_Weekly_Advisor::invalidate();

		// Manually drop the persistent object cache for the few keys
		// that bypass the transient API entirely.
		wp_cache_delete( 'cc_pending_count', 'cc-assistant' );

		// Schedule a fresh link graph rebuild so the next page render
		// reflects the post-flush state. Inline rebuild would block the
		// AJAX response for 5-10s on bigger sites; cron tick fires within
		// seconds and avoids the user-perceived wait.
		if ( ! wp_next_scheduled( 'cc_assistant_link_graph_rebuild' ) ) {
			wp_schedule_single_event( time() + 5, 'cc_assistant_link_graph_rebuild' );
		}
		// Fire the advisor recompute too — the cron handler exists already
		// (registered in cc-assistant.php on plugins_loaded). Without this,
		// the dashboard sees a cache miss on next render, schedules its
		// own recompute, and shows a "computing" placeholder for ~5 sec.
		if ( ! wp_next_scheduled( 'cc_assistant_advisor_recompute' ) ) {
			wp_schedule_single_event( time() + 5, 'cc_assistant_advisor_recompute' );
		}

		return $count;
	}

	/**
	 * Full front-end regeneration for a single Elementor post after an
	 * approved apply.
	 *
	 * Why this exists: the v0.16 flush path (CC_Assistant_Apply::
	 * flush_elementor_css_cache) clears Elementor's global file cache and
	 * fires the page-cache purges, but it never REGENERATES the applied
	 * post's own CSS, never drops Elementor's per-post element (markup)
	 * cache, and only purges SiteGround site-wide. On real engagements the
	 * front end kept serving stale markup after every approved
	 * elementor_full_import until the operator manually ran Elementor
	 * "Regenerate CSS & Data" and purged the SiteGround cache — ~6 manual
	 * round-trips in a single engagement. This method does that work
	 * programmatically, per post, immediately after the apply.
	 *
	 * Every external touchpoint is guarded by class_exists/function_exists
	 * plus try/catch so a missing or half-loaded Elementor / SG Optimizer
	 * can never fatal the apply that calls us.
	 *
	 * @param int  $post_id     Post whose Elementor output must be rebuilt.
	 * @param bool $full_import True for elementor_full_import applies. Adds
	 *                          the heavy global steps (Elementor
	 *                          files_manager->clear_cache() and a no-args
	 *                          SiteGround purge) on top of the per-post work.
	 */
	public static function regenerate_elementor_post( $post_id, $full_import = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		try {
			if ( class_exists( '\Elementor\Plugin' ) ) {
				// 1. Per-post generated CSS: drop the stale meta first so a
				// failed regen below still falls back to Elementor's lazy
				// regeneration on the next front-end render instead of
				// serving old CSS forever.
				delete_post_meta( $post_id, '_elementor_css' );

				// 2. Element (markup) cache. Elementor 3.22+ "Element Caching"
				// stores rendered widget HTML in this postmeta; a stale entry
				// keeps serving the OLD markup even after the data + CSS are
				// updated. Deleting the meta is the reliable invalidation.
				delete_post_meta( $post_id, '_elementor_element_cache' );

				// 2b. Hero-preload URL cache (class-hero-preload.php): the apply
				// may have changed the hero background; recompute lazily.
				delete_post_meta( $post_id, '_cc_hero_preload' );

				// 3. Regenerate the post's CSS file/meta eagerly so the first
				// visitor (and our own render_probe verify) sees final output.
				if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					try {
						$css = \Elementor\Core\Files\CSS\Post::create( $post_id );
						if ( $css && method_exists( $css, 'update' ) ) {
							$css->update();
						}
					} catch ( \Throwable $e ) {
						// Best-effort: meta was already deleted above, so
						// Elementor regenerates lazily on next render.
					}
				}

				// 4. Document-level cache invalidation via Elementor's own API
				// when available (covers cache layers newer Elementor versions
				// hang off the document besides the postmeta we deleted).
				try {
					$plugin = \Elementor\Plugin::instance();
					if ( $plugin && isset( $plugin->documents ) && method_exists( $plugin->documents, 'get' ) ) {
						$document = $plugin->documents->get( $post_id, false );
						if ( $document && method_exists( $document, 'delete_cache' ) ) {
							$document->delete_cache();
						}
					}
					// 5. Global file cache — heavy (regenerates global +
					// kit CSS for the whole site), so full imports only.
					if ( $full_import && $plugin && isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
						$plugin->files_manager->clear_cache();
					}
				} catch ( \Throwable $e ) {
					// Never fatal the apply over an Elementor internals change.
				}
			}

			// 6. WP object/post cache for this post so subsequent
			// get_post/get_permalink reads (including our own post-apply
			// verifier) see fresh rows even on persistent object caches.
			clean_post_cache( $post_id );

			// 7. SiteGround Optimizer purge. Per-URL first (cheap, targeted);
			// site-wide only after a full import.
			$permalink = get_permalink( $post_id );
			if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
				try {
					if ( $permalink ) {
						sg_cachepress_purge_cache( $permalink );
					}
					if ( $full_import ) {
						sg_cachepress_purge_cache();
					}
				} catch ( \Throwable $e ) {
					// SG helper missing internals — page cache purge is
					// best-effort, don't fail the apply.
				}
			}
			if ( class_exists( '\SiteGround_Optimizer\Supercacher\Supercacher' )
				&& is_callable( array( '\SiteGround_Optimizer\Supercacher\Supercacher', 'purge_cache' ) ) ) {
				try {
					\SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
				} catch ( \Throwable $e ) {
					// Same: best-effort.
				}
			}
		} catch ( \Throwable $e ) {
			// Absolute backstop: cache regeneration must never break an apply.
		}
	}

	/**
	 * AJAX handler. Capability + nonce gated. Returns JSON with the count
	 * of cache entries cleared. The dashboard JS reloads the page on
	 * success so the user sees fresh data immediately.
	 */
	public static function ajax_flush() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
		}
		check_ajax_referer( 'cc_assistant_flush_cache' );

		$count = self::flush_all();

		wp_send_json_success(
			array(
				'cleared' => (int) $count,
				'message' => sprintf(
					/* translators: %d: count of cache entries cleared */
					_n( 'Cleared %d cache entry. Reload to see fresh data.', 'Cleared %d cache entries. Reload to see fresh data.', $count, 'cc-assistant' ),
					$count
				),
			)
		);
	}
}

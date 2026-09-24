<?php
require_once __DIR__ . '/class-access.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset references — find and swap a media URL wherever it is stored.
 *
 * The problem this solves: a plugin that keeps its content outside
 * post_content is invisible to every other endpoint here. A popup plugin
 * storing its overlay image in a serialized option, a slider keeping banner
 * paths in postmeta, a theme-mod background — none of them are reachable by
 * the Elementor tools, the Divi tools, or draft_update_post_content, and the
 * plugin cannot ship a bespoke adapter for each one.
 *
 * Storage location is the wrong axis to specialise on. The URL is the same
 * string everywhere, so search for the string. These endpoints scan posts,
 * postmeta, options and termmeta for every spelling a URL takes (raw,
 * JSON-escaped, protocol-relative, root-relative, http twin), then rewrite it
 * in place with the serialized length prefixes recomputed.
 *
 * The one thing that must never happen is a half-written serialized blob:
 * that silently wipes a plugin's settings and there is no error to notice.
 * CC_Assistant_Asset_References handles that; see tests/asset-references-test.php.
 */
class CC_Assistant_REST_Assets {

	const REST_NAMESPACE = 'cc-assistant/v1';

	/** Audit page size. Attachment tables get long on product sites. */
	const AUDIT_LIMIT = 60;

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/assets/references',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_find_references' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/assets/replace',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_replace_reference' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/assets/audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_media_audit' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/assets/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_upload_media' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/assets/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_delete_media' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	/** Decoded-byte ceiling. Generous: a properly sized WebP lands near 150 KB. */
	const UPLOAD_MAX_BYTES = 12582912;

	/**
	 * Extension => expected IMAGETYPE_*, raster only.
	 *
	 * SVG is markup and can carry script, so it is never accepted. Built in a
	 * method rather than a class constant because IMAGETYPE_AVIF only exists
	 * on PHP 8.1+ and a constant expression referencing a missing constant is
	 * a fatal at class-load time, taking the whole plugin down on older hosts.
	 */
	private static function upload_allowed_types() {
		$types = array(
			'jpg'  => IMAGETYPE_JPEG,
			'jpeg' => IMAGETYPE_JPEG,
			'png'  => IMAGETYPE_PNG,
			'gif'  => IMAGETYPE_GIF,
		);
		if ( defined( 'IMAGETYPE_WEBP' ) ) {
			$types['webp'] = IMAGETYPE_WEBP;
		}
		if ( defined( 'IMAGETYPE_AVIF' ) ) {
			$types['avif'] = IMAGETYPE_AVIF;
		}
		return $types;
	}

	public static function check_permission() {
		if ( ! CC_Assistant_Access::can_use() ) {
			return new WP_Error(
				'rest_forbidden',
				'You do not have permission to use CC Assistant.',
				array( 'status' => 403 )
			);
		}
		require_once CC_ASSISTANT_DIR . 'includes/class-site-identity.php';
		CC_Assistant_Site_Identity::record_heartbeat( 'rest' );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * GET /assets/references — where does this URL live?
	 * ------------------------------------------------------------------ */

	public static function handle_find_references( WP_REST_Request $req ) {
		$url = trim( (string) $req->get_param( 'url' ) );
		if ( '' === $url ) {
			return new WP_Error( 'url_required', 'url is required (the full media URL, e.g. https://example.com/wp-content/uploads/2026/08/hero.webp).', array( 'status' => 400 ) );
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-asset-references.php';
		$limit     = (int) $req->get_param( 'limit' );
		$truncated = false;
		$locations = CC_Assistant_Asset_References::find( $url, $limit > 0 ? $limit : CC_Assistant_Asset_References::MAX_RESULTS, $truncated );

		$by_kind = array();
		$hits    = 0;
		foreach ( $locations as $loc ) {
			$by_kind[ $loc['kind'] ] = isset( $by_kind[ $loc['kind'] ] ) ? $by_kind[ $loc['kind'] ] + 1 : 1;
			$hits                   += $loc['hits'];
		}

		return self::wrap(
			array(
				'url'                => $url,
				'variants_searched'  => CC_Assistant_Asset_References::needle_variants( $url ),
				'location_count'     => count( $locations ),
				'occurrence_count'   => $hits,
				'by_kind'            => $by_kind,
				'truncated'          => $truncated,
				'locations'          => $locations,
				'hint'               => $truncated
					? 'RESULTS ARE TRUNCATED — there are more locations than the limit returned. Re-run with a higher limit before replacing anything, or a swap will look complete while leaving the old URL live in the rows not shown.'
					: ( empty( $locations )
					? 'Nothing references this URL. If you expected a hit, check the URL spelling against the live page — a URL that only appears in a CDN rewrite or a CSS file on disk will not be in the database.'
					: 'Swap it everywhere with replace_asset_reference({old_url, new_url}). Serialized rows are rewritten with their length prefixes recomputed; rows holding non-stdClass objects are refused rather than risked.' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * POST /assets/replace — queue a site-wide swap
	 * ------------------------------------------------------------------ */

	public static function handle_replace_reference( WP_REST_Request $req ) {
		$old_url = trim( (string) $req->get_param( 'old_url' ) );
		$new_url = trim( (string) $req->get_param( 'new_url' ) );

		if ( '' === $old_url || '' === $new_url ) {
			return new WP_Error( 'urls_required', 'old_url and new_url are both required.', array( 'status' => 400 ) );
		}
		if ( $old_url === $new_url ) {
			return new WP_Error( 'urls_identical', 'old_url and new_url are the same. Nothing to do.', array( 'status' => 400 ) );
		}

		// A swap to a URL that 404s replaces a heavy image with a broken one,
		// and the failure shows up as a blank space on the front end rather
		// than as an error here. For local uploads we can check the file is
		// really on disk before queueing anything.
		$missing = self::local_file_missing( $new_url );
		if ( null !== $missing && ! filter_var( $req->get_param( 'allow_missing_target' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return new WP_Error(
				'new_url_not_on_disk',
				sprintf(
					'new_url maps to %s, which does not exist on this server. Upload the file first, or pass allow_missing_target=true if it is served from elsewhere (CDN, external host).',
					$missing
				),
				array( 'status' => 422 )
			);
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-asset-references.php';
		$limit = (int) $req->get_param( 'limit' );
		$plan  = CC_Assistant_Asset_References::plan( $old_url, $new_url, $limit > 0 ? $limit : CC_Assistant_Asset_References::MAX_RESULTS );

		if ( empty( $plan['targets'] ) ) {
			return new WP_Error(
				'no_references',
				sprintf(
					'Nothing in the database references %s%s. Nothing was queued.',
					$old_url,
					empty( $plan['skipped'] ) ? '' : sprintf( ' (%d location(s) matched but were skipped — see find_asset_references for the reasons)', count( $plan['skipped'] ) )
				),
				array( 'status' => 404, 'skipped' => $plan['skipped'] )
			);
		}

		$compact = CC_Assistant_Asset_References::compact_plan( $plan );

		// More locations exist than this pass can carry. Queue what we have —
		// refusing would leave no way forward once the cap is the ceiling —
		// but put it in the summary the reviewer reads, not just the response,
		// because a partial swap that looks complete is the failure mode here.
		$partial = ! empty( $plan['truncated'] );

		$summary = sprintf(
			'%sReplace %s with %s across %d location(s), %d occurrence(s)',
			$partial ? 'PARTIAL (more remain) — ' : '',
			self::basename_of( $old_url ),
			self::basename_of( $new_url ),
			$plan['location_count'],
			$plan['replacement_count']
		);

		if ( filter_var( $req->get_param( 'dry_run' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return self::wrap(
				array(
					'dry_run'           => true,
					'queued'            => false,
					'change_summary'    => $summary,
					'location_count'    => $plan['location_count'],
					'replacement_count' => $plan['replacement_count'],
					'truncated'         => $partial,
					'targets'           => $compact['targets'],
					'skipped'           => $plan['skipped'],
				)
			);
		}

		// Labels and context snippets come straight out of the database, and
		// old rows on long-lived sites carry latin1 bytes that are not valid
		// UTF-8. wp_json_encode returns false on those, which would store an
		// empty payload and only surface as a baffling failure at approval
		// time. Catch it here, while there is still something useful to say.
		$payload = wp_json_encode( $compact );
		if ( false === $payload || ! is_string( $payload ) ) {
			return new WP_Error(
				'plan_not_encodable',
				'The replacement plan could not be encoded as JSON, which usually means one of the matched rows contains invalid UTF-8. Nothing was queued. Narrow the search with a lower limit to identify the offending row.',
				array( 'status' => 500 )
			);
		}

		require_once CC_ASSISTANT_DIR . 'includes/class-pending-changes.php';
		$pending_id = CC_Assistant_Pending_Changes::queue(
			array(
				'post_id'        => null,
				'change_type'    => 'asset_reference_replace',
				'change_summary' => $summary,
				'current_value'  => $old_url,
				'proposed_value' => $payload,
				'reasoning'      => (string) $req->get_param( 'reasoning' ),
			)
		);
		if ( is_wp_error( $pending_id ) ) { return $pending_id; }

		return self::wrap(
			array(
				'queued'            => true,
				'pending_id'        => $pending_id,
				'change_summary'    => $summary,
				'location_count'    => $plan['location_count'],
				'replacement_count' => $plan['replacement_count'],
				'targets'           => $compact['targets'],
				'skipped'           => $plan['skipped'],
				'truncated'         => $partial,
				'note'              => ( $partial
					? 'INCOMPLETE: more locations reference this URL than one change can carry. Approve this, then call replace_asset_reference again with the same arguments and repeat until find_asset_references returns nothing. '
					: '' )
					. 'Each row is re-read at apply time and skipped if its content changed after queueing, so an edit made in the meantime is never overwritten. Applying does not delete the old file — revert by running the swap in reverse.',
			)
		);
	}

	/**
	 * Local path a URL maps to, but only when that path is missing.
	 * Returns null when the file exists, or when the URL is not local (an
	 * external host is somebody else's problem to verify).
	 */
	private static function local_file_missing( $url ) {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}

		$normalized = preg_replace( '#^https?://#', '//', $url );
		$base       = preg_replace( '#^https?://#', '//', $uploads['baseurl'] );

		if ( 0 === strpos( $normalized, $base ) ) {
			$relative = substr( $normalized, strlen( $base ) );
		} elseif ( 0 === strpos( $url, '/wp-content/uploads/' ) ) {
			$relative = substr( $url, strlen( '/wp-content/uploads' ) );
		} else {
			return null;
		}

		$relative = rawurldecode( strtok( $relative, '?#' ) );
		if ( false !== strpos( $relative, '..' ) ) {
			return null;
		}

		$path = rtrim( $uploads['basedir'], '/\\' ) . $relative;
		return file_exists( $path ) ? null : $path;
	}

	private static function basename_of( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = '' === $path ? $url : basename( $path );
		return '' === $name ? $url : $name;
	}

	/* ---------------------------------------------------------------------
	 * GET /assets/audit — which images are heavy?
	 * ------------------------------------------------------------------ */

	/**
	 * Heaviest image attachments, measured on disk rather than from the
	 * metadata array — WP only started recording filesize in 6.0, and a file
	 * replaced over FTP leaves stale metadata behind either way.
	 *
	 * Also reports the -scaled situation, because that is where uploads
	 * silently gain weight: WordPress re-encodes anything wider than
	 * big_image_size_threshold (2560px by default) and serves the re-encode,
	 * which for an already-optimised file is routinely LARGER than what was
	 * uploaded. The fix is to resize before uploading, not after.
	 */
	public static function handle_media_audit( WP_REST_Request $req ) {
		$min_kb = (int) $req->get_param( 'min_kb' );
		if ( $min_kb <= 0 ) {
			$min_kb = 150;
		}
		$limit = (int) $req->get_param( 'limit' );
		if ( $limit <= 0 || $limit > self::AUDIT_LIMIT ) {
			$limit = self::AUDIT_LIMIT;
		}
		$min_bytes = $min_kb * 1024;

		$scan_cap = 500;
		$ids      = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif' ),
				'posts_per_page'         => $scan_cap,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		// Filters may legitimately return false to disable scaling entirely.
		// Casting that to int gives 0, which would mark every image in the
		// library as over-threshold, so fall back to the core default.
		$threshold = apply_filters( 'big_image_size_threshold', 2560, array( 0, 0 ), '', 0 );
		$threshold = ( is_numeric( $threshold ) && $threshold > 0 ) ? (int) $threshold : 2560;

		$rows           = array();
		$scanned        = 0;
		$total_bytes    = 0;
		$oversized_dims = 0;

		foreach ( $ids as $id ) {
			$path = get_attached_file( $id );
			if ( ! $path || ! file_exists( $path ) ) {
				continue;
			}
			$scanned++;
			$bytes        = (int) filesize( $path );
			$total_bytes += $bytes;
			if ( $bytes < $min_bytes ) {
				continue;
			}

			$meta = wp_get_attachment_metadata( $id );
			$w    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$h    = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

			$row = array(
				'id'         => (int) $id,
				'kb'         => round( $bytes / 1024, 1 ),
				'width'      => $w,
				'height'     => $h,
				'mime'       => get_post_mime_type( $id ),
				'url'        => wp_get_attachment_url( $id ),
				'file'       => isset( $meta['file'] ) ? $meta['file'] : basename( $path ),
			);

			// The -scaled trap: original_image means WP re-encoded on upload.
			// Report both byte counts so it is visible when the "optimised"
			// file being served is bigger than what was uploaded.
			if ( ! empty( $meta['original_image'] ) ) {
				$original_path = path_join( dirname( $path ), $meta['original_image'] );
				$row['scaled'] = true;
				if ( file_exists( $original_path ) ) {
					$orig_bytes           = (int) filesize( $original_path );
					$row['original_kb']   = round( $orig_bytes / 1024, 1 );
					$row['scaled_is_bigger'] = $bytes > $orig_bytes;
				}
			}
			if ( $w > $threshold || $h > $threshold ) {
				$oversized_dims++;
				$row['over_threshold'] = true;
			}

			$rows[] = $row;
		}

		usort(
			$rows,
			function ( $a, $b ) {
				if ( $a['kb'] === $b['kb'] ) {
					return 0;
				}
				return $a['kb'] < $b['kb'] ? 1 : -1;
			}
		);
		$heaviest = array_slice( $rows, 0, $limit );

		$inflated = array();
		foreach ( $rows as $r ) {
			if ( ! empty( $r['scaled_is_bigger'] ) ) {
				$inflated[] = $r['file'];
			}
		}

		// How many image attachments exist in total, so a library bigger than
		// the scan cap reports "I looked at 500 of 3,000" instead of implying
		// it weighed everything.
		// wp_count_attachments() returns an object mapping mime type directly
		// to an integer count — NOT to a per-status object. Reading ->inherit
		// off the integer yielded 0 for every type, so the cap disclosure this
		// exists to provide silently reported "not capped" on a library that
		// was capped.
		$library_total = 0;
		foreach ( (array) wp_count_attachments() as $mime => $mime_count ) {
			if ( 0 === strpos( (string) $mime, 'image/' ) ) {
				$library_total += (int) $mime_count;
			}
		}
		// Belt and braces: if the count is unavailable for any reason, a full
		// scan batch still means there is more behind it.
		$capped = ( $library_total > $scan_cap ) || ( $library_total < 1 && count( $ids ) >= $scan_cap );

		return self::wrap(
			array(
				'scanned'                  => $scanned,
				'image_attachments_total'  => $library_total,
				'scan_capped'              => $capped,
				'over_threshold_kb'        => count( $rows ),
				'min_kb'                   => $min_kb,
				'scanned_total_mb'         => round( $total_bytes / 1048576, 1 ),
				'big_image_size_threshold' => $threshold,
				'oversized_dimensions'     => $oversized_dims,
				'inflated_by_rescale'      => $inflated,
				'heaviest'                 => $heaviest,
				'hint'                     => ( $capped
					? sprintf(
						'PARTIAL: only the %d most recently added images were scanned%s. Totals below cover the scanned set only. ',
						$scan_cap,
						$library_total > 0 ? sprintf( ', out of %d in the library', $library_total ) : ''
					)
					: '' )
					. 'kb is measured on disk, not from attachment metadata. inflated_by_rescale lists files where WordPress re-encoded the upload into something LARGER than the original — resize to at most ' . $threshold . 'px before uploading and WordPress leaves the file untouched. Find where any of these are used with find_asset_references(url).',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * POST /assets/upload — put a file INTO the media library
	 * ------------------------------------------------------------------ */

	/**
	 * Add one image to the media library from base64 bytes.
	 *
	 * Why this does NOT go through the pending queue, when every content
	 * write here does: the queue exists so nothing the reviewer has not seen
	 * can change what a visitor sees. An unattached attachment changes
	 * nothing — no page references it, no template renders it, it is inert
	 * until a SEPARATE content edit points at it, and that edit is queued
	 * like any other. Routing bytes through the queue would also mean parking
	 * multi-megabyte base64 payloads in a pending row, where they would be
	 * diffed and rendered in the review UI. So: uploading is direct and
	 * inert; USING the upload stays gated. Every attachment created here is
	 * stamped with _cc_assistant_uploaded so the operator can find and remove
	 * anything that was added but never referenced.
	 *
	 * The extension is not trusted. getimagesizefromstring reads the actual
	 * bytes and the detected type must match the claimed extension, so a
	 * .webp carrying something else is refused rather than stored.
	 */
	public static function handle_upload_media( WP_REST_Request $req ) {
		$filename = sanitize_file_name( (string) $req->get_param( 'filename' ) );
		$b64      = (string) $req->get_param( 'content_base64' );

		if ( '' === $filename ) {
			return new WP_Error( 'filename_required', 'filename is required, with an extension (e.g. "insulin-resistance-symptoms.webp").', array( 'status' => 400 ) );
		}
		if ( '' === $b64 ) {
			return new WP_Error( 'content_required', 'content_base64 is required (the raw file bytes, base64-encoded).', array( 'status' => 400 ) );
		}

		$allowed = self::upload_allowed_types();
		$ext     = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! isset( $allowed[ $ext ] ) ) {
			return new WP_Error(
				'extension_not_allowed',
				sprintf( 'Extension "%s" is not accepted. Allowed: %s. SVG is deliberately excluded because it is markup and can carry script.', $ext, implode( ', ', array_keys( $allowed ) ) ),
				array( 'status' => 400 )
			);
		}

		$bytes = base64_decode( $b64, true );
		if ( false === $bytes || '' === $bytes ) {
			return new WP_Error( 'content_not_base64', 'content_base64 did not decode. Send standard base64 of the raw file bytes, with no data: URI prefix.', array( 'status' => 400 ) );
		}
		if ( strlen( $bytes ) > self::UPLOAD_MAX_BYTES ) {
			return new WP_Error(
				'file_too_large',
				sprintf( 'Decoded file is %s; the ceiling is %s. Resize or re-encode before uploading — WordPress re-encodes anything wider than big_image_size_threshold anyway, often LARGER than the original.', size_format( strlen( $bytes ) ), size_format( self::UPLOAD_MAX_BYTES ) ),
				array( 'status' => 400 )
			);
		}

		// Trust the bytes, not the name.
		$probe = @getimagesizefromstring( $bytes );
		if ( ! is_array( $probe ) || empty( $probe[2] ) ) {
			return new WP_Error( 'not_an_image', 'The decoded bytes are not a readable image. Nothing was written.', array( 'status' => 400 ) );
		}
		if ( (int) $probe[2] !== $allowed[ $ext ] ) {
			return new WP_Error(
				'extension_mismatch',
				sprintf( 'The file claims .%s but the bytes are %s. Rename it to match the real format, or re-encode it.', $ext, image_type_to_extension( (int) $probe[2], false ) ),
				array( 'status' => 400 )
			);
		}

		$written = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $written['error'] ) ) {
			return new WP_Error( 'upload_failed', 'WordPress refused the write: ' . $written['error'], array( 'status' => 500 ) );
		}

		$mime       = wp_check_filetype( $written['file'] );
		$attachment = array(
			'post_mime_type' => $mime['type'] ? $mime['type'] : $probe['mime'],
			'post_title'     => (string) $req->get_param( 'title' ) !== ''
				? sanitize_text_field( (string) $req->get_param( 'title' ) )
				: preg_replace( '/\.[^.]+$/', '', basename( $written['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$parent_id = (int) $req->get_param( 'post_id' );
		if ( $parent_id > 0 && get_post( $parent_id ) ) {
			$attachment['post_parent'] = $parent_id;
		}

		$attachment_id = wp_insert_attachment( $attachment, $written['file'], isset( $attachment['post_parent'] ) ? $attachment['post_parent'] : 0, true );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $written['file'] );
			return new WP_Error( 'attachment_failed', 'File written but the attachment record failed: ' . $attachment_id->get_error_message(), array( 'status' => 500 ) );
		}

		// Generates the intermediate sizes the theme requests. Without this the
		// media library shows the item but srcset stays empty.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $written['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$alt = trim( (string) $req->get_param( 'alt' ) );
		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		update_post_meta( $attachment_id, '_cc_assistant_uploaded', gmdate( 'c' ) . ' v' . ( defined( 'CC_ASSISTANT_VERSION' ) ? CC_ASSISTANT_VERSION : '' ) );

		require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
		if ( class_exists( 'CC_Assistant_Activity_Log' ) && method_exists( 'CC_Assistant_Activity_Log', 'record' ) ) {
			// record( $type, $summary, $post_id, ... ) — summary is the SECOND arg.
			CC_Assistant_Activity_Log::record(
				'media_uploaded',
				sprintf( 'Uploaded %s (#%d, %s)', basename( $written['file'] ), $attachment_id, size_format( strlen( $bytes ) ) ),
				$attachment_id
			);
		}

		return self::wrap(
			array(
				'attachment_id' => (int) $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'filename'      => basename( $written['file'] ),
				'mime'          => $attachment['post_mime_type'],
				'width'         => (int) $probe[0],
				'height'        => (int) $probe[1],
				'bytes'         => strlen( $bytes ),
				'bytes_human'   => size_format( strlen( $bytes ) ),
				'alt'           => $alt,
				'post_parent'   => isset( $attachment['post_parent'] ) ? (int) $attachment['post_parent'] : 0,
				'renamed'       => basename( $written['file'] ) !== $filename,
				'sizes'         => isset( $metadata['sizes'] ) ? array_keys( $metadata['sizes'] ) : array(),
				'hint'          => 'The file is in the library and referenced by nothing. Point a page at it with draft_patch_post_content / draft_update_elementor_widget / featured_image_id — that step queues for approval as usual. If the filename collided, "renamed" is true and "url" is the one that actually exists; use it verbatim.',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * POST /assets/delete — remove orphaned attachments, permanently
	 * ------------------------------------------------------------------ */

	/**
	 * Every other write in this plugin is draft-only because a bad edit can be
	 * rejected in the inbox. A deleted attachment cannot: wp_delete_attachment
	 * with force takes the original and every generated size off disk, and no
	 * approval queue can put those bytes back. So the safety lives in front of
	 * the call rather than behind it, in three refusals:
	 *
	 *   1. Not ours. Only attachments carrying _cc_assistant_uploaded can go.
	 *      The library is full of client photography that no automated tool
	 *      has any business removing, and "delete attachment 4821" is one
	 *      transposed digit away from destroying it.
	 *   2. Still referenced. Checked against the full-size URL, every
	 *      registered intermediate size, _thumbnail_id, and numeric ids inside
	 *      _elementor_data. A referenced image deleted anyway does not error,
	 *      it just leaves a hole on a live page that nobody notices for weeks.
	 *   3. Not confirmed. Without confirm=true this reports the plan and
	 *      deletes nothing, so the default behaviour of a mistaken call is a
	 *      dry run.
	 */
	public static function handle_delete_media( WP_REST_Request $req ) {
		$raw = $req->get_param( 'ids' );
		if ( null === $raw || '' === $raw ) {
			$raw = $req->get_param( 'id' );
		}
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,]+/', trim( $raw ), -1, PREG_SPLIT_NO_EMPTY );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $raw ) ) ) );

		if ( empty( $ids ) ) {
			return new WP_Error( 'ids_required', 'ids is required: one attachment ID, or a list of them.', array( 'status' => 400 ) );
		}
		if ( count( $ids ) > 50 ) {
			return new WP_Error( 'too_many', sprintf( '%d ids given; 50 is the ceiling per call so a mistake stays small.', count( $ids ) ), array( 'status' => 400 ) );
		}

		$confirm       = filter_var( $req->get_param( 'confirm' ), FILTER_VALIDATE_BOOLEAN );
		$allow_foreign = filter_var( $req->get_param( 'allow_foreign' ), FILTER_VALIDATE_BOOLEAN );

		require_once CC_ASSISTANT_DIR . 'includes/class-asset-references.php';

		$deletable = array();
		$blocked   = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				$blocked[] = array(
					'id'     => $id,
					'reason' => 'not_an_attachment',
					'detail' => $post ? sprintf( 'Post %d is a %s, not an attachment.', $id, $post->post_type ) : sprintf( 'No post %d exists.', $id ),
				);
				continue;
			}

			$url  = wp_get_attachment_url( $id );
			$file = get_attached_file( $id );
			$ours = get_post_meta( $id, '_cc_assistant_uploaded', true );

			if ( ! $ours && ! $allow_foreign ) {
				$blocked[] = array(
					'id'       => $id,
					'filename' => $file ? basename( $file ) : '',
					'url'      => $url,
					'reason'   => 'not_uploaded_by_cc_assistant',
					'detail'   => 'This attachment has no _cc_assistant_uploaded marker, so it was not put here by this plugin. It may be client photography. Pass allow_foreign=true only if a human has confirmed this specific file is disposable.',
				);
				continue;
			}

			$refs = self::attachment_references( $id, $url );
			if ( ! empty( $refs['blocking'] ) ) {
				$blocked[] = array(
					'id'        => $id,
					'filename'  => $file ? basename( $file ) : '',
					'url'       => $url,
					'reason'    => 'still_referenced',
					'detail'    => sprintf( 'Referenced in %d live place(s). Repoint or remove those first; deleting now leaves a broken image on a live page.', count( $refs['blocking'] ) ),
					'locations' => wp_list_pluck( array_slice( $refs['blocking'], 0, 20 ), 'label' ),
				);
				continue;
			}

			$row = array(
				'id'          => $id,
				'filename'    => $file ? basename( $file ) : '',
				'url'         => $url,
				'uploaded_by' => $ours ? $ours : 'unknown',
				'bytes'       => ( $file && file_exists( $file ) ) ? filesize( $file ) : 0,
			);
			// Reported, not blocking. Superseding a graphic is what creates the
			// revision holding its old URL, so blocking on revisions would mean
			// never being able to delete a superseded graphic at all. Worth
			// knowing about: restoring one of these revisions after the delete
			// would restore a reference to a file that is gone.
			if ( ! empty( $refs['advisory'] ) ) {
				$row['also_in_revisions'] = wp_list_pluck( array_slice( $refs['advisory'], 0, 10 ), 'label' );
			}
			$deletable[] = $row;
		}

		if ( ! $confirm ) {
			return self::wrap(
				array(
					'dry_run'   => true,
					'requested' => count( $ids ),
					'deletable' => $deletable,
					'blocked'   => $blocked,
					'hint'      => empty( $deletable )
						? 'Nothing here can be deleted. Read the blocked reasons; still_referenced means the file is live somewhere.'
						: sprintf( 'Nothing has been deleted. %d attachment(s) would go, permanently, files included. Re-send the same ids with confirm=true to do it.', count( $deletable ) ),
				)
			);
		}

		$deleted = array();
		$failed  = array();
		foreach ( $deletable as $row ) {
			// force=true so the originals and every generated size leave disk
			// too. Without it they sit in the trash as orphaned files nobody
			// ever empties, which is the problem this tool exists to solve.
			$result = wp_delete_attachment( $row['id'], true );
			if ( $result ) {
				$deleted[] = $row;
			} else {
				$failed[] = array_merge( $row, array( 'reason' => 'wp_delete_attachment returned false' ) );
			}
		}

		if ( ! empty( $deleted ) ) {
			require_once CC_ASSISTANT_DIR . 'includes/class-activity-log.php';
			if ( class_exists( 'CC_Assistant_Activity_Log' ) && method_exists( 'CC_Assistant_Activity_Log', 'record' ) ) {
				CC_Assistant_Activity_Log::record(
					'media_deleted',
					sprintf(
						'Deleted %d unreferenced attachment(s): %s',
						count( $deleted ),
						implode( ', ', array_map(
							static function ( $r ) {
								return sprintf( '#%d %s', $r['id'], $r['filename'] );
							},
							array_slice( $deleted, 0, 25 )
						) )
					),
					0
				);
			}
		}

		return self::wrap(
			array(
				'dry_run'   => false,
				'requested' => count( $ids ),
				'deleted'   => $deleted,
				'failed'    => $failed,
				'blocked'   => $blocked,
				'hint'      => sprintf(
					'%d deleted, %d blocked, %d failed. Deletions are permanent and are recorded in the activity log.',
					count( $deleted ),
					count( $blocked ),
					count( $failed )
				),
			)
		);
	}

	/**
	 * Everywhere an attachment can be referenced, split into blocking and not.
	 *
	 * Two lessons are baked in here, both learned the hard way.
	 *
	 * Searching each size URL through the reference finder separately meant
	 * roughly thirty LIKE queries per attachment and timed the endpoint out at
	 * twenty ids. One search on the FILE STEM covers the original and every
	 * generated size at once, because WordPress derives "name-300x157.webp"
	 * from "name.webp". A short stem can over-match, and that is the safe
	 * direction: a false "referenced" costs a blocked delete, a false
	 * "unreferenced" costs the file.
	 *
	 * And revisions must NOT block. Superseding a graphic is precisely what
	 * creates a revision holding the old URL, so treating revisions as
	 * blocking meant the tool could never delete the one thing it exists to
	 * delete. They are reported instead, so the operator knows a rollback
	 * would restore a reference to a file that is gone.
	 */
	private static function attachment_references( $id, $url ) {
		global $wpdb;

		$blocking = array();
		$advisory = array();

		$file = get_post_meta( $id, '_wp_attached_file', true );
		$stem = $file ? pathinfo( $file, PATHINFO_FILENAME ) : ( $url ? pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME ) : '' );
		if ( '' === $stem ) {
			return array( 'blocking' => $blocking, 'advisory' => $advisory );
		}
		$like = '%' . $wpdb->esc_like( $stem ) . '%';

		// Its own attachment row always contains the stem, in guid and title.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type, post_status, post_title
				   FROM {$wpdb->posts}
				  WHERE post_content LIKE %s
				    AND ID <> %d
				    AND post_status <> 'auto-draft'
				  LIMIT 25",
				$like,
				$id
			)
		);
		foreach ( $posts as $row ) {
			$entry = array(
				'kind'      => 'revision' === $row->post_type ? 'revision' : 'post_content',
				'post_id'   => (int) $row->ID,
				'post_type' => $row->post_type,
				'status'    => $row->post_status,
				'label'     => sprintf( '%s #%d "%s"', $row->post_type, (int) $row->ID, self::short_title( $row->post_title ) ),
			);
			if ( 'revision' === $row->post_type ) {
				$advisory[] = $entry;
			} else {
				$blocking[] = $entry;
			}
		}

		// Its own _wp_attached_file / _wp_attachment_metadata rows name the stem.
		$meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key
				   FROM {$wpdb->postmeta}
				  WHERE meta_value LIKE %s
				    AND post_id <> %d
				  LIMIT 25",
				$like,
				$id
			)
		);
		foreach ( $meta as $row ) {
			$blocking[] = array(
				'kind'     => 'postmeta',
				'post_id'  => (int) $row->post_id,
				'meta_key' => $row->meta_key,
				'label'    => sprintf( 'postmeta %s on #%d "%s"', $row->meta_key, (int) $row->post_id, self::short_title( get_the_title( $row->post_id ) ) ),
			);
		}

		// A featured image lives only as a numeric id; no URL appears anywhere.
		$thumbs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s LIMIT 20",
				(string) $id
			)
		);
		foreach ( $thumbs as $post_id ) {
			$blocking[] = array(
				'kind'    => 'featured_image',
				'post_id' => (int) $post_id,
				'label'   => sprintf( 'featured image of #%d "%s"', (int) $post_id, self::short_title( get_the_title( $post_id ) ) ),
			);
		}

		// Elementor keeps a numeric id beside the url so it can resolve a
		// themed size at render time.
		$elementor = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				  WHERE meta_key = '_elementor_data'
				    AND ( meta_value LIKE %s OR meta_value LIKE %s )
				  LIMIT 20",
				'%' . $wpdb->esc_like( '"id":' . $id . ',' ) . '%',
				'%' . $wpdb->esc_like( '"id":"' . $id . '"' ) . '%'
			)
		);
		foreach ( $elementor as $post_id ) {
			$blocking[] = array(
				'kind'    => 'elementor_widget',
				'post_id' => (int) $post_id,
				'label'   => sprintf( 'Elementor widget on #%d "%s"', (int) $post_id, self::short_title( get_the_title( $post_id ) ) ),
			);
		}

		// Queued but unapproved counts. Draft-only writing means a graphic can
		// be uploaded and pointed at by a pending change the live tables know
		// nothing about; deleting it would break the operator's next approval.
		$pending_table = $wpdb->prefix . 'cc_pending_changes';
		if ( $pending_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pending_table ) ) ) {
			$queued = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, post_id FROM {$pending_table}
					  WHERE status = 'pending'
					    AND superseded_by IS NULL
					    AND proposed_value LIKE %s
					  LIMIT 20",
					$like
				)
			);
			foreach ( $queued as $row ) {
				$blocking[] = array(
					'kind'    => 'pending_change',
					'post_id' => (int) $row->post_id,
					'label'   => sprintf( 'unapproved pending #%d on post #%d', (int) $row->id, (int) $row->post_id ),
				);
			}
		}

		return array( 'blocking' => $blocking, 'advisory' => $advisory );
	}

	private static function short_title( $title ) {
		$title = wp_strip_all_tags( (string) $title );
		return ( strlen( $title ) > 50 ) ? substr( $title, 0, 50 ) . '...' : $title;
	}

	private static function wrap( $data ) {
		return array(
			'site' => array(
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url(),
				'fingerprint' => CC_Assistant_Site_Identity::fingerprint(),
			),
			'data' => $data,
		);
	}
}

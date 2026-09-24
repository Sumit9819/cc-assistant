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

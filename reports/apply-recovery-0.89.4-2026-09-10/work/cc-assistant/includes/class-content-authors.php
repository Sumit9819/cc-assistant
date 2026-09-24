<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Public author identities, distinct from the authenticated automation account. */
class CC_Assistant_Content_Authors {
	public static function validate( $id ) {
		$user = get_userdata( (int) $id );
		if ( ! $user || ! empty( $user->spam ) || ! empty( $user->deleted ) || ! user_can( $user, 'edit_posts' ) ||
			( is_multisite() && ! is_user_member_of_blog( $user->ID ) ) ) {
			return new WP_Error( 'content_author_unavailable', 'Select an existing content author on this site. No account or credentials will be invented.', array( 'status' => 422 ) );
		}
		return array( 'id' => (int) $user->ID, 'display_name' => (string) $user->display_name );
	}

	public static function resolve( $requested_id = 0 ) {
		$profile = (array) get_option( 'cc_assistant_content_scope', array() );
		$id = (int) ( $requested_id ?: ( $profile['author_id'] ?? 0 ) );
		if ( $id <= 0 ) { return new WP_Error( 'content_author_required', 'Read get_content_authors and choose the appropriate existing public author. Pass author_id or save it with manage_content_scope; do not silently use the automation login.', array( 'status' => 422 ) ); }
		$author = self::validate( $id );
		if ( ! $requested_id && ! is_wp_error( $author ) && ( $profile['author_name_at_selection'] ?? '' ) !== $author['display_name'] ) { return new WP_Error( 'content_author_changed', 'The selected author display name changed. Read current authors and refresh the intended attribution before creating content.', array( 'status' => 409 ) ); }
		return $author;
	}

	public static function listing() {
		$users = get_users( array( 'has_published_posts' => array( 'post', 'page' ), 'number' => 50, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$authors = array();
		foreach ( $users as $user ) { $author = self::validate( $user->ID ); if ( ! is_wp_error( $author ) ) { $authors[] = $author; } }
		$default = self::resolve();
		return array( 'authors' => $authors, 'default_author' => is_wp_error( $default ) ? null : $default,
			'coverage' => 'Up to fifty existing users with published posts/pages and content-author capability. Login names, emails and credentials are not returned.',
			'limit_reached' => count( $users ) >= 50,
			'next_step' => 'Claude: choose the site-appropriate existing author, normally its established organization author when that matches the operator instruction. Save author_id and expected_author_name in manage_content_scope. Authorship is attribution, not proof of expertise or medical review.' );
	}
}

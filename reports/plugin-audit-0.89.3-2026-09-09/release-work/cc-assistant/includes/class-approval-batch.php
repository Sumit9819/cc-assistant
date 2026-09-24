<?php
/** Request-local evidence for independent widget edits selected in one approval. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CC_Assistant_Approval_Batch {
	private $rows = array();
	private $selected = array();
	private $posts = array();
	private $used = array();

	public function __construct( array $ids ) {
		$blocked = array();
		foreach ( $ids as $id ) {
			$row = CC_Assistant_Pending_Changes::get( $id );
			if ( $row ) { $this->selected[(int) $id] = self::signature( $row ); }
			if ( ! $row || 'pending' !== $row->status || 'elementor_widget_update' !== $row->change_type || ! empty( $row->superseded_by ) ) { continue; }
			$proof = CC_Assistant_Integrity::baseline( $row )['evidence'] ?? array();
			$pid = (int) $row->post_id;
			if ( isset( $blocked[$pid] ) ) { continue; }
			// Only proposals that were current before any batch write are eligible.
			if ( count( $proof['posts'] ?? array() ) !== 1 || empty( $proof['posts'][$pid]['post_hash'] ) || is_wp_error( CC_Assistant_Evidence_Gate::validate_apply( $row ) ) ) { continue; }
			$payload = json_decode( $row->proposed_value, true );
			if ( empty( $payload['widget_id'] ) || ! is_string( $payload['widget_id'] ) || ! is_array( $payload['settings'] ?? null ) ) { continue; }
			if ( isset( $this->posts[$pid] ) && $this->posts[$pid] !== $proof['posts'][$pid]['post_hash'] ) {
				// A concurrent edit during preflight must not rebase earlier rows.
				unset( $this->posts[$pid] ); $blocked[$pid] = true; continue;
			}
			$this->rows[(int) $id] = array( 'signature' => self::signature( $row ), 'post_id' => $pid, 'widget_id' => $payload['widget_id'] );
			$this->posts[$pid] = $proof['posts'][$pid]['post_hash'];
		}
	}

	private static function signature( $row ) {
		return hash( 'sha256', wp_json_encode( array( $row->post_id, $row->change_type, $row->proposed_value, $row->current_value, $row->pre_check_baseline ) ) );
	}

	public function matches( $row ) {
		return isset( $this->selected[(int) $row->id] ) && $this->selected[(int) $row->id] === self::signature( $row );
	}

	private function eligible( $row ) {
		$entry = $this->rows[(int) $row->id] ?? null;
		return $entry && isset( $this->posts[$entry['post_id']] ) && $entry['signature'] === self::signature( $row ) && empty( $this->used[$entry['post_id']][$entry['widget_id']] ) ? $entry : null;
	}

	public function expected_posts( $row ) {
		$entry = $this->eligible( $row );
		return $entry ? array( $entry['post_id'] => $this->posts[$entry['post_id']] ) : array();
	}

	/** Predict the whole post fingerprint before applying the approved widget patch. */
	public function predict( $row ) {
		$entry = $this->eligible( $row );
		if ( ! $entry || CC_Assistant_Integrity::post_hash( $entry['post_id'] ) !== $this->posts[$entry['post_id']] ) { return null; }
		$payload = json_decode( $row->proposed_value, true );
		$tree = json_decode( get_post_meta( $entry['post_id'], '_elementor_data', true ), true );
		if ( ! is_array( $tree ) ) { return null; }
		$count = 0;
		self::patch( $tree, $payload['widget_id'], $payload['settings'], $count );
		$json = wp_json_encode( $tree );
		if ( 1 !== $count || false === $json ) { return null; }
		$entry['expected'] = CC_Assistant_Integrity::post_hash( $entry['post_id'], array( '_elementor_data' => array( $json ), '_elementor_edit_mode' => array( 'builder' ) ) );
		return $entry;
	}

	private static function patch( array &$tree, $id, array $settings, &$count ) {
		foreach ( $tree as &$node ) {
			if ( ! is_array( $node ) ) { continue; }
			if ( ( $node['id'] ?? null ) === $id ) {
				++$count;
				$node['settings'] = array_merge( is_array( $node['settings'] ?? null ) ? $node['settings'] : array(), $settings );
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) { self::patch( $node['elements'], $id, $settings, $count ); }
		}
	}

	public function record_success( $prediction ) {
		// Unexpected hook/editor writes do not become trusted sibling evidence.
		if ( ! $prediction || empty( $prediction['expected'] ) || $prediction['expected'] !== CC_Assistant_Integrity::post_hash( $prediction['post_id'] ) ) { return; }
		$this->posts[$prediction['post_id']] = $prediction['expected'];
		$this->used[$prediction['post_id']][$prediction['widget_id']] = true;
	}
}

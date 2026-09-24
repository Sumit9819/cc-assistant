<?php
/**
 * Plugin Name: IWC Wellness Pass relay
 * Description: Sends Wellness Pass form submissions to the Google Apps Script that issues the coupon codes.
 * Version: 1.0.0
 *
 * WHY THIS EXISTS
 *
 * Elementor's own Webhook action cannot call a Google Apps Script URL. Apps Script
 * answers a POST with a 302. WordPress follows that redirect as a GET but keeps the
 * form body attached (wp-includes/class-wp-http.php, handle_redirects(): it sets
 * $args['method'] = 'GET' and never clears $args['body']), and Google answers a
 * GET-with-body with 400. Elementor sees a non-200 and tells the visitor the
 * submission failed, even though the script already ran, wrote the row and queued
 * the code email. The visitor then submits again.
 *
 * Measured against the live endpoint on 2026-09-16:
 *   POST                     -> 302
 *   GET to the redirect      -> 200   (what a browser does)
 *   GET with body attached   -> 400   (what WordPress does)
 *   POST to the redirect     -> 405
 *
 * This hook sends the same data itself and simply does not inspect the reply, so
 * there is nothing left to misread. Elementor keeps collecting submissions as a
 * local backup; only its Webhook action is switched off.
 *
 * INSTALL: put this file at wp-content/mu-plugins/iwc-wellness-pass.php
 * (create the mu-plugins folder if it does not exist). Must-use plugins load
 * automatically, so there is nothing to activate.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'elementor_pro/forms/new_record',
	function ( $record, $handler ) {

		// ---------------------------------------------------------------
		// Paste your two values here.
		//   SCRIPT_URL: the Apps Script Web app URL, the one ending in /exec
		//   SECRET:     the same long random string as CONFIG.SECRET in Code.gs
		// ---------------------------------------------------------------
		$script_url = 'PASTE_YOUR_EXEC_URL_HERE';
		$secret     = 'PASTE_YOUR_SECRET_HERE';

		// Only the Wellness Pass form. Every other form on the site is untouched.
		if ( 'Wellness Pass' !== $record->get_form_settings( 'form_name' ) ) {
			return;
		}

		if ( false !== strpos( $script_url, 'PASTE_YOUR' ) ) {
			error_log( 'IWC Wellness Pass relay: script URL not configured.' );
			return;
		}

		// Same shape the Apps Script already reads: fields[<id>][value].
		$body = array();
		foreach ( (array) $record->get( 'fields' ) as $id => $field ) {
			$body[ 'fields[' . $id . '][value]' ] = isset( $field['value'] ) ? $field['value'] : '';
		}

		$meta = (array) $record->get( 'meta' );
		$body['meta[page_url][value]'] = isset( $meta['page_url']['value'] ) ? $meta['page_url']['value'] : '';

		// The reply is deliberately ignored. Apps Script redirects to a host that
		// rejects the redirected request, and that is harmless: the script has
		// already done its work by then. Checking the reply is what broke the form.
		$response = wp_remote_post(
			add_query_arg( 'key', rawurlencode( $secret ), $script_url ),
			array(
				'body'      => $body,
				'timeout'   => 15,
				'blocking'  => true,
				'sslverify' => true,
			)
		);

		// Logged only so a genuine outage is findable later. Never shown to the visitor.
		if ( is_wp_error( $response ) ) {
			error_log( 'IWC Wellness Pass relay: ' . $response->get_error_message() );
		}
	},
	10,
	2
);

<?php
/**
 * Registration after an update: once per version change, in the background.
 *
 * On every request, after all plugins are loaded (so the new code is the code
 * running), the stored version stamp is compared with WEBFIABLE_INFO_VERSION.
 * When they differ the new stamp is written first; then, if the site was
 * already set up (site id, valid email, consent, data connection on, OpenSSL),
 * one WP-Cron single event is scheduled. That event re-checks the same
 * conditions and makes the same registration call the settings save makes
 * (webfiable_register_current_site). A failure changes nothing but the
 * activity log: no notice, no retry, no self-test.
 *
 * Never hooked on the upgrader's completion action: that runs inside the
 * request that replaced the files, with the old code still loaded.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBFIABLE_UPDATE_REGISTRATION_HOOK', 'webfiable_registration_after_update' );

/**
 * What a version change leads to. Pure.
 *
 * @param string $stored           The stored stamp ('' when there is none).
 * @param string $current          The running version.
 * @param bool   $preconditions_ok Whether the site is set up to register.
 * @return string 'none' (same version), 'schedule' or 'stamp' (stamp only).
 */
function webfiable_update_registration_decision( $stored, $current, $preconditions_ok ) {
	if ( (string) $stored === (string) $current ) {
		return 'none';
	}
	return $preconditions_ok ? 'schedule' : 'stamp';
}

/**
 * Whether the site is set up to register, read from the stored options.
 *
 * Checked in this order, stopping at the first failure.
 *
 * @return array{ok:bool, reason:string} The verdict and the reason code of the first failure.
 */
function webfiable_registration_preconditions() {
	if ( '' === (string) webfiable_get_option( 'webfiable_site_id' ) ) {
		return array(
			'ok'     => false,
			'reason' => 'no_site_id',
		);
	}
	if ( ! is_email( (string) webfiable_get_option( 'webfiable_admin_email' ) ) ) {
		return array(
			'ok'     => false,
			'reason' => 'no_email',
		);
	}
	if ( (int) webfiable_get_option( 'webfiable_consent_ts' ) <= 0 ) {
		return array(
			'ok'     => false,
			'reason' => 'no_consent',
		);
	}
	// Through the helper, never the raw option: it defaults to 'yes' and ignores the wp-config force.
	if ( ! webfiable_is_endpoint_enabled() ) {
		return array(
			'ok'     => false,
			'reason' => 'endpoint_disabled',
		);
	}
	if ( ! extension_loaded( 'openssl' ) ) {
		return array(
			'ok'     => false,
			'reason' => 'openssl_missing',
		);
	}
	return array(
		'ok'     => true,
		'reason' => '',
	);
}

/**
 * Compare the version stamp with the running version (plugins_loaded).
 *
 * Fast path: one autoloaded option and a string comparison. On a change the
 * stamp is written first, so the next request takes the fast path; at most one
 * event is queued. No translation function runs here.
 *
 * @return void
 */
function webfiable_check_version_stamp() {
	$stored = (string) get_option( 'webfiable_plugin_version', '' );
	if ( WEBFIABLE_INFO_VERSION === $stored ) {
		return;
	}

	$pre      = webfiable_registration_preconditions();
	$decision = webfiable_update_registration_decision( $stored, WEBFIABLE_INFO_VERSION, $pre['ok'] );

	update_option( 'webfiable_plugin_version', WEBFIABLE_INFO_VERSION, true );

	if ( 'schedule' === $decision ) {
		$scheduled = true;
		if ( ! wp_next_scheduled( WEBFIABLE_UPDATE_REGISTRATION_HOOK ) ) {
			// $wp_error = true: WordPress 5.7+ says why it refused; 5.3-5.6 ignore it and return false.
			$scheduled = wp_schedule_single_event( time(), WEBFIABLE_UPDATE_REGISTRATION_HOOK, array(), true );
		}
		if ( true === $scheduled ) {
			webfiable_log_action(
				'update_registration_scheduled',
				array(
					'from'          => $stored,
					'to'            => WEBFIABLE_INFO_VERSION,
					// A site whose WP-Cron never runs waits here until it does (or until a save).
					'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				)
			);
		} else {
			// Nothing was queued and the stamp is already written: no retry. The site
			// stays as it was; saving the settings registers it.
			webfiable_log_action(
				'update_registration_not_scheduled',
				array(
					'from'   => $stored,
					'to'     => WEBFIABLE_INFO_VERSION,
					'reason' => is_wp_error( $scheduled ) ? $scheduled->get_error_code() : 'schedule_returned_false',
				),
				'error'
			);
		}
	} else {
		webfiable_log_action(
			'update_registration_skipped',
			array(
				'from'   => $stored,
				'to'     => WEBFIABLE_INFO_VERSION,
				'reason' => $pre['reason'],
			)
		);
	}
}
add_action( 'plugins_loaded', 'webfiable_check_version_stamp' );

/**
 * The scheduled registration (WP-Cron, in the background).
 *
 * Re-checks the conditions (they may have changed since scheduling), then calls
 * the shared registration path. Only the log records the outcome; on success
 * the shared path also writes the registration stamp.
 *
 * @return void
 */
function webfiable_run_update_registration() {
	$pre = webfiable_registration_preconditions();
	if ( ! $pre['ok'] ) {
		webfiable_log_action(
			'update_registration_skipped_at_run',
			array(
				'reason' => $pre['reason'],
			),
			'warning'
		);
		return;
	}

	$result = webfiable_register_current_site( 'update' );

	if ( ! empty( $result['success'] ) ) {
		webfiable_log_action(
			'update_registration_succeeded',
			array(
				'http_code' => $result['http_code'],
			)
		);
	} else {
		webfiable_log_action(
			'update_registration_failed',
			array(
				'http_code' => isset( $result['http_code'] ) ? $result['http_code'] : null,
				'error'     => isset( $result['error'] ) ? $result['error'] : null,
			),
			'error'
		);
	}
}
add_action( WEBFIABLE_UPDATE_REGISTRATION_HOOK, 'webfiable_run_update_registration' );

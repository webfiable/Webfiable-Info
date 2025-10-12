<?php
/**
 * Webfiable Info — Registration-on-save handler.
 *
 * @package   Webfiable_Info
 * @license   GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the existing settings save and runs activation via the proxy.
 * - No UI changes
 * - No (re)registering settings
 * - Uses existing site_id created elsewhere
 */
final class Webfiable_Registration {

	/**
	 * Option name that stores your plugin settings array.
	 *
	 * @var string
	 */
	const OPTION = 'webfiable_info_settings';

	/**
	 * Settings group slug used by your existing settings_errors() calls.
	 *
	 * @var string
	 */
	const GROUP = 'webfiable_info';

	/**
	 * Default proxy base URL (override by storing proxy_url in the option).
	 *
	 * @var string
	 */
	const DEFAULT_PROXY_BASE = 'https://webfiable.com';

	/**
	 * Bootstrap: call from your main plugin file.
	 *
	 * @return void
	 */
	public static function init() {
		// Intercept the settings save; can veto by returning $old.
		add_filter( 'pre_update_option_' . self::OPTION, array( __CLASS__, 'maybe_register' ), 10, 3 );

		// Ensure our notices show (harmless if your page already calls settings_errors()).
		add_action(
			'admin_notices',
			static function () {
				if ( ! is_admin() ) {
					return;
				}
				settings_errors( self::GROUP );
			}
		);
	}

	/**
	 * Intercept settings save. If proxy returns true, allow save; otherwise keep old values.
	 *
	 * @param mixed  $new_value New (unsaved) option value from the settings form.
	 * @param mixed  $old_value Existing value in the DB.
	 * @param string $option    Option name (self::OPTION).
	 * @return mixed The value that will be saved (or the old value to veto).
	 */
	public static function maybe_register( $new_value, $old_value, $option ) {
		// Unused but required by the filter signature.
		unset( $option ); // phpcs:ignore VariableAnalysis.UnusedFunctionParameter

		$new = is_array( $new_value ) ? $new_value : array();
		$old = is_array( $old_value ) ? $old_value : array();

		// Pull fields from the new submission; fall back to old where appropriate.
		$site_id     = isset( $old['site_id'] ) ? (string) $old['site_id'] : ( isset( $new['site_id'] ) ? (string) $new['site_id'] : '' );
		$admin_email = isset( $new['admin_email'] ) ? sanitize_email( $new['admin_email'] ) : ( isset( $old['admin_email'] ) ? (string) $old['admin_email'] : '' );
		$consent     = ! empty( $new['consent'] ) ? 1 : 0;

		// Optional: proxy base can be configurable in your existing UI.
		$proxy_base = isset( $new['proxy_url'] ) ? esc_url_raw( $new['proxy_url'] ) : ( isset( $old['proxy_url'] ) ? (string) $old['proxy_url'] : self::DEFAULT_PROXY_BASE );

		// Required checks.
		if ( 1 !== (int) $consent ) {
			add_settings_error( self::GROUP, 'consent_required', esc_html__( 'You must accept the consent to register.', 'webfiable-info' ), 'error' );
			return $old;
		}

		if ( '' === $site_id ) {
			add_settings_error( self::GROUP, 'siteid_missing', esc_html__( 'Site ID is missing.', 'webfiable-info' ), 'error' );
			return $old;
		}

		if ( '' === $admin_email || ! is_email( $admin_email ) ) {
			add_settings_error( self::GROUP, 'email_invalid', esc_html__( 'Admin email is invalid.', 'webfiable-info' ), 'error' );
			return $old;
		}

		if ( '' === $proxy_base ) {
			add_settings_error( self::GROUP, 'proxy_missing', esc_html__( 'Proxy URL is required.', 'webfiable-info' ), 'error' );
			return $old;
		}

		// Call your WordPress proxy (boolean API).
		$endpoint = trailingslashit( untrailingslashit( $proxy_base ) ) . 'wp-json/webfiable/v1/activations';

		$payload = array(
			'siteId'     => $site_id,
			'siteUrl'    => home_url(), // must match what your API verifies.
			'adminEmail' => $admin_email,
		);

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			add_settings_error(
				self::GROUP,
				'proxy_net',
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'Registration failed (network): %s', 'webfiable-info' ),
					esc_html( $response->get_error_message() )
				),
				'error'
			);
			return $old;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$json    = json_decode( $body, true );
		$success = ( true === $json ) || ( $code >= 200 && $code < 300 && 'true' === trim( (string) $body ) );

		if ( ! $success ) {
			add_settings_error(
				self::GROUP,
				'proxy_fail',
				esc_html__( 'Registration could not be completed now. Please try again later.', 'webfiable-info' ),
				'error'
			);
			return $old;
		}

		// Success: annotate the new array and allow WordPress to save it.
		$new['registered']       = 1;
		$new['registered_at']    = current_time( 'mysql' );
		$new['last_reg_attempt'] = current_time( 'mysql' );
		$new['proxy_url']        = $proxy_base; // keep persisted if present.
		$new['site_id']          = $site_id;    // ensure we never drop it.

		add_settings_error( self::GROUP, 'proxy_success', esc_html__( 'Registration completed successfully.', 'webfiable-info' ), 'updated' );

		return $new;
	}
}

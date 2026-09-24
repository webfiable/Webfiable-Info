<?php
/**
 * Minimal persistent options and helpers.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Default persistent options.
 *
 * @return array<string, mixed> Default values.
 */
function webfiable_default_options() {
	return array(
		'webfiable_site_id'          => '',
		'webfiable_admin_email'      => '',
		'webfiable_consent_ts'       => 0,
		'webfiable_endpoint_enabled' => 'yes',
		'webfiable_action_log'       => array(),
		'webfiable_registered_ts'    => 0,
		'webfiable_plugin_version'   => '',
	);
}

/**
 * Read a plugin option with a sane default.
 *
 * @param string $key Option name.
 * @return mixed Stored value or default when not set.
 */
function webfiable_get_option( $key ) {
	$defaults = webfiable_default_options();
	return get_option( $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : null );
}

/**
 * Update a plugin option with autoload disabled.
 *
 * @param string $key   Option name.
 * @param mixed  $value Value to store.
 * @return void
 */
function webfiable_update_option( $key, $value ) {
	update_option( $key, $value, false ); // Store with autoload disabled.
}

/**
 * Determine whether the endpoint should be force-enabled via wp-config.
 *
 * @return bool
 */
function webfiable_is_endpoint_forced_enabled() {
	return defined( 'WEBFIABLE_INFO_ACTIVATE_ENDPOINT' ) && WEBFIABLE_INFO_ACTIVATE_ENDPOINT;
}

/**
 * Check if the /webfiable endpoint is enabled (considering forced override).
 *
 * @return bool
 */
function webfiable_is_endpoint_enabled() {
	if ( webfiable_is_endpoint_forced_enabled() ) {
		return true;
	}

	return ( webfiable_get_option( 'webfiable_endpoint_enabled' ) === 'yes' );
}

/**
 * Whether the settings page shows the card that leads to the panel.
 *
 * Consent, a valid email and the data connection are on. A failed registration on
 * save turns the connection off (unless a constant forces it), so this state means
 * the last save registered the site. It does not need the registration stamp: a
 * site configured before 2.2.0 has none until it registers again.
 *
 * @return bool
 */
function webfiable_panel_card_visible() {
	return (int) webfiable_get_option( 'webfiable_consent_ts' ) > 0
		&& is_email( (string) webfiable_get_option( 'webfiable_admin_email' ) )
		&& webfiable_is_endpoint_enabled();
}

/**
 * Whether the site is known to be registered: the card's conditions plus the
 * stamp a successful registration writes.
 *
 * The consent and email clauses matter because uninstall.php deletes them but
 * not the stamp: a reinstall must not say the site is registered.
 *
 * @return bool
 */
function webfiable_is_registered() {
	return (int) webfiable_get_option( 'webfiable_registered_ts' ) > 0
		&& webfiable_panel_card_visible();
}

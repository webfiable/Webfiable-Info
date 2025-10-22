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

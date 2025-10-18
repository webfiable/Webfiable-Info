<?php
/**
 * Uninstall routines for the Webfiable Info plugin.
 *
 * Borra las opciones persistentes creadas por el plugin.
 *
 * @package Webfiable_Info
 * @since 1.5.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$option_keys = array(
	'webfiable_admin_email',
	'webfiable_consent_ts',
	'webfiable_endpoint_enabled',
);

foreach ( $option_keys as $key ) {
	delete_option( $key );
}

// No closing PHP tag.

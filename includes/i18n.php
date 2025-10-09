<?php
/**
 * Internationalization loader.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Load the text domain for translations.
 *
 * @return void
 */
function webfiable_load_textdomain() {
	load_plugin_textdomain( 'webfiable-info', false, dirname( WEBFIABLE_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'webfiable_load_textdomain' );

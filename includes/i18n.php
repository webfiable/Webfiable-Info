<?php
/**
 * Internationalization loader.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Ensure translations load for the current locale.
 *
 * WordPress.org sites receive translations automatically, but we keep a fallback for
 * bundled .mo files (e.g. self-hosted installs without GlotPress downloads).
 *
 * @return void
 */
function webfiable_load_textdomain() {
	$domain = 'webfiable-info';

	if ( is_textdomain_loaded( $domain ) ) {
		return;
	}

	$locale = determine_locale();
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
	$locale = apply_filters( 'plugin_locale', $locale, $domain );

	load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo' );

	if ( is_textdomain_loaded( $domain ) ) {
		return;
	}

	$mofile = WEBFIABLE_PLUGIN_DIR . 'languages/' . $domain . '-' . $locale . '.mo';

	if ( file_exists( $mofile ) ) {
		load_textdomain( $domain, $mofile );
	}
}
add_action( 'init', 'webfiable_load_textdomain' );

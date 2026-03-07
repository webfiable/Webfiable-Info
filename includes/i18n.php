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

	// Build a list of locales to try: exact match first, then base-language fallback.
	$locales_to_try = array( $locale );
	$lang_prefix    = substr( $locale, 0, 2 );

	// For any Spanish variant (es_AR, es_MX, es_PE, …) fall back to es_ES.
	if ( 'es' === $lang_prefix && 'es_ES' !== $locale ) {
		$locales_to_try[] = 'es_ES';
	}

	foreach ( $locales_to_try as $try_locale ) {
		// Try WP global languages directory first.
		$global_mo = WP_LANG_DIR . '/plugins/' . $domain . '-' . $try_locale . '.mo';
		if ( file_exists( $global_mo ) ) {
			load_textdomain( $domain, $global_mo );
		}

		if ( is_textdomain_loaded( $domain ) ) {
			return;
		}

		// Try bundled languages directory.
		$local_mo = WEBFIABLE_PLUGIN_DIR . 'languages/' . $domain . '-' . $try_locale . '.mo';
		if ( file_exists( $local_mo ) ) {
			load_textdomain( $domain, $local_mo );
		}

		if ( is_textdomain_loaded( $domain ) ) {
			return;
		}
	}
}
add_action( 'init', 'webfiable_load_textdomain' );

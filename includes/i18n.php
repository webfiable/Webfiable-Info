<?php
/**
 * Internationalization loader.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Locales whose translation file is tried, in order.
 *
 * The source strings are Spanish, so a Spanish locale (es_ES, es_MX, es_AR…) needs
 * no file and sees the source. Every other locale tries its own file first and then
 * the bundled English translation (en_US), which is what non-Spanish sites saw
 * before 2.2.0.
 *
 * @param string $locale Current locale, e.g. de_DE.
 * @return string[] Locales to try, most specific first.
 */
function webfiable_locales_to_try( $locale ) {
	$locale  = (string) $locale;
	$locales = array( $locale );

	if ( 'es' !== substr( $locale, 0, 2 ) && 'en_US' !== $locale ) {
		$locales[] = 'en_US';
	}

	return $locales;
}

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

	// Exact match first, then the bundled English translation (never for Spanish).
	foreach ( webfiable_locales_to_try( $locale ) as $try_locale ) {
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

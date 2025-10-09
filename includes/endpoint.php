<?php
/**
 * Public endpoint controller for /webfiable.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Handles the /webfiable public endpoint on template_redirect.
 *
 * Gathers plugins/themes and returns an encrypted JSON payload. Sends headers,
 * rate-limits by IP, and exits after output.
 *
 * @since 1.4
 * @hook  template_redirect
 * @return void
 */
function webfiable_template_redirect() {
	if ( get_query_var( 'webfiable_route' ) ) {

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'Expires: 0', true );

		if ( ! extension_loaded( 'openssl' ) ) {
			status_header( 500 );
			wp_send_json( array( 'error' => 'openssl_missing' ) );
		}

		$enabled     = webfiable_get_option( 'webfiable_endpoint_enabled' ) === 'yes';
		$admin_email = webfiable_get_option( 'webfiable_admin_email' );
		$consent_ts  = (int) webfiable_get_option( 'webfiable_consent_ts' );

		if ( ! $enabled ) {
			status_header( 403 );
			wp_send_json( array( 'error' => 'endpoint_disabled' ) );
		}
		if ( empty( $admin_email ) || ! is_email( $admin_email ) || $consent_ts <= 0 ) {
			status_header( 403 );
			wp_send_json( array( 'error' => 'consent_or_email_missing' ) );
		}

		// Use sanitized client IP for a simple rate limit key.
		$raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip     = ( function_exists( 'rest_is_ip_address' ) && rest_is_ip_address( $raw_ip ) ) ? $raw_ip : '0.0.0.0';

		// 1–2 requests per minute per IP.
		$key   = 'webfiable_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 2 ) {
			status_header( 429 );
			wp_send_json( array( 'error' => 'rate_limited' ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		// Gather plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed_plugins = get_plugins();
		$plugins_info      = array();

		foreach ( $installed_plugins as $plugin_slug => $plugin_data ) {
			$folder_or_dot = dirname( $plugin_slug );
			$slug          = ( '.' === $folder_or_dot ) ? basename( $plugin_slug, '.php' ) : $folder_or_dot;

			$plugins_info[] = array(
				'name'        => $plugin_data['Name'],
				'slug'        => $slug,
				'version'     => $plugin_data['Version'],
				'description' => wp_strip_all_tags( $plugin_data['Description'] ),
			);
		}

		// Gather themes.
		$installed_themes = wp_get_themes();
		$themes_info      = array();
		foreach ( $installed_themes as $theme_slug => $theme_data ) {
			$themes_info[] = array(
				'name'        => $theme_data->get( 'Name' ),
				'slug'        => $theme_data->get_stylesheet(),
				'version'     => $theme_data->get( 'Version' ),
				'description' => wp_strip_all_tags( $theme_data->get( 'Description' ) ),
			);
		}

		$payload = array(
			'site_url'       => site_url(),
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'plugin_version' => WEBFIABLE_INFO_VERSION,
			'site_id'        => webfiable_get_option( 'webfiable_site_id' ),
			'admin_email'    => $admin_email,
			'consent_ts'     => $consent_ts,
			'ts'             => time(),
			'plugins'        => $plugins_info,
			'themes'         => $themes_info,
		);

		$json_data = wp_json_encode( $payload );

		$aes_key = openssl_random_pseudo_bytes( 32 );
		$iv      = openssl_random_pseudo_bytes( 16 );

		$encrypted_data = openssl_encrypt( $json_data, 'AES-256-CBC', $aes_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $encrypted_data ) {
			status_header( 500 );
			wp_send_json( array( 'error' => 'aes_encrypt_failed' ) );
		}

		$encrypted_key = null;
		$ok            = openssl_public_encrypt( $aes_key, $encrypted_key, WEBFIABLE_RSA_PUBLIC_KEY );
		if ( ! $ok ) {
			status_header( 500 );
			wp_send_json( array( 'error' => 'rsa_encrypt_failed' ) );
		}

		// Encode binary blobs for JSON transport (benign use, not obfuscation).
		$encoded_key  = base64_encode( $encrypted_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport-encoding binary data for JSON.
		$encoded_iv   = base64_encode( $iv );            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport-encoding binary data for JSON.
		$encoded_data = base64_encode( $encrypted_data );// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport-encoding binary data for JSON.

		wp_send_json(
			array(
				'encrypted_key' => $encoded_key,
				'iv'            => $encoded_iv,
				'data'          => $encoded_data,
			)
		);
		exit;
	}
}
add_action( 'template_redirect', 'webfiable_template_redirect' );

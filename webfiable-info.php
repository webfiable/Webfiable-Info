<?php
/**
 * Plugin Name: Webfiable Info
 * Plugin URI: https://webfiable.com/webfiable-info
 * Description: Ensure your website's security posture and configuration health with monitoring and recommendations.
 * Version: 1.4.1
 * Author: Webfiable Team
 * Author URI: https://webfiable.com
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: webfiable-info
 *
 * @package Webfiable_Info
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// RSA public key (provided by the user).
define(
	'WEBFIABLE_RSA_PUBLIC_KEY',
	'-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAw8y6jWyyz5yJzdj1kdDJ
KDU54+MryJYTBHogyq8m+557Q8gciul2cAZexdhC6EkIzI/hxwNi/t6fcLiK0hdC
88nVaP6B/xkZPuURW/cjtKbCBXo0CLTMNnJSxhECI4Xq5l5koiThdhSvDlqsuMWy
xCUUlbvU9Vg+MmiaEiRtZT7Nd5/NSqftqqdiVH0Q6sUd2OEFYPwnDI5615ALLH+h
XeaQhTu053Tpqcw6cMNbqOCc9Gk6esoM69oNHtXR2tKxxzWldwb0+mRRypUiPLUn
/n/9w5jnPrNsYGu1PVLXb+wlspPyZCSItq4zkzkFPYKvQ7u+U2UY28dHqSeHJhGd
FQIDAQAB
-----END PUBLIC KEY-----'
);

/**
 * Registers the custom rewrite rule for the `webfiable` endpoint.
 *
 * Hooked to `init`.
 *
 * @since 1.4
 * @return void
 */
function webfiable_register_route() {
	add_rewrite_rule( '^webfiable$', 'index.php?webfiable_route=1', 'top' );
}
add_action( 'init', 'webfiable_register_route' );

/**
 * Adds the `webfiable_route` query var so WordPress recognizes the endpoint.
 *
 * Hooked to `query_vars`.
 *
 * @since 1.4
 * @param string[] $vars List of public query vars.
 * @return string[] Modified list of query vars.
 */
function webfiable_add_query_vars( $vars ) {
	$vars[] = 'webfiable_route';
	return $vars;
}
add_filter( 'query_vars', 'webfiable_add_query_vars' );

/**
 * Handles the request to the `webfiable` endpoint and outputs an encrypted JSON payload.
 *
 * Hooked to `template_redirect`.
 *
 * Collects WP version, installed plugins and themes, builds a payload, encrypts it
 * with a random AES-256-CBC key/IV, encrypts that key with the RSA public key,
 * and returns base64-encoded values.
 *
 * @since 1.4
 * @return void
 */
function webfiable_template_redirect() {
	if ( get_query_var( 'webfiable_route' ) ) {

		// Get all installed plugins.
		$installed_plugins = get_plugins();
		$plugins_info      = array();

		foreach ( $installed_plugins as $plugin_slug => $plugin_data ) {
			$plugins_info[] = array(
				'name'        => $plugin_data['Name'],
				'slug'        => dirname( $plugin_slug ),
				'version'     => $plugin_data['Version'],
				'description' => wp_strip_all_tags( $plugin_data['Description'] ),  // Remove HTML tags from description.
			);
		}

		// Get all installed themes.
		$installed_themes = wp_get_themes();
		$themes_info      = array();

		foreach ( $installed_themes as $theme_slug => $theme_data ) {
			$themes_info[] = array(
				'name'        => $theme_data->get( 'Name' ),
				'slug'        => $theme_data->get_stylesheet(),
				'version'     => $theme_data->get( 'Version' ),
				'description' => wp_strip_all_tags( $theme_data->get( 'Description' ) ),  // Remove HTML tags from description.
			);
		}

		// Get the WordPress version.
		$wordpress_version = get_bloginfo( 'version' );

		// Merge all info into one array.
		$all_info = array(
			'wordpress_version' => $wordpress_version,
			'plugins'           => $plugins_info,
			'themes'            => $themes_info,
		);

		// Convert to JSON.
		$json_data = wp_json_encode( $all_info );

		// Generate a 256-bit AES key.
		$aes_key = openssl_random_pseudo_bytes( 32 );

		// Encrypt the JSON data with the AES key.
		$encrypted_data = openssl_encrypt( $json_data, 'AES-256-CBC', $aes_key, OPENSSL_RAW_DATA, $iv = openssl_random_pseudo_bytes( 16 ) );

		// Encrypt the AES key with the RSA public key.
		openssl_public_encrypt( $aes_key, $encrypted_key, WEBFIABLE_RSA_PUBLIC_KEY );

		// Return both the encrypted AES key and the encrypted JSON data.
		// We base64-encode binary values to transport them safely in JSON.
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport encoding, not obfuscation.
		$encoded_key  = base64_encode( $encrypted_key );
		$encoded_iv   = base64_encode( $iv );
		$encoded_data = base64_encode( $encrypted_data );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$response = array(
			'encrypted_key' => $encoded_key,
			'iv'            => $encoded_iv,
			'data'          => $encoded_data,
		);

		// Send JSON response.
		wp_send_json( $response );
		exit;
	}
}
add_action( 'template_redirect', 'webfiable_template_redirect' );

/**
 * Flushes rewrite rules on plugin activation so the custom endpoint works immediately.
 *
 * Calls our route registrar and then flushes the rules. This should only run on activation,
 * never on every request (for performance reasons).
 *
 * Hooked via `register_activation_hook()`.
 *
 * @since 1.4
 * @return void
 */
function webfiable_flush_rewrite_rules() {
	webfiable_register_route();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'webfiable_flush_rewrite_rules' );

/**
 * Flushes rewrite rules on plugin deactivation to remove the custom endpoint.
 *
 * Hooked via `register_deactivation_hook()`.
 *
 * @since 1.4
 * @return void
 */
function webfiable_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'webfiable_deactivate' );

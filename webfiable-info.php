<?php
/**
 * Plugin Name: Webfiable Info
 * Plugin URI: https://webfiable.com/webfiable-info
 * Description: Ensure your website's security posture and configuration health with monitoring and recommendations.
 * Version: 1.4
 * Author: Webfiable Team
 * Author URI: https://webfiable.com
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: webfiable-info
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// RSA public key (provided by the user)
define('WEBFIABLE_RSA_PUBLIC_KEY', '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAw8y6jWyyz5yJzdj1kdDJ
KDU54+MryJYTBHogyq8m+557Q8gciul2cAZexdhC6EkIzI/hxwNi/t6fcLiK0hdC
88nVaP6B/xkZPuURW/cjtKbCBXo0CLTMNnJSxhECI4Xq5l5koiThdhSvDlqsuMWy
xCUUlbvU9Vg+MmiaEiRtZT7Nd5/NSqftqqdiVH0Q6sUd2OEFYPwnDI5615ALLH+h
XeaQhTu053Tpqcw6cMNbqOCc9Gk6esoM69oNHtXR2tKxxzWldwb0+mRRypUiPLUn
/n/9w5jnPrNsYGu1PVLXb+wlspPyZCSItq4zkzkFPYKvQ7u+U2UY28dHqSeHJhGd
FQIDAQAB
-----END PUBLIC KEY-----');

// Register the custom endpoint
function webfiable_register_route() {
    add_rewrite_rule('^webfiable$', 'index.php?webfiable_route=1', 'top');
}
add_action('init', 'webfiable_register_route');

// Add the query var so WP recognizes the custom route
function webfiable_add_query_vars($vars) {
    $vars[] = 'webfiable_route';
    return $vars;
}
add_filter('query_vars', 'webfiable_add_query_vars');

// Handle the request and return encrypted JSON response
function webfiable_template_redirect() {
    if (get_query_var('webfiable_route')) {

        // Get all installed plugins
        $installed_plugins = get_plugins();
        $plugins_info = [];

        foreach ($installed_plugins as $plugin_slug => $plugin_data) {
            $plugins_info[] = [
                'name'        => $plugin_data['Name'],
                'slug'        => dirname($plugin_slug),
                'version'     => $plugin_data['Version'],
                'description' => wp_strip_all_tags($plugin_data['Description']),  // Remove HTML tags from description
            ];
        }

        // Get all installed themes
        $installed_themes = wp_get_themes();
        $themes_info = [];

        foreach ($installed_themes as $theme_slug => $theme_data) {
            $themes_info[] = [
                'name'        => $theme_data->get('Name'),
                'slug'        => $theme_data->get_stylesheet(),
                'version'     => $theme_data->get('Version'),
                'description' => wp_strip_all_tags($theme_data->get('Description')),  // Remove HTML tags from description
            ];
        }

        // Get the WordPress version
        $wordpress_version = get_bloginfo('version');

        // Merge all info into one array
        $all_info = [
            'wordpress_version' => $wordpress_version,
            'plugins'           => $plugins_info,
            'themes'            => $themes_info,
        ];

        // Convert to JSON
        $json_data = json_encode($all_info);

        // Generate a 256-bit AES key
        $aes_key = openssl_random_pseudo_bytes(32);

        // Encrypt the JSON data with the AES key
        $encrypted_data = openssl_encrypt($json_data, 'AES-256-CBC', $aes_key, OPENSSL_RAW_DATA, $iv = openssl_random_pseudo_bytes(16));

        // Encrypt the AES key with the RSA public key
        openssl_public_encrypt($aes_key, $encrypted_key, WEBFIABLE_RSA_PUBLIC_KEY);

        // Return both the encrypted AES key and the encrypted JSON data
        $response = [
            'encrypted_key' => base64_encode($encrypted_key),
            'iv'            => base64_encode($iv),
            'data'          => base64_encode($encrypted_data),
        ];

        // Send JSON response
        wp_send_json($response);
        exit;
    }
}
add_action('template_redirect', 'webfiable_template_redirect');

// Ensure that the rewrite rules are flushed after the plugin is activated
function webfiable_flush_rewrite_rules() {
    webfiable_register_route();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'webfiable_flush_rewrite_rules');

// Flush rewrite rules on deactivation to avoid conflicts
function webfiable_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'webfiable_deactivate');
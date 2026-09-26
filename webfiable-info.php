<?php
/**
 * Plugin Name: Webfiable Análisis de Sitios
 * Plugin URI: https://wordpress.org/plugins/webfiable-info/
 * Description: Conecta tu WordPress con el panel de Análisis de Sitios de Webfiable para analizar su seguridad y su configuración.
 * Version: 2.2.0
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * Author: Webfiable
 * Author URI: https://webfiable.com
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: webfiable-info
 * Domain Path: /languages
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Paths */
define( 'WEBFIABLE_PLUGIN_FILE', __FILE__ );
define( 'WEBFIABLE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WEBFIABLE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEBFIABLE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** Load modules (order matters: constants → i18n → options → admin/routing/endpoint) */
require_once WEBFIABLE_PLUGIN_DIR . 'includes/constants.php';
require_once WEBFIABLE_PLUGIN_DIR . 'includes/i18n.php';
require_once WEBFIABLE_PLUGIN_DIR . 'includes/options.php';
require_once WEBFIABLE_PLUGIN_DIR . 'includes/logger.php';
require_once WEBFIABLE_PLUGIN_DIR . 'includes/admin.php';
require_once WEBFIABLE_PLUGIN_DIR . 'includes/routing.php';
require_once WEBFIABLE_PLUGIN_DIR . 'includes/endpoint.php';
require_once WEBFIABLE_PLUGIN_DIR . 'includes/registration.php';
require_once WEBFIABLE_PLUGIN_DIR . 'includes/update.php';

/** Register activation/deactivation hooks provided by routing.php */
register_activation_hook( __FILE__, 'webfiable_activate' );
register_deactivation_hook( __FILE__, 'webfiable_deactivate' );

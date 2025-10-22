<?php
/**
 * Public query var and rewrite rule registration, activation/deactivation hooks.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Register rewrite rule for /webfiable.
 *
 * @return void
 */
function webfiable_register_route() {
	add_rewrite_rule( '^' . WEBFIABLE_ENDPOINT_SLUG . '$', 'index.php?webfiable_route=1', 'top' );
}
add_action( 'init', 'webfiable_register_route' );

/**
 * Add the webfiable_route query var.
 *
 * @param string[] $vars Existing public query vars.
 * @return string[] Modified public query vars.
 */
function webfiable_add_query_vars( $vars ) {
	$vars[] = 'webfiable_route';
	return $vars;
}
add_filter( 'query_vars', 'webfiable_add_query_vars' );

/**
 * Activation: ensure site_id exists and flush rewrite rules.
 *
 * @return void
 */
function webfiable_activate() {
	if ( ! webfiable_get_option( 'webfiable_site_id' ) ) {
		$uuid = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wf_', true );
		webfiable_update_option( 'webfiable_site_id', $uuid );
	}
	webfiable_register_route();
	flush_rewrite_rules();
	webfiable_log_action(
		'plugin_activated',
		array(
			'site_id' => webfiable_get_option( 'webfiable_site_id' ),
		)
	);
}

/**
 * Deactivation: flush rewrite rules.
 *
 * @return void
 */
function webfiable_deactivate() {
	flush_rewrite_rules();
	webfiable_log_action( 'plugin_deactivated' );
}

<?php
/**
 * Public query var and rewrite rule registration, activation/deactivation hooks.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * The rewrite rule's regex for /webfiable.
 *
 * Accepts both /webfiable and /webfiable/ to avoid 404s on trailing-slash sites.
 *
 * @return string
 */
function webfiable_route_regex() {
	return '^' . WEBFIABLE_ENDPOINT_SLUG . '/?$';
}

/**
 * Register rewrite rule for /webfiable.
 *
 * @return void
 */
function webfiable_register_route() {
	add_rewrite_rule( webfiable_route_regex(), 'index.php?webfiable_route=1', 'top' );
}
add_action( 'init', 'webfiable_register_route' );

/**
 * Take the /webfiable rule out of this request's rewrite rules.
 *
 * On deactivation the plugin is still loaded and its rule was added on init,
 * so a flush would write it back; this removes it first.
 *
 * @return void
 */
function webfiable_unregister_route() {
	global $wp_rewrite;
	remove_action( 'init', 'webfiable_register_route' );
	if ( is_object( $wp_rewrite ) && isset( $wp_rewrite->extra_rules_top ) && is_array( $wp_rewrite->extra_rules_top ) ) {
		unset( $wp_rewrite->extra_rules_top[ webfiable_route_regex() ] );
	}
}

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
 * Deactivation: remove the /webfiable rule, flush rewrite rules and drop a
 * pending registration after update.
 *
 * @return void
 */
function webfiable_deactivate() {
	webfiable_unregister_route();
	flush_rewrite_rules();
	wp_clear_scheduled_hook( WEBFIABLE_UPDATE_REGISTRATION_HOOK );
	webfiable_log_action( 'plugin_deactivated' );
}

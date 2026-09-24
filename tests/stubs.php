<?php
/**
 * The few WordPress functions the plugin's units touch, for tests/run.php.
 *
 * An in-memory option store and a call recorder; no WordPress, no database.
 * Never shipped (tests/ is in .distignore) and not scanned by phpcs.
 *
 * @package Webfiable_Info
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WEBFIABLE_PLUGIN_FILE', dirname( __DIR__ ) . '/webfiable-info.php' );
define( 'WEBFIABLE_PLUGIN_BASENAME', 'webfiable-info/webfiable-info.php' );
define( 'WEBFIABLE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WEBFIABLE_PLUGIN_URL', 'https://example.test/wp-content/plugins/webfiable-info/' );

$GLOBALS['wf_test_options'] = array();
$GLOBALS['wf_test_calls']   = array();

/** Reset the option store and the call recorder between cases. */
function wf_test_reset() {
	$GLOBALS['wf_test_options'] = array();
	$GLOBALS['wf_test_calls']   = array();
}

function wf_test_record( $name, $args ) {
	$GLOBALS['wf_test_calls'][] = array( $name, $args );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['wf_test_options'] ) ? $GLOBALS['wf_test_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['wf_test_options'][ $key ] = $value;
	wf_test_record( 'update_option', array( $key, $value, $autoload ) );
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['wf_test_options'][ $key ] );
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	wf_test_record( 'add_action', array( $hook, $callback ) );
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	wf_test_record( 'add_filter', array( $hook, $callback ) );
	return true;
}

function is_email( $email ) {
	return ( is_string( $email ) && false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ) ? $email : false;
}

function home_url( $path = '' ) {
	return 'https://example.test/' . ltrim( $path, '/' );
}

function untrailingslashit( $value ) {
	return rtrim( $value, '/\\' );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return (string) $url;
}

function wp_kses( $html, $allowed ) {
	return (string) $html;
}

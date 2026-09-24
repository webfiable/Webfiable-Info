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
	$GLOBALS['wf_test_options']       = array();
	$GLOBALS['wf_test_calls']         = array();
	$GLOBALS['wf_test_http']          = array();
	$GLOBALS['wf_test_http_response'] = null;
	$GLOBALS['wf_test_cron']          = array();
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
	wf_test_record( '__', array( $text ) );
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return esc_html( $text );
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

// ------------------------------------------------------------ HTTP (the registration call)
// wp_remote_post records every request and answers with the response the case
// sets in $GLOBALS['wf_test_http_response'] (an array, or a WP_Error).
$GLOBALS['wf_test_http']          = array();
$GLOBALS['wf_test_http_response'] = null;

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/** A response of the shape wp_remote_post returns. */
function wf_test_http_answer( $code, $body ) {
	$GLOBALS['wf_test_http_response'] = array(
		'response' => array( 'code' => $code ),
		'body'     => $body,
		'headers'  => array(),
	);
}

function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['wf_test_http'][] = array( 'POST', $url, $args );
	wf_test_record( 'wp_remote_post', array( $url ) );
	return $GLOBALS['wf_test_http_response'];
}

function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) ? $response['response']['code'] : '';
}

function wp_remote_retrieve_body( $response ) {
	return is_array( $response ) ? $response['body'] : '';
}

function wp_remote_retrieve_headers( $response ) {
	return is_array( $response ) ? $response['headers'] : array();
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

function trailingslashit( $value ) {
	return untrailingslashit( $value ) . '/';
}

function apply_filters( $hook, $value ) {
	return $value;
}

function maybe_serialize( $data ) {
	return is_array( $data ) || is_object( $data ) ? serialize( $data ) : $data;
}

// ------------------------------------------------------------ WP-Cron and rewrite rules
// An in-memory event list keyed by hook: wp_next_scheduled sees what
// wp_schedule_single_event queued, as WordPress's own cron option does.
$GLOBALS['wf_test_cron'] = array();

function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
	$GLOBALS['wf_test_cron'][ $hook ] = $timestamp;
	wf_test_record( 'wp_schedule_single_event', array( $timestamp, $hook ) );
	return true;
}

function wp_next_scheduled( $hook, $args = array() ) {
	return isset( $GLOBALS['wf_test_cron'][ $hook ] ) ? $GLOBALS['wf_test_cron'][ $hook ] : false;
}

function wp_clear_scheduled_hook( $hook, $args = array(), $wp_error = false ) {
	unset( $GLOBALS['wf_test_cron'][ $hook ] );
	wf_test_record( 'wp_clear_scheduled_hook', array( $hook ) );
	return 0;
}

function flush_rewrite_rules( $hard = true ) {
	wf_test_record( 'flush_rewrite_rules', array( $hard ) );
}

/** The recorded calls of one function, in order. */
function wf_test_calls_of( $name ) {
	$calls = array();
	foreach ( $GLOBALS['wf_test_calls'] as $call ) {
		if ( $name === $call[0] ) {
			$calls[] = $call[1];
		}
	}
	return $calls;
}

<?php
/**
 * Dependency-free unit tests for the plugin: php tests/run.php
 *
 * Runs on the PHP the CI jobs install (8.3 and 7.4) and locally through the
 * wordpress:cli image. Exit 1 on any failed assertion.
 *
 * @package Webfiable_Info
 */

require __DIR__ . '/stubs.php';
require WEBFIABLE_PLUGIN_DIR . 'includes/constants.php';
require WEBFIABLE_PLUGIN_DIR . 'includes/i18n.php';
require WEBFIABLE_PLUGIN_DIR . 'includes/options.php';
require WEBFIABLE_PLUGIN_DIR . 'includes/logger.php';
require WEBFIABLE_PLUGIN_DIR . 'includes/admin.php';
require WEBFIABLE_PLUGIN_DIR . 'includes/registration.php';

$GLOBALS['wf_test_count']    = 0;
$GLOBALS['wf_test_failures'] = array();

/**
 * Compare with ===; record a failure with its message.
 */
function assert_same( $expected, $actual, $message ) {
	++$GLOBALS['wf_test_count'];
	if ( $expected !== $actual ) {
		$GLOBALS['wf_test_failures'][] = $message . "\n      expected: " . var_export( $expected, true ) . "\n      actual:   " . var_export( $actual, true );
	}
}

// ------------------------------------------------------------ i18n (P8-18)
// The source strings are Spanish: Spanish locales need no file; every other
// locale tries its own file and then the bundled English translation.
assert_same( array( 'de_DE', 'en_US' ), webfiable_locales_to_try( 'de_DE' ), 'de_DE falls back to the English translation' );
assert_same( array( 'en_GB', 'en_US' ), webfiable_locales_to_try( 'en_GB' ), 'en_GB falls back to en_US' );
assert_same( array( 'en_US' ), webfiable_locales_to_try( 'en_US' ), 'en_US is tried once' );
assert_same( array( 'es_MX' ), webfiable_locales_to_try( 'es_MX' ), 'es_MX sees the Spanish source, no English fallback' );
assert_same( array( 'es_ES' ), webfiable_locales_to_try( 'es_ES' ), 'es_ES sees the Spanish source' );

// ------------------------------------------------------------ the panel card (DES-18 A)
// The card shows whenever consent, a valid email and the connection are on; the
// line that says «registrado» only when the registration stamp exists.
$wf_registered_line = 'Tu sitio está registrado en Análisis de Sitios de Webfiable. Puedes verlo en tu panel.';
$wf_login_line      = 'Para entrar, escribe <strong>dueno@example.com</strong> en la página de acceso: te enviaremos a ese correo un enlace de un solo uso, sin contraseña.';

/**
 * A configured site: consent, a valid email, the connection on, and the stamp.
 */
function wf_test_configured_site( $stamp ) {
	wf_test_reset();
	update_option( 'webfiable_consent_ts', 1700000000 );
	update_option( 'webfiable_admin_email', 'dueno@example.com' );
	update_option( 'webfiable_endpoint_enabled', 'yes' );
	if ( $stamp ) {
		update_option( 'webfiable_registered_ts', 1700000100 );
	}
}

wf_test_configured_site( true );
assert_same( true, webfiable_is_registered(), 'registered: stamp, consent, email and connection' );
assert_same( array( $wf_registered_line, $wf_login_line ), webfiable_panel_card_paragraphs(), 'registered: the card says «registrado» and how to sign in' );

wf_test_configured_site( true );
update_option( 'webfiable_consent_ts', 0 );
assert_same( array( false, false, array() ), array( webfiable_is_registered(), webfiable_panel_card_visible(), webfiable_panel_card_paragraphs() ), 'consent 0 with a stamp left by an old install: not registered, no card' );

wf_test_configured_site( true );
update_option( 'webfiable_endpoint_enabled', 'no' );
assert_same( array( false, false, array() ), array( webfiable_is_registered(), webfiable_panel_card_visible(), webfiable_panel_card_paragraphs() ), 'connection off: not registered, no card' );

wf_test_configured_site( true );
update_option( 'webfiable_admin_email', 'no-es-un-correo' );
assert_same( array( false, false, array() ), array( webfiable_is_registered(), webfiable_panel_card_visible(), webfiable_panel_card_paragraphs() ), 'invalid email: not registered, no card' );

wf_test_configured_site( false );
assert_same( array( false, true, array( $wf_login_line ) ), array( webfiable_is_registered(), webfiable_panel_card_visible(), webfiable_panel_card_paragraphs() ), 'configured with no stamp: card visible, no «registrado» line' );

// ------------------------------------------------------------ one registration path (T5-1)
// The settings save and the update both call webfiable_register_current_site().
// These cases pin the request it makes: where it goes, the three fields and their
// values, the JSON encoding, the 60 s timeout and the content type.

/**
 * A site as the settings save leaves it just before registering: the site id and
 * the lowercased email are stored, consent given, the connection on. WordPress's
 * own admin email is a different address on purpose.
 */
function wf_test_saved_site() {
	wf_test_reset();
	update_option( 'webfiable_site_id', '0b6f7a52-5d0c-4c47-9d1a-2f6c1f0e9a11' );
	update_option( 'webfiable_admin_email', 'dueno@example.com' );
	update_option( 'webfiable_consent_ts', 1700000000 );
	update_option( 'webfiable_endpoint_enabled', 'yes' );
	update_option( 'admin_email', 'wordpress-admin@example.org' );
}

/**
 * The options that are not the activity log (the logger always writes that one).
 */
function wf_test_options_without_log() {
	$options = $GLOBALS['wf_test_options'];
	unset( $options['webfiable_action_log'] );
	return $options;
}

/**
 * The identity of a recorded request (ENG-3): method, URL, sha256 of the raw
 * body, the sorted key list of the JSON body, the timeout and the content type.
 */
function wf_test_request_identity( $request ) {
	list( $method, $url, $args ) = $request;
	$keys = array_keys( (array) json_decode( $args['body'], true ) );
	sort( $keys );
	return array(
		'method'       => $method,
		'url'          => $url,
		'body_sha256'  => hash( 'sha256', $args['body'] ),
		'keys'         => $keys,
		'timeout'      => $args['timeout'],
		'content_type' => $args['headers']['Content-Type'],
	);
}

/**
 * The context of the last log entry with this action in this request, or null.
 */
function wf_test_last_log( $action ) {
	$found = null;
	foreach ( webfiable_get_action_log( 'request' ) as $entry ) {
		if ( $action === $entry['action'] ) {
			$found = $entry['context'];
		}
	}
	return $found;
}

$wf_expected_body = '{"siteId":"0b6f7a52-5d0c-4c47-9d1a-2f6c1f0e9a11","siteUrl":"https:\/\/example.test","adminEmail":"dueno@example.com"}';

wf_test_saved_site();
wf_test_http_answer( 200, 'true' );
webfiable_register_current_site( 'settings' );
$wf_request = isset( $GLOBALS['wf_test_http'][0] ) ? $GLOBALS['wf_test_http'][0] : array( '', '', array( 'body' => '', 'timeout' => null, 'headers' => array( 'Content-Type' => null ) ) );
assert_same( 1, count( $GLOBALS['wf_test_http'] ), 'registration: exactly one request' );
assert_same(
	array( 'POST', 'https://webfiable.com/wp-json/webfiable/v1/activations', $wf_expected_body, 60, 'application/json' ),
	array( $wf_request[0], $wf_request[1], $wf_request[2]['body'], $wf_request[2]['timeout'], $wf_request[2]['headers']['Content-Type'] ),
	'registration: POST to the activations route, the three fields as JSON, 60 s, application/json'
);
assert_same(
	array(
		'trigger'     => 'settings',
		'site_id'     => '0b6f7a52-5d0c-4c47-9d1a-2f6c1f0e9a11',
		'site_url'    => 'https://example.test',
		'admin_email' => 'dueno@example.com',
	),
	wf_test_last_log( 'registration_attempt_started' ),
	'registration: the wrapper passes the stored site id, home_url() without its trailing slash and the plugin email (not WordPress admin_email); the log carries the trigger'
);
assert_same( true, (int) get_option( 'webfiable_registered_ts', 0 ) > 0, 'registration success («true»): the stamp is written' );

wf_test_saved_site();
wf_test_http_answer( 200, 'true' );
$wf_before = wf_test_options_without_log();
webfiable_register_current_site( 'update' );
$wf_after = wf_test_options_without_log();
unset( $wf_after['webfiable_registered_ts'] );
assert_same( $wf_before, $wf_after, 'registration success: no option other than the stamp changes' );
assert_same( 'update', wf_test_last_log( 'registration_attempt_started' )['trigger'], 'registration: the log entry carries the update trigger' );

wf_test_saved_site();
wf_test_http_answer( 200, '{"ok":true}' );
$wf_before = wf_test_options_without_log();
$wf_result = webfiable_register_current_site( 'settings' );
assert_same( array( false, $wf_before ), array( $wf_result['success'], wf_test_options_without_log() ), 'registration answered with something other than literal true: failure, no option written (no stamp)' );

wf_test_saved_site();
$GLOBALS['wf_test_http_response'] = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
$wf_before = wf_test_options_without_log();
$wf_result = webfiable_register_current_site( 'settings' );
assert_same( array( false, $wf_before ), array( $wf_result['success'], wf_test_options_without_log() ), 'registration HTTP error: failure, no option written (no stamp)' );

// ------------------------------------------------------------ result
$failures = $GLOBALS['wf_test_failures'];
foreach ( $failures as $failure ) {
	echo 'FAIL ' . $failure . "\n";
}
echo 'tests/run.php: ' . $GLOBALS['wf_test_count'] . ' assertions, ' . count( $failures ) . " failed\n";
exit( $failures ? 1 : 0 );

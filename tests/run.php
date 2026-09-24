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
require WEBFIABLE_PLUGIN_DIR . 'includes/i18n.php';
require WEBFIABLE_PLUGIN_DIR . 'includes/options.php';
require WEBFIABLE_PLUGIN_DIR . 'includes/admin.php';

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

// ------------------------------------------------------------ result
$failures = $GLOBALS['wf_test_failures'];
foreach ( $failures as $failure ) {
	echo 'FAIL ' . $failure . "\n";
}
echo 'tests/run.php: ' . $GLOBALS['wf_test_count'] . ' assertions, ' . count( $failures ) . " failed\n";
exit( $failures ? 1 : 0 );

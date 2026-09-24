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

// ------------------------------------------------------------ result
$failures = $GLOBALS['wf_test_failures'];
foreach ( $failures as $failure ) {
	echo 'FAIL ' . $failure . "\n";
}
echo 'tests/run.php: ' . $GLOBALS['wf_test_count'] . ' assertions, ' . count( $failures ) . " failed\n";
exit( $failures ? 1 : 0 );

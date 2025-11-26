<?php
/**
 * Simple action logger to capture plugin activity for debugging.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalise context values for storage.
 *
 * @param mixed $value Context value.
 * @return mixed Normalised value safe to encode/store.
 */
function webfiable_logger_normalize_value( $value ) {
	if ( null === $value || is_bool( $value ) || is_numeric( $value ) || is_string( $value ) ) {
		return $value;
	}

	if ( is_object( $value ) ) {
		if ( $value instanceof WP_Error ) {
			return array(
				'error_code'    => $value->get_error_code(),
				'error_message' => $value->get_error_message(),
				'error_data'    => $value->get_error_data(),
			);
		}

		if ( method_exists( $value, 'to_array' ) ) {
			return webfiable_logger_normalize_value( $value->to_array() );
		}

		return webfiable_logger_normalize_value( (array) $value );
	}

	if ( is_array( $value ) ) {
		$normalised = array();
		foreach ( $value as $sub_key => $sub_value ) {
			$normalised[ (string) $sub_key ] = webfiable_logger_normalize_value( $sub_value );
		}
		return $normalised;
	}

	if ( is_resource( $value ) ) {
		return get_resource_type( $value );
	}

	return maybe_serialize( $value );
}

/**
 * Store and optionally persist an action log entry.
 *
 * @param string               $action  Short description of the action.
 * @param array<string, mixed> $context Extra context data.
 * @param string               $level   Log level (debug|info|warning|error).
 * @return array<string, mixed> The logged entry.
 */
function webfiable_log_action( $action, $context = array(), $level = 'info' ) {
	static $request_log   = array();
	static $persisted_log = null;

	$level  = strtolower( (string) $level );
	$levels = array( 'debug', 'info', 'warning', 'error' );
	if ( ! in_array( $level, $levels, true ) ) {
		$level = 'info';
	}

	$entry = array(
		'timestamp' => time(),
		'level'     => $level,
		'action'    => (string) $action,
		'context'   => webfiable_logger_normalize_value( $context ),
	);

	$request_log[]                    = $entry;
	$GLOBALS['webfiable_request_log'] = $request_log;

	if ( null === $persisted_log ) {
		$stored        = get_option( 'webfiable_action_log', array() );
		$persisted_log = is_array( $stored ) ? $stored : array();
	}

	$persisted_log[]                     = $entry;
	$GLOBALS['webfiable_persistent_log'] = $persisted_log;

	$max_entries = (int) apply_filters( 'webfiable_action_log_max_entries', 100 );
	if ( $max_entries > 0 && count( $persisted_log ) > $max_entries ) {
		$persisted_log = array_slice( $persisted_log, -1 * $max_entries );
	}

	update_option( 'webfiable_action_log', $persisted_log, false );

	return $entry;
}

/**
 * Retrieve logged entries.
 *
 * @param string $scope request for current-request entries, persistent for stored entries.
 * @return array<int, array<string, mixed>>
 */
function webfiable_get_action_log( $scope = 'persistent' ) {
	if ( 'request' === $scope ) {
		return ( isset( $GLOBALS['webfiable_request_log'] ) && is_array( $GLOBALS['webfiable_request_log'] ) )
			? $GLOBALS['webfiable_request_log']
			: array();
	}

	if ( isset( $GLOBALS['webfiable_persistent_log'] ) && is_array( $GLOBALS['webfiable_persistent_log'] ) ) {
		return $GLOBALS['webfiable_persistent_log'];
	}

	$stored = get_option( 'webfiable_action_log', array() );
	return is_array( $stored ) ? $stored : array();
}

/**
 * Remove all persisted log entries.
 *
 * @return void
 */
function webfiable_clear_action_log() {
	delete_option( 'webfiable_action_log' );
	$GLOBALS['webfiable_request_log']    = array();
	$GLOBALS['webfiable_persistent_log'] = array();
}

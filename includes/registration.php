<?php
/**
 * Registration helper (proxy call).
 *
 * @package Webfiable_Info
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attempt registration via the Webfiable proxy.
 *
 * @param string $site_id     Existing site ID (UUID).
 * @param string $site_url    Public site URL (e.g., home_url()).
 * @param string $admin_email Admin email to register.
 * @param string $proxy_base  Proxy base URL (default: https://webfiable.com).
 * @return array{
 *     success:bool,
 *     endpoint:string,
 *     payload:array<string,string>,
 *     http_code:int|null,
 *     response_body:string|null,
 *     error:string|null
 * } Registration attempt metadata.
 */
function webfiable_attempt_registration( $site_id, $site_url, $admin_email, $proxy_base = 'https://webfiable.com' ) {
	if ( '' === $site_id || '' === $site_url || '' === $admin_email ) {
		return array(
			'success'       => false,
			'endpoint'      => '',
			'payload'       => array(),
			'http_code'     => null,
			'response_body' => null,
			'error'         => __( 'Missing registration parameters.', 'webfiable-info' ),
		);
	}

	$site_url    = untrailingslashit( (string) $site_url );
	$admin_email = strtolower( (string) $admin_email );

	$endpoint = trailingslashit( untrailingslashit( $proxy_base ) ) . 'wp-json/webfiable/v1/activations';

	$payload = array(
		'siteId'     => (string) $site_id,
		'siteUrl'    => $site_url,
		'adminEmail' => $admin_email,
	);

	$args = array(
		'timeout' => 15,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( $payload ),
	);

	$result = array(
		'success'       => false,
		'endpoint'      => $endpoint,
		'payload'       => $payload,
		'http_code'     => null,
		'response_body' => null,
		'error'         => null,
	);

	$response = wp_remote_post( $endpoint, $args );

	if ( is_wp_error( $response ) ) {
		$result['error'] = $response->get_error_message();

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Webfiable] proxy error: ' . $result['error'] );
		}

		return $result;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	$result['http_code']     = $code;
	$result['response_body'] = (string) $body;

	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( '[Webfiable] proxy reply: code=' . $code . ' body=' . trim( (string) $body ) );
	}

	// Accept JSON true, or a raw "true" (with/without newline/quotes).
	$json    = json_decode( $body, true );
	$trimmed = strtolower( trim( (string) $body ) );
	$success = ( true === $json ) || ( 'true' === $trimmed );

	if ( $success ) {
		$result['success'] = true;
		return $result;
	}

	$result['error'] = __( 'Unexpected API response.', 'webfiable-info' );

	return $result;
}

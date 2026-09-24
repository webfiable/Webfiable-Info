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
		webfiable_log_action(
			'registration_missing_parameters',
			array(
				'site_id'     => $site_id,
				'site_url'    => $site_url,
				'admin_email' => $admin_email,
			),
			'error'
		);
		return array(
			'success'       => false,
			'endpoint'      => '',
			'payload'       => array(),
			'http_code'     => null,
			'response_body' => null,
			'error'         => __( 'Falta información obligatoria. Rellena todos los campos y vuelve a intentarlo.', 'webfiable-info' ),
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
		'timeout' => 60,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( $payload ),
	);

	webfiable_log_action(
		'registration_request',
		array(
			'endpoint' => $endpoint,
			'payload'  => $payload,
			'args'     => $args,
		)
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
		webfiable_log_action(
			'registration_http_error',
			array(
				'endpoint' => $endpoint,
				'error'    => $response->get_error_message(),
				'data'     => $response->get_error_data(),
			),
			'error'
		);
		return $result;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	$result['http_code']     = $code;
	$result['response_body'] = (string) $body;

	// Accept JSON true, or a raw "true" (with/without newline/quotes).
	$json    = json_decode( $body, true );
	$trimmed = strtolower( trim( (string) $body ) );
	$success = ( true === $json ) || ( 'true' === $trimmed );

	$headers = wp_remote_retrieve_headers( $response );
	if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
		$headers = $headers->getAll();
	}

	webfiable_log_action(
		'registration_response',
		array(
			'endpoint'          => $endpoint,
			'http_code'         => $code,
			'body'              => $result['response_body'],
			'headers'           => $headers,
			'decoded_body'      => $json,
			'interpreted_match' => $success,
		)
	);

	if ( $success ) {
		$result['success'] = true;
		webfiable_log_action(
			'registration_success',
			array(
				'endpoint'  => $endpoint,
				'http_code' => $code,
			),
			'info'
		);
		return $result;
	}

	$result['error'] = __( 'Webfiable ha devuelto una respuesta inesperada. Vuelve a intentarlo más tarde.', 'webfiable-info' );
	webfiable_log_action(
		'registration_unexpected_response',
		array(
			'endpoint'  => $endpoint,
			'http_code' => $code,
			'body'      => $result['response_body'],
		),
		'warning'
	);

	return $result;
}

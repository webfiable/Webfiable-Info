<?php
/**
 * Admin UI: menus, settings page, notices, and plugin row links.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Add "Settings" action link in the Plugins list row.
 *
 * @param string[] $links Existing action links.
 * @return string[] Modified links with Settings at the beginning.
 */
function webfiable_plugin_action_links( $links ) {
	$url   = admin_url( 'options-general.php?page=webfiable-info' );
	$label = esc_html__( 'Settings', 'webfiable-info' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . $label . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . WEBFIABLE_PLUGIN_BASENAME, 'webfiable_plugin_action_links' );

/**
 * Extra meta links under the plugin name.
 *
 * @param string[] $links Existing links.
 * @param string   $file  Plugin basename being rendered.
 * @return string[] Modified links.
 */
function webfiable_plugin_row_meta( $links, $file ) {
	if ( WEBFIABLE_PLUGIN_BASENAME !== $file ) {
		return $links;
	}
	$links[] = '<a href="' . esc_url( 'https://webfiable.com/politica-privacidad/' ) . '" target="_blank" rel="noopener">' .
		esc_html__( 'Privacy', 'webfiable-info' ) . '</a>';
	return $links;
}
add_filter( 'plugin_row_meta', 'webfiable_plugin_row_meta', 10, 2 );

/**
 * OpenSSL missing notice.
 */
function webfiable_admin_notice_openssl() {
	if ( extension_loaded( 'openssl' ) ) {
		return;
	}
	if ( is_network_admin() ) {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return; }
	} elseif ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-error is-dismissible"><p>';
	esc_html_e( 'Webfiable Info requires the PHP OpenSSL extension. Please enable it on the server.', 'webfiable-info' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'webfiable_admin_notice_openssl' );
add_action( 'network_admin_notices', 'webfiable_admin_notice_openssl' );

/**
 * Incomplete setup notice (site-admin only).
 */
function webfiable_admin_notice_incomplete_setup() {
	if ( is_network_admin() || is_user_admin() ) {
		return; }
	if ( ! current_user_can( 'manage_options' ) ) {
		return; }

	$email      = webfiable_get_option( 'webfiable_admin_email' );
	$consent_ts = (int) webfiable_get_option( 'webfiable_consent_ts' );
	$enabled    = ( webfiable_get_option( 'webfiable_endpoint_enabled' ) === 'yes' );

	$issues = array();
	if ( empty( $email ) || ! is_email( $email ) ) {
		$issues[] = __( 'Add a valid report recipient email.', 'webfiable-info' );
	}
	if ( $consent_ts <= 0 ) {
		$issues[] = __( 'Grant consent to send the site inventory and email to Webfiable.', 'webfiable-info' );
	}
	if ( ! $enabled ) {
		$issues[] = __( 'Enable the public /webfiable endpoint.', 'webfiable-info' );
	}
	if ( empty( $issues ) ) {
		return; }

	$settings_url = admin_url( 'options-general.php?page=webfiable-info' );
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Webfiable Info is not fully configured.', 'webfiable-info' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Webfiable Info settings page. */
					__( 'Please complete the setup in <a href="%s">Settings → Webfiable Info</a>.', 'webfiable-info' ),
					esc_url( $settings_url )
				),
				array( 'a' => array( 'href' => true ) )
			);
			?>
		</p>
		<ul>
			<?php foreach ( $issues as $msg ) : ?>
				<li><?php echo esc_html( $msg ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}
add_action( 'admin_notices', 'webfiable_admin_notice_incomplete_setup' );

// Settings page registration.
if ( ! function_exists( 'webfiable_admin_menu' ) ) {
	/**
	 * Registers the Webfiable Info settings page under Settings.
	 *
	 * Adds an options page visible to users with the `manage_options` capability.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	function webfiable_admin_menu() {
		add_options_page(
			__( 'Webfiable Info', 'webfiable-info' ),
			__( 'Webfiable Info', 'webfiable-info' ),
			'manage_options',
			'webfiable-info',
			'webfiable_render_settings_page'
		);
	}
}
add_action( 'admin_menu', 'webfiable_admin_menu' );

/**
 * Perform a test request against the /webfiable endpoint.
 *
 * @since 2.0.1
 *
 * @return array{
 *     success:bool,
 *     url:string,
 *     http_code:int|null,
 *     body:string|null,
 *     error:string|null,
 *     decoded:mixed
 * }
 */
function webfiable_run_endpoint_test() {
	$verify_url = add_query_arg(
		array( '_wf' => (string) wp_rand( 1000, 9999 ) ),
		home_url( '/' . WEBFIABLE_ENDPOINT_SLUG )
	);

	$result = array(
		'success'   => false,
		'url'       => $verify_url,
		'http_code' => null,
		'body'      => null,
		'error'     => null,
		'decoded'   => null,
	);

	$response = wp_remote_get(
		$verify_url,
		array(
			'timeout' => 10,
			'headers' => array(
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Pragma'        => 'no-cache',
				'Expires'       => '0',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		$result['error'] = $response->get_error_message();
		return $result;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	$result['http_code'] = $code;
	$result['body']      = (string) $body;

	if ( 200 !== $code ) {
		$result['error'] = sprintf(
			/* translators: %d: HTTP status code returned by the endpoint verification request. */
			__( 'Unexpected HTTP status: %d', 'webfiable-info' ),
			$code
		);
		return $result;
	}

	$json              = json_decode( (string) $body, true );
	$result['decoded'] = $json;

	if ( is_array( $json ) && isset( $json['encrypted_key'], $json['iv'], $json['data'] ) ) {
		$result['success'] = true;
		return $result;
	}

	$result['error'] = __( 'Unexpected response payload.', 'webfiable-info' );
	return $result;
}

/**
 * Settings page (render + save) — simple version:
 * - Always saves consent/email/endpoint.
 * - Always calls the proxy after saving.
 * - No PRG, no change detection, no retries.
 */
function webfiable_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice                 = '';
	$notice_type            = 'success';
	$previous_enabled_value = webfiable_get_option( 'webfiable_endpoint_enabled' );
	$endpoint_test_result   = null;
	$registration_result    = null;

	// Handle submit.
	$post_data = filter_input_array(
		INPUT_POST,
		array(
			'webfiable_save_settings'    => FILTER_DEFAULT,
			'webfiable_admin_email'      => FILTER_UNSAFE_RAW,
			'webfiable_consent'          => FILTER_DEFAULT,
			'webfiable_endpoint_enabled' => FILTER_DEFAULT,
		)
	);
	if ( ! is_array( $post_data ) ) {
		$post_data = array();
	}

	if ( isset( $post_data['webfiable_save_settings'] ) && check_admin_referer( 'webfiable_save_settings' ) ) {
		$raw_email_value = isset( $post_data['webfiable_admin_email'] ) ? $post_data['webfiable_admin_email'] : '';
		$raw_email       = is_string( $raw_email_value ) ? wp_unslash( $raw_email_value ) : '';
		$email           = sanitize_email( $raw_email );
		$consent         = isset( $post_data['webfiable_consent'] ) ? 'yes' : 'no';
		$enable          = isset( $post_data['webfiable_endpoint_enabled'] ) ? 'yes' : 'no';

		// Basic validation.
		if ( 'yes' !== $consent ) {
			$notice      = __( 'You must accept the consent to register.', 'webfiable-info' );
			$notice_type = 'error';
		} elseif ( empty( $email ) || ! is_email( $email ) ) {
			$notice      = __( 'Invalid email address.', 'webfiable-info' );
			$notice_type = 'error';
		} else {
			// Ensure we have a site ID (normally set on activation).
			$site_id = (string) webfiable_get_option( 'webfiable_site_id' );
			if ( '' === $site_id ) {
				$site_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wf_', true );
				webfiable_update_option( 'webfiable_site_id', $site_id );
			}

			// Persist state so the endpoint reflects the new values immediately.
			webfiable_update_option( 'webfiable_consent_ts', time() );
			webfiable_update_option( 'webfiable_admin_email', strtolower( $email ) );
			webfiable_update_option( 'webfiable_endpoint_enabled', ( 'yes' === $enable ? 'yes' : 'no' ) );

			if ( 'yes' === $enable && 'yes' !== $previous_enabled_value ) {
				// Ensure rewrite rules include the /webfiable endpoint immediately when turning it on.
				if ( function_exists( 'webfiable_register_route' ) ) {
					webfiable_register_route();
				}
				if ( function_exists( 'flush_rewrite_rules' ) ) {
					flush_rewrite_rules( false );
				}
			}

			if ( 'yes' === $enable ) {
				$endpoint_test_result = webfiable_run_endpoint_test();

				if ( empty( $endpoint_test_result['success'] ) ) {
					webfiable_update_option( 'webfiable_endpoint_enabled', 'no' );
					$enable      = 'no';
					$notice      = __( 'Endpoint could not be verified and has been disabled. Please check server configuration and try again.', 'webfiable-info' );
					$notice_type = 'error';
				}
			}

			if ( 'yes' === $enable ) {
				$registration_result = webfiable_attempt_registration(
					$site_id,
					untrailingslashit( home_url() ),
					strtolower( $email ),
					'https://webfiable.com'
				);

				$registration_success = is_array( $registration_result ) && ! empty( $registration_result['success'] );

				if ( $registration_success ) {
					$notice      = __( 'Settings saved and registration completed.', 'webfiable-info' );
					$notice_type = 'success';
				} else {
					webfiable_update_option( 'webfiable_endpoint_enabled', 'no' );
					$enable      = 'no';
					$notice      = __( 'Registration failed; please review the API request details below and try again later.', 'webfiable-info' );
					$notice_type = 'error';
				}
			} elseif ( '' === $notice ) {
				$notice      = __( 'Settings saved.', 'webfiable-info' );
				$notice_type = 'success';
			}
		}
	}

	// Current values for rendering.
	$site_id = webfiable_get_option( 'webfiable_site_id' );
	$email   = webfiable_get_option( 'webfiable_admin_email' );
	if ( empty( $email ) ) {
		$email = get_option( 'admin_email' );
	}
	$consented    = (int) webfiable_get_option( 'webfiable_consent_ts' ) > 0;
	$enabled      = ( webfiable_get_option( 'webfiable_endpoint_enabled' ) === 'yes' );
	$endpoint_url = home_url( '/' . WEBFIABLE_ENDPOINT_SLUG );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Webfiable Info', 'webfiable-info' ); ?></h1>

		<?php if ( ! empty( $notice ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice_type ); ?>">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'webfiable_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="webfiable_admin_email"><?php esc_html_e( 'Report recipient email', 'webfiable-info' ); ?></label>
					</th>
					<td>
						<input name="webfiable_admin_email" id="webfiable_admin_email" type="email" class="regular-text" value="<?php echo esc_attr( $email ); ?>" required />
						<p class="description"><?php esc_html_e( 'We will send the first full report and subsequent summaries to this address.', 'webfiable-info' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Consent', 'webfiable-info' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="webfiable_consent" <?php checked( $consented ); ?> />
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: Privacy policy URL. */
									__( 'I agree to send site inventory and my email to Webfiable to receive reports. See <a href="%s" target="_blank" rel="noopener">Privacy</a>.', 'webfiable-info' ),
									esc_url( 'https://webfiable.com/politica-privacidad/' )
								),
								array(
									'a' => array(
										'href'   => true,
										'target' => true,
										'rel'    => true,
									),
								)
							);
							?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Public endpoint', 'webfiable-info' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="webfiable_endpoint_enabled" <?php checked( $enabled ); ?> />
							<?php esc_html_e( 'Enable /webfiable endpoint', 'webfiable-info' ); ?>
						</label>
						<p class="description"><code><?php echo esc_html( $endpoint_url ); ?></code></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'webfiable-info' ), 'primary', 'webfiable_save_settings' ); ?>
		</form>

		<h2><?php esc_html_e( 'Status', 'webfiable-info' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Site ID:', 'webfiable-info' ); ?> <code><?php echo esc_html( $site_id ); ?></code></li>
			<li><?php esc_html_e( 'Endpoint:', 'webfiable-info' ); ?> <?php echo $enabled ? esc_html__( 'Enabled', 'webfiable-info' ) : esc_html__( 'Disabled', 'webfiable-info' ); ?></li>
			<li><?php esc_html_e( 'Consent:', 'webfiable-info' ); ?> <?php echo $consented ? esc_html__( 'Granted', 'webfiable-info' ) : esc_html__( 'Not granted', 'webfiable-info' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'API Request', 'webfiable-info' ); ?></h2>
		<?php if ( null === $registration_result ) : ?>
			<p><?php esc_html_e( 'No registration request was sent during this save operation.', 'webfiable-info' ); ?></p>
		<?php else : ?>
			<p>
				<?php if ( ! empty( $registration_result['success'] ) ) : ?>
					<?php esc_html_e( 'Registration request succeeded.', 'webfiable-info' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Registration request failed.', 'webfiable-info' ); ?>
				<?php endif; ?>
			</p>
			<ul>
				<li><?php esc_html_e( 'Endpoint:', 'webfiable-info' ); ?> <code><?php echo esc_url( isset( $registration_result['endpoint'] ) ? $registration_result['endpoint'] : '' ); ?></code></li>
				<?php if ( isset( $registration_result['http_code'] ) && null !== $registration_result['http_code'] ) : ?>
					<li><?php esc_html_e( 'HTTP status:', 'webfiable-info' ); ?> <?php echo esc_html( (string) $registration_result['http_code'] ); ?></li>
				<?php endif; ?>
				<?php if ( ! empty( $registration_result['error'] ) ) : ?>
					<li><?php esc_html_e( 'Error:', 'webfiable-info' ); ?> <?php echo esc_html( $registration_result['error'] ); ?></li>
				<?php endif; ?>
			</ul>
			<p><?php esc_html_e( 'Payload sent:', 'webfiable-info' ); ?></p>
			<pre><code><?php echo esc_html( wp_json_encode( isset( $registration_result['payload'] ) ? $registration_result['payload'] : array(), JSON_PRETTY_PRINT ) ); ?></code></pre>
			<?php if ( isset( $registration_result['response_body'] ) && null !== $registration_result['response_body'] ) : ?>
				<p><?php esc_html_e( 'Response body:', 'webfiable-info' ); ?></p>
				<pre><code><?php echo esc_html( $registration_result['response_body'] ); ?></code></pre>
			<?php endif; ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Endpoint Test', 'webfiable-info' ); ?></h2>
		<?php if ( null === $endpoint_test_result ) : ?>
			<p><?php esc_html_e( 'No endpoint test was run in this request. Enable the endpoint and save the settings to run a live test.', 'webfiable-info' ); ?></p>
		<?php else : ?>
			<p>
				<?php if ( ! empty( $endpoint_test_result['success'] ) ) : ?>
					<?php esc_html_e( 'Endpoint test succeeded.', 'webfiable-info' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Endpoint test failed.', 'webfiable-info' ); ?>
				<?php endif; ?>
			</p>
			<ul>
				<li><?php esc_html_e( 'URL:', 'webfiable-info' ); ?> <code><?php echo esc_url( $endpoint_test_result['url'] ); ?></code></li>
				<?php if ( null !== $endpoint_test_result['http_code'] ) : ?>
					<li><?php esc_html_e( 'HTTP status:', 'webfiable-info' ); ?>
						<?php echo esc_html( (string) $endpoint_test_result['http_code'] ); ?></li>
				<?php endif; ?>
				<?php if ( ! empty( $endpoint_test_result['error'] ) ) : ?>
					<li><?php esc_html_e( 'Error:', 'webfiable-info' ); ?> <?php echo esc_html( $endpoint_test_result['error'] ); ?></li>
				<?php endif; ?>
			</ul>
			<?php if ( null !== $endpoint_test_result['body'] ) : ?>
				<p><?php esc_html_e( 'Response body:', 'webfiable-info' ); ?></p>
				<pre><code><?php echo esc_html( $endpoint_test_result['body'] ); ?></code></pre>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

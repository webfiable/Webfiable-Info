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
	$enabled    = webfiable_is_endpoint_enabled();

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
 * Handle settings form submission and cache the result for reuse.
 *
 * @return array<string,mixed> Result data for the settings screen.
 */
function webfiable_handle_settings_submission() {

	static $result = null;

	if ( null !== $result ) {
		return $result;
	}

	$result = array(
		'processed'            => false,
		'notice'               => '',
		'notice_type'          => 'success',
		'endpoint_test_result' => null,
		'registration_result'  => null,
	);

	if ( ! current_user_can( 'manage_options' ) ) {
		return $result;
	}

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

	if ( ! isset( $post_data['webfiable_save_settings'] ) || ! check_admin_referer( 'webfiable_save_settings' ) ) {
		return $result;
	}

	$raw_email_value = isset( $post_data['webfiable_admin_email'] ) ? $post_data['webfiable_admin_email'] : '';
	$raw_email       = is_string( $raw_email_value ) ? wp_unslash( $raw_email_value ) : '';
	$email           = sanitize_email( $raw_email );
	$consent         = isset( $post_data['webfiable_consent'] ) ? 'yes' : 'no';
	$enable          = isset( $post_data['webfiable_endpoint_enabled'] ) ? 'yes' : 'no';

	webfiable_log_action(
		'settings_submission_started',
		array(
			'email'             => $email,
			'consent_requested' => $consent,
			'endpoint_requested'=> $enable,
			'user_id'           => get_current_user_id(),
		)
	);

	$notice                 = '';
	$notice_type            = 'success';
	$endpoint_test_result   = null;
	$registration_result    = null;
	$previous_enabled_value = webfiable_get_option( 'webfiable_endpoint_enabled' );

	// Basic validation.
	if ( empty( $email ) || ! is_email( $email ) ) {
		$notice      = __( 'Invalid email address.', 'webfiable-info' );
		$notice_type = 'error';
		webfiable_log_action(
			'settings_validation_failed',
			array(
				'reason'       => 'invalid_email',
				'provided_raw' => $raw_email,
			),
			'error'
		);
	} else {
		$consent_granted    = ( 'yes' === $consent );
		$endpoint_requested = ( 'yes' === $enable );
		$consent_ts_value   = $consent_granted ? time() : 0;
		$enable             = ( $consent_granted && $endpoint_requested ) ? 'yes' : 'no';

		if ( webfiable_is_endpoint_forced_enabled() ) {
			$enable = 'yes';
		}

		// Ensure we have a site ID (normally set on activation).
		$site_id = (string) webfiable_get_option( 'webfiable_site_id' );
		if ( '' === $site_id ) {
			$site_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wf_', true );
			webfiable_update_option( 'webfiable_site_id', $site_id );
			webfiable_log_action(
				'site_id_generated',
				array(
					'site_id' => $site_id,
				)
			);
		}

		// Persist state so the endpoint reflects the new values immediately.
		webfiable_update_option( 'webfiable_consent_ts', $consent_ts_value );
		webfiable_update_option( 'webfiable_admin_email', strtolower( $email ) );
		webfiable_update_option( 'webfiable_endpoint_enabled', $enable );
		webfiable_log_action(
			'settings_persisted',
			array(
				'consent_ts'       => $consent_ts_value,
				'admin_email'      => strtolower( $email ),
				'endpoint_enabled' => $enable,
			)
		);

		if ( 'yes' === $enable && 'yes' !== $previous_enabled_value ) {
			// Ensure rewrite rules include the /webfiable endpoint immediately when turning it on.
			if ( function_exists( 'webfiable_register_route' ) ) {
				webfiable_register_route();
			}
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules( false );
			}
			webfiable_log_action(
				'endpoint_enabled',
				array(
					'previous_value' => $previous_enabled_value,
					'current_value'  => $enable,
				)
			);
		}

		if ( 'yes' === $enable ) {
			webfiable_log_action( 'endpoint_test_started' );
			$endpoint_test_result = webfiable_run_endpoint_test();

			if ( empty( $endpoint_test_result['success'] ) ) {
				if ( ! webfiable_is_endpoint_forced_enabled() ) {
					webfiable_update_option( 'webfiable_endpoint_enabled', 'no' );
					$enable = 'no';
				}
				$notice = webfiable_is_endpoint_forced_enabled()
					? __( 'Endpoint could not be verified, but it remains enabled because WEBFIABLE_INFO_ACTIVATE_ENDPOINT is defined. Please check server configuration and try again.', 'webfiable-info' )
					: __( 'Endpoint could not be verified and has been disabled. Please check server configuration and try again.', 'webfiable-info' );
				$notice_type = 'error';
				webfiable_log_action(
					'endpoint_test_failed',
					array(
						'result' => $endpoint_test_result,
					),
					'error'
				);
			}
		}

		if ( 'yes' === $enable ) {
			webfiable_log_action(
				'registration_attempt_started',
				array(
					'site_id'     => $site_id,
					'site_url'    => untrailingslashit( home_url() ),
					'admin_email' => strtolower( $email ),
				)
			);
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
				webfiable_log_action(
					'registration_completed',
					array(
						'endpoint' => isset( $registration_result['endpoint'] ) ? $registration_result['endpoint'] : '',
					)
				);
			} else {
				if ( ! webfiable_is_endpoint_forced_enabled() ) {
					webfiable_update_option( 'webfiable_endpoint_enabled', 'no' );
					$enable = 'no';
				}
				$notice      = webfiable_is_endpoint_forced_enabled()
					? __( 'Registration failed; the endpoint remains enabled because WEBFIABLE_INFO_ACTIVATE_ENDPOINT is defined. Please review the API request details below and try again later.', 'webfiable-info' )
					: __( 'Registration failed; please review the API request details below and try again later.', 'webfiable-info' );
				$notice_type = 'error';
				webfiable_log_action(
					'registration_failed',
					array(
						'result' => $registration_result,
					),
					'error'
				);
			}
		} elseif ( '' === $notice ) {
			$notice      = __( 'Settings saved.', 'webfiable-info' );
			$notice_type = 'success';
			webfiable_log_action(
				'settings_saved_without_registration',
				array(
					'endpoint_enabled' => $enable,
				)
			);
		}
	}

	$result['processed']            = true;
	$result['notice']               = $notice;
	$result['notice_type']          = $notice_type;
	$result['endpoint_test_result'] = $endpoint_test_result;
	$result['registration_result']  = $registration_result;
	$result['action_log']           = webfiable_get_action_log( 'request' );

	return $result;
}

/**
 * Process settings submission prior to rendering notices.
 *
 * @return void
 */
function webfiable_process_settings_submission() {

	webfiable_handle_settings_submission();
}
add_action( 'load-settings_page_webfiable-info', 'webfiable_process_settings_submission' );

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

	$args = array(
		'timeout' => 10,
		'headers' => array(
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Pragma'        => 'no-cache',
			'Expires'       => '0',
		),
	);

	webfiable_log_action(
		'endpoint_test_request',
		array(
			'url'  => $verify_url,
			'args' => $args,
		)
	);

	$response = wp_remote_get( $verify_url, $args );

	if ( is_wp_error( $response ) ) {
		$result['error'] = $response->get_error_message();
		webfiable_log_action(
			'endpoint_test_error',
			array(
				'url'   => $verify_url,
				'error' => $response->get_error_message(),
				'data'  => $response->get_error_data(),
			),
			'error'
		);
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
		webfiable_log_action(
			'endpoint_test_unexpected_status',
			array(
				'url'       => $verify_url,
				'http_code' => $code,
				'body'      => $result['body'],
			),
			'warning'
		);
		return $result;
	}

	$json              = json_decode( (string) $body, true );
	$result['decoded'] = $json;

	if ( is_array( $json ) && isset( $json['encrypted_key'], $json['iv'], $json['data'] ) ) {
		$result['success'] = true;
		webfiable_log_action(
			'endpoint_test_success',
			array(
				'url'          => $verify_url,
				'http_code'    => $code,
				'body'         => $result['body'],
				'decoded_body' => $json,
			)
		);
		return $result;
	}

	$result['error'] = __( 'Unexpected response payload.', 'webfiable-info' );
	webfiable_log_action(
		'endpoint_test_unexpected_payload',
		array(
			'url'          => $verify_url,
			'http_code'    => $code,
			'body'         => $result['body'],
			'decoded_body' => $json,
		),
		'warning'
	);
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

	$state = webfiable_handle_settings_submission();

	$notice      = isset( $state['notice'] ) ? $state['notice'] : '';
	$notice_type = isset( $state['notice_type'] ) ? $state['notice_type'] : 'success';

	// Current values for rendering.
	$site_id = webfiable_get_option( 'webfiable_site_id' );
	$email   = webfiable_get_option( 'webfiable_admin_email' );
	if ( empty( $email ) ) {
		$email = get_option( 'admin_email' );
	}
	$consented    = (int) webfiable_get_option( 'webfiable_consent_ts' ) > 0;
	$enabled      = webfiable_is_endpoint_enabled();
	$endpoint_forced = webfiable_is_endpoint_forced_enabled();
	$endpoint_url = home_url( '/' . WEBFIABLE_ENDPOINT_SLUG );
	$action_log   = isset( $state['action_log'] ) ? $state['action_log'] : array();
	$registration_failed = (
		is_array( $state['registration_result'] )
		&& array_key_exists( 'success', $state['registration_result'] )
		&& empty( $state['registration_result']['success'] )
	);
	$show_action_log = ( ! empty( $action_log ) && ( $registration_failed || 'success' !== $notice_type ) );
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
							<input type="checkbox" name="webfiable_endpoint_enabled" <?php checked( $enabled ); ?> <?php disabled( $endpoint_forced ); ?> />
							<?php esc_html_e( 'Enable /webfiable endpoint', 'webfiable-info' ); ?>
						</label>
						<p class="description"><code><?php echo esc_html( $endpoint_url ); ?></code></p>
						<?php if ( $endpoint_forced ) : ?>
							<p class="description"><strong><?php esc_html_e( 'Endpoint is forced on via WEBFIABLE_INFO_ACTIVATE_ENDPOINT.', 'webfiable-info' ); ?></strong></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'webfiable-info' ), 'primary', 'webfiable_save_settings' ); ?>
		</form>

		<h2><?php esc_html_e( 'Status', 'webfiable-info' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Site ID:', 'webfiable-info' ); ?> <code><?php echo esc_html( $site_id ); ?></code></li>
			<?php
			$endpoint_status_text = $enabled ? esc_html__( 'Enabled', 'webfiable-info' ) : esc_html__( 'Disabled', 'webfiable-info' );
			if ( $endpoint_forced ) {
				$endpoint_status_text = sprintf(
					/* translators: %s: Endpoint status (Enabled/Disabled). */
					__( '%s (forced via WEBFIABLE_INFO_ACTIVATE_ENDPOINT)', 'webfiable-info' ),
					$endpoint_status_text
				);
			}
			?>
			<li><?php esc_html_e( 'Endpoint:', 'webfiable-info' ); ?> <?php echo esc_html( $endpoint_status_text ); ?></li>
			<li><?php esc_html_e( 'Consent:', 'webfiable-info' ); ?> <?php echo $consented ? esc_html__( 'Granted', 'webfiable-info' ) : esc_html__( 'Not granted', 'webfiable-info' ); ?></li>
		</ul>

		<?php if ( $show_action_log ) : ?>
			<h2><?php esc_html_e( 'Latest Actions Log', 'webfiable-info' ); ?></h2>
			<p><?php esc_html_e( 'Review the detailed endpoint and registration activity captured during the last submission attempt.', 'webfiable-info' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time', 'webfiable-info' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Level', 'webfiable-info' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'webfiable-info' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Details', 'webfiable-info' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $action_log as $entry ) : ?>
						<?php
						$timestamp = isset( $entry['timestamp'] ) ? absint( $entry['timestamp'] ) : 0;
						$time_str  = $timestamp ? wp_date( 'Y-m-d H:i:s', $timestamp ) : '';
						$level     = isset( $entry['level'] ) ? strtoupper( (string) $entry['level'] ) : '';
						$action    = isset( $entry['action'] ) ? (string) $entry['action'] : '';
						$context   = isset( $entry['context'] ) ? $entry['context'] : array();
						$json_opts = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
						$context_json = wp_json_encode( $context, $json_opts );
						?>
						<tr>
							<td><code><?php echo esc_html( $time_str ); ?></code></td>
							<td><?php echo esc_html( $level ); ?></td>
							<td><?php echo esc_html( $action ); ?></td>
							<td>
								<?php if ( ! empty( $context_json ) ) : ?>
									<pre><code><?php echo esc_html( $context_json ); ?></code></pre>
								<?php else : ?>
									<em><?php esc_html_e( 'No additional details.', 'webfiable-info' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	</div>
	<?php
}

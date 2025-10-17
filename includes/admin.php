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
		$hook = add_options_page(
			__( 'Webfiable Info', 'webfiable-info' ),
			__( 'Webfiable Info', 'webfiable-info' ),
			'manage_options',
			'webfiable-info',
			'webfiable_render_settings_page'
		);
		// Handle POST early (before any output) to allow safe redirects.
		if ( $hook ) {
			add_action( 'load-' . $hook, 'webfiable_handle_settings_post' );
		}
	}
}
add_action( 'admin_menu', 'webfiable_admin_menu' );

/**
 * Process settings POST on load- hook to avoid output before redirect (PRG).
 *
 * Runs before admin notices render, preventing the classic “two saves needed”
 * when headers are already sent by notices.
 *
 * @return void
 */
function webfiable_handle_settings_post() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$method = filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_SPECIAL_CHARS );
	if ( 'POST' !== $method ) {
		return;
	}
	// Only handle our form submissions for this screen.
	$form_flag = filter_input( INPUT_POST, 'webfiable_settings_form', FILTER_SANITIZE_NUMBER_INT );
	if ( empty( $form_flag ) ) {
		return;
	}
	// Require the expected nonce to process the form.
	$nonce = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS );
	if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'webfiable_save_settings' ) ) {
		return;
	}

	// Normalize input. Unchecked checkboxes won't be present → treat as 'no'.
	$raw_email = filter_input( INPUT_POST, 'webfiable_admin_email', FILTER_UNSAFE_RAW );
	$email     = $raw_email ? sanitize_email( $raw_email ) : '';
	$consent   = null !== filter_input( INPUT_POST, 'webfiable_consent', FILTER_DEFAULT ) ? 'yes' : 'no';
	$enable    = null !== filter_input( INPUT_POST, 'webfiable_endpoint_enabled', FILTER_DEFAULT ) ? 'yes' : 'no';

	$notice      = '';
	$notice_type = 'success';

	// 1) Validate fields (fail fast; no DB writes).
	if ( 'yes' !== $consent ) {
		$notice      = __( 'You must accept the consent to register.', 'webfiable-info' );
		$notice_type = 'error';
	} elseif ( empty( $email ) || ! is_email( $email ) ) {
		$notice      = __( 'Invalid email address.', 'webfiable-info' );
		$notice_type = 'error';
	} else {
		// 2) site_id must already exist (created on activation). Never regenerate here.
		$site_id = (string) webfiable_get_option( 'webfiable_site_id' );
		if ( '' === $site_id ) {
			$notice      = __( 'Site ID is missing. Please deactivate and activate the plugin again.', 'webfiable-info' );
			$notice_type = 'error';
		} else {
			/*
			---- RACE-SAFE ORDER ----
			 * A) Snapshot previous values (for decision logic).
			 * B) Persist new state (consent/email/endpoint) so /webfiable prechecks pass.
			 * C) (When needed) Warm /webfiable once to defeat caches, then call proxy.
			 */

			// A) Read previous values BEFORE saving new ones.
			$prev_email     = (string) get_option( 'webfiable_admin_email', '' );
			$prev_consented = (int) get_option( 'webfiable_consent_ts', 0 ) > 0;
			$prev_enabled   = ( 'yes' === (string) get_option( 'webfiable_endpoint_enabled', 'no' ) );

			// B) Persist state FIRST (do not roll back; endpoint depends on these).
			webfiable_update_option( 'webfiable_consent_ts', time() );
			webfiable_update_option( 'webfiable_admin_email', strtolower( $email ) );
			webfiable_update_option( 'webfiable_endpoint_enabled', ( 'yes' === $enable ? 'yes' : 'no' ) );

			// Decide if we need to register based on the PREVIOUS state.
			// Register when: consent is newly granted OR email changed OR endpoint toggled from disabled→enabled.
			$needs_registration = ( ! $prev_consented )
				|| ( strtolower( $prev_email ) !== strtolower( $email ) )
				|| ( ! $prev_enabled && 'yes' === $enable );

			$enabled_now = ( 'yes' === $enable );

			// Default optimistic notice when nothing else triggers.
			$notice      = __( 'Settings saved.', 'webfiable-info' );
			$notice_type = 'success';

			if ( $needs_registration ) {
				if ( ! $enabled_now ) {
					// Endpoint must be active to proceed with registration.
					$notice      = __( 'To complete registration, please enable the /webfiable endpoint and save again.', 'webfiable-info' );
					$notice_type = 'error';
				} else {
					// C) Warm and verify the endpoint to avoid caches returning stale state.
					$warm_url       = add_query_arg( array( '_wf' => time() ), home_url( '/' . WEBFIABLE_ENDPOINT_SLUG ) );
					$verify_headers = array(
						'timeout' => 10,
						'headers' => array(
							'Cache-Control' => 'no-cache, no-store, must-revalidate',
							'Pragma'        => 'no-cache',
							'Expires'       => '0',
						),
					);
					// Warm once (ignore result) to populate caches/rewrite path.
					wp_remote_get( $warm_url, $verify_headers );

					// Verify with a fresh cache-busting token.
					$verify_url = add_query_arg( array( '_wf' => (string) ( time() + 1 ) ), home_url( '/' . WEBFIABLE_ENDPOINT_SLUG ) );
					$verify     = wp_remote_get( $verify_url, $verify_headers );

					$published_ok = true;
					if ( is_wp_error( $verify ) ) {
						$published_ok = false;
					} else {
						$code = (int) wp_remote_retrieve_response_code( $verify );
						$body = wp_remote_retrieve_body( $verify );
						if ( 200 !== $code ) {
							$published_ok = false;
						} else {
							$json         = json_decode( (string) $body, true );
							$published_ok = is_array( $json ) && isset( $json['encrypted_key'], $json['iv'], $json['data'] );
						}
					}

					if ( ! $published_ok ) {
						// Safeguard: disable the endpoint if verification fails.
						webfiable_update_option( 'webfiable_endpoint_enabled', 'no' );
						$notice      = __( 'Endpoint could not be verified and has been disabled. Please check server configuration and try again.', 'webfiable-info' );
						$notice_type = 'error';
					} else {
						// Single, idempotent proxy call.
						$ok = webfiable_attempt_registration(
							$site_id,
							untrailingslashit( home_url() ),
							strtolower( $email ),
							'https://webfiable.com'
						);

						if ( ! $ok ) {
							// Safeguard: disable endpoint when registration fails.
							webfiable_update_option( 'webfiable_endpoint_enabled', 'no' );
							$notice      = __( 'Registration failed; the endpoint has been disabled as a safeguard. Please try again later.', 'webfiable-info' );
							$notice_type = 'error';
						} else {
							$notice      = __( 'Settings saved and registration completed.', 'webfiable-info' );
							$notice_type = 'success';
						}
					}
				}
			}
		}
	}

	// Flash the notice and redirect (PRG) so UI reflects final state without needing a second save.
	if ( '' !== $notice ) {
		set_transient(
			'webfiable_flash_notice',
			array(
				'type' => $notice_type,
				'text' => $notice,
			),
			30
		);
	}
	wp_safe_redirect( menu_page_url( 'webfiable-info', false ) );
	exit;
}

/**
 * Settings page (render + save) with PRG and race-safe registration.
 */
function webfiable_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	/* ---------- GET: render after redirect ---------- */
	$site_id = webfiable_get_option( 'webfiable_site_id' );
	$email   = webfiable_get_option( 'webfiable_admin_email' );
	if ( empty( $email ) ) {
		$email = get_option( 'admin_email' ); // UX prefill only.
	}
	$consented    = (int) webfiable_get_option( 'webfiable_consent_ts' ) > 0;
	$enabled      = ( webfiable_get_option( 'webfiable_endpoint_enabled' ) === 'yes' );
	$endpoint_url = home_url( '/' . WEBFIABLE_ENDPOINT_SLUG );

	// Show flash notice set during POST.
	$notice      = '';
	$notice_type = 'success';
	$flash       = get_transient( 'webfiable_flash_notice' );
	if ( $flash && is_array( $flash ) && ! empty( $flash['text'] ) ) {
		delete_transient( 'webfiable_flash_notice' );
		$notice      = $flash['text'];
		$notice_type = ! empty( $flash['type'] ) ? $flash['type'] : 'success';
	}

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
				<input type="hidden" name="webfiable_settings_form" value="1" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="webfiable_admin_email"><?php esc_html_e( 'Report recipient email', 'webfiable-info' ); ?></label></th>
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
	</div>
	<?php
}

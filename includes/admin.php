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
 * Settings page (render + save) — simple version:
 * - Always saves consent/email/endpoint.
 * - Always calls the proxy after saving.
 * - No PRG, no change detection, no retries.
 */
function webfiable_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice      = '';
	$notice_type = 'success';

	// Handle submit.
	if ( isset( $_POST['webfiable_save_settings'] ) && check_admin_referer( 'webfiable_save_settings' ) ) {
		$email   = isset( $_POST['webfiable_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['webfiable_admin_email'] ) ) : '';
		$consent = isset( $_POST['webfiable_consent'] ) ? 'yes' : 'no';
		$enable  = isset( $_POST['webfiable_endpoint_enabled'] ) ? 'yes' : 'no';

		// Basic validation.
		if ( 'yes' !== $consent ) {
			$notice      = __( 'You must accept the consent to register.', 'webfiable-info' );
			$notice_type = 'error';
		} elseif ( empty( $email ) || ! is_email( $email ) ) {
			$notice      = __( 'Invalid email address.', 'webfiable-info' );
			$notice_type = 'error';
		} else {
			// Ensure we have a site ID (normally set on activation).
			$site_id = (string) get_option( 'webfiable_site_id', '' );
			if ( '' === $site_id ) {
				$site_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : wp_generate_password( 36, false );
				update_option( 'webfiable_site_id', $site_id, false );
			}

			// Save state FIRST so the /webfiable endpoint passes its own checks.
			update_option( 'webfiable_consent_ts', time(), false );
			update_option( 'webfiable_admin_email', strtolower( $email ), false );
			update_option( 'webfiable_endpoint_enabled', ( 'yes' === $enable ? 'yes' : 'no' ), false );

			// (Optional but harmless) warm the endpoint once to avoid cache timing edges.
			wp_remote_get(
				home_url( '/' . WEBFIABLE_ENDPOINT_SLUG ),
				array(
					'timeout' => 5,
					'headers' => array(
						'Cache-Control' => 'no-cache',
						'Pragma'        => 'no-cache',
					),
				)
			);

			// Always call the proxy (simple and predictable).
			$ok = webfiable_attempt_registration(
				$site_id,
				untrailingslashit( home_url() ),
				strtolower( $email ),
				'https://webfiable.com'
			);

			if ( $ok ) {
				$notice      = __( 'Settings saved and registration completed.', 'webfiable-info' );
				$notice_type = 'success';
			} else {
				// Settings remain saved; only registration failed.
				$notice      = __( 'Settings saved, but registration could not be completed now. Please try again later.', 'webfiable-info' );
				$notice_type = 'error';
			}
		}
	}

	// Current values for rendering.
	$site_id = get_option( 'webfiable_site_id', '' );
	$email   = get_option( 'webfiable_admin_email', '' );
	if ( empty( $email ) ) {
		$email = get_option( 'admin_email' );
	}
	$consented    = (int) get_option( 'webfiable_consent_ts', 0 ) > 0;
	$enabled      = ( get_option( 'webfiable_endpoint_enabled', 'no' ) === 'yes' );
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

<?php
/**
 * Plugin Name: Webfiable Info
 * Plugin URI: https://wordpress.org/plugins/webfiable-info/
 * Description: Ensure your website's security posture and configuration health with monitoring and recommendations.
 * Version: 1.5.0
 * Author: Webfiable Team
 * Author URI: https://webfiable.com
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: webfiable-info
 *
 * @package Webfiable_Info
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// === Core constants ===
// Mantén aquí la versión del plugin (sincronizada con la cabecera).
define( 'WEBFIABLE_INFO_VERSION', '1.5.0' );

// Slug del endpoint público. Así evitamos “hardcodear” en varios sitios.
define( 'WEBFIABLE_ENDPOINT_SLUG', 'webfiable' );

// RSA public key (provided by the service). Hardcoded by design (sin rotación en esta versión).
define(
	'WEBFIABLE_RSA_PUBLIC_KEY',
	'-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAw8y6jWyyz5yJzdj1kdDJ
KDU54+MryJYTBHogyq8m+557Q8gciul2cAZexdhC6EkIzI/hxwNi/t6fcLiK0hdC
88nVaP6B/xkZPuURW/cjtKbCBXo0CLTMNnJSxhECI4Xq5l5koiThdhSvDlqsuMWy
xCUUlbvU9Vg+MmiaEiRtZT7Nd5/NSqftqqdiVH0Q6sUd2OEFYPwnDI5615ALLH+h
XeaQhTu053Tpqcw6cMNbqOCc9Gk6esoM69oNHtXR2tKxxzWldwb0+mRRypUiPLUn
/n/9w5jnPrNsYGu1PVLXb+wlspPyZCSItq4zkzkFPYKvQ7u+U2UY28dHqSeHJhGd
FQIDAQAB
-----END PUBLIC KEY-----'
);

// === Options helpers (persistencia mínima) ===

/**
 * Default option values for the plugin.
 *
 * Stores minimal persistent state: site ID, admin email, consent timestamp, and endpoint toggle.
 *
 * @since 1.5.0
 * @return array{
 *     webfiable_site_id:string,
 *     webfiable_admin_email:string,
 *     webfiable_consent_ts:int,
 *     webfiable_endpoint_enabled:string
 * } Associative array of default values.
 */
function webfiable_default_options() {
	return array(
		// UUID v4 to identify this WordPress site (created on activation).
		'webfiable_site_id'          => '',

		// Admin email (sanitized) used to receive reports (requires consent).
		'webfiable_admin_email'      => '',

		// Unix timestamp of explicit consent; 0 means no consent.
		'webfiable_consent_ts'       => 0,

		// Public endpoint toggle. Allowed values: yes / no.
		'webfiable_endpoint_enabled' => 'yes',
	);
}

/**
 * Read a plugin option with a sane default.
 *
 * Falls back to the value defined in {@see webfiable_default_options()} when the option is absent.
 *
 * @since 1.5.0
 *
 * @param string $key Option name.
 * @return mixed      Stored value or default when not set.
 */
function webfiable_get_option( $key ) {
	$defaults = webfiable_default_options();
	return get_option( $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : null );
}

/**
 * Update a plugin option (autoload disabled).
 *
 * Uses autoload = false to avoid polluting wp_load_alloptions().
 *
 * @since 1.5.0
 *
 * @param string $key   Option name.
 * @param mixed  $value Option value to store.
 * @return void
 */
function webfiable_update_option( $key, $value ) {
	// Guardamos sin autoload para no afectar el rendimiento de WP.
	update_option( $key, $value, false );
}



/**
 * Registers the custom rewrite rule for the `webfiable` endpoint.
 *
 * Hooked to `init`.
 *
 * @since 1.4
 * @return void
 */
function webfiable_register_route() {
	add_rewrite_rule( '^' . WEBFIABLE_ENDPOINT_SLUG . '$', 'index.php?webfiable_route=1', 'top' );
}
add_action( 'init', 'webfiable_register_route' );

/**
 * Activación del plugin:
 * - Genera un site_id si no existe.
 * - Registra la ruta y flushea reglas de reescritura.
 */
function webfiable_activate() {
	if ( ! webfiable_get_option( 'webfiable_site_id' ) ) {
		$uuid = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wf_', true );
		webfiable_update_option( 'webfiable_site_id', $uuid );
	}
	webfiable_register_route();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'webfiable_activate' );

/**
 * Flushes rewrite rules on plugin deactivation to remove the custom endpoint.
 *
 * Hooked via `register_deactivation_hook()`.
 *
 * @since 1.4
 * @return void
 */
function webfiable_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'webfiable_deactivate' );

/**
 * Carga de texto para internacionalización (WP.org requirement).
 */
function webfiable_load_textdomain() {
	load_plugin_textdomain( 'webfiable-info', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'webfiable_load_textdomain' );

/**
 * Adds the `webfiable_route` query var so WordPress recognizes the endpoint.
 *
 * Hooked to `query_vars`.
 *
 * @since 1.4
 * @param string[] $vars List of public query vars.
 * @return string[] Modified list of query vars.
 */
function webfiable_add_query_vars( $vars ) {
	$vars[] = 'webfiable_route';
	return $vars;
}
add_filter( 'query_vars', 'webfiable_add_query_vars' );

/**
 * Añade página de opciones: Ajustes → Webfiable Info.
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
add_action( 'admin_menu', 'webfiable_admin_menu' );

/**
 * Admin notice if the PHP OpenSSL extension is missing.
 *
 * Muestra el aviso tanto en el panel normal como en el panel de red (multisitio),
 * solo a usuarios con capacidades suficientes.
 *
 * @since 1.5.0
 * @return void
 */
function webfiable_admin_notice_openssl() {
	if ( extension_loaded( 'openssl' ) ) {
		return;
	}

	if ( is_network_admin() ) {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
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
 * Render de la página de ajustes (email + consentimiento + toggle endpoint).
 */
function webfiable_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice = '';
	if ( isset( $_POST['webfiable_save_settings'] ) && check_admin_referer( 'webfiable_save_settings' ) ) {
		$email   = isset( $_POST['webfiable_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['webfiable_admin_email'] ) ) : '';
		$consent = isset( $_POST['webfiable_consent'] ) ? 'yes' : 'no';
		$enable  = isset( $_POST['webfiable_endpoint_enabled'] ) ? 'yes' : 'no';

		if ( ! empty( $email ) && is_email( $email ) ) {
			webfiable_update_option( 'webfiable_admin_email', strtolower( $email ) );
		} else {
			$notice = __( 'Invalid email address.', 'webfiable-info' );
		}

		// Si marcan consentimiento y antes no existía, registra sello temporal.
		if ( 'yes' === $consent && (int) webfiable_get_option( 'webfiable_consent_ts' ) <= 0 ) {
			webfiable_update_option( 'webfiable_consent_ts', time() );
		}
		if ( 'no' === $consent ) {
			webfiable_update_option( 'webfiable_consent_ts', 0 );
		}

		webfiable_update_option( 'webfiable_endpoint_enabled', ( 'yes' === $enable ? 'yes' : 'no' ) );

		if ( empty( $notice ) ) {
			$notice = __( 'Settings saved.', 'webfiable-info' );
		}
	}

	$site_id = webfiable_get_option( 'webfiable_site_id' );
	$email   = webfiable_get_option( 'webfiable_admin_email' );
	if ( empty( $email ) ) {
		$email = get_option( 'admin_email' ); // prefill.
	}
	$consented    = (int) webfiable_get_option( 'webfiable_consent_ts' ) > 0;
	$enabled      = webfiable_get_option( 'webfiable_endpoint_enabled' ) === 'yes';
	$endpoint_url = home_url( '/' . WEBFIABLE_ENDPOINT_SLUG );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Webfiable Info', 'webfiable-info' ); ?></h1>

		<?php if ( ! empty( $notice ) ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'webfiable_save_settings' ); ?>
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
							$consent_text = sprintf(
							/* translators: %s: Privacy policy URL. */
								__( 'I agree to send site inventory and my email to Webfiable to receive reports. See <a href="%s" target="_blank" rel="noopener">Privacy</a>.', 'webfiable-info' ),
								esc_url( 'https://webfiable.com/privacidad' )
							);

							echo wp_kses(
								$consent_text,
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

/**
 * Handles the request to the `webfiable` endpoint and outputs an encrypted JSON payload.
 *
 * Hooked to `template_redirect`.
 *
 * Collects WP version, installed plugins and themes, builds a payload, encrypts it
 * with a random AES-256-CBC key/IV, encrypts that key with the RSA public key,
 * and returns base64-encoded values.
 *
 * @since 1.4
 * @return void
 */
function webfiable_template_redirect() {
	if ( get_query_var( 'webfiable_route' ) ) {

		// No cache y JSON.
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		// extra por si hay proxies tercos.
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'Expires: 0', true );

		// Corta si falta OpenSSL (no podemos cifrar).
		if ( ! extension_loaded( 'openssl' ) ) {
			status_header( 500 );
			wp_send_json( array( 'error' => 'openssl_missing' ) );
		}

		// Lee estado de opciones.
		$enabled     = webfiable_get_option( 'webfiable_endpoint_enabled' ) === 'yes';
		$admin_email = webfiable_get_option( 'webfiable_admin_email' );
		$consent_ts  = (int) webfiable_get_option( 'webfiable_consent_ts' );

		// Si el endpoint está desactivado → 403.
		if ( ! $enabled ) {
			status_header( 403 );
			wp_send_json( array( 'error' => 'endpoint_disabled' ) );
		}

		// Si falta email válido o consentimiento → 403.
		if ( empty( $admin_email ) || ! is_email( $admin_email ) || $consent_ts <= 0 ) {
			status_header( 403 );
			wp_send_json( array( 'error' => 'consent_or_email_missing' ) );
		}

		// IP sanitizada para la clave del rate limit (no confiamos en cabeceras X-Forwarded-*).
		$raw_ip = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) // sanea el superglobal.
		: '';

		$ip = ( function_exists( 'rest_is_ip_address' ) && rest_is_ip_address( $raw_ip ) )
		? $raw_ip
		: '0.0.0.0';

		// Clave del rate limit (1–2 req/min por IP).
		$key   = 'webfiable_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 2 ) {
			status_header( 429 );
			wp_send_json( array( 'error' => 'rate_limited' ) );
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		// Get all installed plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed_plugins = get_plugins();

		$plugins_info = array();

		foreach ( $installed_plugins as $plugin_slug => $plugin_data ) {
			// $plugin_slug ejemplos:
			// - "akismet/akismet.php"  (plugin en carpeta)
			// - "hello.php"            (plugin en la raíz)

			// Obtenemos un slug consistente.
			$folder_or_dot = dirname( $plugin_slug );
			if ( '.' === $folder_or_dot ) {
				// Plugin suelto: usa el nombre del archivo sin extensión.
				$slug = basename( $plugin_slug, '.php' );
			} else {
				// Plugin en carpeta: usa el nombre de la carpeta.
				$slug = $folder_or_dot;
			}

			$plugins_info[] = array(
				'name'        => $plugin_data['Name'],
				'slug'        => $slug,
				'version'     => $plugin_data['Version'],
				'description' => wp_strip_all_tags( $plugin_data['Description'] ),
			);
		}

		// Get all installed themes.
		$installed_themes = wp_get_themes();
		$themes_info      = array();

		foreach ( $installed_themes as $theme_slug => $theme_data ) {
			$themes_info[] = array(
				'name'        => $theme_data->get( 'Name' ),
				'slug'        => $theme_data->get_stylesheet(),
				'version'     => $theme_data->get( 'Version' ),
				'description' => wp_strip_all_tags( $theme_data->get( 'Description' ) ),  // Remove HTML tags from description.
			);
		}

		// === Construcción de payload ===
		// Incluye metadata mínima para el backend (incl. email y consent_ts).
		$payload = array(
			'site_url'       => site_url(),
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'plugin_version' => WEBFIABLE_INFO_VERSION,
			'site_id'        => webfiable_get_option( 'webfiable_site_id' ),
			'admin_email'    => $admin_email,     // PII permitida por consentimiento.
			'consent_ts'     => $consent_ts,
			'ts'             => time(),           // frescura del payload.
			'plugins'        => $plugins_info,
			'themes'         => $themes_info,
		);

		$json_data = wp_json_encode( $payload );

		// Clave e IV aleatorios para AES-256-CBC.
		$aes_key = openssl_random_pseudo_bytes( 32 );
		$iv      = openssl_random_pseudo_bytes( 16 );

		// Cifrado simétrico del JSON.
		$encrypted_data = openssl_encrypt( $json_data, 'AES-256-CBC', $aes_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $encrypted_data ) {
			status_header( 500 );
			wp_send_json( array( 'error' => 'aes_encrypt_failed' ) );
		}

		// Cifrado asimétrico de la clave AES con la RSA pública.
		$encrypted_key = null;
		$ok            = openssl_public_encrypt( $aes_key, $encrypted_key, WEBFIABLE_RSA_PUBLIC_KEY );
		if ( ! $ok ) {
			status_header( 500 );
			wp_send_json( array( 'error' => 'rsa_encrypt_failed' ) );
		}

		// Return both the encrypted AES key and the encrypted JSON data.
		// We base64-encode binary values to transport them safely in JSON.
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport encoding, not obfuscation.
		$encoded_key  = base64_encode( $encrypted_key );
		$encoded_iv   = base64_encode( $iv );
		$encoded_data = base64_encode( $encrypted_data );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$response = array(
			'encrypted_key' => $encoded_key,
			'iv'            => $encoded_iv,
			'data'          => $encoded_data,
		);

		// Send JSON response.
		wp_send_json( $response );
		exit;
	}
}
add_action( 'template_redirect', 'webfiable_template_redirect' );



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
	$label = esc_html__( 'Ajustes', 'webfiable-info' );
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
	$links[] = '<a href="' . esc_url( 'https://webfiable.com/privacidad/' ) . '" target="_blank" rel="noopener">' .
		esc_html__( 'Privacidad', 'webfiable-info' ) . '</a>';
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
	esc_html_e( 'Webfiable Análisis de Sitios necesita la extensión OpenSSL de PHP para funcionar. Pide a tu proveedor de alojamiento que la active.', 'webfiable-info' );
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
		$issues[] = __( 'Escribe un correo válido: es el que usarás para entrar en tu panel.', 'webfiable-info' );
	}
	if ( $consent_ts <= 0 ) {
		$issues[] = __( 'Acepta compartir los datos de tu sitio con Webfiable.', 'webfiable-info' );
	}
	if ( ! $enabled ) {
		$issues[] = __( 'Activa la conexión de datos para que Webfiable pueda leer la información de tu sitio.', 'webfiable-info' );
	}
	if ( empty( $issues ) ) {
		return; }

	$settings_url = admin_url( 'options-general.php?page=webfiable-info' );
	?>
	<div class="webfiable-setup-banner notice is-dismissible">
		<div class="webfiable-setup-banner__icon">
			<img src="<?php echo esc_url( WEBFIABLE_PLUGIN_URL . 'assets/img/icon.png' ); ?>" alt="" width="40" height="40" />
		</div>
		<div class="webfiable-setup-banner__body">
			<p class="webfiable-setup-banner__title"><?php esc_html_e( 'Ya casi está: termina de configurar Webfiable Análisis de Sitios.', 'webfiable-info' ); ?></p>
			<p class="webfiable-setup-banner__text"><?php esc_html_e( 'Completa estos pasos para que Webfiable pueda analizar tu sitio y enseñarte el resultado en tu panel.', 'webfiable-info' ); ?></p>
			<ul class="webfiable-setup-banner__checklist">
				<?php foreach ( $issues as $msg ) : ?>
					<li><span class="dashicons dashicons-marker"></span> <?php echo esc_html( $msg ); ?></li>
				<?php endforeach; ?>
			</ul>
			<a href="<?php echo esc_url( $settings_url ); ?>" class="webfiable-setup-banner__cta">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Abrir los ajustes', 'webfiable-info' ); ?>
			</a>
		</div>
	</div>
	<?php
}
add_action( 'admin_notices', 'webfiable_admin_notice_incomplete_setup' );

// Settings page registration.
if ( ! function_exists( 'webfiable_admin_menu' ) ) {
	/**
	 * Registers the Webfiable Análisis de Sitios settings page under Settings.
	 *
	 * Adds an options page visible to users with the `manage_options` capability.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	function webfiable_admin_menu() {
		add_options_page(
			__( 'Webfiable Análisis de Sitios', 'webfiable-info' ),
			__( 'Webfiable', 'webfiable-info' ),
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
			'email'              => $email,
			'consent_requested'  => $consent,
			'endpoint_requested' => $enable,
			'user_id'            => get_current_user_id(),
		)
	);

	$notice                 = '';
	$notice_type            = 'success';
	$endpoint_test_result   = null;
	$registration_result    = null;
	$previous_enabled_value = webfiable_get_option( 'webfiable_endpoint_enabled' );

	// Basic validation.
	if ( empty( $email ) || ! is_email( $email ) ) {
		$notice      = __( 'Escribe un correo válido (por ejemplo, tu@ejemplo.com).', 'webfiable-info' );
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
				$notice      = webfiable_is_endpoint_forced_enabled()
					? __( 'No hemos podido comprobar la conexión de datos, pero sigue activa porque la configuración de tu sitio la fuerza. Ve a Ajustes → Enlaces permanentes, pulsa «Guardar cambios» y vuelve a intentarlo.', 'webfiable-info' )
					: __( 'No hemos podido comprobar la conexión de datos, así que la hemos desactivado. Ve a Ajustes → Enlaces permanentes, pulsa «Guardar cambios» y vuelve a intentarlo.', 'webfiable-info' );
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
				$notice      = __( 'Ajustes guardados. Tu sitio está registrado en Webfiable.', 'webfiable-info' );
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
					? __( 'Ajustes guardados, pero el registro no se ha completado. La conexión de datos sigue activa porque la configuración de tu sitio la fuerza. Revisa los detalles de abajo y vuelve a intentarlo más tarde.', 'webfiable-info' )
					: __( 'Ajustes guardados, pero no hemos podido completar el registro. Revisa los detalles de abajo y vuelve a intentarlo más tarde.', 'webfiable-info' );
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
			$notice      = __( 'Ajustes guardados.', 'webfiable-info' );
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
		'timeout' => 30,
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
			__( 'La conexión de datos ha devuelto una respuesta inesperada (HTTP %d). Vuelve a intentarlo más tarde.', 'webfiable-info' ),
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

	$result['error'] = __( 'La conexión de datos ha devuelto una respuesta con un formato inesperado. Guarda los enlaces permanentes y vuelve a intentarlo.', 'webfiable-info' );
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
 * Enqueue admin styles.
 *
 * Loads the notice banner CSS on every admin page (lightweight) and the
 * full settings page CSS only on the plugin's own screen.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function webfiable_enqueue_admin_assets( $hook_suffix ) {
	// Notice banner styles — needed on every admin page.
	wp_enqueue_style(
		'webfiable-notice',
		WEBFIABLE_PLUGIN_URL . 'assets/css/notice.css',
		array( 'dashicons' ),
		WEBFIABLE_INFO_VERSION
	);

	// Full settings page styles.
	if ( 'settings_page_webfiable-info' === $hook_suffix ) {
		wp_enqueue_style(
			'webfiable-admin',
			WEBFIABLE_PLUGIN_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			WEBFIABLE_INFO_VERSION
		);
	}
}
add_action( 'admin_enqueue_scripts', 'webfiable_enqueue_admin_assets' );

/**
 * Settings page (render + save).
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
	$consented           = (int) webfiable_get_option( 'webfiable_consent_ts' ) > 0;
	$enabled             = webfiable_is_endpoint_enabled();
	$endpoint_forced     = webfiable_is_endpoint_forced_enabled();
	$endpoint_url        = home_url( '/' . WEBFIABLE_ENDPOINT_SLUG );
	$action_log          = isset( $state['action_log'] ) ? $state['action_log'] : array();
	$registration_failed = (
		is_array( $state['registration_result'] )
		&& array_key_exists( 'success', $state['registration_result'] )
		&& empty( $state['registration_result']['success'] )
	);
	$show_action_log     = ( ! empty( $action_log ) && ( $registration_failed || 'success' !== $notice_type ) );
	?>
	<div class="wrap webfiable-wrap">

		<hr class="wp-header-end" />

		<!-- Page header -->
		<div class="webfiable-header">
			<div class="webfiable-logo"><img src="<?php echo esc_url( WEBFIABLE_PLUGIN_URL . 'assets/img/icon.png' ); ?>" alt="" width="36" height="36" /></div>
			<h1><?php esc_html_e( 'Webfiable Análisis de Sitios', 'webfiable-info' ); ?></h1>
			<span class="webfiable-version"><?php echo esc_html( 'v' . WEBFIABLE_INFO_VERSION ); ?></span>
		</div>

		<!-- Notice -->
		<?php if ( ! empty( $notice ) ) : ?>
			<div class="webfiable-notice webfiable-notice--<?php echo esc_attr( $notice_type ); ?>">
				<span><?php echo esc_html( $notice ); ?></span>
			</div>
		<?php endif; ?>

		<!-- Settings card -->
		<div class="webfiable-card">
			<div class="webfiable-card__header">
				<span class="dashicons dashicons-admin-generic"></span>
				<h2><?php esc_html_e( 'Ajustes', 'webfiable-info' ); ?></h2>
			</div>
			<form method="post">
				<?php wp_nonce_field( 'webfiable_save_settings' ); ?>
				<div class="webfiable-card__body">

					<!-- Email field -->
					<div class="webfiable-field">
						<label class="webfiable-field__label" for="webfiable_admin_email"><?php esc_html_e( 'Correo', 'webfiable-info' ); ?></label>
						<div class="webfiable-field__input">
							<input name="webfiable_admin_email" id="webfiable_admin_email" type="email" value="<?php echo esc_attr( $email ); ?>" placeholder="you@example.com" required />
						</div>
						<p class="webfiable-field__help"><?php esc_html_e( 'Es el correo con el que entrarás en tu panel de Análisis de Sitios de Webfiable. Allí te enviaremos los enlaces para entrar.', 'webfiable-info' ); ?></p>
					</div>

					<!-- Consent field -->
					<div class="webfiable-field">
						<span class="webfiable-field__label"><?php esc_html_e( 'Consentimiento', 'webfiable-info' ); ?></span>
						<label class="webfiable-field__checkbox">
							<input type="checkbox" name="webfiable_consent" <?php checked( $consented ); ?> />
							<span class="webfiable-field__checkbox-text">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: Privacy policy URL. */
									__( 'Acepto compartir con Webfiable la lista de plugins y temas de mi sitio con sus versiones, las versiones de WordPress y de PHP, la dirección del sitio y este correo, para que analice mi sitio y me enseñe el resultado en mi panel. Consulta la <a href="%s" target="_blank" rel="noopener">política de privacidad</a>.', 'webfiable-info' ),
									esc_url( 'https://webfiable.com/privacidad/' )
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
							</span>
						</label>
					</div>

					<!-- Endpoint field -->
					<div class="webfiable-field">
						<span class="webfiable-field__label"><?php esc_html_e( 'Conexión de datos', 'webfiable-info' ); ?></span>
						<label class="webfiable-field__checkbox">
							<input type="checkbox" name="webfiable_endpoint_enabled" <?php checked( $enabled ); ?> <?php disabled( $endpoint_forced ); ?> />
							<span class="webfiable-field__checkbox-text"><?php esc_html_e( 'Permitir que Webfiable lea los datos de mi sitio por una conexión cifrada', 'webfiable-info' ); ?></span>
						</label>
						<div class="webfiable-field__endpoint-url"><?php echo esc_html( $endpoint_url ); ?></div>
						<?php if ( $endpoint_forced ) : ?>
							<div class="webfiable-field__forced-note">
								<span class="dashicons dashicons-lock"></span>
								<?php esc_html_e( 'Este ajuste está fijado como activo por la configuración de tu sitio (WEBFIABLE_INFO_ACTIVATE_ENDPOINT).', 'webfiable-info' ); ?>
							</div>
						<?php endif; ?>
					</div>

				</div>
				<div class="webfiable-card__footer">
					<?php submit_button( __( 'Guardar los ajustes', 'webfiable-info' ), 'primary', 'webfiable_save_settings', false ); ?>
				</div>
			</form>
		</div>

		<!-- Status card -->
		<div class="webfiable-card">
			<div class="webfiable-card__header">
				<span class="dashicons dashicons-yes-alt"></span>
				<h2><?php esc_html_e( 'Estado de la conexión', 'webfiable-info' ); ?></h2>
			</div>
			<div class="webfiable-card__body">
				<div class="webfiable-status-grid">

					<!-- Site ID -->
					<div class="webfiable-status-item">
						<div class="webfiable-status-item__icon webfiable-status-item__icon--id">
							<span class="dashicons dashicons-admin-network"></span>
						</div>
						<div class="webfiable-status-item__content">
							<div class="webfiable-status-item__label"><?php esc_html_e( 'Identificador del sitio', 'webfiable-info' ); ?></div>
							<div class="webfiable-status-item__value"><code><?php echo esc_html( $site_id ); ?></code></div>
						</div>
					</div>

					<!-- Data Connection -->
					<div class="webfiable-status-item">
						<?php
						$conn_icon_class = 'webfiable-status-item__icon webfiable-status-item__icon--connection';
						if ( ! $enabled ) {
							$conn_icon_class .= ' is-disabled';
						}
						?>
						<div class="<?php echo esc_attr( $conn_icon_class ); ?>">
							<span class="dashicons dashicons-<?php echo $enabled ? 'cloud-saved' : 'cloud'; ?>"></span>
						</div>
						<div class="webfiable-status-item__content">
							<div class="webfiable-status-item__label"><?php esc_html_e( 'Conexión de datos', 'webfiable-info' ); ?></div>
							<div class="webfiable-status-item__value">
								<?php if ( $enabled ) : ?>
									<span class="webfiable-pill webfiable-pill--green">
										<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Activada', 'webfiable-info' ); ?>
									</span>
								<?php else : ?>
									<span class="webfiable-pill webfiable-pill--red">
										<span class="dashicons dashicons-no"></span> <?php esc_html_e( 'Desactivada', 'webfiable-info' ); ?>
									</span>
								<?php endif; ?>
								<?php
								if ( $endpoint_forced ) {
									echo ' <small>' . esc_html__( '(fijada por la configuración del sitio)', 'webfiable-info' ) . '</small>';
								}
								?>
							</div>
						</div>
					</div>

					<!-- Data Sharing -->
					<div class="webfiable-status-item">
						<?php
						$consent_icon_class = 'webfiable-status-item__icon webfiable-status-item__icon--consent';
						if ( ! $consented ) {
							$consent_icon_class .= ' is-disabled';
						}
						?>
						<div class="<?php echo esc_attr( $consent_icon_class ); ?>">
							<span class="dashicons dashicons-<?php echo $consented ? 'lock' : 'unlock'; ?>"></span>
						</div>
						<div class="webfiable-status-item__content">
							<div class="webfiable-status-item__label"><?php esc_html_e( 'Datos compartidos', 'webfiable-info' ); ?></div>
							<div class="webfiable-status-item__value">
								<?php if ( $consented ) : ?>
									<span class="webfiable-pill webfiable-pill--green">
										<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Aceptado', 'webfiable-info' ); ?>
									</span>
								<?php else : ?>
									<span class="webfiable-pill webfiable-pill--yellow">
										<span class="dashicons dashicons-marker"></span> <?php esc_html_e( 'Pendiente de aceptar', 'webfiable-info' ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>

		<!-- Activity log card (collapsible) -->
		<?php if ( $show_action_log ) : ?>
			<div class="webfiable-card">
				<div class="webfiable-card__header">
					<span class="dashicons dashicons-media-text"></span>
					<h2><?php esc_html_e( 'Registro de actividad reciente', 'webfiable-info' ); ?></h2>
				</div>
				<div class="webfiable-card__body">
					<p class="webfiable-field__help" style="margin-top:0"><?php esc_html_e( 'Estos detalles pueden ayudar a entender qué ha pasado en el último guardado.', 'webfiable-info' ); ?></p>
					<button type="button" class="webfiable-log-toggle" aria-expanded="false" onclick="var c=this.nextElementSibling;var show=c.style.display==='none'||!c.style.display;c.style.display=show?'block':'none';this.setAttribute('aria-expanded',show);">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
						<?php esc_html_e( 'Mostrar las entradas del registro', 'webfiable-info' ); ?>
					</button>
					<div class="webfiable-log-content" style="display:none">
						<table class="webfiable-log-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Hora', 'webfiable-info' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Tipo', 'webfiable-info' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Acción', 'webfiable-info' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Detalles', 'webfiable-info' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $action_log as $entry ) : ?>
									<?php
									$timestamp    = isset( $entry['timestamp'] ) ? absint( $entry['timestamp'] ) : 0;
									$time_str     = $timestamp ? wp_date( 'Y-m-d H:i:s', $timestamp ) : '';
									$level        = isset( $entry['level'] ) ? strtoupper( (string) $entry['level'] ) : '';
									$action       = isset( $entry['action'] ) ? (string) $entry['action'] : '';
									$context      = isset( $entry['context'] ) ? $entry['context'] : array();
									$json_opts    = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
									$context_json = wp_json_encode( $context, $json_opts );
									?>
									<tr>
										<td><code><?php echo esc_html( $time_str ); ?></code></td>
										<td><span class="webfiable-log-level webfiable-log-level--<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $level ); ?></span></td>
										<td><?php echo esc_html( $action ); ?></td>
										<td>
											<?php if ( ! empty( $context_json ) ) : ?>
												<pre><?php echo esc_html( $context_json ); ?></pre>
											<?php else : ?>
												<span class="webfiable-no-details"><?php esc_html_e( 'Sin más detalles.', 'webfiable-info' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		<?php endif; ?>

	</div>
	<?php
}

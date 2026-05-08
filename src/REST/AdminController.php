<?php
/**
 * Controller REST per le operazioni admin del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Gestisce gli endpoint REST dell'area admin: settings, attachment config, stats.
 *
 * Endpoints esposti:
 *   GET  /wppa/v1/admin/settings
 *   POST /wppa/v1/admin/settings
 */
final class AdminController extends RestController {

	/**
	 * Chiave dell'opzione WordPress per le impostazioni globali.
	 */
	const SETTINGS_OPTION = 'wppa_settings';

	/**
	 * Registra le route REST dell'area admin.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_settings_schema(),
				),
			)
		);
	}

	/**
	 * GET /wppa/v1/admin/settings — Restituisce le impostazioni correnti.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$settings = $this->get_current_settings();

		return $this->success( $settings );
	}

	/**
	 * POST /wppa/v1/admin/settings — Aggiorna le impostazioni globali.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$current  = $this->get_current_settings();
		$incoming = $request->get_json_params();

		if ( ! is_array( $incoming ) ) {
			return $this->error( 'invalid_payload', __( 'Payload JSON non valido.', 'wp-paid-attachments' ) );
		}

		$updated = $this->sanitize_settings( array_merge( $current, $incoming ) );

		update_option( self::SETTINGS_OPTION, $updated, false );

		return $this->success( $updated );
	}

	/**
	 * Restituisce le impostazioni correnti con i valori di default come fallback.
	 *
	 * @return array<string, mixed>
	 */
	private function get_current_settings(): array {
		$saved    = get_option( self::SETTINGS_OPTION, array() );
		$defaults = $this->get_defaults();

		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Valori di default per le impostazioni globali.
	 *
	 * @return array<string, mixed>
	 */
	private function get_defaults(): array {
		return array(
			'paypal_mode'                => 'sandbox',
			'paypal_account_type'        => 'business',
			'paypal_client_id'           => '',
			'paypal_client_secret'       => '',
			'paypal_donate_button_id'    => '',
			'paypal_webhook_id'          => '',
			'default_payment_mode'       => 'paypal_donate',
			'default_currency'           => 'EUR',
			'default_suggested_amounts'  => array( 1, 3, 5 ),
			'default_code_validity_days' => 30,
			'default_max_uses'           => 0,
			'allow_free_view'            => true,
			'free_view_daily_limit'      => 10,
			'default_paywall_title'      => '',
			'default_paywall_text'       => '',
			'default_email_subject'      => '',
			'default_email_message'      => '',
			'delete_data_on_uninstall'   => false,
			'master_unlock_code'         => '',
		);
	}

	/**
	 * Sanitizza i valori delle impostazioni prima di salvarle.
	 *
	 * @param array<string, mixed> $settings Impostazioni da sanitizzare.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( array $settings ): array {
		return array(
			'paypal_mode'                => in_array( $settings['paypal_mode'] ?? '', array( 'sandbox', 'live' ), true )
				? $settings['paypal_mode']
				: 'sandbox',
			'paypal_account_type'        => in_array( $settings['paypal_account_type'] ?? '', array( 'business', 'personal' ), true )
				? $settings['paypal_account_type']
				: 'business',
			'paypal_client_id'           => sanitize_text_field( $settings['paypal_client_id'] ?? '' ),
			'paypal_client_secret'       => sanitize_text_field( $settings['paypal_client_secret'] ?? '' ),
			'paypal_donate_button_id'    => sanitize_text_field( $settings['paypal_donate_button_id'] ?? '' ),
			'paypal_webhook_id'          => sanitize_text_field( $settings['paypal_webhook_id'] ?? '' ),
			'default_payment_mode'       => in_array( $settings['default_payment_mode'] ?? '', array( 'paypal_donate', 'paypal_smart', 'both' ), true )
				? $settings['default_payment_mode']
				: 'paypal_donate',
			'default_currency'           => strtoupper( sanitize_text_field( $settings['default_currency'] ?? 'EUR' ) ),
			'default_suggested_amounts'  => array_map(
				'absint',
				is_array( $settings['default_suggested_amounts'] ?? null )
					? $settings['default_suggested_amounts']
					: array( 1, 3, 5 )
			),
			'default_code_validity_days' => absint( $settings['default_code_validity_days'] ?? 30 ),
			'default_max_uses'           => absint( $settings['default_max_uses'] ?? 0 ),
			'allow_free_view'            => (bool) ( $settings['allow_free_view'] ?? true ),
			'free_view_daily_limit'      => absint( $settings['free_view_daily_limit'] ?? 10 ),
			'default_paywall_title'      => wp_kses_post( $settings['default_paywall_title'] ?? '' ),
			'default_paywall_text'       => wp_kses_post( $settings['default_paywall_text'] ?? '' ),
			'default_email_subject'      => sanitize_text_field( $settings['default_email_subject'] ?? '' ),
			'default_email_message'      => wp_kses_post( $settings['default_email_message'] ?? '' ),
			'delete_data_on_uninstall'   => (bool) ( $settings['delete_data_on_uninstall'] ?? false ),
			'master_unlock_code'         => sanitize_text_field( $settings['master_unlock_code'] ?? '' ),
		);
	}

	/**
	 * Schema dei parametri accettati dall'endpoint POST /admin/settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings_schema(): array {
		return array(
			'paypal_mode'                => array(
				'type' => 'string',
				'enum' => array( 'sandbox', 'live' ),
			),
			'paypal_account_type'        => array(
				'type' => 'string',
				'enum' => array( 'business', 'personal' ),
			),
			'paypal_client_id'           => array( 'type' => 'string' ),
			'paypal_client_secret'       => array( 'type' => 'string' ),
			'paypal_donate_button_id'    => array( 'type' => 'string' ),
			'paypal_webhook_id'          => array( 'type' => 'string' ),
			'default_payment_mode'       => array(
				'type' => 'string',
				'enum' => array( 'paypal_donate', 'paypal_smart', 'both' ),
			),
			'default_currency'           => array( 'type' => 'string' ),
			'default_suggested_amounts'  => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
			'default_code_validity_days' => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'default_max_uses'           => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'allow_free_view'            => array( 'type' => 'boolean' ),
			'free_view_daily_limit'      => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'default_paywall_title'      => array( 'type' => 'string' ),
			'default_paywall_text'       => array( 'type' => 'string' ),
			'default_email_subject'      => array( 'type' => 'string' ),
			'default_email_message'      => array( 'type' => 'string' ),
			'delete_data_on_uninstall'   => array( 'type' => 'boolean' ),
			'master_unlock_code'         => array( 'type' => 'string' ),
		);
	}
}

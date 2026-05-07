<?php
/**
 * Provider PayPal Smart Buttons (checkout inline).
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Payment;

/**
 * Gestisce il flusso PayPal Smart Buttons (checkout inline senza redirect).
 *
 * Il flusso è:
 * 1. Il JS chiama `POST /wppa/v1/checkout` → crea un Order PayPal.
 * 2. Smart Buttons cattura il pagamento (PayPal SDK lato client).
 * 3. Il client chiama `POST /wppa/v1/checkout/capture` → registra il Payment in pending.
 * 4. PayPal invia il webhook → WebhookHandler invia il codice via email.
 */
final class PayPalSmartButtonsProvider implements PaymentProviderInterface {

	/**
	 * Base URL API PayPal sandbox.
	 */
	const SANDBOX_API = 'https://api-m.sandbox.paypal.com';

	/**
	 * Base URL API PayPal live.
	 */
	const LIVE_API = 'https://api-m.paypal.com';

	/**
	 * Identificativo provider.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'paypal_smart';
	}

	/**
	 * Crea un Order PayPal v2 e restituisce il payload per il client.
	 *
	 * @param int    $attachment_id ID attachment WP.
	 * @param float  $amount        Importo scelto.
	 * @param string $currency      Codice valuta ISO 4217.
	 * @param string $return_url    URL di ritorno (non usato per Smart Buttons).
	 * @param string $cancel_url    URL di annullamento (non usato).
	 * @return array{payload: array{order_id: string}}|array{error: string}
	 */
	public function create_payment( int $attachment_id, float $amount, string $currency, string $return_url, string $cancel_url ): array {
		unset( $return_url, $cancel_url );

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'error' => $token->get_error_message() );
		}

		$api_base = $this->api_base();
		$response = wp_remote_post(
			$api_base . '/v2/checkout/orders',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'Authorization'     => 'Bearer ' . $token,
					'PayPal-Request-Id' => 'wppa-' . $attachment_id . '-' . time(),
				),
				'body'    => wp_json_encode(
					array(
						'intent'         => 'CAPTURE',
						'purchase_units' => array(
							array(
								'amount'      => array(
									'currency_code' => $currency,
									'value'         => number_format( $amount, 2, '.', '' ),
								),
								'custom_id'   => (string) $attachment_id,
								'description' => get_the_title( $attachment_id ),
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$data     = json_decode( wp_remote_retrieve_body( $response ), true );
		$order_id = is_array( $data ) ? (string) ( $data['id'] ?? '' ) : '';

		if ( ! $order_id ) {
			return array( 'error' => 'PayPal order creation failed.' );
		}

		return array( 'payload' => array( 'order_id' => $order_id ) );
	}

	/**
	 * Processa il webhook PayPal per Smart Buttons (evento PAYMENT.CAPTURE.COMPLETED).
	 *
	 * @param array<string, mixed> $headers Header HTTP.
	 * @param string               $body    Body JSON.
	 * @return array{valid: bool, transaction_id?: string, attachment_id?: int, amount?: float, currency?: string, payer_email?: string, event_type?: string}
	 */
	public function process_webhook( array $headers, string $body ): array {
		$event = json_decode( $body, true );

		if ( ! is_array( $event ) ) {
			return array( 'valid' => false );
		}

		$event_type = (string) ( $event['event_type'] ?? '' );
		if ( 'PAYMENT.CAPTURE.COMPLETED' !== $event_type ) {
			return array( 'valid' => false );
		}

		$resource       = $event['resource'] ?? array();
		$transaction_id = (string) ( $resource['id'] ?? '' );
		$amount         = (float) ( $resource['amount']['value'] ?? 0 );
		$currency       = (string) ( $resource['amount']['currency_code'] ?? 'EUR' );
		$payer_email    = (string) ( $resource['payer']['email_address'] ?? '' );
		$custom_id      = (string) ( $resource['custom_id'] ?? '' );
		$attachment_id  = (int) $custom_id;

		if ( ! $transaction_id || ! $attachment_id ) {
			return array( 'valid' => false );
		}

		return array(
			'valid'          => true,
			'transaction_id' => $transaction_id,
			'attachment_id'  => $attachment_id,
			'amount'         => $amount,
			'currency'       => $currency,
			'payer_email'    => $payer_email,
			'event_type'     => $event_type,
		);
	}

	/**
	 * Ottiene un access token OAuth2 PayPal.
	 *
	 * @return string|\WP_Error Token o errore.
	 */
	public function get_access_token(): string|\WP_Error {
		$settings      = get_option( 'wppa_settings', array() );
		$client_id     = (string) ( $settings['paypal_client_id'] ?? '' );
		$client_secret = (string) ( $settings['paypal_client_secret'] ?? '' );

		if ( ! $client_id || ! $client_secret ) {
			return new \WP_Error( 'missing_credentials', 'PayPal credentials not configured.' );
		}

		$cached = get_transient( 'wppa_paypal_token' );
		if ( $cached ) {
			return (string) $cached;
		}

		$response = wp_remote_post(
			$this->api_base() . '/v1/oauth2/token',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				),
				'body'    => 'grant_type=client_credentials',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data  = json_decode( wp_remote_retrieve_body( $response ), true );
		$token = is_array( $data ) ? (string) ( $data['access_token'] ?? '' ) : '';

		if ( ! $token ) {
			return new \WP_Error( 'token_error', 'PayPal token request failed.' );
		}

		$expires_in = (int) ( is_array( $data ) ? ( $data['expires_in'] ?? 3600 ) : 3600 );
		set_transient( 'wppa_paypal_token', $token, max( 60, $expires_in - 60 ) );

		return $token;
	}

	/**
	 * Restituisce la base URL API in base alla modalità (sandbox/live).
	 *
	 * @return string
	 */
	private function api_base(): string {
		$settings = get_option( 'wppa_settings', array() );
		return 'live' === ( $settings['paypal_mode'] ?? 'sandbox' ) ? self::LIVE_API : self::SANDBOX_API;
	}
}

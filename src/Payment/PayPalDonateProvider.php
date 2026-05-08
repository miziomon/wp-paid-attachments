<?php
/**
 * Provider PayPal Donate (Hosted Button).
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Payment;

/**
 * Gestisce il flusso PayPal Donate (pulsante hosted con redirect).
 *
 * Il flusso è:
 * 1. `create_payment()` restituisce l'URL del form PayPal Donate.
 * 2. L'utente è reindirizzato su paypal.com per completare la donazione.
 * 3. PayPal invia il webhook a `/wppa/v1/webhook/paypal` (gestito da WebhookHandler).
 * 4. WebhookHandler verifica la firma e invia l'email con il codice.
 * 5. La return_url mostra il messaggio "Grazie, controlla la tua email".
 */
final class PayPalDonateProvider implements PaymentProviderInterface {

	/**
	 * URL sandbox PayPal.
	 */
	const SANDBOX_URL = 'https://www.sandbox.paypal.com/donate';

	/**
	 * URL live PayPal.
	 */
	const LIVE_URL = 'https://www.paypal.com/donate';

	/**
	 * Identificativo provider.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'paypal_donate';
	}

	/**
	 * Genera l'URL del form PayPal Donate con parametri.
	 *
	 * @param int    $attachment_id ID attachment WP.
	 * @param float  $amount        Importo suggerito (non vincolante per Donate).
	 * @param string $currency      Codice valuta ISO 4217.
	 * @param string $return_url    URL di ritorno.
	 * @param string $cancel_url    URL di annullamento.
	 * @return array{url: string}
	 */
	public function create_payment( int $attachment_id, float $amount, string $currency, string $return_url, string $cancel_url ): array {
		$settings     = get_option( 'wppa_settings', array() );
		$mode         = (string) ( $settings['paypal_mode'] ?? 'sandbox' );
		$account_type = (string) ( $settings['paypal_account_type'] ?? 'business' );

		$base_url = 'live' === $mode ? self::LIVE_URL : self::SANDBOX_URL;

		// Parametri di callback comuni a entrambe le modalità.
		$common = array(
			'currency_code' => $currency,
			'amount'        => (string) $amount,
			'return'        => $return_url . '&wppa_payment=success&wppa_attachment=' . $attachment_id,
			'cancel_return' => $cancel_url,
			'custom'        => (string) $attachment_id,
		);

		if ( 'personal' === $account_type ) {
			// Conto Personale: form Donate "non-hosted" con merchant ID nel campo 'business'.
			// La conferma del pagamento arriva tramite IPN (notify_url).
			$merchant_id = (string) ( $settings['paypal_merchant_id'] ?? '' );

			$params = array_merge(
				array(
					'business'     => $merchant_id,
					'item_name'    => sprintf(
						/* translators: %d: ID dell'attachment. */
						__( 'Donazione per attachment #%d', 'wp-paid-attachments' ),
						$attachment_id
					),
					'no_recurring' => '1',
					'notify_url'   => rest_url( 'wppa/v1/ipn/paypal' ),
				),
				$common
			);
		} else {
			// Conto Business: Hosted Button (configurazione lato PayPal). La conferma
			// arriva tramite Webhook v2, non serve notify_url.
			$button_id = (string) ( $settings['paypal_donate_button_id'] ?? '' );

			$params = array_merge(
				array( 'hosted_button_id' => $button_id ),
				$common
			);
		}

		return array( 'url' => $base_url . '?' . http_build_query( $params ) );
	}

	/**
	 * Processa il webhook PayPal Donate.
	 *
	 * La verifica della firma è delegata al WebhookHandler che usa
	 * le PayPal Webhooks v1 API. Qui il provider estrae solo i dati
	 * dalla struttura dell'evento già verificato.
	 *
	 * @param array<string, mixed> $headers Header HTTP.
	 * @param string               $body    Body JSON del webhook.
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
}

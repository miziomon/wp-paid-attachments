<?php
/**
 * Verifica delle notifiche IPN (Instant Payment Notification) PayPal.
 *
 * IPN è il sistema legacy di notifica server-to-server di PayPal,
 * compatibile con i Conti Personali (a differenza dei Webhook v2 che
 * richiedono un Conto Business). Il flusso di verifica è:
 *
 *   1. PayPal POSTa i dati della transazione al nostro endpoint.
 *   2. Noi rispediamo gli stessi dati a PayPal con `cmd=_notify-validate`.
 *   3. PayPal risponde "VERIFIED" o "INVALID".
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Payment;

use WP_Error;

/**
 * Verifica IPN PayPal effettuando il round-trip su `ipnpb.paypal.com`.
 */
final class IpnVerifier {

	/**
	 * URL di verifica IPN per ambiente sandbox.
	 */
	const SANDBOX_VERIFY_URL = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';

	/**
	 * URL di verifica IPN per ambiente live.
	 */
	const LIVE_VERIFY_URL = 'https://ipnpb.paypal.com/cgi-bin/webscr';

	/**
	 * Verifica un payload IPN ricevuto da PayPal.
	 *
	 * @param string $raw_body Body grezzo POST ricevuto (form-urlencoded).
	 * @param string $mode     'sandbox' o 'live'.
	 * @return bool|WP_Error True se VERIFIED, false se INVALID, WP_Error su errore di trasporto.
	 */
	public function verify( string $raw_body, string $mode ): bool|WP_Error {
		if ( '' === $raw_body ) {
			return new WP_Error( 'ipn_empty_body', __( 'Body IPN vuoto.', 'wp-paid-attachments' ) );
		}

		$verify_url = 'live' === $mode ? self::LIVE_VERIFY_URL : self::SANDBOX_VERIFY_URL;

		// PayPal richiede di rispedire IL BODY ESATTO ricevuto, prefissato con cmd=_notify-validate.
		$verification_body = 'cmd=_notify-validate&' . $raw_body;

		$response = wp_remote_post(
			$verify_url,
			array(
				'timeout'     => 15,
				'httpversion' => '1.1',
				'headers'     => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
					'Connection'   => 'Close',
					'User-Agent'   => 'WP-Paid-Attachments/' . WPPA_VERSION,
				),
				'body'        => $verification_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new WP_Error(
				'ipn_http_error',
				sprintf( /* translators: %d: HTTP status code. */ __( 'Verifica IPN fallita: HTTP %d.', 'wp-paid-attachments' ), $status )
			);
		}

		$result = trim( (string) wp_remote_retrieve_body( $response ) );

		return 'VERIFIED' === $result;
	}

	/**
	 * Estrae i campi rilevanti da un payload IPN form-urlencoded.
	 *
	 * Mappa i campi PayPal IPN sul formato comune usato dal WebhookController:
	 *   - txn_id          → transaction_id
	 *   - custom          → attachment_id (passato via parametro custom del Donate URL)
	 *   - mc_gross        → amount
	 *   - mc_currency     → currency
	 *   - payer_email     → payer_email
	 *   - payment_status  → mappato su event_type (solo "Completed" è valido)
	 *
	 * @param string $raw_body Body grezzo form-urlencoded.
	 * @return array{valid: bool, transaction_id?: string, attachment_id?: int, amount?: float, currency?: string, payer_email?: string, event_type?: string}
	 */
	public function parse( string $raw_body ): array {
		$fields = array();
		parse_str( $raw_body, $fields );

		if ( ! is_array( $fields ) ) {
			return array( 'valid' => false );
		}

		$payment_status = (string) ( $fields['payment_status'] ?? '' );
		if ( 'Completed' !== $payment_status ) {
			return array( 'valid' => false );
		}

		$transaction_id = (string) ( $fields['txn_id'] ?? '' );
		$custom         = (string) ( $fields['custom'] ?? '' );
		$attachment_id  = (int) $custom;
		$amount         = (float) ( $fields['mc_gross'] ?? 0 );
		$currency       = (string) ( $fields['mc_currency'] ?? 'EUR' );
		$payer_email    = sanitize_email( (string) ( $fields['payer_email'] ?? '' ) );

		if ( '' === $transaction_id || $attachment_id <= 0 || '' === $payer_email ) {
			return array( 'valid' => false );
		}

		return array(
			'valid'          => true,
			'transaction_id' => $transaction_id,
			'attachment_id'  => $attachment_id,
			'amount'         => $amount,
			'currency'       => $currency,
			'payer_email'    => $payer_email,
			'event_type'     => 'IPN.PAYMENT.COMPLETED',
		);
	}
}

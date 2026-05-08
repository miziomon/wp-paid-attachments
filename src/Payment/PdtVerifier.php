<?php
/**
 * Verifica delle conferme PDT (Payment Data Transfer) PayPal.
 *
 * PDT è il sistema di conferma "synchronous" di PayPal: quando l'utente
 * torna sul sito dopo una donazione, l'URL include `?tx=<TRANSACTION_ID>`
 * e il nostro server può verificarlo via round-trip a PayPal.
 *
 * Differenze chiave rispetto all'IPN:
 *  - Outbound (server → PayPal), funziona anche da localhost dietro NAT
 *  - Sincrono: una sola chiamata, niente retry
 *  - Si attiva solo quando l'utente clicca "Torna al sito" su PayPal
 *
 * Per attivarlo: PayPal Profile → Strumenti di vendita → Trasferimento
 * dati di pagamento (PDT) → ON, copiare l'Identity Token nelle impostazioni.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Payment;

use WP_Error;

/**
 * Verifica PDT effettuando il round-trip su `paypal.com/cgi-bin/webscr`.
 */
final class PdtVerifier {

	/**
	 * URL di verifica PDT per ambiente sandbox.
	 */
	const SANDBOX_VERIFY_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

	/**
	 * URL di verifica PDT per ambiente live.
	 */
	const LIVE_VERIFY_URL = 'https://www.paypal.com/cgi-bin/webscr';

	/**
	 * Verifica un transaction ID PDT con PayPal.
	 *
	 * @param string $tx       Transaction ID ricevuto in `?tx=...`.
	 * @param string $auth_token Identity token PDT dalle impostazioni utente PayPal.
	 * @param string $mode     'sandbox' o 'live'.
	 * @return array<string, string>|WP_Error Mappa key=>value dei campi della transazione, o WP_Error.
	 */
	public function verify( string $tx, string $auth_token, string $mode ): array|WP_Error {
		if ( '' === $tx ) {
			return new WP_Error( 'pdt_empty_tx', __( 'Transaction ID PDT vuoto.', 'wp-paid-attachments' ) );
		}

		if ( '' === $auth_token ) {
			return new WP_Error( 'pdt_no_token', __( 'PDT Identity Token non configurato.', 'wp-paid-attachments' ) );
		}

		$verify_url = 'live' === $mode ? self::LIVE_VERIFY_URL : self::SANDBOX_VERIFY_URL;

		$body = http_build_query(
			array(
				'cmd' => '_notify-synch',
				'tx'  => $tx,
				'at'  => $auth_token,
			)
		);

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
				'body'        => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new WP_Error(
				'pdt_http_error',
				sprintf( /* translators: %d: HTTP status code. */ __( 'Verifica PDT fallita: HTTP %d.', 'wp-paid-attachments' ), $status )
			);
		}

		$raw = (string) wp_remote_retrieve_body( $response );

		// Risposta PayPal: prima riga "SUCCESS" o "FAIL", righe successive key=value.
		$lines = preg_split( '/\r\n|\r|\n/', trim( $raw ) );
		if ( ! is_array( $lines ) || empty( $lines ) ) {
			return new WP_Error( 'pdt_empty_response', __( 'Risposta PDT vuota.', 'wp-paid-attachments' ) );
		}

		$status_line = array_shift( $lines );
		if ( 'SUCCESS' !== $status_line ) {
			return new WP_Error( 'pdt_failed', __( 'PayPal ha rifiutato la verifica PDT (FAIL).', 'wp-paid-attachments' ) );
		}

		// Parsing key=value (i valori sono URL-encoded).
		$fields = array();
		foreach ( $lines as $line ) {
			if ( '' === $line || ! str_contains( $line, '=' ) ) {
				continue;
			}
			list( $key, $value ) = explode( '=', $line, 2 );
			$fields[ $key ]      = urldecode( $value );
		}

		return $fields;
	}

	/**
	 * Estrae i campi rilevanti dal payload PDT verificato.
	 *
	 * Mappa i campi PayPal sul formato comune usato dai webhook controller.
	 *
	 * @param array<string, string> $fields Campi PDT verificati.
	 * @return array{valid: bool, transaction_id?: string, attachment_id?: int, amount?: float, currency?: string, payer_email?: string, event_type?: string}
	 */
	public function parse( array $fields ): array {
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
			'event_type'     => 'PDT.PAYMENT.COMPLETED',
		);
	}
}

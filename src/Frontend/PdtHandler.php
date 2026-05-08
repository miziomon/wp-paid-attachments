<?php
/**
 * Handler PDT: intercetta il ritorno utente da PayPal con `?tx=...`,
 * verifica la transazione e completa il pagamento (codice + email).
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Frontend;

use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Database\PaymentRepository;
use PaidAttachments\Email\EmailSender;
use PaidAttachments\Payment\PdtVerifier;

/**
 * Gestisce il flusso PDT (Payment Data Transfer) di PayPal.
 *
 * Quando un utente torna da una donazione PayPal Donate, il browser
 * arriva a `https://sito/attachment/N/?wppa_payment=success&tx=ABC...`.
 * Questo handler:
 *  1. Verifica la transazione con PayPal (round-trip server-to-server).
 *  2. Idempotenza su `provider_transaction_id`.
 *  3. Genera codice di sblocco e invia email (riusa EmailSender).
 *  4. Redirige sull'attachment con `?wppa_unlock=<codice>` per auto-validare.
 *
 * Funziona anche da localhost dietro NAT (la chiamata è outbound).
 */
final class PdtHandler {

	/**
	 * Verifier PDT.
	 *
	 * @var PdtVerifier
	 */
	private PdtVerifier $verifier;

	/**
	 * Repository pagamenti.
	 *
	 * @var PaymentRepository
	 */
	private PaymentRepository $payment_repo;

	/**
	 * Repository configurazioni attachment.
	 *
	 * @var AttachmentConfigRepository
	 */
	private AttachmentConfigRepository $config_repo;

	/**
	 * Sender email.
	 *
	 * @var EmailSender
	 */
	private EmailSender $email_sender;

	/**
	 * Costruttore.
	 *
	 * @param PdtVerifier                $verifier     Verifier PDT.
	 * @param PaymentRepository          $payment_repo Repository pagamenti.
	 * @param AttachmentConfigRepository $config_repo  Repository configurazioni.
	 * @param EmailSender                $email_sender Sender email.
	 */
	public function __construct(
		PdtVerifier $verifier,
		PaymentRepository $payment_repo,
		AttachmentConfigRepository $config_repo,
		EmailSender $email_sender
	) {
		$this->verifier     = $verifier;
		$this->payment_repo = $payment_repo;
		$this->config_repo  = $config_repo;
		$this->email_sender = $email_sender;
	}

	/**
	 * Registra l'hook `template_redirect`.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_handle_pdt' ) );
	}

	/**
	 * Intercetta `?wppa_payment=success&tx=...` su una attachment page.
	 *
	 * @return void
	 */
	public function maybe_handle_pdt(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verifica firma PDT lato PayPal.
		if ( ! is_attachment() ) {
			return;
		}

		$payment = isset( $_GET['wppa_payment'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['wppa_payment'] ) ) : '';
		$tx      = isset( $_GET['tx'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tx'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'success' !== $payment || '' === $tx ) {
			return;
		}

		$attachment_id = (int) get_queried_object_id();
		if ( $attachment_id <= 0 || ! $this->config_repo->is_protected( $attachment_id ) ) {
			return;
		}

		$settings  = get_option( 'wppa_settings', array() );
		$mode      = isset( $settings['paypal_mode'] ) && 'live' === $settings['paypal_mode'] ? 'live' : 'sandbox';
		$pdt_token = (string) ( $settings['paypal_pdt_token'] ?? '' );

		// Step 1: verifica PDT con PayPal.
		$verified = $this->verifier->verify( $tx, $pdt_token, $mode );
		if ( is_wp_error( $verified ) ) {
			// Verifica fallita: lascia che la pagina renderizzi normalmente (paywall).
			// L'IPN potrebbe arrivare comunque e completare il pagamento in modo asincrono.
			return;
		}

		// Step 2: estrai i campi.
		$transaction = $this->verifier->parse( $verified );
		if ( ! $transaction['valid'] ) {
			return;
		}

		// Sicurezza: il `custom` deve coincidere con l'attachment_id della pagina corrente.
		if ( (int) $transaction['attachment_id'] !== $attachment_id ) {
			return;
		}

		$transaction_id = (string) $transaction['transaction_id'];
		$amount         = (float) $transaction['amount'];
		$currency       = (string) $transaction['currency'];
		$payer_email    = (string) $transaction['payer_email'];

		// Step 3: idempotenza — se IPN ha già completato il pagamento, recupera il record.
		$existing = $this->payment_repo->find_by_transaction_id( $transaction_id );
		if ( null !== $existing && 'completed' === $existing->status ) {
			// Pagamento già completato (IPN arrivato prima): redirige senza ri-emettere il codice.
			// L'utente riceverà / ha già ricevuto l'email separatamente.
			$this->redirect_with_message( $attachment_id, true );
			return;
		}

		// Step 4: salva o aggiorna il Payment.
		$payment_id = null !== $existing
			? $existing->id
			: $this->payment_repo->insert(
				array(
					'attachment_id'           => $attachment_id,
					'provider'                => 'paypal_pdt',
					'provider_transaction_id' => $transaction_id,
					'donor_email'             => $payer_email,
					'amount'                  => $amount,
					'currency'                => $currency,
					'status'                  => 'pending',
					'metadata'                => array( 'event' => $transaction['event_type'] ),
				)
			);

		if ( ! $payment_id ) {
			return;
		}

		// Step 5: recupera config attachment.
		$config = $this->config_repo->find_by_attachment_id( $attachment_id );
		if ( ! $config ) {
			return;
		}

		// Step 6: genera codice + manda email; recupera il plain code per auto-validazione.
		$plain_code = $this->email_sender->send_unlock_email( $payer_email, $attachment_id, $config, $payment_id );
		$this->payment_repo->update_status( $payment_id, null !== $plain_code ? 'completed' : 'failed', null );

		if ( null === $plain_code ) {
			// Email fallita: pagamento valido ma comunicazione rotta. Lascia il paywall.
			return;
		}

		// Step 7: redirige sull'attachment con il codice in `?wppa_unlock=` per auto-validare.
		$redirect_url = add_query_arg(
			array(
				'wppa_unlock' => rawurlencode( $plain_code ),
			),
			get_permalink( $attachment_id )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Redirige sull'attachment con un parametro che il widget può intercettare
	 * per mostrare un messaggio "Pagamento già confermato — controlla l'email".
	 *
	 * @param int  $attachment_id ID attachment.
	 * @param bool $already       True se il pagamento era già stato completato (idempotenza IPN/PDT).
	 * @return void
	 */
	private function redirect_with_message( int $attachment_id, bool $already ): void {
		$redirect_url = add_query_arg(
			array(
				'wppa_status' => $already ? 'already_paid' : 'completed',
			),
			get_permalink( $attachment_id )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}
}

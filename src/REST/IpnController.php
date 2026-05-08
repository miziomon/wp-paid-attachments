<?php
/**
 * Controller REST per le notifiche IPN PayPal (Conti Personali).
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Database\PaymentRepository;
use PaidAttachments\Email\EmailSender;
use PaidAttachments\Payment\IpnVerifier;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Riceve e processa le IPN PayPal.
 *
 * Endpoint esposto:
 *   POST /wppa/v1/ipn/paypal
 *
 * Differenze rispetto al WebhookController:
 *  - Body: form-urlencoded (non JSON).
 *  - Verifica: round-trip a `ipnpb.paypal.com` con `cmd=_notify-validate`.
 *  - Compatibile con account PayPal Personal (Webhook v2 richiede Business).
 *
 * Idempotenza, scrittura Payment, generazione codice e invio email
 * sono identici al flusso webhook.
 */
final class IpnController extends RestController {

	/**
	 * Verifier IPN.
	 *
	 * @var IpnVerifier
	 */
	private IpnVerifier $verifier;

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
	 * Sender email per i codici di sblocco.
	 *
	 * @var EmailSender
	 */
	private EmailSender $email_sender;

	/**
	 * Costruttore.
	 *
	 * @param IpnVerifier                $verifier     Verifier IPN.
	 * @param PaymentRepository          $payment_repo Repository pagamenti.
	 * @param AttachmentConfigRepository $config_repo  Repository configurazioni.
	 * @param EmailSender                $email_sender Sender email.
	 */
	public function __construct(
		IpnVerifier $verifier,
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
	 * Registra le route REST.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/ipn/paypal',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_ipn' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST /wppa/v1/ipn/paypal — Riceve e processa l'IPN PayPal.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_ipn( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$raw_body = $request->get_body();
		$settings = get_option( 'wppa_settings', array() );
		$mode     = isset( $settings['paypal_mode'] ) && 'live' === $settings['paypal_mode'] ? 'live' : 'sandbox';

		// Step 1: round-trip di verifica con PayPal.
		$verified = $this->verifier->verify( $raw_body, $mode );
		if ( is_wp_error( $verified ) ) {
			return $this->error( 'ipn_verify_error', $verified->get_error_message(), 500 );
		}

		if ( true !== $verified ) {
			return $this->error( 'ipn_invalid', __( 'IPN non verificata da PayPal.', 'wp-paid-attachments' ), 401 );
		}

		// Step 2: estrai i campi rilevanti.
		$transaction = $this->verifier->parse( $raw_body );
		if ( ! $transaction['valid'] ) {
			// IPN verificata ma non rilevante (es. payment_status != Completed): rispondi 200 senza fare nulla.
			return $this->success( array( 'status' => 'ignored' ) );
		}

		$transaction_id = (string) $transaction['transaction_id'];
		$attachment_id  = (int) $transaction['attachment_id'];
		$amount         = (float) $transaction['amount'];
		$currency       = (string) $transaction['currency'];
		$payer_email    = (string) $transaction['payer_email'];

		// Step 3: idempotenza su transaction_id.
		$existing = $this->payment_repo->find_by_transaction_id( $transaction_id );
		if ( null !== $existing && 'completed' === $existing->status ) {
			return $this->success( array( 'status' => 'already_processed' ) );
		}

		// Step 4: salva o aggiorna il Payment.
		$payment_id = null !== $existing
			? $existing->id
			: $this->payment_repo->insert(
				array(
					'attachment_id'           => $attachment_id,
					'provider'                => 'paypal_ipn',
					'provider_transaction_id' => $transaction_id,
					'donor_email'             => $payer_email,
					'amount'                  => $amount,
					'currency'                => $currency,
					'status'                  => 'pending',
					'metadata'                => array( 'event' => $transaction['event_type'] ),
				)
			);

		if ( ! $payment_id ) {
			return $this->error( 'db_error', __( 'Errore database.', 'wp-paid-attachments' ), 500 );
		}

		// Step 5: recupera config attachment.
		$config = $this->config_repo->find_by_attachment_id( $attachment_id );
		if ( ! $config ) {
			return $this->error( 'config_not_found', __( 'Configurazione attachment non trovata.', 'wp-paid-attachments' ), 500 );
		}

		// Step 6: genera codice e invia email.
		$sent = $this->email_sender->send_unlock_email( $payer_email, $attachment_id, $config, $payment_id );

		$this->payment_repo->update_status( $payment_id, $sent ? 'completed' : 'failed', null );

		if ( ! $sent ) {
			return $this->error( 'email_error', __( 'Invio email fallito.', 'wp-paid-attachments' ), 500 );
		}

		return $this->success( array( 'status' => 'processed' ) );
	}
}

<?php
/**
 * Controller REST per il webhook PayPal.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Database\PaymentRepository;
use PaidAttachments\Database\UnlockCodeRepository;
use PaidAttachments\Email\EmailSender;
use PaidAttachments\Email\TemplateRenderer;
use PaidAttachments\Payment\PaymentProviderInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Gestisce i webhook PayPal in ingresso.
 *
 * Endpoint esposto:
 *   POST /wppa/v1/webhook/paypal
 *
 * Il controller:
 * 1. Verifica la firma PayPal via API (PAYPAL-TRANSMISSION-SIG header).
 * 2. Controlla l'idempotenza tramite `provider_transaction_id` UNIQUE.
 * 3. Aggiorna lo stato del Payment nel DB.
 * 4. Genera il codice di sblocco e invia l'email.
 */
final class WebhookController extends RestController {

	/**
	 * Provider di pagamento registrati.
	 *
	 * @var PaymentProviderInterface[]
	 */
	private array $providers;

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
	 * @param PaymentProviderInterface[] $providers    Provider di pagamento.
	 * @param PaymentRepository          $payment_repo Repository pagamenti.
	 * @param AttachmentConfigRepository $config_repo  Repository configurazioni.
	 * @param EmailSender                $email_sender Sender email.
	 */
	public function __construct(
		array $providers,
		PaymentRepository $payment_repo,
		AttachmentConfigRepository $config_repo,
		EmailSender $email_sender
	) {
		$this->providers    = $providers;
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
			'/webhook/paypal',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST /wppa/v1/webhook/paypal — Riceve e processa il webhook PayPal.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body    = $request->get_body();
		$headers = $this->extract_headers( $request );

		// Verifica firma PayPal.
		if ( ! $this->verify_paypal_signature( $headers, $body ) ) {
			return $this->error( 'invalid_signature', __( 'Firma webhook non valida.', 'wp-paid-attachments' ), 401 );
		}

		// Trova il provider corretto in base al tipo di evento.
		$event_data = json_decode( $body, true );
		if ( ! is_array( $event_data ) ) {
			return $this->error( 'invalid_payload', __( 'Payload JSON non valido.', 'wp-paid-attachments' ) );
		}

		$provider = $this->find_provider_for_event( $event_data );
		if ( null === $provider ) {
			// Evento non gestito — risponde 200 per non bloccare PayPal.
			return $this->success( array( 'status' => 'ignored' ) );
		}

		$transaction = $provider->process_webhook( $headers, $body );
		if ( ! $transaction['valid'] ) {
			return $this->success( array( 'status' => 'ignored' ) );
		}

		$transaction_id = (string) ( $transaction['transaction_id'] ?? '' );
		$attachment_id  = (int) ( $transaction['attachment_id'] ?? 0 );
		$amount         = (float) ( $transaction['amount'] ?? 0 );
		$currency       = (string) ( $transaction['currency'] ?? 'EUR' );
		$payer_email    = (string) ( $transaction['payer_email'] ?? '' );

		if ( ! $transaction_id || ! $attachment_id || ! $payer_email ) {
			return $this->success( array( 'status' => 'ignored' ) );
		}

		// Idempotenza: se il transaction_id è già nel DB, ignora silenziosamente.
		$existing = $this->payment_repo->find_by_transaction_id( $transaction_id );
		if ( null !== $existing && 'completed' === $existing->status ) {
			return $this->success( array( 'status' => 'already_processed' ) );
		}

		// Salva o aggiorna il Payment.
		$payment_id = null !== $existing
			? $existing->id
			: $this->payment_repo->insert(
				array(
					'attachment_id'           => $attachment_id,
					'provider'                => $provider->get_id(),
					'provider_transaction_id' => $transaction_id,
					'donor_email'             => $payer_email,
					'amount'                  => $amount,
					'currency'                => $currency,
					'status'                  => 'pending',
					'metadata'                => array( 'event' => $event_data['event_type'] ?? '' ),
				)
			);

		if ( ! $payment_id ) {
			return $this->error( 'db_error', __( 'Errore database.', 'wp-paid-attachments' ), 500 );
		}

		// Recupera la configurazione attachment.
		$config = $this->config_repo->find_by_attachment_id( $attachment_id );
		if ( ! $config ) {
			return $this->error( 'config_not_found', __( 'Configurazione attachment non trovata.', 'wp-paid-attachments' ), 500 );
		}

		// Genera codice e invia email.
		$sent = $this->email_sender->send_unlock_email( $payer_email, $attachment_id, $config, $payment_id );

		// Aggiorna stato payment.
		$this->payment_repo->update_status( $payment_id, $sent ? 'completed' : 'failed', null );

		if ( ! $sent ) {
			return $this->error( 'email_error', __( 'Invio email fallito.', 'wp-paid-attachments' ), 500 );
		}

		return $this->success( array( 'status' => 'processed' ) );
	}

	/**
	 * Verifica la firma del webhook PayPal tramite le PayPal Webhooks v1 API.
	 *
	 * In ambiente sandbox usa una verifica semplificata (accetta sempre).
	 *
	 * @param array<string, mixed> $headers Header HTTP.
	 * @param string               $body    Body grezzo.
	 * @return bool
	 */
	private function verify_paypal_signature( array $headers, string $body ): bool {
		$settings = get_option( 'wppa_settings', array() );
		$mode     = (string) ( $settings['paypal_mode'] ?? 'sandbox' );

		// In sandbox accettiamo sempre (PayPal Webhooks Simulator).
		if ( 'sandbox' === $mode ) {
			return true;
		}

		$webhook_id    = (string) ( $settings['paypal_webhook_id'] ?? '' );
		$client_id     = (string) ( $settings['paypal_client_id'] ?? '' );
		$client_secret = (string) ( $settings['paypal_client_secret'] ?? '' );

		if ( ! $webhook_id || ! $client_id || ! $client_secret ) {
			return false;
		}

		$transmission_id   = (string) ( $headers['PAYPAL-TRANSMISSION-ID'] ?? '' );
		$transmission_time = (string) ( $headers['PAYPAL-TRANSMISSION-TIME'] ?? '' );
		$cert_url          = (string) ( $headers['PAYPAL-CERT-URL'] ?? '' );
		$transmission_sig  = (string) ( $headers['PAYPAL-TRANSMISSION-SIG'] ?? '' );
		$auth_algo         = (string) ( $headers['PAYPAL-AUTH-ALGO'] ?? '' );

		$provider = new \PaidAttachments\Payment\PayPalSmartButtonsProvider();
		$token    = $provider->get_access_token();
		if ( is_wp_error( $token ) ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api-m.paypal.com/v1/notifications/verify-webhook-signature',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => wp_json_encode(
					array(
						'auth_algo'         => $auth_algo,
						'cert_url'          => $cert_url,
						'transmission_id'   => $transmission_id,
						'transmission_sig'  => $transmission_sig,
						'transmission_time' => $transmission_time,
						'webhook_id'        => $webhook_id,
						'webhook_event'     => json_decode( $body, true ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) && 'SUCCESS' === ( $data['verification_status'] ?? '' );
	}

	/**
	 * Trova il provider che gestisce il tipo di evento ricevuto.
	 *
	 * @param array<string, mixed> $event Evento PayPal decodificato.
	 * @return PaymentProviderInterface|null
	 */
	private function find_provider_for_event( array $event ): ?PaymentProviderInterface {
		foreach ( $this->providers as $provider ) {
			$result = $provider->process_webhook( array(), wp_json_encode( $event ) );
			if ( $result['valid'] ) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * Estrae gli header rilevanti dalla request REST.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return array<string, string>
	 */
	private function extract_headers( WP_REST_Request $request ): array {
		$paypal_headers = array(
			'PAYPAL-TRANSMISSION-ID',
			'PAYPAL-TRANSMISSION-TIME',
			'PAYPAL-CERT-URL',
			'PAYPAL-TRANSMISSION-SIG',
			'PAYPAL-AUTH-ALGO',
		);

		$headers = array();
		foreach ( $paypal_headers as $header ) {
			$value = $request->get_header( strtolower( str_replace( '_', '-', $header ) ) );
			if ( $value ) {
				$headers[ $header ] = $value;
			}
		}

		return $headers;
	}
}

<?php
/**
 * Controller REST per il checkout PayPal Smart Buttons.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Database\PaymentRepository;
use PaidAttachments\Payment\PayPalDonateProvider;
use PaidAttachments\Payment\PayPalSmartButtonsProvider;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Gestisce la creazione e il pending del pagamento Smart Buttons.
 *
 * Endpoints esposti:
 *   POST /wppa/v1/checkout        — Crea un Order PayPal, restituisce order_id
 *   POST /wppa/v1/checkout/capture — Registra il pagamento in stato pending
 *                                    (il completamento avviene via webhook)
 */
final class CheckoutController extends RestController {

	/**
	 * Repository configurazioni attachment.
	 *
	 * @var AttachmentConfigRepository
	 */
	private AttachmentConfigRepository $config_repo;

	/**
	 * Repository pagamenti.
	 *
	 * @var PaymentRepository
	 */
	private PaymentRepository $payment_repo;

	/**
	 * Provider Smart Buttons.
	 *
	 * @var PayPalSmartButtonsProvider
	 */
	private PayPalSmartButtonsProvider $provider;

	/**
	 * Costruttore.
	 *
	 * @param AttachmentConfigRepository $config_repo  Repository configurazioni.
	 * @param PaymentRepository          $payment_repo Repository pagamenti.
	 * @param PayPalSmartButtonsProvider $provider     Provider Smart Buttons.
	 */
	public function __construct(
		AttachmentConfigRepository $config_repo,
		PaymentRepository $payment_repo,
		PayPalSmartButtonsProvider $provider
	) {
		$this->config_repo  = $config_repo;
		$this->payment_repo = $payment_repo;
		$this->provider     = $provider;
	}

	/**
	 * Registra le route REST.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_order' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'amount'        => array(
						'type'     => 'number',
						'required' => true,
						'minimum'  => 0.01,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/donate-url',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'build_donate_url' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'amount'        => array(
						'type'     => 'number',
						'required' => true,
						'minimum'  => 0.01,
					),
					'return_url'    => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/checkout/capture',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'capture_order' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'order_id'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'payer_email'   => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);
	}

	/**
	 * POST /wppa/v1/checkout — Crea un Order PayPal v2.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_order( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$amount        = (float) $request->get_param( 'amount' );

		if ( ! $this->config_repo->is_protected( $attachment_id ) ) {
			return $this->error( 'not_protected', __( 'Attachment non protetto.', 'wp-paid-attachments' ), 404 );
		}

		$config   = $this->config_repo->find_by_attachment_id( $attachment_id );
		$currency = $config ? $config->currency : 'EUR';

		$result = $this->provider->create_payment( $attachment_id, $amount, $currency, '', '' );

		if ( isset( $result['error'] ) ) {
			return $this->error( 'paypal_error', $result['error'], 502 );
		}

		return $this->success( $result['payload'] ?? array(), 201 );
	}

	/**
	 * POST /wppa/v1/donate-url — Costruisce l'URL del form PayPal Donate.
	 *
	 * Usato dal Web Component frontend in modalità `paypal_donate` per
	 * ottenere l'URL corretto (con `business`+`notify_url` per Personal,
	 * `hosted_button_id` per Business). La logica di branching è in
	 * `PayPalDonateProvider::create_payment()`.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function build_donate_url( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$amount        = (float) $request->get_param( 'amount' );
		$return_url    = (string) $request->get_param( 'return_url' );

		if ( ! $this->config_repo->is_protected( $attachment_id ) ) {
			return $this->error( 'not_protected', __( 'Attachment non protetto.', 'wp-paid-attachments' ), 404 );
		}

		$config   = $this->config_repo->find_by_attachment_id( $attachment_id );
		$currency = $config ? $config->currency : 'EUR';

		// Fallback: se return_url non fornito, usa la pagina dell'attachment.
		if ( '' === $return_url ) {
			$return_url = (string) get_permalink( $attachment_id );
		}

		$cancel_url = $return_url . ( str_contains( $return_url, '?' ) ? '&' : '?' ) . 'wppa_payment=cancel';

		$donate_provider = new PayPalDonateProvider();
		$result          = $donate_provider->create_payment( $attachment_id, $amount, $currency, $return_url, $cancel_url );

		if ( ! isset( $result['url'] ) ) {
			return $this->error( 'donate_url_error', __( 'Impossibile costruire URL Donate.', 'wp-paid-attachments' ), 500 );
		}

		return $this->success( array( 'url' => $result['url'] ) );
	}

	/**
	 * POST /wppa/v1/checkout/capture — Registra il pagamento in stato pending.
	 *
	 * Il completamento effettivo (cambio stato a "completed" + invio email)
	 * avviene ESCLUSIVAMENTE via webhook PayPal verificato.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function capture_order( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$order_id      = sanitize_text_field( (string) $request->get_param( 'order_id' ) );
		$payer_email   = (string) $request->get_param( 'payer_email' );

		if ( ! $this->config_repo->is_protected( $attachment_id ) ) {
			return $this->error( 'not_protected', __( 'Attachment non protetto.', 'wp-paid-attachments' ), 404 );
		}

		// Idempotenza.
		$existing = $this->payment_repo->find_by_transaction_id( $order_id );
		if ( null !== $existing ) {
			return $this->success(
				array(
					'status'  => 'pending',
					'message' => __( 'Pagamento già registrato, attendi la conferma email.', 'wp-paid-attachments' ),
				)
			);
		}

		$this->payment_repo->insert(
			array(
				'attachment_id'           => $attachment_id,
				'provider'                => 'paypal_smart',
				'provider_transaction_id' => $order_id,
				'donor_email'             => $payer_email,
				'amount'                  => 0,
				'currency'                => 'EUR',
				'status'                  => 'pending',
				'metadata'                => array( 'source' => 'checkout_capture' ),
			)
		);

		return $this->success(
			array(
				'status'  => 'pending',
				'message' => __( 'Pagamento registrato. Riceverai un\'email con il codice di sblocco a breve.', 'wp-paid-attachments' ),
			)
		);
	}
}

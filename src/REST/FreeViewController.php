<?php
/**
 * Controller REST per la visualizzazione gratuita (one-time per sessione).
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Support\Hmac;
use PaidAttachments\Support\RateLimiter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Gestisce l'endpoint REST per la richiesta di visualizzazione gratuita.
 *
 * Endpoint esposto:
 *   POST /wppa/v1/free-view
 */
final class FreeViewController extends RestController {

	/**
	 * Limite di free-view per IP al giorno.
	 */
	const FREE_VIEW_LIMIT = 10;

	/**
	 * Finestra rate limiting free-view (86400 = 1 giorno).
	 */
	const FREE_VIEW_WINDOW = 86400;

	/**
	 * Repository configurazioni attachment.
	 *
	 * @var AttachmentConfigRepository
	 */
	private AttachmentConfigRepository $config_repo;

	/**
	 * Costruttore.
	 *
	 * @param AttachmentConfigRepository $config_repo Repository configurazioni.
	 */
	public function __construct( AttachmentConfigRepository $config_repo ) {
		$this->config_repo = $config_repo;
	}

	/**
	 * Registra le route REST.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/free-view',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'request_free_view' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);
	}

	/**
	 * POST /wppa/v1/free-view — Restituisce un token per visualizzazione gratuita.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function request_free_view( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$ip            = $this->get_client_ip();

		// Verifica che l'attachment sia protetto e che la free view sia abilitata.
		$config = $this->config_repo->find_by_attachment_id( $attachment_id );
		if ( ! $config || ! $config->enabled ) {
			return $this->error( 'not_protected', __( 'Attachment non trovato o non protetto.', 'wp-paid-attachments' ), 404 );
		}

		if ( ! $config->allow_free_view ) {
			return $this->error( 'free_view_disabled', __( 'La visualizzazione gratuita non è disponibile per questo attachment.', 'wp-paid-attachments' ), 403 );
		}

		// Rate limiting globale per IP: dipende dalle impostazioni globali.
		$settings  = get_option( 'wppa_settings', array() );
		$daily_max = (int) ( $settings['free_view_daily_limit'] ?? self::FREE_VIEW_LIMIT );

		if ( ! RateLimiter::check_and_increment( $ip, 'freeview', $daily_max, self::FREE_VIEW_WINDOW ) ) {
			return $this->error(
				'rate_limited',
				__( 'Limite di visualizzazioni gratuite giornaliero raggiunto.', 'wp-paid-attachments' ),
				429
			);
		}

		// Registra la free view nella tabella wppa_free_views.
		$this->log_free_view( $attachment_id, $ip );

		// Genera token HMAC per view (TTL 5 min) e download (TTL 1h).
		$session_id     = substr( md5( $ip . (string) $attachment_id . (string) time() ), 0, 16 );
		$token          = Hmac::generate( $attachment_id, $session_id, Hmac::TYPE_FREEVIEW, Hmac::TTL_FREEVIEW );
		$download_token = Hmac::generate( $attachment_id, $session_id, Hmac::TYPE_DOWNLOAD, Hmac::TTL_DOWNLOAD );

		return $this->success(
			array(
				'token'          => $token,
				'download_token' => $download_token,
				'expires_in'     => Hmac::TTL_FREEVIEW,
			)
		);
	}

	/**
	 * Registra la visualizzazione gratuita nella tabella wppa_free_views.
	 *
	 * @param int    $attachment_id ID attachment.
	 * @param string $ip            IP client.
	 * @return void
	 */
	private function log_free_view( int $attachment_id, string $ip ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wppa_free_views';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'attachment_id' => $attachment_id,
				'ip_address'    => $ip,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Ottiene l'IP reale del client considerando proxy comuni.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$ip = sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) );
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}

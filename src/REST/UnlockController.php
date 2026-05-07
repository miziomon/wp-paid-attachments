<?php
/**
 * Controller REST per la validazione dei codici di sblocco e il download dei file.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Database\UnlockCodeRepository;
use PaidAttachments\Support\Hmac;
use PaidAttachments\Support\RateLimiter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Gestisce gli endpoint pubblici di sblocco e download.
 *
 * Endpoints esposti:
 *   POST /wppa/v1/unlock             — Valida codice, restituisce token HMAC
 *   POST /wppa/v1/unlock/resume      — Rinnova token da cookie HMAC persistente
 *   GET  /wppa/v1/download/{token}   — Serve il file con token HMAC
 */
final class UnlockController extends RestController {

	/**
	 * Tentativi massimi di sblocco per IP all'ora.
	 */
	const UNLOCK_RATE_LIMIT = 5;

	/**
	 * Finestra rate limiting sblocco (secondi).
	 */
	const UNLOCK_WINDOW = 3600;

	/**
	 * Repository configurazioni attachment.
	 *
	 * @var AttachmentConfigRepository
	 */
	private AttachmentConfigRepository $config_repo;

	/**
	 * Repository codici di sblocco.
	 *
	 * @var UnlockCodeRepository
	 */
	private UnlockCodeRepository $code_repo;

	/**
	 * Costruttore.
	 *
	 * @param AttachmentConfigRepository $config_repo Repository configurazioni.
	 * @param UnlockCodeRepository       $code_repo   Repository codici.
	 */
	public function __construct( AttachmentConfigRepository $config_repo, UnlockCodeRepository $code_repo ) {
		$this->config_repo = $config_repo;
		$this->code_repo   = $code_repo;
	}

	/**
	 * Registra le route REST.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/unlock',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'validate_code' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'code'          => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/unlock/resume',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'resume_from_cookie' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'cookie_token'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/download/(?P<token>[A-Za-z0-9_\-\.]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'serve_file' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * POST /wppa/v1/unlock — Valida il codice e restituisce token HMAC.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function validate_code( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$code          = (string) $request->get_param( 'code' );
		$ip            = $this->get_client_ip();

		// Controlla il codice master superadmin (formato libero, confronto case-insensitive senza separatori).
		$wppa_settings     = get_option( 'wppa_settings', array() );
		$master_code_raw   = sanitize_text_field( $wppa_settings['master_unlock_code'] ?? '' );
		$master_code_clean = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $master_code_raw ) );
		$submitted_clean   = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $code ) );

		if ( '' !== $master_code_clean && $submitted_clean === $master_code_clean ) {
			if ( ! $this->config_repo->is_protected( $attachment_id ) ) {
				return $this->error( 'not_protected', __( 'Attachment non trovato o non protetto.', 'wp-paid-attachments' ), 404 );
			}
			$master_session = bin2hex( random_bytes( 8 ) );
			$view_token     = Hmac::generate( $attachment_id, $master_session, Hmac::TYPE_VIEW, Hmac::TTL_VIEW );
			$download_token = Hmac::generate( $attachment_id, $master_session, Hmac::TYPE_DOWNLOAD, Hmac::TTL_DOWNLOAD );
			$cookie_session = bin2hex( random_bytes( 8 ) );
			$cookie_token   = Hmac::generate( $attachment_id, $cookie_session, Hmac::TYPE_COOKIE, Hmac::TTL_COOKIE );
			return $this->success(
				array(
					'token'          => $view_token,
					'download_token' => $download_token,
					'cookie_token'   => $cookie_token,
					'expires_in'     => Hmac::TTL_VIEW,
				)
			);
		}

		// Rate limiting: max 5 tentativi/ora per IP.
		if ( ! RateLimiter::check_and_increment( $ip, 'unlock', self::UNLOCK_RATE_LIMIT, self::UNLOCK_WINDOW ) ) {
			return $this->error(
				'rate_limited',
				__( 'Troppi tentativi. Riprova tra un\'ora.', 'wp-paid-attachments' ),
				429
			);
		}

		// Verifica che l'attachment esista e sia protetto.
		if ( ! $this->config_repo->is_protected( $attachment_id ) ) {
			return $this->error( 'not_protected', __( 'Attachment non trovato o non protetto.', 'wp-paid-attachments' ), 404 );
		}

		// Normalizza il codice (uppercase, rimuovi caratteri non validi).
		$code = strtoupper( preg_replace( '/[^A-Z0-9-]/i', '', $code ) );

		// Estrai il prefix (i primi 4 char prima del primo trattino).
		$parts  = explode( '-', $code, 2 );
		$prefix = $parts[0] ?? '';

		if ( strlen( $prefix ) < 4 ) {
			return $this->error( 'invalid_code', __( 'Codice non valido.', 'wp-paid-attachments' ), 400 );
		}

		// Cerca candidati per prefix (O(1) atteso: massimo 1 candidato per prefix a 4 char).
		$candidates = $this->code_repo->find_by_prefix( $prefix, $attachment_id );

		foreach ( $candidates as $unlock_code ) {
			if ( ! password_verify( $code, $unlock_code->code_hash ) ) {
				continue;
			}

			if ( ! $unlock_code->is_usable() ) {
				return $this->error( 'code_unusable', __( 'Il codice è scaduto o non più valido.', 'wp-paid-attachments' ), 410 );
			}

			// Registra utilizzo.
			$this->code_repo->record_use( $unlock_code->id, $ip );

			// Azzera il rate limiter dopo un successo.
			RateLimiter::reset( $ip, 'unlock' );

			// Genera token HMAC per view, download e cookie persistente.
			$session_id     = substr( md5( $ip . $unlock_code->id ), 0, 16 );
			$view_token     = Hmac::generate( $attachment_id, $session_id, Hmac::TYPE_VIEW, Hmac::TTL_VIEW );
			$download_token = Hmac::generate( $attachment_id, $session_id, Hmac::TYPE_DOWNLOAD, Hmac::TTL_DOWNLOAD );

			// Cookie session separato (non IP-bound) per riconoscimento cross-network.
			$cookie_session = bin2hex( random_bytes( 8 ) );
			$cookie_token   = Hmac::generate( $attachment_id, $cookie_session, Hmac::TYPE_COOKIE, Hmac::TTL_COOKIE );

			return $this->success(
				array(
					'token'          => $view_token,
					'download_token' => $download_token,
					'cookie_token'   => $cookie_token,
					'expires_in'     => Hmac::TTL_VIEW,
				)
			);
		}

		return $this->error( 'invalid_code', __( 'Codice non valido.', 'wp-paid-attachments' ), 400 );
	}

	/**
	 * POST /wppa/v1/unlock/resume — Rinnova i token da un cookie HMAC persistente.
	 *
	 * Il client invia il cookie_token ricevuto in precedenza; il server verifica
	 * la firma e restituisce nuovi token view/download freschi senza richiedere
	 * di re-inserire il codice.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function resume_from_cookie( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$cookie_token  = (string) $request->get_param( 'cookie_token' );

		$verified = Hmac::verify( $cookie_token, $attachment_id, Hmac::TYPE_COOKIE );
		if ( null === $verified ) {
			return $this->error( 'invalid_token', __( 'Token non valido o scaduto.', 'wp-paid-attachments' ), 403 );
		}

		if ( ! $this->config_repo->is_protected( $attachment_id ) ) {
			return $this->error( 'not_protected', __( 'Attachment non trovato o non protetto.', 'wp-paid-attachments' ), 404 );
		}

		$session_id     = $verified['session_id'];
		$view_token     = Hmac::generate( $attachment_id, $session_id, Hmac::TYPE_VIEW, Hmac::TTL_VIEW );
		$download_token = Hmac::generate( $attachment_id, $session_id, Hmac::TYPE_DOWNLOAD, Hmac::TTL_DOWNLOAD );

		return $this->success(
			array(
				'token'          => $view_token,
				'download_token' => $download_token,
				'expires_in'     => Hmac::TTL_VIEW,
			)
		);
	}

	/**
	 * GET /wppa/v1/download/{token} — Serve il file protetto.
	 *
	 * Verifica il token HMAC, legge il file dal filesystem e lo invia
	 * al client tramite readfile(). Non espone mai il percorso fisico.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function serve_file( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$token_str = (string) $request->get_param( 'token' );

		// Determina il tipo di token dalla query string (?t=view|dl|fv).
		$type = sanitize_key( (string) ( $_GET['t'] ?? Hmac::TYPE_VIEW ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $type, array( Hmac::TYPE_VIEW, Hmac::TYPE_DOWNLOAD, Hmac::TYPE_FREEVIEW ), true ) ) {
			$type = Hmac::TYPE_VIEW;
		}

		// Estrae l'attachment_id dal payload (prima di verificare — non è secret).
		$parts = explode( '.', $token_str, 2 );
		if ( 2 !== count( $parts ) ) {
			return $this->error( 'invalid_token', __( 'Token non valido.', 'wp-paid-attachments' ), 403 );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$payload_raw = base64_decode( strtr( $parts[0], '-_', '+/' ), true );
		if ( false === $payload_raw ) {
			return $this->error( 'invalid_token', __( 'Token non valido.', 'wp-paid-attachments' ), 403 );
		}

		$fields = explode( '|', $payload_raw, 4 );
		if ( count( $fields ) < 1 ) {
			return $this->error( 'invalid_token', __( 'Token non valido.', 'wp-paid-attachments' ), 403 );
		}

		$attachment_id = (int) $fields[0];

		// Ora verifica la firma HMAC con tutti i campi.
		$verified = Hmac::verify( $token_str, $attachment_id, $type );
		if ( null === $verified ) {
			return $this->error( 'invalid_token', __( 'Token non valido o scaduto.', 'wp-paid-attachments' ), 403 );
		}

		// Recupera il percorso fisico del file.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $this->error( 'file_not_found', __( 'File non trovato.', 'wp-paid-attachments' ), 404 );
		}

		$mime     = get_post_mime_type( $attachment_id );
		$filename = basename( $file_path );

		// Invia il file direttamente al client.
		$this->send_file( $file_path, (string) ( $mime ? $mime : 'application/octet-stream' ), $filename, $type );
	}

	/**
	 * Invia il file al client con readfile().
	 *
	 * @param string $path     Percorso assoluto del file.
	 * @param string $mime     MIME type.
	 * @param string $filename Nome file per Content-Disposition.
	 * @param string $type     Tipo token (TYPE_DOWNLOAD aggiunge attachment).
	 * @return never
	 */
	private function send_file( string $path, string $mime, string $filename, string $type ): never {
		// Impedisce SEO indexing anche sulle response file.
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Cache-Control: private, no-store' );

		if ( Hmac::TYPE_DOWNLOAD === $type ) {
			header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		} else {
			header( 'Content-Disposition: inline; filename="' . rawurlencode( $filename ) . '"' );
		}

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
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

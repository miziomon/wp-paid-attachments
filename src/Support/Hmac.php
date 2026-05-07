<?php
/**
 * Firma e verifica di token HMAC per l'accesso ai file protetti.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Support;

/**
 * Utility per la generazione e validazione di token HMAC SHA-256.
 *
 * Formato token (prima della codifica): `attachment_id|session_id|expires|type`
 * Il token trasmesso via URL è: `base64url( payload ) . '.' . base64url( hmac )`
 */
final class Hmac {

	/**
	 * TTL (secondi) per token di tipo view (immagine inline).
	 */
	const TTL_VIEW = 3600;

	/**
	 * TTL (secondi) per token di tipo download (attachment + Content-Disposition).
	 */
	const TTL_DOWNLOAD = 3600;

	/**
	 * TTL (secondi) per token di tipo free-view.
	 */
	const TTL_FREEVIEW = 300;

	/**
	 * TTL (secondi) per cookie di sblocco persistente.
	 */
	const TTL_COOKIE = 86400;

	/**
	 * Tipo token: visualizzazione inline.
	 */
	const TYPE_VIEW = 'view';

	/**
	 * Tipo token: download con Content-Disposition.
	 */
	const TYPE_DOWNLOAD = 'dl';

	/**
	 * Tipo token: free view temporanea.
	 */
	const TYPE_FREEVIEW = 'fv';

	/**
	 * Tipo token: cookie persistente di sblocco (24h).
	 */
	const TYPE_COOKIE = 'ck';

	/**
	 * Genera un token HMAC per l'accesso a un file.
	 *
	 * @param int    $attachment_id ID attachment WP.
	 * @param string $session_id    Identificatore sessione/utente (IP o nonce).
	 * @param string $type          Tipo token: 'view', 'dl', 'fv'.
	 * @param int    $ttl           Durata in secondi.
	 * @return string Token opaco URL-safe.
	 */
	public static function generate( int $attachment_id, string $session_id, string $type, int $ttl ): string {
		$expires = time() + $ttl;
		$payload = implode( '|', array( $attachment_id, $session_id, $expires, $type ) );
		$secret  = self::get_secret();
		$sig     = hash_hmac( 'sha256', $payload, $secret );

		return self::b64url( $payload ) . '.' . self::b64url( $sig );
	}

	/**
	 * Verifica un token HMAC e ne restituisce i dati se valido.
	 *
	 * @param string $token         Token ricevuto dalla request.
	 * @param int    $attachment_id ID attachment atteso.
	 * @param string $type          Tipo token atteso.
	 * @return array{attachment_id: int, session_id: string, expires: int, type: string}|null
	 *         Null se il token non è valido o è scaduto.
	 */
	public static function verify( string $token, int $attachment_id, string $type ): ?array {
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		$payload = self::b64url_decode( $parts[0] );
		$sig     = self::b64url_decode( $parts[1] );

		if ( false === $payload || false === $sig ) {
			return null;
		}

		$secret       = self::get_secret();
		$expected_sig = hash_hmac( 'sha256', $payload, $secret );

		if ( ! hash_equals( $expected_sig, $sig ) ) {
			return null;
		}

		$fields = explode( '|', $payload, 4 );
		if ( 4 !== count( $fields ) ) {
			return null;
		}

		[ $pid, $session_id, $expires, $token_type ] = $fields;

		if ( (int) $pid !== $attachment_id || $token_type !== $type ) {
			return null;
		}

		if ( time() > (int) $expires ) {
			return null;
		}

		return array(
			'attachment_id' => (int) $pid,
			'session_id'    => $session_id,
			'expires'       => (int) $expires,
			'type'          => $token_type,
		);
	}

	/**
	 * Recupera il secret HMAC dall'opzione WordPress.
	 *
	 * @return string Secret a 64 caratteri.
	 */
	private static function get_secret(): string {
		$secret = (string) get_option( 'wppa_secret_key', '' );

		if ( empty( $secret ) ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( 'wppa_secret_key', $secret, true );
		}

		return $secret;
	}

	/**
	 * Codifica base64 URL-safe (senza padding).
	 *
	 * @param string $data Stringa da codificare.
	 * @return string
	 */
	private static function b64url( string $data ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Decodifica base64 URL-safe.
	 *
	 * @param string $data Stringa codificata.
	 * @return string|false False se la stringa non è valida.
	 */
	private static function b64url_decode( string $data ): string|false {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( strtr( $data, '-_', '+/' ), true );
	}
}

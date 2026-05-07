<?php
/**
 * Rate limiting leggero via transient WordPress.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Support;

/**
 * Implementa un contatore a finestra scorrevole usando i transient WP.
 *
 * Ogni IP+action mantiene un contatore integer con TTL pari alla finestra.
 * Non è un sliding window preciso, ma è sufficiente per limitare abusi
 * senza dipendenze esterne (Redis, Memcached).
 */
final class RateLimiter {

	/**
	 * Verifica se l'azione è ancora consentita e incrementa il contatore.
	 *
	 * @param string $ip      Indirizzo IP del client.
	 * @param string $action  Identificatore azione (es. 'unlock', 'freeview').
	 * @param int    $limit   Numero massimo di tentativi nella finestra.
	 * @param int    $window  Finestra in secondi.
	 * @return bool True se l'azione è consentita, false se il limite è superato.
	 */
	public static function check_and_increment( string $ip, string $action, int $limit, int $window ): bool {
		$key     = self::transient_key( $ip, $action );
		$current = (int) get_transient( $key );

		if ( $current >= $limit ) {
			return false;
		}

		if ( 0 === $current ) {
			set_transient( $key, 1, $window );
		} else {
			// Incrementa senza rinnovare il TTL.
			set_transient( $key, $current + 1, $window );
		}

		return true;
	}

	/**
	 * Restituisce il numero di tentativi rimanenti nella finestra corrente.
	 *
	 * @param string $ip     IP client.
	 * @param string $action Identificatore azione.
	 * @param int    $limit  Limite massimo.
	 * @return int Tentativi rimasti (0 se limite raggiunto).
	 */
	public static function remaining( string $ip, string $action, int $limit ): int {
		$key     = self::transient_key( $ip, $action );
		$current = (int) get_transient( $key );
		return max( 0, $limit - $current );
	}

	/**
	 * Azzera il contatore per un IP e un'azione.
	 *
	 * @param string $ip     IP client.
	 * @param string $action Identificatore azione.
	 * @return void
	 */
	public static function reset( string $ip, string $action ): void {
		delete_transient( self::transient_key( $ip, $action ) );
	}

	/**
	 * Genera la chiave transient per la coppia IP+azione.
	 *
	 * @param string $ip     IP client.
	 * @param string $action Identificatore azione.
	 * @return string Chiave transient (max 172 char per WP).
	 */
	private static function transient_key( string $ip, string $action ): string {
		return 'wppa_rl_' . substr( md5( $ip ), 0, 8 ) . '_' . $action;
	}
}

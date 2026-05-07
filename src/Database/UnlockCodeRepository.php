<?php
/**
 * Repository per la tabella wppa_unlock_codes.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Database;

use DateTimeImmutable;
use PaidAttachments\Domain\UnlockCode;

/**
 * Gestisce generazione, ricerca e aggiornamento dei codici di sblocco.
 */
final class UnlockCodeRepository {

	/**
	 * Istanza wpdb.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Inizializza il repository con l'istanza wpdb.
	 *
	 * @param \wpdb $db Istanza globale $wpdb.
	 */
	public function __construct( \wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * Restituisce il nome completo della tabella (con prefisso DB).
	 *
	 * @return string
	 */
	private function table(): string {
		return $this->db->prefix . 'wppa_unlock_codes';
	}

	/**
	 * Trova tutti i candidati con un dato prefix per la validazione bcrypt.
	 *
	 * Il prefix (4 char) restringe i candidati a ~1/280.000 dello spazio,
	 * rendendo il loop bcrypt successivo O(1) nella pratica.
	 *
	 * @param string $prefix        Primi 4 caratteri del codice in chiaro.
	 * @param int    $attachment_id ID attachment WP.
	 * @return UnlockCode[]
	 */
	public function find_by_prefix( string $prefix, int $attachment_id ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT id, attachment_id, code_hash, code_prefix, payment_id,
				        email, expires_at, max_uses, used_count,
				        last_used_at, last_used_ip, revoked, created_at
				 FROM `' . $this->table() . '`
				 WHERE code_prefix = %s
				   AND attachment_id = %d
				   AND revoked = 0
				   AND expires_at > NOW()',
				$prefix,
				$attachment_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn( array $row ) => UnlockCode::from_db_row( $row ),
			$rows
		);
	}

	/**
	 * Trova un codice per ID.
	 *
	 * @param int $id PK della tabella.
	 * @return UnlockCode|null
	 */
	public function find_by_id( int $id ): ?UnlockCode {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT id, attachment_id, code_hash, code_prefix, payment_id,
				        email, expires_at, max_uses, used_count,
				        last_used_at, last_used_ip, revoked, created_at
				 FROM `' . $this->table() . '`
				 WHERE id = %d
				 LIMIT 1',
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? UnlockCode::from_db_row( $row ) : null;
	}

	/**
	 * Inserisce un nuovo codice di sblocco.
	 *
	 * @param int               $attachment_id ID attachment WP.
	 * @param string            $code_hash     Hash bcrypt del codice in chiaro.
	 * @param string            $code_prefix   Primi 4 caratteri in chiaro.
	 * @param string            $email         Email destinataria.
	 * @param DateTimeImmutable $expires_at    Scadenza.
	 * @param int|null          $payment_id    FK pagamento generante.
	 * @param int               $max_uses      Max utilizzi (0 = illimitato).
	 * @return int ID del codice inserito (0 in caso di errore).
	 */
	public function insert_code(
		int $attachment_id,
		string $code_hash,
		string $code_prefix,
		string $email,
		DateTimeImmutable $expires_at,
		?int $payment_id,
		int $max_uses
	): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->db->insert(
			$this->table(),
			array(
				'attachment_id' => $attachment_id,
				'code_hash'     => $code_hash,
				'code_prefix'   => $code_prefix,
				'payment_id'    => $payment_id,
				'email'         => $email,
				'expires_at'    => $expires_at->format( 'Y-m-d H:i:s' ),
				'max_uses'      => $max_uses,
				'used_count'    => 0,
				'revoked'       => 0,
				'created_at'    => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return (int) $this->db->insert_id;
	}

	/**
	 * Registra un utilizzo del codice (incrementa used_count e aggiorna last_used_at/ip).
	 *
	 * @param int    $code_id ID del codice.
	 * @param string $ip      IP del richiedente.
	 * @return bool True se l'update ha modificato almeno una riga.
	 */
	public function record_use( int $code_id, string $ip ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->query(
			$this->db->prepare(
				'UPDATE `' . $this->table() . '`
				 SET used_count = used_count + 1,
				     last_used_at = NOW(),
				     last_used_ip = %s
				 WHERE id = %d',
				$ip,
				$code_id
			)
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Revoca un codice rendendolo inutilizzabile.
	 *
	 * @param int $code_id ID del codice.
	 * @return bool
	 */
	public function revoke( int $code_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->update(
			$this->table(),
			array( 'revoked' => 1 ),
			array( 'id' => $code_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Lega un codice a un pagamento completato.
	 *
	 * @param int $code_id    ID del codice.
	 * @param int $payment_id ID del pagamento.
	 * @return bool
	 */
	public function link_to_payment( int $code_id, int $payment_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->update(
			$this->table(),
			array( 'payment_id' => $payment_id ),
			array( 'id' => $code_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}
}

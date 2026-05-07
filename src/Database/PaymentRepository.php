<?php
/**
 * Repository per la tabella wppa_payments.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Database;

use DateTimeImmutable;
use PaidAttachments\Domain\Payment;

/**
 * Gestisce inserimento e ricerca dei pagamenti ricevuti.
 */
final class PaymentRepository {

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
		return $this->db->prefix . 'wppa_payments';
	}

	/**
	 * Trova un pagamento per ID.
	 *
	 * @param int $id PK della tabella.
	 * @return Payment|null
	 */
	public function find_by_id( int $id ): ?Payment {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT id, attachment_id, provider, provider_transaction_id, amount,
				        currency, status, donor_email, donor_name, unlock_code_id,
				        ip_address, user_agent, metadata, created_at, updated_at
				 FROM `' . $this->table() . '`
				 WHERE id = %d
				 LIMIT 1',
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? Payment::from_db_row( $row ) : null;
	}

	/**
	 * Trova un pagamento per ID transazione del provider (per idempotenza webhook).
	 *
	 * @param string $transaction_id ID univoco PayPal.
	 * @return Payment|null
	 */
	public function find_by_transaction_id( string $transaction_id ): ?Payment {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT id, attachment_id, provider, provider_transaction_id, amount,
				        currency, status, donor_email, donor_name, unlock_code_id,
				        ip_address, user_agent, metadata, created_at, updated_at
				 FROM `' . $this->table() . '`
				 WHERE provider_transaction_id = %s
				 LIMIT 1',
				$transaction_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? Payment::from_db_row( $row ) : null;
	}

	/**
	 * Inserisce un nuovo pagamento.
	 *
	 * @param array<string, mixed> $data Dati del pagamento (campi della tabella).
	 * @return int ID del pagamento inserito (0 in caso di errore).
	 */
	public function insert( array $data ): int {
		$now = ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->db->insert(
			$this->table(),
			array(
				'attachment_id'           => (int) ( $data['attachment_id'] ?? 0 ),
				'provider'                => sanitize_text_field( $data['provider'] ?? '' ),
				'provider_transaction_id' => sanitize_text_field( $data['provider_transaction_id'] ?? '' ),
				'amount'                  => (float) ( $data['amount'] ?? 0 ),
				'currency'                => sanitize_text_field( $data['currency'] ?? 'EUR' ),
				'status'                  => sanitize_text_field( $data['status'] ?? 'pending' ),
				'donor_email'             => sanitize_email( $data['donor_email'] ?? '' ),
				'donor_name'              => ! empty( $data['donor_name'] ) ? sanitize_text_field( $data['donor_name'] ) : null,
				'unlock_code_id'          => ! empty( $data['unlock_code_id'] ) ? (int) $data['unlock_code_id'] : null,
				'ip_address'              => sanitize_text_field( $data['ip_address'] ?? '' ),
				'user_agent'              => ! empty( $data['user_agent'] ) ? sanitize_text_field( substr( $data['user_agent'], 0, 500 ) ) : null,
				'metadata'                => ! empty( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
				'created_at'              => $now,
				'updated_at'              => $now,
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $this->db->insert_id;
	}

	/**
	 * Aggiorna lo status di un pagamento e opzionalmente lega il codice di sblocco.
	 *
	 * @param int      $id             ID del pagamento.
	 * @param string   $status         Nuovo status ('completed'|'failed'|'refunded').
	 * @param int|null $unlock_code_id ID del codice generato (null per status non 'completed').
	 * @return bool True se l'update ha modificato la riga.
	 */
	public function update_status( int $id, string $status, ?int $unlock_code_id = null ): bool {
		$update_data   = array(
			'status'     => sanitize_text_field( $status ),
			'updated_at' => ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
		);
		$update_format = array( '%s', '%s' );

		if ( null !== $unlock_code_id ) {
			$update_data['unlock_code_id'] = $unlock_code_id;
			$update_format[]               = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->update(
			$this->table(),
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Recupera i pagamenti per un attachment con paginazione.
	 *
	 * @param int $attachment_id ID attachment WP.
	 * @param int $per_page      Righe per pagina.
	 * @param int $page          Pagina corrente (base 1).
	 * @return Payment[]
	 */
	public function find_by_attachment_id( int $attachment_id, int $per_page = 20, int $page = 1 ): array {
		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT id, attachment_id, provider, provider_transaction_id, amount,
				        currency, status, donor_email, donor_name, unlock_code_id,
				        ip_address, user_agent, metadata, created_at, updated_at
				 FROM `' . $this->table() . '`
				 WHERE attachment_id = %d
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d',
				$attachment_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn( array $row ) => Payment::from_db_row( $row ),
			$rows
		);
	}
}

<?php
/**
 * Repository per la tabella wppa_attachment_config.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Database;

use DateTimeImmutable;
use PaidAttachments\Domain\AttachmentConfig;

/**
 * Gestisce le operazioni CRUD sulla tabella di configurazione attachment.
 *
 * Tutte le query dirette su tabelle custom sono intenzionalmente non cacheate
 * a livello di oggetto: il caching delle statistiche aggregate è delegato a
 * StatsRepository + transient. Le query semplici per-attachment sono veloci
 * per indice e non necessitano di strato cache aggiuntivo.
 */
final class AttachmentConfigRepository {

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
		return $this->db->prefix . 'wppa_attachment_config';
	}

	/**
	 * Cerca la configurazione per un attachment specifico.
	 *
	 * @param int $attachment_id ID attachment WP.
	 * @return AttachmentConfig|null Null se non esiste una riga per questo attachment.
	 */
	public function find_by_attachment_id( int $attachment_id ): ?AttachmentConfig {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT id, attachment_id, enabled, payment_mode, suggested_amounts,
				        min_amount, currency, code_validity_days, code_max_uses,
				        custom_text, custom_email_subject, custom_email_body,
				        allow_free_view, created_at, updated_at
				 FROM `' . $this->table() . '`
				 WHERE attachment_id = %d
				 LIMIT 1',
				$attachment_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return AttachmentConfig::from_db_row( $row );
	}

	/**
	 * Verifica se un attachment è attivamente protetto.
	 *
	 * @param int $attachment_id ID attachment WP.
	 * @return bool
	 */
	public function is_protected( int $attachment_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->get_var(
			$this->db->prepare(
				'SELECT enabled FROM `' . $this->table() . '` WHERE attachment_id = %d LIMIT 1',
				$attachment_id
			)
		);

		return '1' === (string) $result;
	}

	/**
	 * Restituisce tutte le configurazioni attive con paginazione.
	 *
	 * @param int $per_page Righe per pagina.
	 * @param int $page     Pagina corrente (base 1).
	 * @return AttachmentConfig[]
	 */
	public function find_all( int $per_page = 20, int $page = 1 ): array {
		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT id, attachment_id, enabled, payment_mode, suggested_amounts,
				        min_amount, currency, code_validity_days, code_max_uses,
				        custom_text, custom_email_subject, custom_email_body,
				        allow_free_view, created_at, updated_at
				 FROM `' . $this->table() . '`
				 ORDER BY updated_at DESC
				 LIMIT %d OFFSET %d',
				$per_page,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn( array $row ) => AttachmentConfig::from_db_row( $row ),
			$rows
		);
	}

	/**
	 * Conta il totale delle configurazioni salvate.
	 *
	 * @return int
	 */
	public function count(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM `' . $this->table() . '`' );
	}

	/**
	 * Inserisce o aggiorna la configurazione per un attachment.
	 *
	 * Usa INSERT … ON DUPLICATE KEY UPDATE per gestire l'upsert atomicamente.
	 *
	 * @param int                  $attachment_id ID attachment WP.
	 * @param array<string, mixed> $data          Campi da salvare (parziale o completo).
	 * @return int ID della riga inserita/aggiornata (0 in caso di errore).
	 */
	public function upsert( int $attachment_id, array $data ): int {
		$now     = ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
		$amounts = ! empty( $data['suggested_amounts'] ) ? wp_json_encode( $data['suggested_amounts'] ) : wp_json_encode( array( 1, 3, 5 ) );

		$fields = array(
			'attachment_id'        => $attachment_id,
			'enabled'              => isset( $data['enabled'] ) ? (int) $data['enabled'] : 1,
			'payment_mode'         => sanitize_text_field( $data['payment_mode'] ?? 'paypal_donate' ),
			'suggested_amounts'    => $amounts,
			'min_amount'           => (float) ( $data['min_amount'] ?? 1.00 ),
			'currency'             => sanitize_text_field( $data['currency'] ?? 'EUR' ),
			'code_validity_days'   => (int) ( $data['code_validity_days'] ?? 30 ),
			'code_max_uses'        => (int) ( $data['code_max_uses'] ?? 0 ),
			'custom_text'          => wp_kses_post( $data['custom_text'] ?? '' ),
			'custom_email_subject' => ! empty( $data['custom_email_subject'] ) ? sanitize_text_field( $data['custom_email_subject'] ) : null,
			'custom_email_body'    => ! empty( $data['custom_email_body'] ) ? wp_kses_post( $data['custom_email_body'] ) : null,
			'allow_free_view'      => isset( $data['allow_free_view'] ) ? (int) $data['allow_free_view'] : 1,
			'updated_at'           => $now,
		);

		// Verifica se esiste già una riga per questo attachment.
		$existing = $this->find_by_attachment_id( $attachment_id );

		if ( null === $existing ) {
			$fields['created_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->db->insert( $this->table(), $fields );
			return (int) $this->db->insert_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db->update(
			$this->table(),
			$fields,
			array( 'attachment_id' => $attachment_id )
		);

		return $existing->id;
	}

	/**
	 * Rimuove la configurazione per un attachment.
	 *
	 * @param int $attachment_id ID attachment WP.
	 * @return bool True se almeno una riga è stata eliminata.
	 */
	public function delete( int $attachment_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->delete(
			$this->table(),
			array( 'attachment_id' => $attachment_id ),
			array( '%d' )
		);

		return false !== $result && $result > 0;
	}
}

<?php
/**
 * Value object che rappresenta un pagamento ricevuto.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Domain;

use DateTimeImmutable;

/**
 * Rappresenta una riga della tabella wppa_payments.
 */
final class Payment {

	/**
	 * Costruisce il value object con tutti i campi di wppa_payments.
	 *
	 * @param int               $id                     PK tabella.
	 * @param int               $attachment_id          ID attachment WP.
	 * @param string            $provider               'paypal_donate' | 'paypal_smart'.
	 * @param string            $provider_transaction_id ID univoco del provider.
	 * @param float             $amount                 Importo donato.
	 * @param string            $currency               Codice valuta ISO 4217.
	 * @param string            $status                 'pending'|'completed'|'failed'|'refunded'.
	 * @param string            $donor_email            Email del donatore.
	 * @param string|null       $donor_name             Nome del donatore (opzionale).
	 * @param int|null          $unlock_code_id         FK codice generato (null finché pending).
	 * @param string            $ip_address             IP del donatore.
	 * @param string|null       $user_agent             User-agent del browser.
	 * @param array<mixed>      $metadata               Payload PayPal completo.
	 * @param DateTimeImmutable $created_at             Data creazione.
	 * @param DateTimeImmutable $updated_at             Data ultimo aggiornamento.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $attachment_id,
		public readonly string $provider,
		public readonly string $provider_transaction_id,
		public readonly float $amount,
		public readonly string $currency,
		public readonly string $status,
		public readonly string $donor_email,
		public readonly ?string $donor_name,
		public readonly ?int $unlock_code_id,
		public readonly string $ip_address,
		public readonly ?string $user_agent,
		public readonly array $metadata,
		public readonly DateTimeImmutable $created_at,
		public readonly DateTimeImmutable $updated_at,
	) {}

	/**
	 * Costruisce l'oggetto da una riga associativa del database.
	 *
	 * Il campo `metadata` è salvato come JSON nel DB.
	 *
	 * @param array<string, mixed> $row Riga da $wpdb->get_row() con ARRAY_A.
	 * @return self
	 */
	public static function from_db_row( array $row ): self {
		$metadata_raw = ! empty( $row['metadata'] ) ? json_decode( (string) $row['metadata'], true ) : array();
		$metadata     = is_array( $metadata_raw ) ? $metadata_raw : array();

		return new self(
			id: (int) $row['id'],
			attachment_id: (int) $row['attachment_id'],
			provider: (string) $row['provider'],
			provider_transaction_id: (string) $row['provider_transaction_id'],
			amount: (float) $row['amount'],
			currency: (string) $row['currency'],
			status: (string) $row['status'],
			donor_email: (string) $row['donor_email'],
			donor_name: isset( $row['donor_name'] ) ? (string) $row['donor_name'] : null,
			unlock_code_id: isset( $row['unlock_code_id'] ) ? (int) $row['unlock_code_id'] : null,
			ip_address: (string) $row['ip_address'],
			user_agent: isset( $row['user_agent'] ) ? (string) $row['user_agent'] : null,
			metadata: $metadata,
			created_at: new DateTimeImmutable( (string) $row['created_at'] ),
			updated_at: new DateTimeImmutable( (string) $row['updated_at'] ),
		);
	}

	/**
	 * Indica se il pagamento è confermato dal provider.
	 *
	 * @return bool
	 */
	public function is_completed(): bool {
		return 'completed' === $this->status;
	}

	/**
	 * Indica se il pagamento è in attesa di conferma webhook.
	 *
	 * @return bool
	 */
	public function is_pending(): bool {
		return 'pending' === $this->status;
	}
}

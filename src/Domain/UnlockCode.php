<?php
/**
 * Value object che rappresenta un codice di sblocco.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Domain;

use DateTimeImmutable;

/**
 * Rappresenta una riga della tabella wppa_unlock_codes.
 *
 * Immutabile per design: ogni modifica allo stato produce una nuova istanza.
 * La persistenza è delegata a UnlockCodeRepository.
 */
final class UnlockCode {

	/**
	 * Costruisce il value object con tutti i campi della tabella wppa_unlock_codes.
	 *
	 * @param int                    $id             PK della tabella.
	 * @param int                    $attachment_id  ID attachment WP protetto.
	 * @param string                 $code_hash      Hash bcrypt del codice in chiaro.
	 * @param string                 $code_prefix    Primi 4 caratteri in chiaro (per lookup).
	 * @param int|null               $payment_id     FK pagamento generante (null = codice manuale).
	 * @param string                 $email          Email a cui il codice è stato inviato.
	 * @param DateTimeImmutable      $expires_at     Scadenza del codice.
	 * @param int                    $max_uses       Numero massimo utilizzi (0 = illimitato).
	 * @param int                    $used_count     Contatore utilizzi effettuati.
	 * @param DateTimeImmutable|null $last_used_at Ultimo utilizzo.
	 * @param string|null            $last_used_ip   IP dell'ultimo utilizzo.
	 * @param bool                   $revoked        True se il codice è stato revocato.
	 * @param DateTimeImmutable      $created_at     Data di creazione.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $attachment_id,
		public readonly string $code_hash,
		public readonly string $code_prefix,
		public readonly ?int $payment_id,
		public readonly string $email,
		public readonly DateTimeImmutable $expires_at,
		public readonly int $max_uses,
		public readonly int $used_count,
		public readonly ?DateTimeImmutable $last_used_at,
		public readonly ?string $last_used_ip,
		public readonly bool $revoked,
		public readonly DateTimeImmutable $created_at,
	) {}

	/**
	 * Costruisce l'oggetto da una riga associativa del database.
	 *
	 * @param array<string, mixed> $row Riga da $wpdb->get_row() con ARRAY_A.
	 * @return self
	 */
	public static function from_db_row( array $row ): self {
		return new self(
			id: (int) $row['id'],
			attachment_id: (int) $row['attachment_id'],
			code_hash: (string) $row['code_hash'],
			code_prefix: (string) $row['code_prefix'],
			payment_id: isset( $row['payment_id'] ) ? (int) $row['payment_id'] : null,
			email: (string) $row['email'],
			expires_at: new DateTimeImmutable( (string) $row['expires_at'] ),
			max_uses: (int) $row['max_uses'],
			used_count: (int) $row['used_count'],
			last_used_at: ! empty( $row['last_used_at'] ) ? new DateTimeImmutable( (string) $row['last_used_at'] ) : null,
			last_used_ip: isset( $row['last_used_ip'] ) ? (string) $row['last_used_ip'] : null,
			revoked: (bool) $row['revoked'],
			created_at: new DateTimeImmutable( (string) $row['created_at'] ),
		);
	}

	/**
	 * Indica se il codice è scaduto.
	 *
	 * @return bool
	 */
	public function is_expired(): bool {
		return $this->expires_at < new DateTimeImmutable();
	}

	/**
	 * Indica se il codice ha esaurito gli utilizzi consentiti.
	 * max_uses = 0 significa illimitato.
	 *
	 * @return bool
	 */
	public function is_exhausted(): bool {
		return $this->max_uses > 0 && $this->used_count >= $this->max_uses;
	}

	/**
	 * Indica se il codice è utilizzabile (non scaduto, non esaurito, non revocato).
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return ! $this->revoked && ! $this->is_expired() && ! $this->is_exhausted();
	}
}

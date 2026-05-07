<?php
/**
 * Value object per i token HMAC di visualizzazione/download.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Domain;

use DateTimeImmutable;

/**
 * Rappresenta un token firmato HMAC per l'accesso temporaneo a un file protetto.
 *
 * Non è persistito in DB: viene generato on-the-fly da Hmac::sign() e verificato
 * da Hmac::verify(). Il payload è: attachment_id|session_id|expires_at|type.
 */
final class ViewToken {

	/** Token per la visualizzazione in-page dell'immagine. */
	const TYPE_VIEW = 'view';

	/** Token per il download diretto del file originale. */
	const TYPE_DOWNLOAD = 'download';

	/** Token per la visualizzazione gratuita (TTL ridotto). */
	const TYPE_FREEVIEW = 'freeview';

	/**
	 * Costruisce il token HMAC con i dati necessari per il payload.
	 *
	 * @param int               $attachment_id ID attachment WP.
	 * @param string            $session_id    Identificatore di sessione del richiedente.
	 * @param DateTimeImmutable $expires_at    Scadenza del token.
	 * @param string            $type          Tipo di accesso (TYPE_* constants).
	 */
	public function __construct(
		public readonly int $attachment_id,
		public readonly string $session_id,
		public readonly DateTimeImmutable $expires_at,
		public readonly string $type,
	) {}

	/**
	 * Costruisce il payload grezzo da firmare con HMAC.
	 *
	 * Formato: attachment_id|session_id|expires_timestamp|type
	 *
	 * @return string
	 */
	public function get_payload(): string {
		return implode(
			'|',
			array(
				$this->attachment_id,
				$this->session_id,
				$this->expires_at->getTimestamp(),
				$this->type,
			)
		);
	}

	/**
	 * Indica se il token è scaduto.
	 *
	 * @return bool
	 */
	public function is_expired(): bool {
		return $this->expires_at < new DateTimeImmutable();
	}

	/**
	 * Costruisce il token dal payload grezzo (parsing inverso di get_payload()).
	 *
	 * @param string $payload Payload nel formato attachment_id|session_id|timestamp|type.
	 * @return self|null Null se il payload è malformato.
	 */
	public static function from_payload( string $payload ): ?self {
		$parts = explode( '|', $payload, 4 );

		if ( count( $parts ) !== 4 ) {
			return null;
		}

		[ $attachment_id, $session_id, $expires_ts, $type ] = $parts;

		if ( ! is_numeric( $attachment_id ) || ! is_numeric( $expires_ts ) ) {
			return null;
		}

		$allowed_types = array( self::TYPE_VIEW, self::TYPE_DOWNLOAD, self::TYPE_FREEVIEW );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return null;
		}

		$expires_at = ( new DateTimeImmutable() )->setTimestamp( (int) $expires_ts );

		return new self(
			attachment_id: (int) $attachment_id,
			session_id: (string) $session_id,
			expires_at: $expires_at,
			type: (string) $type,
		);
	}
}

<?php
/**
 * Value object che rappresenta la configurazione di un attachment protetto.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Domain;

use DateTimeImmutable;

/**
 * Configurazione paywall per un singolo attachment.
 *
 * Può rappresentare una riga di wppa_attachment_config oppure il risultato
 * del merge tra configurazione per-attachment e default globali
 * (pattern config-cascade, implementato in Slice 4).
 */
final class AttachmentConfig {

	/**
	 * Costruisce il value object con tutti i campi di wppa_attachment_config.
	 *
	 * @param int                    $id                    PK tabella (0 se generata dai default).
	 * @param int                    $attachment_id         ID attachment WP.
	 * @param bool                   $enabled               Protezione attiva.
	 * @param string                 $payment_mode          'paypal_donate' | 'paypal_smart' | 'both'.
	 * @param array<int|float>       $suggested_amounts     Importi suggeriti come pillole.
	 * @param float                  $min_amount            Importo minimo accettato.
	 * @param string                 $currency              Codice valuta ISO 4217.
	 * @param int                    $code_validity_days    Giorni di validità del codice.
	 * @param int                    $code_max_uses         Max utilizzi (0 = illimitato).
	 * @param string                 $custom_text           Testo HTML del paywall.
	 * @param string|null            $custom_email_subject  Oggetto email personalizzato.
	 * @param string|null            $custom_email_body     Body email personalizzato.
	 * @param bool                   $allow_free_view       Abilita bypass gratuito per-sessione.
	 * @param DateTimeImmutable|null $created_at        Data creazione (null se da default).
	 * @param DateTimeImmutable|null $updated_at        Data ultimo aggiornamento.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $attachment_id,
		public readonly bool $enabled,
		public readonly string $payment_mode,
		public readonly array $suggested_amounts,
		public readonly float $min_amount,
		public readonly string $currency,
		public readonly int $code_validity_days,
		public readonly int $code_max_uses,
		public readonly string $custom_text,
		public readonly ?string $custom_email_subject,
		public readonly ?string $custom_email_body,
		public readonly bool $allow_free_view,
		public readonly ?DateTimeImmutable $created_at,
		public readonly ?DateTimeImmutable $updated_at,
	) {}

	/**
	 * Costruisce l'oggetto da una riga associativa del database.
	 *
	 * Il campo `suggested_amounts` è salvato come JSON nel DB.
	 *
	 * @param array<string, mixed> $row Riga da $wpdb->get_row() con ARRAY_A.
	 * @return self
	 */
	public static function from_db_row( array $row ): self {
		$amounts_raw = ! empty( $row['suggested_amounts'] ) ? json_decode( (string) $row['suggested_amounts'], true ) : array();
		$amounts     = is_array( $amounts_raw ) ? $amounts_raw : array();

		return new self(
			id: (int) $row['id'],
			attachment_id: (int) $row['attachment_id'],
			enabled: (bool) $row['enabled'],
			payment_mode: (string) $row['payment_mode'],
			suggested_amounts: $amounts,
			min_amount: (float) $row['min_amount'],
			currency: (string) $row['currency'],
			code_validity_days: (int) $row['code_validity_days'],
			code_max_uses: (int) $row['code_max_uses'],
			custom_text: (string) ( $row['custom_text'] ?? '' ),
			custom_email_subject: isset( $row['custom_email_subject'] ) ? (string) $row['custom_email_subject'] : null,
			custom_email_body: isset( $row['custom_email_body'] ) ? (string) $row['custom_email_body'] : null,
			allow_free_view: (bool) $row['allow_free_view'],
			created_at: ! empty( $row['created_at'] ) ? new DateTimeImmutable( (string) $row['created_at'] ) : null,
			updated_at: ! empty( $row['updated_at'] ) ? new DateTimeImmutable( (string) $row['updated_at'] ) : null,
		);
	}

	/**
	 * Costruisce una configurazione dai default globali salvati nelle opzioni WP.
	 *
	 * @param int $attachment_id ID attachment WP.
	 * @return self
	 */
	public static function from_global_defaults( int $attachment_id ): self {
		$settings = get_option( 'wppa_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		return new self(
			id: 0,
			attachment_id: $attachment_id,
			enabled: false,
			payment_mode: (string) ( $settings['default_payment_mode'] ?? 'paypal_donate' ),
			suggested_amounts: (array) ( $settings['default_suggested_amounts'] ?? array( 1, 3, 5 ) ),
			min_amount: (float) ( $settings['default_min_amount'] ?? 1.00 ),
			currency: (string) ( $settings['default_currency'] ?? 'EUR' ),
			code_validity_days: (int) ( $settings['default_code_validity_days'] ?? 30 ),
			code_max_uses: (int) ( $settings['default_code_max_uses'] ?? 0 ),
			custom_text: (string) ( $settings['default_custom_text'] ?? '' ),
			custom_email_subject: $settings['default_email_subject'] ?? null,
			custom_email_body: $settings['default_email_body'] ?? null,
			allow_free_view: (bool) ( $settings['default_allow_free_view'] ?? true ),
			created_at: null,
			updated_at: null,
		);
	}
}
